<?php
// ==========================================
// mikanBox MCP Server (mcp.php)
// CLI (stdio) と HTTP の両方に対応
// ==========================================

error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/functions.php';

// ==========================================
// Helpers
// ==========================================

function mcpResponse($id, $result) {
    return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
}

function mcpErrorResponse($id, $code, $message) {
    return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
}

function toolContent($data) {
    return [
        'content' => [[
            'type' => 'text',
            'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        ]]
    ];
}

// ==========================================
// Tool Definitions
// ==========================================

function toolDefinitions() {
    $noProps = ['type' => 'object', 'properties' => new stdClass(), 'required' => []];

    return [
        [
            'name' => 'list_pages',
            'description' => 'ページ一覧を取得する。ID・タイトル・ステータス・更新日時を返す。',
            'inputSchema' => $noProps,
        ],
        [
            'name' => 'get_page',
            'description' => 'ページの全内容を取得する。',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'description' => 'ページのスラッグ/ID（例: "about", "news/2024"）']
                ],
                'required' => ['id']
            ],
        ],
        [
            'name' => 'create_page',
            'description' => '新規ページを作成する。',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id'           => ['type' => 'string',  'description' => 'スラッグ/ID（英数字・ハイフン・アンダースコア・スラッシュ）'],
                    'title'        => ['type' => 'string',  'description' => 'ページタイトル'],
                    'content_md'   => ['type' => 'string',  'description' => 'ページ本文（MarkdownまたはHTML）'],
                    'status'       => ['type' => 'string',  'description' => 'ステータス: draft / public_dynamic / public_static', 'enum' => ['draft', 'public_dynamic', 'public_static']],
                    'description'  => ['type' => 'string',  'description' => 'メタ description'],
                    'keywords'     => ['type' => 'string',  'description' => 'メタ keywords'],
                    'category'     => ['type' => 'string',  'description' => 'カテゴリ'],
                    'wrapper_comp' => ['type' => 'string',  'description' => 'レイアウトコンポーネントID（省略時: _layout）'],
                    'sort_order'   => ['type' => 'integer', 'description' => '表示順（数値が小さいほど上位）'],
                ],
                'required' => ['id', 'title']
            ],
        ],
        [
            'name' => 'update_page',
            'description' => '既存ページを更新する。指定したフィールドのみ上書きされる。',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id'           => ['type' => 'string',  'description' => '更新対象のスラッグ/ID'],
                    'title'        => ['type' => 'string'],
                    'content_md'   => ['type' => 'string'],
                    'status'       => ['type' => 'string',  'enum' => ['draft', 'public_dynamic', 'public_static']],
                    'description'  => ['type' => 'string'],
                    'keywords'     => ['type' => 'string'],
                    'category'     => ['type' => 'string'],
                    'wrapper_comp' => ['type' => 'string'],
                    'sort_order'   => ['type' => 'integer'],
                    'css'          => ['type' => 'string'],
                    'ogp_image'    => ['type' => 'string'],
                ],
                'required' => ['id']
            ],
        ],
        [
            'name' => 'delete_page',
            'description' => 'ページを削除する。index ページは削除不可。',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'description' => '削除するページのスラッグ/ID']
                ],
                'required' => ['id']
            ],
        ],
        [
            'name' => 'list_components',
            'description' => 'コンポーネント一覧を取得する。',
            'inputSchema' => $noProps,
        ],
        [
            'name' => 'get_component',
            'description' => 'コンポーネントの全内容を取得する。',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'description' => 'コンポーネントID（例: "_header", "_footer"）']
                ],
                'required' => ['id']
            ],
        ],
        [
            'name' => 'update_component',
            'description' => '既存コンポーネントを更新する。',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id'         => ['type' => 'string',  'description' => '更新対象のコンポーネントID'],
                    'html'       => ['type' => 'string',  'description' => 'HTMLテンプレート'],
                    'css'        => ['type' => 'string',  'description' => 'CSS'],
                    'is_global'  => ['type' => 'boolean', 'description' => 'CSSをグローバル適用するか（trueでスコープなし）'],
                    'is_wrapper' => ['type' => 'boolean', 'description' => 'レイアウトラッパーかどうか'],
                ],
                'required' => ['id']
            ],
        ],
        [
            'name' => 'get_settings',
            'description' => 'サイト設定を取得する（パスワードなど機密項目は除外）。',
            'inputSchema' => $noProps,
        ],
        [
            'name' => 'build_ssg',
            'description' => 'public_static のページをすべて静的HTMLとしてビルドする。',
            'inputSchema' => $noProps,
        ],
    ];
}

// ==========================================
// Tool Implementations
// ==========================================

function toolListPages() {
    $ids = getSortedPostIds();
    $pages = [];
    foreach ($ids as $id) {
        $d = loadData(POSTS_DIR, $id);
        if (!$d) continue;
        $pages[] = [
            'id'         => $id,
            'title'      => $d['title']      ?? '',
            'status'     => $d['status']     ?? 'draft',
            'category'   => $d['category']   ?? '',
            'sort_order' => $d['sort_order'] ?? 0,
            'updated_at' => $d['updated_at'] ?? '',
        ];
    }
    return ['pages' => $pages, 'count' => count($pages)];
}

function toolGetPage($id) {
    if (empty($id)) return ['error' => 'id は必須です。'];
    $d = loadData(POSTS_DIR, $id);
    if ($d === null) return ['error' => "ページ '{$id}' が見つかりません。"];
    $d['id'] = $id;
    return $d;
}

function toolCreatePage($args) {
    $id = $args['id'] ?? '';
    if (empty($id))            return ['error' => 'id は必須です。'];
    if (empty($args['title'])) return ['error' => 'title は必須です。'];

    if (loadData(POSTS_DIR, $id) !== null) {
        return ['error' => "ページ '{$id}' はすでに存在します。更新する場合は update_page を使ってください。"];
    }

    $coreDirName = basename(CORE_DIR);
    foreach ([$coreDirName, 'media', 'api'] as $reserved) {
        if (strpos($id . '/', $reserved . '/') === 0 || $id === $reserved) {
            return ['error' => "スラッグ '{$id}' は予約済みです。"];
        }
    }

    $data = buildPageData($args);
    if (saveData(POSTS_DIR, $id, $data)) {
        return ['success' => true, 'id' => $id, 'message' => "ページ '{$id}' を作成しました。"];
    }
    return ['error' => 'ページの保存に失敗しました。'];
}

function toolUpdatePage($args) {
    $id = $args['id'] ?? '';
    if (empty($id)) return ['error' => 'id は必須です。'];

    $existing = loadData(POSTS_DIR, $id);
    if ($existing === null) {
        return ['error' => "ページ '{$id}' が見つかりません。作成する場合は create_page を使ってください。"];
    }

    foreach (['title', 'content_md', 'status', 'description', 'keywords', 'category', 'wrapper_comp', 'sort_order', 'css', 'ogp_image'] as $f) {
        if (array_key_exists($f, $args)) $existing[$f] = $args[$f];
    }
    $existing['updated_at'] = date('Y-m-d H:i:s');

    if (saveData(POSTS_DIR, $id, $existing)) {
        return ['success' => true, 'id' => $id, 'message' => "ページ '{$id}' を更新しました。"];
    }
    return ['error' => 'ページの保存に失敗しました。'];
}

function toolDeletePage($id) {
    if (empty($id))      return ['error' => 'id は必須です。'];
    if ($id === 'index') return ['error' => 'index ページは削除できません。'];
    if (loadData(POSTS_DIR, $id) === null) return ['error' => "ページ '{$id}' が見つかりません。"];

    if (deleteData(POSTS_DIR, $id)) {
        return ['success' => true, 'message' => "ページ '{$id}' を削除しました。"];
    }
    return ['error' => 'ページの削除に失敗しました。'];
}

function toolListComponents() {
    $ids = getFileList(COMPONENTS_DIR);
    sort($ids);
    $components = [];
    foreach ($ids as $id) {
        $d = loadData(COMPONENTS_DIR, $id);
        if (!$d) continue;
        $components[] = [
            'id'          => $id,
            'is_global'   => $d['is_global']  ?? false,
            'is_wrapper'  => $d['is_wrapper'] ?? false,
            'html_length' => strlen($d['html'] ?? ''),
        ];
    }
    return ['components' => $components, 'count' => count($components)];
}

function toolGetComponent($id) {
    if (empty($id)) return ['error' => 'id は必須です。'];
    $d = loadData(COMPONENTS_DIR, $id);
    if ($d === null) return ['error' => "コンポーネント '{$id}' が見つかりません。"];
    $d['id'] = $id;
    return $d;
}

function toolUpdateComponent($args) {
    $id = $args['id'] ?? '';
    if (empty($id)) return ['error' => 'id は必須です。'];

    $existing = loadData(COMPONENTS_DIR, $id);
    if ($existing === null) return ['error' => "コンポーネント '{$id}' が見つかりません。"];

    foreach (['html', 'css', 'is_global', 'is_wrapper'] as $f) {
        if (array_key_exists($f, $args)) $existing[$f] = $args[$f];
    }

    if (saveData(COMPONENTS_DIR, $id, $existing)) {
        return ['success' => true, 'id' => $id, 'message' => "コンポーネント '{$id}' を更新しました。"];
    }
    return ['error' => 'コンポーネントの保存に失敗しました。'];
}

function toolGetSettings() {
    $settings = file_exists(SETTINGS_FILE) ? json_decode(file_get_contents(SETTINGS_FILE), true) : [];
    unset($settings['password_hash'], $settings['mcp_api_key']);
    return $settings;
}

function toolBuildSSG($settings) {
    require_once __DIR__ . '/lib/renderer.php';
    require_once __DIR__ . '/lib/ssg.php';

    $renderer = new MikanBoxRenderer($settings);
    $ssgDir   = $settings['ssg_dir'] ?? ($settings['last_ssg_dir'] ?? '');
    $siteRoot = dirname(CORE_DIR);
    $absPath  = !empty($ssgDir) ? $siteRoot . '/' . ltrim($ssgDir, '/') : $siteRoot;

    $ssgOpts = [
        'structure'      => $settings['ssg_structure'] ?? 'directory',
        'selected_pages' => [],
        'ssg_root_url'   => $settings['ssg_root_url'] ?? '',
        'ssg_dir'        => $ssgDir,
    ];

    $ssg     = new MikanBoxSSG($renderer, $absPath, $ssgOpts);
    $results = $ssg->build();
    $built   = array_values(array_filter($results, fn($r) => strpos($r, 'Error') === false));
    $errors  = array_values(array_filter($results, fn($r) => strpos($r, 'Error') !== false));

    return [
        'success' => empty($errors),
        'built'   => $built,
        'errors'  => $errors,
        'count'   => count($built),
        'message' => count($built) . ' ページをビルドしました。',
    ];
}

function buildPageData($args) {
    return [
        'title'        => $args['title']        ?? '',
        'category'     => trim($args['category']     ?? ''),
        'status'       => $args['status']       ?? 'draft',
        'description'  => $args['description']  ?? '',
        'keywords'     => $args['keywords']     ?? '',
        'ogp_image'    => $args['ogp_image']    ?? '',
        'content_md'   => $args['content_md']   ?? '',
        'css'          => $args['css']          ?? '',
        'wrapper_comp' => $args['wrapper_comp'] ?? '_layout',
        'sort_order'   => (int)($args['sort_order'] ?? 0),
        'updated_at'   => date('Y-m-d H:i:s'),
    ];
}

function executeTool($name, $args, $settings) {
    $GLOBALS['mcp_settings'] = $settings;

    // デモモード中は書き込み系ツールをブロック
    $writeTools = ['create_page', 'update_page', 'delete_page', 'update_component', 'build_ssg'];
    if (!empty($settings['demo_mode']) && in_array($name, $writeTools)) {
        return ['error' => 'デモモードのため保存できません。'];
    }

    return match($name) {
        'list_pages'       => toolListPages(),
        'get_page'         => toolGetPage($args['id'] ?? ''),
        'create_page'      => toolCreatePage($args),
        'update_page'      => toolUpdatePage($args),
        'delete_page'      => toolDeletePage($args['id'] ?? ''),
        'list_components'  => toolListComponents(),
        'get_component'    => toolGetComponent($args['id'] ?? ''),
        'update_component' => toolUpdateComponent($args),
        'get_settings'     => toolGetSettings(),
        'build_ssg'        => toolBuildSSG($settings),
        default            => ['error' => "ツール '{$name}' は存在しません。"]
    };
}

function handleRequest($method, $id, $params, $settings) {
    if ($method === 'initialize') {
        return mcpResponse($id, [
            'protocolVersion' => '2024-11-05',
            'capabilities'    => ['tools' => new stdClass()],
            'serverInfo'      => ['name' => 'mikanBox MCP', 'version' => '1.0'],
        ]);
    }

    if (strpos($method, 'notifications/') === 0) {
        return null; // 返答不要
    }

    if ($method === 'ping') {
        return mcpResponse($id, new stdClass());
    }

    switch ($method) {
        case 'tools/list':
            return mcpResponse($id, ['tools' => toolDefinitions()]);

        case 'tools/call':
            $name   = $params['name']      ?? '';
            $args   = $params['arguments'] ?? [];
            $result = executeTool($name, $args, $settings);
            return mcpResponse($id, toolContent($result));

        default:
            return mcpErrorResponse($id, -32601, "メソッド '{$method}' は存在しません。");
    }
}

// ==========================================
// Transport: stdio (CLI) または HTTP
// ==========================================

if (php_sapi_name() === 'cli') {

    // --- stdio transport ---
    $settings = file_exists(SETTINGS_FILE) ? json_decode(file_get_contents(SETTINGS_FILE), true) : [];

    while (!feof(STDIN)) {
        $line = fgets(STDIN);
        if ($line === false || trim($line) === '') continue;

        $request = json_decode(trim($line), true);
        if (!$request || !isset($request['method'])) continue;

        $response = handleRequest(
            $request['method'],
            $request['id'] ?? null,
            $request['params'] ?? [],
            $settings
        );

        if ($response !== null) {
            echo json_encode($response, JSON_UNESCAPED_UNICODE) . "\n";
            flush();
        }
    }

} else {

    // --- HTTP transport ---
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        http_response_code(405);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(mcpErrorResponse(null, -32700, 'POST リクエストのみ受け付けます。'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $body    = file_get_contents('php://input');
    $request = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($request['method'])) {
        echo json_encode(mcpErrorResponse(null, -32700, 'リクエストのパースに失敗しました。'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $method = $request['method'];
    $id     = $request['id'] ?? null;
    $params = $request['params'] ?? [];

    // initialize と notifications は認証不要
    if ($method === 'initialize' || strpos($method, 'notifications/') === 0 || $method === 'ping') {
        $response = handleRequest($method, $id, $params, []);
        if ($response !== null) {
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            http_response_code(202);
        }
        exit;
    }

    // それ以外は API キー認証
    $settings = file_exists(SETTINGS_FILE) ? json_decode(file_get_contents(SETTINGS_FILE), true) : [];
    $apiKey   = $settings['mcp_api_key'] ?? '';

    if (empty($apiKey)) {
        http_response_code(403);
        echo json_encode(mcpErrorResponse($id, -32001, 'MCP が有効化されていません。settings.json に mcp_api_key を追加してください。'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $authHeader   = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $apiKeyHeader = $_SERVER['HTTP_X_API_KEY'] ?? '';
    $provided = '';
    if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
        $provided = trim($m[1]);
    } elseif (!empty($apiKeyHeader)) {
        $provided = trim($apiKeyHeader);
    } elseif (!empty($_GET['api_key'])) {
        $provided = trim($_GET['api_key']);
    }

    if (!hash_equals($apiKey, $provided)) {
        http_response_code(401);
        echo json_encode(mcpErrorResponse($id, -32001, 'Unauthorized: API キーが正しくありません。'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $response = handleRequest($method, $id, $params, $settings);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
