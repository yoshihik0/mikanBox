<?php
/**
 * Data tool: saving and reading the site, the same in the flat and SQLite editions (and in Cloud,
 * src/data-transfer.ts). One ZIP layout for everything:
 *
 *   data/settings.json            the content settings (MIKANBOX_TRANSFER_SETTINGS) only
 *   data/posts/<page id>.json
 *   data/components/<id>.json
 *   data/revisions/<page id>.json [{created_at, editor, data}, ...] (only when history is included)
 *   media/<file name>
 *
 * Reading: the browser (data-transfer.js) opens the file(s) and sends the data in parts, then each
 * media file, then "finish". Three ways:
 *   add       — only what the site does not have; the same ID or name is skipped
 *   overwrite — the same ID or name is replaced (the page's previous content goes to its history)
 *   replace   — the site becomes the file: what the file does not have is deleted
 * Only the kinds the file holds are touched (pages, components, settings, media): a file of pages
 * leaves the components and media alone, and so on. Users, devices, the site ID
 * and the keys are never read from a file. Settings are read with overwrite and replace only.
 */

/** Settings that describe the content. Everything else (keys, users, addresses, build paths) stays. */
const MIKANBOX_TRANSFER_SETTINGS = ['site_name', 'description', 'keywords', 'ogp_image', 'category_candidates', 'registered_categories', 'memo', 'ai_prompt', 'pages_per_page', 'media_per_page', 'tracking_id', 'tracking_head_code', 'tracking_body_code', 'tracking_component', 'system_lang'];
const MIKANBOX_TRANSFER_MODES = ['add', 'overwrite', 'replace'];
/** Larger parts are refused; the browser sends about 1 MB at a time. */
const MIKANBOX_TRANSFER_PART_BYTES = 4000000;

function mikanBoxTransferHasHistory(): bool {
    return function_exists('getRevisions') && function_exists('mikanBoxImportRevisions');
}

function mikanBoxTransferSettings(array $settings): array {
    return array_intersect_key($settings, array_flip(MIKANBOX_TRANSFER_SETTINGS));
}

/** The media files of the site: regular files with a media name (not .htaccess and the like). */
function mikanBoxTransferMediaNames(): array {
    $names = [];
    if (!is_dir(MEDIA_DIR)) return $names;
    foreach (scandir(MEDIA_DIR) ?: [] as $name) {
        if ($name[0] === '.' || !is_file(MEDIA_DIR . '/' . $name) || !mediaValidFilename($name)) continue;
        $names[] = $name;
    }
    sort($names);
    return $names;
}

/**
 * The lists' filters as the browser read them (address: cat, q, fmt, q_media, media_type, media_all; and the
 * component IDs the list still shows). Each kind is saved on its own filter; with none set, all of it.
 * Returns null when the input is not usable.
 */
function mikanBoxTransferFilter($raw): ?array {
    $input = is_string($raw) ? json_decode($raw, true) : $raw;
    if (!is_array($input)) return null;
    $text = fn($key) => is_string($input[$key] ?? null) ? trim($input[$key]) : '';
    $components = null;
    if (is_array($input['components'] ?? null)) {
        $components = array_values(array_filter($input['components'], fn($id) => is_string($id) && $id !== ''));
    }
    return [
        'cat' => $text('cat'), 'q' => $text('q'), 'fmt' => $text('fmt'),
        'q_media' => $text('q_media'), 'media_type' => $text('media_type'), 'media_all' => !empty($input['media_all']),
        'components' => $components,
    ];
}

/** The page IDs the page list shows with these filters (null: no filter, all pages). */
function mikanBoxTransferPageIds(?array $filter): ?array {
    if ($filter === null || ($filter['cat'] === '' && $filter['q'] === '' && $filter['fmt'] === '')) return null;
    $ids = getFileList(POSTS_DIR);
    if ($filter['q'] !== '' && function_exists('searchPosts')) {
        $found = array_column(searchPosts($filter['q'], true), 'id');
        $ids = array_values(array_filter($ids, fn($id) => in_array($id, $found, true)));
    }
    return array_values(array_filter($ids, function ($id) use ($filter) {
        $data = loadData(POSTS_DIR, $id);
        if (!is_array($data)) return false;
        if ($filter['fmt'] !== '') {
            $format = mikanBoxPageOutputFormat($data);
            if ($filter['fmt'] === 'data' ? !mikanBoxIsDataFormat($format) : $format !== $filter['fmt']) return false;
        }
        if ($filter['cat'] !== '') {
            $cats = array_filter(array_map('trim', explode(',', (string)($data['category'] ?? ''))));
            if (!in_array($filter['cat'], $cats, true)) return false;
        }
        return true;
    }));
}

/** The media names the media list shows with these filters (null: no filter, all media). */
function mikanBoxTransferMediaFilter(?array $filter, array $names): array {
    if ($filter === null) return $names;
    $category = $filter['media_all'] ? '' : $filter['cat'];
    if ($category === '' && $filter['q_media'] === '' && $filter['media_type'] === '') return $names;
    $types = ['image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], 'audio' => ['mp3', 'm4a'], 'video' => ['mp4'], 'js' => ['js'], 'css' => ['css']];
    return array_values(array_filter($names, function ($name) use ($filter, $category, $types) {
        if ($filter['media_type'] !== '' && isset($types[$filter['media_type']])
            && !in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $types[$filter['media_type']], true)) return false;
        if ($filter['q_media'] !== '' && stripos($name, $filter['q_media']) === false) return false;
        if ($category !== '' && !(mediaIsCodeAsset($name) || strpos($name, 'g_') === 0)) {
            $parts = explode('_', $name);
            array_pop($parts);
            if (!in_array($category, $parts, true)) return false;
        }
        return true;
    }));
}

/** Find literal local media references without resolving links to other pages. */
function mikanBoxTransferCollectMedia(string $text, array &$used, bool $script = false): void {
    preg_match_all(<<<'REGEX'
~(?:\b(?:src|poster)\s*=\s*|<link\b[^>]*?\bhref\s*=\s*|\burl\(\s*|!\[[^\]\n]*\]\(\s*)["']?([^\s"'<>\)]+)|\{\{\s*(?:IMAGE|AUDIO|VIDEO):\s*([^}\s]+)\s*\}\}~i
REGEX, $text, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
    foreach ($matches as $match) {
        mikanBoxTransferMediaReference($match[1] ?? $match[2], $used);
    }
    if ($script) {
        preg_match_all(<<<'REGEX'
~["']((?:\{\{\s*(?:SITE_URL|BASE_URL)\s*\}\}/|(?:\.{1,2}/)*|/)media/[^"'\s<>]+)["']~i
REGEX, $text, $literals);
        foreach ($literals[1] as $literal) mikanBoxTransferMediaReference($literal, $used);
    }
    preg_match_all('~\bsrcset\s*=\s*(["\'])(.*?)\1~is', $text, $sets);
    foreach ($sets[2] as $set) {
        foreach (explode(',', $set) as $candidate) {
            mikanBoxTransferMediaReference(preg_split('~\s+~', trim($candidate))[0], $used);
        }
    }
}

function mikanBoxTransferMediaReference(string $path, array &$used): void {
    $path = preg_replace('~^\{\{\s*(?:SITE_URL|BASE_URL)\s*\}\}/~i', '/', trim($path));
    $path = rawurldecode(preg_split('~[?#]~', $path, 2)[0]);
    if (preg_match('~^(?:(?:\.{1,2}/)*(?:(?:media|images)/)?|/(?:[A-Za-z0-9_.-]+/)*(?:media|images)/|/)([A-Za-z0-9_.-]+)$~', $path, $match)
        && preg_match('~\.(?:jpe?g|png|gif|webp|svg|mp3|m4a|mp4|js|css)$~i', $match[1])) {
        $used[$match[1]] = true;
    }
}

/** Collect components and media used directly by the selected pages. Do not traverse page links. */
function mikanBoxTransferPageAssets(array $pages, array $settings): array {
    $components = [];
    $media = [];
    $seen = [];
    $scan = function (string $body) use (&$load, &$media): void {
        $body = preg_replace('~^[ \t]{0,3}(`{3,}|\~{3,})[^\n]*\n.*?(?:^[ \t]{0,3}\1[`\~]*[ \t]*$|\z)|`[^`\n]*`|<!--.*?-->~ms', '', $body);
        mikanBoxTransferCollectMedia($body, $media);
        preg_match_all('~\{\{\s*(COMPONENT|NAV_CARDS|SEARCH_RESULTS)\s*((?::[^}]*)?)\}\}~', $body, $tags, PREG_SET_ORDER);
        foreach ($tags as [, $tag, $args]) {
            $parts = $args === '' ? [] : array_map('trim', explode(':', substr($args, 1)));
            if ($tag === 'NAV_CARDS') {
                $parts = array_values(array_filter($parts, fn($part) => !preg_match('~^(?:sort|limit|sep)=~', $part)));
                $load(($parts[1] ?? '') ?: '_nav_card');
            } elseif ($tag === 'SEARCH_RESULTS' && function_exists('searchPosts')) {
                $load(($parts[0] ?? '') ?: '_search_result_item');
            } elseif ($tag === 'COMPONENT') {
                $load($parts[0] ?? '');
            }
        }
    };
    $load = function (string $id) use (&$seen, &$components, &$media, $scan): bool {
        if ($id === '' || $id === '__none' || mikanBoxValidateNewId($id, 'component') !== null) return false;
        if (isset($seen[$id])) return isset($components[$id]);
        $seen[$id] = true;
        $part = loadData(COMPONENTS_DIR, $id);
        if (!is_array($part) || !empty($part['is_ai_doc'])) return false;
        $components[$id] = $part;
        $scan((string)($part['html'] ?? ''));
        foreach (['css', 'js'] as $field) mikanBoxTransferCollectMedia((string)($part[$field] ?? ''), $media, $field === 'js');
        return true;
    };
    if ($pages) mikanBoxTransferMediaReference((string)($settings['ogp_image'] ?? ''), $media);
    foreach ($pages as $page) {
        $format = mikanBoxPageOutputFormat($page);
        if ($format === 'html' || $format === 'html_md') {
            $wrapper = (string)($page['wrapper_comp'] ?? '_layout');
            if ($wrapper !== '__none' && !$load($wrapper)) $load('_layout');
            if (empty($page['exclude_tracking']) && empty($settings['tracking_id']) && empty($settings['tracking_head_code']) && empty($settings['tracking_body_code'])) {
                $load((string)($settings['tracking_component'] ?? ''));
            }
            foreach (['css', 'js'] as $field) mikanBoxTransferCollectMedia((string)($page[$field] ?? ''), $media, $field === 'js');
        }
        if ($format === 'html_md' || $format === 'md') {
            foreach (mikanBoxNormalizeFooterComponents($page['md_footer_comps'] ?? []) as $id) $load($id);
        }
        mikanBoxTransferMediaReference((string)($page['ogp_image'] ?? ''), $media);
        $scan((string)($page['content_md'] ?? ''));
    }
    return [$components, $media];
}

/** Builds the ZIP (contents: both|data|media) in the data folder. Null when there is nothing to save or it fails. */
function mikanBoxTransferBuildZip(string $contents, bool $withHistory, ?array $filter = null): ?string {
    if (!in_array($contents, ['both', 'data', 'media'], true) || !class_exists('ZipArchive')) return null;
    $tmp = DATA_DIR . '/transfer_' . bin2hex(random_bytes(8)) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return null;
    $json = fn($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    $assets = null;
    if ($contents !== 'media') {
        $settings = mikanBoxTransferSettings(loadSettings());
        $zip->addFromString('data/settings.json', $json((object)$settings));
        $pageIds = mikanBoxTransferPageIds($filter);
        $pages = [];
        foreach ($pageIds ?? getFileList(POSTS_DIR) as $id) {
            $data = loadData(POSTS_DIR, $id);
            if (!is_array($data)) continue;
            if ($pageIds !== null) $pages[] = $data;
            $zip->addFromString('data/posts/' . $id . '.json', $json($data));
            if ($withHistory && mikanBoxTransferHasHistory()) {
                $items = [];
                foreach (array_reverse(getRevisions($id)) as $revision) {
                    $revisionData = getRevisionData($revision['id']);
                    if (is_array($revisionData)) $items[] = ['created_at' => $revision['created_at'], 'editor' => $revision['editor_id'], 'data' => $revisionData];
                }
                if ($items) $zip->addFromString('data/revisions/' . $id . '.json', $json($items));
            }
        }
        if ($pageIds !== null) {
            [$parts, $assets] = mikanBoxTransferPageAssets($pages, $settings);
            foreach ($parts as $id => $data) $zip->addFromString('data/components/' . $id . '.json', $json($data));
        } else {
            $componentIds = getFileList(COMPONENTS_DIR);
            if ($filter !== null && $filter['components'] !== null) $componentIds = array_values(array_intersect($componentIds, $filter['components']));
            foreach ($componentIds as $id) {
                $data = loadData(COMPONENTS_DIR, $id);
                if (is_array($data)) $zip->addFromString('data/components/' . $id . '.json', $json($data));
            }
        }
    }
    if ($contents !== 'data') {
        $names = mikanBoxTransferMediaNames();
        $names = $assets === null ? mikanBoxTransferMediaFilter($filter, $names) : array_values(array_filter($names, fn($name) => isset($assets[$name])));
        foreach ($names as $name) $zip->addFile(MEDIA_DIR . '/' . $name, 'media/' . $name);
    }
    if ($zip->numFiles === 0) { $zip->close(); @unlink($tmp); return null; }   // nothing to save (no media yet)
    if (!$zip->close() || !is_file($tmp)) { @unlink($tmp); return null; }
    return $tmp;
}

/** Saves one ZIP and sends it. Returns only when there is nothing to send. */
function mikanBoxTransferExport(string $contents, bool $withHistory, ?array $filter = null): void {
    $tmp = mikanBoxTransferBuildZip($contents, $withHistory, $filter);
    if ($tmp === null) return;
    $label = ['both' => 'backup', 'data' => 'data', 'media' => 'media'][$contents];
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="mikanbox-' . $label . '-' . date('Y-m-d') . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

/**
 * The public site as a ZIP: built afresh into a temporary folder in the site root, as "Make an upload
 * folder" does (lib/ssg.php export mode), with the media. $relative: links relative to each file, so the
 * pages open from the unpacked folder; otherwise the site's own address. Returns the ZIP path or an error.
 */
function mikanBoxTransferBuildSite(bool $relative): array {
    require_once __DIR__ . '/ssg.php';
    $settings = loadSettings();
    $site = $settings;
    $site['ssg_mode'] = 'export';
    $site['ssg_link_mode'] = $relative ? 'relative' : 'absolute';
    // The site's address: for absolute links, and in both cases for what always needs one (Markdown versions, feeds).
    if (trim((string)($site['ssg_root_url'] ?? '')) === '') $site['ssg_root_url'] = mikanBoxDynamicSiteUrl($settings);
    $out = dirname(CORE_DIR) . '/mikanbox-site-export-' . bin2hex(random_bytes(6));
    $previous = $GLOBALS['mikanbox_settings'] ?? null;
    $GLOBALS['mikanbox_settings'] = $site;
    try {
        $ssg = new MikanBoxSSG(new MikanBoxRenderer($site), $out, [
            'structure' => $relative ? 'file' : ($settings['ssg_server_structure'] ?? 'directory'),
            // Every public page, those served dynamically included: the export is the site as visitors see it.
            'selected_pages' => array_values(array_filter(getSortedPostIds(), fn($id) => in_array(loadData(POSTS_DIR, $id)['status'] ?? '', ['public_static', 'public_dynamic'], true))),
            'output_mode' => 'export', 'link_mode' => $site['ssg_link_mode'], 'copy_media' => true, 'record_baseline' => false,
        ]);
        $errors = array_values(array_filter($ssg->build(), fn($r) => strpos((string)$r, 'Error') !== false));
    } finally {
        $GLOBALS['mikanbox_settings'] = $previous;
    }
    $zipPath = DATA_DIR . '/transfer_site_' . bin2hex(random_bytes(8)) . '.zip';
    if (!$errors && is_dir($out)) {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($out, FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                $rel = str_replace('\\', '/', substr($file->getPathname(), strlen($out) + 1));
                // Server files (.htaccess, the export marker) stay out: the ZIP is the site's own files.
                if ($file->isFile() && !$file->isLink() && !preg_match('~(^|/)\.~', $rel)) $zip->addFile($file->getPathname(), $rel);
            }
            if ($zip->numFiles === 0) $errors[] = t('transfer_nothing_to_save');
            $zip->close();
        } else $errors[] = t('error_save_failed');
    }
    mikanBoxTransferRemoveTree($out);
    if ($errors) { @unlink($zipPath); return ['error' => implode(' / ', $errors)]; }
    return ['zip' => $zipPath];
}

function mikanBoxTransferRemoveTree(string $dir): void {
    if (!is_dir($dir) || is_link($dir)) return;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

/** The site's figures shown above Save and Read. */
function mikanBoxTransferStats(): array {
    $bytes = 0;
    $names = mikanBoxTransferMediaNames();
    foreach ($names as $name) $bytes += (int)@filesize(MEDIA_DIR . '/' . $name);
    $size = fn(int $b) => $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : round($b / 1024, 1) . ' KB';
    $stats = ['pages' => count(getFileList(POSTS_DIR)), 'components' => count(getFileList(COMPONENTS_DIR))];
    if (function_exists('getDb')) {
        try { $stats['users'] = (int)getDb()->querySingle('SELECT COUNT(*) FROM users'); } catch (Throwable $e) {}
    }
    $stats['media'] = count($names);
    $stats['media_size'] = $size($bytes);
    if (defined('DB_FILE') && is_file(DB_FILE)) $stats['database'] = $size((int)filesize(DB_FILE));
    return $stats;
}

function mikanBoxTransferMode(): string {
    $mode = (string)($_POST['mode'] ?? '');
    return in_array($mode, MIKANBOX_TRANSFER_MODES, true) ? $mode : 'add';
}

/** True when the record may be written in this mode: add skips what exists. */
function mikanBoxTransferMayWrite(string $mode, bool $exists): bool {
    return $mode !== 'add' || !$exists;
}

/**
 * One part of the data: {settings?, pages:[{id,data}], components:[{id,data}], revisions:[{id,items}]}.
 * Returns counts; IDs that break the ID rule are listed in "refused".
 */
function mikanBoxTransferImportData(string $mode, array $part): array {
    $result = ['pages' => 0, 'components' => 0, 'skipped' => 0, 'refused' => [], 'settings' => false];
    if (count((array)($part['pages'] ?? [])) + count((array)($part['components'] ?? [])) + count((array)($part['revisions'] ?? [])) > 1000) return $result + ['error' => true];
    $history = mikanBoxTransferHasHistory();
    $editor = $_SESSION['admin_username'] ?? 'admin';
    $added = [];
    // One record for the reading (repeated parts merge into it), not a line per page.
    $GLOBALS['mikanbox_audit_quiet'] = true;
    try {
    if ($mode !== 'add' && isset($part['settings']) && is_array($part['settings'])) {
        $settings = loadSettings();
        foreach (mikanBoxTransferSettings($part['settings']) as $key => $value) {
            if (!(is_scalar($value) || $value === null) || (is_string($value) && strlen($value) > 300000)) continue;
            // Values are checked as the settings screen checks them.
            if ($key === 'tracking_id' && !mikanBoxTrackingIdValid((string)$value)) continue;
            if (in_array($key, ['pages_per_page', 'media_per_page'], true) && !(is_numeric($value) && (int)$value >= 1 && (int)$value <= 1000)) continue;
            $settings[$key] = $value;
        }
        $result['settings'] = (bool)saveSettings($settings);
    }
    foreach (['pages' => POSTS_DIR, 'components' => COMPONENTS_DIR] as $kind => $dir) {
        foreach (($part[$kind] ?? []) as $item) {
            $id = is_array($item) && is_string($item['id'] ?? null) ? $item['id'] : '';
            $data = is_array($item['data'] ?? null) ? $item['data'] : null;
            if ($id === '' || $data === null) continue;
            $exists = mikanBoxLoadDataRaw($dir, $id) !== null;
            if (!$exists && mikanBoxValidateNewId($id, $kind === 'pages' ? 'page' : 'component') !== null) { $result['refused'][] = $id; continue; }
            if (!mikanBoxTransferMayWrite($mode, $exists)) { $result['skipped']++; continue; }
            if ($kind === 'pages') {
                $data = mikanBoxNormalizeLegacyPage($data);
                if ($exists && $mode === 'overwrite' && $history) createRevision($id, $editor);
                if ($mode === 'replace' && $history) deleteRevisionsOfPost($id);   // the file's history (or none) takes its place
            }
            if (!saveData($dir, $id, $data)) continue;
            $result[$kind]++;
            if ($kind === 'pages' && !$exists) $added[$id] = true;
        }
    }
    } finally {
        $GLOBALS['mikanbox_audit_quiet'] = false;
    }
    if ($result['pages'] || $result['components'] || $result['settings']) mikanBoxAudit('data_imported', ['target' => $mode]);
    if (function_exists('mikanBoxIntegrityDataKnown')) { mikanBoxIntegrityDataKnown($GLOBALS['mikanbox_audit_quiet_after'] ?? []); $GLOBALS['mikanbox_audit_quiet_after'] = []; }
    // History from the file (sent in the same part as its page): for pages the file adds, and for every page when replacing.
    if ($history) {
        foreach (($part['revisions'] ?? []) as $item) {
            $id = is_string($item['id'] ?? null) ? $item['id'] : '';
            if ($id === '' || !is_array($item['items'] ?? null) || mikanBoxLoadDataRaw(POSTS_DIR, $id) === null) continue;
            if ($mode === 'replace' || isset($added[$id])) mikanBoxImportRevisions($id, $item['items']);
        }
    }
    return $result;
}

/** One media file. Returns 'saved', 'skipped' or an error message. */
function mikanBoxTransferImportMedia(string $mode, string $name, string $tmpPath): string {
    if (!mediaValidFilename($name) || !is_file($tmpPath)) return t('err_transfer_media_name');
    $content = (string)file_get_contents($tmpPath);
    if (strlen($content) > 10000000 || !mediaValidCodeContent($name, $content)) return t('err_transfer_media_name');
    $target = MEDIA_DIR . '/' . $name;
    if (!mikanBoxTransferMayWrite($mode, is_file($target))) return 'skipped';
    if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'svg') $content = sanitizeSvgContent($content);
    if (!is_dir(MEDIA_DIR)) mkdir(MEDIA_DIR, 0777, true);
    if (file_put_contents($target, $content) === false) return t('err_upload_failed');
    mikanBoxAudit('data_imported', ['target' => $mode]);
    if (function_exists('mikanBoxIntegrityMediaChanged')) mikanBoxIntegrityMediaChanged($name, false, '');
    return 'saved';
}

/**
 * The end of a reading. Replace deletes what the file did not have, for the kinds it held.
 * $keep: {pages:[ids], components:[ids], media:[names]} — null for a kind the file did not hold.
 */
function mikanBoxTransferFinish(string $mode, array $keep): array {
    $deleted = ['pages' => 0, 'components' => 0, 'media' => 0];
    if ($mode !== 'replace') return $deleted;
    foreach (['pages' => POSTS_DIR, 'components' => COMPONENTS_DIR] as $kind => $dir) {
        if (!is_array($keep[$kind] ?? null)) continue;
        $wanted = array_flip(array_map('strval', $keep[$kind]));
        foreach (getFileList($dir) as $id) {
            if (isset($wanted[$id])) continue;
            if ($kind === 'pages' && function_exists('deleteRevisionsOfPost')) deleteRevisionsOfPost($id);
            if (deleteData($dir, $id)) $deleted[$kind]++;
        }
    }
    if (is_array($keep['media'] ?? null)) {
        $wanted = array_flip(array_map('strval', $keep['media']));
        foreach (mikanBoxTransferMediaNames() as $name) {
            if (!isset($wanted[$name]) && @unlink(MEDIA_DIR . '/' . $name)) { $deleted['media']++; if (function_exists('mikanBoxIntegrityMediaChanged')) mikanBoxIntegrityMediaChanged($name, true, ''); }
        }
    }
    mikanBoxAudit('data_restored', ['target' => 'import-replace']);
    return $deleted;
}

/** The admin actions of the data tool (save_action transfer_*). Ends the request. */
function mikanBoxTransferHandle(string $action): void {
    if ($action === 'transfer_export') {
        $contents = (string)($_POST['contents'] ?? 'both');
        if ($contents === 'database' && function_exists('mikanBoxDownloadDatabase')) mikanBoxDownloadDatabase();
        $filter = ($_POST['match_filter'] ?? '') === '1' ? mikanBoxTransferFilter($_POST['filter_json'] ?? '') : null;   // "match the filtered lists"
        mikanBoxTransferExport($contents, ($_POST['include_revisions'] ?? '') === '1', $filter);
        $_SESSION['admin_message'] = $contents === 'media' && !mikanBoxTransferMediaNames() ? t('transfer_nothing_to_save') : t('error_save_failed');
        header('Location: admin.php#backup');
        exit;
    }
    if ($action === 'transfer_site_export') {
        $built = mikanBoxTransferBuildSite(($_POST['relative'] ?? '') === '1');
        if (isset($built['zip'])) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="mikanbox-site-' . date('Y-m-d') . '.zip"');
            header('Content-Length: ' . filesize($built['zip']));
            readfile($built['zip']);
            @unlink($built['zip']);
            exit;
        }
        $_SESSION['admin_message'] = $built['error'];
        header('Location: admin.php#backup');
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    $mode = mikanBoxTransferMode();
    $reply = fn(array $data) => print(json_encode($data, JSON_UNESCAPED_UNICODE));
    if ($action === 'transfer_import_data') {
        $raw = (string)($_POST['part'] ?? '');
        $part = strlen($raw) <= MIKANBOX_TRANSFER_PART_BYTES ? json_decode($raw, true) : null;
        if (!is_array($part)) { http_response_code(400); $reply(['success' => false, 'message' => t('err_transfer_file')]); exit; }
        $result = mikanBoxTransferImportData($mode, $part);
        $reply(empty($result['error']) ? ['success' => true] + $result : ['success' => false, 'message' => t('err_transfer_file')]);
        exit;
    }
    if ($action === 'transfer_import_media') {
        $file = $_FILES['file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $reply(['success' => false, 'message' => ($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE ? t('err_file_size') : t('err_upload_failed')]);
            exit;
        }
        $outcome = mikanBoxTransferImportMedia($mode, (string)($_POST['name'] ?? ''), (string)$file['tmp_name']);
        $reply(in_array($outcome, ['saved', 'skipped'], true) ? ['success' => true, 'outcome' => $outcome] : ['success' => false, 'message' => $outcome]);
        exit;
    }
    if ($action === 'transfer_import_finish') {
        $keep = json_decode((string)($_POST['keep'] ?? '{}'), true);
        $reply(['success' => true, 'deleted' => mikanBoxTransferFinish($mode, is_array($keep) ? $keep : [])]);
        exit;
    }
    http_response_code(400);
    $reply(['success' => false, 'message' => t('err_transfer_file')]);
    exit;
}
