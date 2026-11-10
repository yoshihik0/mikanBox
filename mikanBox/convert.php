<?php
// ==========================================
// mikanBox Data Converter (JSON <=> SQLite)
// ==========================================
require_once __DIR__ . '/config.php';

// This tool lives inside the admin screen (admin.php?view=import). Opening convert.php
// directly (old bookmarks) leads there; POSTs keep working for compatibility.
if (!defined('MIKANBOX_DATA_TOOL') && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    header('Location: admin.php?view=import');
    exit;
}

// CSRF check
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 1. Security Check: Only logged-in administrators can access this converter
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
if (!$isLoggedIn) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>🍊mikanBox Converter - Forbidden</title>
        <link rel="stylesheet" href="admin.css">
        <style>
            body { display: flex; align-items: center; justify-content: center; height: 100vh; background: #f8fafc; font-family: sans-serif; }
            .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); text-align: center; max-width: 400px; }
            h1 { color: #e02424; font-size: 1.5rem; margin-bottom: 10px; }
            p { color: #64748b; margin-bottom: 20px; font-size: 0.95rem; }
        </style>
    </head>
    <body>
        <div class="card">
            <h1>アクセス権限がありません</h1>
            <p>このデータ変換ツールを実行するには、まず mikanBox の管理画面にログインしてください。</p>
            <a href="admin.php" class="btn btn-blue">管理画面ログインへ</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$db = getDb();
$log = [];
$action = $_POST['action'] ?? '';

// Handle conversion action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($action)) {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("CSRF token validation failed.");
    }

    if ($action === 'upload_import') {
        // ZIP Package Upload & Import
        try {
            if (empty($_FILES['package_zip']['tmp_name'])) {
                throw new Exception("ファイルがアップロードされていません。");
            }
            
            $file = $_FILES['package_zip'];
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("ファイルのアップロードに失敗しました。エラーコード: " . $file['error']);
            }
            
            $zipPath = $file['tmp_name'];
            $origName = $file['name'];
            $baseName = pathinfo($origName, PATHINFO_FILENAME);
            // Replace non-alphanumeric chars to be safe for directory name
            $baseName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $baseName);
            
            $importDir = DATA_DIR . '/private-import';
            if (!mikanBoxProtectDataDirectory($importDir)) throw new Exception('Cannot protect import directory.');
            if (!is_dir($importDir)) {
                @mkdir($importDir, 0777, true);
            }
            
            $extractTo = $importDir . '/' . ($baseName ?: 'package') . '-' . bin2hex(random_bytes(8));
            // Clean up existing folder with same name if any
            if (is_dir($extractTo)) {
                moveDirectory($extractTo, $importDir . '/_temp_trash_' . time());
            }
            
            // Extract ZIP
            if (!class_exists('ZipArchive')) {
                throw new Exception("PHPの ZipArchive クラスが利用できません。サーバー環境を確認してください。");
            }
            
            $zip = new ZipArchive;
            if ($zip->open($zipPath) === TRUE) {
                @mkdir($extractTo, 0777, true);
                $entries = []; $total = 0;
                if ($zip->numFiles > 10000) throw new Exception('Too many archive entries.');
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $entry = $zip->statIndex($i); $name = $entry['name'];
                    if (preg_match('~(^/|\\\\|(^|/)\.\.(/|$)|:)~', $name)) throw new Exception('Invalid archive path.');
                    if (str_ends_with($name, '/')) continue;
                    // Import content only: never extract scripts, server configuration or symlinks.
                    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'json') continue;
                    $opsys = 0; $attributes = 0; $zip->getExternalAttributesIndex($i, $opsys, $attributes);
                    if (($attributes >> 16 & 0170000) === 0120000) throw new Exception('Archive links are not allowed.');
                    $total += $entry['size'];
                    if ($entry['size'] > 10000000 || $total > 50000000) throw new Exception('Archive is too large.');
                    $entries[] = $name;
                }
                if (!$entries || !$zip->extractTo($extractTo, $entries)) throw new Exception('No valid JSON package.');
                $zip->close();
                $log[] = "[SUCCESS] ZIPファイルを一時フォルダに解凍しました: {$baseName}";
            } else {
                throw new Exception("ZIPファイルの展開に失敗しました。");
            }
            
            // Helper function to find settings.json recursively
            if (!function_exists('findPackageRootInDir')) {
                function findPackageRootInDir($dir) {
                    if (file_exists($dir . '/settings.json') || is_dir($dir . '/posts') || is_dir($dir . '/components')) {
                        return $dir;
                    }
                    $subdirs = [];
                    if (is_dir($dir)) {
                        $files = array_diff(scandir($dir), array('.', '..', '__MACOSX'));
                        foreach ($files as $f) {
                            $p = $dir . '/' . $f;
                            if (is_dir($p)) {
                                $subdirs[] = $p;
                            }
                        }
                    }
                    if (count($subdirs) === 1) {
                        return findPackageRootInDir($subdirs[0]);
                    }
                    return $dir;
                }
            }
            
            $packageRoot = findPackageRootInDir($extractTo);
            
            if (!file_exists($packageRoot . '/settings.json') && !is_dir($packageRoot . '/posts') && !is_dir($packageRoot . '/components')) {
                throw new Exception("有効なパッケージ構成（settings.json, posts/, components/）が見つかりません。");
            }
            
            $log[] = "[INFO] パッケージのルートディレクトリを特定しました: " . basename($packageRoot);
            
            // Import into SQLite
            importFolderToSqlite($db, $packageRoot);
            $log[] = "[SUCCESS] パッケージのデータをSQLiteデータベースにインポートしました。";
            
            // Rename to _imported
            $finalPath = $extractTo . '_imported';
            if (is_dir($finalPath)) {
                moveDirectory($finalPath, $importDir . '/_temp_trash_' . time());
            }
            @rename($extractTo, $finalPath);
            $log[] = "[SUCCESS] パッケージフォルダをリネームしました: " . basename($finalPath);
            
        } catch (Exception $e) {
            $log[] = "[ERROR] パッケージインポート中にエラーが発生しました: " . $e->getMessage();
        }
    } elseif ($action === 'wordpress_import') {
        // WordPress XML Import
        try {
            if (empty($_FILES['wp_xml']['tmp_name'])) {
                throw new Exception("ファイルが選択されていません。");
            }
            
            $xmlFile = $_FILES['wp_xml']['tmp_name'];
            
            // Try loading XML
            libxml_use_internal_errors(true);
            $xml = simplexml_load_file($xmlFile);
            if ($xml === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                $errMsgs = [];
                foreach ($errors as $error) {
                    $errMsgs[] = trim($error->message);
                }
                throw new Exception("XMLファイルの解析に失敗しました: " . implode(', ', $errMsgs));
            }
            
            $namespaces = $xml->getNamespaces(true);
            if (!isset($namespaces['wp']) || !isset($namespaces['content'])) {
                throw new Exception("有効な WordPress エクスポート XML（WXR）形式ではありません。");
            }
            
            $log[] = "[INFO] WordPress XMLデータの解析を開始します...";
            $postsImported = 0;
            
            // Loop through items
            foreach ($xml->channel->item as $item) {
                $wp = $item->children($namespaces['wp']);
                $post_type = (string)$wp->post_type;
                
                // Only import posts and pages
                if ($post_type !== 'post' && $post_type !== 'page') {
                    continue;
                }
                
                $title = (string)$item->title;
                $post_name = (string)$wp->post_name;
                $wp_status = (string)$wp->status;
                $post_id = (string)$wp->post_id;
                
                // Resolve Slug (ID)
                $id = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $post_name);
                if (empty($id)) {
                    $id = 'post_' . $post_id;
                }
                
                // HTML to Markdown Conversion
                $contentNS = $item->children($namespaces['content']);
                $content_html = (string)$contentNS->encoded;
                $content_md = wp_html_to_markdown($content_html);
                
                // Status Mapping
                $mikanStatus = 'draft';
                if ($wp_status === 'publish') {
                    $mikanStatus = 'public_dynamic';
                } elseif ($wp_status === 'pending') {
                    $mikanStatus = 'pending';
                }
                
                // Category extraction
                $categoryName = '';
                if (isset($item->category)) {
                    foreach ($item->category as $cat) {
                        if (isset($cat['domain']) && (string)$cat['domain'] === 'category') {
                            $categoryName = (string)$cat;
                            break; // Use first category
                        }
                    }
                }
                
                // Update time
                $updated_at = (string)$wp->post_date;
                if (empty($updated_at) || $updated_at === '0000-00-00 00:00:00') {
                    $updated_at = date('Y-m-d H:i:s');
                }
                
                // Sort order
                $sort_order = 0;
                
                // Prepare JSON payload
                $pageData = [
                    'title' => $title,
                    'content_md' => $content_md,
                    'custom_css' => '',
                    'category' => $categoryName,
                    'status' => $mikanStatus,
                    'description' => '',
                    'keywords' => '',
                    'updated_at' => $updated_at
                ];
                $dataJson = json_encode($pageData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                
                // Insert/Replace into posts table
                $stmt = $db->prepare("INSERT OR REPLACE INTO posts (id, title, category, status, sort_order, updated_at, data) VALUES (:id, :title, :category, :status, :sort_order, :updated_at, :data)");
                $stmt->bindValue(':id', $id, SQLITE3_TEXT);
                $stmt->bindValue(':title', $title, SQLITE3_TEXT);
                $stmt->bindValue(':category', $categoryName, SQLITE3_TEXT);
                $stmt->bindValue(':status', $mikanStatus, SQLITE3_TEXT);
                $stmt->bindValue(':sort_order', $sort_order, SQLITE3_INTEGER);
                $stmt->bindValue(':updated_at', $updated_at, SQLITE3_TEXT);
                $stmt->bindValue(':data', $dataJson, SQLITE3_TEXT);
                $stmt->execute();
                
                $log[] = "[INFO] 記事をインポートしました: {$title} (ID: {$id}, タイプ: {$post_type})";
                $postsImported++;
            }
            
            $log[] = "[SUCCESS] WordPress XML からのインポートが完了しました。（合計 {$postsImported} 件）";
            
        } catch (Exception $e) {
            $log[] = "[ERROR] WordPress インポート中にエラーが発生しました: " . $e->getMessage();
        }
    }
}

// Helper to format bytes
if (!function_exists('formatSizeInConvert')) {
    function formatSizeInConvert($bytes) {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        return round($bytes / 1024, 1) . ' KB';
    }
}

// Get media files stats
$mediaFiles = is_dir(MEDIA_DIR) ? array_diff(scandir(MEDIA_DIR), array('.', '..', '.DS_Store', '.gitkeep')) : [];
$mediaCount = 0;
$mediaSize = 0;
foreach ($mediaFiles as $f) {
    $fp = MEDIA_DIR . '/' . $f;
    if (is_file($fp)) {
        $mediaCount++;
        $mediaSize += filesize($fp);
    }
}

// Get stats
$stats = [
    'sqlite_exists' => file_exists(DB_FILE),
    'sqlite_size' => file_exists(DB_FILE) ? formatSizeInConvert(filesize(DB_FILE)) : '未作成',
    'sqlite_posts' => 0,
    'sqlite_comps' => 0,
    'sqlite_users' => 0,
    'media_count' => $mediaCount,
    'media_size' => formatSizeInConvert($mediaSize),
];

if ($stats['sqlite_exists']) {
    try {
        $stats['sqlite_posts'] = $db->querySingle("SELECT COUNT(*) FROM posts") ?: 0;
        $stats['sqlite_comps'] = $db->querySingle("SELECT COUNT(*) FROM components") ?: 0;
        $stats['sqlite_users'] = $db->querySingle("SELECT COUNT(*) FROM users") ?: 0;
    } catch(Exception $e) {}
}

/**
 * Simple HTML to Markdown converter for WordPress imported posts.
 */
function wp_html_to_markdown($html) {
    // 1. Remove WordPress block comments (e.g. <!-- wp:paragraph -->)
    $html = preg_replace('/<!--\s*\/?wp:[^\s>]*\s*-->/i', '', $html);
    $html = preg_replace('/<!--.*?-->/s', '', $html); // Remove all other HTML comments
    
    // 2. Headings
    $html = preg_replace('/<h1[^>]*>(.*?)<\/h1>/i', "\n# $1\n", $html);
    $html = preg_replace('/<h2[^>]*>(.*?)<\/h2>/i', "\n## $1\n", $html);
    $html = preg_replace('/<h3[^>]*>(.*?)<\/h3>/i', "\n### $1\n", $html);
    $html = preg_replace('/<h4[^>]*>(.*?)<\/h4>/i', "\n#### $1\n", $html);
    $html = preg_replace('/<h5[^>]*>(.*?)<\/h5>/i', "\n##### $1\n", $html);
    $html = preg_replace('/<h6[^>]*>(.*?)<\/h6>/i', "\n###### $1\n", $html);
    
    // 3. Bold & Strong
    $html = preg_replace('/<(strong|b)[^>]*>(.*?)<\/\1>/i', '**$2**', $html);
    
    // 4. Italic & Em
    $html = preg_replace('/<(em|i)[^>]*>(.*?)<\/\1>/i', '*$2*', $html);
    
    // 5. Links
    $html = preg_replace('/<a[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/i', '[$2]($1)', $html);
    
    // 6. Images
    $html = preg_replace('/<img[^>]*src="([^"]+)"[^>]*alt="([^"]*)"[^>]*>/i', '![$2]($1)', $html);
    $html = preg_replace('/<img[^>]*alt="([^"]*)"[^>]*src="([^"]+)"[^>]*>/i', '![$1]($2)', $html);
    
    // 7. Lists
    $html = preg_replace('/<li[^>]*>(.*?)<\/li>/i', "* $1\n", $html);
    $html = preg_replace('/<\/?(ul|ol)[^>]*>/i', "\n", $html);
    
    // 8. Blockquotes
    $html = preg_replace('/<blockquote[^>]*>(.*?)<\/blockquote>/is', "\n> $1\n", $html);
    
    // 9. Line breaks & Paragraphs
    $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
    $html = preg_replace('/<p[^>]*>(.*?)<\/p>/is', "\n$1\n", $html);
    
    // 10. Strip remaining HTML tags
    $markdown = strip_tags($html);
    
    // 11. Normalize multiple newlines
    $markdown = preg_replace("/\n{3,}/", "\n\n", $markdown);
    
    return trim($markdown);
}
?>
<?php
$embed = isset($_GET['embed']) || isset($_POST['embed']);
$importAction = 'admin.php?view=import';
$csrfValue = htmlspecialchars($_SESSION['csrf_token']);
ob_start();
?>
<div id="data-import" class="section-anchor">
    <main class="data-tool">
        <header class="data-tool-header">
            <div class="data-tool-logo">🍊</div>
            <div>
                <h1>データ移行・インポートツール</h1>
                <p>mikanBox SQLite版のデータベース状態・メディア状態の確認、および外部データからのインポートを行います。</p>
            </div>
        </header>
        <div class="data-tool-content">
            <div class="data-tool-grid">
                <section class="data-tool-card">
                    <h2><span class="material-symbols-outlined">database</span> SQLiteデータベース状態</h2>
                    <ul class="import-status-list">
                        <li>存在ステータス: <strong><?= $stats['sqlite_exists'] ? '作成済み' : '未作成' ?></strong></li>
                        <li>ファイルサイズ: <strong><?= htmlspecialchars((string)$stats['sqlite_size']) ?></strong></li>
                        <li>登録ページ数: <strong><?= (int)$stats['sqlite_posts'] ?> 件</strong></li>
                        <li>登録コンポーネント数: <strong><?= (int)$stats['sqlite_comps'] ?> 件</strong></li>
                        <li>登録ユーザー（編集者）数: <strong><?= (int)$stats['sqlite_users'] ?> 人</strong></li>
                    </ul>
                </section>
                <section class="data-tool-card">
                    <h2><span class="material-symbols-outlined">image</span> メディアファイル状態</h2>
                    <ul class="import-status-list">
                        <li>保存ディレクトリ: <strong>media/</strong></li>
                        <li>登録メディアファイル数: <strong><?= (int)$stats['media_count'] ?> 件</strong></li>
                        <li>総メディア容量: <strong><?= htmlspecialchars((string)$stats['media_size']) ?></strong></li>
                    </ul>
                </section>
            </div>

            <section id="zip-import" class="data-tool-card">
                <h2><span class="material-symbols-outlined">upload_file</span> コンテンツパッケージ (ZIP) インポート</h2>
                <p>テーマや初期データを含むZIPパッケージ（<code>settings.json</code>, <code>posts/</code>, <code>components/</code> フォルダなどを含むアーカイブ）をアップロードして、現在のSQLiteデータベースにインポートします。<br>
                    ※インポートされたファイル群は、<code>mikanData/private-import/</code> フォルダ配下に <code>{フォルダ名}_imported</code> として展開されます。</p>
                <form method="post" enctype="multipart/form-data" action="<?= $importAction ?>" data-data-tool-form>
                    <input type="hidden" name="csrf_token" value="<?= $csrfValue ?>">
                    <input type="hidden" name="action" value="upload_import">
                    <div class="flex-row flex-row-wrap items-center">
                        <input type="file" name="package_zip" accept=".zip" required>
                        <button type="submit" class="btn btn-orange btn-small"><span class="material-symbols-outlined icon">publish</span> アップロード &amp; インポート</button>
                    </div>
                </form>
                <details class="package-layout-guide">
                    <summary><?= t('package_layout_title') ?> <span class="accordion-arrow" aria-hidden="true">▼</span></summary>
                    <p><?= t('package_layout_intro') ?></p>
                    <pre>posts/
  coffee/
    index.json  → coffee/index
    menu.json   → coffee/menu
components/
  coffee_layout.json</pre>
                    <p><?= t('package_layout_notes') ?></p>
                </details>
            </section>

            <section id="wp-import" class="data-tool-card">
                <h2><span class="material-symbols-outlined">description</span> WordPress データ移行 (XML インポート)</h2>
                <p>WordPress の「ツール ＞ エクスポート」から書き出した XML ファイル（WXR 形式）をアップロードし、投稿や固定ページを mikanBox のページデータとして一括インポートします。<br>
                    ※インポートされたページのステータスは WordPress での公開状態（公開/下書き）が維持されます。本文の HTML は自動的に簡易的な Markdown 形式に変換されます。</p>
                <form method="post" enctype="multipart/form-data" action="<?= $importAction ?>" data-data-tool-form>
                    <input type="hidden" name="csrf_token" value="<?= $csrfValue ?>">
                    <input type="hidden" name="action" value="wordpress_import">
                    <div class="flex-row flex-row-wrap items-center">
                        <input type="file" name="wp_xml" accept=".xml" required>
                        <button type="submit" class="btn btn-orange btn-small"><span class="material-symbols-outlined icon">publish</span> XML を解析してインポート</button>
                    </div>
                </form>
            </section>

            <?php if (!empty($log)): ?>
                <section id="import-log" class="data-tool-card">
                    <h2><span class="material-symbols-outlined">receipt_long</span> 実行ログ</h2>
                    <div class="data-tool-log" aria-live="polite">
                        <?php foreach ($log as $line): ?>
                            <?php
                            $class = 'import-log-info';
                            if (strpos($line, '[SUCCESS]') !== false) $class = 'import-log-success';
                            elseif (strpos($line, '[ERROR]') !== false) $class = 'import-log-error';
                            ?>
                            <div class="<?= $class ?>"><?= htmlspecialchars($line) ?></div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
        <footer class="data-tool-footer">
            <?php if ($embed): ?>
                <a href="admin.php" data-data-tool-close><span class="material-symbols-outlined icon">arrow_back</span> 管理画面へ戻る</a>
            <?php else: ?>
                <a href="admin.php"><span class="material-symbols-outlined icon">arrow_back</span> 管理画面へ戻る</a>
            <?php endif; ?>
            <span>&copy; <?= date('Y') ?> mikanBox</span>
        </footer>
    </main>
</div>
<?php
$fragment = ob_get_clean();
if ($embed) {
    header('Content-Type: text/html; charset=utf-8');
    echo $fragment;
    exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= mikanBoxAdminTitle(loadSettings(), 'データ移行・インポート') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=block" rel="stylesheet">
    <link rel="stylesheet" href="admin.css?v=<?= MIKANBOX_VERSION ?>">
</head>
<body>
<?= $fragment ?>
<script>
// Only the file fields take dropped files; anywhere else a drop does nothing (instead of opening the file).
(() => {
    const isFileInput = (target) => target instanceof Element && target.matches('input[type="file"]');
    window.addEventListener('dragover', (e) => { if (isFileInput(e.target)) return; e.preventDefault(); if (e.dataTransfer) e.dataTransfer.dropEffect = 'none'; });
    window.addEventListener('drop', (e) => { if (!isFileInput(e.target)) e.preventDefault(); });
})();
</script>
</body>
</html>
