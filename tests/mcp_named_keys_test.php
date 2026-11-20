<?php
declare(strict_types=1);

// Named MCP keys: the list functions in lib/security.php (no storage involved).
define('MIKANBOX', true);
$path = is_file(dirname(__DIR__) . '/mikanBox/lib/security.php') ? dirname(__DIR__) . '/mikanBox/lib/security.php' : dirname(__DIR__) . '/lib/security.php';
$source = (string)file_get_contents($path);
$start = strpos($source, 'const MIKANBOX_MCP_KEY_LIMIT');
$end = strpos($source, '/** $id: the key\'s id.');
eval(substr($source, $start, $end - $start));

$failures = [];
function check($ok, string $m): void { global $failures; if (!$ok) $failures[] = $m; }

$s = [];
check(mikanBoxMcpKeyList($s) === [], 'no key: empty list');
$k1 = mikanBoxMcpKeyAdd($s, "  Claude\n Code  ");
$k2 = mikanBoxMcpKeyAdd($s, 'Codex');
$list = mikanBoxMcpKeyList($s);
check(count($list) === 2 && $list[0]['name'] === 'Claude Code' && $list[1]['name'] === 'Codex', 'two named keys, names cleaned');
check(!str_contains(json_encode($s), $k1) && !str_contains(json_encode($s), $k2), 'the keys themselves are never stored');
check(mikanBoxMcpKeyFind($s, $k2)['name'] === 'Codex' && mikanBoxMcpKeyFind($s, 'wrong') === null && mikanBoxMcpKeyFind($s, '') === null, 'a key finds its own record only');
check(mikanBoxMcpKeyRevoke($s, $list[0]['id']) && mikanBoxMcpKeyFind($s, $k1) === null && mikanBoxMcpKeyFind($s, $k2) !== null, 'revoking one key leaves the other');
check(mikanBoxMcpKeyRevoke($s, $list[1]['id']) && $s['mcp_api_key'] === '', 'revoking the last key leaves an empty setting');
check(!mikanBoxMcpKeyRevoke($s, 'nope'), 'an unknown id revokes nothing');

// The key version 2.28 stored: a fingerprint string. (A plain-text key, from before 2.28, is not accepted.)
$old = ['mcp_api_key' => mikanBoxMcpKeyStore('legacy')];
check(mikanBoxMcpKeyFind($old, 'legacy') !== null && mikanBoxMcpKeyList($old)[0]['name'] === '', 'an old single key still works, without a name');
$plain = ['mcp_api_key' => 'plainkey'];
check(mikanBoxMcpKeyFind($plain, 'plainkey') === null && mikanBoxMcpKeyList($plain) === [], 'a plain-text key is not accepted');
$k3 = mikanBoxMcpKeyAdd($old, 'New');
check(count(mikanBoxMcpKeyList($old)) === 2 && mikanBoxMcpKeyFind($old, 'legacy') !== null && mikanBoxMcpKeyFind($old, $k3) !== null, 'adding to an old single key keeps it');

for ($i = 0; $i < 30; $i++) mikanBoxMcpKeyAdd($s, 'k' . $i);
check(count(mikanBoxMcpKeyList($s)) === MIKANBOX_MCP_KEY_LIMIT && mikanBoxMcpKeyAdd($s, 'over') === '', 'the number of keys is limited');
check(mb_strlen(mikanBoxMcpKeyCleanName(str_repeat('あ', 100))) === MIKANBOX_MCP_KEY_NAME_MAX, 'names are limited in length');

if ($failures) { echo "Named MCP key tests failed:\n- " . implode("\n- ", $failures) . "\n"; exit(1); }
echo "Named MCP key tests passed.\n";
