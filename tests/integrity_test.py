#!/usr/bin/env python3
"""The tamper check (2.27.0): public output, media code, program files, data, acceptance and the unusual-actor rule, in both PHP editions."""
import json, re, shutil, subprocess, tempfile, time
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
PRE="""require $argv[1].'/mikanBox/config.php'; define('MIKANBOX',true); require_once $argv[1].'/mikanBox/lib/functions.php'; require_once CORE_DIR.'/lib/renderer.php'; require_once CORE_DIR.'/lib/ssg.php';
$_SERVER['REMOTE_ADDR']='192.0.2.5'; $_SESSION=['admin_logged_in'=>true,'admin_username'=>'editor'];
$OUT=$argv[1].'/pub'; $CTX=['coreDir'=>CORE_DIR,'dataDir'=>DATA_DIR,'repo'=>'owner/repo','version'=>'9.9.9','outputDir'=>$OUT];
$OFFLINE=fn()=>['error'=>'download_failed'];
function build($out){ $s=loadSettings(); $GLOBALS['mikanbox_settings']=$s; $r=new MikanBoxRenderer($s); (new MikanBoxSSG($r,$out,['output_mode'=>'server','structure'=>'directory']))->build(); }
function flat($report){ $o=[]; foreach (['public','media','program'] as $a) foreach (($report[$a]['found']??[]) as $k=>$l) foreach ($l as $p) $o[]=$a.'.'.$k.':'.$p; foreach ($report['data']['found'] as $k=>$l) foreach ($l as $p) $o[]='data.'.$k.':'.$p; sort($o); return $o; }
"""
def php(site,code):
    result=subprocess.run([PHP,'-r',PRE+code,str(site)],capture_output=True,text=True)
    if result.returncode or re.search(r'Warning|Fatal|Deprecated|Notice',result.stderr+result.stdout): raise RuntimeError((result.stdout+result.stderr)[-3000:])
    return result.stdout
def found(site,extra='',official='$OFFLINE'):
    return json.loads(php(site,extra+'echo json_encode(flat(mikanBoxIntegrityCheck($CTX,["official"=>'+official+'])));'))

with tempfile.TemporaryDirectory(prefix='mikanbox-integrity-') as temporary:
    for edition,repo in REPOSITORIES:
        site=Path(temporary)/edition
        shutil.copytree(repo/'mikanBox',site/'mikanBox',ignore=shutil.ignore_patterns('mikanData','media','docs','.DS_Store'))
        shutil.copy2(repo/'index.php',site/'index.php')
        (site/'media').mkdir()
        php(site,'saveSettings(["site_name"=>"Test"]);')
        for i in ['index','about']:
            php(site,'saveData(POSTS_DIR,"%s",["title"=>"%s","content_md"=>"hello","status"=>"public_static","updated_at"=>"2026-01-01 00:00:00"]);'%(i,i))
        php(site,'build($OUT);')
        pub=site/'pub'
        check((pub/'index.html').is_file() and (pub/'about/index.html').is_file(),edition+': the build wrote pages')
        base=json.loads(php(site,'echo json_encode(mikanBoxIntegrityLoad());'))
        check(base['public']['complete'] is True and 'index.html' in base['public']['files'] and 'about/index.html' in base['public']['files'],edition+': the build recorded fingerprints of what it wrote')
        # nothing is wrong yet (program and media have no basis until accepted)
        check(found(site)==[],edition+': a fresh site has no findings')
        rep=json.loads(php(site,'echo json_encode(mikanBoxIntegrityCheck($CTX,["official"=>$OFFLINE]));'))
        check(rep['needs_baseline'] and rep['program']['reference']=='none',edition+': without a basis the program is reported as not checkable')
        # accept: program/media bases exist now
        php(site,'mikanBoxIntegrityAccept($CTX,["official"=>$OFFLINE]);')
        check(found(site)==[],edition+': nothing found after acceptance')
        ev=[json.loads(l) for l in (site/'mikanData/security/events.jsonl.php').read_text().splitlines()[1:] if l.strip()]
        check(ev[-1]['event']=='integrity_accepted',edition+': the acceptance is an important event')
        # --- public output
        (pub/'index.html').write_text((pub/'index.html').read_text()+'<script src="//evil.example/x.js"></script>')
        (pub/'evil.js').write_text('alert(1)')
        (pub/'about'/'shell.php').write_text('<?php ?>')
        (pub/'about/index.html').unlink()
        check(found(site)==sorted(['public.changed:index.html','public.added:evil.js','public.added:about/shell.php','public.missing:about/index.html']),edition+': changed, added and missing public files are found')
        (pub/'dummy.png').write_bytes(b'x')
        check('public.added:dummy.png' not in found(site),edition+': images are not looked at')
        php(site,'mikanBoxIntegrityAccept($CTX,["official"=>$OFFLINE]);')
        check(found(site)==[],edition+': accepting clears public findings')
        # --- media code
        (site/'media/app.js').write_text('console.log(1)')
        php(site,'mikanBoxAuditMedia("media_uploaded","app.js");')
        check(found(site)==['media.added:app.js'] or found(site)==[],edition+': an upload through mikanBox is recorded')
        check(found(site)==[],edition+': an upload through mikanBox is not a finding')
        (site/'media/app.js').write_text('console.log(1);document.write("<script src=//evil.example/a.js></script>")')
        (site/'media/new.svg').write_text('<svg onload="x()"/>')
        (site/'media/photo.jpg').write_bytes(b'\xff\xd8')
        check(found(site)==['media.added:new.svg','media.changed:app.js'],edition+': an injected media script and an unknown svg are found, images are not')
        php(site,'mikanBoxIntegrityAccept($CTX,["official"=>$OFFLINE]);')
        check(found(site)==[],edition+': accepting clears media findings')
        (site/'media/app.js').unlink(); php(site,'mikanBoxAuditMedia("media_deleted","app.js");')
        check(found(site)==['media.added:new.svg'] or found(site)==[],edition+': a deletion through mikanBox is recorded')
        check(found(site)==[],edition+': a deletion through mikanBox is not a finding')
        # --- program files against the recorded state
        lib=site/'mikanBox/lib/functions.php'
        original=lib.read_text()
        lib.write_text(original+"\n// injected\n")
        (site/'mikanBox/lib/evil.php').write_text('<?php ?>')
        check(found(site)==['program.added:mikanBox/lib/evil.php','program.changed:mikanBox/lib/functions.php'],edition+': a modified and an added program file are found against the recorded state')
        # --- program files against the official release
        lib.write_text(original); (site/'mikanBox/lib/evil.php').unlink()
        official=json.loads(php(site,'echo json_encode(["files"=>mikanBoxIntegrityPackageHashes(dirname(CORE_DIR),CORE_DIR)]);'))
        good=json.loads(php(site,'echo json_encode(["files"=>mikanBoxIntegrityProgramHashes(CORE_DIR)]);'))
        check(good['files']==official['files'],edition+': the package listing and the installed listing agree')
        (site/'mikanData/official.json').write_text(json.dumps(good))
        off='fn()=>json_decode(file_get_contents(DATA_DIR."/official.json"),true)'
        check(found(site,official=off)==[],edition+': the installed program equals the official release')
        lib.write_text(original+"\n// injected\n")
        check(found(site,official=off)==['program.changed:mikanBox/lib/functions.php'],edition+': a change from the official release is found')
        php(site,'mikanBoxIntegrityAccept($CTX,["official"=>'+off+']);')
        check(found(site,official=off)==[],edition+': an accepted difference from the release is not reported again')
        lib.write_text(original+"\n// injected twice\n")
        check(found(site,official=off)==['program.changed:mikanBox/lib/functions.php'],edition+': a further change after the acceptance is found')
        lib.write_text(original)
        check(found(site,official=off)==[],edition+': back to the official content')
        rep=json.loads(php(site,'echo json_encode(mikanBoxIntegrityCheck($CTX,["official"=>'+off+']));'))
        check(rep['program']['reference']=='official',edition+': the report says the official release was the reference')
        # --- data
        php(site,'saveData(POSTS_DIR,"p1",["title"=>"One","content_md"=>"a","status"=>"public_dynamic","updated_at"=>"2026-01-01 00:00:00"]);saveData(COMPONENTS_DIR,"c1",["type"=>"wrapper","content"=>"<main>{{CONTENT}}</main>"]);$s=loadSettings();$s["memo"]="m";saveSettings($s);')
        check(found(site)==[],edition+': data saved through mikanBox matches its record')
        php(site,'$d=loadData(POSTS_DIR,"p1");$d["updated_at"]="2030-01-01 00:00:00";saveData(POSTS_DIR,"p1",$d);$d["status"]="draft";saveData(POSTS_DIR,"p1",$d);')
        check(found(site)==[],edition+': a save that only moves the time, and a status change, match their records')
        php(site,'$d=loadData(POSTS_DIR,"p1");$d["content_md"]="<script src=//evil.example></script>";mikanBoxStorageSaveData(POSTS_DIR,"p1",$d);')
        php(site,'$c=loadData(COMPONENTS_DIR,"c1");$c["content"]="<script>x</script>";mikanBoxStorageSaveData(COMPONENTS_DIR,"c1",$c);')
        php(site,'$s=loadSettings();$s["password_hash"]="";mikanBoxStorageSaveSettings($s);')
        check(found(site)==['data.changed:component:c1','data.changed:page:p1','data.changed:settings'],edition+': content changed outside mikanBox is found (page, component, settings)')
        php(site,'mikanBoxStorageDeleteData(POSTS_DIR,"p1");')
        check('data.missing:page:p1' in found(site),edition+': an item removed outside mikanBox is found')
        php(site,'mikanBoxIntegrityAccept($CTX,["official"=>$OFFLINE]);')
        check(found(site)==[],edition+': accepting clears data findings')
        php(site,'$GLOBALS["mikanbox_audit_quiet"]=true;saveData(POSTS_DIR,"imp",["title"=>"Imp","status"=>"draft"]);$GLOBALS["mikanbox_audit_quiet"]=false;mikanBoxIntegrityDataKnown($GLOBALS["mikanbox_audit_quiet_after"]);')
        check(found(site)==[],edition+': items written by an import are not findings')
        # --- an old record without a fingerprint (written before 2.26.0) is not "exists after a deletion"
        php(site,'saveData(POSTS_DIR,"legacy",["title"=>"L","status"=>"draft"]);')
        rows=json.loads(php(site,'echo json_encode(array_reverse(mikanBoxAuditRecent()));'))
        rows=[r for r in rows if r.get('target')!='legacy']+[{'event':'page_status','at':'2026-11-08T13:57:03+00:00','actor':'MCP','ip':'1.1.1.0/24','target':'legacy','from':'draft','to':'public_dynamic'}]
        res=json.loads(php(site,'echo json_encode(mikanBoxIntegrityCheckData(mikanBoxIntegrityLoad(),json_decode($argv[2],true))["found"]);'.replace('$argv[2]',json.dumps(json.dumps(rows)))))
        check('page:legacy' not in res['unrecorded'] and 'page:legacy' not in res['changed'],edition+': an old status record without a fingerprint is not reported')
        rows.append({'event':'page_deleted','at':'2026-11-20T00:00:00+00:00','actor':'a','ip':'1.1.1.0/24','target':'legacy'})
        res=json.loads(php(site,'echo json_encode(mikanBoxIntegrityCheckData(mikanBoxIntegrityLoad(),json_decode($argv[2],true))["found"]);'.replace('$argv[2]',json.dumps(json.dumps(rows)))))
        check('page:legacy' in res['unrecorded'],edition+': an item that exists after a deletion record is still reported')
        php(site,'deleteData(POSTS_DIR,"legacy");')
        # --- files earlier versions shipped: reported on their own, removed by the updater
        old=site/'mikanBox/views/data-tool.php';old.write_text('<?php // old')
        good2=json.loads(php(site,'echo json_encode(["files"=>mikanBoxIntegrityPackageHashes(dirname(CORE_DIR),CORE_DIR)]);'))
        good2['files'].pop('mikanBox/views/data-tool.php',None);(site/'mikanData/official2.json').write_text(json.dumps(good2))
        off2='fn()=>json_decode(file_get_contents(DATA_DIR."/official2.json"),true)'
        check(found(site,official=off2)==['program.old:mikanBox/views/data-tool.php'],edition+': a file an earlier version shipped is listed as left over, not as unknown')
        check(php(site,'require_once CORE_DIR."/lib/updater.php";echo mikanBoxUpdateRemoveRetiredProgramFiles(CORE_DIR);').strip()=='1' and not old.exists(),edition+': the updater removes it')
        (site/'mikanBox/views/evil.php').write_text('<?php // not ours')
        check(found(site,official=off2)==['program.added:mikanBox/views/evil.php'] and php(site,'require_once CORE_DIR."/lib/updater.php";echo mikanBoxUpdateRemoveRetiredProgramFiles(CORE_DIR);').strip()=='0',edition+': other unknown files stay unknown and are never removed')
        (site/'mikanBox/views/evil.php').unlink()
        # --- unusual actors
        u=lambda rows: json.loads(php(site,'echo json_encode(mikanBoxAuditUnusual(json_decode($argv[2]?? "[]",true)));'.replace('$argv[2]?? "[]"',json.dumps(json.dumps(rows)))))
        R=lambda a,i:{'actor':a,'ip':i}
        check(u([R('a','1.1.1.0/24')])==[],edition+': the first record of a site is not unusual')
        check(u([R('a','1.1.1.0/24'),R('a','1.1.1.0/24'),R('a','2.2.2.0/24'),R('b','1.1.1.0/24'),R('a','2.2.2.0/24')])==[2,3],edition+': a new person/IP-range combination is unusual once')
        check(u([R('a','1.1.1.0/24'),R('','1.1.1.0/24')])==[1] and u([R('','')])==[0],edition+': an empty actor is always unusual')
        now=int(time.time())
        rows=[{'event':'page_updated','at':time.strftime('%Y-%m-%dT%H:%M:%S+00:00',time.gmtime(now-40*86400)),'actor':'a','ip':'1.1.1.0/24'},
              {'event':'page_updated','at':time.strftime('%Y-%m-%dT%H:%M:%S+00:00',time.gmtime(now-35*86400)),'actor':'a','ip':'9.9.9.0/24'},
              {'event':'page_updated','at':time.strftime('%Y-%m-%dT%H:%M:%S+00:00',time.gmtime(now-1*86400)),'actor':'a','ip':'8.8.8.0/24'}]
        out=json.loads(php(site,'echo json_encode(array_column(mikanBoxIntegrityUnusual(json_decode($argv[2],true)),"ip"));'.replace('$argv[2]',json.dumps(json.dumps(rows)))))
        check(out==['8.8.8.0/24'],edition+': only records of the last 30 days are listed')
        R2=lambda a,i,t:{'actor':a,'ip':i,'at':t}
        ur=lambda rows,until: json.loads(php(site,'echo json_encode(mikanBoxAuditUnusual(json_decode($argv[2],true),'.replace('$argv[2]',json.dumps(json.dumps(rows)))+json.dumps(until)+'));'))
        rr=[R2('a','1.1.1.0/24','2026-11-01T00:00:00+00:00'),R2('b','1.1.1.0/24','2026-11-02T00:00:00+00:00'),R2('c','1.1.1.0/24','2026-11-04T00:00:00+00:00')]
        check(ur(rr,'')==[1,2] and ur(rr,'2026-11-03T00:00:00+00:00')==[2],edition+': records up to an acceptance are no longer unusual; later ones still are')
        php(site,'mikanBoxAudit("page_updated",["target"=>"x"]);$_SERVER["REMOTE_ADDR"]="203.0.113.9";$GLOBALS["mikanbox_audit_actor"]="stranger";mikanBoxAudit("page_updated",["target"=>"y"]);')
        check(json.loads(php(site,'echo json_encode(count(mikanBoxIntegrityCheck($CTX,["official"=>$OFFLINE])["unusual"]));'))>=1,edition+': a stranger is listed')
        php(site,'mikanBoxIntegrityAccept($CTX,["official"=>$OFFLINE]);')
        check(json.loads(php(site,'echo json_encode(count(mikanBoxIntegrityCheck($CTX,["official"=>$OFFLINE])["unusual"]));'))==0,edition+': accepting stops listing the records that were looked at')
        view=(repo/'mikanBox/views/site-sections/mcp-key.php').read_text()
        check('audit-unusual' in view and 'mikanBoxAuditUnusual' in view,edition+': the history tables highlight unusual records')
        check('site-sections/integrity.php' in (repo/'mikanBox/views/site.php').read_text(),edition+': the Site screen shows the check above the version row')
print('integrity_test: %d checks passed'%checks)
