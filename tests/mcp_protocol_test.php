<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/mikanBox/mcp.php';

$failures = [];

function checkMcp($condition, string $message): void {
    global $failures;
    if (!$condition) $failures[] = $message;
}

function modernRequest(int $id, string $method, array $params = []): array {
    $params['_meta'] = [
        'io.modelcontextprotocol/protocolVersion' => MIKANBOX_MCP_PROTOCOL_VERSION,
        'io.modelcontextprotocol/clientInfo' => [
            'name' => 'mikanBox protocol test',
            'version' => '1.0.0',
        ],
        'io.modelcontextprotocol/clientCapabilities' => [],
    ];

    return [
        'jsonrpc' => '2.0',
        'id' => $id,
        'method' => $method,
        'params' => $params,
    ];
}

$discoverRequest = modernRequest(1, 'server/discover');
checkMcp(mcpValidateRequest($discoverRequest) === null, 'server/discover request must validate');

$discover = handleRequest('server/discover', 1, $discoverRequest['params'], []);
checkMcp(($discover['result']['supportedVersions'] ?? null) === ['2026-07-28'], 'discover must expose only 2026-07-28');
checkMcp(($discover['result']['resultType'] ?? null) === 'complete', 'discover must include resultType');
checkMcp(
    ($discover['result']['_meta']['io.modelcontextprotocol/serverInfo']['name'] ?? null) === 'mikanBox MCP',
    'discover must expose serverInfo inside result _meta'
);
checkMcp(($discover['result']['cacheScope'] ?? null) === 'private', 'discover cache must be private');

$discoverWithExtra = modernRequest(9, 'server/discover', ['unexpected' => true]);
checkMcp(
    (mcpValidateRequest($discoverWithExtra)['error']['code'] ?? null) === -32602,
    'server/discover must reject non-meta params'
);

$listRequest = modernRequest(2, 'tools/list');
$list = handleRequest('tools/list', 2, $listRequest['params'], []);
checkMcp(($list['result']['resultType'] ?? null) === 'complete', 'tools/list must include resultType');
checkMcp(is_int($list['result']['ttlMs'] ?? null), 'tools/list must include ttlMs');
checkMcp(($list['result']['cacheScope'] ?? null) === 'private', 'tools/list cache must be private');
checkMcp(count($list['result']['tools'] ?? []) > 0, 'tools/list must return tools');

$callRequest = modernRequest(3, 'tools/call', [
    'name' => 'get_site_info',
    'arguments' => [],
]);
$call = handleRequest('tools/call', 3, $callRequest['params'], [
    'site_id' => 'site-test',
    'site_name' => 'Test site',
    'site_url' => 'https://example.test/',
    'site_environment' => 'staging',
]);
checkMcp(($call['result']['resultType'] ?? null) === 'complete', 'tools/call must include resultType');
checkMcp(($call['result']['isError'] ?? true) === false, 'successful tool call must set isError=false');
checkMcp(
    ($call['result']['structuredContent']['site_id'] ?? null) === 'site-test',
    'tools/call must include structuredContent'
);

$unknown = handleRequest('tools/call', 4, modernRequest(4, 'tools/call', [
    'name' => 'missing_tool',
    'arguments' => [],
])['params'], []);
checkMcp(($unknown['error']['code'] ?? null) === -32602, 'unknown tools must return Invalid params');

checkMcp(
    mcpValidateToolArguments('create_page', ['id' => 'test', 'title' => 'Test', 'status' => 'invalid']) !== null,
    'tool arguments must enforce enum values'
);
checkMcp(
    mcpValidateToolArguments('update_component', ['id' => '_test', 'is_global' => 'yes']) !== null,
    'tool arguments must enforce declared types'
);
checkMcp(
    mcpValidateToolArguments('get_site_info', ['unexpected' => true]) !== null,
    'parameterless tools must reject unknown arguments'
);
checkMcp(
    mcpValidateToolArguments('create_page', ['id' => 'test', 'title' => 'Test', 'sort_order' => 1]) === null,
    'valid tool arguments must pass schema validation'
);

$legacy = [
    'jsonrpc' => '2.0',
    'id' => 5,
    'method' => 'initialize',
    'params' => ['protocolVersion' => '2024-11-05'],
];
$legacyError = mcpValidateRequest($legacy);
checkMcp(($legacyError['error']['code'] ?? null) === -32022, 'legacy initialize must return UnsupportedProtocolVersion');
checkMcp(
    ($legacyError['error']['data']['supported'] ?? null) === ['2026-07-28'],
    'legacy error must advertise the supported version'
);

$missingMeta = [
    'jsonrpc' => '2.0',
    'id' => 6,
    'method' => 'tools/list',
    'params' => [],
];
checkMcp(
    (mcpValidateRequest($missingMeta)['error']['code'] ?? null) === -32602,
    'missing request metadata must be rejected'
);

$missingVersion = modernRequest(7, 'tools/list');
unset($missingVersion['params']['_meta']['io.modelcontextprotocol/protocolVersion']);
checkMcp(
    (mcpValidateRequest($missingVersion)['error']['code'] ?? null) === -32602,
    'missing protocol version must return Invalid params'
);

$wrongVersion = modernRequest(7, 'tools/list');
$wrongVersion['params']['_meta']['io.modelcontextprotocol/protocolVersion'] = '2025-11-25';
checkMcp(
    (mcpValidateRequest($wrongVersion)['error']['code'] ?? null) === -32022,
    'unsupported protocol versions must be rejected'
);

$pingRequest = modernRequest(8, 'ping');
$ping = handleRequest('ping', 8, $pingRequest['params'], []);
checkMcp(($ping['error']['code'] ?? null) === -32601, 'removed ping method must return Method not found');

$_SERVER['HTTP_MCP_PROTOCOL_VERSION'] = '2026-07-28';
$_SERVER['HTTP_MCP_METHOD'] = 'tools/call';
$_SERVER['HTTP_MCP_NAME'] = 'get_site_info';
checkMcp(mcpValidateHttpHeaders($callRequest) === null, 'matching HTTP metadata headers must validate');

$_SERVER['HTTP_MCP_NAME'] = 'wrong_tool';
checkMcp(
    (mcpValidateHttpHeaders($callRequest)['error']['code'] ?? null) === -32020,
    'Mcp-Name mismatch must return HeaderMismatch'
);

$_SERVER['HTTP_MCP_NAME'] = 'get_site_info';
$_SERVER['HTTP_MCP_METHOD'] = 'tools/list';
checkMcp(
    (mcpValidateHttpHeaders($callRequest)['error']['code'] ?? null) === -32020,
    'Mcp-Method mismatch must return HeaderMismatch'
);

$_SERVER['HTTP_HOST'] = 'example.test';
$_SERVER['HTTP_ORIGIN'] = 'https://example.test';
checkMcp(mcpOriginIsAllowed(), 'same-host Origin must be accepted');
$_SERVER['HTTP_ORIGIN'] = 'https://attacker.test';
checkMcp(!mcpOriginIsAllowed(), 'cross-host Origin must be rejected');

if ($failures) {
    fwrite(STDERR, "MCP protocol tests failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "MCP protocol tests passed\n";
