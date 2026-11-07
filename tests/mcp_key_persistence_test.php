<?php

declare(strict_types=1);

// The MCP API key must be persisted through the storage layer. Writing it straight
// to settings.json works on the flat edition but silently fails on SQLite, where
// loadSettings() reads the database and only falls back to the JSON file when the
// settings table is empty. The key was then displayed in the admin screen while
// mcp.php kept answering "MCP is not enabled".

$adminPath = dirname(__DIR__) . '/mikanBox/admin.php';
$failures = [];

function checkKeyPersistence($condition, string $message): void {
    global $failures;
    if (!$condition) $failures[] = $message;
}

$source = @file_get_contents($adminPath);
checkKeyPersistence($source !== false, 'admin.php must be readable at ' . $adminPath);

if ($source !== false) {
    // Isolate the generate_mcp_key branch: from its condition to the start of the
    // next save_action branch.
    $start = strpos($source, "elseif (\$_POST['save_action'] === 'generate_mcp_key')");
    checkKeyPersistence($start !== false, 'admin.php must handle generate_mcp_key');
    if ($start !== false) {
        $next = strpos($source, "elseif (\$_POST['save_action']", $start + 1);
        $block = $next === false ? substr($source, $start) : substr($source, $start, $next - $start);

        checkKeyPersistence(
            str_contains($block, 'saveSettings($settings)'),
            'the new key must be persisted with saveSettings() so the storage layer decides where it lands'
        );
        checkKeyPersistence(
            !str_contains($block, 'file_put_contents(SETTINGS_FILE'),
            'the new key must not be written straight to settings.json, which SQLite installs never read back'
        );
    }
}

if ($failures) {
    echo "MCP key persistence tests failed:\n- " . implode("\n- ", $failures) . "\n";
    exit(1);
}
echo "MCP key persistence tests passed.\n";
