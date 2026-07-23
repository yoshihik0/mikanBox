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

// id引数必須チェック（各tool*関数の冒頭で共通利用）
function mcpRequireId($id) {
    return empty($id) ? ['error' => t('mcp_err_id_required')] : null;
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
                    'id'           => ['type' => 'string',  'description' => 'スラッグ/ID（英数字・ハイフン・アンダースコア・スラッシュ）。スラッシュでサブディレクトリ構造になる（例: "news/2024"）'],
                    'title'        => ['type' => 'string',  'description' => 'ページタイトル'],
                    'content_md'   => ['type' => 'string',  'description' => 'ページ本文（MarkdownまたはHTML）'],
                    'status'       => ['type' => 'string',  'description' => 'ステータス: draft / public_dynamic / public_static', 'enum' => ['draft', 'public_dynamic', 'public_static']],
                    'description'  => ['type' => 'string',  'description' => 'メタ description'],
                    'keywords'     => ['type' => 'string',  'description' => 'メタ keywords'],
                    'category'     => ['type' => 'string',  'description' => 'カテゴリ'],
                    'wrapper_comp' => ['type' => 'string',  'description' => 'レイアウトコンポーネントID（省略時: _layout）。{{CONTENT}} タグを含むコンポーネントを指定する'],
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
                    'wrapper_comp' => ['type' => 'string',  'description' => 'レイアウトコンポーネントID。{{CONTENT}} タグを含むコンポーネントを指定する'],
                    'sort_order'   => ['type' => 'integer'],
                    'css'          => ['type' => 'string'],
                    'ogp_image'    => ['type' => 'string'],
                ],
                'required' => ['id']
            ],
        ],
        [
            'name' => 'delete_page',
            'description' => 'ページを削除する。',
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
            'name' => 'create_component',
            'description' => '新規コンポーネントを作成する。',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id'         => ['type' => 'string',  'description' => 'コンポーネントID（英数字・ハイフン・アンダースコア。先頭に _ でグローバル系）'],
                    'html'       => ['type' => 'string',  'description' => 'HTMLテンプレート'],
                    'css'        => ['type' => 'string',  'description' => 'CSS'],
                    'is_global'  => ['type' => 'boolean', 'description' => 'CSSをグローバル適用するか。falseだとCSSが自動スコープされコンポーネント外の要素に当たらなくなる。ページコンテンツのDOMを走査するJS/CSSを持つ場合はtrue必須。'],
                    'is_wrapper' => ['type' => 'boolean', 'description' => 'レイアウトラッパーかどうか（{{CONTENT}}を含むコンポーネントの場合true）'],
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
                    'is_global'  => ['type' => 'boolean', 'description' => 'CSSをグローバル適用するか。falseだとCSSが自動スコープされコンポーネント外の要素に当たらなくなる。ページコンテンツのDOMを走査するJS/CSSを持つ場合はtrue必須。'],
                    'is_wrapper' => ['type' => 'boolean', 'description' => 'レイアウトラッパーかどうか（{{CONTENT}}を含むコンポーネントの場合true）'],
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
            'name' => 'upload_media',
            'description' => '画像をメディアフォルダにアップロードする。',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'filename' => ['type' => 'string', 'description' => 'ファイル名（例: "sample.jpg"）。media/ フォルダ内に保存されます。'],
                    'content_base64' => ['type' => 'string', 'description' => 'Base64エンコードされたファイル内容'],
                    'category' => ['type' => 'string', 'description' => '現在のカテゴリ（例: "blog"）。ファイル名に自動でプレフィックスが付与されます。'],
                ],
                'required' => ['filename', 'content_base64']
            ],
        ],
        [
            'name' => 'build_ssg',
            'description' => 'public_static のページをすべて静的HTMLとしてビルドする。',
            'inputSchema' => $noProps,
        ],
        [
            'name' => 'list_ai_docs',
            'description' => '登録されているAI指示書の一覧を取得する。',
            'inputSchema' => $noProps,
        ],
        [
            'name' => 'get_ai_doc',
            'description' => '指定されたAI指示書のMarkdown本文とCSSを取得する。',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string', 'description' => 'AI指示書ID（例: "design", "claude"）']
                ],
                'required' => ['id']
            ],
        ],
        [
            'name' => 'update_ai_doc',
            'description' => 'AI指示書を作成・更新する。本文は Markdown 形式で指定する。',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'id'   => ['type' => 'string', 'description' => 'AI指示書ID（英数字・ハイフン・アンダースコア）'],
                    'html' => ['type' => 'string', 'description' => 'MarkdownまたはHTML形式の本文'],
                    'css'  => ['type' => 'string', 'description' => 'オプション: 付随するCSS。空欄可。']
                ],
                'required' => ['id', 'html']
            ],
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
    if ($err = mcpRequireId($id)) return $err;
    $d = loadData(POSTS_DIR, $id);
    if ($d === null) return ['error' => t('mcp_err_page_not_found', $id)];
    $d['id'] = $id;
    return $d;
}

function toolCreatePage($args) {
    $id = $args['id'] ?? '';
    if ($err = mcpRequireId($id)) return $err;
    if (empty($args['title']))    return ['error' => t('mcp_err_title_required')];

    if (loadData(POSTS_DIR, $id) !== null) {
        return ['error' => t('mcp_err_page_already_exists', $id)];
    }

    $coreDirName = basename(CORE_DIR);
    foreach ([$coreDirName, 'media', 'api'] as $reserved) {
        if (strpos($id . '/', $reserved . '/') === 0 || $id === $reserved) {
            return ['error' => t('mcp_err_slug_reserved', $id)];
        }
    }

    $data = buildPageData($args);
    if (saveData(POSTS_DIR, $id, $data)) {
        return ['success' => true, 'id' => $id, 'message' => t('mcp_success_page_created', $id)];
    }
    return ['error' => t('mcp_err_page_save_failed')];
}

function toolUpdatePage($args) {
    $id = $args['id'] ?? '';
    if ($err = mcpRequireId($id)) return $err;

    $existing = loadData(POSTS_DIR, $id);
    if ($existing === null) {
        return ['error' => t('mcp_err_page_not_found_for_update', $id)];
    }

    foreach (['title', 'content_md', 'status', 'description', 'keywords', 'category', 'wrapper_comp', 'sort_order', 'css', 'ogp_image'] as $f) {
        if (array_key_exists($f, $args)) $existing[$f] = $args[$f];
    }
    $existing['updated_at'] = date('Y-m-d H:i:s');

    if (saveData(POSTS_DIR, $id, $existing)) {
        return ['success' => true, 'id' => $id, 'message' => t('mcp_success_page_updated', $id)];
    }
    return ['error' => t('mcp_err_page_save_failed')];
}

function toolDeletePage($id) {
    if ($err = mcpRequireId($id)) return $err;
    if (loadData(POSTS_DIR, $id) === null) return ['error' => t('mcp_err_page_not_found', $id)];

    if (deleteData(POSTS_DIR, $id)) {
        return ['success' => true, 'message' => t('mcp_success_page_deleted', $id)];
    }
    return ['error' => t('mcp_err_page_delete_failed')];
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
            'is_ai_doc'   => $d['is_ai_doc']  ?? false,
            'html_length' => strlen($d['html'] ?? ''),
        ];
    }
    return ['components' => $components, 'count' => count($components)];
}

function toolGetComponent($id) {
    if ($err = mcpRequireId($id)) return $err;
    $d = loadData(COMPONENTS_DIR, $id);
    if ($d === null) return ['error' => t('mcp_err_component_not_found', $id)];
    $d['id'] = $id;
    return $d;
}

function toolCreateComponent($args) {
    $id = $args['id'] ?? '';
    if ($err = mcpRequireId($id)) return $err;

    if (loadData(COMPONENTS_DIR, $id) !== null) {
        return ['error' => t('mcp_err_component_already_exists', $id)];
    }

    $data = [
        'html'       => $args['html']       ?? '',
        'css'        => $args['css']        ?? '',
        'is_global'  => $args['is_global']  ?? false,
        'is_wrapper' => $args['is_wrapper'] ?? false,
        'is_ai_doc'  => $args['is_ai_doc']  ?? false,
    ];

    if (saveData(COMPONENTS_DIR, $id, $data)) {
        return ['success' => true, 'id' => $id, 'message' => t('mcp_success_component_created', $id)];
    }
    return ['error' => t('mcp_err_component_save_failed')];
}

function toolUpdateComponent($args) {
    $id = $args['id'] ?? '';
    if ($err = mcpRequireId($id)) return $err;

    $existing = loadData(COMPONENTS_DIR, $id);
    if ($existing === null) return ['error' => t('mcp_err_component_not_found', $id)];

    foreach (['html', 'css', 'is_global', 'is_wrapper', 'is_ai_doc'] as $f) {
        if (array_key_exists($f, $args)) $existing[$f] = $args[$f];
    }

    if (saveData(COMPONENTS_DIR, $id, $existing)) {
        return ['success' => true, 'id' => $id, 'message' => t('mcp_success_component_updated', $id)];
    }
    return ['error' => t('mcp_err_component_save_failed')];
}

function toolListAiDocs() {
    $ids = getFileList(COMPONENTS_DIR);
    sort($ids);
    $docs = [];
    foreach ($ids as $id) {
        $d = loadData(COMPONENTS_DIR, $id);
        if (!$d || empty($d['is_ai_doc'])) continue;
        $docs[] = [
            'id'          => $id,
            'html_length' => strlen($d['html'] ?? ''),
        ];
    }
    return ['ai_docs' => $docs, 'count' => count($docs)];
}

function toolGetAiDoc($id) {
    if ($err = mcpRequireId($id)) return $err;
    if (substr(strtolower($id), -3) !== '.md') {
        $id .= '.md';
    }
    $d = loadData(COMPONENTS_DIR, $id);
    if ($d === null || empty($d['is_ai_doc'])) return ['error' => t('mcp_err_ai_doc_not_found', $id)];
    $d['id'] = $id;
    return $d;
}

function toolUpdateAiDoc($args) {
    $id = $args['id'] ?? '';
    if ($err = mcpRequireId($id)) return $err;
    if (substr(strtolower($id), -3) !== '.md') {
        $id .= '.md';
    }

    $existing = loadData(COMPONENTS_DIR, $id);
    if ($existing === null) {
        // 新規作成
        $data = [
            'html'       => $args['html'] ?? '',
            'css'        => $args['css']  ?? '',
            'is_global'  => false,
            'is_wrapper' => false,
            'is_ai_doc'  => true,
        ];
        if (saveData(COMPONENTS_DIR, $id, $data)) {
            return ['success' => true, 'id' => $id, 'message' => t('mcp_success_ai_doc_created', $id)];
        }
        return ['error' => t('mcp_err_ai_doc_save_failed')];
    }

    // 更新
    if (empty($existing['is_ai_doc'])) {
        return ['error' => t('mcp_err_not_ai_doc', $id)];
    }

    if (array_key_exists('html', $args)) $existing['html'] = $args['html'];
    if (array_key_exists('css', $args))  $existing['css']  = $args['css'];

    if (saveData(COMPONENTS_DIR, $id, $existing)) {
        return ['success' => true, 'id' => $id, 'message' => t('mcp_success_ai_doc_updated', $id)];
    }
    return ['error' => t('mcp_err_ai_doc_save_failed')];
}

function toolGetSettings() {
    $settings = loadSettings();
    unset($settings['password_hash'], $settings['mcp_api_key']);
    return $settings;
}

function toolUploadMedia($args) {
    if (empty($args['filename']))       return ['error' => t('mcp_err_filename_required')];
    if (empty($args['content_base64'])) return ['error' => t('mcp_err_content_base64_required')];

    $filename = basename($args['filename']);
    $content  = base64_decode($args['content_base64']);
    $category = $args['category'] ?? '';

    if ($content === false) return ['error' => t('mcp_err_base64_decode_failed')];

    // Security: Validate Extension (matches admin.php's upload whitelist)
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'mp3', 'm4a', 'mp4'];
    if (!in_array($ext, $allowedExts)) {
        return ['error' => t('mcp_err_invalid_extension', $ext)];
    }

    if ($ext === 'svg') {
        $content = sanitizeSvgContent($content);
    }

    if (!is_dir(MEDIA_DIR)) {
        if (!mkdir(MEDIA_DIR, 0777, true)) {
            return ['error' => t('mcp_err_media_dir_create_failed')];
        }
    }

    $resolvedName = resolveMediaSaveName($filename, $category);
    $dest = MEDIA_DIR . '/' . $resolvedName;
    if (file_put_contents($dest, $content)) {
        return ['success' => true, 'filename' => $resolvedName, 'message' => t('mcp_success_media_uploaded', $resolvedName)];
    }
    return ['error' => t('mcp_err_file_save_failed')];
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
        'output_mode'    => $settings['ssg_mode'] ?? 'server',
        'link_mode'      => $settings['ssg_link_mode'] ?? 'absolute',
        'copy_media'     => ($settings['ssg_mode'] ?? 'server') === 'export',
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
        'message' => t('mcp_success_ssg_built', count($built)),
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
    $writeTools = ['create_page', 'update_page', 'delete_page', 'create_component', 'update_component', 'upload_media', 'build_ssg', 'update_ai_doc'];
    if (!empty($settings['demo_mode']) && in_array($name, $writeTools)) {
        return ['error' => t('mcp_err_demo_mode')];
    }

    return match($name) {
        'list_pages'       => toolListPages(),
        'get_page'         => toolGetPage($args['id'] ?? ''),
        'create_page'      => toolCreatePage($args),
        'update_page'      => toolUpdatePage($args),
        'delete_page'      => toolDeletePage($args['id'] ?? ''),
        'list_components'  => toolListComponents(),
        'get_component'    => toolGetComponent($args['id'] ?? ''),
        'create_component' => toolCreateComponent($args),
        'update_component' => toolUpdateComponent($args),
        'list_ai_docs'     => toolListAiDocs(),
        'get_ai_doc'       => toolGetAiDoc($args['id'] ?? ''),
        'update_ai_doc'    => toolUpdateAiDoc($args),
        'upload_media'     => toolUploadMedia($args),
        'get_settings'     => toolGetSettings(),
        'build_ssg'        => toolBuildSSG($settings),
        default            => ['error' => t('mcp_err_tool_not_found', $name)]
    };
}

function handleRequest($method, $id, $params, $settings) {
    if ($method === 'initialize') {
        return mcpResponse($id, [
            'protocolVersion' => '2024-11-05',
            'capabilities'    => ['tools' => new stdClass()],
            'serverInfo'      => ['name' => 'mikanBox MCP', 'version' => MIKANBOX_VERSION],
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
            return mcpErrorResponse($id, -32601, t('mcp_err_method_not_found', $method));
    }
}

// ==========================================
// Transport: stdio (CLI) または HTTP
// ==========================================

if (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    if (php_sapi_name() === 'cli') {
        // --- stdio transport ---
        $settings = loadSettings();

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
            echo json_encode(mcpErrorResponse(null, -32700, t('mcp_err_post_only')), JSON_UNESCAPED_UNICODE);
            exit;
        }

        $body    = file_get_contents('php://input');
        $request = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($request['method'])) {
            echo json_encode(mcpErrorResponse(null, -32700, t('mcp_err_parse_failed')), JSON_UNESCAPED_UNICODE);
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
        $settings = loadSettings();
        $apiKey   = $settings['mcp_api_key'] ?? '';

        if (empty($apiKey)) {
            http_response_code(403);
            echo json_encode(mcpErrorResponse($id, -32001, t('mcp_err_mcp_disabled')), JSON_UNESCAPED_UNICODE);
            exit;
        }

        // クエリパラメータでのAPIキー受付は行わない（アクセスログ・リファラに残るため）。
        // ヘッダー（Authorization: Bearer または X-API-Key）のみを受け付ける。
        $authHeader   = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $apiKeyHeader = $_SERVER['HTTP_X_API_KEY'] ?? '';
        $provided = '';
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $m)) {
            $provided = trim($m[1]);
        } elseif (!empty($apiKeyHeader)) {
            $provided = trim($apiKeyHeader);
        }

        if (!hash_equals($apiKey, $provided)) {
            http_response_code(401);
            echo json_encode(mcpErrorResponse($id, -32001, t('mcp_err_unauthorized')), JSON_UNESCAPED_UNICODE);
            exit;
        }

        $response = handleRequest($method, $id, $params, $settings);
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
