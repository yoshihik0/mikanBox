<?php
// ==========================================
// mikanBox ビジュアル編集（visual.php）
//   visual.php            ページを選ぶ
//   visual.php?id=ID      そのページを、レイアウトごと表示して、クリックして直す
//   visual.php?api=NAME   画面から呼ぶ窓口（JSON）
//
// 管理画面（admin.php）と同じログイン（セッション）が必要。書き込みは、CSRFトークンと同一オリジンの確認つき。
// ==========================================

require_once __DIR__ . '/config.php';              // セッションを開き、定数（POSTS_DIR など）と CSRF の関数を定義する
require_once __DIR__ . '/lib/functions.php';
require_once __DIR__ . '/lib/renderer.php';
require_once __DIR__ . '/lib/visual-blocks.php';
require_once __DIR__ . '/lib/visual-history.php';

// ---------------------------------------------------------------- 共通の部品
function mbv_nonce(): string { static $n = null; return $n ??= rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '='); }

function mbv_headers(bool $html): void {
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: SAMEORIGIN');
    if ($html) {
        // ページ自身のスクリプト（外部のタグ・インラインのスクリプト）は、管理者の権限で動かさない。
        // このページには、管理者のログイン中のセッションがあるため。同じサイトの外部ファイル（部品のJSなど）と、この画面のスクリプトだけ動かす。
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . mbv_nonce() . "'; "
            . "style-src 'self' 'unsafe-inline' https:; font-src 'self' https: data:; img-src * data: blob:; media-src * data: blob:; "
            . "connect-src 'self'; frame-src 'self' https:; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
    }
}
function mbv_json(array $a, int $code = 200): void {
    http_response_code($code); header('Content-Type: application/json; charset=utf-8'); mbv_headers(false);
    echo json_encode($a, JSON_UNESCAPED_UNICODE); exit;
}
function mbv_die(string $msg, int $code = 500): void {
    http_response_code($code); header('Content-Type: text/html; charset=utf-8'); mbv_headers(true);
    echo '<!doctype html><meta charset="utf-8"><title>' . htmlspecialchars(t('btn_visual_edit')) . '</title><body style="font:16px/1.7 system-ui,sans-serif;max-width:640px;margin:60px auto;padding:0 20px;color:#1b3a57"><h2>' . htmlspecialchars(t('btn_visual_edit')) . '</h2><p>' . htmlspecialchars($msg) . '</p></body>';
    exit;
}
function mbv_same_origin(): bool {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $o = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($o !== '') {
        $h = strtolower((string)(parse_url($o, PHP_URL_HOST) ?? '')); $p = parse_url($o, PHP_URL_PORT);
        return ($h . ($p ? ':' . $p : '')) === $host;
    }
    $sf = (string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
    return $sf === '' || $sf === 'same-origin' || $sf === 'none';
}
function mbv_nl(string $s): string { return str_replace("\r\n", "\n", $s); }
function mbv_body(): array { return json_decode((string)file_get_contents('php://input'), true) ?: []; }
function mbv_valid_id(string $id): bool { return (bool)preg_match('~\A[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*\z~', $id); }
/** 管理画面と同じ決まり: 本体・データ・media・api などと重なるIDは使えない */
function mbv_reserved_id(string $id): bool {
    $names = [basename(CORE_DIR), 'media', 'api'];
    if (defined('MIKANBOX_DATA_DIR_BASENAME')) $names[] = MIKANBOX_DATA_DIR_BASENAME;
    if (defined('DATA_DIR') && dirname(rtrim(DATA_DIR, '/\\')) === dirname(CORE_DIR)) $names[] = basename(DATA_DIR);
    foreach (array_unique(array_filter($names)) as $r) {
        if (strcasecmp($id, $r) === 0 || stripos($id, $r . '/') === 0) return true;
    }
    return false;
}
/** ページのステータスの表示名（管理画面と同じ言語キー） */
function mbv_status_label(string $s): string {
    $keys = ['draft' => 'page_status_draft', 'pending' => 'page_status_pending', 'public_dynamic' => 'page_status_public_dynamic', 'public_static' => 'page_status_public_static', 'db' => 'page_status_db'];
    return isset($keys[$s]) ? t($keys[$s]) : $s;
}
/** 画面（JS）に渡す文字列: visual_ で始まるキーと、管理画面と共通のキー */
function mbv_js_strings(): array {
    $lang = getSystemLanguage();
    $file = __DIR__ . "/lang/{$lang}.json";
    if (!file_exists($file)) $file = __DIR__ . '/lang/ja.json';
    $all = json_decode((string)file_get_contents($file), true) ?: [];
    $out = [];
    foreach ($all as $k => $v) { if (strpos($k, 'visual_') === 0) $out[$k] = $v; }
    foreach (['btn_close', 'btn_cancel', 'btn_copy', 'label_title', 'btn_visual_edit'] as $k) { if (isset($all[$k])) $out[$k] = $all[$k]; }
    return $out;
}
function mbv_site_base(): string {
    $d = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/mikanBox/visual.php'));
    return ($d === '/' || $d === '.' || $d === '\\') ? '/' : rtrim($d, '/') . '/';
}

/** ページを本体の描画処理で表示する（目印を入れて、行番号つきのHTMLにする） */
function mbv_render(string $id, ?array $override = null): ?string {
    $data = $override ?? loadData(POSTS_DIR, $id);
    if (!$data) return null;
    $md = mbv_nl((string)($data['content_md'] ?? ''));
    $data['content_md'] = !empty($data['is_html']) ? $md : mbv_inject_markers($md);
    $data['css'] = ".fv-css-start{}\n" . mbv_nl((string)($data['css'] ?? '')) . "\n.fv-css-end{}";   // 空でも、範囲用の枠を出す＋ライブ反映の目印
    $data['status'] = 'public_dynamic';                                                              // 下書きでも表示する（ログイン済みの管理者だけが、ここに来る）
    $settings = $GLOBALS['mikanbox_settings'] ?? [];
    $settings['_site_base'] = mbv_site_base();
    $renderer = new MikanBoxRenderer($settings);
    return mbv_apply_markers((string)$renderer->render($id, $data));
}

// ---------------------------------------------------------------- ログインの確認（管理画面と同じセッション）
$S = $GLOBALS['mikanbox_settings'] ?? [];
// flat版は設定にパスワードがあること、SQLite版（端末認証）は管理画面と同じログイン済みのセッションであること
$hasAccount = function_exists('getPreviewToken') ? true : !empty($S['password_hash']);
$loggedIn = (($_SESSION['admin_logged_in'] ?? false) === true) && $hasAccount && empty($S['demo_mode']);
$isApi = isset($_GET['api']);
if (!$loggedIn) {
    if ($isApi) mbv_json(['error' => t('visual_err_login'), 'login' => true], 401);
    header('Location: admin.php'); exit;     // 管理画面のログインへ
}
$CSRF = generateCsrfToken();
$SELF = $_SERVER['SCRIPT_NAME'];
$CORE_URL = rtrim(dirname($SELF), '/');
$ADMIN_URL = $CORE_URL . '/admin.php';

// ---------------------------------------------------------------- API
if ($isApi) {
    $api = (string)$_GET['api'];
    $post = $_SERVER['REQUEST_METHOD'] === 'POST';
    if ($post) {
        if (!mbv_same_origin()) mbv_json(['error' => 'forbidden'], 403);
        if (!verifyCsrfToken((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) mbv_json(['error' => t('visual_err_csrf')], 403);
    }
    $in = $post && $api !== 'upload' ? mbv_body() : [];
    $id = (string)($in['id'] ?? $_GET['id'] ?? '');
    $needId = !in_array($api, ['scope-css', 'upload', 'create'], true);
    if ($needId && (!mbv_valid_id($id) || !loadData(POSTS_DIR, $id))) mbv_json(['error' => t('visual_err_page_not_found')], 400);

    switch ($api) {
        case 'mtime':       mbv_json(['t' => md5(json_encode(loadData(POSTS_DIR, $id)))]);
        case 'history':     mbv_json(mbv_counts($id));
        case 'source':      { $d = loadData(POSTS_DIR, $id); mbv_json(['text' => mbv_nl((string)($d['content_md'] ?? '')), 'is_html' => !empty($d['is_html'])]); }
        case 'css':         mbv_json(['css' => mbv_nl((string)(loadData(POSTS_DIR, $id)['css'] ?? ''))]);
        case 'scope-css':   mbv_json(['css' => scopeCss(".fv-css-start{}\n" . mbv_nl((string)($in['css'] ?? '')) . "\n.fv-css-end{}", '.mikan-content-scope')]);
        case 'render': {
            if (!$post) mbv_json(['error' => 'method'], 405);
            $data = loadData(POSTS_DIR, $id);
            if (isset($in['text'])) $data['content_md'] = (string)$in['text'];
            mbv_json(['html' => mbv_render($id, $data)]);
        }
        case 'points':      mbv_json(['points' => mbv_points($id)]);
        case 'restore': {
            if (!$post) mbv_json(['error' => 'method'], 405);
            if (!mbv_point_restore($id, (string)($in['point'] ?? ''), (string)($in['sid'] ?? ''))) mbv_json(['error' => t('visual_err_point_missing')], 409);
            mbv_json(['ok' => true] + mbv_counts($id));
        }
        case 'undo': case 'redo': {
            if (!$post) mbv_json(['error' => 'method'], 405);
            if (!mbv_history_move($id, $api)) mbv_json(['error' => $api === 'undo' ? t('visual_err_undo_none') : t('visual_err_redo_none')], 409);
            mbv_json(['ok' => true] + mbv_counts($id));
        }
        case 'save-css': {
            if (!$post) mbv_json(['error' => 'method'], 405);
            $data = loadData(POSTS_DIR, $id); $orig = (string)($data['css'] ?? '');
            if (mbv_nl($orig) !== ($in['old'] ?? null)) mbv_json(['error' => t('visual_err_css_changed')], 409);
            $new = mbv_nl((string)($in['new'] ?? ''));
            mbv_snapshot($id, (string)($in['sid'] ?? ''));
            $data['css'] = strpos($orig, "\r\n") !== false ? str_replace("\n", "\r\n", $new) : $new;
            mbv_json(['ok' => (bool)saveData(POSTS_DIR, $id, $data)] + mbv_counts($id));
        }
        case 'save-md': {
            if (!$post) mbv_json(['error' => 'method'], 405);
            $data = loadData(POSTS_DIR, $id); $orig = (string)($data['content_md'] ?? '');
            if (mbv_nl($orig) !== ($in['old'] ?? null)) mbv_json(['error' => t('visual_err_body_changed')], 409);
            $new = mbv_nl((string)($in['new'] ?? ''));
            mbv_snapshot($id, (string)($in['sid'] ?? ''));
            $data['content_md'] = strpos($orig, "\r\n") !== false ? str_replace("\n", "\r\n", $new) : $new;
            mbv_json(['ok' => (bool)saveData(POSTS_DIR, $id, $data)] + mbv_counts($id));
        }
        case 'save': {   // 1ブロックの置き換え・前後への追加・削除（行範囲で指定）
            if (!$post) mbv_json(['error' => 'method'], 405);
            $data = loadData(POSTS_DIR, $id); $orig = (string)($data['content_md'] ?? ''); $crlf = strpos($orig, "\r\n") !== false;
            $lines = explode("\n", mbv_nl($orig));
            $a = (int)($in['start'] ?? -1); $b = (int)($in['end'] ?? -1);
            if ($a < 0 || $b < $a || $b >= count($lines)) mbv_json(['error' => t('visual_err_range')], 400);
            if (implode("\n", array_slice($lines, $a, $b - $a + 1)) !== ($in['old'] ?? null)) mbv_json(['error' => t('visual_err_block_changed')], 409);
            $mode = $in['mode'] ?? 'replace';
            $new = rtrim(mbv_nl((string)($in['new'] ?? '')), "\n");
            if ($mode === 'delete') {
                if (!mbv_html_balanced(implode("\n", array_slice($lines, $a, $b - $a + 1)))) mbv_json(['error' => t('visual_err_html_unbalanced')], 409);
                $len = $b - $a + 1; if (isset($lines[$b + 1]) && trim($lines[$b + 1]) === '') $len++;
                array_splice($lines, $a, $len);
            } elseif ($mode === 'insert-after') array_splice($lines, $b + 1, 0, array_merge([''], explode("\n", $new)));
            elseif ($mode === 'insert-before') array_splice($lines, $a, 0, array_merge(explode("\n", $new), ['']));
            else array_splice($lines, $a, $b - $a + 1, explode("\n", $new));
            mbv_snapshot($id, (string)($in['sid'] ?? ''));
            $joined = implode("\n", $lines);
            $data['content_md'] = $crlf ? str_replace("\n", "\r\n", $joined) : $joined;
            mbv_json(['ok' => (bool)saveData(POSTS_DIR, $id, $data)] + mbv_counts($id));
        }
        case 'create': {   // 新規作成 / 複製（いつも下書きで作る）
            if (!$post) mbv_json(['error' => 'method'], 405);
            $title = trim((string)($in['title'] ?? ''));
            $from = (string)($in['from'] ?? '');
            if ($id === '') $id = 'page_' . time();
            if (!mbv_valid_id($id)) mbv_json(['error' => t('visual_err_id_chars')], 400);
            if (mbv_reserved_id($id)) mbv_json(['error' => t('visual_err_id_reserved')], 400);
            if (loadData(POSTS_DIR, $id) !== null) mbv_json(['error' => t('visual_err_id_exists')], 409);
            if ($from !== '') {
                $data = mbv_valid_id($from) ? loadData(POSTS_DIR, $from) : null;
                if (!$data) mbv_json(['error' => t('visual_err_source_missing')], 400);
                $data['title'] = $title !== '' ? $title : (string)($data['title'] ?? '') . t('visual_copy_suffix');
            } else {
                $data = [
                    'title' => $title !== '' ? $title : $id, 'category' => '', 'description' => '', 'memo' => '', 'keywords' => '', 'ogp_image' => '',
                    'content_md' => t('visual_new_body'), 'css' => '', 'js' => '', 'is_html' => false, 'wrapper_comp' => '_layout',
                    'exclude_tracking' => false, 'sort_order' => 0,
                ];
            }
            $data['status'] = 'draft'; $data['updated_at'] = date('Y-m-d H:i:s');
            if (!saveData(POSTS_DIR, $id, $data)) mbv_json(['error' => t('visual_err_save')], 500);
            mbv_json(['ok' => true, 'id' => $id]);
        }
        case 'upload': {   // 画像の貼り付け・ドラッグ・選択（png / jpg / gif / webp）。本体の画像保存の決まり（ファイル名・保存名）を通す
            if (!$post) mbv_json(['error' => 'method'], 405);
            $bin = (string)file_get_contents('php://input');
            if ($bin === '' || strlen($bin) > 10 * 1024 * 1024) mbv_json(['error' => t('visual_err_image_size')], 400);
            $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bin);
            $ext = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime] ?? null;
            $dim = $ext ? @getimagesizefromstring($bin) : false;
            if (!$ext || !$dim || $dim[0] < 1 || $dim[1] < 1 || $dim[0] * $dim[1] > 40000000) mbv_json(['error' => t('visual_err_image_type')], 400);
            $name = 'img-' . date('Ymd-His') . '-' . bin2hex(random_bytes(2)) . '.' . $ext;
            if (!mediaValidFilename($name)) mbv_json(['error' => t('visual_err_filename')], 400);
            $resolved = resolveMediaSaveName($name, '');
            if (!is_dir(MEDIA_DIR)) mkdir(MEDIA_DIR, 0755, true);
            if (file_put_contents(MEDIA_DIR . '/' . $resolved, $bin) === false) mbv_json(['error' => t('visual_err_save')], 500);
            mbv_json(['name' => $resolved]);
        }
    }
    mbv_json(['error' => 'not found'], 404);
}

// ---------------------------------------------------------------- ページの表示
$id = (string)($_GET['id'] ?? '');
if ($id !== '') {
    if (!mbv_valid_id($id) || !loadData(POSTS_DIR, $id)) mbv_die(t('visual_err_page_not_found_id', $id), 404);
    $html = mbv_render($id);
    header('Content-Type: text/html; charset=utf-8'); mbv_headers(true);
    if (isset($_GET['fv-frame'])) { echo $html; exit; }     // 画面幅の確認用（編集機能なし）
    $ids = function_exists('getSortedPostIds') ? getSortedPostIds() : getFileList(POSTS_DIR);
    $pages = [];
    foreach ($ids as $pid) { $d = loadData(POSTS_DIR, $pid); if ($d) $pages[] = ['id' => $pid, 'title' => (string)($d['title'] ?? ''), 'status' => (string)($d['status'] ?? '')]; }
    $info = [
        'api' => $SELF, 'list' => $ADMIN_URL . '?view=pages',
        'page' => $ADMIN_URL . '?view=pages&edit=' . rawurlencode($id) . '#page-editor', 'pick' => $SELF,
        'view' => $SELF . '?id=', 'site' => mbv_site_base(), 'edit' => $ADMIN_URL . '?view=pages#', 'editBase' => $ADMIN_URL . '?view=pages&edit=',
    ];
    $n = mbv_nonce();
    $v = static fn(string $f) => @filemtime(__DIR__ . '/visual/assets/' . $f) ?: 0;
    $tags = '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,300,0,0&display=block">'
          . '<link rel="stylesheet" href="' . htmlspecialchars($CORE_URL) . '/visual/assets/visual.css?v=' . $v('visual.css') . '">'
          . '<script nonce="' . $n . '">window.__VISUAL_PAGE=' . json_encode($id) . ';window.__VISUAL_ADMIN=' . json_encode($info) . ';window.__VISUAL_PAGES=' . json_encode($pages, JSON_UNESCAPED_UNICODE) . ';window.__VISUAL_CSRF=' . json_encode($CSRF) . ';window.__VISUAL_T=' . json_encode(mbv_js_strings(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) . ';</script>'
          . '<script nonce="' . $n . '" src="' . htmlspecialchars($CORE_URL) . '/visual/assets/visual.js?v=' . $v('visual.js') . '"></script>';
    echo stripos($html, '</body>') !== false ? preg_replace('~</body>~i', str_replace('$', '\\$', $tags) . '</body>', $html, 1) : $html . $tags;
    exit;
}

// ---------------------------------------------------------------- ページを選ぶ
$ids = function_exists('getSortedPostIds') ? getSortedPostIds() : getFileList(POSTS_DIR);
$rows = '';
foreach ($ids as $pid) {
    $d = loadData(POSTS_DIR, $pid); if (!$d) continue;
    $title = (string)($d['title'] ?? '') ?: t('visual_untitled');
    $status = mbv_status_label((string)($d['status'] ?? ''));
    $rows .= '<a class="row" href="?id=' . htmlspecialchars(rawurlencode($pid), ENT_QUOTES) . '" data-q="' . htmlspecialchars(mb_strtolower($title . ' ' . $pid), ENT_QUOTES) . '">'
           . '<span class="t">' . htmlspecialchars($title) . '</span><span class="i">' . htmlspecialchars($pid) . '</span><span class="s">' . htmlspecialchars($status) . '</span></a>';
}
header('Content-Type: text/html; charset=utf-8'); mbv_headers(true);
$n = mbv_nonce();
?><!doctype html>
<html lang="<?= htmlspecialchars(getSystemLanguage()) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= htmlspecialchars(t('btn_visual_edit') . ' - ' . t('visual_picker_title')) ?></title>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,300,0,0&display=block">
<style>
body{margin:0;background:#f4f8fb;color:#1b3a57;font:15px/1.6 system-ui,-apple-system,"Hiragino Sans",sans-serif}
main{max-width:760px;margin:0 auto;padding:28px 18px 60px}
h1{font-size:1.25rem;margin:0 0 4px;display:flex;align-items:center;gap:8px}
p.note{color:#4f6b86;margin:0 0 18px;font-size:.9rem}
.bar{display:flex;gap:8px;margin-bottom:12px}
input[type=search]{flex:1;border:1px solid #cfd8e3;border-radius:10px;padding:9px 12px;font:inherit;background:#fff;color:inherit}
a.btn{display:inline-flex;align-items:center;gap:4px;border:1px solid #cfd8e3;background:#fff;border-radius:10px;padding:0 12px;color:#1b3a57;text-decoration:none;font-size:.9rem}
a.btn:hover,.row:hover{background:#e3f3fc;border-color:#8cc7e8}
.list{display:flex;flex-direction:column;gap:6px}
.row{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #cfd8e3;border-radius:10px;padding:10px 14px;color:inherit;text-decoration:none}
.row .t{flex:1;font-weight:600;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.row .i{color:#4f6b86;font:12px ui-monospace,Menlo,monospace}
.row .s{font-size:.75rem;background:#e3f3fc;border:1px solid #8cc7e8;border-radius:999px;padding:1px 9px;white-space:nowrap}
.ic{font-family:'Material Symbols Outlined';font-size:20px;line-height:1;font-feature-settings:'liga'}
</style></head><body><main>
<h1><span class="ic">edit_square</span><?= htmlspecialchars(t('btn_visual_edit')) ?></h1>
<p class="note"><?= htmlspecialchars(t('visual_picker_note')) ?></p>
<div class="bar"><input type="search" id="q" placeholder="<?= htmlspecialchars(t('visual_search_placeholder'), ENT_QUOTES) ?>" autofocus><a class="btn" href="<?= htmlspecialchars($ADMIN_URL) ?>?view=pages"><span class="ic">dashboard</span><?= htmlspecialchars(t('admin_site_title')) ?></a></div>
<div class="list" id="list"><?= $rows ?></div>
</main>
<script nonce="<?= $n ?>">
document.getElementById('q').addEventListener('input',function(){var q=this.value.trim().toLowerCase();document.querySelectorAll('#list .row').forEach(function(r){r.style.display=!q||r.dataset.q.indexOf(q)>=0?'':'none'})});
</script></body></html>
