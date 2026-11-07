<?php
// Real migration, with a database-trigger failure and every PHP warning treated as failure.
error_reporting(E_ALL);
set_error_handler(static function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
$repo = is_file(dirname(__DIR__).'/mikanBox/lib/functions.php') ? dirname(__DIR__) : dirname(__DIR__,2).'/mikanBox-sqlite';
$source=file_get_contents($repo.'/mikanBox/lib/functions.php');
foreach(['importFolderToSqlite','migrateJsonToSqlite'] as $function) {
 $start=strpos($source,'function '.$function.'(');$end=strpos($source,"\nfunction ",$start+1);
 eval(substr($source,$start,$end-$start));
}
$root=sys_get_temp_dir().'/mikanbox-migration-exceptions-'.bin2hex(random_bytes(5));mkdir($root,0700);
define('DATA_DIR',$root);define('DB_FILE',$root.'/db.sqlite');define('SETTINGS_FILE',$root.'/settings.json');define('POSTS_DIR',$root.'/posts');define('COMPONENTS_DIR',$root.'/components');
function mikanBoxProtectDataDirectory($dir){return is_dir($dir)||mkdir($dir,0700,true);}
function removeFixture($path){if(is_dir($path)){foreach(scandir($path) as $name) if($name!=='.'&&$name!=='..') removeFixture($path.'/'.$name);rmdir($path);}else unlink($path);}
function verify($condition,$message){if(!$condition)throw new RuntimeException($message);}
$db=new SQLite3(DB_FILE);
try {
 $db->exec('CREATE TABLE settings (key TEXT PRIMARY KEY,value TEXT)');
 $db->exec('CREATE TABLE components (id TEXT PRIMARY KEY,data TEXT)');
 $db->exec('CREATE TABLE posts (id TEXT PRIMARY KEY,title TEXT,category TEXT,status TEXT,sort_order INTEGER,updated_at TEXT,data TEXT)');
 $db->exec("CREATE TRIGGER fail_import BEFORE INSERT ON components BEGIN SELECT RAISE(ABORT,'injected migration failure'); END");
 mkdir(COMPONENTS_DIR);file_put_contents(COMPONENTS_DIR.'/part.json','{"html":"test"}');file_put_contents(SETTINGS_FILE,'{"site_name":"retained"}');
 $failed=false;try{migrateJsonToSqlite($db);}catch(Exception $error){$failed=str_contains($error->getMessage(),'injected migration failure');}
 verify($failed,'original error preserved');
 verify($db->querySingle('SELECT COUNT(*) FROM settings')===0,'earlier settings insert rolled back');
 verify(is_file(SETTINGS_FILE)&&is_file(COMPONENTS_DIR.'/part.json'),'original files retained');
 verify(is_file(DATA_DIR.'/json-migration-pending.php'),'retry marker retained');
 $db->exec('DROP TRIGGER fail_import');migrateJsonToSqlite($db);
 verify($db->querySingle('SELECT COUNT(*) FROM settings')===1,'retry succeeds');
 verify(!is_file(SETTINGS_FILE)&&!is_file(DATA_DIR.'/json-migration-pending.php'),'successful import archived');
 verify(count(glob(DATA_DIR.'/private-import/*/settings.json'))===1,'one protected source backup');
 echo "SQLite migration exception/rollback/retry: passed without warnings\n";
}finally{$db->close();restore_error_handler();removeFixture($root);}
