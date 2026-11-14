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
function mikanBoxAudit(string $event, array $details = []): void {
    $events = ['mcp_enabled','mcp_disabled','mcp_key_issued','mcp_key_rotated','page_status','login_failed','device_registered','device_revoked','code_issued','invite_issued','recovery','accounts_reset','user_deleted','system_restored'];
    if (!in_array($event, $events, true)) return;
    $actor = $GLOBALS['mikanbox_audit_actor'] ?? (!empty($_SESSION['admin_logged_in']) ? ($_SESSION['admin_username'] ?? 'admin') : 'system');
    $entry = ['at'=>gmdate('c'), 'event'=>$event, 'actor'=>substr((string)$actor,0,80), 'ip'=>mikanBoxMaskedIp()];
    foreach (['target','from','to'] as $key) if (isset($details[$key]) && is_string($details[$key]) && preg_match('/\A[A-Za-z0-9_\/.:-]{0,200}\z/', $details[$key])) $entry[$key]=$details[$key];
    $ok = mikanBoxSecurityLocked(function($dir) use ($entry) {
        $path=$dir.'/events.jsonl.php'; $limit=1048576;
        $line=json_encode($entry,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)."\n";
        clearstatcache(true,$path);
        if (!is_file($path) && @file_put_contents($path,MIKANBOX_SECURITY_GUARD,LOCK_EX)===false) return false;
        if (filesize($path)+strlen($line)>$limit) {
            $handle=@fopen($path,'rb'); if (!$handle) return false;
            fseek($handle,-min(filesize($path),intdiv($limit,2)),SEEK_END);
            fgets($handle); $content=stream_get_contents($handle); fclose($handle);
            $ok=@file_put_contents($path,MIKANBOX_SECURITY_GUARD.$content.$line,LOCK_EX)!==false;
        } else $ok=@file_put_contents($path,$line,FILE_APPEND|LOCK_EX)!==false;
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
        if (filesize($path)>1048576) fseek($f,-1048576,SEEK_END);
        fgets($f); // PHP access-denial guard, or a partial line after seeking
        $lines=explode("\n",stream_get_contents($f));fclose($f);
        return array_reverse(array_values(array_filter(array_map(fn($line)=>json_decode($line,true),array_slice($lines,-51)),fn($row)=>is_array($row)&&isset($row['event'],$row['at']))));
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
