<?php

declare(strict_types=1);

$repoRoot = dirname(__DIR__);
$tempRoot = sys_get_temp_dir() . '/mikanbox-mcp-http-' . bin2hex(random_bytes(6));
$testKey = 'mikanbox-http-test-key';
$failures = [];
$process = null;

function copyTree(string $source, string $target): void {
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

function removeTree(string $path): void {
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

function checkHttp($condition, string $message): void {
    global $failures;
    if (!$condition) $failures[] = $message;
}

function httpPost(string $url, array $request, array $headers): array {
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headers),
            'content' => json_encode($request, JSON_UNESCAPED_UNICODE),
            'ignore_errors' => true,
            'timeout' => 5,
        ],
    ]);
    $body = file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];
    preg_match('/\s(\d{3})\s/', $responseHeaders[0] ?? '', $match);
    return [
        'status' => isset($match[1]) ? (int)$match[1] : 0,
        'json' => is_string($body) ? json_decode($body, true) : null,
    ];
}

function modernHttpRequest(int $id, string $method, array $params = []): array {
    $params['_meta'] = [
        'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
        'io.modelcontextprotocol/clientInfo' => [
            'name' => 'mikanBox HTTP integration test',
            'version' => '1.0.0',
        ],
        'io.modelcontextprotocol/clientCapabilities' => [],
    ];
    return ['jsonrpc' => '2.0', 'id' => $id, 'method' => $method, 'params' => $params];
}

try {
    copyTree($repoRoot . '/mikanBox', $tempRoot . '/mikanBox');
    copy($repoRoot . '/index.php', $tempRoot . '/index.php');
    mkdir($tempRoot . '/mikanData', 0777, true);
    file_put_contents($tempRoot . '/mikanData/settings.json', json_encode([
        'mcp_api_key' => $testKey,
        'site_id' => 'site-http-test',
        'site_name' => 'HTTP test site',
        'site_url' => 'https://example.test',
        'site_environment' => 'development',
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorNumber, $errorMessage);
    if ($socket === false) throw new RuntimeException("Unable to reserve test port: {$errorMessage}");
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $port = (int)substr(strrchr($address, ':'), 1);

    $command = escapeshellarg(PHP_BINARY)
        . ' -S 127.0.0.1:' . $port
        . ' -t ' . escapeshellarg($tempRoot)
        . ' ' . escapeshellarg($tempRoot . '/index.php');
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);
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

    $url = "http://127.0.0.1:{$port}/mikanBox/mcp";
    $legacyUrl = "http://127.0.0.1:{$port}/mikanBox/mcp.php";
    $baseHeaders = [
        'Content-Type: application/json',
        'Accept: application/json, text/event-stream',
        'MCP-Protocol-Version: 2026-07-28',
        'X-API-Key: ' . $testKey,
    ];

    $discoverRequest = modernHttpRequest(1, 'server/discover');
    $discover = httpPost($url, $discoverRequest, [...$baseHeaders, 'Mcp-Method: server/discover']);
    checkHttp($discover['status'] === 200, 'authenticated server/discover must return HTTP 200');
    checkHttp(
        ($discover['json']['result']['supportedVersions'] ?? null) === ['2026-07-28'],
        'server/discover must advertise 2026-07-28'
    );

    $legacyUrlDiscover = httpPost(
        $legacyUrl,
        $discoverRequest,
        [...$baseHeaders, 'Mcp-Method: server/discover']
    );
    checkHttp($legacyUrlDiscover['status'] === 200, 'mcp.php compatibility URL must remain available');

    $listRequest = modernHttpRequest(2, 'tools/list');
    $list = httpPost($url, $listRequest, [...$baseHeaders, 'Mcp-Method: tools/list']);
    checkHttp($list['status'] === 200, 'authenticated tools/list must return HTTP 200');
    checkHttp(($list['json']['result']['resultType'] ?? null) === 'complete', 'tools/list must return a complete result');

    $callRequest = modernHttpRequest(3, 'tools/call', [
        'name' => 'get_site_info',
        'arguments' => [],
    ]);
    $call = httpPost($url, $callRequest, [
        ...$baseHeaders,
        'Mcp-Method: tools/call',
        'Mcp-Name: get_site_info',
    ]);
    checkHttp($call['status'] === 200, 'authenticated tools/call must return HTTP 200');
    checkHttp(
        ($call['json']['result']['structuredContent']['site_id'] ?? null) === 'site-http-test',
        'tools/call must return the expected site identity'
    );

    $contextRequest = modernHttpRequest(5, 'tools/call', [
        'name' => 'get_ai_context',
        'arguments' => [],
    ]);
    $context = httpPost($url, $contextRequest, [
        ...$baseHeaders,
        'Mcp-Method: tools/call',
        'Mcp-Name: get_ai_context',
    ]);
    checkHttp($context['status'] === 200, 'get_ai_context must return HTTP 200');
    checkHttp(
        is_array($context['json']['result']['structuredContent']['documents'] ?? null),
        'get_ai_context must return a documents array'
    );

    $noAuthHeaders = array_values(array_filter(
        [...$baseHeaders, 'Mcp-Method: tools/list'],
        fn($header) => !str_starts_with($header, 'X-API-Key:')
    ));
    $unauthorized = httpPost($url, $listRequest, $noAuthHeaders);
    checkHttp($unauthorized['status'] === 401, 'missing API key must return HTTP 401');

    $missingHeader = httpPost($url, $listRequest, [
        'Content-Type: application/json',
        'X-API-Key: ' . $testKey,
    ]);
    checkHttp($missingHeader['status'] === 400, 'missing MCP headers must return HTTP 400');
    checkHttp(($missingHeader['json']['error']['code'] ?? null) === -32020, 'missing MCP headers must return HeaderMismatch');

    $legacy = httpPost($url, [
        'jsonrpc' => '2.0',
        'id' => 4,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-11-25',
            'capabilities' => [],
            'clientInfo' => ['name' => 'Legacy integration test', 'version' => '1.0.0'],
        ],
    ], ['Content-Type: application/json', 'X-API-Key: ' . $testKey]);
    checkHttp($legacy['status'] === 200, 'authenticated legacy initialize must return HTTP 200');
    checkHttp(
        ($legacy['json']['result']['protocolVersion'] ?? null) === '2025-11-25',
        'legacy initialize must negotiate 2025-11-25'
    );

    $legacyList = httpPost($url, [
        'jsonrpc' => '2.0',
        'id' => 6,
        'method' => 'tools/list',
        'params' => [],
    ], [
        'Content-Type: application/json',
        'MCP-Protocol-Version: 2025-11-25',
        'X-API-Key: ' . $testKey,
    ]);
    checkHttp($legacyList['status'] === 200, 'authenticated legacy tools/list must return HTTP 200');
    checkHttp(count($legacyList['json']['result']['tools'] ?? []) > 0, 'legacy tools/list must expose tools');
    checkHttp(!isset($legacyList['json']['result']['resultType']), 'legacy response must omit 2026 result metadata');

    $unauthorizedInitialize = httpPost($url, [
        'jsonrpc' => '2.0',
        'id' => 7,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => ['name' => 'Unauthenticated client', 'version' => '1.0.0'],
        ],
    ], ['Content-Type: application/json']);
    checkHttp($unauthorizedInitialize['status'] === 401, 'legacy initialize must still require the API key');

    $wrongContentType = httpPost($url, $discoverRequest, [
        'Content-Type: text/plain',
        'MCP-Protocol-Version: 2026-07-28',
        'Mcp-Method: server/discover',
        'X-API-Key: ' . $testKey,
    ]);
    checkHttp($wrongContentType['status'] === 415, 'non-JSON Content-Type must return HTTP 415');

    $crossOrigin = httpPost($url, $discoverRequest, [
        ...$baseHeaders,
        'Mcp-Method: server/discover',
        'Origin: https://attacker.test',
    ]);
    checkHttp($crossOrigin['status'] === 403, 'cross-origin requests must be rejected');
} catch (Throwable $error) {
    $failures[] = $error->getMessage();
} finally {
    if (is_resource($process)) {
        proc_terminate($process);
        foreach ($pipes ?? [] as $pipe) {
            if (is_resource($pipe)) fclose($pipe);
        }
        proc_close($process);
    }
    removeTree($tempRoot);
}

if ($failures) {
    fwrite(STDERR, "MCP HTTP integration tests failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "MCP HTTP integration tests passed\n";
