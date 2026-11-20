<?php
if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(403); exit; } // direct request guard
const MIKANBOX_SECURITY_GUARD = "<?php http_response_code(404); exit; ?>\n";

/**
 * True only when PHP is running its built-in development server (`php -S`) AND the request comes
 * from this computer itself: the connection is a loopback address AND the browser addressed it as
 * localhost. The SAPI condition matters because a reverse proxy (for example nginx with a plain
 * `proxy_pass http://127.0.0.1:8000;`) also connects from loopback, sends a localhost-looking Host
 * and may add no forwarding headers, so an Internet visitor would look local. Production web SAPIs
 * (apache2handler, fpm-fcgi, cgi-fcgi, litespeed) never report `cli-server`, so behind any real
 * web server the setup token is always required. Tunnels also carry the public host name or
 * forwarding headers and are rejected by the header and Host checks.
 */
function mikanBoxIsLocalRequest(): bool {
    if (PHP_SAPI !== 'cli-server') return false;
    if (!in_array((string)($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true)) return false;
    foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED_HOST', 'HTTP_FORWARDED', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'HTTP_CF_CONNECTING_IP'] as $header) {
        if (!empty($_SERVER[$header])) return false;
    }
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
    $host = preg_match('/^\[([^\]]+)\](?::\d+)?$/', $host, $m) ? $m[1] : (string)preg_replace('/:\d+$/', '', $host);
    return $host === 'localhost' || $host === '127.0.0.1' || $host === '::1' || str_ends_with($host, '.localhost');
}
/** True when a request reached the site over HTTPS (directly, on port 443, or through a proxy that says so). */
function mikanBoxRequestIsHttps(?array $server = null): bool {
    $server ??= $_SERVER;
    $https = strtolower((string)($server['HTTPS'] ?? ''));
    if ($https !== '' && $https !== 'off') return true;
    if ((string)($server['SERVER_PORT'] ?? '') === '443') return true;
    if (strtolower((string)($server['REQUEST_SCHEME'] ?? '')) === 'https') return true;
    $forwarded = strtolower(trim(explode(',', (string)($server['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
    return $forwarded === 'https';
}

/**
 * Headers for every admin screen (admin.php and its sign-in and setup screens):
 * no framing by other sites (visual editing frames its own pages, hence SAMEORIGIN), no MIME
 * sniffing, and no admin URL in the Referer sent to other sites. visual.php sends its own
 * stricter set. A later Content-Security-Policy (the sandboxed preview) adds frame-ancestors itself.
 */
function mikanBoxSendAdminHeaders(): void {
    if (headers_sent() || PHP_SAPI === 'cli') return;
    header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: frame-ancestors 'self'");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

/** Public (dynamic) pages: only no sniffing and a cross-origin-safe Referer. No framing rules, no CSP. */
/** Sign-in forms take text fields only: an array (name[]=x) becomes '' instead of a type error that names server paths. */
function mikanBoxScalarPost(): void {
    foreach ($_POST as $key => $value) if (!is_string($value)) $_POST[$key] = '';
}
function mikanBoxSendPublicHeaders(): void {
    if (headers_sent() || PHP_SAPI === 'cli') return;
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

/** The browser addressed this computer itself (localhost, 127.0.0.1, [::1] or *.localhost). */
function mikanBoxRequestHostIsLocal(?array $server = null): bool {
    $server ??= $_SERVER;
    $host = strtolower(trim((string)($server['HTTP_HOST'] ?? '')));
    $host = preg_match('/^\[([^\]]+)\](?::\d+)?$/', $host, $m) ? $m[1] : (string)preg_replace('/:\d+$/', '', $host);
    return $host === 'localhost' || $host === '127.0.0.1' || $host === '::1' || str_ends_with($host, '.localhost');
}

/**
 * Where a plain-HTTP request to a sign-in screen should go instead, or null to show it as it is.
 * Not redirected:
 * - PHP's built-in server (`php -S`) and requests addressed to localhost / 127.0.0.1 / [::1];
 * - anything but GET and HEAD (a POST is never redirected or refused);
 * - a request that already carries the marker ?https=1 yet still looks like HTTP. A proxy that
 *   ends TLS without telling PHP (no X-Forwarded-Proto) makes every request look like HTTP;
 *   the marker stops the redirect after one round, so the screen opens as before, never a loop;
 * - a Host with a port other than 80 (the HTTPS port is unknown) or with unexpected characters;
 * - sites that set define('MIKANBOX_HTTPS_REDIRECT', false); in local-config.php (servers
 *   without HTTPS).
 */
function mikanBoxHttpsRedirectTarget(array $server, array $get, string $sapi, bool $enabled = true): ?string {
    if (!$enabled || $sapi === 'cli' || $sapi === 'cli-server') return null;
    $method = strtoupper((string)($server['REQUEST_METHOD'] ?? 'GET'));
    if ($method !== 'GET' && $method !== 'HEAD') return null;
    if (mikanBoxRequestIsHttps($server) || mikanBoxRequestHostIsLocal($server)) return null;
    if (array_key_exists('https', $get)) return null;
    $host = (string)($server['HTTP_HOST'] ?? '');
    if (!preg_match('/\A(?:[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?|\[[0-9A-Fa-f:.]+\])(?::(\d+))?\z/', $host, $m)) return null;
    if (isset($m[1]) && $m[1] !== '' && $m[1] !== '80') return null;
    $host = (string)preg_replace('/:80\z/', '', $host);
    $uri = (string)($server['REQUEST_URI'] ?? '/');
    if ($uri === '' || $uri[0] !== '/' || str_starts_with($uri, '//') || preg_match('/[\x00-\x20\x7f\\\\]/', $uri)) return null;
    return 'https://' . $host . $uri . (str_contains($uri, '?') ? '&' : '?') . 'https=1';
}

/**
 * Screens that sign in or carry the sign-in cookie (admin.php, visual.php) are not used over
 * plain HTTP: a GET or HEAD request goes to the same address on https:// (302); see
 * mikanBoxHttpsRedirectTarget() for what stays as it is. No HSTS header is sent: it would also
 * bind other sites on the same host name.
 */
function mikanBoxRedirectToHttpsIfNeeded(): void {
    $enabled = !(defined('MIKANBOX_HTTPS_REDIRECT') && MIKANBOX_HTTPS_REDIRECT === false);
    $target = mikanBoxHttpsRedirectTarget($_SERVER, $_GET, PHP_SAPI, $enabled);
    if ($target === null || headers_sent()) return;
    while (ob_get_level() > 0) ob_end_clean();
    header('Cache-Control: no-store');
    header('Location: ' . $target, true, 302);
    exit;
}

// Security metadata is kept apart from settings so usage updates cannot restore an old key.
function mikanBoxSecurityDirectory(): ?string {
    $dir = DATA_DIR . '/security';
    if (!function_exists("mikanBoxProtectDataDirectory") || !mikanBoxProtectDataDirectory($dir)) return null;
    @chmod($dir, 0700);
    return $dir;
}
function mikanBoxSecurityLocked(callable $callback) {
    $dir = mikanBoxSecurityDirectory();
    if ($dir === null) return null;
    $lock = @fopen($dir . '/security-lock.php', 'c');
    if (!$lock) return null;
    @chmod($dir . '/security-lock.php', 0600);
    if (!flock($lock, LOCK_EX)) { fclose($lock); return null; }
    if (fstat($lock)['size'] === 0) { fwrite($lock, MIKANBOX_SECURITY_GUARD); fflush($lock); }
    try { return $callback($dir); } finally { flock($lock, LOCK_UN); fclose($lock); }
}
function mikanBoxSecurityReadState(string $dir): ?array {
    $path = $dir . '/mcp.json.php';
    if (!file_exists($path)) return [];
    $raw = (string)@file_get_contents($path);
    if (!str_starts_with($raw, MIKANBOX_SECURITY_GUARD)) return null;
    $data = json_decode(substr($raw, strlen(MIKANBOX_SECURITY_GUARD)), true);
    return is_array($data) ? $data : null;
}
function mikanBoxSecurityWriteState(string $dir, array $state): bool {
    $temp = tempnam($dir, '.state-');
    if ($temp === false) return false;
    if (!@rename($temp, $temp . '.php')) { @unlink($temp); return false; }
    $temp .= '.php';
    @chmod($temp, 0600);
    $ok = @file_put_contents($temp, MIKANBOX_SECURITY_GUARD . json_encode($state, JSON_UNESCAPED_UNICODE)) !== false && @rename($temp, $dir . '/mcp.json.php');
    if (!$ok) @unlink($temp);
    return $ok;
}
// ---- Stateless CSRF tokens for the screens shown before anybody has signed in ----
// A session file per visit of the sign-in screen would let a crawler fill the host's disk, so
// the forms there carry <issued-at>.<nonce>.<HMAC-SHA256(purpose|issued-at|nonce)> instead. The key is
// a random per-installation secret kept in the protected security folder (never in settings,
// backups or exports). Without a usable secret the callers fall back to the session token.
const MIKANBOX_STATELESS_CSRF_SECONDS = 7200;
function mikanBoxCsrfSecret(): ?string {
    static $cache = null;
    if ($cache !== null) return $cache === '' ? null : $cache;
    $secret = mikanBoxSecurityLocked(function($dir) {
        $path = $dir . '/csrf-secret.php';
        $read = static function() use ($path): ?string {
            if (!is_file($path)) return null;
            $raw = (string)@file_get_contents($path);
            if (!str_starts_with($raw, MIKANBOX_SECURITY_GUARD)) return null;
            $hex = trim(substr($raw, strlen(MIKANBOX_SECURITY_GUARD)));
            return preg_match('/\A[0-9a-f]{64}\z/', $hex) ? $hex : null;
        };
        $current = $read();
        if ($current !== null) return $current;
        $hex = bin2hex(random_bytes(32));
        $temp = tempnam($dir, '.csrf-');
        if ($temp === false) return null;
        if (!@rename($temp, $temp . '.php')) { @unlink($temp); return null; }
        $temp .= '.php';
        @chmod($temp, 0600);
        if (@file_put_contents($temp, MIKANBOX_SECURITY_GUARD . $hex . "\n") === false || !@rename($temp, $path)) { @unlink($temp); return null; }
        return $hex;
    });
    $cache = is_string($secret) ? $secret : '';
    return $cache === '' ? null : $cache;
}
function mikanBoxStatelessCsrfMac(string $secretHex, string $purpose, int $issuedAt, string $nonce): string {
    return hash_hmac('sha256', $purpose . '|' . $issuedAt . '|' . $nonce, (string)hex2bin($secretHex));
}
function mikanBoxStatelessCsrfIssue(string $purpose = 'login', ?int $now = null): ?string {
    $secret = mikanBoxCsrfSecret();
    if ($secret === null) return null;
    $now ??= time();
    $nonce = bin2hex(random_bytes(8));
    return $now . '.' . $nonce . '.' . mikanBoxStatelessCsrfMac($secret, $purpose, $now, $nonce);
}
function mikanBoxStatelessCsrfVerify($token, string $purpose = 'login', ?int $now = null): bool {
    if (!is_string($token) || !preg_match('/\A(\d{1,12})\.([0-9a-f]{16})\.([0-9a-f]{64})\z/', $token, $m)) return false;
    $secret = mikanBoxCsrfSecret();
    if ($secret === null) return false;
    $now ??= time();
    $issued = (int)$m[1];
    if ($issued > $now + 60 || $now - $issued > MIKANBOX_STATELESS_CSRF_SECONDS) return false;
    return hash_equals(mikanBoxStatelessCsrfMac($secret, $purpose, $issued, $m[2]), $m[3]);
}
/** Token for a pre-sign-in form: stateless, or (no secret storage) the session's, which starts the session. */
function mikanBoxPreAuthCsrfToken(): string {
    $token = mikanBoxStatelessCsrfIssue('login');
    if ($token !== null) return $token;
    mikanBoxEnsureSession();
    $_SESSION['mikanbox_form_session'] = true;   // this session carries the sign-in form's token: keep it
    return generateCsrfToken();
}
function mikanBoxPreAuthCsrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(mikanBoxPreAuthCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}
function mikanBoxPreAuthCsrfVerify($token): bool {
    if (!is_string($token) || $token === '') return false;
    if (mikanBoxStatelessCsrfVerify($token, 'login')) return true;
    return session_status() === PHP_SESSION_ACTIVE && verifyCsrfToken($token);   // forms issued by the session fallback
}
/**
 * PHP's built-in server started without index.php as the router serves every real file as is,
 * the data folder included. index.php marks routed requests; the desktop app has its own router.
 */
function mikanBoxCliServerWithoutRouter(): bool {
    return PHP_SAPI === 'cli-server' && !defined('MIKANBOX_CLI_ROUTER') && getenv('MIKANBOX_DESKTOP_READY') === false;
}
/**
 * Data folder protection check (2.21.3). The data folder is protected by .htaccess; on a server
 * where that has no effect (nginx without matching rules, AllowOverride None) its files could be
 * read from a browser without anyone noticing. The admin screen therefore asks, from the
 * browser, for one harmless fixed file inside the data folder: a protected folder answers
 * 403/404, and if the file comes back the screen shows a warning.
 * Returns the address of that file relative to admin.php, or null when there is nothing to
 * check (data folder outside the site folder, file cannot be written, or the built-in server
 * without a router, which has its own warning).
 */
const MIKANBOX_DATA_PROBE_FILE = 'protection-check.txt';
const MIKANBOX_DATA_PROBE_TEXT = "mikanBox data protection check\n";
function mikanBoxDataProtectionProbe(): ?string {
    if (!defined('DATA_DIR') || !defined('CORE_DIR') || mikanBoxCliServerWithoutRouter()) return null;
    $data = realpath(DATA_DIR);
    $root = realpath(dirname(CORE_DIR));
    if ($data === false || $root === false || dirname($data) !== $root) return null;
    $file = $data . '/' . MIKANBOX_DATA_PROBE_FILE;
    if (is_link($file)) return null;
    if (!is_file($file) && @file_put_contents($file, MIKANBOX_DATA_PROBE_TEXT) === false) return null;
    if ((string)@file_get_contents($file) !== MIKANBOX_DATA_PROBE_TEXT) return null;
    return '../' . rawurlencode(basename($data)) . '/' . MIKANBOX_DATA_PROBE_FILE;
}
function mikanBoxMcpEnabled(): bool {
    return mikanBoxSecurityLocked(function($dir) {
        $state = mikanBoxSecurityReadState($dir);
        return $state !== null && ($state['enabled'] ?? true) === true;
    }) === true;
}
function mikanBoxMcpSetEnabled(bool $enabled): bool {
    $ok = mikanBoxSecurityLocked(function($dir) use ($enabled) {
        $state = mikanBoxSecurityReadState($dir) ?? [];
        $state['enabled'] = $enabled;
        return mikanBoxSecurityWriteState($dir, $state);
    }) === true;
    if ($ok) mikanBoxAudit($enabled ? 'mcp_enabled' : 'mcp_disabled');
    return $ok;
}
function mikanBoxMcpUsed(string $key): void {
    if ($key === '') return;
    mikanBoxSecurityLocked(function($dir) use ($key) {
        $state = mikanBoxSecurityReadState($dir);
        if ($state === null) return false;
        $fingerprint = hash('sha256', $key);
        if (($state['key'] ?? '') === $fingerprint && time() - (int)($state['used_at'] ?? 0) < 3600) return true;
        $state['key'] = $fingerprint; $state['used_at'] = time();
        return mikanBoxSecurityWriteState($dir, $state);
    });
}
function mikanBoxMcpLastUsed(string $key): int {
    if ($key === '') return 0;
    return (int)mikanBoxSecurityLocked(function($dir) use ($key) {
        $state = mikanBoxSecurityReadState($dir);
        return ($state['key'] ?? '') === hash('sha256', $key) ? (int)($state['used_at'] ?? 0) : 0;
    });
}
function mikanBoxMaskedIp(): string {
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return preg_replace('/\.[0-9]+$/', '.0/24', $ip);
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) return inet_ntop(substr(inet_pton($ip), 0, 6) . str_repeat("\0", 10)) . '/48';
    return '';
}
if (!defined('MIKANBOX_AUDIT_KEEP')) define('MIKANBOX_AUDIT_KEEP', 50);
// Records are kept in three groups of MIKANBOX_AUDIT_KEEP each, so a run of failed sign-ins
// (or of page edits) never pushes out the important ones (MCP keys, devices, recovery, restores).
function mikanBoxAuditGroup(string $event): string {
    if ($event === 'login_failed') return 'login';
    if ($event === 'page_status') return 'edit';
    return 'important';
}
function mikanBoxAuditTrim(array $lines): array {
    // $lines: JSON lines, oldest first. Keep the newest MIKANBOX_AUDIT_KEEP of each group.
    $count=[]; $keep=[];
    for ($i=count($lines)-1;$i>=0;$i--) {
        $row=json_decode($lines[$i],true);
        $g=mikanBoxAuditGroup(is_array($row)?(string)($row['event'] ?? ''):'');
        $count[$g]=($count[$g] ?? 0)+1;
        if ($count[$g] <= MIKANBOX_AUDIT_KEEP) $keep[]=$lines[$i];
    }
    return array_reverse($keep);
}
function mikanBoxAudit(string $event, array $details = []): void {
    $events = ['mcp_enabled','mcp_disabled','mcp_key_issued','mcp_key_rotated','page_status','login_failed','device_registered','device_revoked','code_issued','invite_issued','recovery','accounts_reset','user_deleted','system_restored'];
    if (!in_array($event, $events, true)) return;
    $actor = $GLOBALS['mikanbox_audit_actor'] ?? (!empty($_SESSION['admin_logged_in']) ? ($_SESSION['admin_username'] ?? 'admin') : 'system');
    $entry = ['at'=>gmdate('c'), 'event'=>$event, 'actor'=>substr((string)$actor,0,80), 'ip'=>mikanBoxMaskedIp()];
    foreach (['target','from','to'] as $key) if (isset($details[$key]) && is_string($details[$key]) && preg_match('/\A[A-Za-z0-9_\/.:-]{0,200}\z/', $details[$key])) $entry[$key]=$details[$key];
    $ok = mikanBoxSecurityLocked(function($dir) use ($entry) {
        // Only the latest MIKANBOX_AUDIT_KEEP events of each group are kept.
        $path=$dir.'/events.jsonl.php';
        $line=json_encode($entry,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        clearstatcache(true,$path);
        $lines=[];
        if (is_file($path)) {
            $content=@file_get_contents($path); if ($content===false) return false;
            $lines=array_values(array_filter(array_slice(explode("\n",$content),1),fn($l)=>trim($l)!==''));
        }
        $lines[]=$line;
        $lines=mikanBoxAuditTrim($lines);
        $ok=@file_put_contents($path,MIKANBOX_SECURITY_GUARD.implode("\n",$lines)."\n",LOCK_EX)!==false;
        @chmod($path,0600); return $ok;
    });
    $GLOBALS['mikanbox_audit_unavailable']=$ok !== true;
    if (session_status() === PHP_SESSION_ACTIVE) {
        if ($ok !== true) $_SESSION['mikanbox_audit_unavailable']=true;
        else unset($_SESSION['mikanbox_audit_unavailable']);
    }
}
function mikanBoxAuditRecent(): array {
    $rows=mikanBoxSecurityLocked(function($dir) {
        $path=$dir.'/events.jsonl.php'; if (!is_file($path)) return [];
        $f=@fopen($path,'rb'); if (!$f) return [];
        fgets($f); // PHP access-denial guard
        $lines=array_values(array_filter(explode("\n",stream_get_contents($f)),fn($l)=>trim($l)!==''));fclose($f);
        return array_reverse(array_values(array_filter(array_map(fn($line)=>json_decode($line,true),mikanBoxAuditTrim($lines)),fn($row)=>is_array($row)&&isset($row['event'],$row['at']))));
    });
    return is_array($rows) ? $rows : [];
}
if (!function_exists("saveSettings")) {
function saveSettings($settings) {
    $previous=loadSettings(); $ok=mikanBoxStorageSaveSettings($settings);
    if ($ok && !empty($settings['mcp_api_key']) && ($previous['mcp_api_key'] ?? '') !== $settings['mcp_api_key']) mikanBoxAudit(empty($previous['mcp_api_key'])?'mcp_key_issued':'mcp_key_rotated');
    return $ok;
}
}
if (!function_exists("saveData")) {
function saveData($dir,$id,$data) {
    $before=$dir===POSTS_DIR?loadData($dir,$id):null;
    $ok=mikanBoxStorageSaveData($dir,$id,$data);
    if ($ok && $dir===POSTS_DIR && ($before['status'] ?? '') !== ($data['status'] ?? '') && ($before !== null || in_array($data['status'] ?? '',['public_static','public_dynamic'],true))) mikanBoxAudit('page_status',['target'=>$id,'from'=>$before['status'] ?? '', 'to'=>$data['status'] ?? '']);
    return $ok;
}
}
