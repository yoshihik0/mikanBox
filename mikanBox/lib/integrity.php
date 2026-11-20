<?php
if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(403); exit; } // direct request guard
/**
 * "Check for tampering" (2.27.0): compares what mikanBox knows to be right with what is on the server.
 * It runs only when the button on the Site screen is pressed; nothing here runs for a page view.
 *
 *  - Public files: the static output (text files) and media code (js, css, svg, html), against the
 *    fingerprints recorded when mikanBox wrote them. Images, audio and video are never read.
 *  - Program files: against the official release of the installed version on GitHub; when GitHub
 *    cannot be reached, against the state recorded when the program was installed or accepted.
 *  - Data (pages, components, settings): the stored content against the last "after" fingerprint
 *    in the operation history.
 * "Accept" takes the present state as the new basis. Baselines sit in the protected security folder:
 * someone who can rewrite server files can rewrite them too, so the GitHub comparison is the
 * part that is hard to forge.
 */
const MIKANBOX_INTEGRITY_FILE = 'integrity.json.php';
const MIKANBOX_INTEGRITY_CODE_EXT = ['js','mjs','css','svg','html','htm','php','phtml','phar'];
const MIKANBOX_INTEGRITY_PAGE_EXT = ['html','htm','php','phtml','phar','js','mjs'];

function mikanBoxIntegrityReadFile(string $dir): array {
    $path = $dir . '/' . MIKANBOX_INTEGRITY_FILE;
    if (!is_file($path)) return [];
    $raw = (string)@file_get_contents($path);
    if (!str_starts_with($raw, MIKANBOX_SECURITY_GUARD)) return [];
    $data = json_decode(substr($raw, strlen(MIKANBOX_SECURITY_GUARD)), true);
    return is_array($data) ? $data : [];
}
function mikanBoxIntegrityWriteFile(string $dir, array $state): bool {
    $temp = tempnam($dir, '.integrity-');
    if ($temp === false) return false;
    if (!@rename($temp, $temp . '.php')) { @unlink($temp); return false; }
    $temp .= '.php';
    @chmod($temp, 0600);
    $ok = @file_put_contents($temp, MIKANBOX_SECURITY_GUARD . json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false
        && @rename($temp, $dir . '/' . MIKANBOX_INTEGRITY_FILE);
    if (!$ok) @unlink($temp);
    return $ok;
}
function mikanBoxIntegrityLoad(): array {
    $state = mikanBoxSecurityLocked(fn($dir) => mikanBoxIntegrityReadFile($dir));
    return is_array($state) ? $state : [];
}
/** Change the record under the lock: $change(array &$state). */
function mikanBoxIntegrityMutate(callable $change): bool {
    return mikanBoxSecurityLocked(function($dir) use ($change) {
        $state = mikanBoxIntegrityReadFile($dir);
        $change($state);
        return mikanBoxIntegrityWriteFile($dir, $state);
    }) === true;
}
function mikanBoxIntegrityExt(string $name): string { return strtolower(pathinfo($name, PATHINFO_EXTENSION)); }

// ---- Recording (called when mikanBox itself writes something) ---------------------------------

/** The generator wrote / removed public files: $written = [relative path => sha256], $removed = [relative path => true]. */
function mikanBoxIntegrityPublicChanged(string $outputDir, array $written, array $removed, bool $complete = false): void {
    $root = str_replace('\\', '/', (string)(realpath($outputDir) ?: $outputDir));
    mikanBoxIntegrityMutate(function(&$state) use ($root, $written, $removed, $complete) {
        if (($state['public']['root'] ?? null) !== $root) $state['public'] = ['root' => $root, 'files' => [], 'complete' => false];
        foreach ($written as $path => $hash) $state['public']['files'][(string)$path] = $hash;
        foreach ($removed as $path => $unused) unset($state['public']['files'][(string)$path]);
        if ($complete) $state['public']['complete'] = true;
    });
}
/** A media file was added, changed, renamed or deleted through mikanBox (code files only are recorded). */
function mikanBoxIntegrityMediaChanged(string $name, bool $deleted, string $from = ''): void {
    $code = in_array(mikanBoxIntegrityExt($name), MIKANBOX_INTEGRITY_CODE_EXT, true);
    $fromCode = $from !== '' && in_array(mikanBoxIntegrityExt($from), MIKANBOX_INTEGRITY_CODE_EXT, true);
    if (!$code && !$fromCode) return;
    $hash = (!$deleted && $code && defined('MEDIA_DIR') && is_file(MEDIA_DIR . '/' . $name)) ? (string)hash_file('sha256', MEDIA_DIR . '/' . $name) : '';
    mikanBoxIntegrityMutate(function(&$state) use ($name, $deleted, $from, $hash, $fromCode) {
        $state['media'] ??= ['files' => [], 'complete' => false];
        if ($fromCode) unset($state['media']['files'][$from]);
        if ($deleted || $hash === '') unset($state['media']['files'][$name]); else $state['media']['files'][$name] = $hash;
    });
}
/** Data written without a line in the history (an import): its fingerprints, "key => sha256" ("page:id"). */
function mikanBoxIntegrityDataKnown(array $hashes): void {
    if (!$hashes) return;
    mikanBoxIntegrityMutate(function(&$state) use ($hashes) {
        foreach ($hashes as $key => $hash) $state['data_known'][(string)$key] = $hash;
    });
}
/** The program files as they are now: [path relative to the site folder => sha256]. */
function mikanBoxIntegrityProgramHashes(string $coreDir): array {
    require_once __DIR__ . '/updater.php';
    $siteRoot = dirname($coreDir);
    $hashes = [];
    foreach (mikanBoxUpdateProgramFiles($siteRoot, basename($coreDir)) as $relative) {
        if (basename($relative) === '.DS_Store') continue;
        $hashes[$relative] = (string)@hash_file('sha256', $siteRoot . '/' . $relative);
    }
    ksort($hashes);
    return $hashes;
}
function mikanBoxIntegrityRecordProgram(string $coreDir, string $version, string $source): void {
    $files = mikanBoxIntegrityProgramHashes($coreDir);
    mikanBoxIntegrityMutate(function(&$state) use ($files, $version, $source) {
        $state['program'] = ['version' => $version, 'files' => $files, 'source' => $source, 'at' => gmdate('c')];
        $state['program_accepted'] = [];
    });
}

// ---- The official release (the part that lives outside the server) ---------------------------

/**
 * Fingerprints of the program files of release $tag of $repo, as the updater would install them
 * into $coreDir. Returns ['files' => [...]] or ['error' => code].
 */
function mikanBoxIntegrityOfficialHashes(string $repo, string $version, string $coreDir, string $dataDir): array {
    require_once __DIR__ . '/updater.php';
    if (!class_exists('ZipArchive')) return ['error' => 'zip_unavailable'];
    if (function_exists('set_time_limit')) @set_time_limit(120);
    $tag = 'v' . ltrim($version, 'vV');
    // The release's own archive by tag: no API call (the unauthenticated GitHub API allows few requests per hour).
    if (!preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~', $repo) || !preg_match('/^v[0-9]+\.[0-9]+\.[0-9]+$/', $tag)) return ['error' => 'release_unavailable'];
    $work = $dataDir . '/update-temp/integrity-' . bin2hex(random_bytes(6));
    if (!@mkdir($work . '/files', 0777, true) && !is_dir($work . '/files')) return ['error' => 'download_failed'];
    try {
        $zipPath = $work . '/release.zip';
        if (!mikanBoxUpdateDownload('https://github.com/' . $repo . '/archive/refs/tags/' . $tag . '.zip', $zipPath)) return ['error' => 'release_unavailable'];
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) return ['error' => 'archive_invalid'];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string)$zip->getNameIndex($i));
            if ($name === '' || str_starts_with($name, '/') || preg_match('~(^|/)\.\.(/|$)~', $name)) { $zip->close(); return ['error' => 'archive_invalid']; }
        }
        $ok = $zip->extractTo($work . '/files');
        $zip->close();
        if (!$ok) return ['error' => 'archive_invalid'];
        $root = null;
        foreach (scandir($work . '/files') ?: [] as $item) {
            if ($item !== '.' && $item !== '..' && is_file($work . '/files/' . $item . '/mikanBox/config.php')) { $root = $work . '/files/' . $item; break; }
        }
        if ($root === null || mikanBoxUpdateReadVersion($root . '/mikanBox/config.php') !== ltrim($version, 'vV')) return ['error' => 'archive_invalid'];
        return ['files' => mikanBoxIntegrityPackageHashes($root, $coreDir)];
    } finally {
        mikanBoxUpdateRemoveTree($work);
    }
}
/** Fingerprints of an unpacked release as installed into $coreDir (a renamed core folder included). */
function mikanBoxIntegrityPackageHashes(string $packageRoot, string $coreDir): array {
    require_once __DIR__ . '/updater.php';
    mikanBoxUpdatePrepareFrontController($packageRoot, $coreDir);   // index.php names the core folder
    $hashes = [];
    foreach (mikanBoxUpdateProgramFiles($packageRoot) as $relative) {
        if (basename($relative) === '.DS_Store') continue;
        $hashes[mikanBoxUpdateTargetRelativePath($relative, $coreDir)] = (string)@hash_file('sha256', $packageRoot . '/' . $relative);
    }
    ksort($hashes);
    return $hashes;
}

// ---- The checks -------------------------------------------------------------------------------

function mikanBoxIntegrityDiff(array $reference, array $current, array $accepted = []): array {
    $found = ['changed' => [], 'added' => [], 'missing' => []];
    foreach ($reference as $path => $hash) {
        $now = $current[$path] ?? '';
        if ($now === '') { if (($accepted[$path] ?? null) !== '') $found['missing'][] = (string)$path; }
        elseif ($now !== $hash && ($accepted[$path] ?? null) !== $now) $found['changed'][] = (string)$path;
    }
    foreach ($current as $path => $now) {
        if (!isset($reference[$path]) && $now !== '' && ($accepted[$path] ?? null) !== $now) $found['added'][] = (string)$path;
    }
    foreach ($found as &$list) sort($list);
    return $found;
}
function mikanBoxIntegrityHasFindings(array $found): bool {
    return (bool)($found['changed'] ?? []) || (bool)($found['added'] ?? []) || (bool)($found['missing'] ?? []) || (bool)($found['old'] ?? []);
}
/** A program path (as the check lists it) that is one of the files earlier versions shipped. */
function mikanBoxIntegrityRetired(string $path, string $coreDir): bool {
    if (!defined('MIKANBOX_RETIRED_PROGRAM_FILES')) require_once __DIR__ . '/updater.php';
    $core = basename(rtrim($coreDir, '/\\'));
    $rel = str_starts_with($path, $core . '/') ? substr($path, strlen($core) + 1) : $path;
    return in_array($rel, mikanBoxUpdateRetiredProgramFiles(), true);
}
/** Records up to the last acceptance are not "out of the ordinary" any more (they were looked at). */
function mikanBoxIntegrityUnusualAcceptedUntil(): string {
    return (string)(mikanBoxIntegrityLoad()['unusual_until'] ?? '');
}

/** Public output: baseline files plus unknown page/code files in the places the generator writes to. */
function mikanBoxIntegrityCheckPublic(string $outputDir, array $state): array {
    $base = $state['public'] ?? null;
    $root = str_replace('\\', '/', (string)(realpath($outputDir) ?: $outputDir));
    if (!is_array($base) || ($base['root'] ?? null) !== $root || !is_array($base['files'] ?? null)) return ['baseline' => false];
    $files = $base['files'];
    $current = [];
    foreach ($files as $path => $unused) $current[$path] = is_file($root . '/' . $path) ? (string)hash_file('sha256', $root . '/' . $path) : '';
    $complete = !empty($base['complete']);
    if ($complete) {
        $site = str_replace('\\', '/', (string)(realpath(dirname(CORE_DIR)) ?: dirname(CORE_DIR)));
        $skip = [];
        foreach ([CORE_DIR, defined('DATA_DIR') ? DATA_DIR : '', defined('MEDIA_DIR') ? MEDIA_DIR : ''] as $dir) if ($dir !== '' && ($real = realpath($dir))) $skip[str_replace('\\', '/', $real)] = true;
        $dirs = ['' => true];
        foreach ($files as $path => $unused) { for ($d = dirname($path); $d !== '.' && $d !== '' && $d !== '/'; $d = dirname($d)) $dirs[$d] = true; }
        $recursive = $root !== $site;   // a folder of its own is scanned whole; the site folder only where pages are
        $list = $recursive ? ['' => true] : $dirs;
        $seen = 0;
        $queue = array_keys($list);
        while ($queue && $seen < 20000) {
            $rel = array_shift($queue);
            $path = $rel === '' ? $root : $root . '/' . $rel;
            $items = is_dir($path) ? @scandir($path) : false;
            if (!$items) continue;
            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || $item[0] === '.' || ++$seen > 20000) continue;
                $full = $path . '/' . $item; $itemRel = $rel === '' ? $item : $rel . '/' . $item;
                if (is_link($full)) continue;
                if (is_dir($full)) {
                    $real = str_replace('\\', '/', (string)realpath($full));
                    if (isset($skip[$real]) || str_starts_with($item, 'mikanbox-site-export-') || is_file($full . '/.mikanbox-export')) continue;
                    if ($recursive) $queue[] = $itemRel;
                    continue;
                }
                if (isset($files[$itemRel]) || !in_array(mikanBoxIntegrityExt($item), MIKANBOX_INTEGRITY_PAGE_EXT, true)) continue;
                if ($rel === '' && $root === $site && $item === 'index.php') continue;   // the front controller: a program file
                $current[$itemRel] = (string)hash_file('sha256', $full);
            }
        }
    }
    return ['baseline' => true, 'complete' => $complete, 'current' => $current, 'found' => mikanBoxIntegrityDiff($files, $current)];
}
/** Media code files (js, css, svg, html ...). Images, audio and video are never read. */
function mikanBoxIntegrityCheckMedia(array $state): array {
    $base = $state['media'] ?? null;
    if (!defined('MEDIA_DIR') || !is_array($base) || !is_array($base['files'] ?? null)) return ['baseline' => false];
    $files = $base['files'];
    $current = [];
    foreach ($files as $name => $unused) $current[$name] = is_file(MEDIA_DIR . '/' . $name) ? (string)hash_file('sha256', MEDIA_DIR . '/' . $name) : '';
    $complete = !empty($base['complete']);
    if ($complete && is_dir(MEDIA_DIR)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(MEDIA_DIR, FilesystemIterator::SKIP_DOTS));
        $seen = 0;
        foreach ($it as $file) {
            if (++$seen > 20000) break;
            if (!$file->isFile() || $file->isLink() || !in_array(mikanBoxIntegrityExt($file->getFilename()), MIKANBOX_INTEGRITY_CODE_EXT, true)) continue;
            $name = str_replace('\\', '/', substr($file->getPathname(), strlen(rtrim(MEDIA_DIR, '/')) + 1));
            if (!isset($files[$name])) $current[$name] = (string)hash_file('sha256', $file->getPathname());
        }
    }
    return ['baseline' => true, 'complete' => $complete, 'current' => $current, 'found' => mikanBoxIntegrityDiff($files, $current)];
}
/**
 * Program files. $official: null, or a callable returning ['files' => ...] / ['error' => code]
 * (default: the release on GitHub).
 */
function mikanBoxIntegrityCheckProgram(array $state, array $ctx, ?callable $official = null): array {
    $current = mikanBoxIntegrityProgramHashes($ctx['coreDir']);
    $result = $official ? $official() : mikanBoxIntegrityOfficialHashes($ctx['repo'], $ctx['version'], $ctx['coreDir'], $ctx['dataDir']);
    if (isset($result['files'])) {
        $found = mikanBoxIntegrityDiff($result['files'], $current, is_array($state['program_accepted'] ?? null) ? $state['program_accepted'] : []);
        // Files earlier versions shipped and an update left behind: reported on their own, with what to do.
        $found['old'] = array_values(array_filter($found['added'], fn($path) => mikanBoxIntegrityRetired($path, $ctx['coreDir'])));
        $found['added'] = array_values(array_diff($found['added'], $found['old']));
        $differs = mikanBoxIntegrityDiff($result['files'], $current);
        $diffMap = [];
        foreach ($differs as $list) foreach ($list as $path) $diffMap[$path] = $current[$path] ?? '';
        if (!mikanBoxIntegrityHasFindings($found) && !$diffMap) mikanBoxIntegrityRecordProgram($ctx['coreDir'], $ctx['version'], 'official');
        return ['reference' => 'official', 'version' => $ctx['version'], 'found' => $found, 'current' => $current, 'differs' => $diffMap];
    }
    $base = $state['program']['files'] ?? null;
    if (!is_array($base)) return ['reference' => 'none', 'error' => (string)($result['error'] ?? ''), 'current' => $current, 'found' => []];
    return ['reference' => 'local', 'error' => (string)($result['error'] ?? ''), 'source' => (string)($state['program']['source'] ?? ''), 'current' => $current, 'differs' => [],
            'found' => mikanBoxIntegrityDiff($base, $current)];
}
/**
 * Data: the stored content against the last "after" fingerprint of the history.
 * Items without a record (made before the history, or never edited) cannot be compared and are skipped.
 */
function mikanBoxIntegrityCheckData(array $state, ?array $rows = null): array {
    $rows ??= array_reverse(mikanBoxAuditRecent());   // oldest first
    $last = [];
    foreach ($rows as $row) {
        $event = (string)($row['event'] ?? ''); $target = (string)($row['target'] ?? '');
        if ($event === 'settings_changed') { $last['settings'] = ['after' => (string)($row['after'] ?? ''), 'stale' => !isset($row['after'])]; continue; }
        if (!preg_match('/\A(page|component)_(created|updated|status|deleted)\z/', $event, $m) || $target === '') continue;
        $key = $m[1] . ':' . $target;
        if ($m[2] === 'deleted') $last[$key] = ['after' => '', 'stale' => false];
        elseif (isset($row['after'])) $last[$key] = ['after' => (string)$row['after'], 'stale' => false];
        else $last[$key] = ['after' => $last[$key]['after'] ?? '', 'stale' => true];   // a status change recorded before fingerprints were kept
    }
    $known = is_array($state['data_known'] ?? null) ? $state['data_known'] : [];
    $found = ['changed' => [], 'missing' => [], 'unrecorded' => []];
    $current = [];
    foreach ($last as $key => $row) {
        if ($key === 'settings') {
            $settings = loadSettings();
            $hashes = [mikanBoxAuditSettingsHash($settings), mikanBoxAuditHash(array_diff_key($settings, array_flip(MIKANBOX_AUDIT_SETTINGS_IGNORED)))];
            $exists = true;
        } else {
            [$kind, $id] = explode(':', $key, 2);
            $stored = mikanBoxAuditStored($kind === 'page' ? POSTS_DIR : COMPONENTS_DIR, $id);
            $exists = $stored !== null;
            $hashes = $exists ? [mikanBoxAuditDataHash($stored), mikanBoxAuditHash($stored)] : [''];
        }
        $now = $hashes[0];
        $current[$key] = $now;
        if (array_key_exists($key, $known) && ($known[$key] === $now || ($known[$key] === '' && !$exists))) continue;
        if (!$exists) { if ($row['after'] !== '') $found['missing'][] = $key; }
        elseif ($row['after'] === '' && !$row['stale'] && $key !== 'settings') $found['unrecorded'][] = $key;   // only after a deletion record; old records without fingerprints cannot be compared
        elseif (!$row['stale'] && !in_array($row['after'], $hashes, true)) $found['changed'][] = $key;
    }
    return ['found' => $found, 'current' => $current];
}
/** Records of the last 30 days that are out of the ordinary (see mikanBoxAuditUnusual), newest first. */
function mikanBoxIntegrityUnusual(?array $rows = null, ?int $now = null): array {
    $rows ??= array_reverse(mikanBoxAuditRecent());
    $since = ($now ?? time()) - 30 * 86400;
    $out = [];
    foreach (mikanBoxAuditUnusual($rows, mikanBoxIntegrityUnusualAcceptedUntil()) as $i) if ((int)strtotime((string)($rows[$i]['at'] ?? '')) >= $since) $out[] = $rows[$i];
    return array_slice(array_reverse($out), 0, 100);
}

/** The whole check. $ctx: coreDir, dataDir, repo, version, outputDir. $opts['official']: see CheckProgram. */
function mikanBoxIntegrityCheck(array $ctx, array $opts = []): array {
    $started = microtime(true);
    $state = mikanBoxIntegrityLoad();
    $report = [
        'at' => date('Y-m-d H:i'),
        'public' => mikanBoxIntegrityCheckPublic($ctx['outputDir'], $state),
        'media' => mikanBoxIntegrityCheckMedia($state),
        'program' => mikanBoxIntegrityCheckProgram($state, $ctx, $opts['official'] ?? null),
        'data' => mikanBoxIntegrityCheckData($state),
        'unusual' => mikanBoxIntegrityUnusual(),
    ];
    $report['findings'] = !empty($report['public']['found']) && mikanBoxIntegrityHasFindings($report['public']['found'])
        || (!empty($report['media']['found']) && mikanBoxIntegrityHasFindings($report['media']['found']))
        || (!empty($report['program']['found']) && mikanBoxIntegrityHasFindings($report['program']['found']))
        || mikanBoxIntegrityHasFindings($report['data']['found']);
    $report['needs_baseline'] = empty($report['public']['baseline']) || empty($report['media']['baseline']) || $report['program']['reference'] === 'none'
        || (!empty($report['public']['baseline']) && empty($report['public']['complete'])) || (!empty($report['media']['baseline']) && empty($report['media']['complete']));
    $report['ms'] = (int)round((microtime(true) - $started) * 1000);
    return $report;
}
/** "This is my change": the present state becomes the basis. Returns the report that was adopted. */
function mikanBoxIntegrityAccept(array $ctx, array $opts = []): array {
    $report = mikanBoxIntegrityCheck($ctx, $opts);
    $parts = [];
    $data = $report['data']['found'];
    $known = [];
    foreach ($data as $list) foreach ($list as $key) $known[$key] = $report['data']['current'][$key] ?? '';
    mikanBoxIntegrityMutate(function(&$state) use ($ctx, $report, $known, &$parts) {
        $root = str_replace('\\', '/', (string)(realpath($ctx['outputDir']) ?: $ctx['outputDir']));
        $publicCurrent = $report['public']['current'] ?? [];
        // Unreadable entries ("" = gone) are dropped from the basis.
        $state['public'] = ['root' => $root, 'files' => array_filter($publicCurrent, fn($h) => $h !== ''), 'complete' => true];
        $state['media'] = ['files' => array_filter($report['media']['current'] ?? [], fn($h) => $h !== ''), 'complete' => true];
        foreach (['public', 'media'] as $area) if (!empty($report[$area]['baseline']) ? mikanBoxIntegrityHasFindings($report[$area]['found']) : true) $parts[] = $area;
        $state['program'] = ['version' => $ctx['version'], 'files' => $report['program']['current'], 'source' => 'accepted', 'at' => gmdate('c')];
        $state['program_accepted'] = ($report['program']['reference'] === 'official') ? $report['program']['differs'] : [];
        if (($report['program']['reference'] === 'none') || mikanBoxIntegrityHasFindings($report['program']['found'] ?? [])) $parts[] = 'program';
        foreach ($known as $key => $hash) $state['data_known'][$key] = $hash;
        if ($known) $parts[] = 'data';
        if ($report['unusual']) $parts[] = 'unusual';
    });
    mikanBoxAudit('integrity_accepted', ['target' => implode(',', array_unique($parts))]);
    // After the acceptance's own record, so that record is not "out of the ordinary" either.
    if ($report['unusual']) mikanBoxIntegrityMutate(function(&$state) { $state['unusual_until'] = gmdate('c'); });
    return $report;
}

// ---- Screen ----------------------------------------------------------------------------------

function mikanBoxIntegrityE($text): string { return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
function mikanBoxIntegrityList(string $title, array $items): string {
    if (!$items) return '';
    $html = '<p class="integrity-sub">' . mikanBoxIntegrityE($title) . ' (' . count($items) . ')</p><ul class="integrity-list">';
    foreach (array_slice($items, 0, 100) as $item) $html .= '<li><code>' . mikanBoxIntegrityE($item) . '</code></li>';
    if (count($items) > 100) $html .= '<li>…</li>';
    return $html . '</ul>';
}
function mikanBoxIntegrityDataLabel(string $key): string {
    if ($key === 'settings') return t('integrity_item_settings');
    [$kind, $id] = explode(':', $key, 2);
    return t('integrity_item_' . $kind) . ' ' . $id;
}
/** The result below the button (HTML, every value escaped). */
function mikanBoxIntegrityRender(array $report, ?string $acceptedNote = null): string {
    $html = '<div class="integrity-result">';
    if ($acceptedNote !== null) $html .= '<p class="integrity-ok">' . mikanBoxIntegrityE($acceptedNote) . '</p>';
    $findingsHtml = '';
    $notes = '';
    foreach (['public' => 'integrity_sec_public', 'media' => 'integrity_sec_media'] as $area => $label) {
        $r = $report[$area];
        if (empty($r['baseline'])) { $notes .= '<li>' . mikanBoxIntegrityE(t($area === 'public' ? 'integrity_no_baseline_public' : 'integrity_no_baseline_media')) . '</li>'; continue; }
        if (empty($r['complete'])) $notes .= '<li>' . mikanBoxIntegrityE(t('integrity_partial_' . $area)) . '</li>';
        if (!mikanBoxIntegrityHasFindings($r['found'])) continue;
        $findingsHtml .= '<h4>' . mikanBoxIntegrityE(t($label)) . '</h4>'
            . mikanBoxIntegrityList(t('integrity_changed'), $r['found']['changed'])
            . mikanBoxIntegrityList(t('integrity_added'), $r['found']['added'])
            . mikanBoxIntegrityList(t('integrity_missing'), $r['found']['missing']);
    }
    $p = $report['program'];
    if ($p['reference'] === 'none') $notes .= '<li>' . mikanBoxIntegrityE(t('integrity_ref_none')) . '</li>';
    elseif (mikanBoxIntegrityHasFindings($p['found'])) {
        $findingsHtml .= '<h4>' . mikanBoxIntegrityE(t('integrity_sec_program')) . '</h4><p class="integrity-ref">'
            . mikanBoxIntegrityE($p['reference'] === 'official' ? t('integrity_ref_official', $p['version']) : t('integrity_ref_local')) . '</p>'
            . mikanBoxIntegrityList(t('integrity_changed'), $p['found']['changed'])
            . mikanBoxIntegrityList(t('integrity_added'), $p['found']['added'])
            . mikanBoxIntegrityList(t('integrity_missing'), $p['found']['missing'])
            . (($p['found']['old'] ?? []) ? mikanBoxIntegrityList(t('integrity_old_files'), $p['found']['old']) . '<p class="integrity-ref">' . mikanBoxIntegrityE(t('integrity_old_files_hint')) . '</p>' : '');
    }
    $d = $report['data']['found'];
    if ($d['changed'] || $d['missing'] || $d['unrecorded']) {
        $findingsHtml .= '<h4>' . mikanBoxIntegrityE(t('integrity_sec_data')) . '</h4>'
            . mikanBoxIntegrityList(t('integrity_data_changed'), array_map('mikanBoxIntegrityDataLabel', $d['changed']))
            . mikanBoxIntegrityList(t('integrity_data_missing'), array_map('mikanBoxIntegrityDataLabel', $d['missing']))
            . mikanBoxIntegrityList(t('integrity_data_unrecorded'), array_map('mikanBoxIntegrityDataLabel', $d['unrecorded']));
    }
    if ($report['findings']) {
        $html .= '<p class="integrity-warn" role="alert">' . mikanBoxIntegrityE(t('integrity_found', $report['at'])) . '</p>' . $findingsHtml;
    } else {
        $html .= '<p class="integrity-ok">' . mikanBoxIntegrityE(t('integrity_ok', $report['at'])) . '</p>';
    }
    $refText = $p['reference'] === 'official' ? t('integrity_ref_official', $p['version']) : ($p['reference'] === 'local' ? t('integrity_ref_local') : '');
    if ($refText !== '' && !mikanBoxIntegrityHasFindings($p['found'] ?? [])) $notes .= '<li>' . mikanBoxIntegrityE($refText) . '</li>';
    if ($notes !== '') $html .= '<ul class="integrity-notes">' . $notes . '</ul>';
    if ($report['unusual']) {
        $html .= '<h4>' . mikanBoxIntegrityE(t('integrity_unusual_head')) . '</h4><p class="integrity-ref">' . mikanBoxIntegrityE(t('integrity_unusual_hint')) . '</p>'
            . '<table class="integrity-unusual"><tbody>';
        foreach ($report['unusual'] as $row) {
            $html .= '<tr class="audit-unusual"><td>' . mikanBoxIntegrityE($row['at'] ?? '') . '</td><td>' . mikanBoxIntegrityE(t('audit_event_' . ($row['event'] ?? ''))) . '</td><td>'
                . mikanBoxIntegrityE($row['actor'] ?? '') . '</td><td>' . mikanBoxIntegrityE(mikanBoxAuditTargetText($row)) . '</td><td>' . mikanBoxIntegrityE($row['ip'] ?? '') . '</td></tr>';
        }
        $html .= '</tbody></table>';
    }
    if ($report['findings'] || $report['needs_baseline'] || $report['unusual']) {
        $html .= '<p><button type="button" class="btn btn-gray btn-small" data-integrity-accept>' . mikanBoxIntegrityE(t('integrity_accept_btn')) . '</button> <small class="sub-text">' . mikanBoxIntegrityE(t('integrity_accept_hint')) . '</small></p>';
    }
    return $html . '</div>';
}
/** The two actions of the button (an ajax POST, already past the sign-in and CSRF checks). Ends the request. */
function mikanBoxIntegrityHandle(string $action, string $repo, string $outputDir): void {
    require_once __DIR__ . '/updater.php';
    $version = mikanBoxUpdateReadVersion(CORE_DIR . '/config.php') ?: MIKANBOX_VERSION;
    $ctx = ['coreDir' => CORE_DIR, 'dataDir' => DATA_DIR, 'repo' => $repo, 'version' => $version, 'outputDir' => $outputDir];
    if ($action === 'integrity_accept') {
        $report = mikanBoxIntegrityAccept($ctx);
        $html = mikanBoxIntegrityRender(mikanBoxIntegrityCheck($ctx), t('integrity_accepted_msg'));
    } else {
        $report = mikanBoxIntegrityCheck($ctx);
        $html = mikanBoxIntegrityRender($report);
    }
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['success' => true, 'message' => '', 'html' => $html], JSON_UNESCAPED_UNICODE);
    exit;
}
