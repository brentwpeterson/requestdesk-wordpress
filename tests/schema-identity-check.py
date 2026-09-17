#!/usr/bin/env python3
"""schema-identity-check: read a site's JSON-LD the way an answer engine does.

Catches the identity defects a third-party AEO reviewer finds by hand:
  - the same @id defined by more than one node on a page (two Organizations
    both claiming #organization)
  - a singleton type declared more than once on a page (two WebSite, two
    FAQPage)
  - JSON-LD that does not parse
  - same-site @id references that point at nothing on the page
  - the brand describing itself differently across nodes and pages
    (Organization / WebSite / ProfessionalService name + description), printed
    next to the homepage meta description and H1 so the drift is visible

Usage:
  schema-identity-check.py https://contentcucumber.com [--per-sitemap 3] [--url URL ...]

Pages come from robots.txt Sitemap lines (falling back to /sitemap_index.xml,
/wp-sitemap.xml, /sitemap.xml), newest-looking N per child sitemap, plus the
homepage and any --url given. Exit 0 = clean, 1 = defects found, 2 = could not
check (fetch or sitemap failure). No fallbacks that hide a failure: a page that
cannot be fetched is reported and counts as exit 2.
"""
import argparse
import html
import json
import random
import re
import sys
import urllib.parse
import urllib.request
from collections import defaultdict

UA = "Mozilla/5.0 (schema-identity-check; Content Cucumber)"
SINGLETONS = {"Organization", "WebSite", "WebPage", "FAQPage", "BreadcrumbList",
              "CollectionPage", "ProfilePage", "AboutPage", "ContactPage"}
IDENTITY_TYPES = {"Organization", "WebSite", "ProfessionalService", "LocalBusiness", "Corporation"}


def fetch(url):
    sep = "&" if "?" in url else "?"
    req = urllib.request.Request(f"{url}{sep}cb={random.randint(1, 10**9)}", headers={"User-Agent": UA})
    with urllib.request.urlopen(req, timeout=30) as r:
        return r.read().decode("utf8", "replace")


def sitemap_urls(site, per_sitemap):
    roots = []
    try:
        roots = re.findall(r"(?im)^\s*sitemap:\s*(\S+)", fetch(site + "/robots.txt"))
        roots = [u for u in roots if urllib.parse.urlparse(u).netloc == urllib.parse.urlparse(site).netloc]
    except Exception:
        pass
    roots += [site + p for p in ("/sitemap_index.xml", "/wp-sitemap.xml", "/sitemap.xml")]
    for root in roots:
        try:
            xml = fetch(root)
        except Exception:
            continue
        locs = re.findall(r"<loc>\s*([^<\s]+)\s*</loc>", xml)
        if not locs:
            continue
        if "<sitemapindex" in xml:
            pages = []
            for child in locs:
                try:
                    child_locs = re.findall(r"<loc>\s*([^<\s]+)\s*</loc>", fetch(child))
                except Exception as e:
                    print(f"  ! child sitemap failed: {child} ({e})")
                    continue
                pages += child_locs[-per_sitemap:]
            return root, pages
        return root, locs[-per_sitemap * 5:]
    return None, []


def type_set(node):
    t = node.get("@type")
    if t is None:
        return set()
    return set(t) if isinstance(t, list) else {t}


def walk(obj, defined, refs, nodes):
    """Collect every node that defines an @id (has more than just @id) and every bare reference."""
    if isinstance(obj, dict):
        keys = set(obj.keys()) - {"@context"}
        if "@id" in obj and keys == {"@id"}:
            refs.add(obj["@id"])
        elif "@type" in obj:
            nodes.append(obj)
            if "@id" in obj:
                defined[obj["@id"]].append(obj)
        for v in obj.values():
            walk(v, defined, refs, nodes)
    elif isinstance(obj, list):
        for v in obj:
            walk(v, defined, refs, nodes)


def check_page(url, host):
    h = fetch(url)
    blocks = re.findall(r"<script[^>]*application/ld\+json[^>]*>(.*?)</script>", h, re.S | re.I)
    problems, identity = [], []
    defined, refs, top_nodes = defaultdict(list), set(), []
    for i, raw in enumerate(blocks):
        try:
            data = json.loads(raw)
        except Exception as e:
            problems.append(f"block {i + 1} does not parse ({e.__class__.__name__})")
            continue
        top = data.get("@graph", [data]) if isinstance(data, dict) else data
        for n in top if isinstance(top, list) else [top]:
            if isinstance(n, dict):
                n["_block"] = i + 1
                top_nodes.append(n)
        walk(data, defined, refs, [])

    for node_id, defs in defined.items():
        if len(defs) > 1:
            kinds = sorted({"/".join(sorted(type_set(d))) for d in defs})
            problems.append(f"@id defined {len(defs)} times: {node_id} ({', '.join(kinds)})")

    counts = defaultdict(list)
    for n in top_nodes:
        for t in type_set(n) & SINGLETONS:
            counts[t].append(n["_block"])
    for t, blks in counts.items():
        if len(blks) > 1:
            problems.append(f"{t} declared {len(blks)} times (blocks {', '.join(map(str, blks))})")

    for r in sorted(refs):
        if urllib.parse.urlparse(r).netloc in ("", host) and r not in defined:
            problems.append(f"reference to undefined @id: {r}")

    for n in top_nodes:
        if type_set(n) & IDENTITY_TYPES:
            identity.append(("/".join(sorted(type_set(n))), n.get("name", ""), (n.get("description") or "").strip()))

    meta = re.search(r'<meta\s+name=["\']description["\']\s+content=["\']([^"\']*)', h, re.I)
    h1 = re.search(r"<h1[^>]*>(.*?)</h1>", h, re.S | re.I)
    page_text = {
        "meta description": html.unescape(meta.group(1)).strip() if meta else "",
        "h1": re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", " ", h1.group(1)))).strip() if h1 else "",
    }
    return len(blocks), problems, identity, page_text


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("site")
    ap.add_argument("--per-sitemap", type=int, default=3)
    ap.add_argument("--url", action="append", default=[])
    a = ap.parse_args()
    site = a.site.rstrip("/")
    host = urllib.parse.urlparse(site).netloc

    root, pages = sitemap_urls(site, a.per_sitemap)
    if root is None:
        print(f"CANNOT CHECK: no readable sitemap for {site}")
        return 2
    pages = list(dict.fromkeys([site + "/"] + a.url + pages))
    print(f"schema-identity-check {site}  sitemap={root}  pages={len(pages)}\n")

    bad_pages, fetch_failures = 0, 0
    identity_by_value = defaultdict(set)
    home_text = None
    for u in pages:
        try:
            n_blocks, problems, identity, page_text = check_page(u, host)
        except Exception as e:
            print(f"FETCH FAIL {u}: {e}")
            fetch_failures += 1
            continue
        if u == site + "/":
            home_text = page_text
        for kind, name, desc in identity:
            identity_by_value[(kind, name, desc)].add(u)
        if problems:
            bad_pages += 1
            print(f"RED  {u}  ({n_blocks} ld+json blocks)")
            for p in problems:
                print(f"     - {p}")

    print("\nHow the brand describes itself in schema:")
    descs = defaultdict(set)
    for (kind, name, desc), urls in sorted(identity_by_value.items()):
        print(f"  [{kind}] name={name!r} on {len(urls)} page(s)\n      {desc or '(no description)'}")
        if desc and kind != "WebSite":
            descs[name].add(desc)
    drift = {n: d for n, d in descs.items() if len(d) > 1}
    if home_text:
        print(f"\nHomepage meta description: {home_text['meta description'] or '(none)'}")
        print(f"Homepage H1:               {home_text['h1'] or '(none)'}")
    if drift:
        print("\nRED  identity drift: the same brand name carries more than one description in schema")
        for n, d in drift.items():
            for x in sorted(d):
                print(f"     - {n}: {x[:140]}")

    print(f"\nSummary: {len(pages)} pages, {bad_pages} with structural defects, "
          f"{len(drift)} identity drift, {fetch_failures} fetch failures")
    if fetch_failures:
        return 2
    return 1 if (bad_pages or drift) else 0


if __name__ == "__main__":
    sys.exit(main())
