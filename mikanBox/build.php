<?php
/**
 * MikanBox CLI Build Script
 * Usage: php mikanBox/build.php [output_dir]
 */

// Check if run from CLI
if (php_sapi_name() !== 'cli') {
    die("Access Denied: This script can only be run from the command line.\n");
}

define('CORE_DIR', __DIR__);
require_once CORE_DIR . '/config.php';
require_once CORE_DIR . '/lib/functions.php';
require_once CORE_DIR . '/lib/renderer.php';
require_once CORE_DIR . '/lib/ssg.php';

$outputDirName = $argv[1] ?? '';
// Path calculation relative to root
$outputDir = ($outputDirName === '') ? dirname(CORE_DIR) : dirname(CORE_DIR) . '/' . $outputDirName;

echo "=== MikanBox Static Site Builder ===\n";
echo "Output Directory: $outputDir\n";

$renderer = new MikanBoxRenderer($GLOBALS['mikanbox_settings']);
$ssg = new MikanBoxSSG($renderer, $outputDir, [
    'structure' => $GLOBALS['mikanbox_settings']['ssg_structure'] ?? 'directory'
]);

$results = $ssg->build();

foreach ($results as $line) {
    echo $line . "\n";
}

echo "Done!\n";
