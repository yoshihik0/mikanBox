import io,zipfile
import tempfile,pathlib,shutil,subprocess,socket,time,json,urllib.request,urllib.parse,urllib.error,http.cookiejar,re,secrets,sqlite3
ROOT=pathlib.Path(__file__).resolve().parents[2]; PHP=shutil.which('php')
PROJECT=pathlib.Path(__file__).resolve().parents[1]
REPOSITORIES=[('sqlite' if (PROJECT/'mikanBox/lib/device-auth.php').exists() else 'flat',PROJECT)] if (PROJECT/'mikanBox/config.php').exists() else [(edition,ROOT/('mikanBox-'+edition)) for edition in ['flat','sqlite']]
class Browser:
 def __init__(self): self.jar=http.cookiejar.CookieJar(); self.op=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
 def req(self,url,data=None,headers=None):
  body=None if data is None else (json.dumps(data).encode() if headers and headers.get('Content-Type')=='application/json' else urllib.parse.urlencode(data).encode())
  try:r=self.op.open(urllib.request.Request(url,data=body,headers=headers or {}),timeout=10)
  except urllib.error.HTTPError as e:r=e
  return r.status,r.read().decode()
 def csrf(self,url):return re.search(r'name="csrf_token" value="([^"]+)"',self.req(url)[1]).group(1)
 def post(self,url,data):return self.req(url,{**data,'csrf_token':self.csrf(url)})
def php(site,code):
 p=subprocess.run([PHP,'-r',"require $argv[1].'/mikanBox/config.php'; require_once $argv[1].'/mikanBox/lib/functions.php'; "+code,str(site)],capture_output=True,text=True)
 if p.returncode:raise Exception(p.stderr)
 return p.stdout
with tempfile.TemporaryDirectory(prefix='mikanbox-auth-audit-') as td:
 root=pathlib.Path(td); (root/'sessions').mkdir()
 for edition,repo in REPOSITORIES:
  site=root/edition
  for name in subprocess.check_output(['git','ls-files'],cwd=repo,text=True).splitlines():
   src=repo/name
   if src.is_file() and (name=='index.php' or name.startswith('mikanBox/') or name.startswith('setup-token/')):
    dst=site/name;dst.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(src,dst)
  key=secrets.token_hex(24)
  php(site,'saveSettings(["mcp_api_key"=>'+json.dumps(key)+',"memo"=>"SITE_PRIVATE_MARKER"]);saveData(POSTS_DIR,"private-audit",["title"=>"test","status"=>"draft","memo"=>"PAGE_PRIVATE_MARKER"]);saveData(COMPONENTS_DIR,"private-audit",["html"=>"test","memo"=>"COMPONENT_PRIVATE_MARKER"]);')
  with socket.socket() as s:s.bind(('127.0.0.1',0));port=s.getsockname()[1]
  url='http://127.0.0.1:'+str(port);admin=url+'/mikanBox/admin.php?view=site'
  with tempfile.TemporaryFile(mode='w+') as log:
   proc=subprocess.Popen([PHP,'-d','session.save_path='+str(root/'sessions'),'-S','127.0.0.1:'+str(port),'-t',str(site),str(site/'index.php')],stdout=log,stderr=log)
   try:
    a=Browser();b=Browser()
    for _ in range(100):
     try:a.req(admin);break
     except OSError:time.sleep(.03)
    if edition=='flat':
     password=secrets.token_hex(18); newpass=secrets.token_hex(18)
     a.req(admin,{'login_action':'set_initial_password','new_password':password})
     b.req(admin,{'login_action':'login','password':password})
     assert 'class="page-top-links"' in b.req(admin)[1]
     a.post(admin,{'save_action':'save_settings','current_password':password,'new_password':newpass})
     stale=b.req(admin)[1]
     assert 'class="page-top-links"' not in stale
     status,write=b.req(admin,{'save_action':'save_memo','memo':'STALE_SESSION_WRITE','csrf_token':'invalid'})
     kept=php(site,'echo (loadSettings()["memo"]??"") === "STALE_SESSION_WRITE" ? "yes" : "no";')
     assert kept == 'no'
     assert 'class="page-top-links"' in a.req(admin)[1]
     print('flat: old browser rejected; changing browser remains authenticated')
    else:
     token=secrets.token_urlsafe(32);(site/'setup-token/setup-token.txt').write_text(token)
     a.post(admin,{'login_action':'setup','setup_token':token,'username':'owner','display_name':'Owner','device_name':'Owner browser'})
     assert 'class="page-top-links"' in a.req(admin)[1]
     db=sqlite3.connect(site/'mikanData/mikanBox.sqlite');db.execute('UPDATE device_sessions SET expires_at=?',(int(time.time())-10,));db.commit();db.close()
     token=secrets.token_urlsafe(32);(site/'setup-token/setup-token.txt').write_text(token)
     b.post(admin,{'login_action':'setup','setup_token':token,'username':'owner','device_name':'Recovered browser'})
     assert 'class="page-top-links"' in b.req(admin)[1]
     assert php(site,'echo empty(loadSettings()["mcp_api_key"]) ? "no" : "yes";') == 'no'
     status,out=Browser().req(url+'/mikanBox/mcp.php',{'jsonrpc':'2.0','id':1,'method':'tools/list'},{'Content-Type':'application/json','Authorization':'Bearer '+key})
     assert status in [401,403]
     php(site,'$s=loadSettings();$s["mcp_api_key"]='+json.dumps(key)+';saveSettings($s);')
     print('sqlite: recovery with no active devices revokes old key')
    for tool,args,marker in [('get_page',{'id':'private-audit'},'PAGE_PRIVATE_MARKER'),('get_component',{'id':'private-audit'},'COMPONENT_PRIVATE_MARKER'),('get_settings',{},'SITE_PRIVATE_MARKER')]:
     status,out=Browser().req(url+'/mikanBox/mcp.php',{'jsonrpc':'2.0','id':1,'method':'tools/call','params':{'name':tool,'arguments':args}},{'Content-Type':'application/json','Authorization':'Bearer '+key})
     assert status == 200 and marker in out
     print(edition,tool,': authenticated notes preserved')
    php(site,'$s=loadSettings();$s["demo_mode"]=true;saveSettings($s);')
    anon=Browser();status,html=anon.req(admin)
    assert key not in html
    if edition=='flat': assert 'DEMO-NOT-A-VALID-API-KEY' in html
    status,out=Browser().req(url+'/mikanBox/mcp.php',{'jsonrpc':'2.0','id':1,'method':'tools/list'},{'Content-Type':'application/json','Authorization':'Bearer '+key})
    assert status == 403
    print(edition, 'demo management MCP refused; real key absent')
    for public_path in ['/private-audit','/api/private-audit','/mikanBox/public-mcp.php?action=get_product_info']:
     status,body=Browser().req(url+public_path)
     assert 'PAGE_PRIVATE_MARKER' not in body and 'SITE_PRIVATE_MARKER' not in body and 'COMPONENT_PRIVATE_MARKER' not in body
    print(edition, 'public page/API/help exclude notes')
    if edition=='sqlite':
     package=io.BytesIO()
     with zipfile.ZipFile(package,'w') as z:
      z.writestr('posts/import-note.json',json.dumps({'title':'Imported','status':'draft','content_md':'Imported body','memo':'IMPORT_PRIVATE_MARKER'}))
      z.writestr('unexpected.php','<?php echo "must not extract"; ?>')
      z.writestr('.htaccess','Require all granted')
     boundary='mikanbox-test-boundary'
     parts=[]
     for name,value in {'action':'upload_import','csrf_token':b.csrf(admin)}.items():
      parts.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+name+'"\r\n\r\n'+value+'\r\n').encode())
     parts.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="package_zip"; filename="audit.zip"\r\nContent-Type: application/zip\r\n\r\n').encode()+package.getvalue()+b'\r\n')
     parts.append(('--'+boundary+'--\r\n').encode())
     response=b.op.open(urllib.request.Request(url+'/mikanBox/convert.php',data=b''.join(parts),headers={'Content-Type':'multipart/form-data; boundary='+boundary}),timeout=10)
     assert response.status==200
     archives=list((site/'mikanData/private-import').glob('audit-*_imported'))
     assert len(archives)==1
     assert (archives[0]/'posts/import-note.json').exists()
     assert not (archives[0]/'unexpected.php').exists() and not (archives[0]/'.htaccess').exists()
     assert 'IMPORT_PRIVATE_MARKER' not in Browser().req(url+'/import-note')[1]
     print('sqlite: ZIP imports only JSON into protected storage; imported notes stay private')

   finally:proc.terminate();proc.wait(timeout=5)
