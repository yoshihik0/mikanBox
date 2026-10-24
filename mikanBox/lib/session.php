<?php
// Authentication belongs to one installation, never to the whole hostname.
function mikanBoxStartSession(): void {
    $site = hash('sha256', realpath(CORE_DIR) ?: CORE_DIR);
    $name = 'mikanBox' . substr($site, 0, 32);
    if (session_status() === PHP_SESSION_ACTIVE && session_name() !== $name) {
        // Do not import or destroy another application's (or legacy) session.
        session_write_close();
        $_SESSION = [];
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_name($name);
        $cookieId = $_COOKIE[$name] ?? '';
        session_id(is_string($cookieId) && preg_match('/\A[a-zA-Z0-9,-]{1,256}\z/', $cookieId) ? $cookieId : '');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        $secure = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off');
        session_set_cookie_params([
            'lifetime' => 0, 'path' => '/', 'domain' => '',
            'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax',
        ]);
        if (!session_start()) throw new RuntimeException('Unable to start mikanBox session');
    }
    // PHP's file storage is keyed by ID, not cookie name. A copied ID must not
    // authenticate another installation even when both use the same save_path.
    if (($_SESSION['mikanbox_session_site'] ?? null) !== $site) {
        // Rotate before clearing: PHP writes the old data during regeneration.
        // Never clear or delete another installation's session in shared storage.
        if (!session_regenerate_id(false)) throw new RuntimeException('Unable to renew mikanBox session');
        $_SESSION = [];
        $_SESSION['mikanbox_session_site'] = $site;
    }
}

function mikanBoxClearAuthentication(): void {
    unset($_SESSION['admin_logged_in'], $_SESSION['admin_username'], $_SESSION['admin_display_name']);
}

function mikanBoxRenewAuthentication(): void {
    if (!session_regenerate_id(true)) throw new RuntimeException('Unable to renew mikanBox session');
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// A stable, human-readable username hint for flat's password-only account.
// This is not a credential and never grants permission.
function mikanBoxPasswordAccount(): string {
    return rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/mikanBox/admin.php')), '/') . '/admin.php';
}
