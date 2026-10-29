#!/usr/bin/env python3
"""Disposable HTTP/stdio/security-log regression for both PHP editions."""
import http.cookiejar, json, os, re, secrets, shutil, socket, subprocess, tempfile, time
import urllib.request, urllib.error, urllib.parse
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
PHP=shutil.which('php')
checks=0
PROJECT=Path(__file__).resolve().parents[1]
REPOSITORIES=[('sqlite' if (PROJECT/'mikanBox/lib/device-auth.php').exists() else 'flat',PROJECT)] if (PROJECT/'mikanBox/config.php').exists() else [(edition,ROOT/('mikanBox-'+edition)) for edition in ['flat','sqlite']]

def check(value, label):
    global checks
    if not value: raise AssertionError(label)
    checks+=1

def php(site, code):
    result=subprocess.run([PHP,'-r',"require $argv[1].'/mikanBox/config.php'; require_once $argv[1].'/mikanBox/lib/functions.php'; "+code,str(site)],capture_output=True,text=True)
    if result.returncode: raise RuntimeError((result.stdout+result.stderr)[-3500:])
    check(not re.search(r'Warning|Fatal|Deprecated|Notice',result.stderr+result.stdout),'PHP runtime is clean')
    return result.stdout

with tempfile.TemporaryDirectory(prefix='mikanbox-security-controls-') as temporary:
    for edition,repo in REPOSITORIES:
        site=Path(temporary)/edition
        files=subprocess.check_output(['git','ls-files'],cwd=repo,text=True).splitlines()+['mikanBox/lib/security.php']
        for name in set(files):
            source=repo/name
            if (name=='index.php' or name.startswith('mikanBox/')) and source.is_file():
                target=site/name;target.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(source,target)
        key=secrets.token_hex(24);password=secrets.token_hex(16)
        php(site,'saveSettings(["mcp_api_key"=>'+json.dumps(key)+((',"password_hash"=>password_hash('+json.dumps(password)+',PASSWORD_DEFAULT)') if edition=='flat' else '')+']);')
        with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
        base='http://127.0.0.1:'+str(port);jar=http.cookiejar.CookieJar();browser=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
        def request(path,data=None,headers=None,opener=browser):
            body=None if data is None else urllib.parse.urlencode(data).encode()
            try:res=opener.open(urllib.request.Request(base+path,data=body,headers=headers or {}),timeout=15)
            except urllib.error.HTTPError as error:res=error
            return res.status,res.read().decode()
        admin='/mikanBox/admin.php?view=site'
        def csrf():
            status,body=request(admin);match=re.search(r'name="csrf_token" value="([^"]+)"',body);check(bool(match),'CSRF on admin');return match[1]
        def post(data):return request(admin,{**data,'csrf_token':csrf()})
        def mcp(provided=key):
            req=urllib.request.Request(base+'/mikanBox/mcp.php',data=json.dumps({'jsonrpc':'2.0','id':1,'method':'tools/list'}).encode(),headers={'Content-Type':'application/json','Authorization':'Bearer '+provided})
            try:res=urllib.request.urlopen(req,timeout=15)
            except urllib.error.HTTPError as error:res=error
            return res.status,res.read().decode()
        with tempfile.TemporaryFile(mode='w+') as log:
            server=subprocess.Popen([PHP,'-S','127.0.0.1:'+str(port),'-t',str(site),str(site/'index.php')],stdout=log,stderr=log)
            try:
                for i in range(100):
                    try:request(admin);break
                    except OSError:time.sleep(.05)
                if edition=='flat':
                    request(admin,{'login_action':'login','password':password})
                else:
                    token=secrets.token_hex(24);(site/'setup-token/setup-token.txt').write_text(token)
                    request(admin,{'login_action':'setup','csrf_token':csrf(),'username':'editor','display_name':'Editor','device_name':'Test','setup_token':token})
                check('id="mcp-key-display"' in request(admin)[1],edition+' authenticated site UI')
                check(mcp('wrong-key')[0]==401,'invalid key denied')
                state=site/'mikanData/security/mcp.json.php';check(not state.exists(),'failed key does not record usage')
                check(mcp()[0]==200,'existing key remains enabled by default')
                original=state.read_text();check(key not in original,'usage file has no raw key')
                check(mcp()[0]==200 and state.read_text()==original,'usage is throttled')
                post({'save_action':'set_mcp_enabled'})
                check(mcp()[0]==403,'disabled management MCP denied')
                php(site,'$s=loadSettings();$s["memo"]="Unrelated save";saveSettings($s);')
                check(mcp()[0]==403,'unrelated settings cannot undo stop')
                status,body=request('/mikanBox/public-mcp.php?action=get_product_info');check(status==200,'public help remains available')
                for path in ['/mikanData/security/events.jsonl.php','/mikanData/security/mcp.json.php','/mikanData/security/security-lock.php','/mikan%44ata/security/events.jsonl.php']:
                    check(request(path)[0] in [403,404], 'private security file denied '+path)
                post({'save_action':'set_mcp_enabled','mcp_enabled':'1'})
                check(mcp()[0]==200,'reenabled key works')
                cli=subprocess.Popen([PHP,str(site/'mikanBox/mcp.php')],stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
                try:
                    before_cli=state.read_text();cli.stdin.write('{"jsonrpc":"2.0","id":1,"method":"tools/list"}\n');cli.stdin.flush();check('result' in json.loads(cli.stdout.readline()),'stdio enabled');check(state.read_text()==before_cli,'keyless stdio does not update key usage')
                    php(site,'mikanBoxMcpSetEnabled(false);')
                    cli.stdin.write('{"jsonrpc":"2.0","id":2,"method":"tools/list"}\n');cli.stdin.flush();check('error' in json.loads(cli.stdout.readline()),'existing stdio connection observes stop')
                finally:cli.stdin.close();cli.wait(timeout=5)
                php(site,'$_SERVER["REMOTE_ADDR"]="192.0.2.123";$_SESSION=["admin_logged_in"=>true,"admin_username"=>"<script>audit</script>"];mikanBoxAudit("login_failed",["password"=>"NEVER_LOG_THIS","target"=>"bad\\nrecord"]);saveData(POSTS_DIR,"audit-page",["title"=>"PRIVATE TITLE","status"=>"public_dynamic"]);')
                raw=(site/'mikanData/security/events.jsonl.php').read_text();rows=[json.loads(line) for line in raw.splitlines()[1:]]
                for secret in [key,password,'NEVER_LOG_THIS','PRIVATE TITLE','192.0.2.123']:check(secret not in raw,'secret or full IP absent')
                check(any(row['ip']=='192.0.2.0/24' for row in rows),'IPv4 masked')
                check(any(row['event']=='page_status' for row in rows),'publication state audited')
                rendered=request(admin)[1];check('&lt;script&gt;audit&lt;/script&gt;' in rendered,'audit UI escapes stored actor')
                php(site,'for($i=0;$i<7500;$i++)mikanBoxAudit("login_failed");')
                check((site/'mikanData/security/events.jsonl.php').stat().st_size<=1048576,'log size bounded')
                count=int(php(site,'echo count(mikanBoxAuditRecent());'));check(count==50,'latest 50 events retained')
                event_path=site/'mikanData/security/events.jsonl.php';saved_log=event_path.with_name('saved-events.php');event_path.rename(saved_log);event_path.mkdir()
                try:
                    status,body=post({'save_action':'set_mcp_enabled','mcp_enabled':'1'})
                    check('操作記録を保存できません' in body,'recording error survives redirect and is shown to admin')
                    check(mcp()[0]==200,'log failure does not turn a successful operation into failure')
                finally:event_path.rmdir();saved_log.rename(event_path)
                post({'save_action':'set_mcp_enabled'})
                _,generated=post({'save_action':'generate_mcp_key','ajax_request':'1'});generated=json.loads(generated);new_key=generated.get('mcp_api_key','')
                check(generated.get('success') and new_key and new_key!=key,'key regeneration succeeds')
                check(mcp(new_key)[0]==403,'regeneration does not bypass disabled state')
                check(php(site,'echo mikanBoxMcpLastUsed('+json.dumps(new_key)+');').strip()=='0','new key has no previous usage')
                post({'save_action':'set_mcp_enabled','mcp_enabled':'1'})
                check(mcp(key)[0]==401 and mcp(new_key)[0]==200,'old key revoked and new key works')
                check(new_key not in event_path.read_text(),'regenerated key is absent from audit log')
                with socket.socket() as extra_socket:extra_socket.bind(('127.0.0.1',0));extra_port=extra_socket.getsockname()[1]
                plain=subprocess.Popen([PHP,'-S','127.0.0.1:'+str(extra_port),'-t',str(site)],stdout=log,stderr=log)
                try:
                    for attempt in range(100):
                        try:
                            urllib.request.urlopen('http://127.0.0.1:'+str(extra_port)+'/mikanData/security/events.jsonl.php',timeout=1)
                        except urllib.error.HTTPError as error:
                            if error.code==404:break
                        except OSError:time.sleep(.05);continue
                    for private in ['events.jsonl.php','mcp.json.php','security-lock.php']:
                        try:response=urllib.request.urlopen('http://127.0.0.1:'+str(extra_port)+'/mikanData/security/'+private,timeout=3)
                        except urllib.error.HTTPError as error:response=error
                        check(response.status==404,'security file protected even without a router or .htaccess')
                finally:plain.terminate();plain.wait(timeout=5)
                print(edition+': HTTP/stdio stop, usage, protected files and logs passed')
            finally:
                server.terminate();server.wait(timeout=5);log.seek(0);errors=re.findall(r'.*(?:Fatal|Warning|Notice|Deprecated|Parse error).*',log.read());check(not errors,'HTTP runtime is clean: '+str(errors[:2]))
print(str(checks)+' security checks passed; no remote services used.')
