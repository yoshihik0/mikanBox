#!/usr/bin/env python3
"""Every change to pages, components, media and settings leaves a record, in both PHP editions."""
import json, re, shutil, subprocess, tempfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
PHP=shutil.which('php')
PROJECT=Path(__file__).resolve().parents[1]
REPOSITORIES=[('sqlite' if (PROJECT/'mikanBox/lib/device-auth.php').exists() else 'flat',PROJECT)] if (PROJECT/'mikanBox/config.php').exists() else [(edition,ROOT/('mikanBox-'+edition)) for edition in ['flat','sqlite']]
checks=0
def check(value,label):
    global checks
    if not value: raise AssertionError(label)
    checks+=1
def php(site,code):
    result=subprocess.run([PHP,'-r',"require $argv[1].'/mikanBox/config.php'; require_once $argv[1].'/mikanBox/lib/functions.php'; $_SERVER['REMOTE_ADDR']='192.0.2.5'; $_SESSION=['admin_logged_in'=>true,'admin_username'=>'editor']; "+code,str(site)],capture_output=True,text=True)
    if result.returncode or re.search(r'Warning|Fatal|Deprecated|Notice',result.stderr+result.stdout): raise RuntimeError((result.stdout+result.stderr)[-3000:])
    return result.stdout
def rows(site):
    path=site/'mikanData/security/events.jsonl.php'
    return [json.loads(l) for l in path.read_text().splitlines()[1:] if l.strip()] if path.exists() else []
def events(site): return [(r['event'],r.get('target','')) for r in rows(site)]

with tempfile.TemporaryDirectory(prefix='mikanbox-audit-coverage-') as temporary:
    for edition,repo in REPOSITORIES:
        site=Path(temporary)/edition
        for name in set(subprocess.check_output(['git','ls-files'],cwd=repo,text=True).splitlines()+['mikanBox/lib/security.php']):
            source=repo/name
            if (name=='index.php' or name.startswith('mikanBox/')) and source.is_file():
                target=site/name;target.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(source,target)
        php(site,'saveSettings(["site_name"=>"Test"]);')
        start=len(rows(site))
        # pages
        php(site,'saveData(POSTS_DIR,"p1",["title"=>"One","content_md"=>"a","status"=>"public_dynamic","updated_at"=>"2026-01-01 00:00:00"]);')
        php(site,'$d=loadData(POSTS_DIR,"p1");$d["content_md"]="a<script src=x></script>";saveData(POSTS_DIR,"p1",$d);')
        r=rows(site)[start:]
        check(r[0]['event']=='page_created' and r[0]['target']=='p1' and r[0].get('to')=='public_dynamic' and len(r[0].get('after',''))==64,edition+': page creation recorded with status and hash')
        check(r[1]['event']=='page_updated' and r[1]['before']==r[0]['after'] and r[1]['after']!=r[1]['before'],edition+': edit of a public page recorded with before/after')
        check(r[1]['actor']=='editor' and r[1]['ip']=='192.0.2.0/24',edition+': actor and masked IP')
        n=len(rows(site))
        php(site,'$d=loadData(POSTS_DIR,"p1");$d["content_md"]="b";saveData(POSTS_DIR,"p1",$d);$d["content_md"]="c";saveData(POSTS_DIR,"p1",$d);')
        r=rows(site)
        check(len(r)==n and r[-1]['before']==rows(site)[start+1]['before'],edition+': repeated saves merge into one record keeping the first before')
        n=len(r)
        php(site,'$d=loadData(POSTS_DIR,"p1");saveData(POSTS_DIR,"p1",$d);$d["updated_at"]="2030-01-01 00:00:00";saveData(POSTS_DIR,"p1",$d);')
        check(len(rows(site))==n,edition+': a save without a content change adds nothing')
        php(site,'$d=loadData(POSTS_DIR,"p1");$d["status"]="draft";saveData(POSTS_DIR,"p1",$d);')
        check(events(site)[-1]==('page_status','p1'),edition+': status change recorded')
        php(site,'deleteData(POSTS_DIR,"p1");deleteData(POSTS_DIR,"missing");')
        check(events(site)[-1]==('page_deleted','p1') and ('page_deleted','missing') not in events(site),edition+': deletion recorded only when something was deleted')
        # components
        php(site,'saveData(COMPONENTS_DIR,"wrap",["type"=>"wrapper","content"=>"<main>{{CONTENT}}</main>"]);$c=loadData(COMPONENTS_DIR,"wrap");$c["content"]="<script>x</script>".$c["content"];saveData(COMPONENTS_DIR,"wrap",$c);deleteData(COMPONENTS_DIR,"wrap");')
        check(events(site)[-3:]==[('component_created','wrap'),('component_updated','wrap'),('component_deleted','wrap')],edition+': component changes recorded')
        # settings
        php(site,'$s=loadSettings();$s["site_name"]="Changed";$s["memo"]="m";saveSettings($s);')
        check(events(site)[-1]==('settings_changed','memo,site_name'),edition+': changed setting names recorded')
        n=len(rows(site))
        php(site,'$GLOBALS["mikanbox_audit_actor"]="other";$s=loadSettings();$s["last_build"]=["at"=>1];saveSettings($s);')
        check(len(rows(site))==n,edition+': automatic build record is not an edit')
        check('Changed' not in (site/'mikanData/security/events.jsonl.php').read_text(),edition+': setting values are never written to the log')
        # media
        (site/'media').mkdir(exist_ok=True);(site/'media/a.js').write_text('console.log(1)')
        php(site,'mikanBoxAuditMedia("media_uploaded","a.js");')
        r=rows(site)[-1];check(r['event']=='media_uploaded' and r['after']==__import__('hashlib').sha256(b'console.log(1)').hexdigest(),edition+': media upload recorded with file hash')
        # bulk import: one summary
        n=len(rows(site))
        php(site,'require_once CORE_DIR."/lib/data-transfer.php";mikanBoxTransferImportData("add",["pages"=>[["id"=>"i1","data"=>["title"=>"I1","status"=>"draft"]],["id"=>"i2","data"=>["title"=>"I2","status"=>"draft"]]]]);mikanBoxTransferImportData("add",["pages"=>[["id"=>"i3","data"=>["title"=>"I3","status"=>"draft"]]]]);')
        added=rows(site)[n:]
        check([r['event'] for r in added]==['data_imported'] and added[0]['target']=='add',edition+': an import is one record, not one per page')
        php(site,'saveData(POSTS_DIR,"after-import",["title"=>"x","status"=>"draft"]);')
        check(events(site)[-1]==('page_created','after-import'),edition+': recording resumes after an import')
        php(site,'require_once CORE_DIR."/lib/data-transfer.php";mikanBoxTransferFinish("replace",[]);')
        check(events(site)[-1][0]=='data_restored',edition+': replacing data is recorded')
        # trimming keeps the file bounded
        php(site,'for($i=0;$i<2000;$i++)mikanBoxAudit("page_created",["target"=>"bulk".$i,"after"=>str_repeat("a",64)]);')
        check((site/'mikanData/security/events.jsonl.php').stat().st_size<=400000+1000,edition+': the log file stays bounded')
        check(any(x['event']=='data_restored' for x in rows(site)),edition+': edits do not push out important records')
        shown=json.loads(php(site,'echo json_encode(array_count_values(array_map(fn($r)=>mikanBoxAuditGroup($r["event"]),mikanBoxAuditRecent())));'))
        check(shown.get('edit')==500 and shown.get('important',0)>=1,edition+': the newest 500 edits are shown')
print(f'audit coverage ok ({checks} checks)')
