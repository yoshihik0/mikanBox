<?php
if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(403); exit; }

function mikanBoxMediaLocationIsManual(): bool {
    return defined('MIKANBOX_MEDIA_DIR') || defined('MIKANBOX_MEDIA_URL');
}

function mikanBoxMediaDirectoryForOutput(string $output): string {
    $root = realpath(dirname(CORE_DIR)) ?: dirname(CORE_DIR);
    foreach (explode('/', str_replace('\\', '/', $output)) as $name) {
        if ($name === '' || $name === '.') continue;
        if ($name === '..') {
            $root = dirname($root);
            continue;
        }
        $path = rtrim($root, '/') . '/' . $name;
        $resolved = realpath($path);
        if (is_link($path) && $resolved === false) throw new RuntimeException('Invalid publication path.');
        $root = $resolved ?: $path;
    }
    return rtrim($root, '/') . '/media';
}

function mikanBoxMediaLocationSettings(callable $load): array {
    // Keep readers and uploads on the selected directory for the whole request.
    $lock = @fopen(DATA_DIR . '/.media-location.lock', 'c');
    if ($lock && flock($lock, LOCK_SH)) $GLOBALS['mikanbox_media_location_lock'] = $lock;
    $settings = $load();
    if (!is_file(DATA_DIR . '/.media-move.json')) return $settings;
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Could not recover the media location.');
    try {
        $settings = $load();
        mikanBoxRecoverMediaMove($settings);
        return $settings;
    } finally {
        flock($lock, LOCK_SH);
    }
}

function mikanBoxRecoverMediaMove(array $settings): void {
    $journal = DATA_DIR . '/.media-move.json';
    if (!is_file($journal)) return;
    $move = json_decode((string)file_get_contents($journal), true, 512, JSON_THROW_ON_ERROR);
    $committed = ($settings['media_output'] ?? null) === $move['settings'];
    if (!$committed && !is_dir($move['from']) && is_dir($move['to'])) {
        if (!@rename($move['to'], $move['from'])) throw new RuntimeException('Could not recover the media directory.');
    }
    if (!is_dir($committed ? $move['to'] : $move['from'])) {
        throw new RuntimeException('The media directory could not be found after moving.');
    }
    unlink($journal);
}

function mikanBoxActiveMediaDirectory(array $settings): string {
    $path = defined('MIKANBOX_MEDIA_DIR') ? MIKANBOX_MEDIA_DIR : null;
    $output = $settings['media_output']['directory'] ?? null;
    if (!defined('MIKANBOX_MEDIA_DIR') && is_string($output)) $path = mikanBoxMediaDirectoryForOutput($output);
    return mikanBoxResolveMediaDirectory($path, CORE_DIR, DATA_DIR);
}

function mikanBoxActiveMediaUrl(array $settings): string {
    $url = defined('MIKANBOX_MEDIA_URL') ? MIKANBOX_MEDIA_URL : ($settings['media_output']['url'] ?? '');
    return mikanBoxNormalizeMediaUrl($url);
}

/** Apply the server form's media choice; package exports leave the live placement alone. */
function mikanBoxApplySsgMediaLocation(array $settings, array $input): array {
    if (($settings['ssg_mode'] ?? 'server') !== 'server' || !isset($input['ssg_media_choice']) || mikanBoxMediaLocationIsManual()) return $settings;
    $enabled = ($input['ssg_media_in_output'] ?? '') === '1';
    $previous = $settings['media_output'] ?? null;
    if (!$enabled && !is_string($previous['directory'] ?? null)) return $settings;
    $output = $enabled ? (string)($settings['ssg_dir'] ?? '') : null;
    $rootUrl = rtrim((string)($settings['ssg_root_url'] ?? ''), '/');
    try {
        $adminUrl = mikanBoxNormalizeMediaUrl(mikanBoxDynamicSiteUrl($settings) . '/media/');
        if ($enabled && $output !== '' && $rootUrl === '') throw new RuntimeException();
        $url = $enabled && $rootUrl !== '' ? mikanBoxNormalizeMediaUrl($rootUrl . '/media/') : $adminUrl;
    } catch (RuntimeException $e) {
        throw new RuntimeException(t('err_ssg_media_url'));
    }
    if ($output === ($previous['directory'] ?? null) && $url === ($previous['url'] ?? '')) return $settings;
    $oldUrls = array_values(array_diff(array_unique(array_filter(array_merge(
        $previous['previous_urls'] ?? [], [$previous['url'] ?? $adminUrl]
    ))), [$url]));
    $settings['media_output'] = ['directory' => $output, 'url' => $url, 'previous_urls' => $oldUrls];

    $lock = $GLOBALS['mikanbox_media_location_lock'] ?? null;
    if (!is_resource($lock)) throw new RuntimeException(t('err_ssg_media_busy'));
    flock($lock, LOCK_UN);
    try {
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException(t('err_ssg_media_busy'));
        }
        $current = loadSettings();
        if (($current['media_output'] ?? null) !== $previous) {
            throw new RuntimeException(t('err_ssg_media_busy'));
        }
        $settings = array_replace($current, array_intersect_key($settings, array_flip([
            'ssg_dir', 'ssg_structure', 'ssg_server_structure', 'ssg_export_structure',
            'ssg_mode', 'ssg_link_mode', 'ssg_root_url', 'media_output',
        ])));
        $source = realpath(MEDIA_DIR);
        $destination = $enabled ? mikanBoxMediaDirectoryForOutput($output) : dirname(CORE_DIR) . '/media';
        $sourceHint = is_string($previous['directory'] ?? null)
            ? mikanBoxMediaDirectoryForOutput($previous['directory']) : dirname(CORE_DIR) . '/media';
        $move = $source !== $destination;
        if ($move) {
            try {
                mikanBoxValidateMediaDirectoryPath($destination, CORE_DIR, DATA_DIR);
            } catch (RuntimeException $e) {
                throw new RuntimeException(t('err_ssg_media_destination'));
            }
            $from = strtolower(rtrim((string)$source, '/')) . '/';
            $to = strtolower(rtrim($destination, '/')) . '/';
            if ($source === false || is_link($sourceHint) || is_link($destination)
                || str_starts_with($from, $to) || str_starts_with($to, $from)
                || (file_exists($destination) && (!is_dir($destination) || !@rmdir($destination)))) {
                throw new RuntimeException(t('err_ssg_media_destination'));
            }
            $parent = dirname($destination);
            if (!is_dir($parent) && !@mkdir($parent, 0755, true) && !is_dir($parent)) {
                throw new RuntimeException(t('err_ssg_media_move'));
            }
            $journal = DATA_DIR . '/.media-move.json';
            $temporary = tempnam(DATA_DIR, '.media-move-');
            $record = json_encode(['from' => $source, 'to' => $destination, 'settings' => $settings['media_output']], JSON_THROW_ON_ERROR);
            if ($temporary === false || file_put_contents($temporary, $record) !== strlen($record) || !@rename($temporary, $journal)) {
                if ($temporary !== false) @unlink($temporary);
                throw new RuntimeException(t('err_ssg_media_move'));
            }
            if (!@rename($source, $destination)) {
                unlink($journal);
                throw new RuntimeException(t('err_ssg_media_move'));
            }
        }
        try {
            if (!saveSettings($settings)) throw new RuntimeException(t('err_save_failed'));
        } finally {
            if ($move) mikanBoxRecoverMediaMove(loadSettings());
        }
        $GLOBALS['mikanbox_settings'] = $settings;
        $GLOBALS['mikanbox_media_directory'] = $destination;
        $GLOBALS['mikanbox_media_url'] = $url;
        return $settings;
    } finally {
        flock($lock, LOCK_SH);
    }
}
