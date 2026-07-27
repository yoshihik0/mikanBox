<?php
/**
 * MikanBox SSG Build Processor
 */
class MikanBoxSSG {
    private $renderer;
    private $outputDir;
    private $options;

    public function __construct($renderer, $outputDir = 'dist', $options = []) {
        $this->renderer = $renderer;
        $this->outputDir = rtrim($outputDir, '/');
        $settings = $GLOBALS['mikanbox_settings'] ?? [];
        $defaultOutputMode = $settings['ssg_mode'] ?? 'server';
        $this->options = array_merge([
            'structure' => $settings['ssg_structure'] ?? 'directory',
            'selected_pages' => [],
            'output_mode' => $defaultOutputMode,
            'link_mode' => $settings['ssg_link_mode'] ?? 'absolute',
            'copy_media' => $defaultOutputMode === 'export',
        ], $options);
        if (($this->options['output_mode'] ?? 'server') !== 'export') {
            $this->options['link_mode'] = 'absolute';
            $this->options['copy_media'] = false;
        }
    }

    public function build() {
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
            file_put_contents($this->outputDir . '/.mikanbox-export', "mikanBox upload package\n");
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

        foreach ($pagesToBuild as $pageId) {
            $depth = 0;
            if ($pageId !== 'index') {
                $slashCount = substr_count($pageId, '/');
                $depth = ($this->options['structure'] === 'directory') ? ($slashCount + 1) : $slashCount;
            }
            $this->renderer->setStaticMode(
                true,
                $depth,
                $this->options['structure'] ?? 'directory',
                $this->options['link_mode'] ?? 'absolute'
            );
            $html = $this->renderer->render($pageId);

            if ($pageId === 'index') {
                $targetFile = $this->outputDir . '/index.html';
            } else {
                if (($this->options['structure'] ?? 'directory') === 'directory') {
                    $targetFile = $this->outputDir . '/' . $pageId . '/index.html';
                } else {
                    $targetFile = $this->outputDir . '/' . $pageId . '.html';
                }
            }

            // Ensure parent directory exists
            $dirPath = dirname($targetFile);
            if (!is_dir($dirPath)) mkdir($dirPath, 0777, true);

            if (file_put_contents($targetFile, $html)) {
                $results[] = "Generated: $targetFile";
            } else {
                $results[] = "Error: Could not write $targetFile";
            }
        }

        if (!empty($this->options['copy_media'])) {
            $results = array_merge($results, $this->copyMediaDirectory());
        }

        $siteUrl = rtrim($this->renderer->getConfiguredSiteUrl(), '/');
        if ($siteUrl === '' && ($this->options['output_mode'] ?? 'server') !== 'export') {
            $siteUrl = rtrim($this->renderer->getSiteUrl(), '/');
        }
        if ($siteUrl !== '') {
            $results = array_merge($results, $this->buildSitemap($pagesToBuild, $siteUrl));
            $results = array_merge($results, $this->buildRss($siteUrl));
        } elseif (($this->options['output_mode'] ?? 'server') === 'export') {
            foreach (['sitemap.xml', 'rss.xml'] as $feedFile) {
                $feedPath = $this->outputDir . '/' . $feedFile;
                if (is_file($feedPath)) unlink($feedPath);
            }
        }

        return $results;
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
            ? $this->normalizePath(realpath(MEDIA_DIR) ?: MEDIA_DIR)
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
        if (!defined('MEDIA_DIR') || !is_dir(MEDIA_DIR)) {
            return [];
        }

        $source = $this->normalizePath(realpath(MEDIA_DIR) ?: MEDIA_DIR);
        $destination = $this->normalizePath($this->outputDir . '/media');
        if ($source === $destination) {
            return ['Error: Media source and export destination are the same directory.'];
        }
        if (is_link($destination)) {
            unlink($destination);
        } elseif (is_dir($destination)) {
            $this->clearDirectoryContents($destination);
        }
        if (!is_dir($destination) && !mkdir($destination, 0777, true)) {
            return ["Error: Could not create media export directory: $destination"];
        }

        $results = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                MEDIA_DIR,
                FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isLink()) continue;
            $relative = substr($item->getPathname(), strlen(rtrim(MEDIA_DIR, '/')) + 1);
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
            $results[] = "Copied media: $destination";
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

    private function pageUrl($pageId, $siteUrl) {
        $isDirStyle = ($this->options['structure'] ?? 'directory') === 'directory';
        if ($pageId === 'index') return $siteUrl . '/';
        return $isDirStyle ? $siteUrl . '/' . $pageId . '/' : $siteUrl . '/' . $pageId . '.html';
    }

    private function buildSitemap($pageIds, $siteUrl) {
        $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($pageIds as $pageId) {
            $data    = loadData(POSTS_DIR, $pageId);
            $loc     = htmlspecialchars($this->pageUrl($pageId, $siteUrl));
            $lastmod = substr($data['updated_at'] ?? date('Y-m-d H:i:s'), 0, 10);
            $xml .= "  <url>\n    <loc>{$loc}</loc>\n    <lastmod>{$lastmod}</lastmod>\n  </url>\n";
        }
        $xml .= "</urlset>\n";
        $file = $this->outputDir . '/sitemap.xml';
        file_put_contents($file, $xml);
        return ["Generated: $file"];
    }

    private function buildRss($siteUrl) {
        $settings   = $GLOBALS['mikanbox_settings'] ?? [];
        $siteTitle  = htmlspecialchars($settings['site_name'] ?? 'mikanBox');
        $siteDesc   = htmlspecialchars($settings['description'] ?? '');
        $buildDate  = date('r');

        $allPosts = getSortedPostIds();
        $items = [];
        foreach ($allPosts as $pageId) {
            $data = loadData(POSTS_DIR, $pageId);
            if (($data['status'] ?? '') !== 'public_static') continue;
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
        $file = $this->outputDir . '/rss.xml';
        file_put_contents($file, $xml);
        return ["Generated: $file"];
    }


    public function deletePage($pageId) {
        $count = 0;
        if (empty($pageId)) return 0;
        
        // 1. Delete directory-style: dist/path/to/slug/index.html
        $dirPath = $this->outputDir . '/' . $pageId;
        $indexPath = ($pageId === 'index') ? $this->outputDir . '/index.html' : $dirPath . '/index.html';
        if (is_file($indexPath)) {
            if (unlink($indexPath)) $count++;
        }
        
        // 2. Delete file-style: dist/path/to/slug.html
        $filePath = $this->outputDir . '/' . $pageId . '.html';
        if ($pageId !== 'index' && is_file($filePath)) {
            if (unlink($filePath)) $count++;
        }
        
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
            // Safety: Stop if we are outside the output directory or at the root itself
            if (!$realCurrent || !$realRoot || strpos($realCurrent, $realRoot) !== 0 || $realCurrent === $realRoot) {
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
        
        $realOutput = realpath($this->outputDir);
        $realCore = realpath(CORE_DIR);
        if ($realOutput === $realCore) return "Error: Cannot clear the core directory.";

        $allPosts = getFileList(POSTS_DIR);
        $pids = array_merge($allPosts, ['index']);
        
        foreach ($pids as $pid) {
            $count += $this->deletePage($pid);
        }
        
        return "$count files removed.";
    }
}
