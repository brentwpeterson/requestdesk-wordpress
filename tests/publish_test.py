"""Publishing-flow test for the RequestDesk Connector on wprequestdesk.local with Yoast active.

Usage: publish_test.py <admin_pw_file> <api_key> <state_json_out>
Everything goes through the real paths: wp-admin settings form, the connector's REST API,
and the rendered pages. Writes created ids to <state_json_out> for cleanup.
"""
import http.cookiejar
import json
import random
import re
import sys
import urllib.parse
import urllib.request
from html.parser import HTMLParser

BASE = "http://wprequestdesk.local"
pw = open(sys.argv[1]).read().strip()
KEY = sys.argv[2]
STATE = sys.argv[3]
state = {"post_ids": [], "results": []}
fails = 0


def check(name, ok, detail=""):
    global fails
    fails += 0 if ok else 1
    line = f"{'PASS' if ok else 'FAIL'} {name}" + (f" :: {detail}" if detail else "")
    print(line)
    state["results"].append(line)


class Form(HTMLParser):
    def __init__(self):
        super().__init__()
        self.f = []
        self.sel = None

    def handle_starttag(self, t, a):
        a = dict(a)
        if t == "input" and a.get("name"):
            ty = a.get("type", "text")
            if ty in ("checkbox", "radio"):
                if "checked" in a:
                    self.f.append((a["name"], a.get("value", "on")))
            elif ty != "submit" or a["name"] == "requestdesk_save_settings":
                self.f.append((a["name"], a.get("value", "")))
        if t == "select":
            self.sel = a.get("name")
        if t == "option" and self.sel and "selected" in a:
            self.f.append((self.sel, a.get("value", "")))

    def handle_endtag(self, t):
        if t == "select":
            self.sel = None


cj = http.cookiejar.CookieJar()
op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
op.open(BASE + "/wp-login.php").read()
op.open(BASE + "/wp-login.php", urllib.parse.urlencode({
    "log": "hodgkin-test", "pwd": pw, "wp-submit": "Log In",
    "redirect_to": BASE + "/wp-admin/", "testcookie": "1"}).encode()).read()

# 1. Save the API key through the real settings form.
url = BASE + "/wp-admin/admin.php?page=requestdesk-settings"
h = op.open(url).read().decode("utf8", "replace")
i = h.find('name="requestdesk_nonce"')
form = h[h.rfind("<form", 0, i):h.find("</form>", i)]
p = Form()
p.feed(form)
fields = [x for x in p.f if x[0] != "api_key"] + [("api_key", KEY)]
if not any(k == "default_post_status" for k, _ in fields):
    fields.append(("default_post_status", "draft"))
for k in ("claude_api_key", "claude_model", "requestdesk_endpoint"):
    if not any(x == k for x, _ in fields):
        fields.append((k, ""))
r = op.open(url, urllib.parse.urlencode(fields).encode()).read().decode("utf8", "replace")
check("settings form saves API key", KEY in r)


def api(method, path, body=None, key=KEY):
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(BASE + "/wp-json/requestdesk/v1" + path, data=data, method=method,
                                 headers={"Content-Type": "application/json", "X-RequestDesk-API-Key": key})
    try:
        with urllib.request.urlopen(req, timeout=120) as resp:
            return resp.status, json.loads(resp.read().decode() or "{}")
    except urllib.error.HTTPError as e:
        txt = e.read().decode("utf8", "replace")
        try:
            return e.code, json.loads(txt)
        except Exception:
            return e.code, {"raw": txt[:300]}


# 2. Auth.
c, j = api("POST", "/test-connection", {})
check("test-connection with key", c == 200, f"{c} {str(j)[:120]}")
c, j = api("POST", "/test-connection", {}, key="wrong-key")
check("test-connection rejects wrong key", c in (401, 403), str(c))

# 3. Publish.
content = """<p>Blended families take time. This test post walks through what new stepdads ask most in their first year.</p>
<h2>How long does it take to bond with stepkids?</h2>
<p>Most families describe a period of years rather than months. Consistency matters more than big gestures.</p>
<h2>Should a stepdad discipline?</h2>
<p>Early on, the biological parent usually leads discipline while the stepdad builds the relationship.</p>
<h2>What helps the most?</h2>
<p>Showing up on ordinary days, keeping promises, and talking with your partner before stepping in.</p>"""
img = "https://upload.wikimedia.org/wikipedia/commons/4/47/PNG_transparency_demonstration_1.png"
c, j = api("POST", "/publish", {
    "title": "RDTEST Stepdad First Year Questions", "content": content, "status": "publish",
    "excerpt": "What new stepdads ask most in their first year, answered.",
    "categories": ["RDTEST Stepdad Resources"], "tags": ["rdtest-tag"],
    "featured_image": img, "author": 1})
pid = j.get("post_id") if isinstance(j, dict) else None
check("publish returns 200 + post_id", c == 200 and bool(pid), f"{c} {str(j)[:300]}")
if not pid:
    json.dump(state, open(STATE, "w"))
    print(f"{fails} failures")
    sys.exit(1)
state["post_ids"].append(int(pid))
json.dump(state, open(STATE, "w"))
print("publish response:", json.dumps(j)[:600])
