<?php
// ==========================================
// mikanBox Data Converter (JSON <=> SQLite)
// ==========================================
require_once __DIR__ . '/config.php';

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
            
            $importDir = __DIR__ . '/import';
            if (!is_dir($importDir)) {
                @mkdir($importDir, 0777, true);
            }
            
            $extractTo = $importDir . '/' . $baseName;
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
                $zip->extractTo($extractTo);
                $zip->close();
                $log[] = "[SUCCESS] ZIPファイルを一時フォルダに解凍しました: {$baseName}";
            } else {
                throw new Exception("ZIPファイルの展開に失敗しました。");
            }
            
            // Helper function to find settings.json recursively
            if (!function_exists('findPackageRootInDir')) {
                function findPackageRootInDir($dir) {
                    if (file_exists($dir . '/settings.json')) {
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
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>🍊mikanBox データ相互変換ユーティリティ</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600&family=Noto+Sans+JP:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=block" rel="stylesheet">
    <style>
        :root {
            --primary: #f97316;
            --primary-hover: #ea580c;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text: #1e293b;
            --text-sub: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
            --error: #ef4444;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Outfit', 'Noto Sans JP', sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            width: 100%;
            max-width: 800px;
            background: var(--card-bg);
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%);
            padding: 30px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .header-logo { font-size: 2.5rem; }
        .header-title h1 { font-size: 1.5rem; font-weight: 700; color: #ea580c; margin-bottom: 4px; }
        .header-title p { font-size: 0.88rem; color: var(--text-sub); }
        .content { padding: 30px; }
        
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .card {
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            background: #fafafa;
        }
        .card h2 { font-size: 1.1rem; font-weight: 600; margin-bottom: 12px; color: var(--text); display: flex; align-items: center; gap: 8px; }
        .card ul { list-style: none; }
        .card li { font-size: 0.88rem; color: var(--text-sub); margin-bottom: 8px; display: flex; justify-content: space-between; }
        .card li strong { color: var(--text); }
        
        .badge { padding: 2px 8px; border-radius: 9999px; font-size: 0.75rem; font-weight: 500; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-gray { background: #e2e8f0; color: #475569; }

        .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 14px 20px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
            color: white;
            text-align: center;
        }
        .btn-orange { background: var(--primary); }
        .btn-orange:hover { background: var(--primary-hover); }
        .btn-outline { background: transparent; border: 2px solid var(--primary); color: var(--primary); }
        .btn-outline:hover { background: #fff7ed; }
        .btn-secondary { background: #64748b; }
        .btn-secondary:hover { background: #475569; }
        
        .log-area {
            background: #0f172a;
            color: #e2e8f0;
            padding: 20px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.85rem;
            max-height: 250px;
            overflow-y: auto;
            border: 1px solid #1e293b;
        }
        .log-line { margin-bottom: 5px; line-height: 1.4; }
        .log-success { color: #4ade80; }
        .log-error { color: #f87171; }
        .log-info { color: #38bdf8; }

        .footer {
            padding: 20px 30px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fafafa;
        }
        .footer a { color: var(--primary); text-decoration: none; font-size: 0.88rem; font-weight: 500; display: flex; align-items: center; gap: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-logo">🍊</div>
            <div class="header-title">
                <h1>データ移行・インポートツール</h1>
                <p>mikanBox SQLite版のデータベース状態・メディア状態の確認、および外部データからのインポートを行います。</p>
            </div>
        </div>
        
        <div class="content">
            <div class="grid">
                <!-- SQLite Status -->
                <div class="card">
                    <h2><span class="material-symbols-outlined" style="color: var(--primary);">database</span>SQLiteデータベース状態</h2>
                    <ul>
                        <li>存在ステータス: <strong><?= $stats['sqlite_exists'] ? '作成済み' : '未作成' ?></strong></li>
                        <li>ファイルサイズ: <strong><?= $stats['sqlite_size'] ?></strong></li>
                        <li>登録ページ数: <strong><?= $stats['sqlite_posts'] ?> 件</strong></li>
                        <li>登録コンポーネント数: <strong><?= $stats['sqlite_comps'] ?> 件</strong></li>
                        <li>登録ユーザー（編集者）数: <strong><?= $stats['sqlite_users'] ?> 人</strong></li>
                    </ul>
                </div>
                
                <!-- Media Status -->
                <div class="card">
                    <h2><span class="material-symbols-outlined" style="color: var(--primary);">image</span>メディアファイル状態</h2>
                    <ul>
                        <li>保存ディレクトリ: <strong>media/</strong></li>
                        <li>登録メディアファイル数: <strong><?= $stats['media_count'] ?> 件</strong></li>
                        <li>総メディア容量: <strong><?= $stats['media_size'] ?></strong></li>
                    </ul>
                </div>
            </div>
            
            <!-- ZIP Package Import Form -->
            <div id="zip-import" class="card" style="margin-top: 30px;">
                <h2><span class="material-symbols-outlined" style="color: var(--primary);">upload_file</span>コンテンツパッケージ (ZIP) インポート</h2>
                <form method="post" enctype="multipart/form-data" action="convert.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="upload_import">
                    <p style="font-size: 0.85rem; color: var(--text-sub); margin-bottom: 12px; line-height: 1.5;">
                        テーマや初期データを含むZIPパッケージ（<code>settings.json</code>, <code>posts/</code>, <code>components/</code> フォルダなどを含むアーカイブ）をアップロードして、現在のSQLiteデータベースにインポートします。<br>
                        ※インポートされたファイル群は、<code>import/</code> フォルダ配下に <code>{フォルダ名}_imported</code> として展開されます。
                    </p>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <input type="file" name="package_zip" accept=".zip" required style="font-size: 0.9rem; padding: 6px; border: 1px solid var(--border); border-radius: 6px; flex-grow: 1; background: #fafafa;">
                        <button type="submit" class="btn btn-orange" style="white-space: nowrap; height: 38px; display: inline-flex; align-items: center; gap: 4px; margin-top: 0;">
                            <span class="material-symbols-outlined" style="font-size: 1.15rem;">publish</span>
                            アップロード & インポート
                        </button>
                    </div>
                </form>
            </div>

            <!-- WordPress XML Import Form -->
            <div id="wp-import" class="card" style="margin-top: 30px;">
                <h2><span class="material-symbols-outlined" style="color: var(--primary);">description</span>WordPress データ移行 (XML インポート)</h2>
                <form method="post" enctype="multipart/form-data" action="convert.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="wordpress_import">
                    <p style="font-size: 0.85rem; color: var(--text-sub); margin-bottom: 12px; line-height: 1.5;">
                        WordPress の「ツール ＞ エクスポート」から書き出した XML ファイル（WXR 形式）をアップロードし、投稿や固定ページを mikanBox のページデータとして一括インポートします。<br>
                        ※インポートされたページのステータスは WordPress での公開状態（公開/下書き）が維持されます。本文の HTML は自動的に簡易的な Markdown 形式に変換されます。
                    </p>
                    <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <input type="file" name="wp_xml" accept=".xml" required style="font-size: 0.9rem; padding: 6px; border: 1px solid var(--border); border-radius: 6px; flex-grow: 1; background: #fafafa;">
                        <button type="submit" class="btn btn-orange" style="white-space: nowrap; height: 38px; display: inline-flex; align-items: center; gap: 4px; margin-top: 0;">
                            <span class="material-symbols-outlined" style="font-size: 1.15rem;">publish</span>
                            XML を解析してインポート
                        </button>
                    </div>
                </form>
            </div>

            <!-- Output Log -->
            <?php if (!empty($log)): ?>
                <h3 style="font-size: 0.95rem; margin-bottom: 10px; display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined">receipt_long</span>実行ログ</h3>
                <div class="log-area">
                    <?php foreach ($log as $line): ?>
                        <?php
                        $class = 'log-info';
                        if (strpos($line, '[SUCCESS]') !== false) $class = 'log-success';
                        elseif (strpos($line, '[ERROR]') !== false) $class = 'log-error';
                        ?>
                        <div class="log-line <?= $class ?>"><?= htmlspecialchars($line) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="footer">
            <a href="admin.php">
                <span class="material-symbols-outlined" style="font-size: 1.1rem;">arrow_back</span>
                管理画面へ戻る
            </a>
            <span style="font-size: 0.8rem; color: var(--text-sub);">&copy; 2026 mikanBox Utility</span>
        </div>
    </div>
</body>
</html>
