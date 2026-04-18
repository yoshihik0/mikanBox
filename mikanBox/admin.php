<?php
ob_start();
// ==========================================
// mikanBox Admin Panel (admin.php)
// ==========================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/functions.php';
define('MIKANBOX', true);

// CSRF token generation (initial or on session timeout)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Authentication ---
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Initial setup / Login / Common settings
// If settings.json does not exist, initialize with an empty array
$settings = file_exists(SETTINGS_FILE) ? json_decode(file_get_contents(SETTINGS_FILE), true) : [];
// Pass reference as a global variable (Fix #8)
$GLOBALS['mikanbox_settings'] = &$settings;
$passwordHash = $settings['password_hash'] ?? '';
$isDemoMode = !empty($settings['demo_mode']);
$loginError = ''; // Initialize loginError

// Logout process
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_logged_in']);
    // In demo mode, redirect to login form after logout
    $redirect = $isDemoMode ? basename($_SERVER['PHP_SELF']) . '?login=1' : basename($_SERVER['PHP_SELF']);
    header('Location: ' . $redirect);
    exit;
}

// --- Login / Initial Setup Process ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_action'])) {
    if ($_POST['login_action'] === 'set_initial_password' && empty($passwordHash)) {
        // Initial password setup
        $pass = $_POST['new_password'] ?? '';
        if (strlen($pass) < 4) {
            $loginError = t('err_password_chars');
        } else {
            // Populate defaults on first-time setup
            if (empty($settings)) {
                $settings = [
                    'site_name'   => '🍊mikanBox',
                    'description' => '',
                    'keywords'    => '',
                    'memo'        => 'Welcome to 🍊mikanBox!',
                    'system_lang' => '',
                    'ssg_structure' => 'file'
                ];
            }
            $settings['password_hash'] = password_hash($pass, PASSWORD_DEFAULT);
            if (file_put_contents(SETTINGS_FILE, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
                // 初回セットアップ時に .htaccess が未生成であれば自動作成
                $siteRoot = dirname(CORE_DIR);
                $htaccessPath = $siteRoot . '/.htaccess';
                if (!file_exists($htaccessPath)) {
                    $htaccessContent = "DirectoryIndex index.php\n\n<IfModule mod_rewrite.c>\n    RewriteEngine On\n    RewriteCond %{REQUEST_FILENAME} -f [OR]\n    RewriteCond %{REQUEST_FILENAME} -d\n    RewriteRule ^ - [L]\n    RewriteRule ^ index.php [L,QSA]\n</IfModule>\n";
                    @file_put_contents($htaccessPath, $htaccessContent);
                }
                $_SESSION['admin_logged_in'] = true;
                header('Location: ' . basename(__FILE__));
                exit;
            } else {
                $loginError = t('err_save_failed');
            }
        }
    } elseif ($_POST['login_action'] === 'login' && !empty($passwordHash)) {
        // Normal login
        if (password_verify($_POST['password'] ?? '', $passwordHash)) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: ' . basename(__FILE__));
            exit;
        } else {
            $loginError = t('err_wrong_password');
        }
    }
}

// Show login screen if not logged in
// In demo mode, allow access without login (unless ?login=1 is requested for full access)
if (!$isLoggedIn && (!$isDemoMode || isset($_GET['login']))) {
    if (ob_get_length()) ob_clean();
?>
<!DOCTYPE html>
<html lang="<?= getSystemLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <title>🍊mikanBox - <?= t('admin_login') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=block" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="login-body">
    <div class="login-box">
        <div class="login-title"><span>🍊</span><span>mikanBox</span></div>
        <?php if (empty($passwordHash)): ?>
            <p><strong><?= t('hint_initial_setup') ?></strong><br><?= t('hint_setup_msg') ?></p>
            <form method="post">
                <input type="hidden" name="login_action" value="set_initial_password">
                <input type="password" name="new_password" placeholder="<?= t('admin_new_password') ?>" required autofocus>
                <button type="submit"><?= t('btn_set_password') ?></button>
            </form>
        <?php else: ?>
            <?php if ($isDemoMode): ?>
            <p><?= t('hint_demo_login') ?></p>
            <?php endif; ?>
            <?php if(!empty($loginError)) echo "<div class='error'>{$loginError}</div>"; ?>
            <form method="post">
                <input type="hidden" name="login_action" value="login">
                <input type="password" name="password" placeholder="<?= t('admin_password') ?>" required autofocus>
                <button type="submit"><?= t('btn_login') ?></button>
            </form>
            <?php if ($isDemoMode): ?>
            <p><a href="<?= basename($_SERVER['PHP_SELF']) ?>"><?= t('btn_demo_back') ?></a></p>
            <?php endif; ?>
        <?php endif; ?>
            <p class="login-hint">
                <?= t('admin_forgot_password') ?><br>
                <?= t('admin_forgot_password_hint') ?>
            </p>
    </div>
</body>
</html>
<?php
    exit;
}

// ==========================================
// Post-login Processing (Routing & Data Saving)
// ==========================================
// Logged in: Load common data
// $settings is already loaded above
$site_name = $settings['site_name'] ?? SITE_NAME;
$view = $_GET['view'] ?? 'pages';
if ($view === 'design') $view = 'components'; // 'design' is an alias for 'components'

// 🍊 Tag Guide Helper (Reusable)
$renderTagGuide = function() {
    global $helpFile;
    ?>
    <details class="hint-accordion">
        <summary><h3 class="accordion-title"><?= t('available_tags_content_css') ?> <span class="accordion-arrow">▼</span></h3></summary>
        <div class="hint-accordion-body">
            <div class="hint-grid hint-grid-tag">
                <div>
                    <strong><?= t('standard_info') ?></strong>
                    <ul class="hint-list hint-list-sm">
                        <li><code>{{TITLE}}</code> : <?= t('page_title') ?></li>
                        <li><code>{{FULL_TITLE}}</code> : <?= t('page_title') ?> - <?= t('site_name') ?></li>
                        <li><code>{{UPDATE_DATE}}</code> : <?= t('hint_update_date_ymd') ?></li>
                        <li><code>{{UPDATE_DATE:JP}}</code> : <?= t('hint_update_date_jp') ?></li>
                        <li><code>{{UPDATE_DATE:SLASH}}</code> : <?= t('hint_update_date_slash') ?></li>
                        <li><code>{{IS_NEW:30}}</code> : <?= t('hint_is_new') ?></li>
                        <li><code>{{DESCRIPTION}}</code> : <?= t('page_description') ?></li>
                        <li><code>{{KEYWORDS}}</code> : <?= t('label_keywords') ?></li>
                        <li><code>{{OGP_IMAGE}}</code> : <?= t('page_thumbnail_ogp_image') ?></li>
                        <li><code>{{PAGE_URL}}</code> : <?= t('page_full_url') ?></li>
                        <li><code>{{SITE_URL}}</code> : <?= t('site_root_url') ?></li>
                        <li><code>{{SITE_NAME}}</code> : <?= t('site_title') ?></li>
                        <li><code>{{SITE_DESCRIPTION}}</code> : <?= t('site_description') ?></li>
                        <li><code>{{SITE_OGP_IMAGE}}</code> : <?= t('site_common_ogp_image') ?></li>
                    </ul>
                    <strong style="display:block;margin-top:12px"><?= t('special_wrapper_design') ?></strong>
                    <ul class="hint-list hint-list-sm">
                        <li><code>{{CONTENT}}</code> : <?= t('page_main_content') ?></li>
                        <li><code>{{HEAD_CSS}}</code> : <?= t('combined_css_components') ?></li>
                        <li><code>{{COMPONENT:_global_head}}</code> : <?= t('common_head_section') ?></li>
                        <li><code>{{COMPONENT:_header}}</code> : <?= t('page_header') ?></li>
                        <li><code>{{COMPONENT:_footer}}</code> : <?= t('page_footer') ?></li>

                    </ul>
                </div>
                <div>
                    <strong><?= t('navigation_components') ?></strong>
                    <ul class="hint-list hint-list-sm">
                        <li><code>{{COMPONENT:ID}}</code> : <?= t('embed_registered_component') ?></li>
                        <li><code>{{IMAGE:<?= t('filename') ?>}}</code> : <?= t('display_static_image') ?></li>
                        <li><code>{{AUDIO:<?= t('filename') ?>}}</code> : <?= t('insert_audio_module') ?></li>
                        <li><code>{{VIDEO:<?= t('filename') ?>}}</code> : <?= t('display_video') ?></li>
                        <li><code>{{POST_MD:pageID}}</code> : <?= t('hint_post_md') ?></li>
                        <li><code>{{EXT_MD:url}}</code> : <?= t('hint_ext_md') ?></li>
                        <li><code>{{NAV_LINKS:category}}</code> : <?= t('link_list') ?><span class="hint-desc"> — li.active</span></li>
                        <li><code>{{NAV_CARDS:category:componentID}}</code> : <?= t('card_list') ?></li>
                        <li class="hint-note"><?= t('nav_links_cards_hint') ?></li>
                        <li style="margin-top:8px"><span class="hint-section-label"><?= t('nav_cards_template_vars') ?></span></li>
                        <li><code>{{PAGE_URL}}</code> <code>{{TITLE}}</code> <code>{{DESCRIPTION}}</code> <code>{{OGP_IMAGE}}</code> <code>{{UPDATE_DATE}}</code> <code>{{IS_NEW:N}}</code> <code>{{POST_MD::key}}</code> <code>{{POST_MD::#rowID:key}}</code></li>
                        <li><code>{{IS_ACTIVE}}</code> : <?= t('hint_is_active') ?></li>
                    </ul>
                </div>
                <div>
                    <strong><?= t('label_database') ?></strong>
                    <ul class="hint-list hint-list-sm">
                        <li>
                            <span class="hint-section-label"><?= t('hint_datarow_def') ?></span><br>
                            <code>{{DATA:key}}<?= t('hint_data_value') ?>{{/DATA}}</code> : <?= t('data_block_visible') ?><br>
                            <code>{{DATA:key:GHOST}}<?= t('hint_data_value') ?>{{/DATA}}</code> : <?= t('data_block_hidden') ?><br>
                            <span class="hint-desc"><?= t('hint_data_ascii_rule') ?></span>
                        </li>
                        <li style="margin-top:8px">
                            <span class="hint-section-label"><?= t('hint_datarow_table_def') ?></span><br>
                            <code>{{DATAROW:rowID}}</code><br>
                            <code>{{DATA:key}}<?= t('hint_data_value') ?>{{/DATA}}</code><br>
                            <code>{{/DATAROW}}</code>
                        </li>
                        <li style="margin-top:8px"><span class="hint-section-label"><?= t('hint_datarow_usage') ?></span></li>
                        <li><code>{{POST_MD::key}}</code> : <?= t('data_from_self') ?></li>
                        <li><code>{{POST_MD:pageID:key}}</code> : <?= t('data_from_page') ?></li>
                        <li><code>{{EXT_MD:url:key}}</code> : <?= t('hint_ext_md_key') ?></li>
                        <li style="margin-top:6px"><code>{{POST_MD::#rowID:key}}</code> : <?= t('hint_datarow_self_table') ?></li>
                        <li><code>{{POST_MD:pageID#rowID:key}}</code> : <?= t('hint_datarow_page_table') ?></li>
                        <li><code>{{EXT_MD:url#rowID:key}}</code> : <?= t('hint_datarow_ext_table') ?></li>
                        <li style="margin-top:6px" class="hint-note"><?= t('hint_db_api_hidden') ?></li>
                        <li class="hint-note"><?= t('hint_db_api_public') ?></li>
                    </ul>
                </div>
            </div>
        </div>
    </details>
    <?php
};

// SSG Path Settings
$ssgDir = $settings['ssg_dir'] ?? ($settings['last_ssg_dir'] ?? '');
// サイトルート = CORE_DIR(mikanBox/)の親ディレクトリ
$siteRoot = dirname(CORE_DIR);
$ssgAbsPath = !empty($ssgDir) ? $siteRoot . '/' . ltrim($ssgDir, '/') : $siteRoot;
// プレビューリンク用（admin.phpからの相対パス）
$lastSsgRelPath = '../' . (($ssgDir !== '') ? rtrim($ssgDir, '/') . '/' : '');

$editId = $_GET['edit'] ?? null;
$message = $_SESSION['admin_message'] ?? '';
unset($_SESSION['admin_message']);

// --- Save / Action Processing (with CSRF verification) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_action'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        die(t('err_csrf'));
    }

    // Demo mode: block write operations if not logged in with password
    if ($isDemoMode && !$isLoggedIn) {
        $message = t('msg_demo_mode');
        goto skip_post_actions;
    }
    
    // Path Context for Actions
    $activeSsgDir = isset($_POST['ssg_dir']) ? (string)$_POST['ssg_dir'] : $ssgDir;
    // 絶対パスで解決（CWDに依存しない）
    $activeSsgAbsPath = !empty($activeSsgDir) ? $siteRoot . '/' . ltrim($activeSsgDir, '/') : $siteRoot;
    $activeSsgRelPath = '../' . (($activeSsgDir !== '') ? rtrim($activeSsgDir, '/') . '/' : '');

    // Initialize common renderer for actions
    require_once __DIR__ . '/lib/renderer.php';
    $renderer = new MikanBoxRenderer($settings);

    if ($_POST['save_action'] === 'save_page') {
        $id = $_POST['id'] ?: 'page_' . time();
        $status = $_POST['status'] ?? 'draft';
        $oldId = $_POST['old_id'] ?? null;

        // Reserved slug check: block system directory names
        $coreDirName = basename(CORE_DIR); // e.g. "mikanBox"
        $reservedPrefixes = [$coreDirName, 'media', 'api'];
        $isReserved = false;
        foreach ($reservedPrefixes as $r) {
            if (strcasecmp($id, $r) === 0 || stripos($id, $r . '/') === 0) {
                $isReserved = true; break;
            }
        }
        if ($isReserved) {
            $message = "スラッグ「{$id}」はシステムで予約されています。別のスラッグを使用してください。";
        } // Duplicate slug check: warn if creating new page with existing ID
        elseif (empty($oldId) && loadData(POSTS_DIR, $id) !== null) {
            $message = t('err_slug_exists') ?: "スラッグ「{$id}」はすでに存在します。別のスラッグを使用するか、既存ページを編集してください。";
        } else {

        $updatedAt = $_POST['updated_at'] ?? date('Y-m-d H:i:s');
        $data = [
            'title' => $_POST['title'] ?? '',
            'category' => trim($_POST['category'] ?? ''),
            'status' => $status,
            'description' => $_POST['description'] ?? '',
            'keywords' => $_POST['keywords'] ?? '',
            'ogp_image' => $_POST['ogp_image'] ?? '',
            'content_md' => $_POST['content_md'] ?? '',
            'css' => $_POST['css'] ?? '',
            'wrapper_comp' => $_POST['wrapper_comp'] ?: '_layout',
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'updated_at' => $updatedAt
        ];
        if (saveData(POSTS_DIR, $id, $data)) {
            // Delete old file if URL slug (ID) has changed
            $oldId = $_POST['old_id'] ?? null;
            if ($oldId && $oldId !== $id) {
                deleteData(POSTS_DIR, $oldId);
                // Also delete old static files
                require_once __DIR__ . '/lib/ssg.php';
                $ssg = new MikanBoxSSG($renderer, $activeSsgAbsPath);
                $ssg->deletePage($oldId);
            }

            // --- Automatic SSG Build/Delete Check ---
            require_once __DIR__ . '/lib/ssg.php';
            $ssgOpts = [
                'structure' => $settings['ssg_structure'] ?? 'directory',
                'copy_media' => false,
                'selected_pages' => [$id]
            ];
            $ssg = new MikanBoxSSG($renderer, $activeSsgAbsPath, $ssgOpts);
            
            if ($status !== 'public_static') {
                $ssg->deletePage($id);
            }
            // ----------------------------------------
            if ($id !== 'index') {
                $editId = $id; 
            }
            $message = t('msg_page_saved', $id);
            
            // Redirect if from preview button
            if (isset($_POST['save_and_preview'])) {
                // Use renderer to get canonical link (already root-relative)
                $previewUrl = $renderer->getPageLink($id, '');
                // Convert to relative if needed, but since it's / based, it should work fine from root
                // Actually, header("Location: /path") works.
                header("Location: " . $previewUrl);
                exit;
            }
            $editId = $id; // Keep in edit mode after creation
        } else {
            $message = t('err_page_save');
        }
        } // end duplicate slug check
    }
    elseif ($_POST['save_action'] === 'save_page_status') {
        $id = $_POST['id'];
        $newStatus = $_POST['status'];
        $data = loadData(POSTS_DIR, $id);
        if ($data) {
            $data['status'] = $newStatus;
            saveData(POSTS_DIR, $id, $data);
            
            // Sync SSG if needed
            require_once __DIR__ . '/lib/ssg.php';
            $ssg = new MikanBoxSSG($renderer, $activeSsgAbsPath, ['selected_pages'=>[$id]]);
            if ($newStatus === 'public_static') {
                $ssg->build();
            } else {
                $ssg->deletePage($id);
            }
            $_SESSION['admin_message'] = t('msg_page_saved', $id);
            header("Location: admin.php?view=pages#pages");
            exit;
        }
    }
    elseif ($_POST['save_action'] === 'delete_page') {
        $id = $_POST['id'];
        if ($id !== 'index' && deleteData(POSTS_DIR, $id)) {
            $_SESSION['admin_message'] = t('msg_page_deleted', $id);
            header("Location: admin.php?view=pages");
            exit;
        } else {
            $message = t('err_page_delete');
        }
    }
    elseif ($_POST['save_action'] === 'save_comp') {
        $id = $_POST['id'];
        if(empty($id)) $id = 'comp_' . time();
        $oldId = $_POST['old_id'] ?? null;

        // Duplicate slug check: warn if creating new component with existing ID
        if (empty($oldId) && loadData(COMPONENTS_DIR, $id) !== null) {
            $message = t('err_slug_exists') ?: "コンポーネントID「{$id}」はすでに存在します。別のIDを使用するか、既存コンポーネントを編集してください。";
        } else {
        $data = [
            'html' => $_POST['html'],
            'css' => $_POST['css'] ?? '',
            'is_global' => !isset($_POST['use_scope']),
            'is_wrapper' => isset($_POST['is_wrapper']) ? true : false,
        ];
        if (saveData(COMPONENTS_DIR, $id, $data)) {
            // Delete old file if ID has changed
            if ($oldId && $oldId !== $id) {
                deleteData(COMPONENTS_DIR, $oldId);
            }
            $message = t('msg_comp_saved', $id);
            $editId = $id; // Keep in edit mode after saving
        } else {
            $message = t('err_comp_save');
        }
        } // end duplicate slug check
    }
    elseif ($_POST['save_action'] === 'delete_comp') {
        $id = $_POST['id'];
        if (deleteData(COMPONENTS_DIR, $id)) {
            $_SESSION['admin_message'] = t('msg_comp_deleted', $id);
            header("Location: admin.php?view=components");
            exit;
        } else {
            $message = t('err_comp_delete');
        }
    }
    elseif ($_POST['save_action'] === 'upload_media') {
        if (isset($_FILES['image'])) {
            $err = $_FILES['image']['error'];
            if ($err === UPLOAD_ERR_OK) {
                $tmpPath = $_FILES['image']['tmp_name'];
                $name = basename($_FILES['image']['name']);
                $name = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $name);
                
                // Security: Validate Extension
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'mp3', 'm4a', 'mp4'];
                if (!in_array($ext, $allowedExts)) {
                    $message = t('err_upload_failed') . " (Invalid file extension)";
                } else {
                    $targetPath = MEDIA_DIR . '/' . $name;
                    if (!is_dir(MEDIA_DIR)) mkdir(MEDIA_DIR, 0777, true);
                    if (move_uploaded_file($tmpPath, $targetPath)) {
                        $message = t('msg_media_uploaded', $name);
                    } else {
                        $message = t('err_upload_failed');
                    }
                }
            } else {
                switch($err) {
                    case UPLOAD_ERR_INI_SIZE: $message = t('err_file_size'); break;
                    case UPLOAD_ERR_NO_FILE: $message = t('err_no_file'); break;
                    default: $message = t('err_upload_failed') . " ($err)"; break;
                }
            }
        }
    }
    elseif ($_POST['save_action'] === 'delete_media') {
        $name = basename($_POST['filename']);
        $path = MEDIA_DIR . '/' . $name;
        if (file_exists($path) && unlink($path)) {
            $message = t('msg_media_deleted', $name);
        } else {
            $message = t('err_delete_failed');
        }
    }
    elseif ($_POST['save_action'] === 'resize_media') {
        $name = basename($_POST['filename']);
        $targetPath = MEDIA_DIR . '/' . $name;
        
        if (file_exists($targetPath) && function_exists('imagecreatefromjpeg')) {
            $info = getimagesize($targetPath);
            if ($info) {
                $srcW = $info[0];
                $srcH = $info[1];
                $type = $info[2];
                
                $newWidth = !empty($_POST['new_width']) ? (int)$_POST['new_width'] : null;
                $newHeight = !empty($_POST['new_height']) ? (int)$_POST['new_height'] : null;

                if ($newWidth || $newHeight) {
                    if ($newWidth && !$newHeight) {
                        $newHeight = (int)($srcH * ($newWidth / $srcW));
                    } elseif (!$newWidth && $newHeight) {
                        $newWidth = (int)($srcW * ($newHeight / $srcH));
                    }
                    
                    $dstImg = imagecreatetruecolor($newWidth, $newHeight);
                    
                    switch ($type) {
                        case IMAGETYPE_JPEG: $srcImg = imagecreatefromjpeg($targetPath); break;
                        case IMAGETYPE_PNG: 
                            $srcImg = imagecreatefrompng($targetPath);
                            imagealphablending($dstImg, false);
                            imagesavealpha($dstImg, true);
                            break;
                        case IMAGETYPE_GIF: $srcImg = imagecreatefromgif($targetPath); break;
                        case IMAGETYPE_WEBP: $srcImg = imagecreatefromwebp($targetPath); break;
                        default: $srcImg = null;
                    }
                    
                    if ($srcImg) {
                        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $srcW, $srcH);
                        switch ($type) {
                            case IMAGETYPE_JPEG: imagejpeg($dstImg, $targetPath, 85); break;
                            case IMAGETYPE_PNG: imagepng($dstImg, $targetPath); break;
                            case IMAGETYPE_GIF: imagegif($dstImg, $targetPath); break;
                            case IMAGETYPE_WEBP: imagewebp($dstImg, $targetPath); break;
                        }
                        $message = t('msg_media_resized', $name);
                    }
                }
            }
        } else {
            $message = t('err_resize_failed');
        }
    }
    elseif ($_POST['save_action'] === 'save_settings' || $_POST['save_action'] === 'save_memo' || $_POST['save_action'] === 'save_prompt') {
        // Shared logic for saving settings (Fix #8)
        if ($_POST['save_action'] === 'save_settings' || $_POST['save_action'] === 'save_prompt') {
            if (isset($_POST['site_name'])) $settings['site_name'] = $_POST['site_name'];
            if (isset($_POST['system_lang'])) $settings['system_lang'] = $_POST['system_lang'];
            if (isset($_POST['ssg_root_url'])) $settings['ssg_root_url'] = $_POST['ssg_root_url'];
            if (isset($_POST['description'])) $settings['description'] = $_POST['description'];
            if (isset($_POST['keywords'])) $settings['keywords'] = $_POST['keywords'];
            if (isset($_POST['ogp_image'])) $settings['ogp_image'] = $_POST['ogp_image'];
            if (isset($_POST['ai_prompt'])) $settings['ai_prompt'] = $_POST['ai_prompt'];

            // Password change (Skip current password check for simplicity if from admin panel, but new_password must be set)
            if (!empty($_POST['new_password'])) {
                $settings['password_hash'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            }
        } elseif ($_POST['save_action'] === 'save_memo') {
            $settings['memo'] = $_POST['memo'] ?? '';
        }
        
        if (file_put_contents(SETTINGS_FILE, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
            $message = t('msg_update_success');
        } else {
            $message = t('err_save_failed');
        }
    }
    elseif ($_POST['save_action'] === 'ssg_save_settings') {
        $settings['ssg_dir'] = $_POST['ssg_dir'] ?? '';
        $settings['ssg_structure'] = $_POST['ssg_structure'] ?? 'directory';
        if (file_put_contents(SETTINGS_FILE, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT))) {
            $message = t('msg_update_success');
        } else {
            $message = t('err_save_failed');
        }
        // Update helper variables for immediate use in UI
        $ssgDir = $settings['ssg_dir'] ?? '';
        $lastSsgRelPath = '../' . (($ssgDir !== '') ? rtrim($ssgDir, '/') . '/' : '');
    }
    elseif ($_POST['save_action'] === 'ssg_build') {
        require_once __DIR__ . '/lib/ssg.php';

        $ssgOpts = [
            'structure' => $_POST['ssg_structure'] ?? ($settings['ssg_structure'] ?? 'directory'),
            'selected_pages' => [] // Build all that are public_static
        ];

        $ssg = new MikanBoxSSG($renderer, $activeSsgAbsPath, $ssgOpts);
        $ssg->clear(); // Remove old files (handles structure format changes)
        $results = $ssg->build();
        $built = array_filter($results, fn($r) => strpos($r, 'Error') === false);
        $errors = array_filter($results, fn($r) => strpos($r, 'Error') !== false);
        $message = t('msg_ssg_finished', count($built));
        if (!empty($errors)) $message .= ' / ' . implode(', ', $errors);
        if (empty($results)) $message .= ' (公開(HTML)に設定されたページがありません)';
        
        // Save settings as well
        $settings['ssg_dir'] = $activeSsgDir;
        $settings['ssg_structure'] = $ssgOpts['structure'];
        file_put_contents(SETTINGS_FILE, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $_SESSION['admin_message'] = $message;
        header("Location: admin.php?view=settings#ssg");
        exit;
    }
    elseif ($_POST['save_action'] === 'ssg_clear') {
        require_once __DIR__ . '/lib/ssg.php';
        $ssg = new MikanBoxSSG($renderer, $activeSsgAbsPath, []);
        $msg = $ssg->clear();
        $message = "Cleared: " . $msg;
    }
    elseif ($_POST['save_action'] === 'ssg_delete_page') {
        require_once __DIR__ . '/lib/ssg.php';
        $pid = $_POST['id'];
        $ssg = new MikanBoxSSG($renderer, $activeSsgAbsPath, []);
        $count = $ssg->deletePage($pid);
        $message = "Static files for '$pid' deleted ($count files).";
    }
    elseif ($_POST['save_action'] === 'download_backup_data' || $_POST['save_action'] === 'download_backup_media') {
        $mode = $_POST['save_action'] === 'download_backup_data' ? 'data' : 'media';
        $sourceDir = $mode === 'data' ? DATA_DIR : MEDIA_DIR;
        if (!is_dir($sourceDir)) {
            $message = t('error_save_failed');
        } else {
            $zip = new ZipArchive();
            $zipFile = DATA_DIR . "/backup_{$mode}_" . date('YmdHis') . '.zip';
            if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir), RecursiveIteratorIterator::LEAVES_ONLY);
                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = $mode . '/' . substr($filePath, strlen($sourceDir) + 1);
                        if ($mode === 'data' && strpos(basename($filePath), 'backup_') === 0) continue; 
                        $zip->addFile($filePath, $relativePath);
                    }
                }
                $zip->close();
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . basename(__DIR__) . '_' . $mode . '_' . date('Ymd') . '.zip"');
                readfile($zipFile); unlink($zipFile); exit;
            }
        }
    }
    elseif ($_POST['save_action'] === 'generate_mcp_key') {
        $newKey = bin2hex(random_bytes(24));
        $settings['mcp_api_key'] = $newKey;
        $saved = (bool)file_put_contents(SETTINGS_FILE, json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        if (isset($_POST['ajax_request'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success'     => $saved,
                'message'     => $saved ? t('msg_mcp_key_generated') : t('err_save_failed'),
                'mcp_api_key' => $saved ? $newKey : '',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $_SESSION['admin_message'] = $saved ? t('msg_mcp_key_generated') : t('err_save_failed');
        header('Location: ' . basename(__FILE__) . '#mcp-api-key');
        exit;
    }
    elseif ($_POST['save_action'] === 'change_status') {
        $id = $_POST['id'];
        $status = $_POST['status'];
        $data = loadData(POSTS_DIR, $id);
        if ($data) {
            $data['status'] = $status;
            if (saveData(POSTS_DIR, $id, $data)) {
                require_once __DIR__ . '/lib/ssg.php';
                $ssgOpts = [
                    'structure' => $settings['ssg_structure'] ?? 'directory',
                    'copy_media' => false,
                    'selected_pages' => [$id]
                ];
                $ssg = new MikanBoxSSG($renderer, $activeSsgAbsPath, $ssgOpts);
                if ($status === 'public_static') {
                    $ssg->build();
                } else {
                    $ssg->deletePage($id);
                }
                $message = t('msg_status_changed', $id);
            }
        }
    }
}
skip_post_actions:

// Return JSON response for AJAX saves and skip rendering page
if (isset($_POST['ajax_request'])) {
    $responseData = [
        'success' => true,
        'message' => $message ?? t('msg_update_success'),
        'editId' => $editId ?? null
    ];
    // Compute preview URL for page saves so JS can inject/update the preview button
    if (($_POST['save_action'] ?? '') === 'save_page' && !empty($editId)) {
        $savedStatus = $_POST['status'] ?? 'draft';
        $ssgStruct = $settings['ssg_structure'] ?? 'directory';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $siteDir = dirname(dirname($_SERVER['SCRIPT_NAME']));
        if ($siteDir === '/' || $siteDir === '.') $siteDir = '';
        $siteBaseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $siteDir;
        if ($editId === 'index') {
            $responseData['preview_url'] = $siteBaseUrl . '/';
        } elseif ($savedStatus === 'public_static') {
            $ssgDirForUrl = $settings['ssg_dir'] ?? '';
            $staticRoot = !empty($settings['ssg_root_url'])
                ? rtrim($settings['ssg_root_url'], '/')
                : $siteBaseUrl . (($ssgDirForUrl !== '') ? '/' . trim($ssgDirForUrl, '/') : '');
            $responseData['preview_url'] = $staticRoot . '/' . $editId . ($ssgStruct === 'directory' ? '/' : '.html');
        } else {
            $responseData['preview_url'] = $siteBaseUrl . '/' . $editId;
        }
    }
    header('Content-Type: application/json; charset=utf-8');
    if (ob_get_length()) ob_clean();
    echo json_encode($responseData, JSON_UNESCAPED_UNICODE);
    exit;
}

// --- Fetch data for edit mode ---
$editData = null;
// $editId might be set in POST processing, so only fetch from GET if null
if ($editId === null) {
    $editId = isset($_GET['edit']) ? $_GET['edit'] : null;
}

if ($editId) {
    if ($view === 'pages') $editData = loadData(POSTS_DIR, $editId);
    elseif ($view === 'components') $editData = loadData(COMPONENTS_DIR, $editId);
}

// --- AJAX Editor Fragment Endpoint ---
if (isset($_GET['ajax_editor'])) {
    $helpFile = (getSystemLanguage() === 'ja') ? 'https://yoshihiko.com/mikanbox/help_ja.html' : 'https://yoshihiko.com/mikanbox/help_en.html';
    $site_name = $settings['site_name'] ?? SITE_NAME;
    $ssgDir = $settings['ssg_dir'] ?? ($settings['last_ssg_dir'] ?? '');
    $lastSsgRelPath = '../' . (($ssgDir !== '') ? rtrim($ssgDir, '/') . '/' : '');
    ob_start();
    if ($view === 'pages') {
        include __DIR__ . '/views/page-editor.php';
    } elseif ($view === 'components') {
        include __DIR__ . '/views/design-editor.php';
    }
    $htmlFromFragment = ob_get_clean();
    if (ob_get_length()) ob_clean(); // Clean the top-level buffer
    header('Content-Type: text/html; charset=UTF-8');
    echo $htmlFromFragment;
    exit;
}

// --- AJAX Media Fragment Endpoint ---
if (isset($_GET['ajax_media'])) {
    ob_start();
    include __DIR__ . '/views/media.php';
    $html = ob_get_clean();
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;
}

// --- AJAX Pages List Fragment Endpoint ---
if (isset($_GET['ajax_pages'])) {
    $helpFile = (getSystemLanguage() === 'ja') ? 'https://yoshihiko.com/mikanbox/help_ja.html' : 'https://yoshihiko.com/mikanbox/help_en.html';
    $editId = null;
    ob_start();
    include __DIR__ . '/views/pages.php';
    $html = ob_get_clean();
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;
}

// --- AJAX Comps List Fragment Endpoint ---
if (isset($_GET['ajax_comps'])) {
    $helpFile = (getSystemLanguage() === 'ja') ? 'https://yoshihiko.com/mikanbox/help_ja.html' : 'https://yoshihiko.com/mikanbox/help_en.html';
    $editId = null;
    ob_start();
    include __DIR__ . '/views/design.php';
    $html = ob_get_clean();
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;
}

// ==========================================
// Admin Panel HTML
// ==========================================
$helpFile = (getSystemLanguage() === 'ja') ? 'https://yoshihiko.com/mikanbox/help_ja.html' : 'https://yoshihiko.com/mikanbox/help_en.html';
if (ob_get_length()) ob_clean();
?>
<!DOCTYPE html>
<html lang="<?= getSystemLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <title>🍊mikanBox - <?= t('admin_site_title') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=block" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<script>(function(){
    // ページ読み込み中の全transitionを抑制（scrollイベントによるフラッシュ防止）
    var s=document.createElement('style');s.id='init-notransition';
    s.textContent='body,.side-nav a{transition:none!important}';
    document.head.appendChild(s);
    var bg=sessionStorage.getItem('mikan_bg');
    if(bg){document.body.style.backgroundColor=bg;sessionStorage.removeItem('mikan_bg');}
})();</script>

<?php
function getIcon($name) {
    $icons = [
        'page' => 'description',
        'component' => 'widgets',
        'media' => 'image',
        'save' => 'save',
        'view' => 'visibility',
        'upload' => 'upload',
        'video' => 'videocam',
        'audio' => 'music_note',
        'cloud' => 'cloud_upload',
        'logout' => 'logout',
        'globe' => 'language',
        'sparkles' => 'auto_awesome',
        'edit' => 'edit',
        'delete' => 'delete',
        'download' => 'download',
        'arrow_back' => 'arrow_back',
        'add' => 'add',
        'copy' => 'content_copy',
        'open_in_new' => 'open_in_new'
    ];
    $iconName = $icons[$name] ?? '';
    return $iconName ? '<span class="material-symbols-outlined icon">' . $iconName . '</span>' : '';
}
?>


<div id="drop-zone"><?= getIcon('cloud') ?> <?= t('hint_drop_upload') ?></div>

<nav class="side-nav">
    <div class="side-nav-brand">
        <span class="emoji">🍊</span>
        <span class="text">mikanBox</span>
    </div>
    
    <a href="#pages" class="nav-pages" title="<?= t('nav_pages') ?>">
        <?= getIcon('page') ?>
        <span><?= t('nav_pages') ?></span>
    </a>

    <?php if ($view === 'pages' && ($editId !== null || isset($_GET['new']))): ?>
    <a href="#page-editor" class="nav-edit active" data-editor-type="page" title="<?= t('btn_edit') ?>">
        <?= getIcon('edit') ?>
        <span><?= t('btn_edit') ?></span>
        <span class="close-badge" data-url="admin.php#pages" title="閉じる">×</span>
    </a>
    <?php endif; ?>
    <a href="#site" class="nav-settings" title="<?= t('nav_settings') ?>">
        <?= getIcon('save') ?>
        <span><?= t('nav_settings') ?></span>
    </a>
    <a href="#design" class="nav-design" title="<?= t('nav_design') ?>">
        <?= getIcon('component') ?>
        <span><?= t('nav_design') ?></span>
    </a>

    <?php if ($view === 'components' && ($editId !== null || isset($_GET['new']))): ?>
    <a href="#design-editor" class="nav-edit active" data-editor-type="design" title="<?= t('btn_edit') ?>">
        <?= getIcon('edit') ?>
        <span><?= t('btn_edit') ?></span>
        <span class="close-badge" data-url="admin.php#design" title="閉じる">×</span>
    </a>
    <?php endif; ?>

    <a href="#media" class="nav-media" title="<?= t('nav_media') ?>">
        <?= getIcon('media') ?>
        <span><?= t('nav_media') ?></span>
    </a>
</nav>
<div class="main">
    <!-- ==============================================
         Main Unified View (All sections via includes)
         ============================================== -->
    <div class="page-top-links">
        <a href="<?= $lastSsgRelPath ?>" target="_blank"><?= t('admin_view_site') ?></a>
        <?php if ($isDemoMode && !$isLoggedIn): ?>
        <a href="?login=1"><?= t('btn_login') ?></a>
        <?php else: ?>
        <a href="?action=logout"><?= t('admin_logout') ?></a>
        <?php endif; ?>
    </div>
    

    <?php include __DIR__ . '/views/pages.php'; ?>

    <?php include __DIR__ . '/views/site.php'; ?>

    <?php include __DIR__ . '/views/design.php'; ?>

    <?php include __DIR__ . '/views/media.php'; ?>




    <script>
    // ブラウザのスクロール復元を無効にして競合させない
    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';
    // エディター読み込み時: 遅延なしで即スムーズスクロール開始（window.onload より大幅に早い）
    (function() {
        var hash = window.location.hash;
        if (hash === '#page-editor' || hash === '#design-editor') {
            var el = document.querySelector(hash);
            if (el) el.scrollIntoView({ behavior: 'smooth' });
        }
    })();

    const dropZone = document.getElementById('drop-zone');
    const fileInput = document.getElementById('file-input');
    const uploadForm = document.getElementById('upload-form');

    window.addEventListener('dragover', (e) => {
        e.preventDefault();
        if (dropZone) dropZone.classList.add('active');
    });

    window.addEventListener('dragleave', (e) => {
        if (e.relatedTarget === null && dropZone) {
            dropZone.classList.remove('active');
        }
    });

    window.addEventListener('drop', async (e) => {
        e.preventDefault();
        if (dropZone) dropZone.classList.remove('active');
        const files = e.dataTransfer.files;
        if (files.length > 0 && uploadForm) {
            const csrfInput = uploadForm.querySelector('input[name="csrf_token"]');
            const formData = new FormData();
            formData.append('save_action', 'upload_media');
            if (csrfInput) formData.append('csrf_token', csrfInput.value);
            formData.append('image', files[0]);
            await doMediaUpload(formData);
        }
    });

    if (uploadForm) {
        uploadForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            await doMediaUpload(new FormData(uploadForm));
        });
    }

    async function doMediaUpload(formData) {
        const btn = document.getElementById('upload-btn');
        const origHtml = btn ? btn.innerHTML : '';
        if (btn) { btn.textContent = '<?= t('msg_uploading') ?>'; btn.disabled = true; }
        formData.append('ajax_request', '1');
        try {
            const res = await fetch(window.location.href, { method: 'POST', body: formData });
            const json = await res.json().catch(() => ({}));
            showToast(json.message || '', !json.success);
            if (json.success) {
                if (fileInput) fileInput.value = '';
                await refreshMediaGrid();
            }
        } catch(err) {
            showToast('<?= t('err_upload_failed') ?>', true);
        } finally {
            if (btn) { btn.innerHTML = origHtml; btn.disabled = false; }
        }
    }

    async function refreshMediaGrid() {
        try {
            const res = await fetch('?ajax_media=1');
            const html = await res.text();
            const temp = document.createElement('div');
            temp.innerHTML = html;
            const newGrid = temp.querySelector('.media-grid');
            const oldGrid = document.querySelector('.media-grid');
            if (newGrid && oldGrid) oldGrid.outerHTML = newGrid.outerHTML;
        } catch(err) {}
    }

    async function refreshPageList() {
        try {
            const res = await fetch('?ajax_pages=1&view=pages');
            const html = await res.text();
            const temp = document.createElement('div');
            temp.innerHTML = html;
            const newWrap = temp.querySelector('#pages-table-wrap');
            const oldWrap = document.querySelector('#pages-table-wrap');
            if (newWrap && oldWrap) oldWrap.outerHTML = newWrap.outerHTML;
        } catch(err) {}
    }

    async function refreshCompList() {
        try {
            const res = await fetch('?ajax_comps=1&view=design');
            const html = await res.text();
            const temp = document.createElement('div');
            temp.innerHTML = html;
            const newWrap = temp.querySelector('#comps-table-wrap');
            const oldWrap = document.querySelector('#comps-table-wrap');
            if (newWrap && oldWrap) oldWrap.outerHTML = newWrap.outerHTML;
        } catch(err) {}
    }

    // Resize and delete media forms - event delegation
    document.addEventListener('submit', async function(e) {
        const form = e.target;
        const actionInput = form.querySelector('input[name="save_action"]');
        if (!actionInput) return;
        const action = actionInput.value;
        if (action === 'resize_media' || action === 'delete_media') {
            e.preventDefault();
            const formData = new FormData(form);
            formData.append('ajax_request', '1');
            try {
                const res = await fetch(window.location.href, { method: 'POST', body: formData });
                const json = await res.json().catch(() => ({}));
                showToast(json.message || '', !json.success);
                if (json.success) await refreshMediaGrid();
            } catch(err) {
                showToast('<?= t('err_save_failed') ?>', true);
            }
        }
    });

    function basename(path) {
        return path.split('/').reverse()[0];
    }
    function initAiPrompt() {
        const textarea = document.getElementById('ai-prompt-editor');
        if (!textarea) return;

        const savedPrompt = <?= json_encode($settings['ai_prompt'] ?? '') ?>;
        if (savedPrompt) {
            textarea.value = savedPrompt;
            return;
        }
        const promptId = basename(window.location.pathname.replace('/admin.php', ''));
        const promptContent = `<?= t('prompt_expert_intro', basename(__DIR__)) ?>
<?= t('prompt_sys_info') ?>
- CMS: 🍊mikanBox (Expert in Component-driven flat-file CMS)
- Structure: ${promptId}/ (admin, config, lib, data), media/
- AI Interface: MCP (Model Context Protocol) enabled. Use tools to read/write pages, components, and media directly.
- Tags: {{COMPONENT:id}}, {{NAV_CARDS:id1,id2}}, {{TITLE}}, {{DESCRIPTION}}, {{CONTENT}}, {{DATAROW:n}}, {{DATA:key}}
- Images: Stored in "media/", use "images/filename" in code. AI can upload images using MCP tools.

[Design & Component Rules]
1. Component Naming:
   - Components starting with "_" (e.g., _header) are system defaults. 
   - When creating custom design parts, use names that do NOT start with "_".
2. Best Practices:
   - For quick builds: Use standard "_layout" and write the page body in Markdown.
   - For full custom layouts: Use "_ai" as the page wrapper. In this case, you must include "{{COMPONENT:_global_head}}" within the <head> tags to load global styles and metadata.
   - Use "Wrappers" (structural) and "Parts" (reusable) components efficiently.
   - For page-specific design, write CSS in the "Page CSS" section rather than inside reusable components.
3. Content Formatting:
   - Prefer Markdown for the page body. 
   - You can use "[]{ .className }" syntax in Markdown for styling specific elements.

[Current Request]
Please enter your request here.`;
        textarea.value = promptContent;
    }
    async function copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            alert('<?= t('msg_copied') ?>');
        } catch (err) {
            // Fallback for older browsers or non-secure contexts
            const textArea = document.createElement("textarea");
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                alert('<?= t('msg_copied') ?>');
            } catch (err) {
                alert('<?= t('msg_copy_failed') ?>');
            }
            document.body.removeChild(textArea);
        }
    }
    async function csvConvertAndCopy() {
        const fileInput = document.getElementById('csv-file-input');
        const btn = document.getElementById('csv-copy-btn');
        if (!fileInput.files[0]) { alert('<?= t('csv_no_file') ?>'); return; }
        const buffer = await fileInput.files[0].arrayBuffer();
        const bytes = new Uint8Array(buffer);
        let encoding = 'UTF-8';
        if (bytes[0] === 0xEF && bytes[1] === 0xBB && bytes[2] === 0xBF) {
            encoding = 'UTF-8'; // UTF-8 BOM
        } else {
            const probe = new TextDecoder('UTF-8', { fatal: false }).decode(buffer);
            if (probe.includes('\uFFFD')) encoding = 'Shift_JIS';
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            const text = e.target.result.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
            const rows = [];
            let cur = '', inQ = false;
            const fields = [];
            for (let i = 0; i <= text.length; i++) {
                const c = text[i];
                if (c === '"') {
                    if (inQ && text[i+1] === '"') { cur += '"'; i++; }
                    else inQ = !inQ;
                } else if ((c === ',' && !inQ)) {
                    fields.push(cur); cur = '';
                } else if ((c === '\n' && !inQ) || c === undefined) {
                    fields.push(cur); cur = '';
                    if (fields.some(f => f.trim())) rows.push([...fields]);
                    fields.length = 0;
                } else {
                    cur += c;
                }
            }
            if (rows.length < 2) return;
            const headers = rows[0].map(h => h.trim().replace(/[^a-zA-Z0-9]/g, '_').replace(/^_+|_+$/g, ''));
            let output = '';
            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                output += `{{DATAROW:${i}}}\n`;
                headers.forEach((h, j) => {
                    if (h) output += `{{DATA:${h}}}${(row[j] || '').trim()}{{/DATA}}\n`;
                });
                output += `{{/DATAROW}}\n\n`;
            }
            copyToClipboard(output).then(() => {
                const orig = btn.innerHTML;
                btn.textContent = '<?= t('csv_copied') ?>';
                setTimeout(() => btn.innerHTML = orig, 2000);
            });
        };
        reader.readAsText(fileInput.files[0], encoding);
    }
    function copyAiPrompt() {
        const textarea = document.getElementById('ai-prompt-editor');
        copyToClipboard(textarea.value).then(() => {
            alert('<?= t('msg_prompt_copied') ?>');
        });
    }
    async function changePageStatus(id, newStatus) {
        const formData = new FormData();
        formData.append('save_action', 'save_page_status');
        formData.append('id', id);
        formData.append('status', newStatus);
        formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
        formData.append('ajax_request', '1');
        try {
            const res = await fetch(window.location.href, { method: 'POST', body: formData });
            if (res.ok) {
                const json = await res.json().catch(()=>({}));
                showToast(json.message || '<?= t('msg_update_success') ?? '保存しました' ?>');
                
                // Update class color dynamically without reload
                const selectEl = document.querySelector(`select[onchange*="'${id}'"]`);
                if (selectEl) {
                    selectEl.classList.remove('static', 'dynamic', 'draft');
                    if (newStatus === 'public_static') selectEl.classList.add('static');
                    else if (newStatus === 'public_dynamic') selectEl.classList.add('dynamic');
                    else selectEl.classList.add('draft');
                }
            } else {
                showToast('保存に失敗しました', true);
            }
        } catch(err) {
            showToast('通信エラー', true);
        }
    }
    let isNavigating = false;
    function updateScrollPos() {
        // ナビゲーション中はスクロール位置の更新をスキップ（アイコンの一瞬の色変化を防ぐ）
        if (isNavigating) return;
        const sections = [
            { id: 'pages', color: '#e0f2fe', navId: 'nav-pages' }, // page-list
            { id: 'site', color: '#ffffff', navId: 'nav-settings' }, // site settings start
            { id: 'ssg-accordion', color: '#ffffff', navId: 'nav-settings' },
            { id: 'settings', color: '#ffffff', navId: 'nav-settings' },
            { id: 'backup', color: '#ffffff', navId: 'nav-settings' },
            { id: 'ai-prompt', color: '#ffffff', navId: 'nav-settings' },
            { id: 'design', color: '#fffbeb', navId: 'nav-design' }, // design
            { id: 'media', color: '#f0fdf4', navId: 'nav-media' }    // media
        ];

        let scrollY = window.scrollY + window.innerHeight / 3;
        let current = sections[0];

        for (const sec of sections) {
            const el = document.getElementById(sec.id);
            if (el) {
                const rect = el.getBoundingClientRect();
                const top = rect.top + window.scrollY - 100;
                if (scrollY >= top) {
                    current = sec;
                }
            }
        }

        if (current) {
            document.body.style.backgroundColor = current.color;
            document.querySelectorAll('.side-nav a:not(.nav-edit)').forEach(btn => {
                const shouldBeActive = btn.classList.contains(current.navId);
                if (shouldBeActive && !btn.classList.contains('active')) {
                    btn.classList.add('active');
                } else if (!shouldBeActive && btn.classList.contains('active')) {
                    btn.classList.remove('active');
                }
            });
        }
    }

    // Scroll to parent section first, then navigate after scroll completes
    function navigateAfterScroll(targetUrl) {
        if (isNavigating) return;
        isNavigating = true;

        // Extract hash from targetUrl to find scroll target
        const hashIndex = targetUrl.indexOf('#');
        const hash = hashIndex !== -1 ? targetUrl.substring(hashIndex) : null;
        const scrollTarget = hash ? document.querySelector(hash) : null;

        // ナビアイコンのtransitionを停止してスクロール中のピクつきを防ぐ
        document.querySelectorAll('.side-nav a').forEach(a => a.style.transition = 'none');

        // Apply closing animation to nav-edit button if it exists
        const navEdit = document.querySelector('.side-nav .nav-edit');
        const hasClosingAnim = navEdit && targetUrl.indexOf('edit=') === -1 && targetUrl.indexOf('new=1') === -1;
        if (hasClosingAnim) {
            navEdit.classList.add('closing');
        }

        function doNavigate() {
            window.isDirty = false;
            sessionStorage.setItem('mikan_bg', document.body.style.backgroundColor || '');
            window.location.href = targetUrl;
        }

        // Wait for closing animation to finish (500ms) before navigating
        const animDelay = hasClosingAnim ? 500 : 0;

        if (scrollTarget) {
            scrollTarget.scrollIntoView({ behavior: 'smooth' });
            // Wait for scroll to finish, then navigate
            let scrollTimer;
            const onScrollEnd = () => {
                clearTimeout(scrollTimer);
                scrollTimer = setTimeout(() => {
                    window.removeEventListener('scroll', onScrollEnd);
                    // Ensure animation has completed before navigating
                    const elapsed = performance.now() - navStartTime;
                    const remaining = Math.max(0, animDelay - elapsed);
                    setTimeout(doNavigate, remaining);
                }, 150); // 150ms after last scroll event = scroll finished
            };
            const navStartTime = performance.now();
            window.addEventListener('scroll', onScrollEnd);
            // Fallback: if we're already at the target (no scroll happens)
            scrollTimer = setTimeout(() => {
                window.removeEventListener('scroll', onScrollEnd);
                const elapsed = performance.now() - navStartTime;
                const remaining = Math.max(0, animDelay - elapsed);
                setTimeout(doNavigate, remaining);
            }, 600);
        } else {
            setTimeout(doNavigate, animDelay);
        }
    }

    // ==========================================
    // SPA Editor Open / Close
    // ==========================================
    const csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';

    function createNavEditButton(type) {
        // type: 'page' or 'design'
        const hash = type === 'page' ? '#page-editor' : '#design-editor';
        const closeUrl = type === 'page' ? 'admin.php#pages' : 'admin.php#design';
        const a = document.createElement('a');
        a.href = hash;
        a.className = 'nav-edit active';
        a.dataset.editorType = type;
        a.title = '<?= t('btn_edit') ?>';
        a.innerHTML = '<?= getIcon('edit') ?><span><?= t('btn_edit') ?></span>' +
            '<span class="close-badge" data-url="' + closeUrl + '" title="閉じる">×</span>';
        return a;
    }

    function getNavEditAnchor(type) {
        // nav-edit を挿入すべき位置の直後の兄弟要素を返す
        if (type === 'page') return document.querySelector('.side-nav .nav-settings');
        return document.querySelector('.side-nav .nav-media');
    }

    function bindDirtyTrackers(container) {
        container.querySelectorAll('input, textarea, select').forEach(el => {
            el.addEventListener('input', () => { window.isDirty = true; });
            el.addEventListener('change', () => { window.isDirty = true; });
        });
    }

    let _spaEditorAbortController = null;

    function spaOpenEditor(type, editId) {
        // type: 'page' or 'design'
        const view = type === 'page' ? 'pages' : 'design';
        const slotId = type === 'page' ? 'page-editor-slot' : 'design-editor-slot';
        const editorId = type === 'page' ? 'page-editor' : 'design-editor';
        const param = editId ? 'edit=' + encodeURIComponent(editId) : 'new=1';
        const url = 'admin.php?view=' + view + '&' + param + '&ajax_editor=1';

        // 前のfetchが進行中なら中断する（ダブルクリック対策）
        if (_spaEditorAbortController) {
            _spaEditorAbortController.abort();
        }
        _spaEditorAbortController = new AbortController();

        // 同じtypeのエディタが既に開いていれば閉じる（別typeは維持）
        const existing = document.getElementById(editorId);
        if (existing) {
            const slot = document.getElementById(slotId);
            if (slot) slot.innerHTML = '';
        }
        const oldNav = document.querySelector(`.side-nav .nav-edit[data-editor-type="${type}"]`);
        if (oldNav) oldNav.remove();

        fetch(url, { signal: _spaEditorAbortController.signal })
            .then(r => r.text())
            .then(html => {
                const slot = document.getElementById(slotId);
                if (!slot) return;

                // エディタHTMLをDOMに挿入
                slot.innerHTML = html;
                const editor = document.getElementById(editorId);
                if (!editor) return;

                // nav-editボタンを追加
                const navBtn = createNavEditButton(type);
                const anchor = getNavEditAnchor(type);
                if (anchor) anchor.parentNode.insertBefore(navBtn, anchor);

                // isDirtyトラッカーをバインド
                bindDirtyTrackers(editor);
                window.isDirty = false;

                // URLを更新
                const newUrl = 'admin.php?view=' + view + '&' + param + '#' + editorId;
                history.pushState({ spaEditor: true, type, editId }, '', newUrl);

                // レイアウト確定を待ってからスクロール→アニメーション開始
                requestAnimationFrame(() => {
                    editor.scrollIntoView({ behavior: 'smooth' });
                    // スクロール開始後にフェードインアニメーション
                    requestAnimationFrame(() => {
                        editor.classList.add('spa-entering');
                        editor.addEventListener('animationend', () => {
                            editor.classList.remove('spa-entering');
                        }, { once: true });
                    });
                });
            })
            .catch(err => {
                if (err.name === 'AbortError') return; // ダブルクリックによるキャンセルは無視
                showToast('エディタの読み込みに失敗しました', true);
                console.error(err);
            });
    }

    function spaCloseEditor(type) {
        const editorId = type === 'page' ? 'page-editor' : 'design-editor';
        const slotId = type === 'page' ? 'page-editor-slot' : 'design-editor-slot';
        const sectionId = type === 'page' ? 'pages' : 'design';
        const editor = document.getElementById(editorId);
        const navEdit = document.querySelector(`.side-nav .nav-edit[data-editor-type="${type}"]`);

        isNavigating = true;
        document.querySelectorAll('.side-nav a').forEach(a => a.style.transition = 'none');

        // nav-editの閉じるアニメーション
        if (navEdit) navEdit.classList.add('closing');

        if (editor) {
            // Phase 1: フェードアウト (opacity + transform)
            editor.classList.add('spa-leaving');

            // Phase 2: フェード完了後、高さをスムーズに収縮
            const fadeDuration = 350;
            setTimeout(() => {
                const h = editor.offsetHeight;
                // アニメーションをリセットし、高さを固定してからtransitionで収縮
                editor.style.animation = 'none';
                editor.style.opacity = '0';
                editor.style.height = h + 'px';
                editor.style.overflow = 'hidden';
                editor.style.padding = '0';
                editor.style.margin = '0';
                // reflow
                editor.offsetHeight;
                editor.style.transition = 'height 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
                editor.style.height = '0';
            }, fadeDuration);

            // Phase 3: 収縮完了後にDOM除去
            const totalDuration = fadeDuration + 320;
            setTimeout(() => {
                const slot = document.getElementById(slotId);
                if (slot) slot.innerHTML = '';
                if (navEdit) navEdit.remove();

                // Refresh the list to reflect any changes made in the editor
                if (type === 'page') refreshPageList();
                else refreshCompList();

                // URL更新
                history.pushState({ spaEditor: false }, '', 'admin.php#' + sectionId);

                // 閉じ先セクションの色とナビ状態を明示的にセット
                const sectionColors = { pages: '#e0f2fe', design: '#fffbeb', media: '#f0fdf4' };
                const sectionNavs = { pages: 'nav-pages', design: 'nav-design', media: 'nav-media' };
                document.body.style.backgroundColor = sectionColors[sectionId] || '#ffffff';
                const targetNav = sectionNavs[sectionId];
                if (targetNav) {
                    document.querySelectorAll('.side-nav a:not(.nav-edit)').forEach(btn => {
                        btn.classList.toggle('active', btn.classList.contains(targetNav));
                    });
                }

                // セクションへスムーズスクロール
                const section = document.getElementById(sectionId);
                if (section) section.scrollIntoView({ behavior: 'smooth' });

                window.isDirty = false;

                // スクロール完了を待ってからナビゲーション状態を解除
                setTimeout(() => {
                    isNavigating = false;
                    document.querySelectorAll('.side-nav a').forEach(a => a.style.transition = '');
                }, 400);
            }, totalDuration);
        } else {
            // エディタがない場合（フォールバック）
            if (navEdit) navEdit.remove();
            history.pushState({ spaEditor: false }, '', 'admin.php#' + sectionId);
            window.isDirty = false;
            isNavigating = false;
            document.querySelectorAll('.side-nav a').forEach(a => a.style.transition = '');
            updateScrollPos();
        }
    }

    function getEditorTypeFromUrl(url) {
        if (url.indexOf('view=pages') !== -1 || url.indexOf('#page-editor') !== -1 || url.indexOf('#pages') !== -1) return 'page';
        if (url.indexOf('view=design') !== -1 || url.indexOf('#design-editor') !== -1 || url.indexOf('#design') !== -1) return 'design';
        return null;
    }

    function spaHandlePendingNavigation() {
        const url = window.pendingTargetUrl;
        if (!url) return;

        // 閉じるボタンからの場合 → SPA close
        if (window.pendingCloseBtn) {
            const type = (url.indexOf('#pages') !== -1) ? 'page' : 'design';
            spaCloseEditor(type);
            return;
        }

        // 編集リンクからの場合 → SPA open
        if (url.indexOf('edit=') !== -1 || url.indexOf('new=1') !== -1) {
            const type = url.indexOf('#page-editor') !== -1 ? 'page' : 'design';
            const urlObj = new URL(url, window.location.origin);
            const editId = urlObj.searchParams.get('edit') || null;
            spaOpenEditor(type, editId);
            return;
        }

        // それ以外 → 通常のナビゲーション
        window.location.href = url;
    }

    // popstate: ブラウザの戻る/進む対応
    window.addEventListener('popstate', function(e) {
        const state = e.state;
        if (state && state.spaEditor === false) {
            // 閉じた状態に戻る
            const pageEditor = document.getElementById('page-editor');
            const designEditor = document.getElementById('design-editor');
            if (pageEditor) { document.getElementById('page-editor-slot').innerHTML = ''; }
            if (designEditor) { document.getElementById('design-editor-slot').innerHTML = ''; }
            const navEdit = document.querySelector('.side-nav .nav-edit');
            if (navEdit) navEdit.remove();
            window.isDirty = false;
            updateScrollPos();
        } else if (state && state.spaEditor === true) {
            // 開いた状態に戻る → フェッチして再表示
            spaOpenEditor(state.type, state.editId);
        }
    });

    window.onload = function() {
        initAiPrompt();
        // 早期スクリプトで注入した transition 抑制を解除する前に正しい状態を設定
        updateScrollPos();
        // scroll/resizeリスナーはonload内で登録（ハッシュスクロール中の誤発火を防ぐ）
        window.addEventListener('scroll', updateScrollPos, {passive: true});
        window.addEventListener('resize', updateScrollPos, {passive: true});
        requestAnimationFrame(() => requestAnimationFrame(() => {
            // 正しいbg・nav状態が設定された後にtransition抑制を解除
            var fix = document.getElementById('init-notransition');
            if (fix) fix.remove();
        }));
        
        // Unsaved changes tracker
        window.isDirty = false;
        document.querySelectorAll('input, textarea, select').forEach(el => {
            el.addEventListener('input', () => { window.isDirty = true; });
            el.addEventListener('change', () => { window.isDirty = true; });
        });
        
        window.addEventListener('beforeunload', function (e) {
            if (window.isDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // ページ内リンクの滑らかなスクロール（CSS全体への指定を外すための代替処理）
        document.querySelectorAll('.side-nav a[href^="#"]').forEach(link => {
            link.addEventListener('click', function(e) {
                if (e.target.closest('.close-badge')) return; // close-badge は body ハンドラーに任せる
                const target = document.querySelector(this.hash);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth' });
                    history.pushState(null, null, this.hash);
                }
            });
        });

        // Global unsaved changes interceptor (Replaces default confirm & covers internal navigations)
        document.body.addEventListener('click', function(e) {
            // --- SPA: 編集リンクのインターセプト ---
            const editLink = e.target.closest('a[href*="edit="][href*="#page-editor"], a[href*="new=1"][href*="#page-editor"], a[href*="edit="][href*="#design-editor"], a[href*="new=1"][href*="#design-editor"]');
            if (editLink && !e.target.closest('.close-badge')) {
                e.preventDefault();
                e.stopPropagation();

                if (window.isDirty) {
                    window.pendingTargetUrl = editLink.href;
                    window.pendingCloseBtn = false;
                    const modal = document.getElementById('unsaved-modal');
                    if (modal) modal.style.display = 'flex';
                    return;
                }

                const href = editLink.getAttribute('href');
                const type = href.indexOf('#page-editor') !== -1 ? 'page' : 'design';
                const urlParams = new URLSearchParams(href.split('?')[1]?.split('#')[0] || '');
                const editId = urlParams.get('edit') || null;
                spaOpenEditor(type, editId);
                return;
            }

            // --- 閉じるボタン（SPA処理） ---
            const closeBtn = e.target.closest('.editor-focus-bg a.btn-gray[href^="admin.php#"]') || e.target.closest('.side-nav .close-badge');

            if (closeBtn) {
                e.preventDefault();
                e.stopPropagation();

                const targetUrl = closeBtn.dataset.url || closeBtn.href || closeBtn.closest('a').href;

                if (window.isDirty) {
                    window.pendingTargetUrl = targetUrl;
                    window.pendingCloseBtn = true;
                    const modal = document.getElementById('unsaved-modal');
                    if (modal) modal.style.display = 'flex';
                } else {
                    // SPA close
                    const type = (targetUrl.indexOf('#pages') !== -1) ? 'page' : 'design';
                    spaCloseEditor(type);
                }
            } else if (window.isDirty) {
                // Internal app navigation that leaves the editor
                const link = e.target.closest('a');
                if (link && link.href && link.href.startsWith(window.location.origin) && link.href !== window.location.href && !link.href.includes('javascript:') && !link.hasAttribute('download')) {
                    // Let native scroll happen for safe sidebar jumps
                    const isSideNav = link.closest('.side-nav') && link.getAttribute('href') && link.getAttribute('href').startsWith('#');
                    
                    if (!isSideNav && link.target !== '_blank') {
                        // This navigation discards the current view (like opening another page). Show custom modal!
                        e.preventDefault();
                        e.stopPropagation();
                        window.pendingTargetUrl = link.href;
                        // It's not the close button, we just want to navigate to the new page after save
                        window.pendingCloseBtn = false; 
                        const modal = document.getElementById('unsaved-modal');
                        if (modal) modal.style.display = 'flex';
                    }
                }
            }
        }, true);
        
        // Modal Handlers
        const unsavedModal = document.getElementById('unsaved-modal');
        if (unsavedModal) {
            document.getElementById('btn-modal-cancel').onclick = () => unsavedModal.style.display = 'none';
            document.getElementById('btn-modal-discard').onclick = () => {
                unsavedModal.style.display = 'none';
                window.isDirty = false;
                spaHandlePendingNavigation();
            };
            document.getElementById('btn-modal-save').onclick = async () => {
                const saveBtn = document.querySelector('.editor-focus-bg form button[name="save_action"], .editor-focus-bg form button[type="submit"]');
                if (saveBtn) {
                    const form = saveBtn.closest('form');
                    const originalBtnText = saveBtn.innerHTML;
                    const originalModalText = document.getElementById('btn-modal-save').innerHTML;
                    
                    const savingHtml = '<span class="material-symbols-outlined icon" style="animation: spin 1s linear infinite;">sync</span> 保存中...';
                    saveBtn.innerHTML = savingHtml;
                    saveBtn.disabled = true;
                    document.getElementById('btn-modal-save').innerHTML = savingHtml;
                    document.getElementById('btn-modal-save').disabled = true;
                    
                    try {
                        const formData = new FormData(form);
                        formData.append(saveBtn.name || 'save_action', saveBtn.value);
                        formData.append('ajax_request', '1');
                        const res = await fetch(window.location.href, { method: 'POST', body: formData });
                        if (res.ok) {
                            window.isDirty = false;
                            unsavedModal.style.display = 'none';
                            spaHandlePendingNavigation();
                        } else {
                            showToast('保存に失敗しました', true);
                            unsavedModal.style.display = 'none';
                        }
                    } catch(e) {
                        showToast('通信エラーが発生しました', true);
                        unsavedModal.style.display = 'none';
                    } finally {
                        saveBtn.innerHTML = originalBtnText;
                        saveBtn.disabled = false;
                        document.getElementById('btn-modal-save').innerHTML = originalModalText;
                        document.getElementById('btn-modal-save').disabled = false;
                    }
                } else {
                    document.getElementById('btn-modal-discard').click();
                }
            };
        }
        
        // Modeless AJAX Savelogic
        document.addEventListener('submit', async function(e) {
            const form = e.target;
            const submitter = e.submitter;
            const actionInput = (submitter && submitter.name === 'save_action') ? submitter : form.querySelector('input[name="save_action"]');
            const action = actionInput ? actionInput.value : '';
            
            const ajaxActions = ['save_page', 'save_comp', 'save_settings', 'save_prompt', 'save_memo', 'ssg_save_settings', 'generate_mcp_key'];
            
            if (ajaxActions.includes(action)) {
                e.preventDefault();
                const originalText = submitter.innerHTML;
                submitter.innerHTML = '<span class="material-symbols-outlined icon" style="animation: spin 1s linear infinite;">sync</span> 保存中...';
                submitter.disabled = true;
                
                try {
                    const formData = new FormData(form);
                    if (submitter && submitter.name) formData.append(submitter.name, submitter.value);
                    if (!formData.has('save_action') && actionInput) formData.append('save_action', actionInput.value);
                    formData.append('ajax_request', '1');
                    
                    const res = await fetch(window.location.href, { method: 'POST', body: formData });
                    if (res.ok) {
                        const json = await res.json().catch(()=>({}));
                        window.isDirty = false;
                        showToast(json.message || '<?= t('msg_update_success') ?? '保存しました' ?>');
                        if (action === 'generate_mcp_key' && json.mcp_api_key) {
                            const keyDisplay = document.getElementById('mcp-key-display');
                            if (keyDisplay) keyDisplay.value = json.mcp_api_key;
                        }

                        // Language change requires full reload to apply server-side translations
                        if (action === 'save_settings' && formData.has('system_lang')) {
                            setTimeout(() => { window.location.reload(); }, 600);
                            return;
                        }

                        // update old_id for new records seamlessly without reload
                        const idInput = form.querySelector('input[name="id"]');
                        const oldIdInput = form.querySelector('input[name="old_id"]');
                        if (oldIdInput && idInput && !oldIdInput.value) {
                            oldIdInput.value = idInput.value;
                        }

                        // Refresh page/comp list in background after save
                        if (action === 'save_page') {
                            refreshPageList();
                            // Inject or update preview button after first save of a new page
                            if (json.preview_url) {
                                const editor = document.getElementById('page-editor');
                                if (editor) {
                                    let previewBtn = editor.querySelector('.preview-btn');
                                    if (!previewBtn) {
                                        const saveBtn = editor.querySelector('button[value="save_page"]');
                                        if (saveBtn) {
                                            previewBtn = document.createElement('a');
                                            previewBtn.target = '_blank';
                                            previewBtn.className = 'btn btn-blue preview-btn';
                                            previewBtn.innerHTML = '<span class="material-symbols-outlined icon">visibility</span> プレビュー';
                                            saveBtn.insertAdjacentElement('afterend', previewBtn);
                                        }
                                    }
                                    if (previewBtn) previewBtn.href = json.preview_url;
                                }
                            }
                        } else if (action === 'save_comp') {
                            refreshCompList();
                        }
                    } else {
                        showToast('保存に失敗しました', true);
                    }
                } catch(err) {
                    showToast('通信エラー', true);
                } finally {
                    submitter.innerHTML = originalText;
                    submitter.disabled = false;
                }
            }
        });
    };
    
    function showToast(msg, isErr=false) {
        let t = document.getElementById('ajax-toast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'ajax-toast';
            t.style.cssText = 'position:fixed; bottom:30px; right:30px; background:rgba(0,0,0,0.8); color:white; padding:12px 24px; border-radius:8px; z-index:10000; transition:opacity 0.3s; opacity:0; pointer-events:none;';
            document.body.appendChild(t);
        }
        t.style.background = isErr ? 'rgba(220,53,69,0.9)' : 'rgba(0,0,0,0.8)';
        t.textContent = msg;
        t.style.opacity = '1';
        setTimeout(() => t.style.opacity = '0', 3000);
    }
    
    if (!document.getElementById('spin-keyframes')) {
        const style = document.createElement('style');
        style.id = 'spin-keyframes';
        style.textContent = `@keyframes spin { 100% { transform: rotate(360deg); } }`;
        document.head.appendChild(style);
    }
    </script>
</div>

<footer>
    &copy; 2026 🍊mikanBox v<?= MIKANBOX_VERSION ?> by <a href="http://yoshihiko.com" target="_blank">yoshihiko.com</a>
</footer>

<style>
.unsaved-modal-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5); z-index: 10000;
    display: flex; align-items: center; justify-content: center;
    animation: fadeIn 0.2s ease forwards;
}
.unsaved-modal-content {
    background: #fff; padding: 30px; border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2); max-width: 500px; text-align: center;
    font-family: system-ui, sans-serif;
}
.unsaved-modal-actions {
    display: flex; gap: 10px; justify-content: center; margin-top: 20px;
}
.unsaved-modal-actions .btn {
    white-space: nowrap;
}
.unsaved-modal-content h3 { margin-top: 0; font-size: 1.2rem; color: #333; }
.unsaved-modal-content p { color: #666; font-size: 0.95rem; margin-bottom: 20px; line-height: 1.5; }
@keyframes fadeIn { 0% { opacity: 0; } 100% { opacity: 1; } }
</style>

<div id="unsaved-modal" class="unsaved-modal-overlay" style="display: none;">
    <div class="unsaved-modal-content">
        <h3>未保存の変更があります</h3>
        <p>編集中のデータはまだ保存されていません。<br>保存してから閉じますか？</p>
        <div class="unsaved-modal-actions">
            <button id="btn-modal-save" class="btn btn-blue"><?= getIcon('save') ?> 保存して閉じる</button>
            <button id="btn-modal-discard" class="btn btn-red"><?= getIcon('delete') ?> 破棄して閉じる</button>
            <button id="btn-modal-cancel" class="btn btn-gray">キャンセル</button>
        </div>
    </div>
</div>

</body>
</html>
