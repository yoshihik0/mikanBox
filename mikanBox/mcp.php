<?php
// ==========================================
// mikanBox MCP Server (mcp.php)
// CLI (stdio) と HTTP の両方に対応
// ==========================================

error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/functions.php';

const MIKANBOX_MCP_PROTOCOL_VERSION = '2026-07-28';
const MIKANBOX_MCP_LEGACY_PROTOCOL_VERSIONS = ['2025-11-25', '2025-06-18', '2025-03-26'];
const MIKANBOX_MCP_LIST_TTL_MS = 300000;
const MIKANBOX_MCP_DISCOVERY_TTL_MS = 3600000;

// ==========================================
// Helpers
// ==========================================

function mcpResponse($id, $result) {
    $meta = $result['_meta'] ?? [];
    if (!is_array($meta)) $meta = [];
    $meta['io.modelcontextprotocol/serverInfo'] = [
        'name' => 'mikanBox MCP',
        'version' => MIKANBOX_VERSION,
    ];

    $result['resultType'] = $result['resultType'] ?? 'complete';
    $result['_meta'] = $meta;

    return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
}

function mcpErrorResponse($id, $code, $message, $data = null) {
    $error = ['code' => $code, 'message' => $message];
    if ($data !== null) $error['data'] = $data;
    return ['jsonrpc' => '2.0', 'id' => $id, 'error' => $error];
}

function mcpUnsupportedVersionResponse($id, $requested = null) {
    $data = ['supported' => [MIKANBOX_MCP_PROTOCOL_VERSION, ...MIKANBOX_MCP_LEGACY_PROTOCOL_VERSIONS]];
    if (is_string($requested) && $requested !== '') $data['requested'] = $requested;

    return mcpErrorResponse(
        $id,
        -32022,
        'Unsupported MCP protocol version.',
        $data
    );
}

function mcpValidateInitialize($request) {
    $id = $request['id'] ?? null;
    $params = $request['params'] ?? null;
    if (($request['jsonrpc'] ?? null) !== '2.0'
        || ($request['method'] ?? null) !== 'initialize'
        || !array_key_exists('id', $request)
        || $id === null
        || !is_array($params)) {
        return mcpErrorResponse($id, -32600, 'Invalid Request');
    }
    if (!is_string($params['protocolVersion'] ?? null)
        || !is_array($params['capabilities'] ?? null)
        || !is_array($params['clientInfo'] ?? null)
        || !is_string($params['clientInfo']['name'] ?? null)
        || !is_string($params['clientInfo']['version'] ?? null)) {
        return mcpErrorResponse($id, -32602, 'Invalid initialize params.');
    }
    return null;
}

function mcpInitializeResponse($request) {
    $requested = (string)$request['params']['protocolVersion'];
    $version = in_array($requested, MIKANBOX_MCP_LEGACY_PROTOCOL_VERSIONS, true)
        ? $requested
        : MIKANBOX_MCP_LEGACY_PROTOCOL_VERSIONS[0];
    return [
        'jsonrpc' => '2.0',
        'id' => $request['id'],
        'result' => [
            'protocolVersion' => $version,
            'capabilities' => ['tools' => ['listChanged' => false]],
            'serverInfo' => [
                'name' => 'mikanBox MCP',
                'version' => MIKANBOX_VERSION,
            ],
            'instructions' => '初期接続時にget_site_infoで対象サイトを確認し、続けてget_ai_contextを呼んでください。書き込み前にもget_site_infoで対象サイトを再確認してください。',
        ],
    ];
}

function mcpValidateLegacyRequest($request) {
    $id = $request['id'] ?? null;
    if (($request['jsonrpc'] ?? null) !== '2.0'
        || !is_string($request['method'] ?? null)
        || $request['method'] === ''
        || !array_key_exists('id', $request)
        || $id === null) {
        return mcpErrorResponse($id, -32600, 'Invalid Request');
    }
    if (array_key_exists('params', $request) && !is_array($request['params'])) {
        return mcpErrorResponse($id, -32602, 'Invalid params.');
    }
    $version = mcpHttpHeader('MCP-Protocol-Version');
    if ($version === '') $version = '2025-03-26';
    if (!in_array($version, MIKANBOX_MCP_LEGACY_PROTOCOL_VERSIONS, true)) {
        return mcpUnsupportedVersionResponse($id, $version);
    }
    return null;
}

function mcpLegacyResponse($response) {
    if (isset($response['result']) && is_array($response['result'])) {
        unset(
            $response['result']['resultType'],
            $response['result']['_meta'],
            $response['result']['ttlMs'],
            $response['result']['cacheScope']
        );
    }
    return $response;
}

function mcpRequestMeta($params) {
    if (!is_array($params)) return null;
    $meta = $params['_meta'] ?? null;
    return is_array($meta) ? $meta : null;
}

function mcpValidateRequest($request) {
    $id = $request['id'] ?? null;

    if (($request['jsonrpc'] ?? null) !== '2.0'
        || !is_string($request['method'] ?? null)
        || $request['method'] === ''
        || !array_key_exists('id', $request)
        || $request['id'] === null) {
        return mcpErrorResponse($id, -32600, 'Invalid Request');
    }

    $method = $request['method'];
    $params = $request['params'] ?? null;

    $meta = mcpRequestMeta($params);
    if ($meta === null) {
        return mcpErrorResponse($id, -32602, 'Invalid params: params._meta is required.');
    }

    $requested = $meta['io.modelcontextprotocol/protocolVersion'] ?? null;
    if (!is_string($requested) || $requested === '') {
        return mcpErrorResponse(
            $id,
            -32602,
            'Invalid params: io.modelcontextprotocol/protocolVersion is required.'
        );
    }
    if ($requested !== MIKANBOX_MCP_PROTOCOL_VERSION) {
        return mcpUnsupportedVersionResponse($id, $requested);
    }

    if (!array_key_exists('io.modelcontextprotocol/clientCapabilities', $meta)
        || !is_array($meta['io.modelcontextprotocol/clientCapabilities'])) {
        return mcpErrorResponse(
            $id,
            -32602,
            'Invalid params: io.modelcontextprotocol/clientCapabilities is required.'
        );
    }

    if ($method === 'server/discover') {
        $extraParams = array_diff(array_keys($params), ['_meta']);
        if ($extraParams) {
            return mcpErrorResponse(
                $id,
                -32602,
                'Invalid params: server/discover accepts only standard _meta.'
            );
        }
    }

    return null;
}

function mcpHttpHeader($name) {
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    if (isset($_SERVER[$key])) return trim((string)$_SERVER[$key]);

    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $headerName => $value) {
            if (strcasecmp($headerName, $name) === 0) return trim((string)$value);
        }
    }
    return '';
}

function mcpDecodeHeaderValue($value) {
    if (str_starts_with($value, '=?base64?') && str_ends_with($value, '?=')) {
        $decoded = base64_decode(substr($value, 9, -2), true);
        return $decoded === false ? null : $decoded;
    }
    return $value;
}

function mcpValidateHttpHeaders($request) {
    $id = $request['id'] ?? null;
    $method = $request['method'] ?? '';
    $params = $request['params'] ?? [];
    $meta = mcpRequestMeta($params) ?? [];

    $protocolHeader = mcpHttpHeader('MCP-Protocol-Version');
    $methodHeader = mcpHttpHeader('Mcp-Method');
    $bodyVersion = $meta['io.modelcontextprotocol/protocolVersion'] ?? null;

    if ($protocolHeader === '' || $protocolHeader !== $bodyVersion) {
        return mcpErrorResponse($id, -32020, 'Header mismatch: MCP-Protocol-Version is missing or does not match the request body.');
    }
    if ($methodHeader === '' || $methodHeader !== $method) {
        return mcpErrorResponse($id, -32020, 'Header mismatch: Mcp-Method is missing or does not match the request body.');
    }

    if ($method === 'tools/call') {
        $nameHeader = mcpHttpHeader('Mcp-Name');
        $decodedName = $nameHeader === '' ? null : mcpDecodeHeaderValue($nameHeader);
        $bodyName = is_array($params) ? ($params['name'] ?? null) : null;
        if ($decodedName === null || $decodedName !== $bodyName) {
            return mcpErrorResponse($id, -32020, 'Header mismatch: Mcp-Name is missing, malformed, or does not match the request body.');
        }
    }

    return null;
}

function mcpOriginIsAllowed() {
    $origin = mcpHttpHeader('Origin');
    if ($origin === '') return true;

    $originHost = parse_url($origin, PHP_URL_HOST);
    $requestHost = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    return is_string($originHost)
        && $originHost !== ''
        && $requestHost !== ''
        && strcasecmp($originHost, $requestHost) === 0;
}

// id引数必須チェック（各tool*関数の冒頭で共通利用）
function mcpRequireId($id) {
    return empty($id) ? ['error' => t('mcp_err_id_required')] : null;
}

function toolContent($data) {
    return [
        'resultType' => 'complete',
        'content' => [[
            'type' => 'text',
            'text' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        ]],
        'structuredContent' => $data,
        'isError' => is_array($data) && array_key_exists('error', $data),
    ];
}

// ==========================================
// Tool Definitions
// ==========================================

// 各ツールの性質。MCPクライアントはこれを見て確認ダイアログの要否を判断するため、
// ツールを追加したら必ずここにも追加する。読み書きの分類はこの表が唯一の正で、
// executeTool() のデモモード判定もここから導出される（mcpWriteToolNames() を参照）。
//   readOnlyHint         … 環境を変更しない
//   destructiveHint      … 既存データを上書き・削除しうる（readOnlyHint=false のときのみ意味を持つ）
//   idempotentHint       … 同じ引数で繰り返しても結果が変わらない（同上）
//   untrustedContentHint … 戻り値に利用者・AIが書いた本文が含まれ、指示として扱ってはならない
function mcpToolAnnotations(): array {
    return [
        // 読み取り専用
        'get_site_info'    => ['readOnlyHint' => true, 'untrustedContentHint' => false],
        'get_settings'     => ['readOnlyHint' => true, 'untrustedContentHint' => false],
        'list_pages'       => ['readOnlyHint' => true, 'untrustedContentHint' => true],
        'get_page'         => ['readOnlyHint' => true, 'untrustedContentHint' => true],
        'list_components'  => ['readOnlyHint' => true, 'untrustedContentHint' => true],
        'get_component'    => ['readOnlyHint' => true, 'untrustedContentHint' => true],
        'get_ai_context'   => ['readOnlyHint' => true, 'untrustedContentHint' => true],
        'list_ai_docs'     => ['readOnlyHint' => true, 'untrustedContentHint' => true],
        'get_ai_doc'       => ['readOnlyHint' => true, 'untrustedContentHint' => true],

        // 書き込み
        // create_* は新規作成のみで既存を壊さない。同じIDで再実行すると失敗するため非冪等。
        'create_page'      => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false],
        'create_component' => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false],
        // update_* / delete_* は既存データを上書き・削除する。同じ引数なら結果は同じ。
        'update_page'      => ['readOnlyHint' => false, 'destructiveHint' => true,  'idempotentHint' => true],
        'delete_page'      => ['readOnlyHint' => false, 'destructiveHint' => true,  'idempotentHint' => true],
        'update_component' => ['readOnlyHint' => false, 'destructiveHint' => true,  'idempotentHint' => true],
        'update_ai_doc'    => ['readOnlyHint' => false, 'destructiveHint' => true,  'idempotentHint' => true],
        // resolveMediaSaveName() が連番で重複を避けるため既存ファイルを上書きしない。
        // 同じ画像を2回送ると別名で2つ保存されるので非冪等。
        'upload_media'     => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => false],
        // 出力先を作り直すが、元データからいつでも再生成できるため非破壊。
        'build_ssg'        => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true],
    ];
}

// 書き込み系ツール名。mcpToolAnnotations() から導出するので、分類を二重に持たない。
function mcpWriteToolNames(): array {
    $names = [];
    foreach (mcpToolAnnotations() as $name => $annotations) {
        if (empty($annotations['readOnlyHint'])) $names[] = $name;
    }
    return $names;
}

function toolDefinitions() {
    $noProps = [
        'type' => 'object',
        'properties' => new stdClass(),
        'required' => [],
        'additionalProperties' => false,
    ];

    $tools = [
        [
            'name' => 'get_site_info',
            'description' => '現在接続しているmikanBoxサイトの不変ID・サイト名・URL・環境を取得する。複数サイト利用時は、他のツールを使う前に必ずこの情報で対象サイトを確認する。',
            'inputSchema' => $noProps,
        ],
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
            'name' => 'get_ai_context',
            'description' => 'このサイト固有のAI指示コンポーネント（DESIGN.md、BRAND.md、CONTENTS.mdなど）をすべて一括取得する。接続直後、および内容・デザイン・コードの変更を計画する前に呼び、返された指示に従う。',
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

    $annotations = mcpToolAnnotations();
    foreach ($tools as &$tool) {
        $tool['inputSchema']['additionalProperties'] = false;
        // 対象はこのサイト自身のコンテンツに限られ、外部サービスは操作しない。
        $tool['annotations'] = ($annotations[$tool['name']] ?? []) + ['openWorldHint' => false];
    }
    unset($tool);

    return $tools;
}

function mcpValidateToolArguments($name, $args) {
    $definition = null;
    foreach (toolDefinitions() as $tool) {
        if ($tool['name'] === $name) {
            $definition = $tool;
            break;
        }
    }
    if ($definition === null) return t('mcp_err_tool_not_found', $name);

    $schema = $definition['inputSchema'];
    $properties = is_array($schema['properties'] ?? null) ? $schema['properties'] : [];

    foreach ($schema['required'] ?? [] as $required) {
        if (!array_key_exists($required, $args)) {
            return "Invalid params: {$required} is required.";
        }
    }

    if (($schema['additionalProperties'] ?? true) === false) {
        $unknown = array_diff(array_keys($args), array_keys($properties));
        if ($unknown) {
            return 'Invalid params: unknown argument ' . reset($unknown) . '.';
        }
    }

    foreach ($args as $key => $value) {
        if (!isset($properties[$key])) continue;
        $property = $properties[$key];
        $type = $property['type'] ?? null;
        $validType = match($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_int($value) || is_float($value),
            'boolean' => is_bool($value),
            'object' => is_array($value),
            'array' => is_array($value),
            default => true,
        };
        if (!$validType) {
            return "Invalid params: {$key} must be {$type}.";
        }
        if (isset($property['enum']) && !in_array($value, $property['enum'], true)) {
            return "Invalid params: {$key} has an unsupported value.";
        }
    }

    return null;
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

function toolGetAiContext() {
    $ids = getFileList(COMPONENTS_DIR);
    sort($ids);
    $documents = [];
    foreach ($ids as $id) {
        $d = loadData(COMPONENTS_DIR, $id);
        if (!$d || empty($d['is_ai_doc'])) continue;
        $documents[] = [
            'id' => $id,
            'content_md' => (string)($d['html'] ?? ''),
        ];
    }
    return [
        'documents' => $documents,
        'count' => count($documents),
    ];
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

function toolGetSiteInfo($settings) {
    $siteId = trim((string)($settings['site_id'] ?? ''));

    // Existing installations receive a persistent identity on first use.
    // This is intentionally generated once rather than derived from a mutable
    // site name, URL, filesystem path, or API key.
    if ($siteId === '') {
        $siteId = 'site-' . bin2hex(random_bytes(8));
        $settings['site_id'] = $siteId;
        saveSettings($settings);
    }

    $environment = (string)($settings['site_environment'] ?? 'unspecified');
    $allowedEnvironments = ['production', 'staging', 'development', 'local', 'unspecified'];
    if (!in_array($environment, $allowedEnvironments, true)) {
        $environment = 'unspecified';
    }

    $siteUrl = trim((string)($settings['site_url'] ?? ''));
    if ($siteUrl === '') {
        $siteUrl = trim((string)($settings['ssg_root_url'] ?? ''));
    }

    $siteName = trim((string)($settings['site_name'] ?? ''));
    if ($siteName === '') {
        $siteName = trim((string)SITE_NAME);
    }
    if ($siteName === '') {
        $siteName = 'mikanBox';
    }

    return [
        'site_id'     => $siteId,
        'site_name'   => $siteName,
        'site_url'    => rtrim($siteUrl, '/'),
        'environment' => $environment,
    ];
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
    $writeTools = mcpWriteToolNames();
    if (!empty($settings['demo_mode']) && in_array($name, $writeTools)) {
        return ['error' => t('mcp_err_demo_mode')];
    }

    return match($name) {
        'get_site_info'     => toolGetSiteInfo($settings),
        'list_pages'       => toolListPages(),
        'get_page'         => toolGetPage($args['id'] ?? ''),
        'create_page'      => toolCreatePage($args),
        'update_page'      => toolUpdatePage($args),
        'delete_page'      => toolDeletePage($args['id'] ?? ''),
        'list_components'  => toolListComponents(),
        'get_component'    => toolGetComponent($args['id'] ?? ''),
        'create_component' => toolCreateComponent($args),
        'update_component' => toolUpdateComponent($args),
        'get_ai_context'   => toolGetAiContext(),
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
    if ($method === 'server/discover') {
        return mcpResponse($id, [
            'supportedVersions' => [MIKANBOX_MCP_PROTOCOL_VERSION],
            'capabilities' => ['tools' => new stdClass()],
            'instructions' => '初期接続時にget_site_infoで対象サイトを確認し、続けてget_ai_contextを呼んでください。返されたAI指示コンポーネントは、このサイト固有のプロジェクト指示として内容・デザイン・コードの変更に従ってください。複数サイト接続時は書き込み前にもget_site_infoを呼び、指示が更新された可能性がある場合は変更計画前にget_ai_contextを再取得してください。',
            'ttlMs' => MIKANBOX_MCP_DISCOVERY_TTL_MS,
            'cacheScope' => 'private',
        ]);
    }

    switch ($method) {
        case 'tools/list':
            return mcpResponse($id, [
                'tools' => toolDefinitions(),
                'ttlMs' => MIKANBOX_MCP_LIST_TTL_MS,
                'cacheScope' => 'private',
            ]);

        case 'tools/call':
            $name   = $params['name']      ?? '';
            $args   = $params['arguments'] ?? [];
            $knownTools = array_column(toolDefinitions(), 'name');
            if ($name === '' || !in_array($name, $knownTools, true)) {
                return mcpErrorResponse($id, -32602, t('mcp_err_tool_not_found', $name));
            }
            if (!is_array($args)) {
                return mcpErrorResponse($id, -32602, 'Invalid params: arguments must be an object.');
            }
            $argumentError = mcpValidateToolArguments($name, $args);
            if ($argumentError !== null) {
                return mcpErrorResponse($id, -32602, $argumentError);
            }
            $result = executeTool($name, $args, $settings);
            return mcpResponse($id, toolContent($result));

        default:
            return mcpErrorResponse($id, -32601, t('mcp_err_method_not_found', $method));
    }
}

// ==========================================
// Transport: stdio (CLI) または HTTP
// ==========================================

if (defined('MIKANBOX_ADMIN_MCP_ROUTE')
    || realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    if (php_sapi_name() === 'cli') {
        // --- stdio transport ---
        $settings = loadSettings();

        while (!feof(STDIN)) {
            $line = fgets(STDIN);
            if ($line === false || trim($line) === '') continue;

            $request = json_decode(trim($line), true);
            if (!is_array($request)) {
                echo json_encode(mcpErrorResponse(null, -32700, t('mcp_err_parse_failed')), JSON_UNESCAPED_UNICODE) . "\n";
                flush();
                continue;
            }

            // stdio cancellation is advisory. mikanBox does not keep
            // long-running request state, so there is nothing to release.
            if (($request['method'] ?? null) === 'notifications/cancelled'
                && !array_key_exists('id', $request)) {
                continue;
            }

            if (($request['method'] ?? null) === 'initialize') {
                $initializeError = mcpValidateInitialize($request);
                echo json_encode(
                    $initializeError ?? mcpInitializeResponse($request),
                    JSON_UNESCAPED_UNICODE
                ) . "\n";
                flush();
                continue;
            }

            if (($request['method'] ?? null) === 'notifications/initialized'
                && !array_key_exists('id', $request)) {
                continue;
            }

            $requestMeta = mcpRequestMeta($request['params'] ?? []) ?? [];
            $isModernRequest = ($requestMeta['io.modelcontextprotocol/protocolVersion'] ?? null)
                === MIKANBOX_MCP_PROTOCOL_VERSION;

            $validationError = $isModernRequest
                ? mcpValidateRequest($request)
                : mcpValidateLegacyRequest($request);
            if ($validationError !== null) {
                echo json_encode($validationError, JSON_UNESCAPED_UNICODE) . "\n";
                flush();
                continue;
            }

            $response = handleRequest(
                $request['method'],
                $request['id'] ?? null,
                $request['params'] ?? [],
                $settings
            );

            if ($response !== null) {
                if (!$isModernRequest) $response = mcpLegacyResponse($response);
                echo json_encode($response, JSON_UNESCAPED_UNICODE) . "\n";
                flush();
            }
        }
    } else {
        // --- HTTP transport ---
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Accept, Authorization, X-API-Key, MCP-Protocol-Version, Mcp-Method, Mcp-Name');

        if (!mcpOriginIsAllowed()) {
            http_response_code(403);
            echo json_encode(mcpErrorResponse(null, -32000, 'Forbidden origin.'), JSON_UNESCAPED_UNICODE);
            exit;
        }

        $origin = mcpHttpHeader('Origin');
        if ($origin !== '') {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }

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

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $mediaType = strtolower(trim(explode(';', $contentType, 2)[0]));
        if ($mediaType !== 'application/json') {
            http_response_code(415);
            echo json_encode(mcpErrorResponse(null, -32600, 'Content-Type must be application/json.'), JSON_UNESCAPED_UNICODE);
            exit;
        }

        $body    = file_get_contents('php://input');
        $request = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($request)) {
            http_response_code(400);
            echo json_encode(mcpErrorResponse(null, -32700, t('mcp_err_parse_failed')), JSON_UNESCAPED_UNICODE);
            exit;
        }

        $method = $request['method'];
        $id     = $request['id'] ?? null;
        $params = $request['params'] ?? [];

        // すべてのMCPリクエストでAPIキー認証
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

        if ($method === 'initialize') {
            $initializeError = mcpValidateInitialize($request);
            if ($initializeError !== null) {
                http_response_code(400);
                echo json_encode($initializeError, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                exit;
            }
            echo json_encode(mcpInitializeResponse($request), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }

        if ($method === 'notifications/initialized' && !array_key_exists('id', $request)) {
            $version = mcpHttpHeader('MCP-Protocol-Version');
            if ($version !== '' && !in_array($version, MIKANBOX_MCP_LEGACY_PROTOCOL_VERSIONS, true)) {
                http_response_code(400);
                echo json_encode(mcpUnsupportedVersionResponse(null, $version), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                exit;
            }
            http_response_code(202);
            exit;
        }

        $requestMeta = mcpRequestMeta($request['params'] ?? []) ?? [];
        $bodyProtocolVersion = $requestMeta['io.modelcontextprotocol/protocolVersion'] ?? null;
        $headerProtocolVersion = mcpHttpHeader('MCP-Protocol-Version');
        $isModernRequest = $bodyProtocolVersion === MIKANBOX_MCP_PROTOCOL_VERSION
            || $headerProtocolVersion === MIKANBOX_MCP_PROTOCOL_VERSION;

        $transportError = $isModernRequest
            ? mcpValidateHttpHeaders($request)
            : mcpValidateLegacyRequest($request);
        if ($transportError !== null) {
            http_response_code(400);
            echo json_encode($transportError, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }

        $validationError = $isModernRequest ? mcpValidateRequest($request) : null;
        if ($validationError !== null) {
            http_response_code(400);
            echo json_encode($validationError, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }

        $response = handleRequest($method, $id, $params, $settings);
        if (!$isModernRequest) $response = mcpLegacyResponse($response);
        if (($response['error']['code'] ?? null) === -32601) {
            http_response_code(404);
        }
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
