"""Second half of the publishing-flow test: FAQ, page output, update, admin screens.

Usage: publish_verify.py <admin_pw_file> <api_key> <state_json>
"""
import http.cookiejar
import json
import random
import re
import sys
import urllib.parse
import urllib.request

BASE = "http://wprequestdesk.local"
pw = open(sys.argv[1]).read().strip()
KEY = sys.argv[2]
STATE = sys.argv[3]
state = json.load(open(STATE))
pid = state["post_ids"][0]
fails = 0


def check(name, ok, detail=""):
    global fails
    fails += 0 if ok else 1
    line = f"{'PASS' if ok else 'FAIL'} {name}" + (f" :: {detail}" if detail else "")
    print(line)
    state["results"].append(line)


def api(method, path, body=None):
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(BASE + "/wp-json/requestdesk/v1" + path, data=data, method=method,
                                 headers={"Content-Type": "application/json", "X-RequestDesk-API-Key": KEY})
    try:
        with urllib.request.urlopen(req, timeout=120) as resp:
            return resp.status, json.loads(resp.read().decode() or "{}")
    except urllib.error.HTTPError as e:
        txt = e.read().decode("utf8", "replace")
        try:
            return e.code, json.loads(txt)
        except Exception:
            return e.code, {"raw": txt[:300]}


def page(url):
    with urllib.request.urlopen(url + ("&" if "?" in url else "?") + "cb=%d" % random.randint(1, 10**9)) as r:
        return r.status, r.read().decode("utf8", "replace")


def ld(h):
    out = []
    for b in re.findall(r'<script[^>]*application/ld\+json[^>]*>(.*?)</script>', h, re.S):
        d = json.loads(b)
        out.append(d.get("@graph", [d]))
    return out


def count(h, pat):
    return len(re.findall(pat, h, re.I))


# Post URL from WordPress core REST (read).
with urllib.request.urlopen(f"{BASE}/wp-json/wp/v2/posts/{pid}") as r:
    wp = json.loads(r.read().decode())
url = wp["link"]
check("post is published", wp.get("status") == "publish", wp.get("status"))
check("featured image attached", wp.get("featured_media", 0) > 0, str(wp.get("featured_media")))
state["media_ids"] = [wp.get("featured_media")] if wp.get("featured_media") else []
state["category_ids"] = wp.get("categories", [])
state["tag_ids"] = wp.get("tags", [])
check("category assigned (not Uncategorized only)", any(c != 1 for c in wp.get("categories", [])), str(wp.get("categories")))
check("tag assigned", len(wp.get("tags", [])) == 1, str(wp.get("tags")))

# FAQ through the AEO endpoint, the same way Content Cucumber publishing does it.
pairs = [
    {"question": "How long does it take to bond with stepkids?", "answer": "Usually years rather than months; consistency matters most."},
    {"question": "Should a stepdad discipline?", "answer": "Early on, the biological parent usually leads discipline."},
    {"question": "What helps the most?", "answer": "Showing up on ordinary days and keeping promises."},
]
c, j = api("POST", f"/aeo-qa/{pid}", {"post_id": pid, "qa_pairs": pairs, "mode": "replace"})
check("aeo-qa replace returns 200", c == 200, f"{c} {str(j)[:200]}")

code, h = page(url)
graphs = ld(h)
check("post page 200", code == 200, str(code))
check("exactly one ld+json block", len(graphs) == 1, str(len(graphs)))
nodes = [n for g in graphs for n in g]
faq = [n for n in nodes if n.get("@type") == "FAQPage"]
check("one FAQPage node", len(faq) == 1, str(len(faq)))
if faq:
    check("FAQPage id is #requestdesk-faq", str(faq[0].get("@id", "")).endswith("#requestdesk-faq"), faq[0].get("@id"))
    check("FAQPage has the 3 questions", len(faq[0].get("mainEntity", [])) == 3, str(len(faq[0].get("mainEntity", []))))
    check("FAQPage linked to Yoast WebPage", bool(faq[0].get("isPartOf", {}).get("@id")), str(faq[0].get("isPartOf")))
check("no ProfessionalService on a plain site", not any("ProfessionalService" in str(n.get("@type")) for n in nodes))
for label, pat in [("title", r"<title"), ("meta description", r'<meta name="description"'), ("canonical", r'rel="canonical"'),
                   ("robots", r"name=.robots."), ("og:title", r'property="og:title"'), ("og:image", r'property="og:image"')]:
    n = count(h, pat)
    check(f"{label} printed once", n == 1, str(n))
check("meta description is the excerpt", "What new stepdads ask most" in h)
check("page has no PHP error text", not re.search(r"(Fatal error|Warning:|Notice:|critical error)", re.sub(r"<script.*?</script>", "", h, flags=re.S)))

# Update through /publish with post_id as a string.
c, j = api("POST", "/publish", {"post_id": str(pid), "title": "RDTEST Stepdad First Year Questions (Updated)",
                                "content": "<p>Updated body for the update test.</p><h2>Still true?</h2><p>Yes.</p>",
                                "status": "publish"})
check("update returns 200/201 same post_id", c in (200, 201) and str(j.get("post_id")) == str(pid), f"{c} {str(j)[:200]}")
with urllib.request.urlopen(f"{BASE}/wp-json/wp/v2/posts/{pid}?cb={random.randint(1, 10**9)}") as r:
    wp2 = json.loads(r.read().decode())
check("update changed title", "(Updated)" in wp2["title"]["rendered"], wp2["title"]["rendered"])
check("update kept URL", wp2["link"] == url, wp2["link"])
with urllib.request.urlopen(f"{BASE}/wp-json/wp/v2/posts?search=RDTEST&status=publish&cb={random.randint(1, 10**9)}") as r:
    found = json.loads(r.read().decode())
check("update created no duplicate post", len(found) == 1, str([f["id"] for f in found]))
code, h2 = page(url)
faq2 = [n for g in ld(h2) for n in g if n.get("@type") == "FAQPage"]
check("after update: still one ld+json and one FAQPage", len(ld(h2)) == 1 and len(faq2) == 1, f"{len(ld(h2))} / {len(faq2)}")
if faq2:
    q = len(faq2[0].get("mainEntity", []))
    check("after update: FAQ question count", q == 3, f"{q} (CC note: republish can append an auto pair)")

# Admin: edit screen with Yoast + connector boxes, logged in.
cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
op.open(BASE + "/wp-login.php").read()
op.open(BASE + "/wp-login.php", urllib.parse.urlencode({
    "log": "hodgkin-test", "pwd": pw, "wp-submit": "Log In",
    "redirect_to": BASE + "/wp-admin/", "testcookie": "1"}).encode()).read()
eh = op.open(f"{BASE}/wp-admin/post.php?post={pid}&action=edit").read().decode("utf8", "replace")
check("edit screen loads logged in", "wp-admin-bar" in eh)
check("Yoast editor present on edit screen", "wpseo" in eh.lower())
check("edit screen no PHP error text", not re.search(r"(Fatal error|<b>Warning</b>|<b>Notice</b>|critical error)", eh))

# Yoast still indexes the post (its sitemap lists it).
code, sm = page(BASE + "/post-sitemap.xml")
check("post listed in Yoast post-sitemap", url in sm)

json.dump(state, open(STATE, "w"))
print(f"{fails} failures")
sys.exit(1 if fails else 0)
