<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$tempRoot = sys_get_temp_dir() . '/mikanbox-public-mcp-http-' . bin2hex(random_bytes(6));
$failures = [];
$process = null;

function publicMcpCopyTree(string $source, string $target): void {
    if (!is_dir($target)) mkdir($target, 0777, true);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $destination = $target . '/' . $iterator->getSubPathName();
        if ($item->isDir()) {
            if (!is_dir($destination)) mkdir($destination, 0777, true);
        } else {
            copy($item->getPathname(), $destination);
        }
    }
}

function publicMcpRemoveTree(string $path): void {
    if (!is_dir($path)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($path);
}

function publicMcpCheck($condition, string $message): void {
    global $failures;
    if (!$condition) $failures[] = $message;
}

function publicMcpRequest(string $url, string $method = 'GET', ?array $json = null, array $headers = []): array {
    $options = [
        'method' => $method,
        'header' => implode("\r\n", $headers),
        'ignore_errors' => true,
        'timeout' => 5,
    ];
    if ($json !== null) $options['content'] = json_encode($json, JSON_UNESCAPED_UNICODE);
    $context = stream_context_create(['http' => $options]);
    $body = file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $match);
    return [
        'status' => isset($match[1]) ? (int)$match[1] : 0,
        'body' => is_string($body) ? $body : '',
        'json' => is_string($body) ? json_decode($body, true) : null,
    ];
}

function publicMcpModernRequest(int $id, string $method, array $params = []): array {
    $params['_meta'] = [
        'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
        'io.modelcontextprotocol/clientInfo' => ['name' => 'public MCP test', 'version' => '1.0'],
        'io.modelcontextprotocol/clientCapabilities' => [],
    ];
    return ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params];
}

try {
    publicMcpCopyTree($repoRoot . '/mikanBox', $tempRoot . '/mikanBox');
    copy($repoRoot . '/index.php', $tempRoot . '/index.php');

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);
    if ($socket === false) throw new RuntimeException("Unable to reserve test port: {$errorMessage}");
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int)substr(strrchr($address, ':'), 1);

    $command = escapeshellarg(PHP_BINARY)
        . ' -S 127.0.0.1:' . $port
        . ' -t ' . escapeshellarg($tempRoot)
        . ' ' . escapeshellarg($tempRoot . '/index.php');
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Unable to start PHP test server.');

    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
        if (is_resource($connection)) {
            fclose($connection);
            $ready = true;
            break;
        }
        usleep(100000);
    }
    if (!$ready) throw new RuntimeException('PHP test server did not start.');

    $url = "http://127.0.0.1:{$port}/mcp";
    $manifest = publicMcpRequest($url . '?action=manifest&language=ja');
    publicMcpCheck($manifest['status'] === 200, 'public manifest GET must return HTTP 200');
    publicMcpCheck(($manifest['json']['mode'] ?? null) === 'public-read-only', 'manifest must declare public-read-only mode');
    publicMcpCheck(($manifest['json']['mcp_endpoint'] ?? null) === $url, 'manifest must advertise the root /mcp endpoint');

    $legacyManifest = publicMcpRequest("http://127.0.0.1:{$port}/mikanBox/public-mcp.php?action=manifest&language=ja");
    publicMcpCheck($legacyManifest['status'] === 200, 'legacy public-mcp.php endpoint must remain available');

    $search = publicMcpRequest($url . '?action=search_help&language=ja&query=' . rawurlencode('MCP APIキー'));
    publicMcpCheck($search['status'] === 200, 'public help search GET must return HTTP 200');
    publicMcpCheck(count($search['json']['results'] ?? []) > 0, 'public help search must return manual sections');

    $initialize = publicMcpRequest($url, 'POST', [
        'jsonrpc' => '2.0',
        'id' => 10,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-11-25',
            'capabilities' => [],
            'clientInfo' => ['name' => 'Claude-compatible test', 'version' => '1.0'],
        ],
    ], ['Content-Type: application/json', 'Accept: application/json, text/event-stream']);
    publicMcpCheck($initialize['status'] === 200, 'legacy initialize must succeed without MCP metadata headers');
    publicMcpCheck(($initialize['json']['result']['protocolVersion'] ?? null) === '2025-11-25', 'initialize must negotiate the requested supported version');
    publicMcpCheck(isset($initialize['json']['result']['capabilities']['tools']), 'initialize must advertise read-only tools');

    $claudeInitialize = publicMcpRequest($url, 'POST', [
        'jsonrpc' => '2.0',
        'id' => 13,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => ['name' => 'Claude-compatible test', 'version' => '1.0'],
        ],
    ], ['Content-Type: application/json', 'Accept: application/json, text/event-stream']);
    publicMcpCheck(($claudeInitialize['json']['result']['protocolVersion'] ?? null) === '2025-06-18', 'Claude-compatible initialize version must be accepted');

    $initialized = publicMcpRequest($url, 'POST', [
        'jsonrpc' => '2.0',
        'method' => 'notifications/initialized',
    ], ['Content-Type: application/json', 'MCP-Protocol-Version: 2025-11-25']);
    publicMcpCheck($initialized['status'] === 202, 'initialized notification must return HTTP 202');
    publicMcpCheck($initialized['body'] === '', 'initialized notification response must have no body');

    $legacyList = publicMcpRequest($url, 'POST', [
        'jsonrpc' => '2.0',
        'id' => 11,
        'method' => 'tools/list',
        'params' => [],
    ], ['Content-Type: application/json', 'MCP-Protocol-Version: 2025-11-25']);
    publicMcpCheck($legacyList['status'] === 200, 'legacy tools/list must not require 2026 routing headers');
    publicMcpCheck(count($legacyList['json']['result']['tools'] ?? []) === 4, 'legacy tools/list must expose four read-only tools');
    publicMcpCheck(!isset($legacyList['json']['result']['resultType']), 'legacy responses must not include 2026-only result metadata');

    $legacyDefault = publicMcpRequest($url, 'POST', [
        'jsonrpc' => '2.0',
        'id' => 12,
        'method' => 'ping',
        'params' => [],
    ], ['Content-Type: application/json']);
    publicMcpCheck($legacyDefault['status'] === 200, 'missing legacy version header must fall back to 2025-03-26');

    $sseGet = publicMcpRequest($url, 'GET', null, ['Accept: text/event-stream']);
    publicMcpCheck($sseGet['status'] === 405, 'GET event stream must return 405 when SSE is not offered');

    $listRequest = publicMcpModernRequest(1, 'tools/list');
    $list = publicMcpRequest($url, 'POST', $listRequest, [
        'Content-Type: application/json',
        'MCP-Protocol-Version: 2026-07-28',
        'Mcp-Method: tools/list',
    ]);
    $toolNames = array_column($list['json']['result']['tools'] ?? [], 'name');
    sort($toolNames);
    publicMcpCheck($list['status'] === 200, 'public tools/list must return HTTP 200 without an API key');
    publicMcpCheck($toolNames === ['get_agent_instructions', 'get_help_section', 'get_product_info', 'search_help'], 'public tools/list must expose exactly four read-only tools');
    publicMcpCheck(!in_array('create_page', $toolNames, true), 'public tools/list must not expose administration tools');

    $adminCall = publicMcpModernRequest(2, 'tools/call', ['name' => 'create_page', 'arguments' => ['id' => 'bad', 'title' => 'Bad']]);
    $rejected = publicMcpRequest($url, 'POST', $adminCall, [
        'Content-Type: application/json',
        'MCP-Protocol-Version: 2026-07-28',
        'Mcp-Method: tools/call',
        'Mcp-Name: create_page',
    ]);
    publicMcpCheck(($rejected['json']['error']['code'] ?? null) === -32602, 'public endpoint must reject administration tool names');
    publicMcpCheck(!is_file($tempRoot . '/mikanData/posts/bad.json'), 'rejected administration calls must not write data');

    $crossOrigin = publicMcpRequest($url . '?action=manifest', 'GET', null, ['Origin: https://attacker.test']);
    publicMcpCheck($crossOrigin['status'] === 403, 'cross-origin browser requests must be rejected');
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
        foreach ($pipes ?? [] as $pipe) if (is_resource($pipe)) fclose($pipe);
        proc_close($process);
    }
    publicMcpRemoveTree($tempRoot);
}

if ($failures) {
    fwrite(STDERR, "Public MCP HTTP integration tests failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Public MCP HTTP integration tests passed\n";
