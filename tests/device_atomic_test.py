"""Fault injection against a disposable SQLite edition; no live data or HTTP."""
import pathlib, subprocess, shutil, tempfile
repo=pathlib.Path(__file__).resolve().parents[1]
with tempfile.TemporaryDirectory(prefix='mikanbox-device-atomic-') as tmp:
    root=pathlib.Path(tmp)
    for name in subprocess.check_output(['git','ls-files'],cwd=repo,text=True).splitlines():
        if name.startswith('mikanBox/') and (repo/name).is_file():
            dst=root/name;dst.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(repo/name,dst)
    code=r'''
require $argv[1].'/mikanBox/config.php'; require_once $argv[1].'/mikanBox/lib/functions.php'; require_once $argv[1].'/mikanBox/lib/device-auth.php';
$db=getDb();$db->exec("INSERT INTO users(username,display_name) VALUES('owner','Owner'),('other','Other')");
mikanBoxDeviceCreate('owner','test','setup');$device=mikanBoxDeviceList()[0];$code=mikanBoxDeviceIssueCode($device,true);
foreach (['stop'=>['DELETE ON device_codes','UPDATE ON device_sessions'], 'delete'=>['DELETE ON device_codes','DELETE ON device_sessions','DELETE ON users']] as $operation=>$faults) {
 foreach($faults as $fault) {
  $db->exec("CREATE TRIGGER injected BEFORE $fault BEGIN SELECT RAISE(ABORT,'injected'); END");
  $failed=false;try {if($operation==='stop')mikanBoxDeviceRevoke($device,$device['id']);else mikanBoxDeviceForgetUser('owner');}catch(RuntimeException $e){$failed=true;}
  if(!$failed||mikanBoxDeviceActiveCount()!==1||mikanBoxDeviceFindCode($code)===null||(int)$db->querySingle('SELECT COUNT(*) FROM users')!==2)throw new RuntimeException('Rollback failed: '.$fault);
  $db->exec('DROP TRIGGER injected');
 }
}
mikanBoxDeviceRevoke($device,$device['id']);if(mikanBoxDeviceFindCode($code)!==null||mikanBoxDeviceActiveCount()!==0)throw new RuntimeException('Stop failed');
mikanBoxDeviceCreate('owner','second','setup');$code=mikanBoxDeviceIssueCode(mikanBoxDeviceList()[0],true);mikanBoxDeviceForgetUser('owner');if(mikanBoxDeviceFindCode($code)!==null||mikanBoxDeviceActiveCount()!==0||(int)$db->querySingle('SELECT COUNT(*) FROM users')!==1)throw new RuntimeException('Delete failed');
echo "Five injected failures rolled back; stop and deletion succeeded after recovery.\n";
'''
    subprocess.run(['php','-r',code,str(root)],check=True)
