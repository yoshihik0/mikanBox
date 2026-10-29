<?php

declare(strict_types=1);

// The MCP key section lives inside a collapsed <details> accordion, and the key is
// issued over AJAX. Both states must therefore always be present in the markup:
// if the key field only appeared once a key existed, the very first key would have
// nowhere to be shown, and reloading to reveal it would close the accordion.

define('MIKANBOX', true);

$viewPath = dirname(__DIR__) . '/mikanBox/views/site-sections/mcp-key.php';

function t(string $key, ...$args): string { return $key; }
function getIcon(string $name): string { return ''; }
function csrfField(): string { return '<input type="hidden" name="csrf" value="test">'; }

function mikanBoxMcpEnabled(): bool { return true; }
function mikanBoxMcpLastUsed(string $key): int { return 0; }
function mikanBoxAuditRecent(): array { return []; }

function renderMcpKeySection(array $settings): string {
    global $viewPath;
    ob_start();
    include $viewPath;
    return (string)ob_get_clean();
}

$failures = [];

function checkMcpKeyView($condition, string $message): void {
    global $failures;
    if (!$condition) $failures[] = $message;
}

// No key yet: the field must exist so the AJAX handler has somewhere to write.
$html = renderMcpKeySection([]);
checkMcpKeyView(str_contains($html, 'id="mcp-key-display"'), 'the key field must be rendered before any key exists');
checkMcpKeyView(str_contains($html, 'value="" readonly'), 'the key field must start empty');
checkMcpKeyView((bool)preg_match('/id="mcp-key-issued" style="display:none;"/', $html), 'the issued block must be hidden before any key exists');
checkMcpKeyView((bool)preg_match('/id="mcp-key-generate"(?! style="display:none)/', $html), 'the generate button must be visible before any key exists');

// Key present: the issued block is shown and the generate button is put away.
$html = renderMcpKeySection(['mcp_api_key' => 'abc123DEADBEEF']);
checkMcpKeyView(str_contains($html, 'value="abc123DEADBEEF"'), 'an existing key must be rendered into the field');
checkMcpKeyView((bool)preg_match('/id="mcp-key-issued"(?! style="display:none)/', $html), 'the issued block must be visible once a key exists');
checkMcpKeyView((bool)preg_match('/id="mcp-key-generate" style="display:none;"/', $html), 'the generate button must be hidden once a key exists');

// The key is attacker-influenced only via the admin, but it still lands in an
// attribute, so it must be escaped.
$html = renderMcpKeySection(['mcp_api_key' => '"><script>alert(1)</script>']);
checkMcpKeyView(!str_contains($html, '<script>alert(1)</script>'), 'the key value must be HTML-escaped');

if ($failures) {
    echo "MCP key view tests failed:\n- " . implode("\n- ", $failures) . "\n";
    exit(1);
}
echo "MCP key view tests passed.\n";
