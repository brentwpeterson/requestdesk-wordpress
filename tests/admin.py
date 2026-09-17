import sys,re,http.cookiejar,urllib.request,urllib.parse
from html.parser import HTMLParser
base="http://wprequestdesk.local"; pw=open(sys.argv[1]).read().strip(); action=sys.argv[2]
cj=http.cookiejar.CookieJar(); op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj))
op.open(base+"/wp-login.php").read()
op.open(base+"/wp-login.php",urllib.parse.urlencode({"log":"hodgkin-test","pwd":pw,"wp-submit":"Log In","redirect_to":base+"/wp-admin/","testcookie":"1"}).encode()).read()
def get(path):
    try:
        r=op.open(base+path); return r.status, r.read().decode("utf8","replace")
    except urllib.error.HTTPError as e: return e.code, e.read().decode("utf8","replace")
if action=="screens":
    for p in ["/wp-admin/","/wp-admin/plugins.php","/wp-admin/admin.php?page=requestdesk-settings","/wp-admin/post.php?post=5&action=edit","/wp-admin/post-new.php","/wp-admin/admin.php?page=wpseo_dashboard","/wp-admin/edit.php"]:
        c,h=get(p); bad=[w for w in ("critical error","Fatal error","Warning:","Notice:","Deprecated:") if w in h]
        print(c, p, "problems:", bad, "| logged-in:", "wp-admin-bar" in h)
    c,h=get("/wp-admin/admin.php?page=requestdesk-settings")
    print("radios:", re.findall(r'<input type="radio" name="yoast_mode"[^>]*>',h)); print("yoast status line:", re.findall(r"Yoast SEO on this site: <strong>([^<]+)",h))
else:
    url="/wp-admin/admin.php?page=requestdesk-settings"; c,h=get(url)
    i=h.find('name="requestdesk_aeo_nonce"'); fs=h.rfind("<form",0,i); fe=h.find("</form>",i); form=h[fs:fe]
    class P(HTMLParser):
        def __init__(s): super().__init__(); s.f=[]; s.sel=None
        def handle_starttag(s,t,a):
            a=dict(a)
            if t=="input" and a.get("name"):
                ty=a.get("type","text")
                if ty in("checkbox","radio"):
                    if "checked" in a: s.f.append((a["name"],a.get("value","on")))
                elif ty!="submit" or a["name"]=="requestdesk_aeo_save_settings": s.f.append((a["name"],a.get("value","")))
            if t=="select": s.sel=a.get("name")
            if t=="option" and s.sel and "selected" in a: s.f.append((s.sel,a.get("value","")))
        def handle_endtag(s,t):
            if t=="select": s.sel=None
    p=P(); p.feed(form); fields=[x for x in p.f if x[0]!="yoast_mode"]+[("yoast_mode",action)]
    r=op.open(base+url,urllib.parse.urlencode(fields).encode()).read().decode("utf8","replace")
    print("saved:", "AEO settings saved!" in r, "| radios:", re.findall(r'<input type="radio" name="yoast_mode"[^>]*>',r))
