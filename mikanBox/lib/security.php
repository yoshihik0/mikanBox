<?php
const MIKANBOX_SECURITY_GUARD = "<?php http_response_code(404); exit; ?>\n";
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
