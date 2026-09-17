import sys,re,json,urllib.request,os,random
out=sys.argv[1]; os.makedirs(out,exist_ok=True)
B="http://wprequestdesk.local"
urls={"home":"/","faq_post":"/stepdad-faq-test/","yoast_faq_block":"/yoast-faq-block-test/","hello":"/hello-world/","page":"/sample-page/","category":"/category/uncategorized/","author":"/author/admin/"}
def norm(h):
    h=re.sub(r'ver=[0-9a-f.]+','ver=X',h)
    h=re.sub(r'(nonce|_wpnonce)["\':= ]+[0-9a-f]{10}','nonce=X',h)
    h=re.sub(r'\?cb=\d+','',h)
    return h
for k,u in urls.items():
    try:
        r=urllib.request.urlopen(B+u+"?cb=%d"%random.randint(1,10**9)); code=r.status; h=r.read().decode("utf8","replace"); u2=r.geturl()
    except urllib.error.HTTPError as e:
        code=e.code; h=e.read().decode("utf8","replace"); u2=u
    open(f"{out}/{k}.html","w").write(norm(h))
    blocks=re.findall(r'<script[^>]*application/ld\+json[^>]*>(.*?)</script>',h,re.S)
    types=[]
    for b in blocks:
        try:
            d=json.loads(b); nodes=d.get("@graph",[d]); types.append([ (n.get("@type"), n.get("@id","")[-24:]) for n in nodes])
        except Exception: types.append("PARSE-ERR")
    meta={m:len(re.findall(p,h,re.I)) for m,p in {"title":r"<title","desc":r'name=["\']description','canonical':r'rel=["\']canonical','robots':r'name=["\']robots','og:title':r'og:title"','og:desc':r'og:description"','tw:card':r'twitter:card'}.items()}
    crit="critical error" in h.lower() or "fatal error" in h.lower() or "warning:" in h.lower()
    print(f"{k:16} {code} ldjson={len(blocks)} errtext={crit} meta={meta}")
    for t in types: print("      ",t)
