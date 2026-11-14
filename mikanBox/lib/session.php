<?php
if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(403); exit; } // direct request guard
// A sign-in lasts this long without activity (24 hours). One constant, used for PHP's
// garbage-collection threshold; the cookie itself stays a browser-session cookie.
if (!defined('MIKANBOX_SESSION_IDLE_SECONDS')) define('MIKANBOX_SESSION_IDLE_SECONDS', 86400);

/**
 * Keep this installation's sessions in its own protected folder (DATA_DIR/sessions) instead
 * of the host's shared save_path, where another application's garbage collection or a
 * system cron job can delete them after PHP's default 24 minutes. Returns the folder, or
 * null when it cannot be prepared (PHP then keeps its default save_path).
 */
function mikanBoxSessionDirectory(): ?string {
    if (!defined('DATA_DIR') || !function_exists('mikanBoxProtectDataDirectory')) return null;
    $dir = DATA_DIR . '/sessions';
    if (!mikanBoxProtectDataDirectory($dir)) return null;
    @chmod($dir, 0700);
    return (is_dir($dir) && is_writable($dir)) ? $dir : null;
}

/**
 * Anonymous visitors of public pages need no session: nothing public reads or writes one
 * (the renderer only asks "is an admin signed in?"). A session is started for the screens that
 * sign in or work with the session (admin, visual, build, convert), for the command line, and
 * for any browser that already holds this installation's session cookie or (SQLite edition) its
 * device cookie, so a signed-in admin still sees drafts and the toolbar on public pages.
 */
function mikanBoxSessionWanted(string $sessionName, string $site): bool {
    if (PHP_SAPI === 'cli') return true;
    if (in_array(basename((string)($_SERVER['SCRIPT_FILENAME'] ?? '')), ['admin.php', 'visual.php', 'build.php', 'convert.php'], true)) return true;
    return isset($_COOKIE[$sessionName]) || isset($_COOKIE['mikanBoxDevice' . substr($site, 0, 32)]);
}

// Authentication belongs to one installation, never to the whole hostname.
function mikanBoxStartSession(): void {
    $site = hash('sha256', realpath(CORE_DIR) ?: CORE_DIR);
    $name = 'mikanBox' . substr($site, 0, 32);
    if (session_status() === PHP_SESSION_ACTIVE && session_name() !== $name) {
        // Do not import or destroy another application's (or legacy) session.
        session_write_close();
        $_SESSION = [];
    }
    if (session_status() === PHP_SESSION_NONE && !mikanBoxSessionWanted($name, $site)) {
        $_SESSION = [];   // anonymous public request: no session file, no cookie
        return;
    }
    if (session_status() === PHP_SESSION_NONE) {
        session_name($name);
        $cookieId = $_COOKIE[$name] ?? '';
        session_id(is_string($cookieId) && preg_match('/\A[a-zA-Z0-9,-]{1,256}\z/', $cookieId) ? $cookieId : '');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string)MIKANBOX_SESSION_IDLE_SECONDS);
        $sessionDir = mikanBoxSessionDirectory();
        if ($sessionDir !== null) {
            ini_set('session.save_path', $sessionDir);
            // The folder is ours alone, so collect expired files here even where the host
            // turns PHP's own collection off (Debian/Ubuntu defaults).
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '100');
        }
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
        // A brand-new (empty) session only needs the binding. A session that already holds data
        // without this installation's binding came from elsewhere (a copied ID): rotate the ID
        // before clearing, because PHP writes the old data during regeneration, and never clear
        // or delete another installation's session in shared storage.
        if (!empty($_SESSION)) {
            if (!session_regenerate_id(false)) throw new RuntimeException('Unable to renew mikanBox session');
            $_SESSION = [];
        }
        $_SESSION['mikanbox_session_site'] = $site;
    }
}

function mikanBoxClearAuthentication(): void {
    unset($_SESSION['admin_logged_in'], $_SESSION['admin_username'], $_SESSION['admin_display_name'], $_SESSION['mikanbox_password_fingerprint']);
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
