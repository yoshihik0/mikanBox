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
        $this->options = array_merge([
            'structure' => 'directory', 
            'selected_pages' => [] 
        ], $options);
    }

    public function build() {
        // Safety: Do not write into core directory or any subdirectory of it
        $realOut  = realpath($this->outputDir) ?: $this->outputDir;
        $realCore = realpath(CORE_DIR) ?: CORE_DIR;
        if (strpos(rtrim($realOut, '/') . '/', rtrim($realCore, '/') . '/') === 0) {
            return ["Error: Output directory cannot be inside the mikanBox core directory."];
        }

        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0777, true);
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
            $this->renderer->setStaticMode(true, $depth, $this->options['structure'] ?? 'directory');
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

        $siteUrl = rtrim($this->renderer->getSiteUrl(), '/');
        $results = array_merge($results, $this->buildSitemap($pagesToBuild, $siteUrl));
        $results = array_merge($results, $this->buildRss($siteUrl));

        return $results;
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
