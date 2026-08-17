<?php
defined('MIKANBOX') or die();

/**
 * mikanBox self-updater.
 *
 * Only program files are replaced. DATA_DIR, media, generated static files,
 * site-root/data/media .htaccess files and local connection settings are
 * never included. The core mikanBox/.htaccess is a versioned program file
 * because it provides required routing and Authorization-header forwarding.
 */

function mikanBoxUpdateRemoveTree(string $path): void {
    if (!file_exists($path)) return;
    if (is_file($path) || is_link($path)) {
        @unlink($path);
        return;
    }
    $items = scandir($path);
    if ($items === false) return;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        mikanBoxUpdateRemoveTree($path . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($path);
}

function mikanBoxUpdateCopyFile(string $source, string $target): bool {
    $parent = dirname($target);
    if (!is_dir($parent) && !@mkdir($parent, 0777, true) && !is_dir($parent)) {
        return false;
    }
    $temporary = $target . '.mikanbox-new-' . bin2hex(random_bytes(4));
    if (!@copy($source, $temporary)) {
        @unlink($temporary);
        return false;
    }
    $mode = file_exists($target) ? (@fileperms($target) & 0777) : 0644;
    @chmod($temporary, $mode ?: 0644);
    if (!@rename($temporary, $target)) {
        @unlink($temporary);
        return false;
    }
    return hash_file('sha256', $source) === hash_file('sha256', $target);
}

function mikanBoxUpdateDownload(string $url, string $destination): bool {
    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        $output = @fopen($destination, 'wb');
        if ($handle !== false && $output !== false) {
            curl_setopt_array($handle, [
                CURLOPT_FILE => $output,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 90,
                CURLOPT_USERAGENT => 'mikanBox-updater',
                CURLOPT_FAILONERROR => true,
            ]);
            $success = curl_exec($handle) === true;
            fclose($output);
            if ($success && is_file($destination) && filesize($destination) > 0) {
                return true;
            }
        } else {
            if (is_resource($output)) fclose($output);
        }
        @unlink($destination);
    }

    $context = stream_context_create(['http' => [
        'timeout' => 90,
        'follow_location' => 1,
        'header' => "User-Agent: mikanBox-updater\r\n",
    ]]);
    $input = @fopen($url, 'rb', false, $context);
    $output = @fopen($destination, 'wb');
    if ($input === false || $output === false) {
        if (is_resource($input)) fclose($input);
        if (is_resource($output)) fclose($output);
        @unlink($destination);
        return false;
    }
    $copied = stream_copy_to_stream($input, $output);
    fclose($input);
    fclose($output);
    return $copied !== false && $copied > 0;
}

function mikanBoxUpdateProgramFiles(string $packageRoot): array {
    $relativeFiles = [];
    foreach (['index.php'] as $relative) {
        if (is_file($packageRoot . '/' . $relative)) $relativeFiles[] = $relative;
    }
    foreach (['.htaccess', 'admin.php', 'admin.css', 'ai-question.js', 'build.php', 'config.php', 'convert.php', 'mcp.php', 'public-mcp.php'] as $filename) {
        $relative = 'mikanBox/' . $filename;
        if (is_file($packageRoot . '/' . $relative)) $relativeFiles[] = $relative;
    }
    foreach (['docs', 'lib', 'views', 'lang'] as $directory) {
        $sourceDir = $packageRoot . '/mikanBox/' . $directory;
        if (!is_dir($sourceDir)) continue;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) continue;
            $relativeFiles[] = str_replace('\\', '/', substr($file->getPathname(), strlen($packageRoot) + 1));
        }
    }
    $relativeFiles = array_values(array_unique($relativeFiles));
    sort($relativeFiles);

    // Replace the files that run the updater itself last.
    foreach (['mikanBox/lib/updater.php', 'mikanBox/admin.php', 'mikanBox/config.php'] as $lastFile) {
        $index = array_search($lastFile, $relativeFiles, true);
        if ($index !== false) {
            unset($relativeFiles[$index]);
            $relativeFiles[] = $lastFile;
        }
    }
    return array_values($relativeFiles);
}

/**
 * Release archives always use mikanBox/ as the core directory name, while an
 * installed site may rename that directory.
 */
function mikanBoxUpdateTargetRelativePath(string $packageRelative, string $coreDir): string {
    if ($packageRelative === 'mikanBox') return basename($coreDir);
    if (str_starts_with($packageRelative, 'mikanBox/')) {
        return basename($coreDir) . substr($packageRelative, strlen('mikanBox'));
    }
    return $packageRelative;
}

function mikanBoxUpdateReadVersion(string $configPath): ?string {
    $source = @file_get_contents($configPath);
    if ($source && preg_match('/define\\s*\\(\\s*[\'"]MIKANBOX_VERSION[\'"]\\s*,\\s*[\'"]([^\'"]+)[\'"]\\s*\\)/', $source, $match)) {
        return $match[1];
    }
    return null;
}

function mikanBoxUpdateFetchText(string $url, int $timeout = 10): ?string {
    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        if ($handle !== false) {
            curl_setopt_array($handle, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => 'mikanBox-admin',
                CURLOPT_HTTPHEADER => ['Accept: application/vnd.github+json'],
                CURLOPT_FAILONERROR => true,
            ]);
            $body = curl_exec($handle);
            if (is_string($body) && $body !== '') return $body;
        }
    }

    $context = stream_context_create(['http' => [
        'timeout' => $timeout,
        'ignore_errors' => false,
        'follow_location' => 1,
        'header' => "User-Agent: mikanBox-admin\r\nAccept: application/vnd.github+json\r\n",
    ]]);
    $body = @file_get_contents($url, false, $context);
    return is_string($body) && $body !== '' ? $body : null;
}

function mikanBoxFetchLatestVersion(string $githubRepo): array {
    $candidates = [];

    $releaseJson = mikanBoxUpdateFetchText("https://api.github.com/repos/{$githubRepo}/releases/latest");
    if ($releaseJson) {
        $release = json_decode($releaseJson, true);
        if (!empty($release['tag_name'])) {
            $candidates[] = ['version' => $release['tag_name'], 'ref' => $release['tag_name']];
        }
    }

    $tagsJson = mikanBoxUpdateFetchText("https://api.github.com/repos/{$githubRepo}/tags");
    if ($tagsJson) {
        $tags = json_decode($tagsJson, true);
        foreach (is_array($tags) ? $tags : [] as $tag) {
            if (!empty($tag['name'])) $candidates[] = ['version' => $tag['name'], 'ref' => $tag['name']];
        }
    }

    $configPhp = mikanBoxUpdateFetchText(
        "https://raw.githubusercontent.com/{$githubRepo}/main/mikanBox/config.php"
    );
    if ($configPhp && preg_match('/define\\s*\\(\\s*[\'"]MIKANBOX_VERSION[\'"]\\s*,\\s*[\'"]([^\'"]+)[\'"]\\s*\\)/', $configPhp, $match)) {
        $candidates[] = ['version' => $match[1], 'ref' => 'main'];
    }

    if (!$candidates) return ['version' => null, 'ref' => null];
    usort($candidates, fn($a, $b) => version_compare(
        ltrim($b['version'], 'vV'),
        ltrim($a['version'], 'vV')
    ));
    return $candidates[0];
}

function mikanBoxUpdateRestoreFromDirectory(string $backupDir, string $siteRoot): bool {
    $manifestPath = $backupDir . '/manifest.json';
    $manifest = is_file($manifestPath) ? json_decode((string)file_get_contents($manifestPath), true) : null;
    if (!is_array($manifest) || empty($manifest['files']) || !is_array($manifest['files'])) {
        return false;
    }

    $success = true;
    foreach (array_reverse($manifest['files']) as $file) {
        $relative = $file['path'] ?? '';
        if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/')) {
            $success = false;
            continue;
        }
        $target = $siteRoot . '/' . $relative;
        if (!empty($file['existed'])) {
            $source = $backupDir . '/files/' . $relative . '.bak';
            if (!is_file($source) || !mikanBoxUpdateCopyFile($source, $target)) $success = false;
        } elseif (file_exists($target) && !@unlink($target)) {
            $success = false;
        }
    }
    return $success;
}

function mikanBoxGetUpdateBackup(string $dataDir): ?array {
    $backupRoot = $dataDir . '/update-backups';
    if (!is_dir($backupRoot)) return null;
    $candidates = [];
    foreach (scandir($backupRoot) ?: [] as $item) {
        if ($item === '.' || $item === '..' || str_starts_with($item, '.pending-')) continue;
        $directory = $backupRoot . '/' . $item;
        $manifestPath = $directory . '/manifest.json';
        if (!is_file($manifestPath)) continue;
        $manifest = json_decode((string)file_get_contents($manifestPath), true);
        if (!is_array($manifest) || empty($manifest['from_version'])) continue;
        $manifest['directory'] = $directory;
        $candidates[] = $manifest;
    }
    if (!$candidates) return null;
    usort($candidates, fn($a, $b) => ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0));
    return $candidates[0];
}

function mikanBoxUpdateBackupMatchesVersion(?array $backup, string $currentVersion): bool {
    if (!$backup || empty($backup['to_version'])) return false;
    return version_compare(
        ltrim((string)$backup['to_version'], 'vV'),
        ltrim($currentVersion, 'vV'),
        '=='
    );
}

function mikanBoxInstallUpdate(
    string $githubRepo,
    string $currentVersion,
    string $coreDir,
    string $dataDir,
    ?string $expectedVersion = null,
    string $archiveRef = 'main'
): array {
    if (!class_exists('ZipArchive')) return ['success' => false, 'code' => 'zip_unavailable'];
    if (function_exists('set_time_limit')) @set_time_limit(120);

    $siteRoot = dirname($coreDir);
    $workRoot = $dataDir . '/update-temp';
    $backupRoot = $dataDir . '/update-backups';
    $token = date('YmdHis') . '-' . bin2hex(random_bytes(4));
    $workDir = $workRoot . '/' . $token;
    $pendingBackup = $backupRoot . '/.pending-' . $token;
    if ((!is_dir($workDir) && !@mkdir($workDir, 0777, true)) ||
        (!is_dir($pendingBackup . '/files') && !@mkdir($pendingBackup . '/files', 0777, true))) {
        return ['success' => false, 'code' => 'backup_failed'];
    }

    if ($archiveRef !== 'main' && !preg_match('/^[0-9A-Za-z._-]+$/', $archiveRef)) {
        mikanBoxUpdateRemoveTree($workDir);
        mikanBoxUpdateRemoveTree($pendingBackup);
        return ['success' => false, 'code' => 'archive_invalid'];
    }
    $zipPath = $workDir . '/update.zip';
    $archiveType = $archiveRef === 'main' ? 'heads' : 'tags';
    $archiveUrl = 'https://github.com/' . $githubRepo . '/archive/refs/' . $archiveType . '/' . rawurlencode($archiveRef) . '.zip';
    if (!mikanBoxUpdateDownload($archiveUrl, $zipPath)) {
        mikanBoxUpdateRemoveTree($workDir);
        mikanBoxUpdateRemoveTree($pendingBackup);
        return ['success' => false, 'code' => 'download_failed'];
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        mikanBoxUpdateRemoveTree($workDir);
        mikanBoxUpdateRemoveTree($pendingBackup);
        return ['success' => false, 'code' => 'archive_invalid'];
    }
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = str_replace('\\', '/', (string)$zip->getNameIndex($i));
        if ($name === '' || str_starts_with($name, '/') || preg_match('~(^|/)\\.\\.(/|$)~', $name)) {
            $zip->close();
            mikanBoxUpdateRemoveTree($workDir);
            mikanBoxUpdateRemoveTree($pendingBackup);
            return ['success' => false, 'code' => 'archive_invalid'];
        }
    }
    $extractDir = $workDir . '/files';
    @mkdir($extractDir, 0777, true);
    $extracted = $zip->extractTo($extractDir);
    $zip->close();
    if (!$extracted) {
        mikanBoxUpdateRemoveTree($workDir);
        mikanBoxUpdateRemoveTree($pendingBackup);
        return ['success' => false, 'code' => 'archive_invalid'];
    }

    $packageRoot = null;
    foreach (scandir($extractDir) ?: [] as $item) {
        $candidate = $extractDir . '/' . $item;
        if ($item !== '.' && $item !== '..' && is_file($candidate . '/mikanBox/config.php')) {
            $packageRoot = $candidate;
            break;
        }
    }
    $newVersion = $packageRoot ? mikanBoxUpdateReadVersion($packageRoot . '/mikanBox/config.php') : null;
    if (!$packageRoot || !$newVersion) {
        mikanBoxUpdateRemoveTree($workDir);
        mikanBoxUpdateRemoveTree($pendingBackup);
        return ['success' => false, 'code' => 'archive_invalid'];
    }
    if ($expectedVersion !== null &&
        version_compare(ltrim($newVersion, 'vV'), ltrim($expectedVersion, 'vV'), '!=')) {
        mikanBoxUpdateRemoveTree($workDir);
        mikanBoxUpdateRemoveTree($pendingBackup);
        return ['success' => false, 'code' => 'archive_invalid'];
    }
    if (version_compare(ltrim($newVersion, 'vV'), ltrim($currentVersion, 'vV'), '<=')) {
        mikanBoxUpdateRemoveTree($workDir);
        mikanBoxUpdateRemoveTree($pendingBackup);
        return ['success' => false, 'code' => 'no_update'];
    }

    $programFiles = mikanBoxUpdateProgramFiles($packageRoot);
    if (!$programFiles) {
        mikanBoxUpdateRemoveTree($workDir);
        mikanBoxUpdateRemoveTree($pendingBackup);
        return ['success' => false, 'code' => 'archive_invalid'];
    }

    $manifestFiles = [];
    foreach ($programFiles as $relative) {
        $targetRelative = mikanBoxUpdateTargetRelativePath($relative, $coreDir);
        $target = $siteRoot . '/' . $targetRelative;
        $existed = is_file($target);
        $manifestFiles[] = ['path' => $targetRelative, 'existed' => $existed];
        if ($existed) {
            // The .bak suffix prevents backed-up PHP files from being executed
            // even on servers where data/.htaccess is not honored.
            $backupFile = $pendingBackup . '/files/' . $targetRelative . '.bak';
            if (!mikanBoxUpdateCopyFile($target, $backupFile)) {
                mikanBoxUpdateRemoveTree($workDir);
                mikanBoxUpdateRemoveTree($pendingBackup);
                return ['success' => false, 'code' => 'backup_failed'];
            }
        }
    }
    $manifest = [
        'from_version' => $currentVersion,
        'to_version' => $newVersion,
        'created_at' => time(),
        'github_repo' => $githubRepo,
        'files' => $manifestFiles,
    ];
    @file_put_contents($pendingBackup . '/.htaccess', "Require all denied\nDeny from all\n");
    @file_put_contents($pendingBackup . '/index.html', '');
    if (@file_put_contents(
        $pendingBackup . '/manifest.json',
        json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    ) === false) {
        mikanBoxUpdateRemoveTree($workDir);
        mikanBoxUpdateRemoveTree($pendingBackup);
        return ['success' => false, 'code' => 'backup_failed'];
    }

    $installed = true;
    foreach ($programFiles as $relative) {
        $targetRelative = mikanBoxUpdateTargetRelativePath($relative, $coreDir);
        if (!mikanBoxUpdateCopyFile(
            $packageRoot . '/' . $relative,
            $siteRoot . '/' . $targetRelative
        )) {
            $installed = false;
            break;
        }
    }
    if (!$installed || mikanBoxUpdateReadVersion($coreDir . '/config.php') !== $newVersion) {
        mikanBoxUpdateRestoreFromDirectory($pendingBackup, $siteRoot);
        mikanBoxUpdateRemoveTree($workDir);
        mikanBoxUpdateRemoveTree($pendingBackup);
        return ['success' => false, 'code' => 'install_failed'];
    }

    $stableBackup = $backupRoot . '/' . preg_replace('/[^0-9A-Za-z._-]/', '_', $currentVersion);
    foreach (scandir($backupRoot) ?: [] as $item) {
        if ($item === '.' || $item === '..' || $item === basename($pendingBackup)) continue;
        mikanBoxUpdateRemoveTree($backupRoot . '/' . $item);
    }
    mikanBoxUpdateRemoveTree($stableBackup);
    if (!@rename($pendingBackup, $stableBackup)) {
        mikanBoxUpdateRestoreFromDirectory($pendingBackup, $siteRoot);
        mikanBoxUpdateRemoveTree($workDir);
        mikanBoxUpdateRemoveTree($pendingBackup);
        return ['success' => false, 'code' => 'backup_failed'];
    }

    mikanBoxUpdateRemoveTree($workDir);
    return ['success' => true, 'version' => $newVersion];
}

function mikanBoxRestorePreviousVersion(string $coreDir, string $dataDir): array {
    $backup = mikanBoxGetUpdateBackup($dataDir);
    if (!$backup || empty($backup['directory'])) {
        return ['success' => false, 'code' => 'restore_missing'];
    }
    $currentVersion = mikanBoxUpdateReadVersion($coreDir . '/config.php');
    if (!$currentVersion || !mikanBoxUpdateBackupMatchesVersion($backup, $currentVersion)) {
        return ['success' => false, 'code' => 'restore_stale'];
    }

    $backupDir = $backup['directory'];
    $coreConfigRelative = basename($coreDir) . '/config.php';
    $previousConfigBackup = $backupDir . '/files/' . $coreConfigRelative . '.bak';
    $previousConfig = is_file($previousConfigBackup)
        ? (string)file_get_contents($previousConfigBackup)
        : '';
    $previousUsesLegacyData = (bool)preg_match(
        '/define\s*\(\s*[\'"]DATA_DIR[\'"]\s*,\s*__DIR__\s*\.\s*[\'"]\/data[\'"]\s*\)/',
        $previousConfig
    );

    $legacyDataDir = rtrim($coreDir, '/\\') . '/data';
    $movedDataForRestore = false;
    if ($previousUsesLegacyData
        && rtrim($dataDir, '/\\') !== rtrim($legacyDataDir, '/\\')) {
        if (file_exists($legacyDataDir)) {
            return ['success' => false, 'code' => 'restore_failed'];
        }

        $normalizedDataDir = rtrim($dataDir, '/\\');
        if (!str_starts_with($backupDir, $normalizedDataDir . '/')) {
            return ['success' => false, 'code' => 'restore_failed'];
        }
        $relativeBackupDir = substr($backupDir, strlen($normalizedDataDir));
        if ($relativeBackupDir === false
            || !@rename(rtrim($dataDir, '/\\'), $legacyDataDir)) {
            return ['success' => false, 'code' => 'restore_failed'];
        }
        $movedDataForRestore = true;
        $backupDir = $legacyDataDir . $relativeBackupDir;
    }

    if (!mikanBoxUpdateRestoreFromDirectory($backupDir, dirname($coreDir))) {
        if ($movedDataForRestore && !file_exists($dataDir)) {
            @rename($legacyDataDir, rtrim($dataDir, '/\\'));
        }
        return ['success' => false, 'code' => 'restore_failed'];
    }
    return ['success' => true, 'version' => $backup['from_version']];
}
