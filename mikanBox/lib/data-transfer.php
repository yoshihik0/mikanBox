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
const MIKANBOX_TRANSFER_PART_BYTES = 8000000;

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

/** Builds the ZIP (contents: both|data|media) in the data folder. Null when there is nothing to save or it fails. */
function mikanBoxTransferBuildZip(string $contents, bool $withHistory): ?string {
    if (!in_array($contents, ['both', 'data', 'media'], true) || !class_exists('ZipArchive')) return null;
    $tmp = DATA_DIR . '/transfer_' . bin2hex(random_bytes(8)) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return null;
    $json = fn($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($contents !== 'media') {
        $zip->addFromString('data/settings.json', $json((object)mikanBoxTransferSettings(loadSettings())));
        foreach (getFileList(POSTS_DIR) as $id) {
            $data = loadData(POSTS_DIR, $id);
            if (!is_array($data)) continue;
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
        foreach (getFileList(COMPONENTS_DIR) as $id) {
            $data = loadData(COMPONENTS_DIR, $id);
            if (is_array($data)) $zip->addFromString('data/components/' . $id . '.json', $json($data));
        }
    }
    if ($contents !== 'data') {
        foreach (mikanBoxTransferMediaNames() as $name) $zip->addFile(MEDIA_DIR . '/' . $name, 'media/' . $name);
    }
    if ($zip->numFiles === 0) { $zip->close(); @unlink($tmp); return null; }   // nothing to save (no media yet)
    if (!$zip->close() || !is_file($tmp)) { @unlink($tmp); return null; }
    return $tmp;
}

/** Saves one ZIP and sends it. Returns only when there is nothing to send. */
function mikanBoxTransferExport(string $contents, bool $withHistory): void {
    $tmp = mikanBoxTransferBuildZip($contents, $withHistory);
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
            'output_mode' => 'export', 'link_mode' => $site['ssg_link_mode'], 'copy_media' => true,
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
    $history = mikanBoxTransferHasHistory();
    $editor = $_SESSION['admin_username'] ?? 'admin';
    $added = [];
    if ($mode !== 'add' && isset($part['settings']) && is_array($part['settings'])) {
        $settings = loadSettings();
        foreach (mikanBoxTransferSettings($part['settings']) as $key => $value) {
            if (is_scalar($value) || $value === null) $settings[$key] = $value;
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
    return file_put_contents($target, $content) !== false ? 'saved' : t('err_upload_failed');
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
            if (!isset($wanted[$name]) && @unlink(MEDIA_DIR . '/' . $name)) $deleted['media']++;
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
        mikanBoxTransferExport($contents, ($_POST['include_revisions'] ?? '') === '1');
        $_SESSION['admin_message'] = $contents === 'media' && !mikanBoxTransferMediaNames() ? t('transfer_nothing_to_save') : t('error_save_failed');
        header('Location: admin.php?view=import');
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
        header('Location: admin.php?view=import');
        exit;
    }
    header('Content-Type: application/json; charset=utf-8');
    $mode = mikanBoxTransferMode();
    $reply = fn(array $data) => print(json_encode($data, JSON_UNESCAPED_UNICODE));
    if ($action === 'transfer_import_data') {
        $raw = (string)($_POST['part'] ?? '');
        $part = strlen($raw) <= MIKANBOX_TRANSFER_PART_BYTES ? json_decode($raw, true) : null;
        if (!is_array($part)) { http_response_code(400); $reply(['success' => false, 'message' => t('err_transfer_file')]); exit; }
        $reply(['success' => true] + mikanBoxTransferImportData($mode, $part));
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
