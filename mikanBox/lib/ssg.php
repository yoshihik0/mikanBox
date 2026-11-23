<?php
if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(403); exit; } // direct request guard
/**
 * mikanBox SSG Build Processor
 */
class MikanBoxSSG {
    private $renderer;
    private $outputDir;
    private $options;
    /** Paths refused by permitted() during the current operation, as "Error: ..." lines. */
    private $refused = [];
    /** Fingerprints of the files written (path => sha256) and the paths removed, handed to the integrity record once. */
    private $written = [];
    private $removed = [];
    private $batch = 0;
    private $fullBuild = false;

    public function __construct($renderer, $outputDir = 'dist', $options = []) {
        $this->renderer = $renderer;
        $this->outputDir = rtrim($outputDir, '/');
        $settings = $GLOBALS['mikanbox_settings'] ?? [];
        $defaultOutputMode = $options['output_mode'] ?? ($settings['ssg_mode'] ?? 'server');
        $this->options = array_merge([
            'structure' => $settings['ssg_structure'] ?? 'directory',
            'selected_pages' => [],
            'output_mode' => $defaultOutputMode,
            'link_mode' => $settings['ssg_link_mode'] ?? 'relative',
            'copy_media' => $defaultOutputMode === 'export',
            'record_baseline' => true,   // false for a build into a temporary folder (a download), which is not the public site
        ], $options);
        if (($this->options['output_mode'] ?? 'server') !== 'export') {
            $this->options['link_mode'] = 'absolute';
            $this->options['copy_media'] = false;
        }
    }

    public function build() {
        $this->batch++;
        try { return $this->buildSite(); } finally { $this->batch--; $this->flushBaseline(); }
    }

    /** Hand what this operation wrote and removed to the integrity record (what the "check for tampering" compares with). */
    private function flushBaseline() {
        if ($this->batch > 0 || (!$this->written && !$this->removed)) return;
        if (!empty($this->options['record_baseline']) && function_exists('mikanBoxIntegrityPublicChanged')) {
            mikanBoxIntegrityPublicChanged($this->outputDir, $this->written, $this->removed, $this->fullBuild);
        }
        $this->written = []; $this->removed = []; $this->fullBuild = false;
    }

    private function relativeOutput($file) {
        $root = rtrim(str_replace('\\', '/', $this->outputDir), '/') . '/';
        $file = str_replace('\\', '/', (string)$file);
        return strncmp($file, $root, strlen($root)) === 0 ? substr($file, strlen($root)) : null;
    }

    private function buildSite() {
        $this->refused = [];
        // Safety: Do not write into core/data directories or their subdirectories.
        $realOut  = realpath($this->outputDir) ?: $this->outputDir;
        $realCore = realpath(CORE_DIR) ?: CORE_DIR;
        if (strpos(rtrim($realOut, '/') . '/', rtrim($realCore, '/') . '/') === 0) {
            return ["Error: Output directory cannot be inside the mikanBox core directory."];
        }
        if (defined('DATA_DIR')) {
            $realData = realpath(DATA_DIR) ?: DATA_DIR;
            if (strpos(rtrim($realOut, '/') . '/', rtrim($realData, '/') . '/') === 0) {
                return ["Error: Output directory cannot be inside the mikanBox data directory."];
            }
        }

        if (($this->options['output_mode'] ?? 'server') === 'export') {
            $safetyError = $this->validateExportDestination();
            if ($safetyError !== null) {
                return ["Error: $safetyError"];
            }
            if (($this->options['link_mode'] ?? 'relative') === 'absolute'
                && !$this->isValidPublishedUrl($this->renderer->getConfiguredSiteUrl())) {
                return ["Error: A published root URL is required for fixed-URL export."];
            }
        }

        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
        }
        if (($this->options['output_mode'] ?? 'server') === 'export') {
            $marker = $this->outputDir . '/.mikanbox-export';
            if ($this->permitted($marker)) file_put_contents($marker, "mikanBox upload package\n");
            else $this->refuse($marker);
        }

        $allPosts = getSortedPostIds();
        $pagesToBuild = $this->options['selected_pages'];
        if (empty($pagesToBuild)) {
            // Only build pages that have status 'public_static'
            $pagesToBuild = array_filter($allPosts, function($id) {
                $data = loadData(POSTS_DIR, $id);
                return (isset($data['status']) && $data['status'] === 'public_static');
            });
        }

        $results = [];
        // A full build also removes what earlier builds left for pages that are no longer
        // "Public (Static)" (changed to dynamic or draft elsewhere), so nothing stale shadows
        // a dynamic page or keeps a draft public. Only each page's own files are touched.
        if (empty($this->options['selected_pages'])) {
            foreach ($allPosts as $staleId) {
                $staleData = loadData(POSTS_DIR, $staleId);
                if (!$staleData || ($staleData['status'] ?? '') === 'public_static') continue;
                if ($this->deletePage($staleId) > 0) $results[] = "Removed stale static files: $staleId";
            }
        }

        // Data pages first: HTML pages embed a hash of their built output in {{PAGE_URL:ID}}.
        $dataPages = [];
        $otherPages = [];
        $apiPages = [];
        foreach ($pagesToBuild as $pageId) {
            $format = mikanBoxPageOutputFormat(loadData(POSTS_DIR, $pageId));
            if (mikanBoxIsDataFormat($format)) $dataPages[] = $pageId;
            elseif (mikanBoxIsApiFormat($format)) $apiPages[] = $pageId;
            else $otherPages[] = $pageId;
        }
        $dataHashes = [];
        foreach ($dataPages as $pageId) {
            $format = mikanBoxPageOutputFormat(loadData(POSTS_DIR, $pageId));
            $this->renderer->setStaticMode(true, substr_count($pageId, '/'), $this->options['structure'], $this->options['link_mode'], !empty($this->options['copy_media']));
            $this->renderer->setDataHashes($dataHashes);
            $output = $this->renderer->renderData($pageId);
            if ($output === null) continue;
            $dataHashes[$pageId] = MikanBoxRenderer::dataHash($output);
            $results[] = $this->writeOutputFile($this->outputDir . '/' . $pageId . '.' . $format, $output);
            $this->deleteHtmlFiles($pageId);
        }
        // mikanBox DB: the API JSON as a file, /api/ID.json (the same as Cloud's split setup).
        foreach ($apiPages as $pageId) {
            $output = $this->renderer->renderApi($pageId);
            if ($output === null) continue;
            $dataHashes[$pageId] = MikanBoxRenderer::dataHash($output);
            $results[] = $this->writeOutputFile($this->outputDir . '/api/' . $pageId . '.json', $output);
            $this->deleteHtmlFiles($pageId);
        }
        $this->renderer->setDataHashes($dataHashes);

        $includeAiQuestionAsset = false;
        foreach ($otherPages as $pageId) {
            $format = mikanBoxPageOutputFormat(loadData(POSTS_DIR, $pageId));
            if (mikanBoxFormatHasMarkdown($format)) {
                // The file is /ID.md whatever the URL style, so its depth is the number of slashes in the ID
                // (it decides the relative links when there is no published root URL).
                $this->renderer->setStaticMode(true, substr_count($pageId, '/'), $this->options['structure'], $this->options['link_mode'], !empty($this->options['copy_media']));
                $markdown = $this->renderer->renderMarkdown($pageId);
                if ($markdown !== null) $results[] = $this->writeOutputFile($this->outputDir . '/' . $pageId . '.md', $markdown);
            }
            if (!mikanBoxFormatHasHtml($format)) {
                $this->deleteHtmlFiles($pageId);
                continue;
            }

            $depth = 0;
            if ($pageId !== 'index') {
                $slashCount = substr_count($pageId, '/');
                $depth = ($this->options['structure'] === 'directory') ? ($slashCount + 1) : $slashCount;
            }
            $this->renderer->setStaticMode(
                true,
                $depth,
                $this->options['structure'] ?? 'directory',
                $this->options['link_mode'] ?? 'relative',
                !empty($this->options['copy_media'])
            );
            $html = $this->renderer->render($pageId);
            if (str_contains($html, 'data-mikanbox-ai-question')) $includeAiQuestionAsset = true;

            if ($pageId === 'index') {
                $targetFile = $this->outputDir . '/index.html';
            } else {
                if (($this->options['structure'] ?? 'directory') === 'directory') {
                    $targetFile = $this->outputDir . '/' . $pageId . '/index.html';
                } else {
                    $targetFile = $this->outputDir . '/' . $pageId . '.html';
                }
            }
            $results[] = $this->writeOutputFile($targetFile, $html);
        }

        if (!empty($this->options['copy_media'])) {
            $results = array_merge($results, $this->copyMediaDirectory());
            if ($includeAiQuestionAsset) {
                $asset = MikanBoxRenderer::AI_QUESTION_ASSET;
                $script = @file_get_contents(CORE_DIR . '/' . $asset);
                $results[] = $script === false
                    ? 'Error: Could not read the AI question box script.'
                    : $this->writeOutputFile($this->outputDir . '/' . basename(CORE_DIR) . '/' . $asset, $script);
            }
        }

        $siteUrl = rtrim($this->renderer->getConfiguredSiteUrl(), '/');
        if ($siteUrl === '' && ($this->options['output_mode'] ?? 'server') !== 'export') {
            $siteUrl = rtrim($this->renderer->getSiteUrl(), '/');
        }
        if ($siteUrl !== '') {
            // The sitemap always covers every static page, also after a single-page build.
            $allStatic = array_values(array_filter($allPosts, fn($id) => (loadData(POSTS_DIR, $id)['status'] ?? '') === 'public_static'));
            $results = array_merge($results, $this->buildSitemap($allStatic, $siteUrl));
            $results = array_merge($results, $this->buildRss($siteUrl));
        } elseif (($this->options['output_mode'] ?? 'server') === 'export') {
            foreach (['sitemap.xml', 'rss.xml'] as $feedFile) {
                $this->removeFile($this->outputDir . '/' . $feedFile);
            }
        }

        if (empty($this->options['selected_pages']) && !$this->refused) $this->fullBuild = true;   // every static page was written: a complete basis
        return array_merge($results, $this->refused);
    }

    private function normalizePath($path) {
        if ($path === '') return '';
        if ($path[0] !== '/') {
            $path = getcwd() . '/' . $path;
        }
        $parts = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
            if ($part === '' || $part === '.') continue;
            if ($part === '..') {
                array_pop($parts);
                continue;
            }
            $parts[] = $part;
        }
        return '/' . implode('/', $parts);
    }

    private function isValidPublishedUrl($url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) return false;
        $scheme = strtolower(parse_url($url, PHP_URL_SCHEME) ?? '');
        return in_array($scheme, ['http', 'https'], true);
    }

    private function validateExportDestination() {
        $output = $this->normalizePath(realpath($this->outputDir) ?: $this->outputDir);
        $core = $this->normalizePath(realpath(CORE_DIR) ?: CORE_DIR);
        $siteRoot = $this->normalizePath(realpath(dirname(CORE_DIR)) ?: dirname(CORE_DIR));
        $media = defined('MEDIA_DIR')
            ? $this->normalizePath(realpath(mikanBoxMediaDirectory()) ?: mikanBoxMediaDirectory())
            : $siteRoot . '/media';
        $data = defined('DATA_DIR')
            ? $this->normalizePath(realpath(DATA_DIR) ?: DATA_DIR)
            : $siteRoot . '/mikanData';

        if ($output === '' || $output === '/' || $output === $siteRoot) {
            return 'Upload-package output must be a dedicated folder, not the site root.';
        }
        if (strpos($output . '/', rtrim($siteRoot, '/') . '/') !== 0) {
            return 'Upload-package output must be a dedicated folder inside the site root.';
        }
        if ($output === $core || strpos($output . '/', rtrim($core, '/') . '/') === 0) {
            return 'Upload-package output cannot be inside the mikanBox core directory.';
        }
        if ($output === $media || strpos($output . '/', rtrim($media, '/') . '/') === 0) {
            return 'Upload-package output cannot be the media directory or a folder inside it.';
        }
        if ($output === $data || strpos($output . '/', rtrim($data, '/') . '/') === 0) {
            return 'Upload-package output cannot be the data directory or a folder inside it.';
        }
        if (is_dir($output) && !is_file($output . '/.mikanbox-export')) {
            $existing = array_values(array_diff(scandir($output) ?: [], ['.', '..', '.DS_Store']));
            if (!empty($existing)) {
                return 'Upload-package output must be empty or an existing mikanBox export folder.';
            }
        }
        return null;
    }

    private function copyMediaDirectory() {
        if (!defined('MEDIA_DIR') || !is_dir(mikanBoxMediaDirectory())) {
            return [];
        }

        $source = $this->normalizePath(realpath(mikanBoxMediaDirectory()) ?: mikanBoxMediaDirectory());
        $destination = $this->normalizePath($this->outputDir . '/media');
        $realDestination = realpath($destination) ?: (realpath(dirname($destination)) ?: dirname($destination)) . '/media';
        $sourceRoot = self::lowerPath($source) . '/';
        $destinationRoot = self::lowerPath($realDestination) . '/';
        if (str_starts_with($sourceRoot, $destinationRoot) || str_starts_with($destinationRoot, $sourceRoot)) {
            return ['Error: Media source and export destination overlap.'];
        }
        if (!$this->permitted($this->outputDir . '/media/.mikanbox-check')) {
            return ['Error: Refused to write a protected location: media'];
        }
        if (is_link($destination)) {
            unlink($destination);
        } elseif (is_dir($destination)) {
            $this->clearDirectoryContents($destination);
        }
        if (!is_dir($destination) && !mkdir($destination, 0777, true)) {
            return ['Error: Could not create media export directory: media'];
        }

        $results = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                mikanBoxMediaDirectory(),
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isLink()) continue;
            $relative = substr($item->getPathname(), strlen(rtrim(mikanBoxMediaDirectory(), '/')) + 1);
            $target = $destination . '/' . $relative;
            if ($item->isDir()) {
                if (!is_dir($target) && !mkdir($target, 0777, true)) {
                    $results[] = "Error: Could not create media directory: $target";
                }
                continue;
            }
            $parent = dirname($target);
            if (!is_dir($parent) && !mkdir($parent, 0777, true)) {
                $results[] = "Error: Could not create media directory: $parent";
                continue;
            }
            if (!copy($item->getPathname(), $target)) {
                $results[] = "Error: Could not copy media file: $relative";
            }
        }
        if (empty($results)) {
            $results[] = 'Copied media: media';
        }
        return $results;
    }

    private function clearDirectoryContents($directory) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $directory,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if ($item->isLink() || $item->isFile()) {
                unlink($path);
            } elseif ($item->isDir()) {
                rmdir($path);
            }
        }
    }

    private function writeOutputFile($targetFile, $content) {
        // Static .md/.txt/.csv start with a UTF-8 BOM: servers that send them without a
        // charset (nginx by default) otherwise make browsers guess the encoding wrongly.
        // fetch()/TextDecoder drop the BOM; JSON, XML, JS and CSS get none.
        if (preg_match('/\.(md|txt|csv)$/i', $targetFile) && strncmp((string)$content, "\xEF\xBB\xBF", 3) !== 0) {
            $content = "\xEF\xBB\xBF" . $content;
        }
        $shown = $this->displayPath($targetFile);
        if (!$this->permitted($targetFile)) return 'Error: Refused to write a protected location: ' . $shown;
        $dirPath = dirname($targetFile);
        if (!is_dir($dirPath)) @mkdir($dirPath, 0777, true);
        // Checked again once the folders exist, so a link created in the meantime is caught too.
        if (!$this->permitted($targetFile)) return 'Error: Refused to write a protected location: ' . $shown;
        if (@file_put_contents($targetFile, $content) === false) return "Error: Could not write $shown";
        if (($relative = $this->relativeOutput($targetFile)) !== null) { $this->written[$relative] = hash('sha256', (string)$content); unset($this->removed[$relative]); }
        return "Generated: $shown";
    }

    // ------------------------------------------------------------------
    // Where the generator may write and delete (2.21.2)
    // ------------------------------------------------------------------

    private static function lowerPath($path) {
        return strtolower(rtrim(str_replace('\\', '/', (string)$path), '/'));
    }

    /**
     * Folders the generator never writes into or removes from, whatever a page ID says:
     * the program folder, the data folder, media and setup-token. Lower-cased, so
     * MIKANBOX/x cannot reach mikanBox/ on case-insensitive file systems (macOS, Windows).
     */
    private function protectedRoots() {
        static $cache = [];
        $siteRoot = dirname(CORE_DIR);
        $paths = [CORE_DIR, $siteRoot . '/media', $siteRoot . '/setup-token'];
        if (defined('DATA_DIR')) $paths[] = DATA_DIR;
        if (defined('MEDIA_DIR')) $paths[] = mikanBoxMediaDirectory();
        if (function_exists('mikanBoxSetupTokenDir')) $paths[] = mikanBoxSetupTokenDir();
        $key = implode("\0", $paths);
        if (isset($cache[$key])) return $cache[$key];
        $roots = [];
        foreach ($paths as $path) {
            $roots[] = self::lowerPath($path);
            $real = realpath($path);
            if ($real !== false) $roots[] = self::lowerPath($real);
        }
        return $cache[$key] = array_values(array_unique(array_filter($roots, fn($r) => $r !== '')));
    }

    private function insideProtected($lowerPath) {
        foreach ($this->protectedRoots() as $root) {
            if ($lowerPath === $root || strpos($lowerPath, $root . '/') === 0) return true;
        }
        return false;
    }

    /**
     * True when $file may be written or removed: it lies below the output folder (also after
     * resolving its parent folder with realpath, so symbolic links cannot lead elsewhere), its
     * path below the output folder has no "." or ".." parts, it is not itself a link, and it is
     * not inside a protected folder. Every write and delete of the generator passes here.
     */
    private function permitted($file) {
        $file = str_replace('\\', '/', (string)$file);
        $out = rtrim(str_replace('\\', '/', $this->outputDir), '/');
        if ($out === '' || strpos($file, $out . '/') !== 0) return false;
        foreach (explode('/', substr($file, strlen($out) + 1)) as $part) {
            if ($part === '' || $part === '.' || $part === '..') return false;
        }
        $outReal = realpath($this->outputDir);
        if ($outReal === false) return false;
        if (is_link($file)) return false;
        // The nearest existing folder, resolved; the folders still to be created are plain names.
        $parent = dirname($file);
        $missing = [];
        while (!is_dir($parent)) {
            if (file_exists($parent) || is_link($parent)) return false;
            $missing[] = basename($parent);
            $next = dirname($parent);
            if ($next === $parent) return false;
            $parent = $next;
        }
        $realParent = realpath($parent);
        if ($realParent === false) return false;
        $resolved = self::lowerPath($realParent . ($missing ? '/' . implode('/', array_reverse($missing)) : '') . '/' . basename($file));
        $lowerOut = self::lowerPath($outReal);
        if (strpos($resolved, $lowerOut . '/') !== 0) return false;
        if ($this->insideProtected($resolved) || $this->insideProtected(self::lowerPath($file))) return false;
        return true;
    }

    /** A folder below the output folder that cleanEmptyParents() may remove. */
    private function permittedDirectory($dir) {
        $real = realpath($dir);
        $outReal = realpath($this->outputDir);
        if ($real === false || $outReal === false || is_link($dir)) return false;
        $lowerReal = self::lowerPath($real);
        $lowerOut = self::lowerPath($outReal);
        if (strpos($lowerReal, $lowerOut . '/') !== 0) return false;
        return !$this->insideProtected($lowerReal) && !$this->insideProtected(self::lowerPath($dir));
    }

    private function refuse($file) {
        $line = 'Error: Refused to touch a protected location: ' . $this->displayPath($file);
        if (!in_array($line, $this->refused, true)) $this->refused[] = $line;
    }

    /** Remove one generated file if it may be removed; a refused existing file is reported. */
    private function removeFile($file) {
        if (!is_file($file) && !is_link($file)) return false;
        if (!$this->permitted($file)) {
            $this->refuse($file);
            return false;
        }
        $ok = @unlink($file);
        if (!$ok && (is_file($file) || is_link($file))) {
            $this->refused[] = 'Error: Could not remove ' . $this->displayPath($file);
        }
        if ($ok && ($relative = $this->relativeOutput($file)) !== null) { $this->removed[$relative] = true; unset($this->written[$relative]); }
        return $ok;
    }

    /** Lines refused during the last operation (build() already includes them in its result). */
    public function refusals() {
        return $this->refused;
    }

    /** A path for messages: relative to the site root or the output folder, never the server's absolute path. */
    private function displayPath($path) {
        $path = str_replace('\\', '/', (string)$path);
        $bases = [rtrim(str_replace('\\', '/', $this->outputDir), '/'), str_replace('\\', '/', dirname(CORE_DIR))];
        foreach ([realpath($this->outputDir), realpath(dirname(CORE_DIR))] as $real) {
            if ($real !== false) $bases[] = str_replace('\\', '/', $real);
        }
        usort($bases, fn($a, $b) => strlen($a) - strlen($b));
        foreach ($bases as $base) {
            if ($base !== '' && stripos($path, rtrim($base, '/') . '/') === 0) return substr($path, strlen(rtrim($base, '/')) + 1);
        }
        return basename($path);
    }

    private function pageUrl($pageId, $siteUrl) {
        if (mikanBoxPageOutputFormat(loadData(POSTS_DIR, $pageId)) === 'md') return $siteUrl . '/' . $pageId . '.md';
        $isDirStyle = ($this->options['structure'] ?? 'directory') === 'directory';
        if ($pageId === 'index') return $siteUrl . '/';
        return $isDirStyle ? $siteUrl . '/' . $pageId . '/' : $siteUrl . '/' . $pageId . '.html';
    }

    private function buildSitemap($pageIds, $siteUrl) {
        $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($pageIds as $pageId) {
            if (mikanBoxIsErrorPage($pageId)) continue;
            $data    = loadData(POSTS_DIR, $pageId);
            if (!mikanBoxPageIsListed($data)) continue;
            $loc     = htmlspecialchars($this->pageUrl($pageId, $siteUrl));
            $lastmod = substr($data['updated_at'] ?? date('Y-m-d H:i:s'), 0, 10);
            $xml .= "  <url>\n    <loc>{$loc}</loc>\n    <lastmod>{$lastmod}</lastmod>\n  </url>\n";
        }
        $xml .= "</urlset>\n";
        return [$this->writeOutputFile($this->outputDir . '/sitemap.xml', $xml)];
    }

    private function buildRss($siteUrl) {
        $settings   = $GLOBALS['mikanbox_settings'] ?? [];
        $siteTitle  = htmlspecialchars($settings['site_name'] ?? 'mikanBox');
        $siteDesc   = htmlspecialchars($settings['description'] ?? '');
        $buildDate  = date('r');

        $allPosts = getSortedPostIds();
        $items = [];
        foreach ($allPosts as $pageId) {
            if (mikanBoxIsErrorPage($pageId)) continue;
            $data = loadData(POSTS_DIR, $pageId);
            if (($data['status'] ?? '') !== 'public_static') continue;
            if (!mikanBoxPageIsListed($data)) continue;
            $items[] = ['id' => $pageId, 'data' => $data];
        }
        usort($items, fn($a, $b) => strcmp($b['data']['updated_at'] ?? '', $a['data']['updated_at'] ?? ''));
        $items = array_slice($items, 0, 20);

        $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<rss version=\"2.0\">\n<channel>\n";
        $xml .= "  <title>{$siteTitle}</title>\n";
        $xml .= "  <link>{$siteUrl}/</link>\n";
        $xml .= "  <description>{$siteDesc}</description>\n";
        $xml .= "  <lastBuildDate>{$buildDate}</lastBuildDate>\n";
        foreach ($items as $item) {
            $title   = htmlspecialchars($item['data']['title'] ?? $item['id']);
            $desc    = htmlspecialchars($item['data']['description'] ?? '');
            $link    = htmlspecialchars($this->pageUrl($item['id'], $siteUrl));
            $rawDate = $item['data']['updated_at'] ?? date('Y-m-d H:i:s');
            $pubDate = date('r', strtotime($rawDate));
            $xml .= "  <item>\n";
            $xml .= "    <title>{$title}</title>\n";
            $xml .= "    <link>{$link}</link>\n";
            $xml .= "    <description>{$desc}</description>\n";
            $xml .= "    <pubDate>{$pubDate}</pubDate>\n";
            $xml .= "    <guid>{$link}</guid>\n";
            $xml .= "  </item>\n";
        }
        $xml .= "</channel>\n</rss>\n";
        return [$this->writeOutputFile($this->outputDir . '/rss.xml', $xml)];
    }


    /**
     * Remove a page's static files. $format is the output format the files were built
     * with (default: the page's saved format). Only that format's /id.md or /id.<extension>
     * is removed, so a hand-placed file such as robots.txt beside an HTML page "robots" stays.
     */
    public function deletePage($pageId, $format = null) {
        $this->batch++;
        try { return $this->deletePageFiles($pageId, $format); } finally { $this->batch--; $this->flushBaseline(); }
    }

    private function deletePageFiles($pageId, $format) {
        if (empty($pageId)) return 0;
        $count = 0;
        $format = $format ?? mikanBoxPageOutputFormat(loadData(POSTS_DIR, $pageId));
        if (mikanBoxIsApiFormat($format)) {
            $apiFile = $this->outputDir . '/api/' . $pageId . '.json';
            if ($this->removeFile($apiFile)) $count++;
            if (is_dir(dirname($apiFile))) $this->cleanEmptyParents(dirname($apiFile));
        }
        $extensions = [];
        if (mikanBoxFormatHasMarkdown($format)) $extensions[] = 'md';
        if (mikanBoxIsDataFormat($format)) $extensions[] = $format;
        foreach ($extensions as $extension) {
            if (in_array(strtolower($pageId . '.' . $extension), ['sitemap.xml', 'rss.xml', 'podcast.xml'], true)) continue;
            $file = $this->outputDir . '/' . $pageId . '.' . $extension;
            if ($this->removeFile($file)) $count++;
        }
        $count += $this->deleteHtmlFiles($pageId);
        if ($pageId !== 'index') {
            $parentDir = dirname($this->outputDir . '/' . $pageId);
            if (is_dir($parentDir)) $this->cleanEmptyParents($parentDir);
        }
        return $count;
    }

    private function deleteHtmlFiles($pageId) {
        $count = 0;
        if (empty($pageId)) return 0;
        
        // 1. Delete directory-style: dist/path/to/slug/index.html
        $dirPath = $this->outputDir . '/' . $pageId;
        $indexPath = ($pageId === 'index') ? $this->outputDir . '/index.html' : $dirPath . '/index.html';
        if ($this->removeFile($indexPath)) $count++;
        
        // 2. Delete file-style: dist/path/to/slug.html
        $filePath = $this->outputDir . '/' . $pageId . '.html';
        if ($pageId !== 'index' && $this->removeFile($filePath)) $count++;
        
        // 3. Recursive cleanup of empty parent directories
        if ($pageId !== 'index') {
            // Clean dirPath (the folder that would contain index.html)
            if (is_dir($dirPath)) $this->cleanEmptyParents($dirPath);
            // Clean parent of filePath (the folder that contains slug.html)
            $parentDir = dirname($filePath);
            if (is_dir($parentDir)) $this->cleanEmptyParents($parentDir);
        }
        
        return $count;
    }

    private function cleanEmptyParents($dir) {
        $realRoot = realpath($this->outputDir);
        $currentDir = $dir;
        
        while ($currentDir && is_dir($currentDir)) {
            $realCurrent = realpath($currentDir);
            // Safety: Stop if we are outside the output directory, at the root itself, or at a
            // protected folder (program, data, media, setup-token).
            if (!$realCurrent || !$realRoot || $realCurrent === $realRoot || !$this->permittedDirectory($currentDir)) {
                break;
            }
            
            // Check if directory is empty
            $files = array_diff(scandir($realCurrent), array('.', '..', '.DS_Store'));
            if (empty($files)) {
                if (@rmdir($realCurrent)) {
                    $currentDir = dirname($realCurrent);
                } else {
                    break;
                }
            } else {
                break;
            }
        }
    }

    public function clear() {
        $this->batch++;
        try { return $this->clearOutput(); } finally { $this->batch--; $this->flushBaseline(); }
    }

    private function clearOutput() {
        $count = 0;
        if (!is_dir($this->outputDir)) return "0 files (Directory not found)";

        if (($this->options['output_mode'] ?? 'server') === 'export') {
            $safetyError = $this->validateExportDestination();
            if ($safetyError !== null) return "Error: $safetyError";
            if (($this->options['link_mode'] ?? 'relative') === 'absolute'
                && !$this->isValidPublishedUrl($this->renderer->getConfiguredSiteUrl())) {
                return 'Error: A published root URL is required for fixed-URL export.';
            }
        }
        
        $this->refused = [];
        $realOutput = realpath($this->outputDir);
        $realCore = realpath(CORE_DIR);
        if ($realOutput === $realCore) return "Error: Cannot clear the core directory.";

        $allPosts = getFileList(POSTS_DIR);
        $pids = array_merge($allPosts, ['index']);
        
        foreach ($pids as $pid) {
            $count += $this->deletePage($pid);
        }
        
        return "$count files removed." . ($this->refused ? ' / ' . implode(', ', $this->refused) : '');
    }
}

/** Remove a page's generated files from the configured output folder. */
function mikanBoxRemovePageOutput(string $id, string $format, array $settings): bool {
    $dir = $settings['ssg_dir'] ?? $settings['last_ssg_dir'] ?? '';
    if ($dir === '' && ($settings['ssg_mode'] ?? '') === 'export') $dir = 'export';
    $output = dirname(CORE_DIR) . ($dir !== '' ? '/' . ltrim($dir, '/') : '');
    $ssg = new MikanBoxSSG(null, $output);
    $ssg->deletePage($id, $format);
    return !$ssg->refusals();
}

/** Check output before deleting the source so a failed removal can be retried. */
function mikanBoxRemovePublishedPageOutput(string $id, ?array $page, array $settings): bool {
    if (($page['status'] ?? '') !== 'public_static' || mikanBoxIdIsReserved($id)) return true;
    return mikanBoxRemovePageOutput($id, mikanBoxPageOutputFormat($page), $settings);
}
