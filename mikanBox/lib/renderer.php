<?php
/**
 * MikanBox Renderer Class
 * Centralizes rendering logic for both dynamic and static display.
 */
class MikanBoxRenderer {
    private $siteSettings;
    private $markdown;
    private $staticMode = false;
    private $currentPageId = '';
    private $globalCssBuffer = [];
    private $depth = 0;
    private $depthSetManually = false;
    private $ssgStructure = 'directory';
    private $currentPageData = []; // DATA blocks of current page

    public function __construct($settings) {
        // Renaming/Aliasing for better visibility: ssg_root_url is the primary source of truth
        $this->siteSettings = array_merge([
            'site_name' => 'mikanBox',
            'description' => '',
            'keywords' => '',
            'ogp_image' => '',
            'ssg_root_url' => '',
            'ssg_dir' => ($settings['last_ssg_dir'] ?? '') // Compatibility fallback
        ], $settings);
        
        // Ensure ssg_dir is correctly set from settings
        if (isset($settings['ssg_dir'])) {
            $this->siteSettings['ssg_dir'] = $settings['ssg_dir'];
        }

        $this->markdown = new MikanBoxMarkdown();
        $this->ssgStructure = $settings['ssg_structure'] ?? 'directory';
    }

    /**
     * Public API: extract DATA/DATAROW blocks from a page as structured array.
     * Returns ['fields' => [...], 'rows' => [['id'=>..., FIELD=>...], ...]]
     */
    public function getPageDataBlocks($pageId) {
        $postData = loadData(POSTS_DIR, $pageId);
        if (!$postData) return null;
        $blocks = [];
        $this->extractDataBlocks($postData['content_md'] ?? '', $blocks);
        $rows = [];
        $fields = [];
        foreach ($blocks as $key => $value) {
            if (preg_match('/^#([^:]+):(.+)$/', $key, $m)) {
                $rows[$m[1]][$m[2]] = $value;
            } else {
                $fields[$key] = $value;
            }
        }
        $rowList = [];
        foreach ($rows as $id => $data) {
            $rowList[] = array_merge(['id' => $id], $data);
        }
        return [
            'id'     => $pageId,
            'title'  => $postData['title'] ?? '',
            'fields' => $fields,
            'rows'   => $rowList,
        ];
    }

    public function setStaticMode($enabled, $depth = 0, $structure = 'directory') {
        $this->staticMode = $enabled;
        $this->depth = $depth;
        $this->depthSetManually = true; // Use manual depth (from SSG)
        $this->ssgStructure = $structure;
    }

    /**
     * Renders a page by its ID.
     */
    public function render($pageId) {
        $this->currentPageId = $pageId;
        $this->globalCssBuffer = [];
        
        // Calculate depth automatically in dynamic mode if not set manually
        if (!$this->depthSetManually) {
            if ($pageId === 'index') {
                $this->depth = 0;
            } else {
                $this->depth = substr_count($pageId, '/');
            }
        }

        // Determine directories
        $pageData = loadData(POSTS_DIR, $pageId);
        if (!$pageData) {
            http_response_code(404);
            $pageData = loadData(POSTS_DIR, '404') ?: [
                'title' => '404 Not Found',
                'content_md' => "# 404 Not Found\nページが見つかりませんでした。",
                'wrapper_comp' => '_layout'
            ];
        }

        // Check for private status (only if not in static mode or authenticated)
        if (!$this->staticMode && (isset($pageData['status']) && in_array($pageData['status'], ['draft', 'db']) && !isset($_SESSION['admin_logged_in']))) {
            return "<!-- Private content -->";
        }

        // Get site metadata for convenience
        $pageTitle = isset($pageData['title']) ? $pageData['title'] . ' - ' . $this->siteSettings['site_name'] : $this->siteSettings['site_name'];
        $pageDesc = isset($pageData['description']) && !empty($pageData['description']) ? $pageData['description'] : $this->siteSettings['description'];
        $pageKeywords = isset($pageData['keywords']) && !empty($pageData['keywords']) ? $pageData['keywords'] : $this->siteSettings['keywords'];
        $ogpImage = isset($pageData['ogp_image']) && !empty($pageData['ogp_image']) ? $pageData['ogp_image'] : $this->siteSettings['ogp_image'];

        // 1. Parse Main Content Markdown
        $contentMd = isset($pageData['content_md']) ? $pageData['content_md'] : '';

        // Extract DATA blocks before Markdown processing (protect code blocks first)
        $codeMap = [];
        $contentMd = $this->protectCodeSpans($contentMd, $codeMap);
        $contentMd = $this->extractDataBlocks($contentMd, $this->currentPageData);
        $contentMd = $this->restoreCodeSpans($contentMd, $codeMap);

        if (!empty($pageData['is_html'])) {
            $contentHtml = $contentMd;
        } else {
            // Protect tags
            $protectedMd = preg_replace_callback('/\{\{[^\}]+\}\}/', function($m) {
                return '<!--MKNTG' . base64_encode($m[0]) . '-->';
            }, $contentMd);
            
            $contentHtml = $this->markdown->text($protectedMd);
            $contentHtml = preg_replace_callback('/<!--MKNTG([a-zA-Z0-9+\/=]+)-->/', function($m) {
                return base64_decode($m[1]);
            }, $contentHtml);
            // HTML-encoded placeholders (inside <code>) are left as-is here,
            // and restored after all tag replacements in the final step below.
        }

        // 2. Load Wrapper
        $wrapperCompId = isset($pageData['wrapper_comp']) ? $pageData['wrapper_comp'] : '_layout';
        $wrapperData = loadData(COMPONENTS_DIR, $wrapperCompId) ?: loadData(COMPONENTS_DIR, '_layout');
        $html = $wrapperData['html'] ?? '{{CONTENT}}';

        // Embed main content into the wrapper
        $html = str_replace('{{CONTENT}}', $contentHtml, $html);

        // Wrapper CSS
        if ($wrapperData && !empty($wrapperData['css'])) {
            if (!empty($wrapperData['is_global']) || $wrapperCompId === '_layout') {
                $this->globalCssBuffer[] = "/* Wrapper (Global): {$wrapperCompId} */\n" . $wrapperData['css'];
            } else {
                $prefixClass = 'cmp-' . $wrapperCompId;
                $scopedCss = scopeCss($wrapperData['css'], '.' . $prefixClass);
                $this->globalCssBuffer[] = "/* Wrapper: {$wrapperCompId} */\n" . $scopedCss;
            }
        }

        // 3. Page-specific CSS
        if (!empty($pageData['css'])) {
            $this->globalCssBuffer[] = "/* Page CSS */\n" . $pageData['css'];
        }

        // 4. Parse components (recursively)
        $html = $this->parseComponents($html);

        // 5. Expand basic tags
        $html = $this->replaceBasicTags($html, $pageData, $pageTitle, $pageDesc, $pageKeywords, $ogpImage);

        // 6. Expand Navigation/Special Tags
        $html = $this->replaceSpecialTags($html);

        // 7. Final path completion
        $html = $this->applyPathCompletion($html);

        // 7.5. Restore HTML-encoded placeholders left inside <code> blocks as literal text
        $html = preg_replace_callback('/&lt;!--MKNTG([a-zA-Z0-9+\/=]+)--&gt;/', function($m) {
            return htmlspecialchars(base64_decode($m[1]));
        }, $html);

        // 8. Process CSS Buffer
        foreach ($this->globalCssBuffer as &$cssLine) {
            $cssLine = $this->replaceBasicTags($cssLine, $pageData, $pageTitle, $pageDesc, $pageKeywords, $ogpImage);
            $cssLine = preg_replace('/\b(?:images|media)\//', $this->getSiteBasePath() . 'media/', $cssLine);
        }

        // 9. Embed CSS
        $cssLinkTag = "<style>\n" . implode("\n", array_unique($this->globalCssBuffer)) . "\n</style>";
        $html = preg_replace('/\{\{\s*HEAD_CSS\s*\}\}/i', $cssLinkTag, $html);
        $html = str_ireplace(['{{HEAD_CSS}}', '{{ HEAD_CSS }}'], $cssLinkTag, $html);

        return $this->enforceStandardMode($html);
    }

    /**
     * Enforces Standards Mode by ensuring the HTML starts with <!DOCTYPE html>
     * and removing any duplicate or misplaced DOCTYPE declarations.
     */
    private function enforceStandardMode($html) {
        // Only apply to HTML content (basic check)
        if (strpos(trim($html), '<html') === false && strpos(trim($html), '<!DOCTYPE') === false) {
             return $html;
        }

        // 1. Remove all existing DOCTYPE declarations to consolidate
        $html = preg_replace('/<!DOCTYPE[^>]*>/i', '', $html);
        
        // 2. Trim whitespace
        $html = trim($html);
        
        // 3. Prepend the standard DOCTYPE
        return "<!DOCTYPE html>\n" . $html;
    }

    private function parseComponents($content, array $visited = []) {
        if (empty($content)) return '';
        return preg_replace_callback('/\{\{COMPONENT:([a-zA-Z0-9_\-]+)\}\}/', function($matches) use ($visited) {
            $compId = $matches[1];

            // Security: Prevent Infinite Loops (DoS)
            if (in_array($compId, $visited)) {
                return "<!-- Infinite loop detected: Component '{$compId}' includes itself -->";
            }
            $visited[] = $compId;

            $compData = loadData(COMPONENTS_DIR, $compId);
            
            if (!$compData) return "<!-- Component '{$compId}' not found -->";
            
            $compHtml = $compData['html'] ?? '';
            $compCss  = $compData['css'] ?? '';
            $isGlobal = !empty($compData['is_global']);
            
            if (!empty($compCss)) {
                if ($isGlobal) {
                    $this->globalCssBuffer[] = "/* Component (Global): {$compId} */\n" . $compCss;
                } else {
                    $prefixClass = 'cmp-' . $compId;
                    $scopedCss = scopeCss($compCss, '.' . $prefixClass);
                    $this->globalCssBuffer[] = "/* Component: {$compId} */\n" . $scopedCss;
                    $compHtml = '<div class="' . $prefixClass . '">' . $compHtml . '</div>';
                }
            }
            return $this->parseComponents($compHtml, $visited);
        }, $content);
    }

    private function replaceBasicTags($text, $pageData, $pageTitle, $pageDesc, $pageKeywords, $ogpImage) {
        $siteUrl = $this->getSiteUrl();

        $replacements = [
            '/\{\{\s*SITE_NAME\s*\}\}/' => htmlspecialchars($this->siteSettings['site_name']),
            '/\{\{\s*SITE_DESCRIPTION\s*\}\}/' => htmlspecialchars($this->siteSettings['description']),
            '/\{\{\s*SITE_KEYWORDS\s*\}\}/' => htmlspecialchars($this->siteSettings['keywords']),
            '/\{\{\s*SITE_OGP_IMAGE\s*\}\}/' => $this->resolveAbsoluteUrl(resolveMediaPath($this->siteSettings['ogp_image'])),

            '/\{\{\s*PAGE_TITLE\s*\}\}/' => htmlspecialchars($pageData['title'] ?? ''),
            '/\{\{\s*PAGE_DESCRIPTION\s*\}\}/' => htmlspecialchars($pageData['description'] ?? ''),
            '/\{\{\s*PAGE_KEYWORDS\s*\}\}/' => htmlspecialchars($pageData['keywords'] ?? ''),
            '/\{\{\s*PAGE_OGP_IMAGE\s*\}\}/' => $this->resolveAbsoluteUrl(resolveMediaPath($pageData['ogp_image'] ?? '')),

            '/\{\{\s*TITLE\s*\}\}/' => htmlspecialchars($pageData['title'] ?? $this->siteSettings['site_name']),
            '/\{\{\s*DESCRIPTION\s*\}\}/' => htmlspecialchars($pageDesc),
            '/\{\{\s*OGP_IMAGE\s*\}\}/' => $this->resolveAbsoluteUrl(resolveMediaPath($ogpImage)),
            '/\{\{\s*FULL_TITLE\s*\}\}/' => htmlspecialchars($pageTitle),
            '/\{\{\s*KEYWORDS\s*\}\}/' => htmlspecialchars($pageKeywords),

            '/\{\{\s*SITE_URL\s*\}\}/' => $siteUrl,
            '/\{\{\s*BASE_URL\s*\}\}/' => $siteUrl, // Backward compatibility
            '/\{\{\s*UPDATE_DATE\s*\}\}/' => substr($pageData['updated_at'] ?? date('Y-m-d H:i:s'), 0, 10),
        ];

        // PAGE_URL: use canonical URL based on page status and structure
        $id = $this->currentPageId;
        $pageStatus = $pageData['status'] ?? 'draft';
        $isDirStyle = ($this->ssgStructure === 'directory');
        if ($pageStatus === 'public_static') {
            // Static page: URL matches the actual static file
            if ($id === 'index') {
                $pageRelPath = '/';
            } elseif ($isDirStyle) {
                $pageRelPath = '/' . $id . '/';
            } else {
                $pageRelPath = '/' . $id . '.html';
            }
            $pageFullUrl = rtrim($siteUrl, '/') . $pageRelPath;
        } else {
            $rootLink = $this->getPageLink($id, '');
            $pageFullUrl = $this->buildFullUrl($siteUrl, $rootLink);
        }
        $replacements['/\{\{\s*PAGE_URL\s*\}\}/'] = $pageFullUrl;

        foreach ($replacements as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        // UPDATE_DATE with format: JP = 年月日, SLASH = /区切り
        $rawDate = $pageData['updated_at'] ?? date('Y-m-d H:i:s');
        $text = preg_replace_callback('/\{\{\s*UPDATE_DATE:([A-Z]+)\s*\}\}/', function($m) use ($rawDate) {
            return $this->formatDate($rawDate, $m[1]);
        }, $text);

        // IS_NEW:days — "new" if updated within N days, else ""
        $text = preg_replace_callback('/\{\{\s*IS_NEW:(\d+)\s*\}\}/', function($m) use ($rawDate) {
            return $this->isNew($rawDate, (int)$m[1]) ? 'new' : '';
        }, $text);

        return $text;
    }

    private function formatDate($dateStr, $format) {
        $ts = strtotime($dateStr);
        if ($ts === false) return $dateStr;
        if ($format === 'JP')    return date('Y', $ts) . '年' . (int)date('n', $ts) . '月' . (int)date('j', $ts) . '日';
        if ($format === 'SLASH') return date('Y', $ts) . '/' . (int)date('n', $ts) . '/' . (int)date('j', $ts);
        return date('Y-m-d', $ts);
    }

    private function isNew($dateStr, $days) {
        $ts = strtotime($dateStr);
        return $ts !== false && (time() - $ts) < $days * 86400;
    }

    /** Combine siteUrl (includes base path) with an absolute link (also includes base path), avoiding duplication. */
    private function buildFullUrl($siteUrl, $absLink) {
        $base = rtrim($this->getSiteBasePath(), '/'); // e.g. '/mikanbox'
        if ($base !== '' && $base !== '/' && strpos($absLink, $base) === 0) {
            $absLink = substr($absLink, strlen($base)); // strip base prefix
        }
        return rtrim($siteUrl, '/') . $absLink;
    }

    public function getSiteUrl() {
        $ssgRootUrl = rtrim($this->siteSettings['ssg_root_url'] ?? '', '/');
        if (!empty($ssgRootUrl)) return $ssgRootUrl;
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . rtrim($this->getSiteBasePath(), '/');
    }

    private function getSiteBasePath() {
        // 1. index.phpが直接計算した値（動的モードで最も確実）
        if (!empty($this->siteSettings['_site_base'])) {
            return $this->siteSettings['_site_base'];
        }
        // 2. ssg_root_url が設定されている場合（SSG別ドメインデプロイ用）
        $rootUrl = trim($this->siteSettings['ssg_root_url'] ?? '');
        if (!empty($rootUrl)) {
            $path = parse_url($rootUrl, PHP_URL_PATH);
            if ($path && $path !== '/') {
                return rtrim($path, '/') . '/';
            }
            return '/';
        }
        // 3. SSGビルド時（admin.php経由）: DOCUMENT_ROOT と CORE_DIR から算出
        if (defined('CORE_DIR') && isset($_SERVER['DOCUMENT_ROOT'])) {
            $docRoot = rtrim(realpath($_SERVER['DOCUMENT_ROOT']) ?: $_SERVER['DOCUMENT_ROOT'], '/');
            $siteDir = rtrim(realpath(dirname(CORE_DIR)) ?: dirname(CORE_DIR), '/');
            if ($siteDir !== $docRoot && strpos($siteDir, $docRoot) === 0) {
                $sitePath = substr($siteDir, strlen($docRoot));
                if ($sitePath && $sitePath !== '/') {
                    return rtrim($sitePath, '/') . '/';
                }
            }
        }
        return '/';
    }

    private function resolveRootPath($url) {
        if (empty($url)) return $url;
        if (preg_match('/^https?:\/\//', $url)) return $url;
        if (strpos($url, '/') === 0) return $url;
        return $this->getSiteBasePath() . $url;
    }

    private function resolveAbsoluteUrl($url) {
        if (empty($url)) return $url;
        if (preg_match('/^https?:\/\//', $url)) return $url;
        return rtrim($this->getSiteUrl(), '/') . '/' . ltrim($url, '/');
    }

    private function protectCodeSpans($content, array &$codeMap) {
        // Fenced code blocks (``` or ~~~)
        $content = preg_replace_callback('/(`{3,}|~{3,})[^\n]*\n.*?\1/s', function($m) use (&$codeMap) {
            $key = '___CODEBLOCK' . count($codeMap) . '___';
            $codeMap[$key] = $m[0];
            return $key;
        }, $content);
        // Inline code spans (backtick)
        $content = preg_replace_callback('/`[^`\n]+`/', function($m) use (&$codeMap) {
            $key = '___CODEBLOCK' . count($codeMap) . '___';
            $codeMap[$key] = $m[0];
            return $key;
        }, $content);
        return $content;
    }

    private function restoreCodeSpans($content, array $codeMap) {
        return str_replace(array_keys($codeMap), array_values($codeMap), $content);
    }

    private function replaceSpecialTags($html) {
        // 3. Navigation Tags: {{NAV_LINKS:category}}, {{NAV_CARDS:category:template}}
        $html = preg_replace_callback('/\{\{\s*(NAV_LINKS|NAV_CARDS)\s*(?::\s*([^:}\s]*)\s*)?(?::\s*([a-zA-Z0-9_\-]+)\s*)?\}\}/u', function($matches) {
            $tagType = trim($matches[1]);
            $category = isset($matches[2]) ? trim($matches[2]) : '';
            $compId = isset($matches[3]) ? trim($matches[3]) : '';
            
            if ($tagType === 'NAV_LINKS') {
                return $this->generateNavLinks($category);
            }
            if ($tagType === 'NAV_CARDS') {
                return $this->generateNavCards($category, $compId);
            }
            return $matches[0];
        }, $html);

        // Media Tags (Direct expansion, though applyPathCompletion also handles standard tags)
        $mediaBase = rtrim($this->getSiteUrl(), '/') . '/media/';
        $html = preg_replace('/\{\{VIDEO:([a-zA-Z0-9_\-\.]+)\}\}/', '<video src="' . $mediaBase . '$1" controls style="max-width:100%; height:auto;"></video>', $html);
        $html = preg_replace('/\{\{IMAGE:([a-zA-Z0-9_\-\.]+)\}\}/', '<img src="' . $mediaBase . '$1" style="max-width:100%; height:auto;">', $html);
        $html = preg_replace('/\{\{AUDIO:([a-zA-Z0-9_\-\.]+)\}\}/', '<audio src="' . $mediaBase . '$1" controls style="width:100%; margin:10px 0;"></audio>', $html);

        // EXT_MD:url#rowID:KEY or EXT_MD:url:KEY — extract DATA block (must come before full EXT_MD)
        $html = preg_replace_callback('/\{\{EXT_MD:(https?:\/\/[^\}#]+?)(?:#([a-zA-Z0-9_\-]+))?:([a-zA-Z][a-zA-Z0-9_]*)\}\}/', function($matches) {
            $url   = $matches[1];
            $rowId = $matches[2];
            $key   = $matches[3];
            $lookupKey = $rowId ? "#{$rowId}:{$key}" : $key;
            $context = stream_context_create(['http' => ['timeout' => 5]]);
            $externalContent = @file_get_contents($url, false, $context);
            if ($externalContent === false) return "<!-- Error: Fetch failed $url -->";
            $externalContent = $this->sanitizeExternalContent($externalContent);
            $dataBlocks = [];
            $this->extractDataBlocks($externalContent, $dataBlocks);
            return $dataBlocks[$lookupKey] ?? '';
        }, $html);

        // EXT_MD:url — render full external content
        $html = preg_replace_callback('/\{\{EXT_MD:(https?:\/\/[^\}]+)\}\}/', function($matches) {
            $url = $matches[1];
            $context = stream_context_create(['http' => ['timeout' => 5]]);
            $externalContent = @file_get_contents($url, false, $context);
            if ($externalContent === false) return "<!-- Error: Fetch failed $url -->";
            $sanitized = $this->sanitizeExternalContent($externalContent);
            $dataBlocks = [];
            $sanitized = $this->extractDataBlocks($sanitized, $dataBlocks);
            return $this->markdown->text($sanitized);
        }, $html);

        // POST_MD:pageID#rowID:KEY — another page, specific DATAROW (must come first)
        $html = preg_replace_callback('/\{\{POST_MD:([a-zA-Z0-9_\-\/]+)#([a-zA-Z0-9_\-]+):([a-zA-Z][a-zA-Z0-9_]*)\}\}/', function($matches) {
            $postData = loadData(POSTS_DIR, $matches[1]);
            if (!$postData || empty($postData['content_md'])) return '';
            $dataBlocks = [];
            $this->extractDataBlocks($postData['content_md'], $dataBlocks);
            return $dataBlocks["#{$matches[2]}:{$matches[3]}"] ?? '';
        }, $html);

        // POST_MD::#rowID:KEY — self page, specific DATAROW (must come before ::KEY)
        $html = preg_replace_callback('/\{\{POST_MD::#([a-zA-Z0-9_\-]+):([a-zA-Z][a-zA-Z0-9_]*)\}\}/', function($matches) {
            return $this->currentPageData["#{$matches[1]}:{$matches[2]}"] ?? '';
        }, $html);

        // POST_MD:ID:KEY or POST_MD::KEY — field (no row)
        $html = preg_replace_callback('/\{\{POST_MD:([a-zA-Z0-9_\-\/]*):([a-zA-Z][a-zA-Z0-9_]*)\}\}/', function($matches) {
            $postId = $matches[1];
            $key    = $matches[2];
            if ($postId === '') {
                return $this->currentPageData[$key] ?? '';
            }
            $postData = loadData(POSTS_DIR, $postId);
            if (!$postData || empty($postData['content_md'])) return '';
            $dataBlocks = [];
            $this->extractDataBlocks($postData['content_md'], $dataBlocks);
            return $dataBlocks[$key] ?? '';
        }, $html);

        // POST_MD:ID — full markdown of another page (with tag expansion)
        $html = preg_replace_callback('/\{\{POST_MD:([a-zA-Z0-9_\-\/]+)\}\}/', function($matches) {
            $postId = $matches[1];
            $postData = loadData(POSTS_DIR, $postId);
            if (!$postData || empty($postData['content_md'])) return "<!-- Error: Post not found $postId -->";
            $savedPageId   = $this->currentPageId;
            $savedPageData = $this->currentPageData;
            $this->currentPageId   = $postId;
            $this->currentPageData = [];
            $content = $postData['content_md'];
            $codeMap = [];
            $content = $this->protectCodeSpans($content, $codeMap);
            $content = $this->extractDataBlocks($content, $this->currentPageData);
            $content = $this->restoreCodeSpans($content, $codeMap);
            $protected = preg_replace_callback('/\{\{[^\}]+\}\}/', function($m) {
                return '<!--MKNTG' . base64_encode($m[0]) . '-->';
            }, $content);
            $embeddedHtml = $this->markdown->text($protected);
            $embeddedHtml = preg_replace_callback('/<!--MKNTG([a-zA-Z0-9+\/=]+)-->/', function($m) {
                return base64_decode($m[1]);
            }, $embeddedHtml);
            $postTitle    = isset($postData['title']) ? $postData['title'] . ' - ' . $this->siteSettings['site_name'] : $this->siteSettings['site_name'];
            $postDesc     = !empty($postData['description']) ? $postData['description'] : $this->siteSettings['description'];
            $postKeywords = !empty($postData['keywords'])    ? $postData['keywords']    : $this->siteSettings['keywords'];
            $postOgp      = !empty($postData['ogp_image'])   ? $postData['ogp_image']   : $this->siteSettings['ogp_image'];
            $embeddedHtml = $this->replaceBasicTags($embeddedHtml, $postData, $postTitle, $postDesc, $postKeywords, $postOgp);
            $this->currentPageId   = $savedPageId;
            $this->currentPageData = $savedPageData;
            return $embeddedHtml;
        }, $html);

        return $html;
    }

    /**
     * Strip dangerous content from externally fetched HTML/Markdown.
     * Removes <script> blocks, on* event handlers, and javascript: hrefs.
     */
    private function sanitizeExternalContent($content) {
        $content = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $content);
        $content = preg_replace('/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $content);
        $content = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']/i', 'href="#"', $content);
        return $content;
    }

    /**
     * Extract {{DATA:KEY}}value{{/DATA}} and {{DATA:KEY:GHOST}}value{{/DATA}} blocks.
     * Visible blocks are replaced with their value; GHOST blocks are removed from output.
     * All values are stored in $dataBlocks by reference.
     */
    private function extractDataBlocks($content, array &$dataBlocks) {
        // {{DATAROW:id}}...{{/DATAROW}} — named record; inner fields stored as #id:KEY, block removed from output
        $content = preg_replace_callback(
            '/\{\{DATAROW:([a-zA-Z0-9_\-]+)\}\}(.*?)\{\{\/DATAROW\}\}/s',
            function($m) use (&$dataBlocks) {
                $rowId = $m[1];
                $inner = $m[2];
                // Extract GHOST fields within row
                preg_replace_callback(
                    '/\{\{DATA:([a-zA-Z][a-zA-Z0-9_]*):GHOST\}\}(.*?)\{\{\/DATA\}\}/s',
                    function($d) use ($rowId, &$dataBlocks) {
                        $dataBlocks["#{$rowId}:{$d[1]}"] = trim($d[2]);
                        return '';
                    }, $inner
                );
                // Extract visible fields within row
                preg_replace_callback(
                    '/\{\{DATA:([a-zA-Z][a-zA-Z0-9_]*)\}\}(.*?)\{\{\/DATA\}\}/s',
                    function($d) use ($rowId, &$dataBlocks) {
                        $dataBlocks["#{$rowId}:{$d[1]}"] = trim($d[2]);
                        return '';
                    }, $inner
                );
                return ''; // Remove entire DATAROW block from output
            },
            $content
        );
        // {{DATA:KEY:GHOST}}value{{/DATA}} — invisible, stored only
        $content = preg_replace_callback(
            '/\{\{DATA:([a-zA-Z][a-zA-Z0-9_]*):GHOST\}\}(.*?)\{\{\/DATA\}\}/s',
            function($m) use (&$dataBlocks) {
                $dataBlocks[$m[1]] = trim($m[2]);
                return '';
            },
            $content
        );
        // {{DATA:KEY}}value{{/DATA}} — visible, stored and shown
        $content = preg_replace_callback(
            '/\{\{DATA:([a-zA-Z][a-zA-Z0-9_]*)\}\}(.*?)\{\{\/DATA\}\}/s',
            function($m) use (&$dataBlocks) {
                $dataBlocks[$m[1]] = trim($m[2]);
                return $m[2];
            },
            $content
        );
        return $content;
    }

    private function applyPathCompletion($html) {
        $allPosts = getSortedPostIds();
        
        // 1. Rewrite internal links to be root-relative (e.g. /pages/p3/)
        $html = preg_replace_callback('/href=["\'](\.?\/?)([^"\']+)["\']/i', function($matches) use ($allPosts) {
            $prefix = $matches[1];
            $url = $matches[2];

            // Skip external links, hashes, mailto, or already absolute paths
            // Check full original URL (prefix + url) to catch root-relative paths like /mikanbox/page
            if (preg_match('/^(?:https?:\/\/|#|mailto:|\/)/i', $url) || $prefix === '/') {
                return $matches[0];
            }

            // Normalize: strip .html and trailing slash for comparison
            $checkId = $url;
            if (str_ends_with(strtolower($checkId), '.html')) {
                $checkId = substr($checkId, 0, -5);
            }
            $checkId = trim($checkId, '/');

            // Find matching post (case-insensitive)
            foreach ($allPosts as $pid) {
                if (strcasecmp($checkId, $pid) === 0) {
                    return 'href="' . $this->buildFullUrl($this->getSiteUrl(), $this->getPageLink($pid, '')) . '"';
                }
            }

            // Special fallback: if it looks like an ID (no extension, has slash etc.)
            // and we didn't find a direct match, but it's clearly an internal link attempt
            if (preg_match('/^[a-z0-9_\-\/]+$/i', $checkId)) {
                return 'href="' . $this->buildFullUrl($this->getSiteUrl(), $this->getPageLink($checkId, '')) . '"';
            }

            // No match, return original
            return $matches[0];
        }, $html);

        // Fix Home link "./" to site base
        $siteUrl = $this->getSiteUrl();
        $html = preg_replace('/href=["\']\.\/["\']/', 'href="' . $siteUrl . '"', $html);

        // 2. Rewrite media paths to full URLs (e.g. https://example.com/mikanbox/media/...)
        $mediaBase = rtrim($siteUrl, '/') . '/media/';
        return preg_replace_callback('/<(img|video|audio|source)\b([^>]*)\bsrc=["\']([^"\'\s>]+)["\']/i', function($m) use ($mediaBase) {
            $tag = $m[1];
            $attrs = $m[2];
            $src = $m[3];

            if (preg_match('/^(?:https?:\/\/|data:)/i', $src)) {
                return $m[0];
            }

            // Convert images/myfile.jpg or myfile.jpg to full URL
            $filename = basename($src);
            return "<{$tag}{$attrs}src=\"{$mediaBase}{$filename}\"";
        }, $html);
    }

    private function generateNavLinks($targetCategory) {
        $postsList = getSortedPostIds();
        $html = '<ul class="nav-links">';

        foreach ($postsList as $postId) {
            $postInfo = loadData(POSTS_DIR, $postId);
            if (in_array($postInfo['status'] ?? 'public', ['draft', 'db'])) continue;
            if (($postInfo['sort_order'] ?? 0) < 0) continue;

            $catStr = $postInfo['category'] ?? '';
            $cats = array_filter(array_map('trim', explode(',', $catStr)));
            
            if ($targetCategory !== '') {
                if ($targetCategory !== 'all' && !in_array($targetCategory, $cats)) continue;
            } elseif (!empty($cats)) continue;

            $title = htmlspecialchars($postInfo['title'] ?? $postId, ENT_QUOTES);
            $activeClass = ($this->currentPageId === $postId) ? ' class="active"' : '';
            $link = $this->buildFullUrl($this->getSiteUrl(), $this->getPageLink($postId, ''));
            $html .= "<li{$activeClass}><a href=\"{$link}\">{$title}</a></li>";
        }
        $html .= '</ul>';
        return $html;
    }

    private function generateNavCards($targetCategory, $templateCompId) {
        $postsList = getSortedPostIds();
        $effectiveCompId = $templateCompId ?: '_nav_card';
        $compData = loadData(COMPONENTS_DIR, $effectiveCompId);
        $customTemplate = null;
        $prefixClass = '';

        if ($compData) {
            $customTemplate = $compData['html'] ?? '';
            $compCss = $compData['css'] ?? '';
            if (!empty($compCss)) {
                if (!empty($compData['is_global'])) {
                    $this->globalCssBuffer[] = "/* Nav Card (Global): {$effectiveCompId} */\n" . $compCss;
                } else {
                    $prefixClass = 'cmp-' . $effectiveCompId;
                    $this->globalCssBuffer[] = "/* Nav Card: {$effectiveCompId} */\n" . scopeCss($compCss, '.' . $prefixClass);
                }
            }
        }

        $innerHtml = '';
        foreach ($postsList as $postId) {
            $postInfo = loadData(POSTS_DIR, $postId);
            if (in_array($postInfo['status'] ?? 'public', ['draft', 'db'])) continue;
            if (($postInfo['sort_order'] ?? 0) < 0) continue;

            $catStr = $postInfo['category'] ?? '';
            $cats = array_filter(array_map('trim', explode(',', $catStr)));

            if ($targetCategory !== '') {
                if ($targetCategory !== 'all' && !in_array($targetCategory, $cats)) continue;
            } elseif (!empty($cats)) continue;

            $title = htmlspecialchars($postInfo['title'] ?? $postId, ENT_QUOTES);
            $desc = htmlspecialchars($postInfo['description'] ?? '', ENT_QUOTES);
            $rawImg = resolveMediaPath($postInfo['ogp_image'] ?? '');
            if (!empty($rawImg) && !preg_match('/^(?:https?:\/\/|data:)/', $rawImg)) {
                $rawImg = rtrim($this->getSiteUrl(), '/') . '/' . ltrim($rawImg, '/');
            }
            if (empty($rawImg)) {
                $rawImg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='210'%3E%3Crect width='400' height='210' fill='%23e0e0e0'/%3E%3C/svg%3E";
            }
            $img = htmlspecialchars($rawImg, ENT_QUOTES);

            $link = $this->buildFullUrl($this->getSiteUrl(), $this->getPageLink($postId, ''));
            $pageUrl = $link;
            $updateDate = substr($postInfo['updated_at'] ?? date('Y-m-d H:i:s'), 0, 10);
            $isActive = ($this->currentPageId === $postId) ? 'active' : '';

            if ($customTemplate) {
                $itemHtml = str_replace(
                    ['{{TITLE}}', '{{DESCRIPTION}}', '{{LINK}}', '{{OGP_IMAGE}}', '{{PAGE_URL}}', '{{UPDATE_DATE}}', '{{IS_ACTIVE}}'],
                    [$title, $desc, $link, $img, $pageUrl, $updateDate, $isActive],
                    $customTemplate
                );
                $rawDate = $postInfo['updated_at'] ?? date('Y-m-d H:i:s');
                $itemHtml = preg_replace_callback('/\{\{UPDATE_DATE:([A-Z]+)\}\}/', function($m) use ($rawDate) {
                    return $this->formatDate($rawDate, $m[1]);
                }, $itemHtml);
                $itemHtml = preg_replace_callback('/\{\{IS_NEW:(\d+)\}\}/', function($m) use ($rawDate) {
                    return $this->isNew($rawDate, (int)$m[1]) ? 'new' : '';
                }, $itemHtml);
                // Expand POST_MD:: tags using this card's own DATA blocks
                if (!empty($postInfo['content_md'])) {
                    $cardDataBlocks = [];
                    $this->extractDataBlocks($postInfo['content_md'], $cardDataBlocks);
                    // POST_MD::#rowID:KEY — self with row
                    $itemHtml = preg_replace_callback('/\{\{POST_MD::#([a-zA-Z0-9_\-]+):([a-zA-Z][a-zA-Z0-9_]*)\}\}/', function($m) use ($cardDataBlocks) {
                        return $cardDataBlocks["#{$m[1]}:{$m[2]}"] ?? '';
                    }, $itemHtml);
                    // POST_MD::KEY — self field
                    $itemHtml = preg_replace_callback('/\{\{POST_MD::([a-zA-Z][a-zA-Z0-9_]*)\}\}/', function($m) use ($cardDataBlocks) {
                        return $cardDataBlocks[$m[1]] ?? '';
                    }, $itemHtml);
                }
                $innerHtml .= $itemHtml;
            } else {
                $imgHtml = $img ? '<div class="nav-card-img"><img src="' . $img . '" alt=""></div>' : '';
                $innerHtml .= sprintf('<a href="%s" class="nav-card">%s<div class="nav-card-content"><h3 class="nav-card-title">%s</h3>%s</div></a>', 
                    $link, $imgHtml, $title, ($desc ? '<p class="nav-card-desc">' . $desc . '</p>' : ''));
            }
        }

        $output = '<div class="nav-cards">' . $innerHtml . '</div>';
        return $prefixClass ? '<div class="' . $prefixClass . '">' . $output . '</div>' : $output;
    }

    public function getPageLink($postId, $relPrefix) {
        $isDirStyle = ($this->ssgStructure === 'directory');
        // Root-relative path (site base, e.g. /mikanbox/ for subdirectory installs)
        $root = $this->getSiteBasePath();
        
        if ($this->staticMode) {
            // Static Build Context: 
            // In directory style, we link to '/path/'. In file style, '/path.html'.
            if ($postId === 'index') {
                return ($isDirStyle) ? $root : $root . 'index.html';
            }
            
            $parts = explode('/', $postId);
            $encodedParts = array_map('urlencode', $parts);
            $path = implode('/', $encodedParts);

            if ($isDirStyle) {
                return $root . $path . '/';
            } else {
                return $root . $path . '.html';
            }
        }
        
        // Dynamic mode
        // For clean URLs, we return the path from the base.
        $id = ($postId === 'index') ? '' : $postId;
        
        if ($isDirStyle) {
            // In directory style, we prefer trailing slashes for consistency with SSG
            return $id === '' ? $root : $root . $id . '/';
        } else {
            // Dynamic mode, file style: clean URL without extension
            return $id === '' ? $root : $root . $id;
        }
    }
}
