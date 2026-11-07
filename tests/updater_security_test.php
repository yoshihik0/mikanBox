<?php
// Execute the real updater with only network and file-write fault injection replaced.
define('MIKANBOX', true);
$sourcePath = is_file(dirname(__DIR__) . '/mikanBox/lib/updater.php')
    ? dirname(__DIR__) . '/mikanBox/lib/updater.php' : dirname(__DIR__) . '/lib/updater.php';
$source = file_get_contents($sourcePath);
foreach (['mikanBoxUpdateFetchText', 'mikanBoxUpdateDownload', 'mikanBoxUpdateCopyFile'] as $name) {
    $source = str_replace('function ' . $name . '(', 'function original_' . $name . '(', $source);
}
eval(substr($source, 5));
$sha = str_repeat('a', 40);
$release = ['tag_name'=>'v2.0.0', 'draft'=>false, 'prerelease'=>false, 'published_at'=>'2026-11-07'];
$requests = []; $downloaded = ''; $fault = '';
function mikanBoxUpdateFetchText(string $url, int $timeout = 10): ?string {
    global $release, $requests, $sha;
    $requests[] = $url;
    if (str_contains($url, '/releases/')) return json_encode($release);
    if (str_contains($url, '/commits/v2.0.0')) return json_encode(['sha'=>$sha]);
    throw new RuntimeException('Unexpected network request: ' . $url);
}
function mikanBoxUpdateDownload(string $url, string $destination): bool {
    global $archive, $downloaded;
    $downloaded = $url;
    return copy($archive, $destination);
}
function mikanBoxUpdateCopyFile(string $source, string $target): bool {
    global $fault;
    if ($fault && str_contains($source, '/update-temp/') && str_ends_with($target, '/admin.php')) return false;
    if ($fault === 'rollback' && str_ends_with($source, 'admin.php.bak')) return false;
    return original_mikanBoxUpdateCopyFile($source, $target);
}
$checks=0;
function check($value, string $message) { global $checks; $checks++; if (!$value) throw new RuntimeException($message); }
$root = sys_get_temp_dir() . '/mikanbox-update-security-' . bin2hex(random_bytes(5));
mkdir($root.'/core',0700,true);mkdir($root.'/data',0700);
function installed(string $version): void { global $root; file_put_contents($root.'/core/config.php', "<?php define('MIKANBOX_VERSION', '$version');"); file_put_contents($root.'/core/admin.php','old'); }
$archive=$root.'/release.zip';$zip=new ZipArchive();$zip->open($archive,ZipArchive::CREATE);
$zip->addFromString('package/mikanBox/config.php',"<?php define('MIKANBOX_VERSION', '2.0.0');");
$zip->addFromString('package/mikanBox/admin.php','new');$zip->close();
function install(string $ref='v2.0.0', ?string $version='v2.0.0'): array { global $root; return mikanBoxInstallUpdate('owner/repo','0.0.1',$root.'/core',$root.'/data',$version,$ref); }
try {
    installed('1.0.0');
    check(mikanBoxFetchLatestVersion('owner/repo')['ref']==='v2.0.0','select release');
    check(count($requests)===1 && str_ends_with($requests[0],'/releases/latest'),'no branch/tag discovery');
    foreach (['draft','prerelease'] as $flag) { $release[$flag]=true; check(mikanBoxFetchLatestVersion('owner/repo')['ref']===null,'reject '.$flag); $release[$flag]=false; }
    check(install('main')['code']==='archive_invalid','reject main POST');
    check(install(str_repeat('b',40))['code']==='archive_invalid','reject arbitrary SHA');
    check(install('v3.0.0','v3.0.0')['code']==='release_unavailable','reject substituted tag');
    check(install('v2.0.0',null)['code']==='archive_invalid','require version');
    $oldSha=$sha;$sha='main';check(install()['code']==='release_unavailable','require commit SHA');$sha=$oldSha;
    $lock=fopen($root.'/core/.update.lock','c');flock($lock,LOCK_EX);
    $before=count($requests);
    check(install()['code']==='busy','concurrent install blocked');
    check(mikanBoxRestorePreviousVersion($root.'/core',$root.'/data')['code']==='busy','concurrent restore blocked');
    check(count($requests)===$before && !is_dir($root.'/data/update-temp'),'busy does not fetch or mutate');
    flock($lock,LOCK_UN);fclose($lock);
    try { mikanBoxUpdateWithLock($root.'/core',static function(){throw new RuntimeException('injected');}); } catch(RuntimeException $e) {}
    check(mikanBoxUpdateWithLock($root.'/core',static fn()=>['success'=>true])['success'],'exception releases lock');
    check(is_file($root.'/core/.update.lock'),'lock inode retained');
    check(install()['success'],'install success');
    check(str_ends_with($downloaded,'/archive/'.$sha.'.zip'),'download fixed commit');
    $backup=mikanBoxGetUpdateBackup($root.'/data');
    check($backup['from_version']==='1.0.0','reread actual version, ignore stale caller');
    check($backup['commit']===$sha,'backup records commit');
    check(install()['code']==='no_update','early return releases lock and prevents duplicate update');
    check(mikanBoxRestorePreviousVersion($root.'/core',$root.'/data')['success'],'restore success');
    check(mikanBoxUpdateReadVersion($root.'/core/config.php')==='1.0.0','restored version');
    $fault='install';check(install()['code']==='install_failed','failed copy rolls back');
    check(file_get_contents($root.'/core/admin.php')==='old','old content restored');
    $fault='rollback';check(install()['code']==='rollback_failed','rollback failure reported accurately');
    check(count(glob($root.'/data/update-backups/.pending-*/manifest.json'))===1,'failed rollback backup retained');
    $fault='';check(install()['success'],'lock reusable after failure');
    check(count(glob($root.'/data/update-backups/.pending-*/manifest.json'))===1,'later update preserves recovery backup');
    $backup=mikanBoxGetUpdateBackup($root.'/data');
    file_put_contents($backup['directory'].'/files/core/config.php.bak', "<?php define('MIKANBOX_VERSION', '1.0.0'); define('DATA_DIR', __DIR__ . '/data');");
    $inode=fileinode($root.'/core/.update.lock');
    check(mikanBoxRestorePreviousVersion($root.'/core',$root.'/data')['success'],'legacy data-moving restore succeeds');
    check(is_dir($root.'/core/data') && !is_dir($root.'/data'),'legacy restore moves data');
    clearstatcache();check(fileinode($root.'/core/.update.lock')===$inode,'data move preserves lock inode');
    $lock=fopen($root.'/core/.update.lock','c');flock($lock,LOCK_EX);
    check(mikanBoxRestorePreviousVersion($root.'/core',$root.'/core/data')['code']==='busy','moved data still uses same restore lock');
    flock($lock,LOCK_UN);fclose($lock);
    echo "Updater security: $checks checks passed\n";
} finally { mikanBoxUpdateRemoveTree($root); }
