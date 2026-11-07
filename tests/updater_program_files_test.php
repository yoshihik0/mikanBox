<?php

declare(strict_types=1);

// The in-app updater only replaces an explicit list of program files and folders.
// Anything not on that list is never delivered to an installed site, so every
// program file that ships with a release must be reachable from it.

define('MIKANBOX', true);
require_once dirname(__DIR__) . '/mikanBox/lib/updater.php';

$failures = [];
function checkFiles($condition, string $message): void {
    global $failures;
    if (!$condition) $failures[] = $message;
}

$root = sys_get_temp_dir() . '/mikanbox-program-files-' . bin2hex(random_bytes(6));
foreach ([
    'index.php', 'mikanBox/admin.php', 'mikanBox/config.php', 'mikanBox/visual.php', 'mikanBox/mcp.php',
    'mikanBox/lib/functions.php', 'mikanBox/lib/visual-blocks.php', 'mikanBox/lib/visual-history.php',
    'mikanBox/views/pages.php', 'mikanBox/lang/ja.json', 'mikanBox/visual/assets/visual.js', 'mikanBox/visual/assets/visual.css',
    'mikanBox/local-config.php', 'mikanBox/some-other.php', 'mikanData/settings.json',
] as $relative) {
    @mkdir(dirname($root . '/' . $relative), 0777, true);
    file_put_contents($root . '/' . $relative, '');
}
$files = mikanBoxUpdateProgramFiles($root);

foreach (['mikanBox/visual.php', 'mikanBox/lib/visual-blocks.php', 'mikanBox/lib/visual-history.php', 'mikanBox/visual/assets/visual.js', 'mikanBox/visual/assets/visual.css'] as $shipped) {
    checkFiles(in_array($shipped, $files, true), 'the updater delivers ' . $shipped);
}
foreach (['mikanBox/admin.php', 'mikanBox/views/pages.php', 'mikanBox/lang/ja.json'] as $kept) {
    checkFiles(in_array($kept, $files, true), 'existing program files are still delivered: ' . $kept);
}
foreach (['mikanBox/local-config.php', 'mikanBox/some-other.php', 'mikanData/settings.json'] as $never) {
    checkFiles(!in_array($never, $files, true), 'site-specific or unknown files are never replaced: ' . $never);
}
checkFiles(end($files) === 'mikanBox/config.php', 'the files that run the updater are replaced last');

(function (string $dir) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); } @rmdir($dir); })($root);

if ($failures) { fwrite(STDERR, implode("\n", $failures) . "\n"); exit(1); }
echo "Updater program-file list tests passed.\n";
