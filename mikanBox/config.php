<?php
// ==========================================
// mikanBox Basic Settings
// ==========================================
define('MIKANBOX_VERSION', '1.0');
if (!defined('CORE_DIR')) define('CORE_DIR', __DIR__);

// Directory path settings (supports folder renaming by using __DIR__ as the base)
define('DATA_DIR', __DIR__ . '/data');
define('POSTS_DIR', DATA_DIR . '/posts');
define('COMPONENTS_DIR', DATA_DIR . '/components');
define('MEDIA_DIR', dirname(__DIR__) . '/media');
define('SETTINGS_FILE', DATA_DIR . '/settings.json');

// --- Security: Auto-generate .htaccess to block direct access ---
function secureDirectory($dirPath) {
    if (!is_dir($dirPath)) {
        @mkdir($dirPath, 0777, true);
    }
    $htaccessPath = $dirPath . '/.htaccess';
    if (!file_exists($htaccessPath)) {
        $content = "Order deny,allow\nDeny from all\n";
        @file_put_contents($htaccessPath, $content);
    }
}
// Protect the data directory
secureDirectory(DATA_DIR);
// -----------------------------------------------------------------
// Timezone settings
date_default_timezone_set('Asia/Tokyo');

// Start session (if not already started)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Centralized loading of site settings (shared across all files)
$GLOBALS['mikanbox_settings'] = [];
if (file_exists(SETTINGS_FILE)) {
    $GLOBALS['mikanbox_settings'] = json_decode(file_get_contents(SETTINGS_FILE), true) ?: [];
}
define('SITE_NAME', $GLOBALS['mikanbox_settings']['site_name'] ?? 'mikanBox');

// CSRF token generation and verification
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';
}
