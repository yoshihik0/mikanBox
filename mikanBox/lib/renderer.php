<?php
if (PHP_SAPI !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) { http_response_code(403); exit; } // direct request guard
/**
 * mikanBox Renderer Class
 * Centralizes rendering logic for both dynamic and static display.
 */
class MikanBoxRenderer {
    private $siteSettings;
    private $markdown;
    private $staticMode = false;
    private $currentPageId = '';
    private $globalCssBuffer = [];
    private $javascriptBuffer = [];
    private $javascriptComponentIds = [];
    private $includedComponentIds = [];
    private $excludeTracking = false;
    private $depth = 0;
    private $depthSetManually = false;
    private $ssgStructure = 'directory';
    private $ssgLinkMode = 'absolute';
    private $currentPageData = []; // DATA blocks of current page
    // Output formats (2.17.0): 'html' renders the page, 'md' its Markdown version,
    // 'data' the body of a data-format page (json, xml, csv, txt, js, css).
    private $outputMode = 'html';
    private $dataFormat = '';
    private $forceAbsolute = false;   // Markdown versions always use absolute URLs
    private $linkToMarkdown = false;  // in Markdown versions, link to other pages' Markdown versions
    private $currentPageInfo = [];    // stored fields of the page being rendered (for @same)
    private $pageCache = [];
    private $dataHashes = [];         // static build: page ID => hash of its built data output
    private static $hashStack = [];

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
    public function getPageDataBlocks($pageId, $postData = null) {
        $postData = $postData ?? loadData(POSTS_DIR, $pageId);
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

    public function setStaticMode($enabled, $depth = 0, $structure = 'directory', $linkMode = 'absolute') {
        $this->staticMode = $enabled;
        $this->depth = $depth;
        $this->depthSetManually = true; // Use manual depth (from SSG)
        $this->ssgStructure = $structure;
        $this->ssgLinkMode = in_array($linkMode, ['absolute', 'relative'], true) ? $linkMode : 'absolute';
    }

    /**
     * Renders a page by its ID.
     */
    public function render($pageId, $previewData = null) {
        $this->outputMode = 'html';
        $this->dataFormat = '';
        $this->forceAbsolute = false;
        $this->linkToMarkdown = false;
        $this->pageCache = [];
        $this->currentPageId = $pageId;
        $this->globalCssBuffer = [];
        $this->javascriptBuffer = [];
        $this->javascriptComponentIds = [];
        $this->includedComponentIds = [];
        // The "first large image is eager, the rest are lazy" rule in
        // mediaImgAttrs() is per page, so clear the flag before each render.
        mediaResetImageFlow();
        
        // Calculate depth automatically in dynamic mode if not set manually
        if (!$this->depthSetManually) {
            if ($pageId === 'index') {
                $this->depth = 0;
            } else {
                $this->depth = substr_count($pageId, '/');
            }
        }

        // Determine directories
        $pageData = $previewData ?? loadData(POSTS_DIR, $pageId);
        if (!$pageData) {
            http_response_code(404);
            $pageData = loadData(POSTS_DIR, '404') ?: [
                'title' => '404 Not Found',
                'content_md' => "# 404 Not Found\n" . t('err_404_body'),
                'wrapper_comp' => '_layout'
            ];
        }

        // Check for private status (only if not in static mode or authenticated)
        // NOTE: branches on function_exists('getPreviewToken') so this file can be
        // byte-identical between the SQLite backend (approval/preview workflow) and
        // the JSON flat-file backend (no approval workflow, no getPreviewToken()).
        $isPrivate = false;
        $status = $pageData['status'] ?? '';
        if (function_exists('getPreviewToken')) {
            // SQLite backend: supports 'pending' approval status with a preview token.
            if ($status === 'draft' || $status === 'db') {
                $isPrivate = true;
            } elseif ($status === 'pending') {
                if (!verifyPreviewToken($pageId, $_GET['preview'] ?? '')) {
                    $isPrivate = true;
                } elseif (!$this->staticMode && !headers_sent()) {
                    // The URL is a bearer credential: keep it out of Referer, caches and search indexes.
                    header('Referrer-Policy: no-referrer');
                    header('X-Robots-Tag: noindex, nofollow');
                    header('Cache-Control: no-store');
                }
            }
            if (!$this->staticMode && $isPrivate && (($_SESSION['admin_logged_in'] ?? false) !== true)) {
                http_response_code(403);
                return "<h1>403 Forbidden</h1><p>" . t('err_403_pending_body') . "</p>";
            }
        } else {
            // Flat backend: simple draft/db check, no approval workflow.
            if (!$this->staticMode && in_array($status, ['draft', 'db'], true) && (($_SESSION['admin_logged_in'] ?? false) !== true)) {
                return "<!-- Private content -->";
            }
        }

        $this->excludeTracking = !empty($pageData['exclude_tracking']);
        $this->currentPageInfo = $pageData;

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
            // A zero-width space (U+200B) is added on both sides of the placeholder so a line
            // ending OR starting with a tag doesn't look like "adjacent to an HTML tag" to the
            // Markdown parser's <br> line-break logic (which suppresses <br> right next to a bare
            // '<' or '>'). htmlspecialchars() (used for lines inside ``` code blocks) leaves the
            // ZWSPs untouched, so the escaped-placeholder restore step below still matches them.
            $protectedMd = preg_replace_callback('/\{\{[^\}]+\}\}/', function($m) {
                return "\xE2\x80\x8B<!--MKNTG" . base64_encode($m[0]) . "-->\xE2\x80\x8B";
            }, $contentMd);

            $contentHtml = $this->restoreMarkdownUrlTags($this->markdown->text($protectedMd));
            $contentHtml = preg_replace_callback('/\x{200B}?<!--MKNTG([a-zA-Z0-9+\/=]+)-->\x{200B}?/u', function($m) {
                return base64_decode($m[1]);
            }, $contentHtml);
            // HTML-encoded placeholders (inside <code>) are left as-is here,
            // and restored after all tag replacements in the final step below.
        }

        // 2. Load Wrapper
        $wrapperCompId = isset($pageData['wrapper_comp']) ? $pageData['wrapper_comp'] : '_layout';
        $wrapperData = $wrapperCompId === '__none' ? null : loadData(COMPONENTS_DIR, $wrapperCompId);
        $wrapperJsId = $wrapperData ? $wrapperCompId : '_layout';
        if ($wrapperData) $this->includedComponentIds[$wrapperJsId] = true;
        $wrapperData = $wrapperCompId === '__none' ? null : ($wrapperData ?: loadData(COMPONENTS_DIR, '_layout'));
        $html = $wrapperData['html'] ?? '{{CONTENT}}';

        // Embed main content into the wrapper
        // Page-specific CSS is scoped to a wrapper div around the content (like component/wrapper
        // CSS already is), so it can't leak into the header/footer/nav outside {{CONTENT}}.
        // Only added when page CSS is actually present, so a full pasted HTML document
        // (e.g. with no wrapper component selected) isn't nested inside an extra <div> unnecessarily.
        $hasPageCss = !empty($pageData['css']);
        $contentToEmbed = $hasPageCss ? '<div class="mikan-content-scope">' . $contentHtml . '</div>' : $contentHtml;
        if ($hasPageCss) {
            $bodyScoped = false;
            $scopedDocument = preg_replace_callback('~<!--[\s\S]*?-->|<(script|style|textarea|title)\b[^>]*>[\s\S]*?</\1\s*>|<body\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>~i', function($m) use (&$bodyScoped) {
                if ($bodyScoped || !preg_match('~^<body\b~i', $m[0])) return $m[0];
                $bodyScoped = true;
                $attrs = substr($m[0], 5, -1);
                $classPattern = '~(^|\s)class\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~i';
                if (preg_match($classPattern, $attrs)) {
                    $attrs = preg_replace_callback($classPattern, function($a) {
                        $value = ($a[2] ?? '') !== '' ? $a[2] : (($a[3] ?? '') !== '' ? $a[3] : ($a[4] ?? ''));
                        return $a[1] . 'class="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false) . ' mikan-content-scope"';
                    }, $attrs, 1);
                } else $attrs .= ' class="mikan-content-scope"';
                return '<body' . $attrs . '>';
            }, $contentHtml);
            if ($bodyScoped) $contentToEmbed = $scopedDocument;
        }
        $html = str_replace('{{CONTENT}}', $contentToEmbed, $html);

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
        if ($hasPageCss) {
            $scopedPageCss = scopeCss($pageData['css'], '.mikan-content-scope');
            $this->globalCssBuffer[] = "/* Page CSS */\n" . $scopedPageCss;
        }

        // 4. Parse components (recursively)
        $html = $this->parseComponents($html);

        $trackingHtml = '';
        // Code or ID settings win over the legacy tracking component and are inserted verbatim.
        $trackingCode = $this->excludeTracking ? null : mikanBoxTrackingSnippets($this->siteSettings);
        $trackingId = $trackingCode === null ? (string)($this->siteSettings['tracking_component'] ?? '') : '';
        $tracking = $trackingId !== '' ? loadData(COMPONENTS_DIR, $trackingId) : null;
        if (!$this->excludeTracking && $tracking && empty($tracking['is_ai_doc']) && empty($tracking['is_wrapper']) && !isset($this->includedComponentIds[$trackingId])) {
            $trackingHtml = $this->parseComponents('{{COMPONENT:' . $trackingId . '}}');
        }
        // Process tracking markup through the same tag/path pipeline as the page.
        $trackingMarker = "\x02TRACKING\x03";
        if ($trackingHtml !== '') $html .= $trackingMarker . $trackingHtml;

        // Protect HTML comments (from the wrapper and every included component) before
        // steps 5-7, so a {{TITLE}}/{{POST_MD:...}}/etc. tag mentioned inside one as
        // documentation text isn't expanded or path-rewritten — comments must stay inert.
        $renderCommentMap = [];
        $html = preg_replace_callback('/<!--.*?-->/s', function($m) use (&$renderCommentMap) {
            $ph = "\x02RENDERCOMMENT" . count($renderCommentMap) . "\x03";
            $renderCommentMap[$ph] = $m[0];
            return $ph;
        }, $html);

        // 5. Expand basic tags
        $html = $this->replaceBasicTags($html, $pageData, $pageTitle, $pageDesc, $pageKeywords, $ogpImage);

        // 6. Expand Navigation/Special Tags
        $html = $this->replaceSpecialTags($html);

        // 7. Final path completion
        $html = $this->applyPathCompletion($html);

        foreach ($renderCommentMap as $ph => $original) {
            $html = str_replace($ph, $original, $html);
        }

        // 7.5. Restore HTML-encoded placeholders left inside <code> blocks as literal text
        $html = preg_replace_callback('/\x{200B}?&lt;!--MKNTG([a-zA-Z0-9+\/=]+)--&gt;\x{200B}?/u', function($m) {
            return htmlspecialchars(base64_decode($m[1]));
        }, $html);

        $trackingHtml = '';
        if (($trackingOffset = strpos($html, $trackingMarker)) !== false) {
            $trackingHtml = substr($html, $trackingOffset + strlen($trackingMarker));
            $html = substr($html, 0, $trackingOffset);
        }
        if ($trackingCode !== null) $trackingHtml = $trackingCode['head'];
        // A page that also publishes a Markdown version announces it, the way RSS is discovered.
        if (mikanBoxPageOutputFormat($pageData) === 'html_md' && mikanBoxMarkdownAvailable($this->siteSettings)) {
            $trackingHtml = '<link rel="alternate" type="text/markdown" href="'
                . htmlspecialchars($this->absoluteExtensionUrl($pageId, 'md'), ENT_QUOTES) . '">' . $trackingHtml;
        }
        $html = $this->completeHead($html, $pageTitle, $pageDesc, $this->resolveMediaUrl(resolveMediaPath($ogpImage)), $trackingHtml);
        if ($trackingCode !== null) $html = $this->insertBodyStart($html, $trackingCode['body']);

        // 8. Process CSS Buffer
        foreach ($this->globalCssBuffer as &$cssLine) {
            $cssLine = $this->replaceBasicTags($cssLine, $pageData, $pageTitle, $pageDesc, $pageKeywords, $ogpImage);
            $cssMediaBase = $this->isRelativeStaticMode()
                ? $this->getStaticRootPrefix() . 'media/'
                : $this->getSiteBasePath() . 'media/';
            $cssLine = preg_replace_callback(
                '/url\(\s*(["\']?)(?:images|media)\/([^)"\']+)\1\s*\)/i',
                fn($m) => 'url(' . $m[1] . $cssMediaBase . $m[2] . $m[1] . ')',
                $cssLine
            );
        }

        // 9. Embed CSS
        $cssLinkTag = "<style>\n" . implode("\n", array_unique($this->globalCssBuffer)) . "\n</style>";
        $html = $this->embedCss($html, $cssLinkTag, !empty($this->globalCssBuffer));

        // Keep JS out of Markdown, template expansion and path rewriting. Component
        // scripts run once per ID in first-inclusion order, followed by any remaining
        // layout script and the page script.
        $this->collectJavaScript($wrapperData ?? [], $wrapperJsId);
        $this->collectJavaScript($pageData);
        return $this->embedJavaScript($this->enforceStandardMode($html));
    }

    private function insertBodyStart(string $html, string $code): string {
        if ($code === '') return $html;
        $pattern = '~<!--[\s\S]*?-->|<(script|style|textarea|title)\b[^>]*>[\s\S]*?</\1\s*>|</?[a-z][a-z0-9:-]*\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>~i';
        preg_match_all($pattern, $html, $tokens, PREG_OFFSET_CAPTURE);
        foreach ($tokens[0] as [$tag, $offset]) {
            if (!preg_match('~^<body\b~i', $tag)) continue;
            $at = $offset + strlen($tag);
            return substr($html, 0, $at) . $code . substr($html, $at);
        }
        return $html;
    }

    private function completeHead(string $html, string $title, string $description, string $image, string $tracking): string {
        $pattern = '~<!--[\s\S]*?-->|<(script|style|textarea|title)\b[^>]*>[\s\S]*?</\1\s*>|</?[a-z][a-z0-9:-]*\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>~i';
        preg_match_all($pattern, $html, $tokens, PREG_OFFSET_CAPTURE);
        $start = null; $end = null; $htmlStart = null;
        foreach ($tokens[0] as [$tag, $offset]) {
            if (preg_match('~^<html\b~i', $tag)) $htmlStart = $offset + strlen($tag);
            if ($start === null && preg_match('~^<head\b~i', $tag)) $start = $offset + strlen($tag);
            if ($start !== null && preg_match('~^</head\s*>~i', $tag)) { $end = $offset; break; }
        }
        if ($start === null) {
            if ($htmlStart !== null) $html = substr($html, 0, $htmlStart) . '<head></head>' . substr($html, $htmlStart);
            else $html = '<!DOCTYPE html><html><head></head><body>' . $html . '</body></html>';
            return $this->completeHead($html, $title, $description, $image, $tracking);
        }
        if ($end === null) {
            // Repair a missing closing head before the body, or at the end.
            $body = null;
            foreach ($tokens[0] as [$tag, $offset]) if ($offset >= $start && preg_match('~^<body\b~i', $tag)) { $body = $offset; break; }
            $end = $body ?? strlen($html);
            $html = substr($html, 0, $end) . '</head>' . substr($html, $end);
        }
        $head = substr($html, $start, $end - $start);
        preg_match_all($pattern, $head, $headTokens, PREG_OFFSET_CAPTURE);
        $present = []; $charsetEnd = 0;
        foreach ($headTokens[0] as [$tag, $offset]) {
            if (preg_match('~^<title\b~i', $tag)) $present['title'] = true;
            if (!preg_match('~^<meta\b~i', $tag)) continue;
            preg_match_all('~([a-z][a-z0-9:_-]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~i', $tag, $attributes, PREG_SET_ORDER);
            $attrs = [];
            foreach ($attributes as $a) $attrs[strtolower($a[1])] = strtolower(html_entity_decode($a[2] !== '' ? $a[2] : (($a[3] ?? '') !== '' ? $a[3] : ($a[4] ?? '')), ENT_QUOTES, 'UTF-8'));
            if (isset($attrs['charset']) || ($attrs['http-equiv'] ?? '') === 'content-type') { $present['charset'] = true; $charsetEnd = $offset + strlen($tag); }
            if (isset($attrs['name'])) $present[$attrs['name']] = true;
            if (isset($attrs['property'])) $present[$attrs['property']] = true;
        }
        $escape = fn($value) => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $prefix = isset($present['charset']) ? '' : '<meta charset="UTF-8">';
        if (!isset($present['viewport'])) $prefix .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        if (!isset($present['title'])) $prefix .= '<title>' . $escape($title) . '</title>';
        if (!isset($present['description']) && $description !== '') $prefix .= '<meta name="description" content="' . $escape($description) . '">';
        if (!isset($present['og:image']) && $image !== '') $prefix .= '<meta property="og:image" content="' . $escape($image) . '">';
        // Keep an existing encoding declaration ahead of potentially long tracking code.
        $head = substr($head, 0, $charsetEnd) . $prefix . $tracking . substr($head, $charsetEnd);
        return substr($html, 0, $start) . $head . substr($html, $end);
    }

    private function embedCss(string $html, string $cssTag, bool $hasCss): string {
        // Comments and raw-text elements contain examples/code, not insertion points.
        $inert = '<!--[\s\S]*?-->|<(script|style|textarea|title)\b[^>]*>[\s\S]*?</\1\s*>';
        $inserted = false;
        $html = preg_replace_callback('~' . $inert . '|\{\{\s*HEAD_CSS\s*\}\}~i', function($match) use (&$inserted, $cssTag) {
            if (substr($match[0], 0, 2) !== '{{') return $match[0];
            if ($inserted) return '';
            $inserted = true;
            return $cssTag;
        }, $html);
        if ($inserted || !$hasCss) return $html;

        preg_match_all('~' . $inert . '|(?<head></head\s*>)~i', $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $match) {
            if (isset($match['head']) && $match['head'][1] >= 0) {
                $offset = $match['head'][1];
                return substr($html, 0, $offset) . $cssTag . substr($html, $offset);
            }
        }
        return $cssTag . $html;
    }

    private function collectJavaScript(array $data, ?string $componentId = null): void {
        if (empty($data['is_ai_doc']) && trim((string)($data['js'] ?? '')) !== '') {
            if ($componentId !== null) {
                if (isset($this->javascriptComponentIds[$componentId])) return;
                $this->javascriptComponentIds[$componentId] = true;
            }
            $this->javascriptBuffer[] = (string)$data['js'];
        }
    }

    private function embedJavaScript(string $html): string {
        if (!$this->javascriptBuffer) return $html;
        $scripts = '';
        foreach ($this->javascriptBuffer as $js) {
            // HTML parsers recognise a closing script tag even inside a JS string.
            $js = preg_replace('~</script(?=[\s/>])~i', '<\\/script', $js);
            $scripts .= "\n<script>\n" . $js . "\n</script>\n";
        }

        // Skip comments and raw-text elements so a literal </body> in existing
        // code does not become our insertion point.
        preg_match_all('~<!--[\s\S]*?-->|<(script|style|textarea|title)\b[^>]*>[\s\S]*?</\1\s*>|(?<body></body\s*>)|(?<html></html\s*>)~i', $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $bodyOffset = null;
        $htmlOffset = null;
        foreach ($matches as $match) {
            if (isset($match['body']) && $match['body'][1] >= 0) $bodyOffset = $match['body'][1];
            if (isset($match['html']) && $match['html'][1] >= 0) $htmlOffset = $match['html'][1];
        }
        $offset = $bodyOffset ?? $htmlOffset ?? strlen($html);
        return substr($html, 0, $offset) . $scripts . substr($html, $offset);
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

    // Markdown escapes placeholder comments in link attributes. Restore only URL tags
    // there; escaped placeholders in code examples must remain inert until final output.
    private function restoreMarkdownUrlTags($html) {
        return preg_replace_callback('/<a\s+href="([^"]*)"/i', function($link) {
            $href = preg_replace_callback('/\x{200B}?&lt;!--MKNTG([a-zA-Z0-9+\/=]+)--&gt;\x{200B}?/u', function($tag) {
                $original = base64_decode($tag[1], true);
                return $original !== false && preg_match('/^\{\{\s*(?:SITE_URL|BASE_URL|PAGE_URL(?::[A-Za-z0-9_\-\/]+)?)\s*\}\}$/', $original) ? $original : $tag[0];
            }, $link[1]);
            return '<a href="' . $href . '"';
        }, $html);
    }

    private function parseComponents($content, array $visited = []) {
        if (empty($content)) return '';

        // Protect HTML comments so a {{COMPONENT:...}} tag mentioned inside one (e.g. as
        // documentation/example text, which a component's own comment may reasonably want
        // to show) is never treated as a live tag to expand. Comments must stay inert
        // regardless of what they happen to mention — otherwise a component whose comment
        // names itself triggers the infinite-loop guard mid-comment, breaking the comment
        // early and leaking the rest of its text onto the page.
        $commentMap = [];
        $protectedContent = preg_replace_callback('/<!--.*?-->/s', function($m) use (&$commentMap) {
            $ph = "\x02COMMENT" . count($commentMap) . "\x03";
            $commentMap[$ph] = $m[0];
            return $ph;
        }, $content);

        $result = preg_replace_callback('/\{\{COMPONENT:([a-zA-Z0-9_\-]+)\}\}/', function($matches) use ($visited) {
            $compId = $matches[1];
            if ($this->excludeTracking && $compId === ($this->siteSettings['tracking_component'] ?? '')) return '';

            // Security: Prevent Infinite Loops (DoS)
            if (in_array($compId, $visited)) {
                return "<!-- Infinite loop detected: Component '{$compId}' includes itself -->";
            }
            $visited[] = $compId;

            $compData = loadData(COMPONENTS_DIR, $compId);

            if (!$compData) return "<!-- Component '{$compId}' not found -->";
            $this->includedComponentIds[$compId] = true;

            $this->collectJavaScript($compData, $compId);

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
            // This recursive call independently protects/restores comments found within
            // $compHtml, so a nested component's own documentation comments are equally safe.
            return $this->parseComponents($compHtml, $visited);
        }, $protectedContent);

        foreach ($commentMap as $ph => $original) {
            $result = str_replace($ph, $original, $result);
        }
        return $result;
    }

    private function replaceBasicTags($text, $pageData, $pageTitle, $pageDesc, $pageKeywords, $ogpImage) {
        $siteUrl = $this->isRelativeStaticMode()
            ? $this->getRelativeSiteRoot()
            : $this->getSiteUrl();
        $value = fn($v) => $this->escapeValue((string)$v);
        $url = fn($v) => $this->escapeUrl((string)$v);

        $replacements = [
            'SITE_NAME' => $value($this->siteSettings['site_name']),
            'SITE_DESCRIPTION' => $value($this->siteSettings['description']),
            'SITE_KEYWORDS' => $value($this->siteSettings['keywords']),
            'SITE_OGP_IMAGE' => $url($this->resolveMediaUrl(resolveMediaPath($this->siteSettings['ogp_image']))),

            'PAGE_TITLE' => $value($pageData['title'] ?? ''),
            'PAGE_DESCRIPTION' => $value($pageData['description'] ?? ''),
            'PAGE_KEYWORDS' => $value($pageData['keywords'] ?? ''),
            'PAGE_OGP_IMAGE' => $url($this->resolveMediaUrl(resolveMediaPath($pageData['ogp_image'] ?? ''))),

            'TITLE' => $value($pageData['title'] ?? $this->siteSettings['site_name']),
            'DESCRIPTION' => $value($pageDesc),
            'OGP_IMAGE' => $url($this->resolveMediaUrl(resolveMediaPath($ogpImage))),
            'FULL_TITLE' => $value($pageTitle),
            'KEYWORDS' => $value($pageKeywords),

            'SITE_URL' => $url($siteUrl),
            'BASE_URL' => $url($siteUrl), // Backward compatibility
            'UPDATE_DATE' => $value(substr($pageData['updated_at'] ?? date('Y-m-d H:i:s'), 0, 10)),
        ];

        // PAGE_URL: use canonical URL based on page status and structure
        $id = $this->currentPageId;
        $pageStatus = $pageData['status'] ?? 'draft';
        $isDirStyle = ($this->ssgStructure === 'directory');
        $ownExtension = $this->outputMode === 'md' ? 'md' : ($this->outputMode === 'data' ? $this->dataFormat : null);
        if ($ownExtension !== null) {
            // Markdown versions and data pages always live at /id.<extension>.
            $pageFullUrl = $this->extensionUrl($id, $ownExtension);
        } elseif ($this->isRelativeStaticMode()) {
            $pageFullUrl = $this->getPageLink($id, '');
        } elseif ($pageStatus === 'public_static') {
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
        $replacements['PAGE_URL'] = $url($pageFullUrl);

        $text = preg_replace_callback('/\{\{\s*(' . implode('|', array_keys($replacements)) . ')\s*\}\}/', fn($m) => $replacements[$m[1]], $text);

        // CATEGORY: categories except structural "_" ones; CATEGORY:all includes them.
        $text = preg_replace_callback('/\{\{\s*CATEGORY(?::\s*(all)\s*)?\}\}/', function($m) use ($pageData) {
            return $this->formatCategories($pageData['category'] ?? '', !empty($m[1]));
        }, $text);

        // PAGE_URL:ID — URL of another page, with its extension (and ?v= for data pages).
        $text = preg_replace_callback('/\{\{\s*PAGE_URL:\s*([A-Za-z0-9_\-\/]+)\s*\}\}/', function($m) {
            return $this->escapeUrl($this->pageUrlTag($m[1]));
        }, $text);

        // UPDATE_DATE with format: JP = 年月日, SLASH = /区切り
        $rawDate = $pageData['updated_at'] ?? date('Y-m-d H:i:s');
        $text = preg_replace_callback('/\{\{\s*UPDATE_DATE:([A-Z]+)\s*\}\}/', function($m) use ($rawDate) {
            return $this->escapeValue($this->formatDate($rawDate, $m[1]));
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
        // The published root describes where the BUILT site will live, so it only
        // applies while building. Serving dynamically — the local admin preview of
        // a site operated on your own machine — must answer with the host actually
        // being used, or images and canonical URLs point at a site that does not
        // carry them yet.
        $ssgRootUrl = rtrim($this->siteSettings['ssg_root_url'] ?? '', '/');
        if ($this->staticMode && $ssgRootUrl !== '') return $ssgRootUrl;
        return mikanBoxDynamicSiteUrl($this->siteSettings);
    }

    public function getConfiguredSiteUrl() {
        return rtrim(trim($this->siteSettings['ssg_root_url'] ?? ''), '/');
    }

    private function isRelativeStaticMode() {
        return $this->staticMode && $this->ssgLinkMode === 'relative' && !$this->forceAbsolute;
    }

    private function getStaticRootPrefix() {
        return str_repeat('../', max(0, (int)$this->depth));
    }

    private function getRelativeSiteRoot() {
        $prefix = $this->getStaticRootPrefix();
        return $prefix === '' ? '.' : rtrim($prefix, '/');
    }

    private function getSiteBasePath() {
        return mikanBoxSiteBasePath($this->siteSettings);
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

    private function resolveMediaUrl($url) {
        if (empty($url)) return $url;
        if (preg_match('/^(?:https?:\/\/|data:)/i', $url)) return $url;
        if ($this->isRelativeStaticMode()) {
            return $this->getStaticRootPrefix() . ltrim($url, '/');
        }
        return $this->resolveAbsoluteUrl($url);
    }

    private function buildPageUrl($postId) {
        if ($this->isRelativeStaticMode()) {
            return $this->getPageLink($postId, '');
        }
        return $this->buildFullUrl($this->getSiteUrl(), $this->getPageLink($postId, ''));
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
        // Public AI question box: {{AI_QUESTION}} or {{AI_QUESTION:help-section-id}}
        // The external script also registers the four public read-only WebMCP tools.
        $html = preg_replace_callback('/\{\{\s*AI_QUESTION\s*(?::\s*([a-zA-Z0-9_-]+)\s*)?\}\}/u', function($matches) {
            $section = isset($matches[1]) ? trim($matches[1]) : '';
            $coreName = defined('CORE_DIR') ? basename(CORE_DIR) : 'mikanBox';
            $scriptFile = defined('CORE_DIR') ? CORE_DIR . '/ai-question.js' : '';
            $scriptVersion = $scriptFile !== '' && is_file($scriptFile) ? (string)@filemtime($scriptFile) : '';
            $scriptUrl = rtrim($this->getSiteBasePath(), '/') . '/' . rawurlencode($coreName) . '/ai-question.js'
                . ($scriptVersion !== '' ? '?v=' . rawurlencode($scriptVersion) : '');
            return '<div data-mikanbox-ai-question data-section="'
                . htmlspecialchars($section, ENT_QUOTES)
                . '" data-context="'
                . htmlspecialchars($this->currentPageId, ENT_QUOTES)
                . '"></div><script src="'
                . htmlspecialchars($scriptUrl, ENT_QUOTES)
                . '" defer></script>';
        }, $html);

        // 3. Navigation Tags: {{NAV_LINKS:category}}, {{NAV_CARDS:category:template}}
        //    with named options :sort=new|old, :limit=N, :sep=text, and the @same selector.
        $html = $this->replaceNavTags($html);

        // 4. Search Results Tag: {{SEARCH_RESULTS:componentID}} or {{SEARCH_RESULTS}}
        // NOTE: only active when searchPosts()/getSearchSnippet() exist (SQLite backend).
        // On the flat backend the tag is left unexpanded, matching its current behavior.
        if (function_exists('searchPosts')) {
            $html = preg_replace_callback('/\{\{\s*SEARCH_RESULTS\s*(?::\s*([a-zA-Z0-9_\-]+)\s*)?\}\}/u', function($matches) {
                $compId = isset($matches[1]) ? trim($matches[1]) : '';
                return $this->generateSearchResultsHtml($compId);
            }, $html);
        }

        // Media Tags (Direct expansion, though applyPathCompletion also handles standard tags)
        $mediaBase = $this->isRelativeStaticMode()
            ? $this->getStaticRootPrefix() . 'media/'
            : rtrim($this->getSiteUrl(), '/') . '/media/';
        $html = preg_replace('/\{\{VIDEO:([a-zA-Z0-9_\-\.]+)\}\}/', '<video src="' . $mediaBase . '$1" controls style="max-width:100%; height:auto;"></video>', $html);
        $html = preg_replace_callback('/\{\{IMAGE:([a-zA-Z0-9_\-\.]+)\}\}/', function($m) use ($mediaBase) {
            return '<img src="' . $mediaBase . $m[1] . '" style="max-width:100%; height:auto;"' . mediaImgAttrs($m[1]) . '>';
        }, $html);
        $html = preg_replace('/\{\{AUDIO:([a-zA-Z0-9_\-\.]+)\}\}/', '<audio src="' . $mediaBase . '$1" controls style="width:100%; margin:10px 0;"></audio>', $html);

        // EXT_MD:url#rowID:KEY or EXT_MD:url:KEY — extract DATA block (must come before full EXT_MD)
        $html = preg_replace_callback('/\{\{EXT_MD:(https?:\/\/[^\}#]+?)(?:#([a-zA-Z0-9_\-]+))?:([a-zA-Z][a-zA-Z0-9_]*)\}\}/', function($matches) {
            $url   = $matches[1];
            $rowId = $matches[2];
            $key   = $matches[3];
            $lookupKey = $rowId ? "#{$rowId}:{$key}" : $key;
            if (!mikanBoxExternalMarkdownEnabled($this->siteSettings)) return '';
            $externalContent = mikanBoxFetchExternalMarkdown($url);
            if ($externalContent === null) return '<!-- External Markdown unavailable -->';
            $dataBlocks = [];
            $this->extractDataBlocks($externalContent, $dataBlocks);
            return mikanBoxExternalMarkdownText($dataBlocks[$lookupKey] ?? '');
        }, $html);

        // EXT_MD:url — render full external content
        $html = preg_replace_callback('/\{\{EXT_MD:(https?:\/\/[^\}]+)\}\}/', function($matches) {
            $url = $matches[1];
            if (!mikanBoxExternalMarkdownEnabled($this->siteSettings)) return '';
            $externalContent = mikanBoxFetchExternalMarkdown($url);
            if ($externalContent === null) return '<!-- External Markdown unavailable -->';
            $sanitized = $externalContent;
            $dataBlocks = [];
            $sanitized = $this->extractDataBlocks($sanitized, $dataBlocks);
            return $this->markdown->text(mikanBoxExternalMarkdownText($sanitized));
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
                return "\xE2\x80\x8B<!--MKNTG" . base64_encode($m[0]) . "-->\xE2\x80\x8B";
            }, $content);
            $embeddedHtml = $this->restoreMarkdownUrlTags($this->markdown->text($protected));
            $embeddedHtml = preg_replace_callback('/\x{200B}?<!--MKNTG([a-zA-Z0-9+\/=]+)-->\x{200B}?/u', function($m) {
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

        // Component asset paths are site-relative even on nested pages. Portable
        // exports contain these files too, so use the export root in static mode.
        $html = preg_replace_callback('~(<script\b[^>]*\bsrc\s*=\s*)(["\'])([^"\']+)\2~i', function($m) {
            $src = $m[3];
            $basePath = trim($this->getSiteBasePath(), '/');
            $path = ltrim($src, '/');
            if (str_starts_with($src, '/') && $basePath !== '' && str_starts_with($path, $basePath . '/')) {
                $path = substr($path, strlen($basePath) + 1);
            }
            $path = preg_replace('~^\./~', '', $path);
            // Like media filenames, a saved component's JS filename needs no
            // directory. Leave other script assets and absolute URLs untouched.
            if (!str_starts_with($src, '/') && preg_match('~\A([A-Za-z0-9_-]+)\.js(?:[?#].*)?\z~', $path, $asset)
                && mikanBoxComponentJavaScript($asset[1]) !== null) {
                $path = 'component-js/' . $path;
            }
            if (!preg_match('~\Acomponent-js/[A-Za-z0-9_-]+\.js(?:[?#].*)?\z~', $path)) return $m[0];
            $root = $this->isRelativeStaticMode()
                ? $this->getStaticRootPrefix()
                : ($this->staticMode ? rtrim($this->getSiteUrl(), '/') . '/' : $this->getSiteBasePath());
            return $m[1] . $m[2] . $root . $path . $m[2];
        }, $html);
        
        // Uploaded code assets use explicit media/ URLs, avoiding component ID ambiguity.
        $html = preg_replace_callback('~(<(?:script|link)\b[^>]*\b(?:src|href)\s*=\s*)(["\'])(?:\./)?media/([A-Za-z0-9_-][A-Za-z0-9_.-]*\.(?:js|css)(?:[?#][^"\']*)?)\2~i', function($m) {
            $root = $this->isRelativeStaticMode() ? $this->getStaticRootPrefix() : rtrim($this->getSiteUrl(), '/') . '/';
            return $m[1] . $m[2] . $root . 'media/' . $m[3] . $m[2];
        }, $html);

        // 1. Rewrite internal links to be root-relative (e.g. /pages/p3/)
        $html = preg_replace_callback('/href=["\'](\.?\/?)([^"\']+)["\']/i', function($matches) use ($allPosts) {
            $resolved = $this->resolveInternalLink($matches[1] . $matches[2], $allPosts);
            return $resolved === null ? $matches[0] : 'href="' . $resolved . '"';
        }, $html);

        // Fix Home link "./" to site base
        $siteUrl = $this->isRelativeStaticMode()
            ? $this->getPageLink('index', '')
            : $this->getSiteUrl();
        $html = preg_replace('/href=["\']\.\/["\']/', 'href="' . $siteUrl . '"', $html);

        // 2. Rewrite media paths to full URLs (e.g. https://example.com/mikanbox/media/...)
        $mediaBase = $this->isRelativeStaticMode()
            ? $this->getStaticRootPrefix() . 'media/'
            : rtrim($siteUrl, '/') . '/media/';
        $mediaRoots = array_filter(array_unique([rtrim($this->getSiteUrl(), '/'), rtrim((string)($this->siteSettings['site_url'] ?? ''), '/'), $this->getConfiguredSiteUrl()]));
        $portableMedia = function($url) use ($mediaBase, $mediaRoots) {
            if (!$this->isRelativeStaticMode()) return $url;
            foreach ($mediaRoots as $root) {
                $prefix = $root . '/media/';
                if (str_starts_with($url, $prefix)) {
                    $name = substr($url, strlen($prefix));
                    if (preg_match('/\A[A-Za-z0-9_.-]+(?:[?#][^\s]*)?\z/', $name)) return $mediaBase . $name;
                }
            }
            return $url;
        };
        $html = preg_replace_callback('/<(img|video|audio|source)\b([^>]*)\bsrc=["\']([^"\'\s>]+)["\']/i', function($m) use ($mediaBase, $portableMedia) {
            $tag = $m[1];
            $attrs = $m[2];
            $src = $m[3];

            if (preg_match('/^(?:https?:\/\/|data:)/i', $src)) {
                $rewritten = $portableMedia($src);
                return $rewritten === $src ? $m[0] : "<{$tag}{$attrs}src=\"{$rewritten}\"";
            }

            // Convert images/myfile.jpg or myfile.jpg to full URL
            $filename = basename($src);
            return "<{$tag}{$attrs}src=\"{$mediaBase}{$filename}\"";
        }, $html);
        $html = preg_replace_callback('/url\(\s*(["\']?)(https?:\/\/[^)"\'\s]+)\1\s*\)/i', fn($m) => 'url(' . $m[1] . $portableMedia($m[2]) . $m[1] . ')', $html);
        return preg_replace_callback(
            '/url\(\s*(["\']?)(?:images|media)\/([^)"\']+)\1\s*\)/i',
            fn($m) => 'url(' . $m[1] . $mediaBase . $m[2] . $m[1] . ')',
            $html
        );
    }

    // NOTE: only reachable when searchPosts()/getSearchSnippet() exist (SQLite backend).
    // Kept here for byte-identical sync with the flat backend's copy of this file —
    // do not call directly on the flat backend, where those functions are undefined.
    private function generateSearchResultsHtml($compId) {
        $keyword = $_GET['q'] ?? '';
        $keyword = trim($keyword);
        if ($keyword === '') {
            return '<p class="search-no-query">' . t('search_no_query') . '</p>';
        }
        
        // Search posts (visitor search)
        $results = searchPosts($keyword, false);
        
        if (empty($results)) {
            return '<p class="search-no-results">' . t('search_no_results', htmlspecialchars($keyword)) . '</p>';
        }
        
        $effectiveCompId = $compId ?: 'search_result_item';
        $compData = loadData(COMPONENTS_DIR, $effectiveCompId);
        $customTemplate = null;
        $prefixClass = '';
        
        if ($compData) {
            $customTemplate = $compData['html'] ?? '';
            $compCss = $compData['css'] ?? '';
            if (!empty($compCss)) {
                if (!empty($compData['is_global'])) {
                    $this->globalCssBuffer[] = "/* Search Result Item (Global): {$effectiveCompId} */\n" . $compCss;
                } else {
                    $prefixClass = 'cmp-' . $effectiveCompId;
                    $this->globalCssBuffer[] = "/* Search Result Item: {$effectiveCompId} */\n" . scopeCss($compCss, '.' . $prefixClass);
                }
            }
        }
        
        if (!$customTemplate) {
            $customTemplate = '<div class="search-result-item"><h3><a href="{{RESULT_URL}}">{{RESULT_TITLE}}</a></h3><p class="search-snippet">{{RESULT_SNIPPET}}</p><span class="search-date">{{RESULT_DATE}}</span></div>';
        }
        
        $outputHtml = '<div class="search-results-list' . ($prefixClass !== '' ? ' ' . $prefixClass : '') . '">';
        foreach ($results as $item) {
            $title = highlightSearchKeyword(htmlspecialchars($item['title'] !== '' ? $item['title'] : $item['id'], ENT_QUOTES, 'UTF-8'), $keyword);
            $snippet = getSearchSnippet($item['content_md'], $keyword);
            
            // Build absolute URL for scroll-to-text-fragment
            $link = $this->buildPageUrl($item['id']);
            
            // Text Fragment highlight
            $fragment = '#:~:text=' . urlencode($keyword);
            $linkWithFragment = $link . $fragment;
            
            $updateDate = substr($item['updated_at'] !== '' ? $item['updated_at'] : date('Y-m-d H:i:s'), 0, 10);
            
            $itemHtml = str_replace(
                ['{{RESULT_TITLE}}', '{{RESULT_SNIPPET}}', '{{RESULT_URL}}', '{{RESULT_DATE}}'],
                [$title, $snippet, $linkWithFragment, $updateDate],
                $customTemplate
            );
            
            $outputHtml .= $itemHtml;
            if ($compData) $this->collectJavaScript($compData, $effectiveCompId);
        }
        $outputHtml .= '</div>';
        
        return $outputHtml;
    }

    // ==========================================
    // Navigation lists: NAV_LINKS / NAV_CARDS
    // ==========================================

    private function replaceNavTags($text) {
        return preg_replace_callback('/\{\{\s*(NAV_LINKS|NAV_CARDS)\s*((?::[^:}]*)*)\}\}/u', function($m) {
            $options = $this->parseNavArguments($m[2]);
            if ($options === null) return $m[0];
            return $this->renderNav($m[1], $options);
        }, $text);
    }

    /**
     * Positional arguments (category, card component) followed by named options.
     * A value runs to the next ":" or "}}"; in sep, \n and \t mean newline and tab.
     */
    private function parseNavArguments($argumentString) {
        $options = ['category' => '', 'component' => '', 'sort' => '', 'limit' => 0, 'sep' => ''];
        $positional = [];
        $tokens = $argumentString === '' ? [] : explode(':', substr($argumentString, 1));
        foreach ($tokens as $token) {
            if (preg_match('/\A\s*(sort|limit|sep)=(.*)\z/s', $token, $named)) {
                if ($named[1] === 'sort') {
                    $sort = trim($named[2]);
                    $options['sort'] = in_array($sort, ['new', 'old'], true) ? $sort : '';
                } elseif ($named[1] === 'limit') {
                    $options['limit'] = max(0, (int)trim($named[2]));
                } else {
                    $options['sep'] = str_replace(['\n', '\t'], ["\n", "\t"], $named[2]);
                }
                continue;
            }
            if (strpos($token, '=') !== false) continue; // unknown option
            $positional[] = trim($token);
        }
        $options['category'] = $positional[0] ?? '';
        $options['component'] = $positional[1] ?? '';
        if ($options['component'] !== '' && !preg_match('/\A[A-Za-z0-9_\-]+\z/', $options['component'])) return null;
        return $options;
    }

    /** Listable pages in display order, matching a category ('' = none, 'all' = every page). */
    private function navCandidates($category, array $options) {
        $items = [];
        foreach (getSortedPostIds() as $postId) {
            $postInfo = $this->loadPage($postId);
            if (!$postInfo) continue;
            if (in_array($postInfo['status'] ?? 'public', ['draft', 'db'], true)) continue;
            if ($this->isRelativeStaticMode() && ($postInfo['status'] ?? '') !== 'public_static') continue;
            if (($postInfo['sort_order'] ?? 0) < 0) continue;
            // Data-format pages are parts of a site, not content, so lists never show them.
            if (!mikanBoxPageIsListed($postInfo)) continue;

            $cats = mikanBoxSplitCategories($postInfo['category'] ?? '');
            if ($category !== '') {
                if ($category !== 'all' && !in_array($category, $cats, true)) continue;
            } elseif (!empty($cats)) continue;
            $items[] = ['id' => $postId, 'data' => $postInfo];
        }
        if ($options['sort'] !== '') {
            // usort is stable, so pages with the same date keep their display order.
            $direction = $options['sort'] === 'new' ? -1 : 1;
            usort($items, fn($a, $b) => $direction * strcmp((string)($a['data']['updated_at'] ?? ''), (string)($b['data']['updated_at'] ?? '')));
        }
        if ($options['limit'] > 0) $items = array_slice($items, 0, $options['limit']);
        return $items;
    }

    private function renderNav($tagType, array $options) {
        if ($options['category'] !== '@same') {
            return $this->renderNavGroups($tagType, $options, [['label' => null, 'items' => $this->navCandidates($options['category'], $options)]]);
        }
        // @same: pages sharing a non-structural category with the page being shown.
        $groups = [];
        foreach (mikanBoxVisibleCategories($this->currentPageInfo['category'] ?? '') as $category) {
            $items = array_values(array_filter(
                $this->navCandidates($category, array_merge($options, ['limit' => 0])),
                fn($item) => $item['id'] !== $this->currentPageId
            ));
            if ($options['limit'] > 0) $items = array_slice($items, 0, $options['limit']);
            $groups[] = ['label' => $category, 'items' => $items];
        }
        if (!$groups) return '';
        if (count($groups) === 1) $groups[0]['label'] = null;
        return $this->renderNavGroups($tagType, $options, $groups);
    }

    /** $groups: one unlabelled group for a flat list, or labelled groups per category. */
    private function renderNavGroups($tagType, array $options, array $groups) {
        $grouped = $groups[0]['label'] !== null;
        $sep = $options['sep'];

        if ($this->outputMode !== 'html') {
            $isMarkdown = $this->outputMode === 'md';
            $template = null;
            $compData = null;
            $effectiveCompId = $options['component'] ?: '_nav_card';
            if ($tagType === 'NAV_CARDS' && !$isMarkdown) {
                $compData = loadData(COMPONENTS_DIR, $effectiveCompId);
                $template = $compData ? (string)($compData['html'] ?? '') : null;
            }
            $parts = [];
            foreach ($groups as $group) {
                $lines = [];
                foreach ($group['items'] as $item) {
                    if ($template !== null) {
                        $lines[] = $this->renderCardItem($item['id'], $item['data'], $template);
                    } else {
                        $line = '- [' . $this->escapeMarkdownLinkText($item['data']['title'] ?? $item['id']) . '](' . $this->escapeMarkdownUrl($this->buildPageUrl($item['id'])) . ')';
                        $lines[] = ($grouped ? '  ' : '') . ($isMarkdown ? $line : $this->escapeValue($line));
                    }
                }
                $joined = implode($sep !== '' ? $sep : ($template !== null ? '' : "\n"), $lines);
                if ($grouped && $isMarkdown) {
                    $joined = '- ' . $this->escapeMarkdownLinkText($group['label']) . ($joined !== '' ? "\n" . $joined : '');
                }
                $parts[] = $joined;
            }
            $parts = array_values(array_filter($parts, fn($part) => $part !== ''));
            return implode($sep !== '' && !$isMarkdown ? $sep : "\n", $parts);
        }

        if ($tagType === 'NAV_LINKS') {
            $renderLinks = function(array $items) use ($sep) {
                $lis = [];
                foreach ($items as $item) {
                    $title = htmlspecialchars($item['data']['title'] ?? $item['id'], ENT_QUOTES);
                    $activeClass = ($this->currentPageId === $item['id']) ? ' class="active"' : '';
                    $lis[] = "<li{$activeClass}><a href=\"" . $this->buildPageUrl($item['id']) . "\">{$title}</a></li>";
                }
                return implode($sep, $lis);
            };
            if (!$grouped) return '<ul class="nav-links">' . $renderLinks($groups[0]['items']) . '</ul>';
            $html = '<ul class="nav-links">';
            foreach ($groups as $group) {
                $html .= '<li class="nav-group"><span class="nav-group-label">' . htmlspecialchars($group['label'], ENT_QUOTES) . '</span>'
                    . '<ul class="nav-links">' . $renderLinks($group['items']) . '</ul></li>';
            }
            return $html . '</ul>';
        }

        // NAV_CARDS in HTML
        $effectiveCompId = $options['component'] ?: '_nav_card';
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
        $renderCards = function(array $items) use ($customTemplate, $compData, $effectiveCompId, $sep) {
            $cards = [];
            foreach ($items as $item) {
                $cards[] = $this->renderCardItem($item['id'], $item['data'], $customTemplate);
                if ($customTemplate && $compData) $this->collectJavaScript($compData, $effectiveCompId);
            }
            return implode($sep, $cards);
        };
        if (!$grouped) {
            $output = '<div class="nav-cards">' . $renderCards($groups[0]['items']) . '</div>';
        } else {
            $output = '';
            foreach ($groups as $group) {
                $output .= '<div class="nav-group"><div class="nav-group-label">' . htmlspecialchars($group['label'], ENT_QUOTES) . '</div>'
                    . '<div class="nav-cards">' . $renderCards($group['items']) . '</div></div>';
            }
        }
        return $prefixClass ? '<div class="' . $prefixClass . '">' . $output . '</div>' : $output;
    }

    /** One card: the item component filled with this page's values, escaped for the output. */
    private function renderCardItem($postId, array $postInfo, $customTemplate) {
        $isHtml = $this->outputMode === 'html';
        $esc = $isHtml ? fn($v) => htmlspecialchars((string)$v, ENT_QUOTES) : fn($v) => $this->escapeValue((string)$v);
        $title = $esc($postInfo['title'] ?? $postId);
        $desc = $esc($postInfo['description'] ?? '');
        $rawImg = resolveMediaPath($postInfo['ogp_image'] ?? '');
        if (!empty($rawImg) && !preg_match('/^(?:https?:\/\/|data:)/', $rawImg)) {
            $rawImg = $this->resolveMediaUrl($rawImg);
        }
        if (empty($rawImg) && $isHtml) {
            $rawImg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='210'%3E%3Crect width='400' height='210' fill='%23e0e0e0'/%3E%3C/svg%3E";
        }
        $img = $esc($rawImg);
        $link = $this->buildPageUrl($postId);
        if (!$isHtml) $link = $this->escapeValue($link);
        $updateDate = substr($postInfo['updated_at'] ?? date('Y-m-d H:i:s'), 0, 10);
        $isActive = ($this->currentPageId === $postId) ? 'active' : '';

        if (!$customTemplate) {
            $imgHtml = $img ? '<div class="nav-card-img"><img src="' . $img . '" alt="" loading="lazy" decoding="async"></div>' : '';
            return sprintf('<a href="%s" class="nav-card">%s<div class="nav-card-content"><h3 class="nav-card-title">%s</h3>%s</div></a>',
                $link, $imgHtml, $title, ($desc ? '<p class="nav-card-desc">' . $desc . '</p>' : ''));
        }
        $itemHtml = str_replace(
            ['{{TITLE}}', '{{DESCRIPTION}}', '{{LINK}}', '{{OGP_IMAGE}}', '{{PAGE_URL}}', '{{UPDATE_DATE}}', '{{IS_ACTIVE}}'],
            [$title, $desc, $link, $img, $link, $updateDate, $isActive],
            $customTemplate
        );
        $itemHtml = preg_replace_callback('/\{\{\s*CATEGORY(?::\s*(all)\s*)?\}\}/', function($m) use ($postInfo) {
            return $this->formatCategories($postInfo['category'] ?? '', !empty($m[1]));
        }, $itemHtml);
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
            $valueOut = $isHtml ? fn($v) => $v : fn($v) => $this->escapeValue($v);
            // POST_MD::#rowID:KEY — self with row
            $itemHtml = preg_replace_callback('/\{\{POST_MD::#([a-zA-Z0-9_\-]+):([a-zA-Z][a-zA-Z0-9_]*)\}\}/', function($m) use ($cardDataBlocks, $valueOut) {
                return $valueOut($cardDataBlocks["#{$m[1]}:{$m[2]}"] ?? '');
            }, $itemHtml);
            // POST_MD::KEY — self field
            $itemHtml = preg_replace_callback('/\{\{POST_MD::([a-zA-Z][a-zA-Z0-9_]*)\}\}/', function($m) use ($cardDataBlocks, $valueOut) {
                return $valueOut($cardDataBlocks[$m[1]] ?? '');
            }, $itemHtml);
        }
        return $itemHtml;
    }

    // ==========================================
    // Escaping by output
    // ==========================================

    /** A text value: HTML-escaped in HTML, as it is in Markdown, escaped for the data format otherwise. */
    private function escapeValue($value) {
        if ($this->outputMode === 'html') return htmlspecialchars($value);
        if ($this->outputMode === 'md') return $value;
        return $this->escapeForData($value);
    }

    /** A URL value: as it is in HTML and Markdown (as before), escaped for the data format otherwise. */
    private function escapeUrl($value) {
        return $this->outputMode === 'data' ? $this->escapeForData($value) : $value;
    }

    private function escapeForData($value) {
        switch ($this->dataFormat) {
            case 'json':
                return substr(json_encode((string)$value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), 1, -1);
            case 'xml':
                return htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
            case 'csv':
                return preg_match('/[",\r\n]/', (string)$value) ? '"' . str_replace('"', '""', (string)$value) . '"' : (string)$value;
            default:
                return (string)$value;
        }
    }

    private function escapeMarkdownLinkText($text) {
        return str_replace(['\\', '[', ']'], ['\\\\', '\\[', '\\]'], (string)$text);
    }

    private function escapeMarkdownUrl($url) {
        return str_replace([' ', '(', ')'], ['%20', '%28', '%29'], (string)$url);
    }

    /** {{CATEGORY}}: spans in HTML, comma-separated elsewhere. */
    private function formatCategories($category, $includeStructural) {
        $cats = $includeStructural ? mikanBoxSplitCategories($category) : mikanBoxVisibleCategories($category);
        if ($this->outputMode === 'html') {
            return implode(' ', array_map(fn($c) => '<span class="category">' . htmlspecialchars($c, ENT_QUOTES) . '</span>', $cats));
        }
        return $this->escapeValue(implode(', ', $cats));
    }

    // ==========================================
    // Page URLs by output format
    // ==========================================

    private function loadPage($pageId) {
        if (!array_key_exists($pageId, $this->pageCache)) $this->pageCache[$pageId] = loadData(POSTS_DIR, $pageId);
        return $this->pageCache[$pageId];
    }

    private function encodePageIdPath($pageId) {
        return implode('/', array_map('urlencode', explode('/', $pageId)));
    }

    /** /id.<extension> in the current link mode (relative in a relative export, absolute otherwise). */
    private function extensionUrl($pageId, $extension) {
        $path = $this->encodePageIdPath($pageId) . '.' . $extension;
        if ($this->isRelativeStaticMode()) return $this->getStaticRootPrefix() . $path;
        return rtrim($this->getSiteUrl(), '/') . '/' . $path;
    }

    /** /id.<extension> as an absolute URL, also during a relative export when the published URL is known. */
    private function absoluteExtensionUrl($pageId, $extension) {
        $base = $this->isRelativeStaticMode() ? $this->getConfiguredSiteUrl() : $this->getSiteUrl();
        return rtrim($base, '/') . '/' . $this->encodePageIdPath($pageId) . '.' . $extension;
    }

    /** {{PAGE_URL:ID}}: the page's URL with its extension; data pages carry ?v=<hash of content>. */
    private function pageUrlTag($targetId) {
        $target = $this->loadPage($targetId);
        if (!$target) return '';
        $url = $this->buildPageUrl($targetId);
        if (mikanBoxIsDataFormat(mikanBoxPageOutputFormat($target))) {
            $url .= '?v=' . $this->dataVersion($targetId, $target);
        }
        return $url;
    }

    /** Static builds hash the built output; dynamic pages are never cached, so the body is enough. */
    private function dataVersion($pageId, array $pageData) {
        if (!$this->staticMode) return substr(hash('sha256', (string)($pageData['content_md'] ?? '')), 0, 8);
        if (isset($this->dataHashes[$pageId])) return $this->dataHashes[$pageId];
        if (in_array($pageId, self::$hashStack, true)) return substr(hash('sha256', (string)($pageData['content_md'] ?? '')), 0, 8);
        self::$hashStack[] = $pageId;
        try {
            $builder = new self($this->siteSettings);
            $builder->setStaticMode(true, substr_count($pageId, '/'), $this->ssgStructure, $this->ssgLinkMode);
            $builder->setDataHashes($this->dataHashes);
            $output = $builder->renderData($pageId);
        } finally {
            array_pop(self::$hashStack);
        }
        $this->dataHashes[$pageId] = substr(hash('sha256', (string)$output), 0, 8);
        return $this->dataHashes[$pageId];
    }

    public function setDataHashes(array $hashes) {
        $this->dataHashes = $hashes;
    }

    public static function dataHash($output) {
        return substr(hash('sha256', (string)$output), 0, 8);
    }

    // ==========================================
    // Access to Markdown versions and data pages
    // ==========================================

    /** Same publication rules as render(): drafts and DB pages are private; SQLite pending pages need the preview token. */
    private function pageIsAccessible($pageId, array $pageData) {
        $status = $pageData['status'] ?? '';
        $isAdmin = !$this->staticMode && (($_SESSION['admin_logged_in'] ?? false) === true);
        if (in_array($status, ['draft', 'db'], true)) return $isAdmin;
        if ($status === 'pending') {
            if (!function_exists('getPreviewToken')) return $isAdmin;
            if ($isAdmin) return true;
            if (verifyPreviewToken($pageId, $_GET['preview'] ?? '')) {
                if (!$this->staticMode && !headers_sent()) {
                    header('Referrer-Policy: no-referrer');
                    header('X-Robots-Tag: noindex, nofollow');
                    header('Cache-Control: no-store');
                }
                return true;
            }
            return false;
        }
        return true;
    }

    private function beginOutput($pageId, $mode, $format, array $pageData) {
        $this->outputMode = $mode;
        $this->dataFormat = $format;
        $this->forceAbsolute = $mode === 'md';
        $this->linkToMarkdown = $mode === 'md';
        $this->pageCache = [];
        $this->currentPageId = $pageId;
        $this->currentPageData = [];
        $this->currentPageInfo = $pageData;
        $this->globalCssBuffer = [];
        $this->javascriptBuffer = [];
        $this->javascriptComponentIds = [];
        $this->includedComponentIds = [];
        if (!$this->depthSetManually) $this->depth = substr_count($pageId, '/');
    }

    private function endOutput() {
        $this->outputMode = 'html';
        $this->dataFormat = '';
        $this->forceAbsolute = false;
        $this->linkToMarkdown = false;
    }

    // ==========================================
    // Data-format pages (json, xml, csv, txt, js, css)
    // ==========================================

    /**
     * The body of a data-format page, as it is, with tags expanded when the page asks
     * for it. Returns null when the page does not exist, is not a data page, or is private.
     */
    public function renderData($pageId, $previewData = null) {
        $pageData = $previewData ?? loadData(POSTS_DIR, $pageId);
        if (!$pageData) return null;
        $format = mikanBoxPageOutputFormat($pageData);
        if (!mikanBoxIsDataFormat($format) || !$this->pageIsAccessible($pageId, $pageData)) return null;
        $body = (string)($pageData['content_md'] ?? '');
        if (!mikanBoxPageExpandsTags($pageData)) return $body;

        $this->beginOutput($pageId, 'data', $format, $pageData);
        try {
            $body = $this->extractDataBlocks($body, $this->currentPageData);
            $body = $this->includeComponentsAsText($body);
            $body = $this->removeFunctionalTags($body);
            $body = $this->replaceBasicTags($body, $pageData, ...$this->pageMeta($pageData));
            $body = $this->replaceNavTags($body);
            $body = $this->replaceTextContentTags($body);
            return $body;
        } finally {
            $this->endOutput();
        }
    }

    // ==========================================
    // Markdown versions (html_md and md pages)
    // ==========================================

    /**
     * The Markdown version: front matter, the saved Markdown with tags handled for
     * Markdown readers, then the chosen footer components. Every URL is absolute.
     * Returns null when the page has no Markdown version or is private.
     */
    public function renderMarkdown($pageId, $previewData = null) {
        $pageData = $previewData ?? loadData(POSTS_DIR, $pageId);
        if (!$pageData) return null;
        if (!mikanBoxFormatHasMarkdown(mikanBoxPageOutputFormat($pageData)) || !$this->pageIsAccessible($pageId, $pageData)) return null;

        $this->beginOutput($pageId, 'md', '', $pageData);
        try {
            $body = $this->markdownText((string)($pageData['content_md'] ?? ''), $pageData);
            $footers = [];
            foreach (mikanBoxNormalizeFooterComponents($pageData['md_footer_comps'] ?? []) as $componentId) {
                $component = loadData(COMPONENTS_DIR, $componentId);
                $footer = trim($this->markdownText((string)($component['html'] ?? ''), $pageData));
                if ($footer !== '') $footers[] = $footer;
            }

            $quote = fn($v) => '"' . str_replace(['\\', '"', "\r\n", "\r", "\n"], ['\\\\', '\\"', '\n', '\n', '\n'], (string)$v) . '"';
            $front = ['---', 'title: ' . $quote($pageData['title'] ?? $pageId), 'date: ' . substr((string)($pageData['updated_at'] ?? date('Y-m-d H:i:s')), 0, 10)];
            if (trim((string)($pageData['description'] ?? '')) !== '') $front[] = 'description: ' . $quote(trim($pageData['description']));
            if (mikanBoxPageOutputFormat($pageData) === 'html_md') {
                $this->linkToMarkdown = false;
                $front[] = 'url: ' . $quote($this->buildPageUrl($pageId));
                $this->linkToMarkdown = true;
            }
            $categories = mikanBoxVisibleCategories($pageData['category'] ?? '');
            if ($categories) $front[] = 'categories: ' . $quote(implode(', ', $categories));
            $front[] = '---';

            $output = implode("\n", $front) . "\n\n" . trim($body);
            if ($footers) $output .= "\n\n" . implode("\n\n", $footers);
            return $output . "\n";
        } finally {
            $this->endOutput();
        }
    }

    /** Tag handling and link completion for Markdown text (the page body or a footer component). */
    private function markdownText($text, array $pageData, $depth = 0) {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Code and HTML comments stay exactly as written. Code first, so "<!--" inside
        // a code span does not start a comment.
        $codeMap = [];
        $text = $this->protectCodeSpans($text, $codeMap);
        $text = preg_replace_callback('/<!--.*?-->/s', function($m) use (&$codeMap) {
            $key = "\x02MDKEEP" . count($codeMap) . "\x03";
            $codeMap[$key] = $m[0];
            return $key;
        }, $text);
        $text = $this->extractDataBlocks($text, $this->currentPageData);
        // Components are design: only the chosen footer components reach the Markdown version.
        $text = preg_replace('/\{\{\s*COMPONENT:[^}]*\}\}/', '', $text);
        $text = $this->removeFunctionalTags($text);
        $text = $this->replaceBasicTags($text, $pageData, ...$this->pageMeta($pageData));
        $text = $this->replaceNavTags($text);
        $text = $this->replaceTextContentTags($text, $depth);
        $text = $this->completeMarkdownLinks($text);
        // Removed tags can leave runs of empty lines; code blocks are still protected here.
        $text = preg_replace("/\n[ \t]*\n(?:[ \t]*\n)+/", "\n\n", $text);
        // Restore in reverse order: a comment may contain a code placeholder.
        foreach (array_reverse($codeMap, true) as $key => $original) $text = str_replace($key, $original, $text);
        return $text;
    }

    private function pageMeta(array $pageData) {
        $site = $this->siteSettings;
        $title = isset($pageData['title']) ? $pageData['title'] . ' - ' . $site['site_name'] : $site['site_name'];
        return [
            $title,
            !empty($pageData['description']) ? $pageData['description'] : $site['description'],
            !empty($pageData['keywords']) ? $pageData['keywords'] : $site['keywords'],
            !empty($pageData['ogp_image']) ? $pageData['ogp_image'] : $site['ogp_image'],
        ];
    }

    /** Functions and decoration that mean nothing outside an HTML page. */
    private function removeFunctionalTags($text) {
        return preg_replace('/\{\{\s*(?:AI_QUESTION(?::[^}]*)?|SEARCH_RESULTS(?::[^}]*)?|CONTENT|HEAD_CSS|IS_ACTIVE)\s*\}\}/u', '', $text);
    }

    /** In data pages, a component is inserted as plain text (no scope wrapper, CSS or JS). */
    private function includeComponentsAsText($text, array $visited = []) {
        return preg_replace_callback('/\{\{COMPONENT:([a-zA-Z0-9_\-]+)\}\}/', function($m) use ($visited) {
            if (in_array($m[1], $visited, true)) return '';
            $component = loadData(COMPONENTS_DIR, $m[1]);
            if (!$component || !empty($component['is_ai_doc'])) return '';
            return $this->includeComponentsAsText((string)($component['html'] ?? ''), array_merge($visited, [$m[1]]));
        }, $text);
    }

    /** POST_MD, EXT_MD and media tags for Markdown versions and data pages. */
    private function replaceTextContentTags($text, $depth = 0) {
        $isMarkdown = $this->outputMode === 'md';
        $out = fn($v) => $isMarkdown ? (string)$v : $this->escapeValue((string)$v);
        $mediaUrl = fn($file) => $this->resolveMediaUrl('media/' . $file);

        $text = preg_replace_callback('/\{\{IMAGE:([a-zA-Z0-9_\-\.]+)\}\}/', fn($m) => $isMarkdown ? '![](' . $this->escapeMarkdownUrl($mediaUrl($m[1])) . ')' : $out($mediaUrl($m[1])), $text);
        $text = preg_replace_callback('/\{\{(?:AUDIO|VIDEO):([a-zA-Z0-9_\-\.]+)\}\}/', fn($m) => $isMarkdown ? '[' . $this->escapeMarkdownLinkText($m[1]) . '](' . $this->escapeMarkdownUrl($mediaUrl($m[1])) . ')' : $out($mediaUrl($m[1])), $text);

        // EXT_MD:url#rowID:KEY or EXT_MD:url:KEY
        $text = preg_replace_callback('/\{\{EXT_MD:(https?:\/\/[^\}#]+?)(?:#([a-zA-Z0-9_\-]+))?:([a-zA-Z][a-zA-Z0-9_]*)\}\}/', function($m) use ($out) {
            if (!mikanBoxExternalMarkdownEnabled($this->siteSettings)) return '';
            $external = mikanBoxFetchExternalMarkdown($m[1]);
            if ($external === null) return '';
            $blocks = [];
            $this->extractDataBlocks($external, $blocks);
            return $out(mikanBoxExternalMarkdownText($blocks[$m[2] ? "#{$m[2]}:{$m[3]}" : $m[3]] ?? ''));
        }, $text);
        // EXT_MD:url — the external Markdown itself
        $text = preg_replace_callback('/\{\{EXT_MD:(https?:\/\/[^\}]+)\}\}/', function($m) use ($out) {
            if (!mikanBoxExternalMarkdownEnabled($this->siteSettings)) return '';
            $external = mikanBoxFetchExternalMarkdown($m[1]);
            if ($external === null) return '';
            $blocks = [];
            return $out(mikanBoxExternalMarkdownText($this->extractDataBlocks($external, $blocks)));
        }, $text);

        $pageBlocks = function($pageId) {
            $postData = $this->loadPage($pageId);
            if (!$postData || empty($postData['content_md'])) return [];
            $blocks = [];
            $this->extractDataBlocks($postData['content_md'], $blocks);
            return $blocks;
        };
        $text = preg_replace_callback('/\{\{POST_MD:([a-zA-Z0-9_\-\/]+)#([a-zA-Z0-9_\-]+):([a-zA-Z][a-zA-Z0-9_]*)\}\}/', fn($m) => $out($pageBlocks($m[1])["#{$m[2]}:{$m[3]}"] ?? ''), $text);
        $text = preg_replace_callback('/\{\{POST_MD::#([a-zA-Z0-9_\-]+):([a-zA-Z][a-zA-Z0-9_]*)\}\}/', fn($m) => $out($this->currentPageData["#{$m[1]}:{$m[2]}"] ?? ''), $text);
        $text = preg_replace_callback('/\{\{POST_MD:([a-zA-Z0-9_\-\/]*):([a-zA-Z][a-zA-Z0-9_]*)\}\}/', function($m) use ($out, $pageBlocks) {
            return $out($m[1] === '' ? ($this->currentPageData[$m[2]] ?? '') : ($pageBlocks($m[1])[$m[2]] ?? ''));
        }, $text);
        // POST_MD:ID — the other page's body
        $text = preg_replace_callback('/\{\{POST_MD:([a-zA-Z0-9_\-\/]+)\}\}/', function($m) use ($isMarkdown, $out, $depth) {
            $postData = $this->loadPage($m[1]);
            if (!$postData || empty($postData['content_md']) || $depth >= 5) return '';
            if (!$isMarkdown) {
                $blocks = [];
                return $out($this->extractDataBlocks((string)$postData['content_md'], $blocks));
            }
            $savedId = $this->currentPageId;
            $savedData = $this->currentPageData;
            $this->currentPageId = $m[1];
            $this->currentPageData = [];
            try {
                return trim($this->markdownText((string)$postData['content_md'], $postData, $depth + 1));
            } finally {
                $this->currentPageId = $savedId;
                $this->currentPageData = $savedData;
            }
        }, $text);
        return $text;
    }

    /** Markdown links and images: internal links resolve like HTML links; everything becomes absolute. */
    private function completeMarkdownLinks($text) {
        $allPosts = getSortedPostIds();
        $siteUrl = rtrim($this->getSiteUrl(), '/');
        $origin = preg_match('~\A(https?://[^/]+)~i', $siteUrl, $o) ? $o[1] : $siteUrl;
        return preg_replace_callback('/(!?)\[((?:\\\\.|[^\[\]\n\\\\])*)\]\(\s*([^)\s]+)((?:\s+"[^"\n]*")?)\s*\)/u', function($m) use ($allPosts, $siteUrl, $origin) {
            $url = $m[3];
            if (preg_match('/^(?:[a-z][a-z0-9+.\-]*:|\/\/|#)/i', $url)) return $m[0];
            if ($m[1] === '!') {
                $resolved = rtrim($this->getSiteUrl(), '/') . '/media/' . basename(parse_url($url, PHP_URL_PATH) ?: $url);
            } else {
                $resolved = $this->resolveInternalLink($url, $allPosts);
                if ($resolved === null) {
                    if ($url === './' || $url === '.') $resolved = $siteUrl . '/';
                    elseif (str_starts_with($url, '/')) $resolved = $origin . $url;
                    else $resolved = $siteUrl . '/' . preg_replace('#^(?:\./)+#', '', $url);
                }
            }
            return $m[1] . '[' . $m[2] . '](' . $this->escapeMarkdownUrl($resolved) . $m[4] . ')';
        }, $text);
    }

    /**
     * Shared by HTML (href="…") and Markdown ([…](…)) link completion: which page a written
     * link target means, and the URL it should point at. Returns null to leave the link as written.
     */
    private function resolveInternalLink($originalUrl, array $allPosts) {
        // Skip external links, hashes, mailto, or already absolute paths
        if (preg_match('/^(?:https?:\/\/|\/\/|#|mailto:|tel:)/i', $originalUrl)) return null;
        $markdownRoot = $this->outputMode === 'md' && str_starts_with($originalUrl, '/');
        if (!$this->isRelativeStaticMode() && !$markdownRoot && str_starts_with($originalUrl, '/')) return null;
        // Links already emitted by portable SSG must not be interpreted as page IDs again.
        if ($this->isRelativeStaticMode()
            && preg_match('/(?:^|\/)[^?#]+\.html(?:[?#].*)?$/i', $originalUrl)) {
            return null;
        }

        $path = parse_url($originalUrl, PHP_URL_PATH);
        $path = $path === null || $path === false ? $originalUrl : $path;
        $query = parse_url($originalUrl, PHP_URL_QUERY);
        $fragment = parse_url($originalUrl, PHP_URL_FRAGMENT);
        $suffix = ($query !== null && $query !== false ? '?' . $query : '')
            . ($fragment !== null && $fragment !== false ? '#' . $fragment : '');

        if (($this->isRelativeStaticMode() || $markdownRoot) && str_starts_with($path, '/')) {
            $basePath = trim($this->getSiteBasePath(), '/');
            $path = ltrim($path, '/');
            if ($basePath !== '' && ($path === $basePath || str_starts_with($path, $basePath . '/'))) {
                $path = ltrim(substr($path, strlen($basePath)), '/');
            }
        }
        if ($this->isRelativeStaticMode() || $this->outputMode === 'md') {
            $path = preg_replace('#^(?:(?:\./)|(?:\.\./))+#', '', $path);
        }

        // Normalize generated URL forms for comparison with page IDs.
        $checkId = trim($path, '/');
        if (str_ends_with(strtolower($checkId), '/index.html')) {
            $checkId = substr($checkId, 0, -11);
        } elseif (str_ends_with(strtolower($checkId), '.html')) {
            $checkId = substr($checkId, 0, -5);
        } elseif (str_ends_with(strtolower($checkId), '.md')) {
            $checkId = substr($checkId, 0, -3);
        }
        if ($checkId === '' && $this->outputMode === 'md') $checkId = 'index';

        // Find matching post (case-insensitive)
        foreach ($allPosts as $pid) {
            if (strcasecmp($checkId, $pid) === 0) {
                if ($this->isRelativeStaticMode()) {
                    $post = $this->loadPage($pid);
                    if (($post['status'] ?? '') !== 'public_static') return null;
                }
                return $this->buildPageUrl($pid) . $suffix;
            }
        }

        // Special fallback: if it looks like an ID (no extension, has slash etc.)
        // and we didn't find a direct match, but it's clearly an internal link attempt
        if (!$this->isRelativeStaticMode() && preg_match('/^[a-z0-9_\-\/]+$/i', $checkId)) {
            return $this->buildPageUrl($checkId);
        }
        return null;
    }

    /** Output for the editor preview: what the page actually publishes first. */
    public function renderPreview($pageId, array $pageData) {
        $format = mikanBoxPageOutputFormat($pageData);
        if (mikanBoxIsApiFormat($format)) {
            // The JSON that /api/ID returns.
            return ['type' => 'text/plain; charset=utf-8', 'body' => (string)json_encode($this->getPageDataBlocks($pageId, $pageData), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)];
        }
        if (mikanBoxIsDataFormat($format)) {
            return ['type' => 'text/plain; charset=utf-8', 'body' => (string)$this->renderData($pageId, array_merge($pageData, ['status' => 'public_dynamic']))];
        }
        if ($format === 'md') {
            return ['type' => 'text/plain; charset=utf-8', 'body' => (string)$this->renderMarkdown($pageId, array_merge($pageData, ['status' => 'public_dynamic']))];
        }
        return ['type' => 'text/html; charset=utf-8', 'body' => $this->render($pageId, $pageData)];
    }

    public function getPageLink($postId, $relPrefix) {
        $isDirStyle = ($this->ssgStructure === 'directory');
        // Root-relative path (site base, e.g. /mikanbox/ for subdirectory installs)
        $root = $this->getSiteBasePath();

        // Everything with an extension lives at /id.<extension>, whatever the URL style:
        // data pages, Markdown-only pages, and (inside Markdown versions) pages that also
        // publish a Markdown version.
        $target = $this->loadPage($postId);
        $format = mikanBoxPageOutputFormat($target);
        $extension = mikanBoxFormatExtension($format);
        if ($extension === null && $format === 'html_md' && $this->linkToMarkdown) $extension = 'md';
        if (mikanBoxIsApiFormat($format)) {
            // mikanBox DB pages answer only at /api/ID.
            $path = 'api/' . $this->encodePageIdPath($postId);
            if ($this->isRelativeStaticMode()) return $this->getStaticRootPrefix() . $path;
            return $root . $path;
        }
        if ($extension !== null) {
            $path = $this->encodePageIdPath($postId) . '.' . $extension;
            if ($this->isRelativeStaticMode()) return $this->getStaticRootPrefix() . $path;
            return $root . $path;
        }

        if ($this->staticMode) {
            if ($this->isRelativeStaticMode()) {
                $parts = explode('/', $postId);
                $encodedParts = array_map('urlencode', $parts);
                $path = implode('/', $encodedParts);
                $rootPrefix = $this->getStaticRootPrefix();

                if ($postId === 'index') {
                    return $rootPrefix . 'index.html';
                }
                return $isDirStyle
                    ? $rootPrefix . $path . '/index.html'
                    : $rootPrefix . $path . '.html';
            }

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
