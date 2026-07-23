<?php
ob_start();
// ==========================================
// mikanBox Admin Panel (admin.php)
// ==========================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/functions.php';
require_once __DIR__ . '/lib/renderer.php';
define('MIKANBOX', true);

// CSRF token generation (initial or on session timeout)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Authentication ---
$isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Initial setup / Login / Common settings
// If settings.json does not exist, initialize with an empty array
$settings = loadSettings();
// Pass reference as a global variable (Fix #8)
$GLOBALS['mikanbox_settings'] = &$settings;

$db = getDb();
// Check if initial setup is needed by checking if users table has any rows
$stmtUsersCount = 0;
try {
    $stmtUsersCount = $db->querySingle("SELECT COUNT(*) FROM users") ?: 0;
} catch (Exception $e) {}
$isInitialSetup = ($stmtUsersCount === 0);
$isDemoMode = !empty($settings['demo_mode']);
$loginError = ''; // Initialize loginError

// Logout process
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_username']);
    unset($_SESSION['admin_display_name']);
    // In demo mode, redirect to login form after logout
    $redirect = $isDemoMode ? basename($_SERVER['PHP_SELF']) . '?login=1' : basename($_SERVER['PHP_SELF']);
    header('Location: ' . $redirect);
    exit;
}

// Forgot Password action reset
// Only reset on a fresh GET (clicking the "forgot password?" link). The
// step2/step3 forms below have no explicit action attribute, so browsers
// re-submit their POST back to this same "?action=forgot_password" URL —
// resetting on POST here would wipe reset_step/reset_username before the
// step handler below ever runs, making the flow impossible to complete.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['action']) && $_GET['action'] === 'forgot_password') {
    $_SESSION['reset_step'] = 1;
    unset($_SESSION['reset_username']);
    unset($_SESSION['reset_verified']);
}

$resetError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_action'])) {
    if ($_POST['reset_action'] === 'step1') {
        $user = trim($_POST['username'] ?? '');
        $stmt = $db->prepare("SELECT security_question FROM users WHERE username = :username");
        $stmt->bindValue(':username', $user, SQLITE3_TEXT);
        $res = $stmt->execute();
        $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;
        
        if (!$row) {
            $resetError = t('reset_err_user_not_found');
        } elseif (empty($row['security_question'])) {
            $resetError = t('reset_err_no_question');
        } else {
            $_SESSION['reset_username'] = $user;
            $_SESSION['reset_step'] = 2;
        }
    } elseif ($_POST['reset_action'] === 'step2') {
        $user = $_SESSION['reset_username'] ?? '';
        $answer = trim($_POST['security_answer'] ?? '');

        $rateLimitId = getClientIp() . '|reset';
        $lockedRemain = checkLoginRateLimit($rateLimitId);
        if ($lockedRemain > 0) {
            $resetError = t('err_rate_limited', (int)ceil($lockedRemain / 60));
        } else {
            $stmt = $db->prepare("SELECT security_answer_hash FROM users WHERE username = :username");
            $stmt->bindValue(':username', $user, SQLITE3_TEXT);
            $res = $stmt->execute();
            $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;

            if (!$row || empty($row['security_answer_hash']) || !password_verify(normalizeSecurityAnswer($answer), $row['security_answer_hash'])) {
                recordLoginFailure($rateLimitId);
                $resetError = t('reset_err_wrong_answer');
            } else {
                clearLoginAttempts($rateLimitId);
                $_SESSION['reset_verified'] = true;
                $_SESSION['reset_step'] = 3;
            }
        }
    } elseif ($_POST['reset_action'] === 'step3') {
        $user = $_SESSION['reset_username'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $isVerified = $_SESSION['reset_verified'] ?? false;
        
        if (!$isVerified || empty($user)) {
            $resetError = t('err_session_error');
            $_SESSION['reset_step'] = 1;
        } elseif (strlen($newPass) < 4) {
            $resetError = t('err_password_chars');
        } else {
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmtUpdate = $db->prepare("UPDATE users SET password_hash = :hash WHERE username = :username");
            $stmtUpdate->bindValue(':hash', $newHash, SQLITE3_TEXT);
            $stmtUpdate->bindValue(':username', $user, SQLITE3_TEXT);
            if ($stmtUpdate->execute()) {
                unset($_SESSION['reset_username']);
                unset($_SESSION['reset_verified']);
                unset($_SESSION['reset_step']);
                $_SESSION['admin_message'] = t('reset_success_msg');
                
                $redirect = isset($_GET['login']) ? basename($_SERVER['PHP_SELF']) . '?login=1' : basename($_SERVER['PHP_SELF']);
                header('Location: ' . $redirect);
                exit;
            } else {
                $resetError = t('err_save_failed');
            }
        }
    }
}

// --- Login / Initial Setup Process ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_action'])) {
    if ($_POST['login_action'] === 'set_initial_password' && $isInitialSetup) {
        // Initial user & password setup
        $user = trim($_POST['username'] ?? '');
        $disp = trim($_POST['display_name'] ?? '');
        $pass = $_POST['new_password'] ?? '';
        $sq = trim($_POST['security_question'] ?? '');
        $sa = trim($_POST['security_answer'] ?? '');
        if (empty($user) || !preg_match('/^[a-zA-Z0-9_\-]+$/', $user)) {
            $loginError = t('err_username_chars');
        } elseif (empty($disp)) {
            $loginError = t('err_display_name_required');
        } elseif (strlen($pass) < 4) {
            $loginError = t('err_password_chars');
        } elseif (empty($sq) || empty($sa)) {
            $loginError = t('err_security_qa_required');
        } else {
            // Populate defaults on first-time setup
            if (empty($settings)) {
                $settings = [
                    'site_name'   => '🍊mikanBox',
                    'description' => '',
                    'keywords'    => '',
                    'memo'        => 'Welcome to 🍊mikanBox!',
                    'system_lang' => '',
                    'ssg_structure' => 'directory',
                    'ssg_server_structure' => 'directory',
                    'ssg_export_structure' => 'file'
                ];
                saveSettings($settings);
            }
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $saHash = password_hash(normalizeSecurityAnswer($sa), PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT OR REPLACE INTO users (username, password_hash, display_name, security_question, security_answer_hash) VALUES (:username, :password_hash, :display_name, :security_question, :security_answer_hash)");
            $stmt->bindValue(':username', $user, SQLITE3_TEXT);
            $stmt->bindValue(':password_hash', $hash, SQLITE3_TEXT);
            $stmt->bindValue(':display_name', $disp, SQLITE3_TEXT);
            $stmt->bindValue(':security_question', $sq, SQLITE3_TEXT);
            $stmt->bindValue(':security_answer_hash', $saHash, SQLITE3_TEXT);
            if ($stmt->execute()) {
                // 初回セットアップ時に .htaccess が未生成であれば自動作成
                $siteRoot = dirname(CORE_DIR);
                $htaccessPath = $siteRoot . '/.htaccess';
                if (!file_exists($htaccessPath)) {
                    $htaccessContent = "DirectoryIndex index.php

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]
    RewriteRule ^ index.php [L,QSA]
</IfModule>
";
                    @file_put_contents($htaccessPath, $htaccessContent);
                }
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $user;
                $_SESSION['admin_display_name'] = $disp;
                header('Location: ' . basename(__FILE__));
                exit;
            } else {
                $loginError = t('err_save_failed');
            }
        }
    } elseif ($_POST['login_action'] === 'login' && !$isInitialSetup) {
        // Normal login
        $user = trim($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? '';

        $rateLimitId = getClientIp() . '|login';
        $lockedRemain = checkLoginRateLimit($rateLimitId);
        if ($lockedRemain > 0) {
            $loginError = t('err_rate_limited', (int)ceil($lockedRemain / 60));
        } else {
            $stmt = $db->prepare("SELECT password_hash, display_name FROM users WHERE username = :username");
            $stmt->bindValue(':username', $user, SQLITE3_TEXT);
            $res = $stmt->execute();
            $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;

            if ($row && password_verify($pass, $row['password_hash'])) {
                clearLoginAttempts($rateLimitId);
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $user;
                $_SESSION['admin_display_name'] = $row['display_name'];
                header('Location: ' . basename(__FILE__));
                exit;
            } else {
                recordLoginFailure($rateLimitId);
                $loginError = t('err_username_or_password_invalid');
            }
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
        <?php
        if (isset($_SESSION['admin_message'])) {
            echo "<div class='error' style='background-color:#e2f0d9; border-color:#a9d08e; color:#375623; margin-bottom:15px; font-size:0.85rem; padding:10px; border-radius:4px; border:1px solid;'>{$_SESSION['admin_message']}</div>";
            unset($_SESSION['admin_message']);
        }
        ?>

        <?php if (isset($_GET['action']) && $_GET['action'] === 'forgot_password'): ?>
            <?php
            $resetStep = $_SESSION['reset_step'] ?? 1;
            if ($resetStep == 2 && empty($_SESSION['reset_username'])) {
                $resetStep = 1;
            }
            if ($resetStep == 3 && (empty($_SESSION['reset_username']) || empty($_SESSION['reset_verified']))) {
                $resetStep = 1;
            }
            ?>
            <h3 style="margin-top: 0; font-size: 1.1rem; color: var(--text);"><?= t('reset_title') ?> (Step <?= $resetStep ?>/3)</h3>
            <?php if (!empty($resetError)) echo "<div class='error'>{$resetError}</div>"; ?>
            
            <?php if ($resetStep == 1): ?>
                <p style="font-size: 0.82rem; color: var(--text-sub); margin-bottom: 15px; line-height: 1.4;"><?= t('reset_step1_intro') ?></p>
                <form method="post" style="display: flex; flex-direction: column; gap: 10px;">
                    <input type="hidden" name="reset_action" value="step1">
                    <input type="text" name="username" placeholder="<?= t('label_username') ?>" required autofocus style="padding: 10px; border-radius: 4px; border: 1px solid #d1d5db; font-size: 0.9rem;">
                    <button type="submit" class="btn btn-blue" style="width: 100%; padding: 10px; font-size: 0.95rem; margin-top: 10px;"><?= t('btn_next') ?></button>
                </form>
            <?php elseif ($resetStep == 2): ?>
                <?php
                $user = $_SESSION['reset_username'] ?? '';
                $question = '';
                try {
                    $stmtQ = $db->prepare("SELECT security_question FROM users WHERE username = :username");
                    $stmtQ->bindValue(':username', $user, SQLITE3_TEXT);
                    $resQ = $stmtQ->execute();
                    $rowQ = $resQ ? $resQ->fetchArray(SQLITE3_ASSOC) : null;
                    if ($rowQ) {
                        $question = $rowQ['security_question'];
                    }
                } catch (Exception $e) {}
                ?>
                <p style="font-size: 0.82rem; color: var(--text-sub); margin-bottom: 15px; line-height: 1.4;"><?= t('reset_step2_intro') ?></p>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px; font-weight: 600; margin-bottom: 15px; text-align: center; font-size: 0.9rem; color: var(--text);">
                    <?= htmlspecialchars($question) ?>
                </div>
                <form method="post" style="display: flex; flex-direction: column; gap: 10px;">
                    <input type="hidden" name="reset_action" value="step2">
                    <input type="text" name="security_answer" placeholder="<?= t('security_answer_placeholder') ?>" required autofocus style="padding: 10px; border-radius: 4px; border: 1px solid #d1d5db; font-size: 0.9rem;">
                    <button type="submit" class="btn btn-blue" style="width: 100%; padding: 10px; font-size: 0.95rem; margin-top: 10px;"><?= t('btn_next') ?></button>
                </form>
            <?php elseif ($resetStep == 3): ?>
                <p style="font-size: 0.82rem; color: var(--text-sub); margin-bottom: 15px; line-height: 1.4;"><?= t('reset_step3_intro') ?></p>
                <form method="post" style="display: flex; flex-direction: column; gap: 10px;">
                    <input type="hidden" name="reset_action" value="step3">
                    <input type="password" name="new_password" placeholder="<?= t('admin_new_password') ?>" required autofocus style="padding: 10px; border-radius: 4px; border: 1px solid #d1d5db; font-size: 0.9rem;">
                    <button type="submit" class="btn btn-blue" style="width: 100%; padding: 10px; font-size: 0.95rem; margin-top: 10px;"><?= t('btn_reset_password') ?></button>
                </form>
            <?php endif; ?>
            <p style="text-align: center; margin-top: 15px; font-size: 0.85rem;">
                <a href="<?= basename($_SERVER['PHP_SELF']) ?><?= isset($_GET['login']) ? '?login=1' : '' ?>" style="color: var(--text-sub); text-decoration: none; display: inline-flex; align-items: center; gap: 4px; justify-content: center; width: 100%;"><?= getIcon('arrow_back') ?> <?= t('btn_back_to_login') ?></a>
            </p>
        <?php elseif ($isInitialSetup): ?>
            <p><strong><?= t('hint_initial_setup') ?></strong><br>管理者のアカウントを作成してください。</p>
            <?php if(!empty($loginError)) echo "<div class='error'>{$loginError}</div>"; ?>
            <form method="post" style="display: flex; flex-direction: column; gap: 10px;">
                <input type="hidden" name="login_action" value="set_initial_password">
                <input type="text" name="username" placeholder="ユーザーID (半角英数字)" required autofocus style="padding: 10px; border-radius: 4px; border: 1px solid #d1d5db; font-size: 0.9rem;">
                <input type="text" name="display_name" placeholder="表示名 (例: 山田太郎)" required style="padding: 10px; border-radius: 4px; border: 1px solid #d1d5db; font-size: 0.9rem;">
                <input type="password" name="new_password" placeholder="<?= t('admin_new_password') ?>" required style="padding: 10px; border-radius: 4px; border: 1px solid #d1d5db; font-size: 0.9rem;">
                <input type="text" name="security_question" placeholder="<?= t('security_question_label') ?> (<?= t('security_question_placeholder') ?>)" required style="padding: 10px; border-radius: 4px; border: 1px solid #d1d5db; font-size: 0.9rem;">
                <input type="text" name="security_answer" placeholder="<?= t('security_answer_label') ?>" required style="padding: 10px; border-radius: 4px; border: 1px solid #d1d5db; font-size: 0.9rem;">
                <button type="submit" class="btn btn-blue" style="width: 100%; padding: 10px; font-size: 0.95rem; margin-top: 10px;"><?= t('btn_set_password') ?></button>
            </form>
        <?php else: ?>
            <?php if ($isDemoMode): ?>
            <p><?= t('hint_demo_login') ?></p>
            <?php endif; ?>
            <?php if(!empty($loginError)) echo "<div class='error'>{$loginError}</div>"; ?>
            <form method="post" style="display: flex; flex-direction: column; gap: 10px;">
                <input type="hidden" name="login_action" value="login">
                <input type="text" name="username" placeholder="<?= t('label_username') ?>" required autofocus style="padding: 10px; border-radius: 4px; border: 1px solid #d1d5db; font-size: 0.9rem;">
                <input type="password" name="password" placeholder="<?= t('admin_password') ?>" required style="padding: 10px; border-radius: 4px; border: 1px solid #d1d5db; font-size: 0.9rem;">
                <button type="submit" class="btn btn-blue" style="width: 100%; padding: 10px; font-size: 0.95rem; margin-top: 10px;"><?= t('btn_login') ?></button>
            </form>
            <p style="text-align: right; font-size: 0.8rem; margin-top: 8px; margin-bottom: 0;">
                <a href="?action=forgot_password<?= isset($_GET['login']) ? '&login=1' : '' ?>" style="color: var(--accent); text-decoration: none;"><?= t('admin_forgot_password_link') ?></a>
            </p>
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
    $success = true;
    $resolvedFilename = null;

    // Demo mode: block write operations if not logged in with password
    if ($isDemoMode && !$isLoggedIn) {
        $message = t('msg_demo_mode');
        goto skip_post_actions;
    }

    if (in_array($_POST['save_action'], ['system_update', 'system_restore'], true)) {
        require_once __DIR__ . '/lib/updater.php';
        try {
            if ($_POST['save_action'] === 'system_update') {
                $result = mikanBoxInstallUpdate(
                    'yoshihik0/mikanBox',
                    MIKANBOX_VERSION,
                    CORE_DIR,
                    DATA_DIR,
                    $_POST['target_version'] ?? null,
                    $_POST['update_ref'] ?? 'main'
                );
                $message = !empty($result['success'])
                    ? t('msg_system_update_success', $result['version'])
                    : t('update_error_' . ($result['code'] ?? 'internal'));
            } else {
                $result = mikanBoxRestorePreviousVersion(CORE_DIR, DATA_DIR);
                $message = !empty($result['success'])
                    ? t('msg_system_restore_success', $result['version'])
                    : t('update_error_' . ($result['code'] ?? 'internal'));
            }
        } catch (Throwable $e) {
            $message = t('update_error_internal');
        }
        unset($_SESSION['mikanbox_latest_ver_' . md5('yoshihik0/mikanBox')]);
        $_SESSION['admin_message'] = $message;
        header('Location: admin.php?view=settings');
        exit;
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
            $message = t('err_slug_reserved', $id);
        } // Duplicate slug check: warn if creating new page with existing ID
        elseif (empty($oldId) && loadData(POSTS_DIR, $id) !== null) {
            $message = t('err_slug_exists', $id);
        } else {

        $updatedAt = $_POST['updated_at'] ?? date('Y-m-d H:i:s');

        // Auto-set the update date to "now" when a page transitions from non-public
        // (draft/db) to public, so it reflects the actual publish moment rather than
        // whatever was left in the (manually editable) date field while still a draft.
        // Once public, further edits keep whatever date is already in that field
        // (nothing here bumps it automatically), so minor fixes don't move the date.
        $existingPageData = loadData(POSTS_DIR, $oldId ?: $id);
        $wasPublic = $existingPageData && in_array($existingPageData['status'] ?? 'draft', ['public_dynamic', 'public_static'], true);
        $isPublicNow = in_array($status, ['public_dynamic', 'public_static'], true);
        if (!$wasPublic && $isPublicNow) {
            $updatedAt = date('Y-m-d H:i:s');
        }

        $data = [
            'title' => $_POST['title'] ?? '',
            'category' => trim($_POST['category'] ?? ''),
            'status' => $status,
            'description' => $_POST['description'] ?? '',
            'memo' => $_POST['memo'] ?? '',
            'keywords' => $_POST['keywords'] ?? '',
            'ogp_image' => $_POST['ogp_image'] ?? '',
            'content_md' => $_POST['content_md'] ?? '',
            'css' => $_POST['css'] ?? '',
            'is_html' => isset($_POST['is_html']) ? true : false,
            'wrapper_comp' => $_POST['wrapper_comp'] ?: '_layout',
            'sort_order' => (int)($_POST['sort_order'] ?? 0),
            'updated_at' => $updatedAt
        ];
        
        // Save revision of current page state before saving new data
        $editorUsername = $_SESSION['admin_username'] ?? 'admin';
        createRevision($id, $editorUsername);

        if (saveData(POSTS_DIR, $id, $data)) {
            // Delete old file if URL slug (ID) has changed
            $oldId = $_POST['old_id'] ?? null;
            if ($oldId && $oldId !== $id) {
                deleteData(POSTS_DIR, $oldId);
                
                // Update post revisions to map to the new slug
                try {
                    $db = getDb();
                    $stmtUpdate = $db->prepare("UPDATE post_revisions SET post_id = :new_id WHERE post_id = :old_id");
                    $stmtUpdate->bindValue(':new_id', $id, SQLITE3_TEXT);
                    $stmtUpdate->bindValue(':old_id', $oldId, SQLITE3_TEXT);
                    $stmtUpdate->execute();
                } catch (Exception $e) {}

                // Also delete old static files
                require_once __DIR__ . '/lib/ssg.php';
                $ssg = new MikanBoxSSG($renderer, $activeSsgAbsPath);
                $ssg->deletePage($oldId);
            }

            // --- Automatic SSG Build/Delete Check ---
            require_once __DIR__ . '/lib/ssg.php';
            $ssgOpts = [
                'structure' => $settings['ssg_structure'] ?? 'directory',
                'copy_media' => ($settings['ssg_mode'] ?? 'server') === 'export',
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
        // index has no special protection: a missing page (including index) already falls
        // back to the 404 page in MikanBoxRenderer::render(), so there's nothing unsafe
        // about deleting it like any other page.
        if (deleteData(POSTS_DIR, $id)) {
            deleteRevisionsOfPost($id); // Clean up revisions
            $_SESSION['admin_message'] = t('msg_page_deleted', $id);
            header("Location: admin.php?view=pages");
            exit;
        } else {
            $message = t('err_page_delete');
        }
    }
    elseif ($_POST['save_action'] === 'save_comp') {
        $id = $_POST['id'];
        $compType = $_POST['comp_type'] ?? 'part';
        if ($compType === 'ai_doc') {
            if (substr(strtolower($id), -3) !== '.md') {
                $id .= '.md';
            }
        }
        if(empty($id)) $id = 'comp_' . time();
        $oldId = $_POST['old_id'] ?? null;

        // Duplicate slug check: warn if creating new component with existing ID
        if (empty($oldId) && loadData(COMPONENTS_DIR, $id) !== null) {
            $message = t('err_slug_exists', $id);
        } else {
        $data = [
            'html' => $_POST['html'],
            'css' => $_POST['css'] ?? '',
            'memo' => $_POST['memo'] ?? '',
            'is_global' => !isset($_POST['use_scope']),
            'is_wrapper' => ($compType === 'wrapper'),
            'is_ai_doc' => ($compType === 'ai_doc'),
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
            header("Location: admin.php?view=components#design");
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
                $originalName = basename($_FILES['image']['name']);
                
                // Security: Validate Extension
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'mp3', 'm4a', 'mp4'];
                if (!in_array($ext, $allowedExts)) {
                    $message = t('err_upload_failed') . " (Invalid file extension)";
                    $success = false;
                } else {
                    $category = $_POST['cat'] ?? $_GET['cat'] ?? '';
                    $resolvedName = resolveMediaSaveName($originalName, $category);
                    $targetPath = MEDIA_DIR . '/' . $resolvedName;
                    
                    if (!is_dir(MEDIA_DIR)) mkdir(MEDIA_DIR, 0777, true);
                    if (move_uploaded_file($tmpPath, $targetPath)) {
                        if ($ext === 'svg') {
                            file_put_contents($targetPath, sanitizeSvgContent(file_get_contents($targetPath)));
                        }
                        $message = t('msg_media_uploaded', $resolvedName);
                        $success = true;
                        $resolvedFilename = $resolvedName;
                        $editId = $resolvedName;
                    } else {
                        $message = t('err_upload_failed');
                        $success = false;
                    }
                }
            } else {
                $success = false;
                switch($err) {
                    case UPLOAD_ERR_INI_SIZE: $message = t('err_file_size'); break;
                    case UPLOAD_ERR_NO_FILE: $message = t('err_no_file'); break;
                    default: $message = t('err_upload_failed') . " ($err)"; break;
                }
            }
        } else {
            $success = false;
            $message = t('err_no_file');
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
    elseif ($_POST['save_action'] === 'rename_media') {
        $oldName = basename($_POST['old_filename']);
        $newName = basename($_POST['new_filename']);
        $newName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $newName);
        
        $oldPath = MEDIA_DIR . '/' . $oldName;
        $newPath = MEDIA_DIR . '/' . $newName;
        
        $confirmed = isset($_POST['confirmed']) ? $_POST['confirmed'] : null;
        
        if (empty($newName)) {
            $message = t('err_filename_empty');
            $success = false;
        } elseif (!file_exists($oldPath)) {
            $message = t('err_original_file_not_found');
            $success = false;
        } elseif (file_exists($newPath)) {
            $message = sprintf(t('err_rename_target_exists'), $newName);
            $success = false;
        } else {
            // Find references in posts and components
            $pattern = '%' . $oldName . '%';
            
            // Check posts
            $affectedPosts = [];
            try {
                $stmtPosts = $db->prepare("SELECT id, data FROM posts WHERE data LIKE :pattern");
                $stmtPosts->bindValue(':pattern', $pattern, SQLITE3_TEXT);
                $resPosts = $stmtPosts->execute();
                while ($row = $resPosts ? $resPosts->fetchArray(SQLITE3_ASSOC) : false) {
                    $affectedPosts[] = $row;
                }
            } catch (Exception $e) {}
            
            // Check components
            $affectedComps = [];
            try {
                $stmtComps = $db->prepare("SELECT id, data FROM components WHERE data LIKE :pattern");
                $stmtComps->bindValue(':pattern', $pattern, SQLITE3_TEXT);
                $resComps = $stmtComps->execute();
                while ($row = $resComps ? $resComps->fetchArray(SQLITE3_ASSOC) : false) {
                    $affectedComps[] = $row;
                }
            } catch (Exception $e) {}
            
            $totalCount = count($affectedPosts) + count($affectedComps);
            
            if ($totalCount > 0 && is_null($confirmed)) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'require_confirm' => true,
                    'message' => sprintf(t('media_rename_in_use_confirm'), $totalCount)
                ]);
                exit;
            }
            
            if (rename($oldPath, $newPath)) {
                $message = sprintf(t('msg_media_renamed'), $newName);
                $success = true;
                
                if ($confirmed === '1' && $totalCount > 0) {
                    $updatedCount = 0;
                    foreach ($affectedPosts as $post) {
                        $newData = str_replace($oldName, $newName, $post['data']);
                        $stmtUp = $db->prepare("UPDATE posts SET data = :data WHERE id = :id");
                        $stmtUp->bindValue(':data', $newData, SQLITE3_TEXT);
                        $stmtUp->bindValue(':id', $post['id'], SQLITE3_TEXT);
                        if ($stmtUp->execute()) {
                            $updatedCount++;
                        }
                    }
                    foreach ($affectedComps as $comp) {
                        $newData = str_replace($oldName, $newName, $comp['data']);
                        $stmtUp = $db->prepare("UPDATE components SET data = :data WHERE id = :id");
                        $stmtUp->bindValue(':data', $newData, SQLITE3_TEXT);
                        $stmtUp->bindValue(':id', $comp['id'], SQLITE3_TEXT);
                        if ($stmtUp->execute()) {
                            $updatedCount++;
                        }
                    }
                    $message .= sprintf(t('msg_media_links_updated'), $updatedCount);
                }
            } else {
                $message = t('err_rename_failed_msg');
                $success = false;
            }
        }
    }
    elseif ($_POST['save_action'] === 'add_category') {
        $newCat = trim($_POST['category'] ?? '');
        $newCat = preg_replace('/[^a-zA-Z0-9_\-]/', '', $newCat);
        if (empty($newCat)) {
            $message = t('err_invalid_category');
            $success = false;
        } else {
            $candidates = array_filter(array_map('trim', explode(',', $settings['category_candidates'] ?? '')));
            if (!in_array($newCat, $candidates)) {
                $candidates[] = $newCat;
                $settings['category_candidates'] = implode(', ', $candidates);
                $saved = saveSettings($settings);
                if ($saved) {
                    $message = sprintf(t('msg_category_added'), $newCat);
                    $success = true;
                } else {
                    $message = t('err_category_add_failed_msg');
                    $success = false;
                }
            } else {
                $message = sprintf(t('err_category_exists'), $newCat);
                $success = true;
            }
        }
    }
    elseif ($_POST['save_action'] === 'delete_category') {
        $delCat = trim($_POST['category'] ?? '');
        $confirmed = isset($_POST['confirmed']) && $_POST['confirmed'] === '1';

        if (empty($delCat)) {
            $message = t('err_category_not_specified');
            $success = false;
        } else {
            $db = getDb();
            
            // Count current usage of the category in posts
            $stmt = $db->prepare("SELECT id, category FROM posts");
            $res = $stmt->execute();
            $usageCount = 0;
            if ($res) {
                while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                    $cats = array_filter(array_map('trim', explode(',', $row['category'] ?? '')));
                    if (in_array($delCat, $cats)) {
                        $usageCount++;
                    }
                }
            }

            // If category is in use and deletion is not confirmed yet, ask client for confirmation
            if ($usageCount > 0 && !$confirmed) {
                if (isset($_POST['ajax_request'])) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'require_confirm' => true,
                        'usage_count' => $usageCount,
                        'message' => sprintf(t('category_delete_in_use_confirm'), $usageCount)
                    ]);
                    exit;
                } else {
                    $message = sprintf(t('err_category_in_use'), $usageCount);
                    $success = false;
                }
            } else {
                // 1. Remove from settings category_candidates
                $candidates = array_filter(array_map('trim', explode(',', $settings['category_candidates'] ?? '')));
                $newCandidates = array_diff($candidates, [$delCat]);
                $settings['category_candidates'] = implode(', ', $newCandidates);
                $savedSettings = saveSettings($settings);
                
                // 2. Remove from all posts in the SQLite database
                $stmt = $db->prepare("SELECT id, category, data FROM posts");
                $res = $stmt->execute();
                $updatedPages = 0;
                if ($res) {
                    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
                        $cats = array_filter(array_map('trim', explode(',', $row['category'] ?? '')));
                        if (in_array($delCat, $cats)) {
                            $newCats = array_diff($cats, [$delCat]);
                            $newCatStr = implode(', ', $newCats);
                            
                            $postData = json_decode($row['data'], true);
                            if (is_array($postData)) {
                                $postData['category'] = $newCatStr;
                                $postData['updated_at'] = date('Y-m-d H:i:s');
                                if (saveData(POSTS_DIR, $row['id'], $postData)) {
                                    $updatedPages++;
                                }
                            }
                        }
                    }
                }
                
                if ($savedSettings) {
                    $message = sprintf(t('msg_category_deleted'), $delCat) . ($updatedPages > 0 ? sprintf(t('msg_category_removed_from_pages'), $updatedPages) : "");
                    $success = true;
                } else {
                    $message = t('err_category_delete_failed_msg');
                    $success = false;
                }
            }
        }
    }
    elseif ($_POST['save_action'] === 'save_settings' || $_POST['save_action'] === 'save_memo') {
        // Shared logic for saving settings (Fix #8)
        if ($_POST['save_action'] === 'save_settings') {
            if (isset($_POST['site_name'])) $settings['site_name'] = $_POST['site_name'];
            if (isset($_POST['system_lang'])) $settings['system_lang'] = $_POST['system_lang'];
            if (isset($_POST['ssg_root_url'])) $settings['ssg_root_url'] = $_POST['ssg_root_url'];
            if (isset($_POST['description'])) $settings['description'] = $_POST['description'];
            if (isset($_POST['keywords'])) $settings['keywords'] = $_POST['keywords'];
            if (isset($_POST['ogp_image'])) $settings['ogp_image'] = $_POST['ogp_image'];
            if (isset($_POST['pages_per_page'])) $settings['pages_per_page'] = (int)$_POST['pages_per_page'];
            if (isset($_POST['media_per_page'])) $settings['media_per_page'] = (int)$_POST['media_per_page'];
            if (isset($_POST['category_candidates'])) $settings['category_candidates'] = $_POST['category_candidates'];
        } elseif ($_POST['save_action'] === 'save_memo') {
            $settings['memo'] = $_POST['memo'] ?? '';
        }
        
        if (saveSettings($settings)) {
            $message = t('msg_update_success');
        } else {
            $message = t('err_save_failed');
        }
    }
    elseif ($_POST['save_action'] === 'ssg_save_settings') {
        $settings['ssg_dir'] = $_POST['ssg_dir'] ?? '';
        $settings['ssg_mode'] = in_array($_POST['ssg_mode'] ?? '', ['server', 'export'], true) ? $_POST['ssg_mode'] : 'server';
        $settings['ssg_server_structure'] = in_array($_POST['ssg_server_structure'] ?? '', ['directory', 'file'], true) ? $_POST['ssg_server_structure'] : 'directory';
        $settings['ssg_export_structure'] = in_array($_POST['ssg_export_structure'] ?? '', ['directory', 'file'], true) ? $_POST['ssg_export_structure'] : 'file';
        $settings['ssg_structure'] = $settings['ssg_mode'] === 'export' ? $settings['ssg_export_structure'] : $settings['ssg_server_structure'];
        $settings['ssg_link_mode'] = in_array($_POST['ssg_link_mode'] ?? '', ['relative', 'absolute'], true) ? $_POST['ssg_link_mode'] : 'relative';
        if (isset($_POST['ssg_root_url'])) $settings['ssg_root_url'] = trim($_POST['ssg_root_url']);
        if (saveSettings($settings)) {
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

        $ssgMode = in_array($_POST['ssg_mode'] ?? ($settings['ssg_mode'] ?? 'server'), ['server', 'export'], true)
            ? ($_POST['ssg_mode'] ?? ($settings['ssg_mode'] ?? 'server'))
            : 'server';
        $ssgLinkMode = in_array($_POST['ssg_link_mode'] ?? ($settings['ssg_link_mode'] ?? 'relative'), ['relative', 'absolute'], true)
            ? ($_POST['ssg_link_mode'] ?? ($settings['ssg_link_mode'] ?? 'relative'))
            : 'relative';
        if ($ssgMode === 'server') $ssgLinkMode = 'absolute';
        if ($ssgMode === 'export' && trim($activeSsgDir) === '') {
            $activeSsgDir = 'export';
            $activeSsgAbsPath = $siteRoot . '/export';
            $activeSsgRelPath = '../export/';
        }

        $ssgServerStructure = in_array($_POST['ssg_server_structure'] ?? ($settings['ssg_server_structure'] ?? 'directory'), ['directory', 'file'], true)
            ? ($_POST['ssg_server_structure'] ?? ($settings['ssg_server_structure'] ?? 'directory'))
            : 'directory';
        $ssgExportStructure = in_array($_POST['ssg_export_structure'] ?? ($settings['ssg_export_structure'] ?? 'file'), ['directory', 'file'], true)
            ? ($_POST['ssg_export_structure'] ?? ($settings['ssg_export_structure'] ?? 'file'))
            : 'file';
        $ssgStructure = in_array($_POST['ssg_structure'] ?? '', ['directory', 'file'], true)
            ? $_POST['ssg_structure']
            : ($ssgMode === 'export' ? $ssgExportStructure : $ssgServerStructure);
        if ($ssgMode === 'export') {
            $ssgExportStructure = $ssgStructure;
        } else {
            $ssgServerStructure = $ssgStructure;
        }

        $settings['ssg_dir'] = $activeSsgDir;
        $settings['ssg_structure'] = $ssgStructure;
        $settings['ssg_server_structure'] = $ssgServerStructure;
        $settings['ssg_export_structure'] = $ssgExportStructure;
        $settings['ssg_mode'] = $ssgMode;
        $settings['ssg_link_mode'] = $ssgLinkMode;
        if (isset($_POST['ssg_root_url'])) $settings['ssg_root_url'] = trim($_POST['ssg_root_url']);
        $renderer = new MikanBoxRenderer($settings);

        $ssgOpts = [
            'structure' => $settings['ssg_structure'],
            'selected_pages' => [], // Build all that are public_static
            'output_mode' => $ssgMode,
            'link_mode' => $ssgLinkMode,
            'copy_media' => $ssgMode === 'export',
        ];

        $ssg = new MikanBoxSSG($renderer, $activeSsgAbsPath, $ssgOpts);
        $ssg->clear(); // Remove old files (handles structure format changes)
        $results = $ssg->build();
        $built = array_filter($results, fn($r) => strpos($r, 'Error') === false);
        $errors = array_filter($results, fn($r) => strpos($r, 'Error') !== false);
        $message = t('msg_ssg_finished', count($built));
        if (!empty($errors)) $message .= ' / ' . implode(', ', $errors);
        if (empty($results)) $message .= t('msg_html_pages_none');
        
        if (!saveSettings($settings)) {
            $message .= ' / ' . t('err_save_failed');
        }
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
    elseif ($_POST['save_action'] === 'download_backup_sqlite') {
        // Download raw SQLite database file directly
        $dbFile = DB_FILE;
        if (file_exists($dbFile)) {
            header('Content-Type: application/x-sqlite3');
            header('Content-Disposition: attachment; filename="mikanBox_' . date('Ymd') . '.sqlite"');
            header('Content-Length: ' . filesize($dbFile));
            readfile($dbFile);
            exit;
        } else {
            $message = t('error_save_failed');
        }
    }
    elseif ($_POST['save_action'] === 'download_backup_json' || $_POST['save_action'] === 'download_backup_media') {
        $mode = $_POST['save_action'] === 'download_backup_json' ? 'data' : 'media';
        $zip = new ZipArchive();
        $zipFile = DATA_DIR . "/backup_{$mode}_" . date('YmdHis') . '.zip';
        
        if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
            if ($mode === 'data') {
                // Dynamically build JSON structure from SQLite
                $settings = [];
                $resSettings = $db->query("SELECT key, value FROM settings");
                if ($resSettings) {
                    while ($row = $resSettings->fetchArray(SQLITE3_ASSOC)) {
                        $settings[$row['key']] = json_decode($row['value'], true);
                    }
                }
                
                // Add users data
                $resUsers = $db->query("SELECT username, password_hash, display_name FROM users");
                if ($resUsers) {
                    $users = [];
                    while ($u = $resUsers->fetchArray(SQLITE3_ASSOC)) {
                        $users[$u['username']] = [
                            'password_hash' => $u['password_hash'],
                            'display_name' => $u['display_name']
                        ];
                        if ($u['username'] === 'admin') {
                            $settings['password_hash'] = $u['password_hash'];
                        }
                    }
                    if (!empty($users)) {
                        $settings['users'] = $users;
                    }
                }
                
                // Add settings.json
                $zip->addFromString('data/settings.json', json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                
                // Add components
                $resComp = $db->query("SELECT id, data FROM components");
                if ($resComp) {
                    while ($row = $resComp->fetchArray(SQLITE3_ASSOC)) {
                        $zip->addFromString('data/components/' . $row['id'] . '.json', $row['data']);
                    }
                }
                
                // Add posts (pages)
                $resPosts = $db->query("SELECT id, data FROM posts");
                if ($resPosts) {
                    while ($row = $resPosts->fetchArray(SQLITE3_ASSOC)) {
                        $zip->addFromString('data/posts/' . $row['id'] . '.json', $row['data']);
                    }
                }
            } else {
                // Media backup remains file-system based
                $sourceDir = MEDIA_DIR;
                if (is_dir($sourceDir)) {
                    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir), RecursiveIteratorIterator::LEAVES_ONLY);
                    foreach ($files as $file) {
                        if (!$file->isDir()) {
                            $filePath = $file->getRealPath();
                            $relativePath = 'media/' . substr($filePath, strlen($sourceDir) + 1);
                            $zip->addFile($filePath, $relativePath);
                        }
                    }
                }
            }
            
            $zip->close();
            
            // Output ZIP file to browser
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename(__DIR__) . '_' . $mode . '_' . date('Ymd') . '.zip"');
            readfile($zipFile);
            unlink($zipFile);
            exit;
        } else {
            $message = t('error_save_failed');
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
                    'copy_media' => ($settings['ssg_mode'] ?? 'server') === 'export',
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
    elseif ($_POST['save_action'] === 'acquire_lock') {
        $type = $_POST['type'] ?? '';
        $id = $_POST['id'] ?? '';
        $editorName = $_SESSION['admin_display_name'] ?? $_SESSION['admin_username'] ?? 'Unknown';
        
        $currentTime = time();
        $expiresAt = $currentTime + 40; // 40 seconds validity
        
        // Clean expired locks
        $db->exec("DELETE FROM locks WHERE expires_at <= {$currentTime}");
        
        // Check if there is a valid lock by someone else
        $stmt = $db->prepare("SELECT editor_id, expires_at FROM locks WHERE item_type = :type AND item_id = :id");
        $stmt->bindValue(':type', $type, SQLITE3_TEXT);
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $res = $stmt->execute();
        $lock = $res ? $res->fetchArray(SQLITE3_ASSOC) : null;
        
        if ($lock && $lock['editor_id'] !== $editorName) {
            $responseData = [
                'success' => false,
                'locked' => true,
                'editor' => $lock['editor_id']
            ];
        } else {
            // Lock is free, expired, or held by current user -> acquire/renew
            $stmtInsert = $db->prepare("INSERT OR REPLACE INTO locks (item_type, item_id, editor_id, expires_at) VALUES (:type, :id, :editor, :expires)");
            $stmtInsert->bindValue(':type', $type, SQLITE3_TEXT);
            $stmtInsert->bindValue(':id', $id, SQLITE3_TEXT);
            $stmtInsert->bindValue(':editor', $editorName, SQLITE3_TEXT);
            $stmtInsert->bindValue(':expires', $expiresAt, SQLITE3_INTEGER);
            $stmtInsert->execute();
            
            $responseData = [
                'success' => true,
                'locked' => false
            ];
        }
        header('Content-Type: application/json');
        echo json_encode($responseData);
        exit;
    }
    elseif ($_POST['save_action'] === 'release_lock') {
        $type = $_POST['type'] ?? '';
        $id = $_POST['id'] ?? '';
        $editorName = $_SESSION['admin_display_name'] ?? $_SESSION['admin_username'] ?? 'Unknown';
        
        $stmt = $db->prepare("DELETE FROM locks WHERE item_type = :type AND item_id = :id AND editor_id = :editor");
        $stmt->bindValue(':type', $type, SQLITE3_TEXT);
        $stmt->bindValue(':id', $id, SQLITE3_TEXT);
        $stmt->bindValue(':editor', $editorName, SQLITE3_TEXT);
        $stmt->execute();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
    elseif ($_POST['save_action'] === 'add_user') {
        $user = trim($_POST['username'] ?? '');
        $disp = trim($_POST['display_name'] ?? '');
        if (empty($disp)) {
            $disp = $user;
        }
        $pass = $_POST['password'] ?? '';
        
        if (empty($user) || !preg_match('/^[a-zA-Z0-9_\-]+$/', $user)) {
            $message = t('err_username_chars');
            $success = false;
        } elseif (strlen($pass) < 4) {
            $message = t('err_password_chars');
            $success = false;
        } else {
            // Check if username already exists
            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
            $stmtCheck->bindValue(':username', $user, SQLITE3_TEXT);
            $exists = $stmtCheck->execute()->fetchArray(SQLITE3_NUM)[0] ?? 0;
            if ($exists > 0) {
                $message = t('err_username_exists');
                $success = false;
            } else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmtInsert = $db->prepare("INSERT INTO users (username, password_hash, display_name, security_question, security_answer_hash) VALUES (:username, :password_hash, :display_name, NULL, NULL)");
                $stmtInsert->bindValue(':username', $user, SQLITE3_TEXT);
                $stmtInsert->bindValue(':password_hash', $hash, SQLITE3_TEXT);
                $stmtInsert->bindValue(':display_name', $disp, SQLITE3_TEXT);
                if ($stmtInsert->execute()) {
                    $message = sprintf(t('msg_user_added'), $disp);
                } else {
                    $message = t('err_save_failed');
                    $success = false;
                }
            }
        }
        if (isset($_POST['ajax_request'])) {
            // Bypass redirect
        } else {
            $_SESSION['admin_message'] = $message;
            header("Location: admin.php?view=site#users-mgmt");
            exit;
        }
    }
    elseif ($_POST['save_action'] === 'delete_user') {
        $user = trim($_POST['username'] ?? '');
        $currentEditor = $_SESSION['admin_username'] ?? '';
        
        // Count users to prevent deleting the last user
        $totalUsers = $db->querySingle("SELECT COUNT(*) FROM users") ?: 0;
        
        if ($user === $currentEditor) {
            $message = t('err_delete_current_user');
            $success = false;
        } elseif ($totalUsers <= 1) {
            $message = t('err_delete_last_user');
            $success = false;
        } else {
            $stmtDelete = $db->prepare("DELETE FROM users WHERE username = :username");
            $stmtDelete->bindValue(':username', $user, SQLITE3_TEXT);
            if ($stmtDelete->execute()) {
                $message = t('msg_user_deleted');
            } else {
                $message = t('err_user_delete_failed');
                $success = false;
            }
        }
        if (isset($_POST['ajax_request'])) {
            // Bypass redirect
        } else {
            $_SESSION['admin_message'] = $message;
            header("Location: admin.php?view=site#users-mgmt");
            exit;
        }
    }
    elseif ($_POST['save_action'] === 'change_my_password') {
        $currentEditor = $_SESSION['admin_username'] ?? '';
        $newUsername = trim($_POST['username'] ?? '');
        $disp = trim($_POST['display_name'] ?? '');
        $currPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $sq = trim($_POST['security_question'] ?? '');
        $sa = trim($_POST['security_answer'] ?? '');
        
        $stmt = $db->prepare("SELECT password_hash, security_answer_hash FROM users WHERE username = :username");
        $stmt->bindValue(':username', $currentEditor, SQLITE3_TEXT);
        $row = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
        
        $hasAnswerSet = $row ? !empty($row['security_answer_hash']) : false;
        
        $verifyPassword = ($newPass !== '') || !$hasAnswerSet || ($currPass !== '');
        
        if (empty($newUsername) || !preg_match('/^[a-zA-Z0-9_\-]+$/', $newUsername)) {
            $message = t('err_userid_chars');
            $success = false;
        } elseif ($newUsername !== $currentEditor) {
            $stmtCheck = $db->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
            $stmtCheck->bindValue(':username', $newUsername, SQLITE3_TEXT);
            $exists = $stmtCheck->execute()->fetchArray(SQLITE3_NUM)[0] ?? 0;
            if ($exists > 0) {
                $message = t('err_userid_exists');
                $success = false;
            }
        }
        
        if ($success !== false) {
            if ($verifyPassword && empty($currPass)) {
                $message = t('err_current_password_required');
                $success = false;
            } elseif ($verifyPassword && (!$row || !password_verify($currPass, $row['password_hash']))) {
                $message = t('err_current_password');
                $success = false;
            } elseif (empty($disp)) {
                $message = t('err_display_name_required');
                $success = false;
            } elseif (empty($sq)) {
                $message = t('err_security_question_required');
                $success = false;
            } elseif (!$hasAnswerSet && empty($sa)) {
                $message = t('err_security_answer_required');
                $success = false;
            } else {
                $updateParts = [
                    "username = :new_username",
                    "display_name = :display_name",
                    "security_question = :security_question"
                ];
                $params = [
                    [':new_username', $newUsername, SQLITE3_TEXT],
                    [':display_name', $disp, SQLITE3_TEXT],
                    [':security_question', $sq, SQLITE3_TEXT]
                ];
            
            if ($newPass !== '') {
                if (strlen($newPass) < 4) {
                    $message = t('err_password_chars');
                    $success = false;
                } else {
                    $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                    $updateParts[] = "password_hash = :password_hash";
                    $params[] = [':password_hash', $newHash, SQLITE3_TEXT];
                }
            }
            
            if ($success !== false && $sa !== '') {
                $saHash = password_hash(normalizeSecurityAnswer($sa), PASSWORD_DEFAULT);
                $updateParts[] = "security_answer_hash = :security_answer_hash";
                $params[] = [':security_answer_hash', $saHash, SQLITE3_TEXT];
            }
            
            if ($success !== false) {
                $sql = "UPDATE users SET " . implode(', ', $updateParts) . " WHERE username = :username";
                $stmtUpdate = $db->prepare($sql);
                $stmtUpdate->bindValue(':username', $currentEditor, SQLITE3_TEXT);
                foreach ($params as $param) {
                    $stmtUpdate->bindValue($param[0], $param[1], $param[2]);
                }
                
                if ($stmtUpdate->execute()) {
                    $_SESSION['admin_username'] = $newUsername;
                    $_SESSION['admin_display_name'] = $disp;
                    $message = t('msg_account_updated');
                } else {
                    $message = t('err_account_update_failed');
                    $success = false;
                }
            }
        }
        }
        if (isset($_POST['ajax_request'])) {
            // Bypass redirect
        } else {
            $_SESSION['admin_message'] = $message;
            header("Location: admin.php?view=site#users-mgmt");
            exit;
        }
    }
}
skip_post_actions:

// Return JSON response for AJAX saves and skip rendering page
if (isset($_POST['ajax_request'])) {
    $responseData = [
        'success' => $success ?? true,
        'message' => $message ?? t('msg_update_success'),
        'editId' => $editId ?? null
    ];
    if (isset($resolvedFilename)) {
        $responseData['filename'] = $resolvedFilename;
    }
    // Compute preview URL for page saves so JS can inject/update the preview button
    if (($_POST['save_action'] ?? '') === 'save_page' && !empty($editId)) {
        $savedStatus = $_POST['status'] ?? 'draft';
        $ssgStruct = $settings['ssg_structure'] ?? 'directory';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $siteDir = dirname(dirname($_SERVER['SCRIPT_NAME']));
        if ($siteDir === '/' || $siteDir === '.') $siteDir = '';
        $siteBaseUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $siteDir;
        if ($editId === 'index') {
            $responseData['preview_url'] = $siteBaseUrl . '/' . ($savedStatus === 'pending' ? '?preview=' . getPreviewToken($editId) : '');
        } elseif ($savedStatus === 'pending') {
            $responseData['preview_url'] = $siteBaseUrl . '/' . $editId . '?preview=' . getPreviewToken($editId);
        } elseif ($savedStatus === 'public_static') {
            $ssgDirForUrl = $settings['ssg_dir'] ?? '';
            $staticRoot = !empty($settings['ssg_root_url'])
                ? rtrim($settings['ssg_root_url'], '/')
                : $siteBaseUrl . (($ssgDirForUrl !== '') ? '/' . trim($ssgDirForUrl, '/') : '');
            $responseData['preview_url'] = $staticRoot . '/' . $editId . ($ssgStruct === 'directory' ? '/' : '.html');
        } else {
            $responseData['preview_url'] = $siteBaseUrl . '/' . $editId;
        }
        
        // Reflect the date actually saved (may be unchanged, or auto-bumped on
        // draft→public transition above), not just "now" on every save.
        $responseData['updated_at'] = $updatedAt ?? date('Y-m-d H:i:s');
        
        $responseData['revisions'] = [];
        $revs = getRevisions($editId);
        foreach ($revs as $r) {
            $responseData['revisions'][] = [
                'id' => $r['id'],
                'created_at' => $r['created_at'],
                'editor_id' => $r['editor_id']
            ];
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

$selectedRevId = isset($_GET['rev']) ? (int)$_GET['rev'] : 0;
$selectedRevInfo = null;

if ($editId) {
    if ($view === 'pages') {
        if ($selectedRevId > 0) {
            $editData = getRevisionData($selectedRevId);
            if ($editData) {
                // Fetch info for warning banner
                $db = getDb();
                $stmtRev = $db->prepare("SELECT created_at, editor_id FROM post_revisions WHERE id = :id");
                $stmtRev->bindValue(':id', $selectedRevId, SQLITE3_INTEGER);
                $resRev = $stmtRev->execute();
                if ($resRev) {
                    $selectedRevInfo = $resRev->fetchArray(SQLITE3_ASSOC);
                }
            }
        }
        if (!$editData) {
            $editData = loadData(POSTS_DIR, $editId);
            $selectedRevId = 0; // reset if invalid revision
        }
    }
    elseif ($view === 'components') {
        $editData = loadData(COMPONENTS_DIR, $editId);
    }
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

// --- AJAX Users List Fragment Endpoint ---
if (isset($_GET['ajax_users'])) {
    $helpFile = (getSystemLanguage() === 'ja') ? 'https://yoshihiko.com/mikanbox/help_ja.html' : 'https://yoshihiko.com/mikanbox/help_en.html';
    ob_start();
    include __DIR__ . '/views/site.php';
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
        'open_in_new' => 'open_in_new',
        'reset' => 'restart_alt',
        'check' => 'check',
        'history' => 'history',
        'warning' => 'warning',
        'close' => 'close'
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
        <span class="close-badge" data-url="admin.php#pages" title="<?= t('btn_close') ?>">×</span>
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
        <span class="close-badge" data-url="admin.php#design" title="<?= t('btn_close') ?>">×</span>
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

    <!-- Category Cloud (Steps 3 & 4) -->
    <?php
    $allPageIds = getFileList(POSTS_DIR);
    $allCategories = [];
    foreach ($allPageIds as $pid) {
        $pData = loadData(POSTS_DIR, $pid);
        if ($pData && !empty($pData['category'])) {
            $cats = array_filter(array_map('trim', explode(',', $pData['category'])));
            foreach ($cats as $c) {
                if ($c !== '') $allCategories[] = $c;
            }
        }
    }
    if (!empty($settings['category_candidates'])) {
        $registeredCats = array_filter(array_map('trim', explode(',', $settings['category_candidates'])));
        foreach ($registeredCats as $c) {
            if ($c !== '') $allCategories[] = $c;
        }
    }
    $allCategories = array_unique($allCategories);
    
    $selectedCat = $_GET['cat'] ?? '';
    if ($selectedCat !== '' && !in_array($selectedCat, $allCategories)) {
        $allCategories[] = $selectedCat;
    }
    sort($allCategories);
    ?>
    <div class="category-cloud-wrap">
        <div class="category-cloud">
            <span class="category-cloud-label"><?= t('category_cloud_label') ?></span>
            <a href="?cat=" class="category-cloud-tag <?= $selectedCat === '' ? 'active' : '' ?>">
                <?= t('all_pages') ?>
            </a>
            <?php foreach ($allCategories as $c): ?>
                <span class="category-cloud-tag-wrap">
                    <a href="?cat=<?= urlencode($c) ?>" class="category-cloud-tag <?= $selectedCat === $c ? 'active' : '' ?>">
                        <?= htmlspecialchars($c) ?>
                    </a>
                    <span class="category-delete-badge" data-category="<?= htmlspecialchars($c) ?>" title="<?= t('category_delete_confirm_title') ?>">&times;</span>
                </span>
            <?php endforeach; ?>
            <span class="category-control-group" style="display: inline-flex; align-items: center; gap: 8px; white-space: nowrap;">
                <span class="category-cloud-divider" style="margin: 0;">|</span>
                <a href="#" class="category-cloud-tag btn-add-cat" style="border-style: dashed; background: transparent; display: inline-flex; align-items: center; gap: 5px;">
                    <?= getIcon('add') ?> <?= t('btn_add') ?>
                </a>
                <a href="#" class="category-cloud-tag btn-toggle-del-cat" style="border-style: dashed; background: transparent; display: inline-flex; align-items: center; gap: 5px;">
                    <?= getIcon('delete') ?> <?= t('btn_delete') ?>
                </a>
                <input type="text" id="new-category-input" placeholder="<?= t('placeholder_new_category') ?>" style="display: none; width: 120px; padding: 4px 10px; border-radius: 8px; border: 1px solid #ff8c00; font-size: 0.85rem; height: 30px; box-sizing: border-box; vertical-align: middle;">
            </span>
        </div>
    </div>

    <?php include __DIR__ . '/views/pages.php'; ?>

    <?php include __DIR__ . '/views/site.php'; ?>

    <?php include __DIR__ . '/views/design.php'; ?>

    <?php include __DIR__ . '/views/media.php'; ?>




    <script>
    // ブラウザのスクロール復元を無効にして競合させない
    if ('scrollRestoration' in history) history.scrollRestoration = 'manual';

    // 新規カテゴリ追加処理 (Steps 3 & 4) とカテゴリ削除処理
    document.addEventListener('click', async function(e) {
        const addBtn = e.target.closest('.btn-add-cat');
        if (addBtn) {
            e.preventDefault();
            addBtn.style.display = 'none';
            const input = document.getElementById('new-category-input');
            if (input) {
                input.style.display = 'inline-block';
                input.focus();
            }
            return;
        }

        const toggleDelBtn = e.target.closest('.btn-toggle-del-cat');
        if (toggleDelBtn) {
            e.preventDefault();
            const wrap = document.querySelector('.category-cloud-wrap');
            if (wrap) {
                const isActive = wrap.classList.toggle('delete-mode');
                if (isActive) {
                    toggleDelBtn.innerHTML = '<?= getIcon("check") ?> <?= t('btn_done') ?>';
                    toggleDelBtn.style.color = '#15803d';
                    toggleDelBtn.style.borderColor = '#bbf7d0';
                } else {
                    toggleDelBtn.innerHTML = '<?= getIcon("delete") ?> <?= t('btn_delete') ?>';
                    toggleDelBtn.style.color = '';
                    toggleDelBtn.style.borderColor = '';
                }
            }
            return;
        }

        const deleteBadge = e.target.closest('.category-delete-badge');
        if (deleteBadge) {
            e.preventDefault();
            const val = deleteBadge.getAttribute('data-category');
            if (!val) return;

            const sendDeleteRequest = async (confirmed = false) => {
                const formData = new FormData();
                formData.append('save_action', 'delete_category');
                formData.append('category', val);
                formData.append('ajax_request', '1');
                if (confirmed) {
                    formData.append('confirmed', '1');
                }
                const csrfInput = document.querySelector('input[name="csrf_token"]');
                if (csrfInput) formData.append('csrf_token', csrfInput.value);

                try {
                    const res = await fetch(window.location.href, { method: 'POST', body: formData });
                    const json = await res.json().catch(() => ({}));

                    if (json.success) {
                        // Find the wrap element and remove it
                        const wrap = deleteBadge.closest('.category-cloud-tag-wrap');
                        if (wrap) {
                            wrap.remove();
                        }

                        // Check if deleted category was selected
                        const url = new URL(window.location.href);
                        if (url.searchParams.get('cat') === val) {
                            url.searchParams.delete('cat');
                            url.searchParams.delete('p_pages');
                            url.searchParams.delete('p_media');
                            url.searchParams.delete('media_all');
                            
                            history.pushState(null, '', url.pathname + url.search + url.hash);

                            // Reset active states, set "All" to active
                            document.querySelectorAll('.category-cloud-tag').forEach(el => el.classList.remove('active'));
                            const allTag = document.querySelector('a.category-cloud-tag[href="?cat="]');
                            if (allTag) allTag.classList.add('active');
                        }

                        // Refresh page list and media grid
                        await Promise.all([refreshPageList(), refreshMediaGrid()]);
                        showToast(json.message);
                    } else if (json.require_confirm) {
                        const dialog = document.getElementById('cat-delete-confirm-dialog');
                        const msgEl = document.getElementById('cat-delete-dialog-message');
                        const btnCancel = document.getElementById('btn-cat-delete-cancel');
                        const btnOk = document.getElementById('btn-cat-delete-ok');
                        
                        if (dialog && msgEl && btnCancel && btnOk) {
                            msgEl.textContent = json.message;
                            dialog.showModal();
                            
                            const confirmed = await new Promise((resolve) => {
                                const onCancel = () => { dialog.close(); resolve(false); };
                                const onOk = () => { dialog.close(); resolve(true); };
                                
                                btnCancel.addEventListener('click', onCancel, { once: true });
                                btnOk.addEventListener('click', onOk, { once: true });
                                
                                dialog.addEventListener('close', () => {
                                    btnCancel.removeEventListener('click', onCancel);
                                    btnOk.removeEventListener('click', onOk);
                                    resolve(false);
                                }, { once: true });
                            });
                            
                            if (confirmed) {
                                await sendDeleteRequest(true);
                            }
                        }
                    } else {
                        showToast(json.message, true);
                    }
                } catch (err) {
                    showToast('<?= t('err_category_delete_failed') ?>', true);
                }
            };

            await sendDeleteRequest(false);
        }
    });

    const newCatInput = document.getElementById('new-category-input');
    if (newCatInput) {
        newCatInput.addEventListener('keydown', async function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const val = newCatInput.value.trim();
                if (val) {
                    // Send AJAX request to save category candidate permanently in database
                    const formData = new FormData();
                    formData.append('save_action', 'add_category');
                    formData.append('category', val);
                    formData.append('ajax_request', '1');
                    const csrfInput = document.querySelector('input[name="csrf_token"]');
                    if (csrfInput) formData.append('csrf_token', csrfInput.value);

                    try {
                        const res = await fetch(window.location.href, { method: 'POST', body: formData });
                        const json = await res.json().catch(() => ({}));                        if (json.success) {
                            // Check if tag already exists in the cloud
                            let existingTag = null;
                            document.querySelectorAll('.category-cloud-tag').forEach(tag => {
                                if (tag.textContent.trim() === val) {
                                    existingTag = tag;
                                }
                            });

                            if (!existingTag) {
                                // Create new wrap element
                                const wrap = document.createElement('span');
                                wrap.className = 'category-cloud-tag-wrap';

                                // Create new tag element
                                const newTag = document.createElement('a');
                                newTag.href = `?cat=${encodeURIComponent(val)}`;
                                newTag.className = 'category-cloud-tag';
                                newTag.textContent = val;

                                // Create delete badge
                                const badge = document.createElement('span');
                                badge.className = 'category-delete-badge';
                                badge.setAttribute('data-category', val);
                                badge.title = '<?= t('category_delete_confirm_title') ?>';
                                badge.innerHTML = '&times;';

                                wrap.appendChild(newTag);
                                wrap.appendChild(badge);

                                // Insert it before the control group
                                const controlGroup = document.querySelector('.category-control-group');
                                if (controlGroup) {
                                    controlGroup.parentNode.insertBefore(wrap, controlGroup);
                                } else {
                                    newCatInput.parentNode.insertBefore(wrap, newCatInput);
                                }
                            }
                            showToast(json.message);
                        } else {
                            showToast(json.message, true);
                        }
                    } catch(err) {
                        showToast('<?= t('err_category_add_failed') ?>', true);
                    } finally {
                        // Reset and hide input
                        newCatInput.value = '';
                        newCatInput.style.display = 'none';
                        const btn = document.querySelector('.btn-add-cat');
                        if (btn) btn.style.display = 'inline-flex';
                    }
                } else {
                    newCatInput.style.display = 'none';
                    const btn = document.querySelector('.btn-add-cat');
                    if (btn) btn.style.display = 'inline-flex';
                }
            } else if (e.key === 'Escape') {
                newCatInput.value = '';
                newCatInput.style.display = 'none';
                const btn = document.querySelector('.btn-add-cat');
                if (btn) btn.style.display = 'inline-flex';
            }
        });
        
        newCatInput.addEventListener('blur', function() {
            if (!newCatInput.value.trim()) {
                newCatInput.style.display = 'none';
                const btn = document.querySelector('.btn-add-cat');
                if (btn) btn.style.display = 'inline-flex';
            }
        });
    }
    // エディター読み込み時: 遅延なしで即スムーズスクロール開始（window.onload より大幅に早い）
    (function() {
        var hash = window.location.hash;
        if (hash === '#page-editor' || hash === '#design-editor' || hash === '#pages' || hash === '#media') {
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
        const urlParams = new URLSearchParams(window.location.search);
        const cat = urlParams.get('cat') || '';
        if (cat && !formData.has('cat')) {
            formData.append('cat', cat);
        }
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
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('ajax_media', '1');
            const res = await fetch('?' + urlParams.toString());
            const html = await res.text();
            const temp = document.createElement('div');
            temp.innerHTML = html;
            const newWrap = temp.querySelector('#media-list-wrap');
            const oldWrap = document.querySelector('#media-list-wrap');
            if (newWrap && oldWrap) oldWrap.outerHTML = newWrap.outerHTML;
        } catch(err) {}
    }

    async function refreshPageList() {
        try {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('ajax_pages', '1');
            urlParams.set('view', 'pages');
            const res = await fetch('?' + urlParams.toString());
            const html = await res.text();
            const temp = document.createElement('div');
            temp.innerHTML = html;
            const newWrap = temp.querySelector('#pages-table-wrap');
            const oldWrap = document.querySelector('#pages-table-wrap');
            if (newWrap && oldWrap) oldWrap.outerHTML = newWrap.outerHTML;
            // Also refresh pagination controls if any
            const newPag = temp.querySelector('#pages .pagination');
            const oldPag = document.querySelector('#pages .pagination');
            if (newPag && oldPag) {
                oldPag.outerHTML = newPag.outerHTML;
            } else if (newPag && !oldPag) {
                const parent = document.querySelector('#pages .section-container');
                if (parent) parent.insertBefore(newPag, parent.querySelector('.ssg-build-row'));
            } else if (!newPag && oldPag) {
                oldPag.remove();
            }
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

    async function refreshUserList() {
        try {
            const res = await fetch('?ajax_users=1&view=site');
            const html = await res.text();
            const temp = document.createElement('div');
            temp.innerHTML = html;
            const newWrap = temp.querySelector('#users-list-wrap');
            const oldWrap = document.querySelector('#users-list-wrap');
            if (newWrap && oldWrap) oldWrap.outerHTML = newWrap.outerHTML;
        } catch(err) {}
    }

    // Category Cloud click handler
    document.addEventListener('click', async function(e) {
        const tag = e.target.closest('.category-cloud-tag');
        if (!tag) return;

        // Skip category filtering for control buttons (New and Delete)
        if (tag.classList.contains('btn-add-cat') || tag.classList.contains('btn-toggle-del-cat')) {
            return;
        }

        e.preventDefault();
        const url = new URL(tag.href, window.location.href);
        
        // Remove active class from all tags and add it to the clicked one
        document.querySelectorAll('.category-cloud-tag').forEach(el => el.classList.remove('active'));
        tag.classList.add('active');

        // Reset pagination and media ignoring state when category changes
        url.searchParams.delete('p_pages');
        url.searchParams.delete('p_media');
        url.searchParams.delete('media_all');

        // Update URL
        history.pushState(null, '', url.pathname + url.search + url.hash);

        // Refresh both lists
        await Promise.all([refreshPageList(), refreshMediaGrid()]);
    });

    // Media filter toggle click handler
    document.addEventListener('click', async function(e) {
        const btn = e.target.closest('.media-filter-toggle-btn');
        if (!btn) return;

        e.preventDefault();
        const url = new URL(btn.href, window.location.href);

        // Update URL
        history.pushState(null, '', url.pathname + url.search + url.hash);

        // Refresh media grid
        await refreshMediaGrid();
    });

    // Modeless AJAX Pagination click handler
    document.addEventListener('click', async function(e) {
        const link = e.target.closest('.pagination-link');
        if (!link) return;

        e.preventDefault();
        const url = new URL(link.href, window.location.href);
        const pPages = url.searchParams.get('p_pages');
        const pMedia = url.searchParams.get('p_media');

        // Update browser URL without reload
        history.pushState(null, '', url.pathname + url.search + url.hash);

        if (pPages) {
            await refreshPageList();
        } else if (pMedia) {
            await refreshMediaGrid();
        }

        // Smoothly scroll to the top of the section (e.g. #pages or #media)
        if (url.hash) {
            const target = document.querySelector(url.hash);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth' });
            }
        }
    });

    // save_action値がメディア操作（AJAXで別途ハンドリングされる）かどうかを判定
    function isMediaFormAction(action) {
        return action === 'resize_media' || action === 'delete_media' || action === 'rename_media';
    }

    // Resize, rename and delete media forms - event delegation
    document.addEventListener('submit', async function(e) {
        const form = e.target;
        const actionInput = form.querySelector('input[name="save_action"]');
        if (!actionInput) return;
        const action = actionInput.value;
        if (isMediaFormAction(action)) {
            e.preventDefault();
            
            if (action === 'delete_media') {
                const confirmed = await window.showConfirmDialog('<?= t('hint_confirm_delete') ?>', '<?= t('btn_delete') ?>', '<?= t('btn_delete_confirm') ?>', 'btn-red');
                if (!confirmed) return;
            }
            const formData = new FormData(form);
            formData.append('ajax_request', '1');
            try {
                const res = await fetch(window.location.href, { method: 'POST', body: formData });
                const json = await res.json().catch(() => ({}));
                
                if (action === 'rename_media' && json.require_confirm) {
                    const dialog = document.getElementById('rename-confirm-dialog');
                    const msgEl = document.getElementById('rename-dialog-message');
                    const btnCancel = document.getElementById('btn-rename-cancel');
                    const btnOnly = document.getElementById('btn-rename-only');
                    const btnUpdate = document.getElementById('btn-rename-update');
                    
                    if (dialog && msgEl && btnCancel && btnOnly && btnUpdate) {
                        msgEl.textContent = json.message;
                        dialog.showModal();
                        
                        const choice = await new Promise((resolve) => {
                            const onCancel = () => { dialog.close(); resolve('cancel'); };
                            const onOnly = () => { dialog.close(); resolve('only'); };
                            const onUpdate = () => { dialog.close(); resolve('update'); };
                            
                            btnCancel.addEventListener('click', onCancel, { once: true });
                            btnOnly.addEventListener('click', onOnly, { once: true });
                            btnUpdate.addEventListener('click', onUpdate, { once: true });
                            
                            dialog.addEventListener('close', () => {
                                btnCancel.removeEventListener('click', onCancel);
                                btnOnly.removeEventListener('click', onOnly);
                                btnUpdate.removeEventListener('click', onUpdate);
                                resolve('cancel');
                            }, { once: true });
                        });
                        
                        if (choice === 'update') {
                            formData.append('confirmed', '1');
                            const res2 = await fetch(window.location.href, { method: 'POST', body: formData });
                            const json2 = await res2.json().catch(() => ({}));
                            showToast(json2.message || '', !json2.success);
                            if (json2.success) await refreshMediaGrid();
                        } else if (choice === 'only') {
                            formData.append('confirmed', '0');
                            const res2 = await fetch(window.location.href, { method: 'POST', body: formData });
                            const json2 = await res2.json().catch(() => ({}));
                            showToast(json2.message || '', !json2.success);
                            if (json2.success) await refreshMediaGrid();
                        } else {
                            const newFileInput = form.querySelector('input[name="new_filename"]');
                            const oldFileInput = form.querySelector('input[name="old_filename"]');
                            if (newFileInput && oldFileInput) {
                                newFileInput.value = oldFileInput.value;
                            }
                        }
                    }
                } else {
                    showToast(json.message || '', !json.success);
                    if (json.success) await refreshMediaGrid();
                }
            } catch(err) {
                showToast('<?= t('err_save_failed') ?>', true);
            }
        }
    });

    function basename(path) {
        return path.split('/').reverse()[0];
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
                showToast('<?= t('err_save_failed') ?>', true);
            }
        } catch(err) {
            showToast('<?= t('err_network_error') ?>', true);
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
    let pageLockInterval = null;
    let designLockInterval = null;
    let pageLockedId = null;
    let designLockedId = null;

    function escapeHtml(str) {
        return (str || '').replace(/[&<>"']/g, function(m) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[m];
        });
    }

    // acquire_lock/release_lock用のFormDataを組み立てる（fetch/sendBeacon両方から共通利用）
    function buildLockFormData(action, type, id, includeAjaxFlag = true) {
        const data = new FormData();
        data.append('save_action', action);
        data.append('type', type);
        data.append('id', id);
        data.append('csrf_token', csrfToken);
        if (includeAjaxFlag) data.append('ajax_request', '1');
        return data;
    }

    async function acquireLockFrontend(type, id) {
        if (!id) return;
        
        if (type === 'page') {
            if (pageLockInterval) clearInterval(pageLockInterval);
            pageLockInterval = null;
            pageLockedId = id;
        } else {
            if (designLockInterval) clearInterval(designLockInterval);
            designLockInterval = null;
            designLockedId = id;
        }

        const formData = buildLockFormData('acquire_lock', type, id);

        try {
            const res = await fetch(window.location.href, { method: 'POST', body: formData });
            if (res.ok) {
                const json = await res.json().catch(() => ({}));
                const editorCard = document.querySelector(type === 'page' ? '#page-editor .editor-floating-card' : '#design-editor .editor-floating-card');
                if (editorCard) {
                    const existing = editorCard.querySelector('.lock-warning-banner');
                    if (existing) existing.remove();
                }
                
                if (json.locked) {
                    if (editorCard) {
                        const banner = document.createElement('div');
                        banner.className = 'lock-warning-banner';
                        banner.style.cssText = 'background-color:#fffbeb; border:1px solid #fef3c7; border-left:4px solid #f59e0b; padding:12px 16px; border-radius:6px; margin-bottom:15px; display:flex; align-items:center; gap:8px; color:#b45309; font-size:0.9rem; text-align:left;';
                        banner.innerHTML = `<span class="material-symbols-outlined" style="color:#d97706; font-size:1.25rem;">warning</span>` +
                                           `<span>${<?= json_encode(t('msg_editor_conflict_warning')) ?>.replace('%s', escapeHtml(json.editor))}</span>`;
                        editorCard.insertAdjacentElement('afterbegin', banner);
                    }
                } else if (json.success) {
                    const intervalId = setInterval(async () => {
                        const hbData = buildLockFormData('acquire_lock', type, id);
                        await fetch(window.location.href, { method: 'POST', body: hbData }).catch(() => {});
                    }, 20000);
                    
                    if (type === 'page') {
                        pageLockInterval = intervalId;
                    } else {
                        designLockInterval = intervalId;
                    }
                }
            }
        } catch (e) {
            console.error('Lock acquisition failed:', e);
        }
    }

    async function releaseLockFrontend(type) {
        let id = null;
        if (type === 'page') {
            id = pageLockedId;
            if (pageLockInterval) clearInterval(pageLockInterval);
            pageLockInterval = null;
            pageLockedId = null;
        } else {
            id = designLockedId;
            if (designLockInterval) clearInterval(designLockInterval);
            designLockInterval = null;
            designLockedId = null;
        }
        
        if (!id) return;

        const formData = buildLockFormData('release_lock', type, id);
        await fetch(window.location.href, { method: 'POST', body: formData }).catch(() => {});
    }

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
            '<span class="close-badge" data-url="' + closeUrl + '" title="<?= t('btn_close') ?>">×</span>';
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

    function spaOpenEditor(type, editId, revId = null) {
        // Release old lock of same type if any
        releaseLockFrontend(type);

        // type: 'page' or 'design'
        const view = type === 'page' ? 'pages' : 'design';
        const slotId = type === 'page' ? 'page-editor-slot' : 'design-editor-slot';
        const editorId = type === 'page' ? 'page-editor' : 'design-editor';
        const param = editId ? 'edit=' + encodeURIComponent(editId) : 'new=1';
        const revParam = revId ? '&rev=' + encodeURIComponent(revId) : '';
        
        // Preserve active search query in editor URLs
        const searchInput = document.getElementById('admin-page-search');
        const qVal = searchInput ? searchInput.value.trim() : '';
        const qParam = qVal ? '&q=' + encodeURIComponent(qVal) : '';
        
        const url = 'admin.php?view=' + view + '&' + param + revParam + qParam + '&ajax_editor=1';

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

                // Parse scripts first
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = html;

                // DOMに挿入
                slot.innerHTML = html;
                const editor = document.getElementById(editorId);
                if (!editor) return;

                // Run fetched scripts in global scope
                tempDiv.querySelectorAll('script').forEach(s => {
                    const scriptEl = document.createElement('script');
                    scriptEl.textContent = s.textContent;
                    document.body.appendChild(scriptEl);
                    scriptEl.remove();
                });

                // nav-editボタンを追加
                const navBtn = createNavEditButton(type);
                const anchor = getNavEditAnchor(type);
                if (anchor) anchor.parentNode.insertBefore(navBtn, anchor);

                // isDirtyトラッカーをバインド
                bindDirtyTrackers(editor);
                window.isDirty = false;

                // URLを更新 (q/revパラメータを保持)
                const newUrl = 'admin.php?view=' + view + '&' + param + revParam + qParam + '#' + editorId;
                history.pushState({ spaEditor: true, type, editId, revId }, '', newUrl);

                if (editId) {
                    acquireLockFrontend(type, editId);
                }

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
                showToast(<?= json_encode(t('err_editor_load_failed')) ?>, true);
                console.error(err);
            });
    }

    function spaCloseEditor(type) {
        releaseLockFrontend(type);

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
        // Scoped to the page/design editor containers only (if server-rendered open on initial load).
        // Elements outside an open editor (e.g. inline status dropdowns in the list) must not
        // mark the app as dirty, or the unsaved-changes modal pops up with no editor open.
        window.isDirty = false;
        const initialPageEditor = document.getElementById('page-editor');
        const initialDesignEditor = document.getElementById('design-editor');
        if (initialPageEditor) bindDirtyTrackers(initialPageEditor);
        if (initialDesignEditor) bindDirtyTrackers(initialDesignEditor);
        
        window.addEventListener('beforeunload', function (e) {
            if (window.isDirty) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        window.addEventListener('unload', function() {
            if (pageLockedId) {
                navigator.sendBeacon('admin.php', buildLockFormData('release_lock', 'page', pageLockedId, false));
            }
            if (designLockedId) {
                navigator.sendBeacon('admin.php', buildLockFormData('release_lock', 'design', designLockedId, false));
            }
        });

        // ページ内リンクの滑らかなスクロール（動的に追加される編集アイコン等にも対応するデリゲーション処理）
        const sidebar = document.querySelector('.side-nav');
        if (sidebar) {
            sidebar.addEventListener('click', function(e) {
                const link = e.target.closest('a[href^="#"]');
                if (link) {
                    if (e.target.closest('.close-badge')) return; // close-badge は body ハンドラーに任せる
                    try {
                        const target = document.querySelector(link.hash);
                        if (target) {
                            e.preventDefault();
                            target.scrollIntoView({ behavior: 'smooth' });
                            history.pushState(null, null, link.hash);
                        }
                    } catch (err) {
                        // Ignore query selector errors for invalid hashes
                    }
                }
            });
        }

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
                const revId = urlParams.get('rev') || null;
                spaOpenEditor(type, editId, revId);
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
                    
                    const savingHtml = '<span class="material-symbols-outlined icon" style="animation: spin 1s linear infinite;">sync</span> ' + <?= json_encode(t('msg_saving')) ?>;
                    saveBtn.innerHTML = savingHtml;
                    saveBtn.disabled = true;
                    document.getElementById('btn-modal-save').innerHTML = savingHtml;
                    document.getElementById('btn-modal-save').disabled = true;
                    
                    try {
                        const formData = new FormData(form);
                        // Only override save_action when the button itself declares a name
                        // (e.g. delete buttons). Some save buttons (like the component editor's)
                        // rely solely on the form's hidden save_action input and have no name/value
                        // of their own — appending an empty pair here would clobber that hidden value.
                        if (saveBtn.name) {
                            formData.append(saveBtn.name, saveBtn.value);
                        }
                        formData.append('ajax_request', '1');
                        const res = await fetch(window.location.href, { method: 'POST', body: formData });
                        if (res.ok) {
                            const json = await res.json().catch(() => ({}));
                            if (json.success) {
                                window.isDirty = false;
                                unsavedModal.style.display = 'none';
                                spaHandlePendingNavigation();
                            } else {
                                showToast(json.message || '<?= t('err_save_failed') ?>', true);
                            }
                        } else {
                            showToast('<?= t('err_save_failed') ?>', true);
                            unsavedModal.style.display = 'none';
                        }
                    } catch(e) {
                        showToast('<?= t('err_network_error_detail') ?>', true);
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
            
            const ajaxActions = ['save_page', 'save_comp', 'save_settings', 'save_memo', 'ssg_save_settings', 'generate_mcp_key', 'add_user', 'delete_user', 'change_my_password'];
            
            if (ajaxActions.includes(action)) {
                e.preventDefault();
                const originalText = submitter.innerHTML;
                let loadingText = '<?= t('msg_saving') ?>';
                if (action === 'delete_user') loadingText = '<?= t('msg_deleting') ?>';
                else if (action === 'change_my_password') loadingText = '<?= t('msg_changing') ?>';
                else if (action === 'add_user') loadingText = '<?= t('msg_adding') ?>';
                submitter.innerHTML = '<span class="material-symbols-outlined icon" style="animation: spin 1s linear infinite;">sync</span> ' + loadingText;
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
                        showToast(json.message || '<?= t('msg_update_success') ?? '保存しました' ?>', !json.success);
                        if (json.success) {
                            if (action === 'add_user') {
                                form.reset();
                            } else if (action === 'change_my_password') {
                                setTimeout(() => { window.location.reload(); }, 600);
                            }
                        }
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
                            const newId = idInput ? idInput.value : '';
                            if (newId && pageLockedId !== newId) {
                                acquireLockFrontend('page', newId);
                            }
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
                                            previewBtn.innerHTML = '<span class="material-symbols-outlined icon">visibility</span> ' + <?= json_encode(t('btn_preview')) ?>;
                                            saveBtn.insertAdjacentElement('afterend', previewBtn);
                                        }
                                    }
                                    if (previewBtn) previewBtn.href = json.preview_url;
                                }
                            }

                            // Update last updated timestamp input
                            // This input lives in the editor header (outside <form id="page-form">,
                            // associated only via its form="page-form" attribute), so form.querySelector
                            // (which only searches descendants of the form) can't find it. Search the
                            // whole editor container instead.
                            const pageEditorEl = document.getElementById('page-editor');
                            const updatedAtInput = pageEditorEl ? pageEditorEl.querySelector('input[name="updated_at"]') : null;
                            if (updatedAtInput && json.updated_at) {
                                updatedAtInput.value = json.updated_at;
                            }

                            // Update or build the pending preview block contents
                            const previewBlock = document.getElementById('pending-preview-block');
                            if (previewBlock && json.preview_url) {
                                let inputGroup = previewBlock.querySelector('div');
                                if (!inputGroup) {
                                    previewBlock.innerHTML = '';
                                    
                                    const label = document.createElement('span');
                                    label.style.cssText = 'font-size: 0.8rem; color: #64748b; font-weight: 500; display: block; margin-bottom: 5px;';
                                    label.textContent = <?= json_encode(t('preview_share_url_label')) ?>;
                                    previewBlock.appendChild(label);
                                    
                                    inputGroup = document.createElement('div');
                                    inputGroup.style.cssText = 'display: flex; gap: 8px; align-items: center;';
                                    
                                    const input = document.createElement('input');
                                    input.type = 'text';
                                    input.id = 'pending-preview-url';
                                    input.value = json.preview_url;
                                    input.readOnly = true;
                                    input.style.cssText = 'flex: 1; padding: 6px 10px; font-size: 0.85rem; font-family: monospace; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff;';
                                    input.onclick = function() { this.select(); };
                                    inputGroup.appendChild(input);
                                    
                                    const btn = document.createElement('button');
                                    btn.type = 'button';
                                    btn.className = 'btn btn-gray btn-small';
                                    btn.style.cssText = 'padding: 6px 12px; display: inline-flex; align-items: center; gap: 4px; white-space: nowrap;';
                                    btn.onclick = function() { copyPendingPreviewUrl(); };
                                    btn.innerHTML = '<span class="material-symbols-outlined icon">content_copy</span> <span id="copy-btn-text">' + <?= json_encode(t('btn_copy')) ?> + '</span>';
                                    inputGroup.appendChild(btn);
                                    
                                    previewBlock.appendChild(inputGroup);
                                } else {
                                    const input = document.getElementById('pending-preview-url');
                                    if (input) input.value = json.preview_url;
                                }
                            }

                            // Rebuild Revisions Dropdown dynamically
                            const revsContainer = document.getElementById('editor-revisions-container');
                            if (revsContainer && json.revisions && json.revisions.length > 0) {
                                let html = '<div class="revisions-select-group" style="display: flex; align-items: center; gap: 8px; white-space: nowrap; flex-shrink: 0;">';
                                html += '<span style="font-size: 0.85rem; color: var(--text-sub); font-weight: 500; display: inline-flex; align-items: center; gap: 4px;">';
                                html += '<span class="material-symbols-outlined icon">history</span> ' + <?= json_encode(t('label_revisions')) ?> + ':';
                                html += '</span>';
                                html += '<select id="revision-select" onchange="spaChangeRevision(this);" style="padding: 6px 12px; font-size: 0.85rem; border: 1px solid var(--border); border-radius: 6px; background-color: #fff; cursor: pointer; min-width: 180px; max-width: 280px;">';
                                html += '<option value="?view=pages&edit=' + encodeURIComponent(json.editId) + '#page-editor">' + <?= json_encode(t('select_revision')) ?> + '</option>';
                                json.revisions.forEach(rev => {
                                    const escCreatedAt = rev.created_at.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
                                    const escEditorId = rev.editor_id.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
                                    html += '<option value="?view=pages&edit=' + encodeURIComponent(json.editId) + '&rev=' + encodeURIComponent(rev.id) + '#page-editor">';
                                    html += escCreatedAt + ' (' + escEditorId + ')';
                                    html += '</option>';
                                });
                                html += '</select></div>';
                                revsContainer.innerHTML = html;
                            }

                            // Remove rev parameter from address bar on successful save (restores back to current/latest version)
                            const currentUrl = new URL(window.location.href);
                            if (currentUrl.searchParams.has('rev')) {
                                currentUrl.searchParams.delete('rev');
                                history.replaceState(null, '', currentUrl.pathname + currentUrl.search + currentUrl.hash);
                            }

                            // Remove revision warning banner
                            const revWarning = document.getElementById('revision-warning-banner');
                            if (revWarning) {
                                revWarning.remove();
                            }
                        } else if (action === 'save_comp') {
                            refreshCompList();
                            const newId = idInput ? idInput.value : '';
                            if (newId && designLockedId !== newId) {
                                acquireLockFrontend('design', newId);
                            }
                        } else if (action === 'add_user' || action === 'delete_user') {
                            if (json.success) {
                                refreshUserList();
                            }
                        }
                    } else {
                        showToast('<?= t('err_save_failed') ?>', true);
                    }
                } catch(err) {
                    showToast('<?= t('err_network_error') ?>', true);
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

    window.showConfirmDialog = function(message, title = '', okText = '', okClass = 'btn-red') {
        return new Promise((resolve) => {
            const dialog = document.getElementById('global-confirm-dialog');
            const titleEl = document.getElementById('global-confirm-title');
            const msgEl = document.getElementById('global-confirm-message');
            const btnCancel = document.getElementById('btn-global-confirm-cancel');
            const btnOk = document.getElementById('btn-global-confirm-ok');
            
            if (!dialog || !msgEl || !btnCancel || !btnOk) {
                resolve(confirm(message));
                return;
            }
            
            if (titleEl) titleEl.textContent = title || '<?= t('btn_delete') ?>';
            msgEl.textContent = message;
            btnOk.textContent = okText || '<?= t('btn_delete_confirm') ?>';
            
            btnOk.className = 'btn btn-small ' + okClass;
            
            dialog.showModal();
            
            const onCancel = () => { dialog.close(); resolve(false); };
            const onOk = () => { dialog.close(); resolve(true); };
            
            btnCancel.addEventListener('click', onCancel, { once: true });
            btnOk.addEventListener('click', onOk, { once: true });
            
            dialog.addEventListener('close', () => {
                btnCancel.removeEventListener('click', onCancel);
                btnOk.removeEventListener('click', onOk);
                resolve(false);
            }, { once: true });
        });
    };

    // Global submit event listener to intercept native delete operations
    document.addEventListener('submit', async function(e) {
        const form = e.target;
        
        // Skip AJAX forms (like media forms) that are handled elsewhere
        const actionInput = form.querySelector('input[name="save_action"]');
        const action = actionInput ? actionInput.value : '';
        if (isMediaFormAction(action)) {
            return;
        }

        if (form.dataset.confirmed === '1') {
            return;
        }
        
        const submitter = e.submitter;
        const isDeleteAction = 
            (submitter && submitter.name === 'save_action' && (submitter.value === 'delete_page' || submitter.value === 'delete_comp')) ||
            (action === 'delete_user');
            
        if (isDeleteAction) {
            e.preventDefault();
            e.stopPropagation();
            
            let msg = '<?= t('hint_confirm_delete') ?>';
            if (action === 'delete_user') {
                const dispName = form.dataset.displayName || '';
                msg = <?= json_encode(t('confirm_delete_user')) ?>.replace('%s', dispName);
            }
            
            const confirmed = await window.showConfirmDialog(msg, '<?= t('btn_delete') ?>', '<?= t('btn_delete_confirm') ?>', 'btn-red');
            if (confirmed) {
                form.dataset.confirmed = '1';
                if (submitter && submitter.name && submitter.value) {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = submitter.name;
                    hidden.value = submitter.value;
                    form.appendChild(hidden);
                }
                form.submit();
            }
        }
    });
    
    if (!document.getElementById('spin-keyframes')) {
        const style = document.createElement('style');
        style.id = 'spin-keyframes';
        style.textContent = `@keyframes spin { 100% { transform: rotate(360deg); } }`;
        document.head.appendChild(style);
    }

    // Admin page search inline logic (AJAX driven, no page reload)
    function initAdminPageSearch() {
        const searchInput = document.getElementById('admin-page-search');
        if (!searchInput) return;

        const searchIcon = document.getElementById('admin-page-search-icon');
        const clearBtn = document.getElementById('admin-page-search-clear');
        let debounceTimer;

        searchInput.addEventListener('input', function() {
            const hasText = !!searchInput.value;
            if (clearBtn) clearBtn.style.display = hasText ? 'flex' : 'none';
            if (searchIcon) searchIcon.style.display = hasText ? 'none' : 'flex';
            
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                triggerAdminSearch(searchInput.value);
            }, 250);
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                clearBtn.style.display = 'none';
                if (searchIcon) searchIcon.style.display = 'flex';
                triggerAdminSearch('');
            });
        }
    }

    function triggerAdminSearch(keyword) {
        const url = new URL(window.location.href);
        if (keyword) {
            url.searchParams.set('q', keyword);
        } else {
            url.searchParams.delete('q');
        }
        url.searchParams.delete('p_pages'); // reset pagination
        
        // Update URL without reload and without triggering hash jumps
        history.pushState(null, '', url.pathname + url.search + url.hash);
        
        refreshPageList();
    }

    // Global revisions selector handler
    function spaChangeRevision(select) {
        if (!select || !select.value) return;
        const url = new URL(select.value, window.location.href);
        const editId = url.searchParams.get('edit');
        const revId = url.searchParams.get('rev');
        spaOpenEditor('page', editId, revId);
    }

    // Run initialization
    initAdminPageSearch();
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

<!-- Native Dialog for category delete confirmation -->
<dialog id="cat-delete-confirm-dialog" class="custom-dialog">
    <h3 style="margin-top:0; margin-bottom:12px; font-size:1.05rem; display:flex; align-items:center; gap:8px; color:var(--text);">
        <span class="material-symbols-outlined" style="color: #ef4444;">warning</span> <?= t('category_delete_confirm_title') ?>
    </h3>
    <p id="cat-delete-dialog-message" style="font-size:0.9rem; line-height:1.5; margin-bottom:20px; color:#475569;"></p>
    <div class="dialog-buttons" style="display:flex; justify-content:flex-end; gap:8px; flex-wrap:wrap;">
        <button id="btn-cat-delete-cancel" class="btn btn-small" style="background:#f1f5f9; color:#1e293b; border: 1px solid #cbd5e1;"><?= t('btn_cancel') ?></button>
        <button id="btn-cat-delete-ok" class="btn btn-small btn-red"><?= t('btn_delete_confirm') ?></button>
    </div>
</dialog>

<!-- Native Dialog for media rename confirmation -->
<dialog id="rename-confirm-dialog" class="custom-dialog">
    <h3 style="margin-top:0; margin-bottom:12px; font-size:1.05rem; display:flex; align-items:center; gap:8px; color:var(--text);">
        <span class="material-symbols-outlined" style="color: #f59e0b;">warning</span> <?= t('media_rename_confirm_title') ?>
    </h3>
    <p id="rename-dialog-message" style="font-size:0.9rem; line-height:1.5; margin-bottom:20px; color:#475569;"></p>
    <div class="dialog-buttons" style="display:flex; justify-content:flex-end; gap:8px; flex-wrap:wrap;">
        <button id="btn-rename-cancel" class="btn btn-small" style="background:#f1f5f9; color:#1e293b; border: 1px solid #cbd5e1;"><?= t('btn_rename_cancel') ?></button>
        <button id="btn-rename-only" class="btn btn-small" style="background:#f1f5f9; color:#1e293b; border: 1px solid #cbd5e1;"><?= t('btn_rename_only') ?></button>
        <button id="btn-rename-update" class="btn btn-small btn-blue"><?= t('btn_rename_update') ?></button>
    </div>
</dialog>

<!-- Native Dialog for global confirmation -->
<dialog id="global-confirm-dialog" class="custom-dialog">
    <h3 style="margin-top:0; margin-bottom:12px; font-size:1.05rem; display:flex; align-items:center; gap:8px; color:var(--text);">
        <span class="material-symbols-outlined" style="color: #ef4444;">warning</span> <span id="global-confirm-title"><?= t('btn_delete') ?></span>
    </h3>
    <p id="global-confirm-message" style="font-size:0.9rem; line-height:1.5; margin-bottom:20px; color:#475569;"></p>
    <div class="dialog-buttons" style="display:flex; justify-content:flex-end; gap:8px; flex-wrap:wrap;">
        <button id="btn-global-confirm-cancel" class="btn btn-small" style="background:#f1f5f9; color:#1e293b; border: 1px solid #cbd5e1;"><?= t('btn_cancel') ?></button>
        <button id="btn-global-confirm-ok" class="btn btn-small btn-red"><?= t('btn_delete_confirm') ?></button>
    </div>
</dialog>


<div id="unsaved-modal" class="unsaved-modal-overlay" style="display: none;">
    <div class="unsaved-modal-content">
        <h3><?= t('modal_unsaved_title') ?></h3>
        <p><?= t('modal_unsaved_text') ?></p>
        <div class="unsaved-modal-actions">
            <button id="btn-modal-save" class="btn btn-blue"><?= getIcon('save') ?> <?= t('btn_save_and_close') ?></button>
            <button id="btn-modal-discard" class="btn btn-red"><?= getIcon('delete') ?> <?= t('btn_discard_and_close') ?></button>
            <button id="btn-modal-cancel" class="btn btn-gray"><?= t('btn_cancel') ?></button>
        </div>
    </div>
</div>

</body>
</html>
