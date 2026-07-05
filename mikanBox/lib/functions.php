<?php
// ==========================================
// mikanBox Core Functions (lib/functions.php)
// ==========================================

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions-common.php';

/**
 * Get the SQLite database connection.
 * Automatically creates the database and initializes tables if they don't exist.
 * Also performs automatic migration if the database was just created.
 */
function getDb() {
    static $db = null;
    if ($db === null) {
        $dbPath = DB_FILE;
        $dbExists = file_exists($dbPath);
        
        try {
            $db = new SQLite3($dbPath);
            $db->busyTimeout(5000); // 5 seconds busy timeout
            
            // Create tables if not exist
            $db->exec("CREATE TABLE IF NOT EXISTS posts (
                id TEXT PRIMARY KEY,
                title TEXT,
                category TEXT,
                status TEXT,
                sort_order INTEGER,
                updated_at TEXT,
                data TEXT
            )");
            
            $db->exec("CREATE TABLE IF NOT EXISTS components (
                id TEXT PRIMARY KEY,
                data TEXT
            )");
            
            $db->exec("CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT
            )");

            $db->exec("CREATE TABLE IF NOT EXISTS locks (
                item_type TEXT,
                item_id TEXT,
                editor_id TEXT,
                expires_at INTEGER,
                PRIMARY KEY (item_type, item_id)
            )");

            $db->exec("CREATE TABLE IF NOT EXISTS users (
                username TEXT PRIMARY KEY,
                password_hash TEXT,
                display_name TEXT,
                security_question TEXT,
                security_answer_hash TEXT
            )");

            $db->exec("CREATE TABLE IF NOT EXISTS post_revisions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id TEXT,
                title TEXT,
                category TEXT,
                status TEXT,
                sort_order INTEGER,
                updated_at TEXT,
                data TEXT,
                editor_id TEXT,
                created_at TEXT
            )");

            // Schema migration: Add security_question and security_answer_hash columns if missing in existing databases
            try {
                $tableInfo = $db->query("PRAGMA table_info(users)");
                $hasQuestion = false;
                $hasAnswer = false;
                while ($col = $tableInfo->fetchArray(SQLITE3_ASSOC)) {
                    if ($col['name'] === 'security_question') $hasQuestion = true;
                    if ($col['name'] === 'security_answer_hash') $hasAnswer = true;
                }
                if (!$hasQuestion) {
                    @$db->exec("ALTER TABLE users ADD COLUMN security_question TEXT");
                }
                if (!$hasAnswer) {
                    @$db->exec("ALTER TABLE users ADD COLUMN security_answer_hash TEXT");
                }
            } catch (Exception $e) {}
            
            if (!$dbExists) {
                // Check if legacy JSON files exist in the data directory
                $hasLegacyFiles = file_exists(SETTINGS_FILE) || is_dir(COMPONENTS_DIR) || is_dir(POSTS_DIR);
                
                if ($hasLegacyFiles) {
                    migrateJsonToSqlite($db);
                } else {
                    // Fresh installation path
                    $lang = getSystemLanguage(); // Detect browser language or default
                    $importBase = __DIR__ . '/../import';
                    $srcPreset = $importBase . '/' . $lang;
                    
                    if (!is_dir($srcPreset)) {
                        $srcPreset = $importBase . '/ja'; // Fallback to ja
                        $lang = 'ja';
                    }
                    
                    if (is_dir($srcPreset)) {
                        importFolderToSqlite($db, $srcPreset);
                        // Rename the imported folder to add _imported suffix
                        @rename($srcPreset, $srcPreset . '_imported');
                    }
                }
            }

            // Password Recovery / Reset trigger
            $resetFile = DATA_DIR . '/reset_password.txt';
            if (file_exists($resetFile)) {
                try {
                    $db->exec("DELETE FROM users");
                } catch (Exception $e) {}
                @unlink($resetFile);
            }

            // Migrate legacy password_hash to users table if users is empty
            $userCount = 0;
            try {
                $userCount = $db->querySingle("SELECT COUNT(*) FROM users");
            } catch (Exception $e) {}

            if ($userCount === 0) {
                try {
                    $val = $db->querySingle("SELECT value FROM settings WHERE key = 'password_hash'");
                    if ($val !== null && $val !== false) {
                        $legacyHash = json_decode($val, true);
                        if (!empty($legacyHash)) {
                            $stmtInsert = $db->prepare("INSERT OR REPLACE INTO users (username, password_hash, display_name) VALUES (:username, :password_hash, :display_name)");
                            $stmtInsert->bindValue(':username', 'admin', SQLITE3_TEXT);
                            $stmtInsert->bindValue(':password_hash', $legacyHash, SQLITE3_TEXT);
                            $stmtInsert->bindValue(':display_name', '管理者', SQLITE3_TEXT);
                            $stmtInsert->execute();
                            
                            // Delete legacy settings password
                            $db->exec("DELETE FROM settings WHERE key = 'password_hash'");
                        }
                    }
                } catch (Exception $e) {}
            }
        } catch (Exception $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    return $db;
}

/**
 * Helper function to recursively move directories.
 */
function moveDirectory($src, $dst) {
    if (!is_dir($src)) return false;
    if (!is_dir($dst)) {
        @mkdir($dst, 0777, true);
    }
    $files = array_diff(scandir($src), array('.', '..'));
    foreach ($files as $file) {
        $srcPath = "$src/$file";
        $dstPath = "$dst/$file";
        if (is_dir($srcPath)) {
            moveDirectory($srcPath, $dstPath);
        } else {
            @rename($srcPath, $dstPath);
        }
    }
    return @rmdir($src);
}

/**
 * Generic function to import data from a structured folder (settings, components, posts) into SQLite.
 * @param bool $importSettings settings.jsonを取り込むかどうか。既存サイトへのZIPインポート等、
 *   意図せず現在のsettings（パスワードハッシュ・APIキー含む）を上書きしてしまうのを防ぐため、
 *   既定ではfalse（無視する）。flat→sqlite移行（自分自身の既存設定の引き継ぎ）の時だけtrueにする。
 */
function importFolderToSqlite($db, $folderPath, $importSettings = false) {
    // 1. Settings import（$importSettings=trueの時のみ）
    if ($importSettings) {
        $settingsFile = $folderPath . '/settings.json';
        if (file_exists($settingsFile)) {
            $json = file_get_contents($settingsFile);
            $settings = json_decode($json, true);
            if (is_array($settings)) {
                $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :value)");
                foreach ($settings as $k => $v) {
                    $stmt->bindValue(':key', $k, SQLITE3_TEXT);
                    $stmt->bindValue(':value', json_encode($v, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
                    $stmt->execute();
                }
            }
        }
    }

    // 2. Components import
    // 既存IDと重複する場合はスキップする（上書きしない）。上書きしたい場合は
    // 先に該当コンポーネントを削除してからインポートすること。
    $componentsDir = $folderPath . '/components';
    if (is_dir($componentsDir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($componentsDir, RecursiveDirectoryIterator::SKIP_DOTS));
        $checkStmt = $db->prepare("SELECT 1 FROM components WHERE id = :id");
        $stmt = $db->prepare("INSERT INTO components (id, data) VALUES (:id, :data)");
        foreach ($it as $file) {
            if ($file->getExtension() === 'json') {
                $relPath = substr($file->getPathname(), strlen($componentsDir) + 1);
                $id = substr($relPath, 0, -5); // remove .json
                // Use forward slashes for IDs on Windows
                $id = str_replace('\\', '/', $id);

                $checkStmt->bindValue(':id', $id, SQLITE3_TEXT);
                $exists = $checkStmt->execute()->fetchArray(SQLITE3_ASSOC);
                if ($exists) continue; // 既存IDはスキップ

                $dataJson = file_get_contents($file->getPathname());

                $stmt->bindValue(':id', $id, SQLITE3_TEXT);
                $stmt->bindValue(':data', $dataJson, SQLITE3_TEXT);
                $stmt->execute();
            }
        }
    }

    // 3. Posts import
    // 既存IDと重複する場合はスキップする（上書きしない）。上書きしたい場合は
    // 先に該当ページを削除してからインポートすること（index も削除可能なため同じ扱い）。
    $postsDir = $folderPath . '/posts';
    if (is_dir($postsDir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($postsDir, RecursiveDirectoryIterator::SKIP_DOTS));
        $checkStmt = $db->prepare("SELECT 1 FROM posts WHERE id = :id");
        $stmt = $db->prepare("INSERT INTO posts (id, title, category, status, sort_order, updated_at, data) VALUES (:id, :title, :category, :status, :sort_order, :updated_at, :data)");
        foreach ($it as $file) {
            if ($file->getExtension() === 'json') {
                $relPath = substr($file->getPathname(), strlen($postsDir) + 1);
                $id = substr($relPath, 0, -5); // remove .json
                // Use forward slashes for IDs on Windows
                $id = str_replace('\\', '/', $id);

                $checkStmt->bindValue(':id', $id, SQLITE3_TEXT);
                $exists = $checkStmt->execute()->fetchArray(SQLITE3_ASSOC);
                if ($exists) continue; // 既存IDはスキップ

                $dataJson = file_get_contents($file->getPathname());
                $data = json_decode($dataJson, true);

                if (is_array($data)) {
                    $title = $data['title'] ?? '';
                    $category = $data['category'] ?? '';
                    $status = $data['status'] ?? '';
                    $sort_order = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;
                    $updated_at = $data['updated_at'] ?? '';

                    $stmt->bindValue(':id', $id, SQLITE3_TEXT);
                    $stmt->bindValue(':title', $title, SQLITE3_TEXT);
                    $stmt->bindValue(':category', $category, SQLITE3_TEXT);
                    $stmt->bindValue(':status', $status, SQLITE3_TEXT);
                    $stmt->bindValue(':sort_order', $sort_order, SQLITE3_INTEGER);
                    $stmt->bindValue(':updated_at', $updated_at, SQLITE3_TEXT);
                    $stmt->bindValue(':data', $dataJson, SQLITE3_TEXT);
                    $stmt->execute();
                }
            }
        }
    }
}

/**
 * Migrate existing JSON files into the SQLite database.
 * Moves legacy files/folders from data/ to import/oldsystem, imports them, and renames the folder.
 */
function migrateJsonToSqlite($db) {
    $settingsFile = SETTINGS_FILE;
    $compDir = COMPONENTS_DIR;
    $postsDir = POSTS_DIR;

    $importDir = __DIR__ . '/../import';
    $oldsystemDir = $importDir . '/oldsystem';
    
    if (!is_dir($oldsystemDir)) {
        @mkdir($oldsystemDir, 0777, true);
    }

    // Move settings
    if (file_exists($settingsFile)) {
        @rename($settingsFile, $oldsystemDir . '/settings.json');
    }
    // Move components
    if (is_dir($compDir)) {
        moveDirectory($compDir, $oldsystemDir . '/components');
    }
    // Move posts
    if (is_dir($postsDir)) {
        moveDirectory($postsDir, $oldsystemDir . '/posts');
    }

    // Import from oldsystem folder（flat→sqlite移行なので、自分自身の既存設定を引き継ぐ）
    importFolderToSqlite($db, $oldsystemDir, true);

    // Rename oldsystem -> oldsystem_imported
    if (is_dir($oldsystemDir)) {
        @rename($oldsystemDir, $oldsystemDir . '_imported');
    }
}

/**
 * Load settings from database with JSON fallback.
 */
function loadSettings() {
    $db = getDb();
    $res = $db->query("SELECT key, value FROM settings");
    $settings = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $settings[$row['key']] = json_decode($row['value'], true);
    }
    
    // Fallback if settings table is empty
    if (empty($settings)) {
        $settingsFile = SETTINGS_FILE;
        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
        } elseif (file_exists($settingsFile . '.bak')) {
            $settings = json_decode(file_get_contents($settingsFile . '.bak'), true) ?: [];
        }
    }
    return $settings;
}

/**
 * Save settings to the database.
 */
function saveSettings($settings) {
    $db = getDb();
    $db->exec("BEGIN TRANSACTION");
    $db->exec("DELETE FROM settings"); // Clear old settings
    $stmt = $db->prepare("INSERT INTO settings (key, value) VALUES (:key, :value)");
    foreach ($settings as $k => $v) {
        $stmt->bindValue(':key', $k, SQLITE3_TEXT);
        $stmt->bindValue(':value', json_encode($v, JSON_UNESCAPED_UNICODE), SQLITE3_TEXT);
        $stmt->execute();
    }
    $res = $db->exec("COMMIT");
    
    // Keep in-memory settings in sync
    $GLOBALS['mikanbox_settings'] = $settings;
    
    return $res !== false;
}

/**
 * Save data to SQLite tables (intercepting POSTS_DIR and COMPONENTS_DIR) or fallback to file storage.
 * @param string $dir Target directory (POSTS_DIR, COMPONENTS_DIR, etc.)
 * @param string $id File/Record ID (e.g., 'index', 'header')
 * @param array $data Data to save
 * @return bool Success or failure
 */
function saveData($dir, $id, $data) {
    // Allow slashes for hierarchy but prevent directory traversal (no ..)
    $id = ltrim($id, '/\\');
    $id = preg_replace('/[^a-zA-Z0-9_\-\/\.]/', '', $id);
    if (empty($id)) return false;

    if ($dir === POSTS_DIR) {
        $db = getDb();
        $title = $data['title'] ?? '';
        $category = $data['category'] ?? '';
        $status = $data['status'] ?? '';
        $sort_order = isset($data['sort_order']) ? (int)$data['sort_order'] : 0;
        $updated_at = $data['updated_at'] ?? date('Y-m-d H:i:s');
        $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        $stmt = $db->prepare("INSERT OR REPLACE INTO posts (id, title, category, status, sort_order, updated_at, data) VALUES (:id, :title, :category, :status, :sort_order, :updated_at, :data)");
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $stmt->bindValue(':title', $title, SQLITE3_TEXT);
        $stmt->bindValue(':category', $category, SQLITE3_TEXT);
        $stmt->bindValue(':status', $status, SQLITE3_TEXT);
        $stmt->bindValue(':sort_order', $sort_order, SQLITE3_INTEGER);
        $stmt->bindValue(':updated_at', $updated_at, SQLITE3_TEXT);
        $stmt->bindValue(':data', $dataJson, SQLITE3_TEXT);
        return $stmt->execute() !== false;
    }
    
    if ($dir === COMPONENTS_DIR) {
        $db = getDb();
        $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        $stmt = $db->prepare("INSERT OR REPLACE INTO components (id, data) VALUES (:id, :data)");
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $stmt->bindValue(':data', $dataJson, SQLITE3_TEXT);
        return $stmt->execute() !== false;
    }

    // Fallback/Legacy file write
    $filePath = resolveSafeDataPath($dir, $id, '.json', true);
    if ($filePath === false) return false;
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    return file_put_contents($filePath, $json) !== false;
}

/**
 * Load data from SQLite tables (intercepting POSTS_DIR and COMPONENTS_DIR) or fallback to file storage.
 * @param string $dir Target directory
 * @param string $id record/File ID
 * @return array|null The data as an associative array, or null on failure
 */
function loadData($dir, $id) {
    // Allow slashes for hierarchy but prevent directory traversal
    $id = ltrim($id, '/\\');
    $id = preg_replace('/[^a-zA-Z0-9_\-\/\.]/', '', $id);
    if (empty($id)) return null;

    if ($dir === POSTS_DIR) {
        $db = getDb();
        $stmt = $db->prepare("SELECT data FROM posts WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $res = $stmt->execute();
        $row = $res->fetchArray(SQLITE3_ASSOC);
        if ($row) {
            return json_decode($row['data'], true);
        }
        return null;
    }
    
    if ($dir === COMPONENTS_DIR) {
        $db = getDb();
        $stmt = $db->prepare("SELECT data FROM components WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $res = $stmt->execute();
        $row = $res->fetchArray(SQLITE3_ASSOC);
        if ($row) {
            return json_decode($row['data'], true);
        }
        return null;
    }

    // Fallback/Legacy file read
    $filePath = resolveSafeDataPath($dir, $id, '.json');
    if ($filePath === false || !file_exists($filePath)) {
        // ディレクトリ型ページ: coffee → coffee/index.json
        $indexPath = resolveSafeDataPath($dir, rtrim($id, '/') . '/index', '.json');
        if ($indexPath !== false && file_exists($indexPath)) {
            $filePath = $indexPath;
        } else {
            return null;
        }
    }

    $json = file_get_contents($filePath);
    return json_decode($json, true);
}

/**
 * Retrieve lists of keys from SQLite tables or fallback to scanning files.
 * @param string $dir Target directory
 * @return array Array of record IDs (without extension)
 */
function getFileList($dir) {
    if ($dir === POSTS_DIR) {
        $db = getDb();
        $res = $db->query("SELECT id FROM posts");
        $files = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $files[] = $row['id'];
        }
        return $files;
    }
    
    if ($dir === COMPONENTS_DIR) {
        $db = getDb();
        $res = $db->query("SELECT id FROM components");
        $files = [];
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $files[] = $row['id'];
        }
        return $files;
    }

    // Fallback/Legacy file scanning
    $files = [];
    if (!is_dir($dir)) return $files;
    
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->getExtension() === 'json') {
            // Calculate relative path from $dir and strip extension
            $relPath = substr($file->getPathname(), strlen($dir) + 1);
            $files[] = substr($relPath, 0, -5); // remove .json
        }
    }
    return $files;
}

/**
 * Get sorted post ID list directly via SQLite database query.
 * Sorted by: sort_order ASC, updated_at DESC.
 * @return array Array of sorted post IDs
 */
function getSortedPostIds() {
    $db = getDb();
    $res = $db->query("SELECT id FROM posts ORDER BY sort_order ASC, updated_at DESC");
    $ids = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $ids[] = $row['id'];
    }
    return $ids;
}

/**
 * Delete data from SQLite tables or fallback to deleting files.
 * @param string $dir Preservation target directory
 * @param string $id Record/File ID
 * @return bool
 */
function deleteData($dir, $id) {
    $id = ltrim($id, '/\\');
    $id = preg_replace('/[^a-zA-Z0-9_\-\/\.]/', '', $id);
    if (empty($id)) return false;

    if ($dir === POSTS_DIR) {
        $db = getDb();
        $stmt = $db->prepare("DELETE FROM posts WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        return $stmt->execute() !== false;
    }

    if ($dir === COMPONENTS_DIR) {
        $db = getDb();
        $stmt = $db->prepare("DELETE FROM components WHERE id = :id");
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        return $stmt->execute() !== false;
    }

    // Fallback/Legacy file deletion
    $filePath = resolveSafeDataPath($dir, $id, '.json');
    if ($filePath !== false && file_exists($filePath)) {
        return unlink($filePath);
    }
    return false;
}

/**
 * Generate a secure 16-character preview token for verification (pending approval).
 * Uses the password hash of the first user in the database as salt.
 */
function getPreviewToken($pageId) {
    $db = getDb();
    $salt = 'mikanbox_salt';
    try {
        $firstHash = $db->querySingle("SELECT password_hash FROM users LIMIT 1");
        if ($firstHash) {
            $salt = $firstHash;
        }
    } catch (Exception $e) {}
    return substr(hash_hmac('sha256', $pageId, $salt), 0, 16);
}

/**
 * Normalize the security answer to prevent verification failures due to spaces and casing.
 * @param string $answer The raw answer text
 * @return string Normalized answer text (lowercase, no spaces)
 */
function normalizeSecurityAnswer($answer) {
    $answer = mb_strtolower($answer, 'UTF-8');
    return preg_replace('/\s+/u', '', $answer);
}

/**
 * Searches posts for the specified keyword in their title or content.
 */
function searchPosts($keyword, $isAdmin = false) {
    $db = getDb();
    $keyword = trim($keyword);
    if ($keyword === '') {
        return [];
    }
    
    $sql = "SELECT id, title, status, data FROM posts";
    if (!$isAdmin) {
        $sql .= " WHERE status = 'public_dynamic' OR status = 'public_static'";
    }
    $sql .= " ORDER BY sort_order ASC, id ASC";
    
    $res = $db->query($sql);
    $results = [];
    if ($res) {
        $lowerKeyword = mb_strtolower($keyword, 'UTF-8');
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $data = json_decode($row['data'], true) ?: [];
            
            // Extract search target fields
            $id = $row['id'];
            $title = $row['title'] !== '' ? $row['title'] : $row['id'];
            $content_md = $data['content_md'] ?? '';
            $description = $data['description'] ?? '';
            $category = $data['category'] ?? '';
            $keywords = $data['keywords'] ?? '';

            // Visitor-facing search excludes pages with sort_order < 0 — the same convention
            // NAV_LINKS/NAV_CARDS already use to hide utility pages (404, search results, etc.)
            // from listings. Using sort_order instead of a "system" category avoids relying on
            // a category tag, which is user-editable/deletable like any other category.
            if (!$isAdmin && (int)($data['sort_order'] ?? 0) < 0) {
                continue;
            }

            $matched = false;
            
            // 1. Admin can search by Page ID (slug)
            if ($isAdmin && mb_strpos(mb_strtolower($id, 'UTF-8'), $lowerKeyword) !== false) {
                $matched = true;
            }
            // 2. All searches check Title, Content, Description, Keywords, Category
            elseif (mb_strpos(mb_strtolower($title, 'UTF-8'), $lowerKeyword) !== false) {
                $matched = true;
            }
            elseif (mb_strpos(mb_strtolower($content_md, 'UTF-8'), $lowerKeyword) !== false) {
                $matched = true;
            }
            elseif (mb_strpos(mb_strtolower($description, 'UTF-8'), $lowerKeyword) !== false) {
                $matched = true;
            }
            elseif (mb_strpos(mb_strtolower($keywords, 'UTF-8'), $lowerKeyword) !== false) {
                $matched = true;
            }
            elseif (mb_strpos(mb_strtolower($category, 'UTF-8'), $lowerKeyword) !== false) {
                $matched = true;
            }
            
            if ($matched) {
                $results[] = [
                    'id' => $id,
                    'title' => $title,
                    'description' => $description,
                    'content_md' => $content_md,
                    'category' => $category,
                    'updated_at' => $data['updated_at'] ?? '',
                ];
            }
        }
    }
    return $results;
}

/**
 * Wraps keyword matches (case-insensitive) in a <strong class="search-highlight"> tag.
 * $safeText must already be HTML-escaped.
 */
function highlightSearchKeyword($safeText, $keyword) {
    $escapedKeyword = preg_quote(htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8'), '/');
    return preg_replace('/(' . $escapedKeyword . ')/iu', '<strong class="search-highlight">$1</strong>', $safeText);
}

/**
 * Extracts a text snippet around the search keyword and highlights it.
 */
function getSearchSnippet($content, $keyword, $length = 150) {
    // Strip HTML tags and markdown symbols
    $cleanText = strip_tags($content);
    $cleanText = preg_replace('/\{\{[^}]+\}\}/', '', $cleanText); // remove custom tags
    $cleanText = preg_replace('/[#*`_\-]/', ' ', $cleanText); // remove markdown characters
    $cleanText = preg_replace('/\s+/', ' ', $cleanText); // normalize spacing
    
    $pos = mb_strpos(mb_strtolower($cleanText, 'UTF-8'), mb_strtolower($keyword, 'UTF-8'));
    if ($pos === false) {
        return htmlspecialchars(mb_substr($cleanText, 0, $length), ENT_QUOTES, 'UTF-8') . '...';
    }
    
    $start = max(0, $pos - ($length / 2));
    $snippet = mb_substr($cleanText, $start, $length);
    
    $prefix = ($start > 0) ? '...' : '';
    $suffix = (mb_strlen($cleanText) > ($start + $length)) ? '...' : '';
    
    $safeSnippet = htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8');
    $highlighted = highlightSearchKeyword($safeSnippet, $keyword);

    return $prefix . $highlighted . $suffix;
}

/**
 * Create a revision backup for a page before saving.
 */
function createRevision($postId, $editorUsername) {
    $db = getDb();
    
    // Load current post data from DB
    $stmt = $db->prepare("SELECT title, category, status, sort_order, updated_at, data FROM posts WHERE id = :id");
    $stmt->bindValue(':id', $postId, SQLITE3_TEXT);
    $res = $stmt->execute();
    $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;
    
    if (!$row) {
        return false; // No existing post to back up
    }
    
    $stmtInsert = $db->prepare("INSERT INTO post_revisions 
        (post_id, title, category, status, sort_order, updated_at, data, editor_id, created_at) 
        VALUES (:post_id, :title, :category, :status, :sort_order, :updated_at, :data, :editor_id, :created_at)");
        
    $stmtInsert->bindValue(':post_id', $postId, SQLITE3_TEXT);
    $stmtInsert->bindValue(':title', $row['title'], SQLITE3_TEXT);
    $stmtInsert->bindValue(':category', $row['category'], SQLITE3_TEXT);
    $stmtInsert->bindValue(':status', $row['status'], SQLITE3_TEXT);
    $stmtInsert->bindValue(':sort_order', $row['sort_order'], SQLITE3_INTEGER);
    $stmtInsert->bindValue(':updated_at', $row['updated_at'], SQLITE3_TEXT);
    $stmtInsert->bindValue(':data', $row['data'], SQLITE3_TEXT);
    $stmtInsert->bindValue(':editor_id', $editorUsername, SQLITE3_TEXT);
    $stmtInsert->bindValue(':created_at', date('Y-m-d H:i:s'), SQLITE3_TEXT);
    
    $stmtInsert->execute();
    
    // Keep max 10 revisions per page to avoid database bloat
    try {
        $db->exec("DELETE FROM post_revisions WHERE id IN (
            SELECT id FROM post_revisions WHERE post_id = '" . $db->escapeString($postId) . "' 
            ORDER BY created_at DESC, id DESC LIMIT -1 OFFSET 10
        )");
    } catch (Exception $e) {}
    
    return true;
}

/**
 * Get revisions for a post.
 */
function getRevisions($postId) {
    $db = getDb();
    $stmt = $db->prepare("SELECT id, editor_id, created_at FROM post_revisions WHERE post_id = :post_id ORDER BY created_at DESC, id DESC");
    $stmt->bindValue(':post_id', $postId, SQLITE3_TEXT);
    $res = $stmt->execute();
    
    $list = [];
    if ($res) {
        while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
            $list[] = $row;
        }
    }
    return $list;
}

/**
 * Get post data stored in a specific revision.
 */
function getRevisionData($revisionId) {
    $db = getDb();
    $stmt = $db->prepare("SELECT title, category, status, sort_order, updated_at, data FROM post_revisions WHERE id = :id");
    $stmt->bindValue(':id', $revisionId, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;
    
    if (!$row) return null;
    
    $data = json_decode($row['data'], true) ?: [];
    
    // Merge core columns
    $data['title'] = $row['title'];
    $data['category'] = $row['category'];
    $data['status'] = $row['status'];
    $data['sort_order'] = (int)$row['sort_order'];
    $data['updated_at'] = $row['updated_at'];
    
    return $data;
}

/**
 * Delete revisions associated with a page.
 */
function deleteRevisionsOfPost($postId) {
    $db = getDb();
    $stmt = $db->prepare("DELETE FROM post_revisions WHERE post_id = :post_id");
    $stmt->bindValue(':post_id', $postId, SQLITE3_TEXT);
    $stmt->execute();
}

