<?php
// ==========================================
// mikanBox Device Authentication (lib/device-auth.php) — SQLite edition only
// ==========================================
// There are no passwords. A browser holds a random token in an HttpOnly cookie
// and the database keeps only its SHA-256, so a leaked database cannot sign
// anyone in. A device is added in one of two ways:
//   * setup token: a random value the owner writes to DATA_DIR/setup-token.txt
//     before uploading. It works once; it registers the first device, and a
//     new value is the recovery path when every device is lost.
//   * one-time code: issued on an already registered device, valid for
//     MIKANBOX_DEVICE_CODE_HOURS, used once.
// The PHP session only carries the result of the cookie check
// (see mikanBoxDeviceLogin), so revoking a device takes effect immediately.
// This mirrors mikanBox-cloud/src/devices.ts; keep behaviour and wording aligned.

const MIKANBOX_DEVICE_CODE_HOURS = 3;
// A device younger than this cannot stop other devices, so a stolen add code
// cannot be used to lock the original owner out before they notice.
const MIKANBOX_DEVICE_PROBATION_DAYS = 3;
const MIKANBOX_DEVICE_EXPIRY_CHOICES = [30, 90, 180];
const MIKANBOX_DEVICE_RENEW_SECONDS = 3600;
const MIKANBOX_DEVICE_COOKIE_DAYS = 400;
const MIKANBOX_SETUP_TOKEN_MIN_LENGTH = 20;
const MIKANBOX_ADOPT_WINDOW_SECONDS = 7200;
const MIKANBOX_DEVICE_CODE_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

// ---- Schema and small key/value store (kept out of the settings table so that
// ---- secrets never reach exports or the MCP get_settings tool) ----

function mikanBoxAuthMetaGet(SQLite3 $db, string $key): ?string {
    $stmt = $db->prepare('SELECT value FROM auth_meta WHERE key = :k');
    $stmt->bindValue(':k', $key, SQLITE3_TEXT);
    $res = $stmt->execute();
    $row = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
    return $row ? (string)$row['value'] : null;
}

function mikanBoxAuthMetaSet(SQLite3 $db, string $key, string $value): void {
    $stmt = $db->prepare('INSERT INTO auth_meta (key, value) VALUES (:k, :v) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $stmt->bindValue(':k', $key, SQLITE3_TEXT);
    $stmt->bindValue(':v', $value, SQLITE3_TEXT);
    $stmt->execute();
}

/** Store $value only if the key has no value yet; an existing value is never replaced. */
function mikanBoxAuthMetaInit(SQLite3 $db, string $key, string $value): void {
    $stmt = $db->prepare('INSERT OR IGNORE INTO auth_meta (key, value) VALUES (:k, :v)');
    $stmt->bindValue(':k', $key, SQLITE3_TEXT);
    $stmt->bindValue(':v', $value, SQLITE3_TEXT);
    $stmt->execute();
}

/** Replace the stored value with $value. True for exactly one caller per distinct value. */
function mikanBoxAuthMetaClaim(SQLite3 $db, string $key, string $value): bool {
    $insert = $db->prepare('INSERT OR IGNORE INTO auth_meta (key, value) VALUES (:k, :v)');
    $insert->bindValue(':k', $key, SQLITE3_TEXT);
    $insert->bindValue(':v', $value, SQLITE3_TEXT);
    $insert->execute();
    if ($db->changes() === 1) return true;
    $update = $db->prepare('UPDATE auth_meta SET value = :v WHERE key = :k AND value <> :v');
    $update->bindValue(':k', $key, SQLITE3_TEXT);
    $update->bindValue(':v', $value, SQLITE3_TEXT);
    $update->execute();
    return $db->changes() === 1;
}

function mikanBoxDeviceEnsureSchema(SQLite3 $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS device_sessions (
        id TEXT PRIMARY KEY,
        username TEXT NOT NULL,
        token_hash TEXT NOT NULL UNIQUE,
        name TEXT,
        created_at INTEGER,
        last_used_at INTEGER,
        expires_at INTEGER,
        seen_until INTEGER,
        user_agent TEXT,
        via TEXT,
        revoked_at INTEGER
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS device_codes (
        code_hash TEXT PRIMARY KEY,
        username TEXT NOT NULL,
        display_name TEXT,
        created_by TEXT,
        created_at INTEGER,
        expires_at INTEGER,
        used_at INTEGER
    )");
    $db->exec("CREATE TABLE IF NOT EXISTS auth_meta (key TEXT PRIMARY KEY, value TEXT)");

    // One-time switch from passwords: drop every password and security answer.
    // From here on the only way in is a registered device.
    if (mikanBoxAuthMetaGet($db, 'device_auth_v1') === null) {
        $upgrading = false;
        try {
            $upgrading = (int)$db->querySingle('SELECT COUNT(*) FROM users') > 0;
            $db->exec("UPDATE users SET password_hash = NULL, security_question = NULL, security_answer_hash = NULL");
            $db->exec("DELETE FROM settings WHERE key = 'password_hash'");
        } catch (Exception $e) {}
        // The browser that is signed in at the moment of the upgrade (typically the one that
        // ran the system update) may become the first device, for two hours, so an update
        // from the admin screen does not lock its owner out. Later, the setup token is the way in.
        if ($upgrading) mikanBoxAuthMetaSet($db, 'adopt_until', (string)(time() + MIKANBOX_ADOPT_WINDOW_SECONDS));
        mikanBoxAuthMetaSet($db, 'device_auth_v1', '1');
    }
    // Salt for preview tokens; created once, never replaced.
    mikanBoxAuthMetaInit($db, 'site_secret', bin2hex(random_bytes(32)));
    mikanBoxAccountsResetIfRequested($db);
}

/**
 * Hand over a site: an empty DATA_DIR/reset-accounts.txt removes every user,
 * device and code on the next request, so the new owner starts from the setup
 * screen with a setup token of their own. The file deletes itself.
 */
function mikanBoxAccountsResetIfRequested(SQLite3 $db): void {
    $file = DATA_DIR . '/reset-accounts.txt';
    if (!is_file($file)) return;
    foreach (['DELETE FROM users', 'DELETE FROM device_sessions', 'DELETE FROM device_codes', "DELETE FROM auth_meta WHERE key = 'setup_token_used'"] as $sql) {
        $db->exec($sql);
    }
    @unlink($file);
}

/** Per-site secret (created with the schema). Replaces the first user's password hash as the preview-token salt. */
function mikanBoxSiteSecret(): string {
    $db = getDb();
    return mikanBoxAuthMetaGet($db, 'site_secret') ?? '';
}

// ---- Cookie ----

function mikanBoxDeviceCookieName(): string {
    return 'mikanBoxDevice' . substr(hash('sha256', realpath(CORE_DIR) ?: CORE_DIR), 0, 32);
}

function mikanBoxDeviceSetCookie(string $value, int $expires): void {
    if (headers_sent()) return;
    $secure = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off');
    setcookie(mikanBoxDeviceCookieName(), $value, [
        'expires' => $expires, 'path' => '/', 'domain' => '',
        'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax',
    ]);
}

function mikanBoxDeviceClearCookie(): void {
    mikanBoxDeviceSetCookie('', 1);
}

function mikanBoxDeviceCookieToken(): string {
    $value = $_COOKIE[mikanBoxDeviceCookieName()] ?? '';
    return (is_string($value) && preg_match('/\A[A-Za-z0-9_-]{43}\z/', $value)) ? $value : '';
}

// ---- Devices ----

function mikanBoxDeviceExpiryDays(): int {
    $days = (int)(mikanBoxAuthMetaGet(getDb(), 'device_expiry_days') ?? 30);
    return in_array($days, MIKANBOX_DEVICE_EXPIRY_CHOICES, true) ? $days : 30;
}

function mikanBoxDeviceSetExpiryDays(int $days): bool {
    if (!in_array($days, MIKANBOX_DEVICE_EXPIRY_CHOICES, true)) return false;
    mikanBoxAuthMetaSet(getDb(), 'device_expiry_days', (string)$days);
    return true;
}

function mikanBoxDeviceName(string $name): string {
    $name = trim(preg_replace('/[\x00-\x1f\x7f]+/u', ' ', $name) ?? '');
    return mb_substr($name, 0, 60) ?: '—';
}

/** Register this browser as a device for $username and send its cookie. Returns the device id. */
function mikanBoxDeviceCreate(string $username, string $name, string $via): string {
    $db = getDb();
    $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    $id = bin2hex(random_bytes(16));
    $now = time();
    $expires = $now + mikanBoxDeviceExpiryDays() * 86400;
    $stmt = $db->prepare('INSERT INTO device_sessions (id, username, token_hash, name, created_at, last_used_at, expires_at, seen_until, user_agent, via)
        VALUES (:id, :u, :h, :n, :c, :c, :e, :c, :ua, :via)');
    $stmt->bindValue(':id', $id, SQLITE3_TEXT);
    $stmt->bindValue(':u', $username, SQLITE3_TEXT);
    $stmt->bindValue(':h', hash('sha256', $token), SQLITE3_TEXT);
    $stmt->bindValue(':n', mikanBoxDeviceName($name), SQLITE3_TEXT);
    $stmt->bindValue(':c', $now, SQLITE3_INTEGER);
    $stmt->bindValue(':e', $expires, SQLITE3_INTEGER);
    $stmt->bindValue(':ua', mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200), SQLITE3_TEXT);
    $stmt->bindValue(':via', $via, SQLITE3_TEXT);
    $stmt->execute();
    mikanBoxDeviceSetCookie($token, $now + MIKANBOX_DEVICE_COOKIE_DAYS * 86400);
    $_COOKIE[mikanBoxDeviceCookieName()] = $token; // this request already holds the new device
    return $id;
}

/**
 * The registered device behind this request's cookie, or null.
 * Renews it while in use. Cached for the request.
 */
function mikanBoxCurrentDevice(bool $refresh = false): ?array {
    static $cache = false;
    if ($refresh) $cache = false;
    if ($cache !== false) return $cache;
    $token = mikanBoxDeviceCookieToken();
    if ($token === '') return $cache = null;
    $db = getDb();
    $now = time();
    $stmt = $db->prepare('SELECT d.*, u.display_name AS display_name FROM device_sessions d
        JOIN users u ON u.username = d.username
        WHERE d.token_hash = :h AND d.revoked_at IS NULL AND d.expires_at > :n');
    $stmt->bindValue(':h', hash('sha256', $token), SQLITE3_TEXT);
    $stmt->bindValue(':n', $now, SQLITE3_INTEGER);
    $res = $stmt->execute();
    $device = $res ? $res->fetchArray(SQLITE3_ASSOC) : false;
    if (!$device) return $cache = null;
    if ($now - (int)$device['last_used_at'] > MIKANBOX_DEVICE_RENEW_SECONDS) {
        $renew = $db->prepare('UPDATE device_sessions SET last_used_at = :n, expires_at = :e WHERE id = :id AND revoked_at IS NULL');
        $renew->bindValue(':n', $now, SQLITE3_INTEGER);
        $renew->bindValue(':e', $now + mikanBoxDeviceExpiryDays() * 86400, SQLITE3_INTEGER);
        $renew->bindValue(':id', $device['id'], SQLITE3_TEXT);
        $renew->execute();
    }
    return $cache = $device;
}

/**
 * Derive the PHP session's sign-in state from the device cookie. Without a
 * valid registered device the session is signed out, whatever it held before.
 */
function mikanBoxDeviceLogin(): ?array {
    $device = mikanBoxCurrentDevice();
    if ($device === null) {
        mikanBoxDeviceRememberAdoptable();
        if (defined('MIKANBOX_ADMIN_REQUEST') && mikanBoxDeviceAdoptSession()) $device = mikanBoxCurrentDevice(true);
    }
    if ($device === null) {
        mikanBoxClearAuthentication();
        return null;
    }
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = $device['username'];
    $_SESSION['admin_display_name'] = $device['display_name'] ?? $device['username'];
    return $device;
}

/** True while the upgrade window is open and no device exists yet. */
function mikanBoxDeviceAdoptWindowOpen(): bool {
    return time() < (int)(mikanBoxAuthMetaGet(getDb(), 'adopt_until') ?? 0) && mikanBoxDeviceActiveCount() === 0;
}

/**
 * Upgrade from passwords. A session that was signed in before the upgrade is
 * noted here, because a public-page request would otherwise sign it out before
 * the owner reaches the admin screen. Only the note, never the sign-in, survives.
 */
function mikanBoxDeviceRememberAdoptable(): void {
    if (($_SESSION['admin_logged_in'] ?? false) === true && mikanBoxDeviceAdoptWindowOpen()) {
        $_SESSION['device_adoptable_user'] = (string)($_SESSION['admin_username'] ?? '');
    } elseif (!mikanBoxDeviceAdoptWindowOpen()) {
        unset($_SESSION['device_adoptable_user']);
    }
}

/**
 * While the two-hour window is open and no device exists yet, a browser whose
 * session was signed in before the upgrade becomes the first device when it
 * reaches the admin screen. Returns true when it did.
 */
function mikanBoxDeviceAdoptSession(): bool {
    $username = (string)($_SESSION['device_adoptable_user'] ?? '');
    if ($username === '' || !mikanBoxDeviceAdoptWindowOpen()) return false;
    $db = getDb();
    $stmt = $db->prepare('SELECT username FROM users WHERE username = :u');
    $stmt->bindValue(':u', $username, SQLITE3_TEXT);
    $user = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$user) return false;
    mikanBoxDeviceCreate($user['username'], t('device_adopted_name'), 'setup');
    mikanBoxAuthMetaSet($db, 'adopt_until', '0');
    unset($_SESSION['device_adoptable_user']);
    $_SESSION['device_adopted_notice'] = true; // shown as a banner until dismissed (see admin.php)
    return true;
}

/** Register this browser and sign the session in (call right after mikanBoxDeviceCreate). */
function mikanBoxDeviceSignIn(string $username, string $displayName): void {
    mikanBoxRenewAuthentication();
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = $username;
    $_SESSION['admin_display_name'] = $displayName;
}

function mikanBoxDeviceOnProbation(array $device): bool {
    return ($device['via'] ?? '') !== 'setup'
        && (time() - (int)$device['created_at']) < MIKANBOX_DEVICE_PROBATION_DAYS * 86400;
}

function mikanBoxDeviceList(): array {
    $stmt = getDb()->prepare('SELECT * FROM device_sessions WHERE revoked_at IS NULL AND expires_at > :n ORDER BY created_at');
    $stmt->bindValue(':n', time(), SQLITE3_INTEGER);
    $res = $stmt->execute();
    $rows = [];
    while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) $rows[] = $row;
    return $rows;
}

function mikanBoxDeviceActiveCount(): int {
    return count(mikanBoxDeviceList());
}

/** Stop a device. Throws a translated message when a new device tries to stop another. */
function mikanBoxDeviceRevoke(array $actor, string $id): void {
    if ($id !== $actor['id'] && mikanBoxDeviceOnProbation($actor)) {
        throw new RuntimeException(t('err_device_probation', MIKANBOX_DEVICE_PROBATION_DAYS));
    }
    $stmt = getDb()->prepare('UPDATE device_sessions SET revoked_at = :n WHERE id = :id AND revoked_at IS NULL');
    $stmt->bindValue(':n', time(), SQLITE3_INTEGER);
    $stmt->bindValue(':id', $id, SQLITE3_TEXT);
    $stmt->execute();
}

/** Devices registered by someone else since this device last looked; marks them seen. */
function mikanBoxDeviceUnseen(array $device): array {
    $db = getDb();
    $stmt = $db->prepare('SELECT * FROM device_sessions WHERE created_at > :seen AND id <> :id AND revoked_at IS NULL ORDER BY created_at');
    $stmt->bindValue(':seen', (int)$device['seen_until'], SQLITE3_INTEGER);
    $stmt->bindValue(':id', $device['id'], SQLITE3_TEXT);
    $res = $stmt->execute();
    $rows = [];
    while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) $rows[] = $row;
    if ($rows) {
        $mark = $db->prepare('UPDATE device_sessions SET seen_until = :n WHERE id = :id');
        $mark->bindValue(':n', time(), SQLITE3_INTEGER);
        $mark->bindValue(':id', $device['id'], SQLITE3_TEXT);
        $mark->execute();
    }
    return $rows;
}

/** A user was deleted or renamed: their devices and unused codes follow. */
function mikanBoxDeviceForgetUser(string $username): void {
    $db = getDb();
    foreach (['DELETE FROM device_sessions WHERE username = :u', 'DELETE FROM device_codes WHERE username = :u'] as $sql) {
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':u', $username, SQLITE3_TEXT);
        $stmt->execute();
    }
}

function mikanBoxDeviceRenameUser(string $old, string $new): void {
    $db = getDb();
    foreach (['UPDATE device_sessions SET username = :new WHERE username = :old', 'UPDATE device_codes SET username = :new WHERE username = :old'] as $sql) {
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':new', $new, SQLITE3_TEXT);
        $stmt->bindValue(':old', $old, SQLITE3_TEXT);
        $stmt->execute();
    }
}

// ---- One-time codes: add a device, or invite a user ----

function mikanBoxDeviceNormalizeCode(string $code): string {
    return strtr(preg_replace('/[^0-9A-Z]/', '', strtoupper($code)) ?? '', ['O' => '0', 'I' => '1', 'L' => '1']);
}

/** Issue a code. With $invite the code creates that user; otherwise it adds a device for the issuer. */
function mikanBoxDeviceIssueCode(array $creator, ?array $invite = null): string {
    $db = getDb();
    if ($invite !== null) {
        $invite['username'] = trim($invite['username'] ?? '');
        $invite['display_name'] = trim($invite['display_name'] ?? '');
        if (!preg_match('/\A[A-Za-z0-9_-]{1,80}\z/', $invite['username'])) throw new RuntimeException(t('err_username_chars'));
        if ($invite['display_name'] === '' || mb_strlen($invite['display_name']) > 120) throw new RuntimeException(t('err_display_name_required'));
        $exists = $db->prepare('SELECT 1 FROM users WHERE username = :u');
        $exists->bindValue(':u', $invite['username'], SQLITE3_TEXT);
        if ($exists->execute()->fetchArray()) throw new RuntimeException(t('err_username_exists'));
    }
    $code = '';
    for ($i = 0; $i < 10; $i++) $code .= MIKANBOX_DEVICE_CODE_ALPHABET[random_int(0, 31)];
    $now = time();
    $stmt = $db->prepare('INSERT INTO device_codes (code_hash, username, display_name, created_by, created_at, expires_at)
        VALUES (:h, :u, :d, :by, :c, :e)');
    $stmt->bindValue(':h', hash('sha256', $code), SQLITE3_TEXT);
    $stmt->bindValue(':u', $invite['username'] ?? $creator['username'], SQLITE3_TEXT);
    $stmt->bindValue(':d', $invite['display_name'] ?? '', SQLITE3_TEXT);
    $stmt->bindValue(':by', $creator['id'], SQLITE3_TEXT);
    $stmt->bindValue(':c', $now, SQLITE3_INTEGER);
    $stmt->bindValue(':e', $now + MIKANBOX_DEVICE_CODE_HOURS * 3600, SQLITE3_INTEGER);
    $stmt->execute();
    return substr($code, 0, 5) . '-' . substr($code, 5);
}

/** Use a code once and register this browser. Returns the signed-in user, or null for a bad, used or expired code. */
function mikanBoxDeviceClaimCode(string $code, string $deviceName): ?array {
    $db = getDb();
    $hash = hash('sha256', mikanBoxDeviceNormalizeCode($code));
    $now = time();
    $find = $db->prepare('SELECT * FROM device_codes WHERE code_hash = :h AND used_at IS NULL AND expires_at > :n');
    $find->bindValue(':h', $hash, SQLITE3_TEXT);
    $find->bindValue(':n', $now, SQLITE3_INTEGER);
    $row = $find->execute()->fetchArray(SQLITE3_ASSOC);
    if (!$row) return null;
    $use = $db->prepare('UPDATE device_codes SET used_at = :n WHERE code_hash = :h AND used_at IS NULL');
    $use->bindValue(':n', $now, SQLITE3_INTEGER);
    $use->bindValue(':h', $hash, SQLITE3_TEXT);
    $use->execute();
    if ($db->changes() !== 1) return null;
    $displayName = (string)$row['display_name'];
    if ($displayName !== '') {
        // An invite must create its own user; never attach a device to an account that appeared meanwhile.
        $add = $db->prepare('INSERT OR IGNORE INTO users (username, display_name) VALUES (:u, :d)');
        $add->bindValue(':u', $row['username'], SQLITE3_TEXT);
        $add->bindValue(':d', $displayName, SQLITE3_TEXT);
        $add->execute();
        if ($db->changes() !== 1) return null;
    } else {
        $who = $db->prepare('SELECT display_name FROM users WHERE username = :u');
        $who->bindValue(':u', $row['username'], SQLITE3_TEXT);
        $user = $who->execute()->fetchArray(SQLITE3_ASSOC);
        if (!$user) return null;
        $displayName = (string)$user['display_name'];
    }
    mikanBoxDeviceCreate($row['username'], $deviceName, 'code');
    return ['username' => $row['username'], 'display_name' => $displayName];
}

function mikanBoxDeviceCodes(): array {
    $stmt = getDb()->prepare('SELECT * FROM device_codes WHERE used_at IS NULL AND expires_at > :n ORDER BY created_at');
    $stmt->bindValue(':n', time(), SQLITE3_INTEGER);
    $res = $stmt->execute();
    $rows = [];
    while ($res && ($row = $res->fetchArray(SQLITE3_ASSOC))) $rows[] = $row;
    return $rows;
}

function mikanBoxDeviceCancelCode(string $codeHash): void {
    $stmt = getDb()->prepare('DELETE FROM device_codes WHERE code_hash = :h AND used_at IS NULL');
    $stmt->bindValue(':h', $codeHash, SQLITE3_TEXT);
    $stmt->execute();
}

// ---- Setup token: first device and recovery ----

function mikanBoxSetupTokenFile(): string {
    return DATA_DIR . '/setup-token.txt';
}

/** First non-empty line of the setup token file, or ''. */
function mikanBoxSetupToken(): string {
    $file = mikanBoxSetupTokenFile();
    if (!is_file($file) || !is_readable($file)) return '';
    foreach (preg_split('/\R/', (string)file_get_contents($file)) ?: [] as $line) {
        if (trim($line) !== '') return trim($line);
    }
    return '';
}

/** 'missing' | 'weak' | 'used' | 'ready' */
function mikanBoxSetupTokenState(): string {
    $token = mikanBoxSetupToken();
    if ($token === '') return 'missing';
    if (strlen($token) < MIKANBOX_SETUP_TOKEN_MIN_LENGTH) return 'weak';
    $used = mikanBoxAuthMetaGet(getDb(), 'setup_token_used') ?? '';
    return hash_equals($used, hash('sha256', $token)) ? 'used' : 'ready';
}

function mikanBoxSetupTokenMatches(string $input): bool {
    return mikanBoxSetupTokenState() === 'ready' && hash_equals(mikanBoxSetupToken(), trim($input));
}

/**
 * Spend the current setup token (each value works once). With registered
 * devices this is recovery: every device, unused code, edit lock and the MCP
 * key are cleared. Returns false when another request spent it first.
 */
function mikanBoxSetupTokenConsume(): bool {
    $db = getDb();
    $recovery = mikanBoxDeviceActiveCount() > 0;
    if (!mikanBoxAuthMetaClaim($db, 'setup_token_used', hash('sha256', mikanBoxSetupToken()))) return false;
    if ($recovery) {
        $stmt = $db->prepare('UPDATE device_sessions SET revoked_at = :n WHERE revoked_at IS NULL');
        $stmt->bindValue(':n', time(), SQLITE3_INTEGER);
        $stmt->execute();
        $db->exec('DELETE FROM device_codes');
        $db->exec('DELETE FROM locks');
        $settings = loadSettings();
        if (!empty($settings['mcp_api_key'])) {
            $settings['mcp_api_key'] = '';
            saveSettings($settings);
        }
    }
    return true;
}

/** Page that explains the install change; shown when no setup token is available. */
function mikanBoxAuthGuideUrl(): string {
    return t('url_device_auth_guide');
}
