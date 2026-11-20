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

const MIKANBOX_MCP_KEY_NAME_MAX = 40;
function mikanBoxMcpEnabled(): bool { return true; }
function mikanBoxMcpLastUsed(string $id): int { return 0; }
function mikanBoxMcpKeyUpgrade(): void {}
function mikanBoxMcpKeyList(array $settings): array {
    $list = [];
    foreach ((array)($settings['keys'] ?? []) as $i => $k) $list[] = ['id' => 'id' . $i, 'name' => $k['name'], 'hash' => 'sha256:x', 'created' => $k['created'] ?? 0];
    return $list;
}
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

// The accordion is collapsed and keys are issued over AJAX: the place the new key is shown, and the
// form that issues it, are always in the markup, so the first key has somewhere to appear.
$html = renderMcpKeySection([]);
checkMcpKeyView((bool)preg_match('/id="mcp-key-issued" style="display:none;"/', $html), 'the issued block exists, hidden');
checkMcpKeyView(str_contains($html, 'id="mcp-key-display"') && str_contains($html, 'value="" readonly'), 'the key field exists and starts empty');
checkMcpKeyView(str_contains($html, 'name="mcp_key_name"') && str_contains($html, 'value="generate_mcp_key"'), 'the issue form has a name field');
checkMcpKeyView(str_contains($html, 'mcp_key_none') && !str_contains($html, 'revoke_mcp_key'), 'with no keys there is a note and nothing to revoke');

// Keys are listed by name, each with a revoke form; names are escaped; unnamed keys get a label.
$html = renderMcpKeySection(['keys' => [['name' => '"><script>alert(1)</script>', 'created' => 86400], ['name' => '']]]);
checkMcpKeyView(!str_contains($html, '<script>alert(1)</script>') && str_contains($html, '&quot;&gt;&lt;script&gt;'), 'key names are HTML-escaped');
checkMcpKeyView(substr_count($html, 'value="revoke_mcp_key"') === 2, 'every key has a revoke form');
checkMcpKeyView(str_contains($html, 'mcp_key_unnamed') && str_contains($html, '1970-01-02'), 'an unnamed key is labelled and the issue date is shown');
checkMcpKeyView(str_contains($html, 'name="mcp_key_id" value="id1"'), 'revoking sends the key id');

if ($failures) {
    echo "MCP key view tests failed:\n- " . implode("\n- ", $failures) . "\n";
    exit(1);
}
echo "MCP key view tests passed.\n";
