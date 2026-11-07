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
    clearstatcache(true, $target);
    // The next request must run the new program, even with delayed/disabled
    // OPcache timestamp checks. This also applies to restore and rollback.
    if (strtolower(pathinfo($target, PATHINFO_EXTENSION)) === 'php' && function_exists('opcache_invalidate')) {
        @opcache_invalidate($target, true);
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
    foreach (['.htaccess', 'admin.php', 'admin.css', 'ai-question.js', 'build.php', 'config.php', 'convert.php', 'mcp.php', 'public-mcp.php', 'visual.php'] as $filename) {
        $relative = 'mikanBox/' . $filename;
        if (is_file($packageRoot . '/' . $relative)) $relativeFiles[] = $relative;
    }
    foreach (['docs', 'lib', 'views', 'lang', 'visual'] as $directory) {
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

/**
 * Release archives ship index.php with the default core folder name, but an
 * installed site may have renamed that folder. The update runs from admin.php,
 * which lives inside the core folder, so the real name is known for certain --
 * bake it into the staged file instead of making index.php guess at runtime.
 *
 * Returns false only when the name is known to differ and the value could not be
 * written; installing the default in that case would take the front end down.
 */
function mikanBoxUpdatePrepareFrontController(string $packageRoot, string $coreDir): bool {
    $path = $packageRoot . '/index.php';
    if (!is_file($path)) return true;
    $name = basename($coreDir);
    if ($name === '' || $name === 'mikanBox') return true;
    if (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) return false;
    $source = @file_get_contents($path);
    if ($source === false) return false;
    $count = 0;
    $patched = preg_replace_callback(
        '/\$core_dir\s*=\s*([\'"])mikanBox\1\s*;/',
        static fn(): string => "\$core_dir = '" . $name . "';",
        $source,
        1,
        $count
    );
    if ($patched === null || $count !== 1) return false;
    return @file_put_contents($path, $patched) !== false;
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

/** Only published stable releases are eligible; tags and branches alone are not releases. */
function mikanBoxUpdateRelease(string $githubRepo, ?string $tag = null): ?array {
    if (!preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~', $githubRepo)) return null;
    if ($tag !== null && !preg_match('/^v?[0-9]+\.[0-9]+\.[0-9]+$/', $tag)) return null;
    $endpoint = $tag === null ? 'latest' : 'tags/' . rawurlencode($tag);
    $release = json_decode(mikanBoxUpdateFetchText("https://api.github.com/repos/{$githubRepo}/releases/{$endpoint}") ?? '', true);
    if (!is_array($release) || ($release['draft'] ?? true) !== false ||
        ($release['prerelease'] ?? true) !== false || empty($release['published_at']) ||
        !preg_match('/^v?[0-9]+\.[0-9]+\.[0-9]+$/', $release['tag_name'] ?? '') ||
        ($tag !== null && $release['tag_name'] !== $tag)) return null;
    return $release;
}

function mikanBoxFetchLatestVersion(string $githubRepo): array {
    $release = mikanBoxUpdateRelease($githubRepo);
    return ['version' => $release['tag_name'] ?? null, 'ref' => $release['tag_name'] ?? null];
}

/** Resolve once, then download by immutable commit ID, including annotated tags. */
function mikanBoxUpdateReleaseCommit(string $githubRepo, string $tag): ?string {
    if (!mikanBoxUpdateRelease($githubRepo, $tag)) return null;
    $commit = json_decode(mikanBoxUpdateFetchText("https://api.github.com/repos/{$githubRepo}/commits/" . rawurlencode($tag)) ?? '', true);
    $sha = $commit['sha'] ?? '';
    return is_string($sha) && preg_match('/^[a-f0-9]{40}$/', $sha) ? $sha : null;
}

/** Keep this inode outside DATA_DIR, which a legacy restore may move. Never unlink it. */
function mikanBoxUpdateWithLock(string $coreDir, callable $operation): array {
    $lock = @fopen(rtrim($coreDir, '/\\') . '/.update.lock', 'c');
    if (!$lock) return ['success' => false, 'code' => 'lock_failed'];
    if (!flock($lock, LOCK_EX | LOCK_NB)) {
        fclose($lock);
        return ['success' => false, 'code' => 'busy'];
    }
    try { return $operation(); }
    finally { flock($lock, LOCK_UN); fclose($lock); }
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
    string $archiveRef = '',
    bool $allowReinstall = false
): array {
    return mikanBoxUpdateWithLock($coreDir, static function () use ($githubRepo, $coreDir, $dataDir, $expectedVersion, $archiveRef, $allowReinstall) {
        $currentVersion = mikanBoxUpdateReadVersion($coreDir . '/config.php');
        if (!$currentVersion) return ['success' => false, 'code' => 'archive_invalid'];
        return mikanBoxInstallUpdateLocked($githubRepo, $currentVersion, $coreDir, $dataDir, $expectedVersion, $archiveRef, $allowReinstall);
    });
}

function mikanBoxInstallUpdateLocked(
    string $githubRepo,
    string $currentVersion,
    string $coreDir,
    string $dataDir,
    ?string $expectedVersion = null,
    string $archiveRef = '',
    bool $allowReinstall = false
): array {
    if (!class_exists('ZipArchive')) return ['success' => false, 'code' => 'zip_unavailable'];
    if (function_exists('set_time_limit')) @set_time_limit(120);

    if ($expectedVersion === null || !preg_match('/^v?[0-9]+\.[0-9]+\.[0-9]+$/', $archiveRef) ||
        ltrim($archiveRef, 'v') !== ltrim($expectedVersion, 'v')) return ['success' => false, 'code' => 'archive_invalid'];
    $commit = mikanBoxUpdateReleaseCommit($githubRepo, $archiveRef);
    if (!$commit) return ['success' => false, 'code' => 'release_unavailable'];

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

    $zipPath = $workDir . '/update.zip';
    $archiveUrl = 'https://github.com/' . $githubRepo . '/archive/' . $commit . '.zip';
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
    // Reinstalling the current version is allowed on request: if an update left a
    // program file in a bad state, waiting for the next release to repair it is
    // not an acceptable recovery path. Older versions are still refused.
    $comparison = version_compare(ltrim($newVersion, 'vV'), ltrim($currentVersion, 'vV'));
    if ($comparison < 0 || ($comparison === 0 && !$allowReinstall)) {
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
        'commit' => $commit,
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

    if (!mikanBoxUpdatePrepareFrontController($packageRoot, $coreDir)) {
        mikanBoxUpdateRemoveTree($workDir);
        mikanBoxUpdateRemoveTree($pendingBackup);
        return ['success' => false, 'code' => 'core_dir_write_failed'];
    }

    $installed = true;
    try {
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
    } catch (Throwable $error) { $installed = false; }
    if (!$installed || mikanBoxUpdateReadVersion($coreDir . '/config.php') !== $newVersion) {
        $restored = mikanBoxUpdateRestoreFromDirectory($pendingBackup, $siteRoot);
        mikanBoxUpdateRemoveTree($workDir);
        if ($restored) mikanBoxUpdateRemoveTree($pendingBackup);
        return ['success' => false, 'code' => $restored ? 'install_failed' : 'rollback_failed'];
    }

    $stableBackup = $backupRoot . '/' . preg_replace('/[^0-9A-Za-z._-]/', '_', $currentVersion) . '-' . $token;
    if (!@rename($pendingBackup, $stableBackup)) {
        $restored = mikanBoxUpdateRestoreFromDirectory($pendingBackup, $siteRoot);
        mikanBoxUpdateRemoveTree($workDir);
        if ($restored) mikanBoxUpdateRemoveTree($pendingBackup);
        return ['success' => false, 'code' => $restored ? 'backup_failed' : 'rollback_failed'];
    }
    // Retire old backups only after the new backup is safely published.
    foreach (scandir($backupRoot) ?: [] as $item) {
        if ($item === '.' || $item === '..' || $item === basename($stableBackup) || str_starts_with($item, '.pending-')) continue;
        mikanBoxUpdateRemoveTree($backupRoot . '/' . $item);
    }

    mikanBoxUpdateRemoveTree($workDir);
    return ['success' => true, 'version' => $newVersion];
}

function mikanBoxRestorePreviousVersion(string $coreDir, string $dataDir): array {
    return mikanBoxUpdateWithLock($coreDir, static fn() => mikanBoxRestorePreviousVersionLocked($coreDir, $dataDir));
}

function mikanBoxRestorePreviousVersionLocked(string $coreDir, string $dataDir): array {
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
