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
function mikanBoxMcpKeyUpgrade(): void {}
function mikanBoxAuditRecent(): array { return []; }
function mikanBoxAuditUnusual(array $rows): array { return []; }
function mikanBoxAuditGroup(string $event): string { return $event === 'login_failed' ? 'login' : ($event === 'page_status' ? 'edit' : 'important'); }

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
checkMcpKeyView(str_contains($html, 'value="" placeholder='), 'the key field must start empty');
checkMcpKeyView((bool)preg_match('/id="mcp-key-issued" style="display:none;"/', $html), 'the issued block must be hidden before any key exists');
checkMcpKeyView((bool)preg_match('/id="mcp-key-generate"(?! style="display:none)/', $html), 'the generate button must be visible before any key exists');

// Key present: the issued block is shown and the generate button is put away.
// Only the fingerprint is stored, and the field never shows what settings hold.
$html = renderMcpKeySection(['mcp_api_key' => 'sha256:' . str_repeat('ab', 32)]);
checkMcpKeyView(!str_contains($html, str_repeat('ab', 32)), 'the stored fingerprint is never rendered');
checkMcpKeyView(str_contains($html, 'value="" placeholder="mcp_key_hidden_placeholder"'), 'an existing key is not shown: the field is empty with a note');
checkMcpKeyView((bool)preg_match('/id="mcp-key-issued"(?! style="display:none)/', $html), 'the issued block must be visible once a key exists');
checkMcpKeyView((bool)preg_match('/id="mcp-key-generate" style="display:none;"/', $html), 'the generate button must be hidden once a key exists');
checkMcpKeyView((bool)preg_match('/id="mcp-key-once" style="display:none;"/', $html), 'the shown-once note is hidden when no key was just issued');

// Just issued without ajax: the key is shown once from the session, escaped, then forgotten.
$_SESSION['mikanbox_mcp_new_key'] = '"><script>alert(1)</script>';
$html = renderMcpKeySection(['mcp_api_key' => 'sha256:' . str_repeat('cd', 32)]);
checkMcpKeyView(!str_contains($html, '<script>alert(1)</script>') && str_contains($html, '&quot;&gt;&lt;script&gt;'), 'the just-issued key is shown once, HTML-escaped');
checkMcpKeyView((bool)preg_match('/id="mcp-key-once"(?! style="display:none)/', $html), 'the shown-once note is visible with it');
checkMcpKeyView(!isset($_SESSION['mikanbox_mcp_new_key']), 'the key is forgotten after it is shown');
$html = renderMcpKeySection(['mcp_api_key' => 'sha256:' . str_repeat('cd', 32)]);
checkMcpKeyView(!str_contains($html, 'alert(1)'), 'it is not shown a second time');

if ($failures) {
    echo "MCP key view tests failed:\n- " . implode("\n- ", $failures) . "\n";
    exit(1);
}
echo "MCP key view tests passed.\n";
