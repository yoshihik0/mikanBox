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
  php(site,'saveSettings(["mcp_api_key"=>"sha256:".hash("sha256",'+json.dumps(key)+'),"memo"=>"SITE_PRIVATE_MARKER"]);saveData(POSTS_DIR,"private-audit",["title"=>"test","status"=>"draft","memo"=>"PAGE_PRIVATE_MARKER"]);saveData(COMPONENTS_DIR,"private-audit",["html"=>"test","memo"=>"COMPONENT_PRIVATE_MARKER"]);')
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
     a.post(admin,{'login_action':'set_initial_password','new_password':password})
     # Setting the password while password_hash is empty retires a key left from before (2.21.3); issue one again.
     assert php(site,'echo empty(loadSettings()["mcp_api_key"]) ? "no" : "yes";') == 'no'
     php(site,'$s=loadSettings();$s["mcp_api_key"]="sha256:".hash("sha256",'+json.dumps(key)+');saveSettings($s);')
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
     php(site,'$s=loadSettings();$s["mcp_api_key"]="sha256:".hash("sha256",'+json.dumps(key)+');saveSettings($s);')
     print('sqlite: recovery with no active devices revokes old key')
    for tool,args,marker in [('get_page',{'id':'private-audit'},'PAGE_PRIVATE_MARKER'),('get_component',{'id':'private-audit'},'COMPONENT_PRIVATE_MARKER'),('get_settings',{},'SITE_PRIVATE_MARKER')]:
     status,out=Browser().req(url+'/mikanBox/mcp.php',{'jsonrpc':'2.0','id':1,'method':'tools/call','params':{'name':tool,'arguments':args}},{'Content-Type':'application/json','Authorization':'Bearer '+key})
     assert status == 200 and marker in out
     print(edition,tool,': authenticated notes preserved')
    anon=Browser();status,html=anon.req(admin)
    assert key not in html
    print(edition, 'unauthenticated admin page never carries the MCP key')
    for public_path in ['/private-audit','/api/private-audit','/mikanBox/public-mcp.php?action=get_product_info']:
     status,body=Browser().req(url+public_path)
     assert 'PAGE_PRIVATE_MARKER' not in body and 'SITE_PRIVATE_MARKER' not in body and 'COMPONENT_PRIVATE_MARKER' not in body
    print(edition, 'public page/API/help exclude notes')
    if True:
     # The data tool reads only media files into media/ and keeps imported notes private (2.24.0).
     b=a if edition=='flat' else b   # the browser that is still signed in
     part={'pages':[{'id':'import-note','data':{'title':'Imported','status':'draft','content_md':'Imported body','memo':'IMPORT_PRIVATE_MARKER'}}]}
     status,out=b.req(admin,{'save_action':'transfer_import_data','mode':'add','part':json.dumps(part),'csrf_token':b.csrf(admin),'ajax_request':'1'})
     assert status==200 and json.loads(out)['pages']==1
     for name,body in [('unexpected.php','<?php echo 1;'),('.htaccess','Require all granted'),('x.php.png','<?php echo 1;')]:
      boundary='mikanbox-test-boundary'
      fields={'save_action':'transfer_import_media','mode':'overwrite','name':name,'csrf_token':b.csrf(admin),'ajax_request':'1'}
      parts=[('--'+boundary+'\r\nContent-Disposition: form-data; name="'+k+'"\r\n\r\n'+v+'\r\n').encode() for k,v in fields.items()]
      parts.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="file"; filename="f"\r\nContent-Type: application/octet-stream\r\n\r\n').encode()+body.encode()+('\r\n--'+boundary+'--\r\n').encode())
      response=b.op.open(urllib.request.Request(admin,data=b''.join(parts),headers={'Content-Type':'multipart/form-data; boundary='+boundary}),timeout=10)
      assert json.loads(response.read())['success'] is False, name
      assert not (site/'media'/name).exists() or name=='.htaccess'
     assert 'IMPORT_PRIVATE_MARKER' not in Browser().req(url+'/import-note')[1]
     print(edition+': the data tool reads only media files; imported notes stay private')

    editor=url+'/mikanBox/admin.php?view=pages'
    def save_page(id,old_id='',title='',status='draft',**extra):
     response,body=b.post(editor,{'save_action':'save_page','ajax_request':'1','id':id,'old_id':old_id,'title':title,'status':status,**extra})
     return response,json.loads(body)
    assert save_page('collision-source',title='Source')[1]['success']
    assert save_page('collision-target',title='Target')[1]['success']
    status,conflict=save_page('collision-target','collision-source','Edited')
    assert status==409 and not conflict['success']
    assert conflict['page_id_conflict']['keep_backup']
    assert save_page('collision-target','collision-target','Changed target')[1]['success']
    status,refreshed=save_page('collision-target','collision-source','Edited',overwrite_page=conflict['page_id_conflict']['token'],backup_overwritten_page='1')
    assert status==409 and refreshed['page_id_conflict']['token'] != conflict['page_id_conflict']['token']
    assert php(site,'echo loadData(POSTS_DIR,"collision-source")["title"]."|".loadData(POSTS_DIR,"collision-target")["title"];') == 'Source|Changed target'
    assert php(site,'echo loadData(POSTS_DIR,"collision-target-bk")===null ? "missing" : "exists";') == 'missing'
    assert save_page('collision-target-bk/index',title='Alias')[1]['success']
    status,saved=save_page('collision-target','collision-source','Edited',overwrite_page=refreshed['page_id_conflict']['token'],backup_overwritten_page='1')
    assert status==200 and saved['success'] and saved['editId']=='collision-target'
    assert 'collision-target-bk-2' in saved['message']
    assert php(site,'$p=loadData(POSTS_DIR,"collision-target-bk-2"); echo $p["title"]."|".$p["status"];') == 'Changed target|draft'
    assert php(site,'echo loadData(POSTS_DIR,"collision-target-bk/index")["title"];') == 'Alias'
    assert php(site,'echo loadData(POSTS_DIR,"collision-source")===null ? loadData(POSTS_DIR,"collision-target")["title"] : "source still exists";') == 'Edited'
    status,conflict=save_page('collision-target',title='New content')
    assert status==409
    assert save_page('collision-target',title='New content',overwrite_page=conflict['page_id_conflict']['token'],backup_overwritten_page='0')[1]['success']
    assert php(site,'echo loadData(POSTS_DIR,"collision-target-bk-3")===null ? "missing" : "exists";') == 'missing'
    assert save_page('static-target',title='Static JSON',status='public_static',output_format='json')[1]['success']
    published=site/'static-target.json'
    published.write_text('{"previous":true}')
    status,conflict=save_page('static-target',title='Draft HTML')
    assert status==409 and published.exists()
    assert save_page('static-target',title='Draft HTML',overwrite_page=conflict['page_id_conflict']['token'],backup_overwritten_page='1')[1]['success']
    assert not published.exists()
    assert php(site,'$p=loadData(POSTS_DIR,"static-target-bk"); echo $p["title"]."|".$p["status"]."|".$p["output_format"];') == 'Static JSON|draft|json'
    assert not (site/'static-target-bk.json').exists()
    assert save_page('folder/index',title='Nested')[1]['success']
    assert save_page('folder',title='Exact')[1]['success']
    if edition=='sqlite':
     assert 'Changed target' in php(site,'echo json_encode(getDb()->querySingle("SELECT GROUP_CONCAT(title) FROM post_revisions WHERE post_id = \'collision-target\'"));')
    print(edition+': page ID collision, refreshed approval, and overwrite verified')

   finally:proc.terminate();proc.wait(timeout=5)
