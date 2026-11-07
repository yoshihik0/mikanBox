<?php

declare(strict_types=1);

// The release archive always ships index.php with the default core folder name.
// An installed site may have renamed that folder, and the front end goes down if
// the shipped default is installed as-is, so the updater bakes the real name in.

define('MIKANBOX', true);
require_once dirname(__DIR__) . '/mikanBox/lib/updater.php';

$failures = [];

function checkCoreDir($condition, string $message): void {
    global $failures;
    if (!$condition) $failures[] = $message;
}

function makePackage(string $root, string $indexBody): void {
    if (!is_dir($root)) mkdir($root, 0777, true);
    file_put_contents($root . '/index.php', $indexBody);
}

function readCoreDir(string $path): ?string {
    $source = (string)@file_get_contents($path);
    return preg_match('/\$core_dir\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/', $source, $m) ? $m[1] : null;
}

$base = sys_get_temp_dir() . '/mikanbox-coredir-' . bin2hex(random_bytes(6));
$shipped = "<?php\n\$core_dir = 'mikanBox';\nforeach ([\$core_dir, 'admin', 'system'] as \$dir) {}\n";

// A renamed core folder is written into the staged file.
$pkg = $base . '/renamed';
makePackage($pkg, $shipped);
checkCoreDir(
    mikanBoxUpdatePrepareFrontController($pkg, '/var/www/html/mikanBoxCore') === true,
    'a renamed core folder must be accepted'
);
checkCoreDir(readCoreDir($pkg . '/index.php') === 'mikanBoxCore', 'the renamed folder must be written into index.php');

// The default name needs no rewrite, and the file must stay byte-identical.
$pkg = $base . '/default';
makePackage($pkg, $shipped);
checkCoreDir(
    mikanBoxUpdatePrepareFrontController($pkg, '/var/www/html/mikanBox') === true,
    'the default core folder must be accepted'
);
checkCoreDir(file_get_contents($pkg . '/index.php') === $shipped, 'the default core folder must leave index.php untouched');

// A trailing slash on CORE_DIR must not leak into the written name.
$pkg = $base . '/trailing';
makePackage($pkg, $shipped);
mikanBoxUpdatePrepareFrontController($pkg, '/var/www/html/secret-core/');
checkCoreDir(readCoreDir($pkg . '/index.php') === 'secret-core', 'a trailing slash must not affect the written name');

// Names that cannot be written safely are rejected rather than installed wrong.
$pkg = $base . '/unsafe';
makePackage($pkg, $shipped);
checkCoreDir(
    mikanBoxUpdatePrepareFrontController($pkg, "/var/www/html/core'; system('x');") === false,
    'an unsafe folder name must be rejected'
);

// An archive whose index.php no longer matches the expected shape is reported
// instead of being installed with the wrong core folder.
$pkg = $base . '/unexpected';
makePackage($pkg, "<?php\n// no core dir assignment here\n");
checkCoreDir(
    mikanBoxUpdatePrepareFrontController($pkg, '/var/www/html/mikanBoxCore') === false,
    'an index.php without the expected assignment must be rejected'
);

// A package without index.php is not an error; the file simply is not shipped.
$pkg = $base . '/noindex';
if (!is_dir($pkg)) mkdir($pkg, 0777, true);
checkCoreDir(
    mikanBoxUpdatePrepareFrontController($pkg, '/var/www/html/mikanBoxCore') === true,
    'a package without index.php must be accepted'
);

// Replaced files must be immediately visible even with OPcache timestamp checks disabled.
$source = $base . '/new-config.php';
$target = $base . '/installed-config.php';
file_put_contents($source, "<?php return '2.8.2';");
file_put_contents($target, "<?php return '2.8.1';");
checkCoreDir((include $target) === '2.8.1', 'the original program is executed before replacement');
if (function_exists('opcache_compile_file') && opcache_get_status(false) !== false) {
    checkCoreDir(opcache_compile_file($target), 'the old program is cached before replacement');
}
checkCoreDir(mikanBoxUpdateCopyFile($source, $target), 'the new program is installed atomically');
checkCoreDir((include $target) === '2.8.2', 'the next execution uses the new program without waiting for OPcache');
file_put_contents($source, "<?php return '2.8.1';");
checkCoreDir(mikanBoxUpdateCopyFile($source, $target), 'the previous program is restored atomically');
checkCoreDir((include $target) === '2.8.1', 'restoration also invalidates the cached new program');
file_put_contents($target, "<?php define('MIKANBOX_VERSION', '2.8.2');");
checkCoreDir(mikanBoxUpdateReadVersion($target) === '2.8.2', 'display version is read from the installed source');

mikanBoxUpdateRemoveTree($base);

if ($failures) {
    echo "Updater core directory tests failed:\n- " . implode("\n- ", $failures) . "\n";
    exit(1);
}
echo "Updater core directory tests passed.\n";
