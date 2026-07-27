<?php
// ==========================================
// mikanBox Storage Location & Migration
// ==========================================

/**
 * Keep the configurable data directory inside the site root.
 * Only a single directory name is accepted; paths and traversal are rejected.
 */
function mikanBoxNormalizeDataDirectoryName($name, array $forbiddenNames = []): string {
    $name = is_string($name) ? trim($name) : '';
    if ($name !== '' && preg_match('/\A[A-Za-z0-9_-]+\z/', $name)) {
        foreach ($forbiddenNames as $forbiddenName) {
            if (strcasecmp($name, (string)$forbiddenName) === 0) {
                $GLOBALS['mikanbox_data_location_notice'] = 'invalid_name';
                return 'mikanData';
            }
        }
        return $name;
    }
    $GLOBALS['mikanbox_data_location_notice'] = 'invalid_name';
    return 'mikanData';
}

/**
 * Deny direct HTTP access before any user data is placed in a directory.
 */
function mikanBoxProtectDataDirectory(string $dirPath): bool {
    if (!is_dir($dirPath) && !@mkdir($dirPath, 0777, true) && !is_dir($dirPath)) {
        return false;
    }

    $htaccessPath = $dirPath . '/.htaccess';
    if (!file_exists($htaccessPath)) {
        $content = "<IfModule mod_authz_core.c>\n"
                 . "    Require all denied\n"
                 . "</IfModule>\n"
                 . "<IfModule !mod_authz_core.c>\n"
                 . "    Order deny,allow\n"
                 . "    Deny from all\n"
                 . "</IfModule>\n";
        if (@file_put_contents($htaccessPath, $content) === false) {
            return false;
        }
    }
    $protection = @file_get_contents($htaccessPath);
    return is_string($protection)
        && (stripos($protection, 'Require all denied') !== false
            || stripos($protection, 'Deny from all') !== false);
}

/**
 * Ignore protection/metadata files when deciding whether a directory contains
 * site data. Empty legacy directories must never override a populated target.
 */
function mikanBoxDataDirectoryHasPayload(string $dirPath): bool {
    if (!is_dir($dirPath)) return false;
    $ignored = ['.', '..', '.htaccess', '.DS_Store', 'index.html'];
    foreach (scandir($dirPath) ?: [] as $entry) {
        if (!in_array($entry, $ignored, true)) return true;
    }
    return false;
}

/**
 * Detect a page whose URL would collide with the configured physical data
 * directory. Supports both flat JSON data and the SQLite edition.
 */
function mikanBoxDataHasReservedPage(string $dirPath, string $reservedPrefix): bool {
    $postsDir = $dirPath . '/posts';
    if (is_dir($postsDir)) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($postsDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'json') continue;
            $relative = substr($file->getPathname(), strlen($postsDir) + 1, -5);
            $relative = str_replace('\\', '/', $relative);
            if (strcasecmp($relative, $reservedPrefix) === 0
                || stripos($relative, $reservedPrefix . '/') === 0) {
                return true;
            }
        }
    }

    $sqlitePath = $dirPath . '/mikanBox.sqlite';
    if (is_file($sqlitePath) && class_exists('SQLite3')) {
        $db = null;
        try {
            $db = new SQLite3($sqlitePath, SQLITE3_OPEN_READONLY);
            $hasPosts = $db->querySingle(
                "SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'posts' LIMIT 1"
            );
            if ($hasPosts) {
                $stmt = $db->prepare(
                    "SELECT 1 FROM posts
                     WHERE lower(id) = lower(:exact)
                        OR lower(id) LIKE lower(:nested)
                     LIMIT 1"
                );
                $stmt->bindValue(':exact', $reservedPrefix, SQLITE3_TEXT);
                $stmt->bindValue(':nested', $reservedPrefix . '/%', SQLITE3_TEXT);
                $result = $stmt->execute();
                $found = $result && $result->fetchArray(SQLITE3_NUM);
                if ($result) $result->finalize();
                if ($found) return true;
            }
        } catch (Throwable $e) {
            // A database that cannot be inspected must stay in its legacy location.
            $GLOBALS['mikanbox_data_location_notice'] = 'inspection_failed';
            return true;
        } finally {
            if ($db instanceof SQLite3) $db->close();
        }
    }
    return false;
}

/**
 * Resolve the active data directory without ever switching an existing site to
 * an empty location.
 *
 * Automatic migration is deliberately limited to one atomic directory rename:
 *   core/data -> site-root/{configured-name}
 * If the target already exists, a reserved page URL collides, or rename fails,
 * the legacy directory remains active and untouched.
 */
function mikanBoxResolveDataDirectory(
    string $siteRoot,
    string $coreDir,
    string $configuredName
): string {
    $legacyDir = rtrim($coreDir, '/\\') . DIRECTORY_SEPARATOR . 'data';

    // Only legacy migration needs serialization. Fresh directory creation uses
    // idempotent mkdir and the storage backend's own locking.
    if (!mikanBoxDataDirectoryHasPayload($legacyDir)) {
        return mikanBoxResolveDataDirectoryUnlocked($siteRoot, $coreDir, $configuredName);
    }

    // Keep the lock file in the core so it is independent of both old and new
    // data paths. Never remove it: unlinking a lock file can split waiters onto
    // different inodes and defeat mutual exclusion.
    $lockPath = rtrim($coreDir, '/\\') . DIRECTORY_SEPARATOR . '.data-migration.lock';
    $lockHandle = @fopen($lockPath, 'c');
    if ($lockHandle === false || !@flock($lockHandle, LOCK_EX)) {
        if (is_resource($lockHandle)) @fclose($lockHandle);
        mikanBoxProtectDataDirectory($legacyDir);
        $GLOBALS['mikanbox_data_location_notice'] = 'lock_failed';
        return $legacyDir;
    }

    try {
        // State is intentionally re-evaluated only after the exclusive lock.
        return mikanBoxResolveDataDirectoryUnlocked($siteRoot, $coreDir, $configuredName);
    } finally {
        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);
    }
}

function mikanBoxResolveDataDirectoryUnlocked(
    string $siteRoot,
    string $coreDir,
    string $configuredName
): string {
    $configuredName = mikanBoxNormalizeDataDirectoryName($configuredName);
    $forbiddenNames = [basename(rtrim($coreDir, '/\\')), 'media', 'api'];
    foreach ($forbiddenNames as $forbiddenName) {
        if (strcasecmp($configuredName, $forbiddenName) === 0) {
            $GLOBALS['mikanbox_data_location_notice'] = 'invalid_name';
            $configuredName = 'mikanData';
            break;
        }
    }
    $targetDir = rtrim($siteRoot, '/\\') . DIRECTORY_SEPARATOR . $configuredName;
    $legacyDir = rtrim($coreDir, '/\\') . DIRECTORY_SEPARATOR . 'data';
    $defaultDir = rtrim($siteRoot, '/\\') . DIRECTORY_SEPARATOR . 'mikanData';

    $legacyHasData = mikanBoxDataDirectoryHasPayload($legacyDir);
    if ($legacyHasData) {
        // Never move private data into the public site root unless direct access
        // protection is already confirmed at the source.
        if (!mikanBoxProtectDataDirectory($legacyDir)) {
            $GLOBALS['mikanbox_data_location_notice'] = 'protection_failed';
            return $legacyDir;
        }

        if (file_exists($targetDir)) {
            $GLOBALS['mikanbox_data_location_notice'] = 'target_exists';
            return $legacyDir;
        }
        if (mikanBoxDataHasReservedPage($legacyDir, $configuredName)) {
            $GLOBALS['mikanbox_data_location_notice'] =
                $GLOBALS['mikanbox_data_location_notice'] ?? 'reserved_page';
            return $legacyDir;
        }

        // Same-site directory rename is atomic. Never fall back to recursive copy.
        if (@rename($legacyDir, $targetDir)) {
            if (!mikanBoxProtectDataDirectory($targetDir)) {
                // Roll back atomically rather than accepting an unprotected target.
                if (!file_exists($legacyDir) && @rename($targetDir, $legacyDir)) {
                    $GLOBALS['mikanbox_data_location_notice'] = 'protection_failed';
                    return $legacyDir;
                }
                throw new RuntimeException(
                    'The migrated mikanBox data directory could not be protected or rolled back.'
                );
            }
            $GLOBALS['mikanbox_data_location_notice'] = 'migrated';
            return $targetDir;
        }

        // A concurrent request from an already-running process may have completed
        // the same atomic move. Re-evaluate before ever returning a vanished path.
        if (!is_dir($legacyDir) && mikanBoxDataDirectoryHasPayload($targetDir)) {
            if (!mikanBoxProtectDataDirectory($targetDir)) {
                throw new RuntimeException('The migrated mikanBox data directory is not protected.');
            }
            $GLOBALS['mikanbox_data_location_notice'] = 'migrated';
            return $targetDir;
        }

        $GLOBALS['mikanbox_data_location_notice'] = 'move_failed';
        return $legacyDir;
    }

    if (is_dir($targetDir)) {
        if (!mikanBoxProtectDataDirectory($targetDir)) {
            throw new RuntimeException('The mikanBox data directory could not be protected.');
        }
        return $targetDir;
    }
    if (file_exists($targetDir)) {
        throw new RuntimeException('The configured mikanBox data location is not a directory.');
    }

    // A mistyped custom setting must not create empty data beside an existing site.
    if ($targetDir !== $defaultDir && mikanBoxDataDirectoryHasPayload($defaultDir)) {
        if (!mikanBoxProtectDataDirectory($defaultDir)) {
            throw new RuntimeException('The existing mikanBox data directory could not be protected.');
        }
        $GLOBALS['mikanbox_data_location_notice'] = 'configured_target_missing';
        return $defaultDir;
    }

    if (!mikanBoxProtectDataDirectory($targetDir)) {
        throw new RuntimeException('The mikanBox data directory could not be created securely.');
    }
    $GLOBALS['mikanbox_data_location_notice'] =
        $GLOBALS['mikanbox_data_location_notice'] ?? 'created';
    return $targetDir;
}
