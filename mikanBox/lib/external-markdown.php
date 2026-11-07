<?php
if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(403); exit; } // direct request guard
/** External documents are untrusted text, never templates or executable HTML. */
function mikanBoxExternalMarkdownText(string $text): string {
    return str_replace(['{', '}'], ['&#123;', '&#125;'], htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}

function mikanBoxExternalMarkdownEnabled(array $settings): bool {
    if (array_key_exists('external_markdown_enabled', $settings)) return $settings['external_markdown_enabled'] === true;
    // Preserve existing EXT_MD users on upgrade. Fresh seed sites have no such tags.
    static $legacy = null;
    if ($legacy !== null) return $legacy;
    $legacy = false;
    if (!function_exists('getFileList') || !defined('POSTS_DIR') || !defined('COMPONENTS_DIR')) return false;
    foreach ([POSTS_DIR, COMPONENTS_DIR] as $dir) {
        foreach (getFileList($dir) as $id) {
            $data = loadData($dir, $id) ?: [];
            if (preg_match('/\{\{EXT_MD:https?:\/\//', (string)($data['content_md'] ?? '') . (string)($data['html'] ?? ''))) return $legacy = true;
        }
    }
    return false;
}

function mikanBoxExternalPublicIp(string $ip): bool {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
    $packed = inet_pton($ip);
    if (strlen($packed) === 16) {
        // Only global unicast; excludes mapped IPv4, NAT64, local and multicast ranges.
        if ((ord($packed[0]) & 0xe0) !== 0x20) return false;
        $denied = ['2001::/23', '2001:db8::/32', '2002::/16', '3fff::/20'];
    } else {
        $denied = ['0.0.0.0/8','100.64.0.0/10','192.0.0.0/24','192.0.2.0/24','192.88.99.0/24','198.18.0.0/15','198.51.100.0/24','203.0.113.0/24','224.0.0.0/4','240.0.0.0/4'];
    }
    foreach ($denied as $cidr) {
        [$network, $bits] = explode('/', $cidr); $network = inet_pton($network); $bits = (int)$bits;
        $bytes = intdiv($bits, 8); $remaining = $bits % 8;
        if (substr($packed, 0, $bytes) === substr($network, 0, $bytes)
            && (!$remaining || ((ord($packed[$bytes]) ^ ord($network[$bytes])) & (0xff << (8 - $remaining))) === 0)) return false;
    }
    return true;
}

/** Resolve once, reject every non-public answer, and pin the validated address at connection time. */
function mikanBoxExternalTarget(string $url): ?array {
    if (!filter_var($url, FILTER_VALIDATE_URL) || preg_match('/[\x00-\x20\x7f]/', $url)) return null;
    $parts = parse_url($url);
    if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http','https'], true) || isset($parts['user']) || isset($parts['pass'])) return null;
    $host = trim($parts['host'] ?? '', '[]');
    if ($host === '' || strtolower(rtrim($host, '.')) === 'localhost') return null;
    if (filter_var($host, FILTER_VALIDATE_IP)) $ips = [$host];
    else {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        $ips = [];
        foreach ($records ?: [] as $record) {
            if (isset($record['ip'])) $ips[] = $record['ip'];
            if (isset($record['ipv6'])) $ips[] = $record['ipv6'];
        }
    }
    if (!$ips) return null;
    foreach ($ips as $ip) if (!mikanBoxExternalPublicIp($ip)) return null;
    return ['host'=>$host, 'port'=>$parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80), 'ip'=>$ips[0]];
}

function mikanBoxFetchExternalMarkdown(string $url): ?string {
    // cURL is required only for this optional feature. Never fall back to an unpinned stream.
    if (!function_exists('curl_init')) return null;
    $target = mikanBoxExternalTarget($url);
    if (!$target) return null;
    $handle = curl_init($url);
    if ($handle === false) return null;
    $body = ''; $limit = 1024 * 1024;
    $ip = str_contains($target['ip'], ':') ? '[' . $target['ip'] . ']' : $target['ip'];
    $configured = curl_setopt_array($handle, [
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_MAXREDIRS => 0,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_PROXY => '', CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT => 'mikanBox-external-markdown',
        CURLOPT_RESOLVE => filter_var($target['host'], FILTER_VALIDATE_IP) ? [] : [$target['host'] . ':' . $target['port'] . ':' . $ip],
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, $limit): int {
            if (strlen($body) + strlen($chunk) > $limit) return 0;
            $body .= $chunk; return strlen($chunk);
        },
    ]);
    if (!$configured) { unset($handle); return null; }
    $ok = curl_exec($handle); $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE); unset($handle);
    return $ok !== false && $status >= 200 && $status < 300 ? $body : null;
}
