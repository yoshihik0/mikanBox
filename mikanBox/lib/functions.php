<?php
// ==========================================
// mikanBox Core Functions (lib/functions.php)
// ==========================================

require_once __DIR__ . '/../config.php';

/**
 * Get current system language based on settings or browser preference.
 * Defaults to 'ja' if not set and browser preference is not found.
 */
function getSystemLanguage() {
    global $mikanbox_settings;
    
    // 1. Check if language is explicitly set in settings
    $lang = $mikanbox_settings['system_lang'] ?? '';

    if ($lang !== '' && $lang !== 'auto') {
        return $lang;
    }
    
    // 2. Detect from browser (HTTP_ACCEPT_LANGUAGE)
    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
        if ($browserLang === 'ja') return 'ja';
        if ($browserLang === 'en') return 'en';
    }
    
    return 'ja'; // Default fallback
}

/**
 * Translation helper function.
 * @param string $key The key for the translation string.
 * @param mixed ...$args Optional arguments for vsprintf.
 * @return string The translated string.
 */
function t($key, ...$args) {
    static $translations = null;
    
    if ($translations === null) {
        $lang = getSystemLanguage();
        $langFile = __DIR__ . "/../lang/{$lang}.json";
        
        if (!file_exists($langFile)) {
            $langFile = __DIR__ . "/../lang/ja.json"; // Fallback to ja
        }
        
        $json = file_get_contents($langFile);
        $translations = json_decode($json, true) ?: [];
    }
    
    $text = $translations[$key] ?? $key;
    
    if (!empty($args)) {
        return vsprintf($text, $args);
    }
    
    return $text;
}

/**
 * Save data as JSON format.
 * @param string $dir Target directory (POSTS_DIR, COMPONENTS_DIR, etc.)
 * @param string $id File ID (e.g., 'index', 'header')
 * @param array $data Data to save
 * @return bool Success or failure
 */
function saveData($dir, $id, $data) {
    // Allow slashes for hierarchy but prevent directory traversal (no ..)
    $id = str_replace('..', '', $id);
    $id = ltrim($id, '/\\');
    $id = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $id);
    if (empty($id)) return false;

    // Create directory path if it doesn't exist
    $filePath = $dir . '/' . $id . '.json';
    $targetDir = dirname($filePath);
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
    return file_put_contents($filePath, $json) !== false;
}

/**
 * Load data from JSON format.
 * @param string $dir Target directory
 * @param string $id File ID
 * @return array|null The data as an associative array, or null on failure
 */
function loadData($dir, $id) {
    // Allow slashes for hierarchy but prevent directory traversal
    $id = str_replace('..', '', $id);
    $id = ltrim($id, '/\\');
    $id = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $id);
    if (empty($id)) return null;

    $filePath = $dir . '/' . $id . '.json';
    if (!file_exists($filePath)) {
        // ディレクトリ型ページ: coffee → coffee/index.json
        $indexPath = $dir . '/' . $id . '/index.json';
        if (file_exists($indexPath)) {
            $filePath = $indexPath;
        } else {
            return null;
        }
    }

    $json = file_get_contents($filePath);
    return json_decode($json, true);
}

/**
 * ディレクトリ内の全JSONファイルのリストを取得
 * @param string $dir 対象ディレクトリ
 * @return array ファイルのID(拡張子なし)の配列
 */
function getFileList($dir) {
    $files = [];
    if (!is_dir($dir)) return $files;
    
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if ($file->getExtension() === 'json') {
            // Calculate relative path from $dir and strip extension
            $relPath = substr($file->getPathname(), strlen($dir) + 1);
            $files[] = substr($relPath, 0, -5); // remove .json
        }
    }
    return $files;
}

/**
 * ソート済みの全ページIDリストを取得
 * sort_order (昇順) -> タイトル (昇順) の順でソートする
 */
function getSortedPostIds() {
    $ids = getFileList(POSTS_DIR);
    $dataList = [];
    foreach ($ids as $id) {
        $data = loadData(POSTS_DIR, $id);
        if (!$data) continue;
        $dataList[] = [
            'id' => $id,
            'sort_order' => (isset($data['sort_order']) && is_numeric($data['sort_order'])) ? (int)$data['sort_order'] : 0,
            'updated_at_ts' => isset($data['updated_at']) ? strtotime($data['updated_at']) : 0,
            'title' => $data['title'] ?? $id
        ];
    }

    usort($dataList, function($a, $b) {
        if ($a['sort_order'] !== $b['sort_order']) {
            return $a['sort_order'] <=> $b['sort_order'];
        }
        // 順序が同じなら更新日時の新しい順(降順)
        return $b['updated_at_ts'] <=> $a['updated_at_ts'];
    });

    return array_column($dataList, 'id');
}

/**
 * データの削除
 * @param string $dir 保存先ディレクトリ
 * @param string $id ファイルのID
 * @return bool
 */
function deleteData($dir, $id) {
    $id = str_replace('..', '', $id);
    $id = ltrim($id, '/\\');
    $id = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $id);
    if (empty($id)) return false;

    $filePath = $dir . '/' . $id . '.json';
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return false;
}

/**
 * 簡易CSSスコーピング処理器
 * CSSの各セレクタの先頭に、特定のプレフィックス（例: クラス名）を付与する。
 * （※シンプルな正規表現ベースのため、一部の複雑なCSSには対応しきれない場合があります）
 * 
 * @param string $css 元のCSS文字列
 * @param string $prefix セレクタの先頭につけるプレフィックス (例: '.cmp-header ')
 * @return string スコープ化されたCSS文字列
 */
function scopeCss($css, $prefix) {
    if (empty(trim($css))) return '';

    // テンプレートタグ {{...}} を一時退避（中括弧がCSS解析を壊すため）
    $tagMap = [];
    $css = preg_replace_callback('/\{\{[A-Z_]+\}\}/', function($m) use (&$tagMap) {
        $placeholder = '___TAG' . count($tagMap) . '___';
        $tagMap[$placeholder] = $m[0];
        return $placeholder;
    }, $css);

    // コメントを削除
    $css = preg_replace('!/\*.*?\*/!s', '', $css);
    
    // 改行を調整
    $css = str_replace(["\r\n", "\r"], "\n", $css);

    $scopedCss = '';
    $buffer = '';
    $depth = 0;

    $length = strlen($css);
    for ($i = 0; $i < $length; $i++) {
        $char = $css[$i];
        
        if ($char === '{') {
            if ($depth === 0) {
                // 最外位のセレクタ（または @media 等）
                $selectors = explode(',', $buffer);
                $scopedSelectors = [];
                foreach ($selectors as $selector) {
                    $sel = trim($selector);
                    if (empty($sel)) continue;
                    
                    if (strpos($sel, '@') === 0) {
                        $scopedSelectors[] = $sel;
                    } elseif ($sel === ':root' || $sel === 'body' || $sel === 'html') {
                        $scopedSelectors[] = $prefix . ' ' . ltrim($sel, ':');
                    } else {
                        $scopedSelectors[] = $prefix . ' ' . $sel;
                    }
                }
                $scopedCss .= implode(', ', $scopedSelectors) . ' {';
            } else {
                // ネストされたブロック（例: @media 内のセレクタ）
                $parts = explode('}', $buffer); // 直前のルールセットとの区切り
                $currentRule = array_pop($parts);
                
                if (trim($currentRule) !== '' && strpos(trim($currentRule), '@') === false) {
                     // セレクタらしきものがあればプレフィックスを試みる
                     $innerSelectors = explode(',', $currentRule);
                     $scopedInner = [];
                     foreach($innerSelectors as $is) {
                         $is = trim($is);
                         if (empty($is)) continue;
                         $scopedInner[] = $prefix . ' ' . $is;
                     }
                     $currentRule = implode(', ', $scopedInner);
                }
                
                $scopedCss .= (count($parts) > 0 ? implode('}', $parts) . '}' : '') . $currentRule . ' {';
            }
            $buffer = '';
            $depth++;
        } elseif ($char === '}') {
            $depth--;
            $scopedCss .= $buffer . "}";
            if ($depth === 0) $scopedCss .= "\n";
            $buffer = '';
        } else {
            $buffer .= $char;
        }
    }

    $scopedCss = trim($scopedCss);

    // 退避したテンプレートタグを復元
    foreach ($tagMap as $placeholder => $original) {
        $scopedCss = str_replace($placeholder, $original, $scopedCss);
    }

    return $scopedCss;
}

/**
 * mikanBox 独自の軽量Markdownパーサー
 * シンプルな正規表現ベースで、基本的なMarkdown記法に対応します。
 */
class MikanBoxMarkdown {
    public function text($text) {
        if (empty($text)) return '';

        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Pre-process: Markdown table → HTML（GFM形式）
        $text = preg_replace_callback(
            '/^(\|.+)\n(\|[\s\-:|]+)\n((?:\|.+\n?)+)/m',
            function($m) {
                $sepCells = array_map('trim', explode('|', trim($m[2], "| \t")));
                $aligns = array_map(function($s) {
                    $l = str_starts_with($s, ':'); $r = str_ends_with($s, ':');
                    if ($l && $r) return ' style="text-align:center"';
                    if ($r)       return ' style="text-align:right"';
                    if ($l)       return ' style="text-align:left"';
                    return '';
                }, $sepCells);
                $headers = array_map('trim', explode('|', trim($m[1], "| \t")));
                $thead = '<tr>';
                foreach ($headers as $i => $h) {
                    $thead .= '<th' . ($aligns[$i] ?? '') . '>' . $this->parseInline($h) . '</th>';
                }
                $thead .= '</tr>';
                $tbody = '';
                foreach (explode("\n", rtrim($m[3])) as $row) {
                    $row = trim($row);
                    if ($row === '' || $row[0] !== '|') continue;
                    $cells = array_map('trim', explode('|', trim($row, "| \t")));
                    $tbody .= '<tr>';
                    foreach ($cells as $i => $cell) {
                        $tbody .= '<td' . ($aligns[$i] ?? '') . '>' . $this->parseInline($cell) . '</td>';
                    }
                    $tbody .= '</tr>';
                }
                return "<table><thead>{$thead}</thead><tbody>{$tbody}</tbody></table>";
            },
            $text
        );

        $lines = explode("\n", $text);
        $result = [];
        $currentParagraph = [];
        $inCodeBlock = false;
        $inHtmlBlock = false;
        $prevWasBlank = true;               // コンテンツ先頭は「空白行の後」扱い
        $paragraphStartedAfterBlank = true; // <p> を付けるか判定する前側フラグ

        // --- 属性（.class や #id）を抽出してタグを組み立てる共通関数 ---
        $applyAttributes = function($content, $tag = 'p') {
            $idAttr = '';
            $classAttr = '';

            // 文末の {.class #id} を探す (より確実な正規表現に変更)
            if (preg_match('/\s*\{([.#][^\{\}]+)\}\s*$/', $content, $matches)) {
                $attrString = $matches[1];
                $content = preg_replace('/\s*\{[.#][^\{\}]+\}\s*$/', '', $content);

                if (preg_match_all('/\.([\w-]+)/', $attrString, $classMatches)) {
                    $classAttr = ' class="' . implode(' ', $classMatches[1]) . '"';
                }
                if (preg_match('/#([\w-]+)/', $attrString, $idMatch)) {
                    $idAttr = ' id="' . $idMatch[1] . '"';
                }
            }

            // タグが null の場合は、属性なしのコンテンツのみを返す
            if ($tag === null) return [trim($content), $idAttr, $classAttr];

            return "<{$tag}{$idAttr}{$classAttr}>" . trim($content) . "</{$tag}>";
        };

        // <p> を付けるか判定：
        //   $followedByBlank=true  → 後ろが空白行/Markdownブロック/コンテンツ末尾
        //   $followedByBlank=false → 後ろがHTMLブロック（空白行なし隣接）
        // 前後どちらかがHTMLに隣接していれば <p> なし
        $closeParagraph = function($followedByBlank = true) use (&$result, &$currentParagraph, &$paragraphStartedAfterBlank, $applyAttributes) {
            if (!empty($currentParagraph)) {
                $content = implode("\n", $currentParagraph);
                $content = $this->parseInline($content);
                if (strpos($content, "\n") !== false) {
                    $content = str_replace("\n", "<br>\n", $content);
                }
                if ($paragraphStartedAfterBlank && $followedByBlank) {
                    $result[] = $applyAttributes($content, 'p');
                } else {
                    $result[] = trim($content);
                }
                $currentParagraph = [];
            }
        };

        // HTMLブロックとして認識するブロックレベルタグ
        $htmlBlockTags = 'address|article|aside|blockquote|body|canvas|caption|col|colgroup|dd|details|dialog|div|dl|dt|fieldset|figcaption|figure|footer|form|h[1-6]|head|header|hr|html|iframe|legend|li|link|main|menu|meta|nav|noscript|ol|optgroup|option|p|pre|script|section|select|source|style|summary|table|tbody|td|tfoot|th|thead|title|tr|ul|video|audio';

        foreach ($lines as $line) {
            // コードブロック
            if (preg_match('/^```/', $line)) {
                $closeParagraph();
                if ($inCodeBlock) {
                    $result[] = '</code></pre>';
                    $inCodeBlock = false;
                } else {
                    $result[] = '<pre><code>';
                    $inCodeBlock = true;
                }
                $prevWasBlank = true; // コードブロック境界 = Markdownブロック扱い
                continue;
            }
            if ($inCodeBlock) {
                $result[] = htmlspecialchars($line);
                continue;
            }

            // HTMLブロックモード中
            if ($inHtmlBlock) {
                if (trim($line) === '') {
                    $inHtmlBlock = false;
                    $prevWasBlank = true; // 空行でHTMLブロック終了 → 次は空白行の後
                } else {
                    $result[] = $line;
                    // 単独の閉じブロックタグ（行全体が </div> などのみ）で終了
                    if (preg_match('/^\s*<\/(?:' . $htmlBlockTags . ')\s*>\s*$/i', $line)) {
                        $inHtmlBlock = false;
                        $prevWasBlank = false; // 閉じタグ = HTMLコンテンツ隣接
                    }
                }
                continue;
            }

            // 空行
            if (trim($line) === '') {
                $closeParagraph(true);
                $prevWasBlank = true;
                continue;
            }

            // HTMLブロック開始
            $isHtmlBlockLine = preg_match('/^\s*(<\/?(?:' . $htmlBlockTags . '|!--|!DOCTYPE)[\s\/>])/i', $line);
            if ($isHtmlBlockLine) {
                $closeParagraph(false); // HTML隣接 → <p> なし
                $result[] = $line;
                $inHtmlBlock = true;
                $prevWasBlank = false;
                continue;
            }

            // Markdownブロック要素（見出し・引用・リスト等）
            // → 前後の段落を <p> ありで閉じ、自身は境界として扱う
            if (preg_match('/^\s*>(?:\s|　)?(.*)/', $line, $matches)) {
                $closeParagraph(true);
                $result[] = '<blockquote>' . $this->parseInline($matches[1]) . '</blockquote>';
                $prevWasBlank = true;
            } elseif (preg_match('/^\s*(#{1,6})(?:\s|　)+(.*)/', $line, $matches)) {
                $closeParagraph(true);
                $level = strlen($matches[1]);
                $result[] = $applyAttributes($this->parseInline($matches[2]), "h{$level}");
                $prevWasBlank = true;
            } elseif (preg_match('/^(\-{3,}|\*{3,}|_{3,})$/', $line)) {
                $closeParagraph(true);
                $result[] = '<hr>';
                $prevWasBlank = true;
            } elseif (preg_match('/^\s*[\*\-\+](?:\s|　)+(.*)/', $line, $matches)) {
                $closeParagraph(true);
                $result[] = '<ul><li>' . $this->parseInline($matches[1]) . '</li></ul>';
                $prevWasBlank = true;
            } elseif (preg_match('/^\s*\d+\.(?:\s|　)+(.*)/', $line, $matches)) {
                $closeParagraph(true);
                $result[] = '<ol><li>' . $this->parseInline($matches[1]) . '</li></ol>';
                $prevWasBlank = true;
            } else {
                // テキスト段落の蓄積
                if (empty($currentParagraph)) {
                    $paragraphStartedAfterBlank = $prevWasBlank;
                }
                $currentParagraph[] = $line;
                $prevWasBlank = false;
            }
        }

        $closeParagraph(true); // コンテンツ末尾 = 境界扱い

        $output = implode("\n", $result);
        $output = preg_replace('/<\/ul>\n<ul>/', "\n", $output);
        $output = preg_replace('/<\/ol>\n<ol>/', "\n", $output);
        
        return $output;
    }

    private function parseInline($text) {
        $map = [];

        // 1. Protect inline code
        $text = preg_replace_callback('/`(.*?)`/', function($m) use (&$map) {
            $ph = "\x02CODE" . count($map) . "\x03";
            $map[$ph] = '<code>' . htmlspecialchars($m[1]) . '</code>';
            return $ph;
        }, $text);

        // 2. Protect image/link syntax so _ inside URLs won't be parsed as italic
        $text = preg_replace_callback('/\!\[(.*?)\]\((.*?)\)/', function($m) use (&$map) {
            $url = $m[2];
            if (!preg_match('/^(?:https?:\/\/|\/|media\/)/', $url)) { $url = 'media/' . $url; }
            $ph = "\x02LINK" . count($map) . "\x03";
            $map[$ph] = '<img src="' . $url . '" alt="' . $m[1] . '">';
            return $ph;
        }, $text);
        $text = preg_replace_callback('/\[(.*?)\]\((.*?)\)/', function($m) use (&$map) {
            $ph = "\x02LINK" . count($map) . "\x03";
            $map[$ph] = '<a href="' . $m[2] . '">' . $m[1] . '</a>';
            return $ph;
        }, $text);

        // 3. Process bold / italic / strikethrough
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/~~(.*?)~~/', '<del>$1</del>', $text);

        // 3.5 Inline span with class/id: [text]{.class #id}
        $text = preg_replace_callback('/\[([^\]]+)\]\{([^}]+)\}/', function($m) use (&$map) {
            $classAttr = '';
            $idAttr = '';
            if (preg_match_all('/\.([\w-]+)/', $m[2], $cm)) {
                $classAttr = ' class="' . implode(' ', $cm[1]) . '"';
            }
            if (preg_match('/#([\w-]+)/', $m[2], $im)) {
                $idAttr = ' id="' . $im[1] . '"';
            }
            $ph = "\x02SPAN" . count($map) . "\x03";
            $map[$ph] = "<span{$idAttr}{$classAttr}>{$m[1]}</span>";
            return $ph;
        }, $text);

        // 4. Restore placeholders
        foreach ($map as $ph => $html) {
            $text = str_replace($ph, $html, $text);
        }

        return $text;
    }
}

function generateSitemapXml($settings) {
    $renderer = new MikanBoxRenderer($settings);
    $siteUrl  = rtrim($renderer->getSiteUrl(), '/');
    $allPosts = getSortedPostIds();
    $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    foreach ($allPosts as $pageId) {
        $data = loadData(POSTS_DIR, $pageId);
        if (!in_array($data['status'] ?? '', ['public_dynamic', 'public_static'])) continue;
        $loc     = htmlspecialchars($siteUrl . '/' . ($pageId === 'index' ? '' : $pageId));
        $lastmod = substr($data['updated_at'] ?? date('Y-m-d H:i:s'), 0, 10);
        $xml .= "  <url>\n    <loc>{$loc}</loc>\n    <lastmod>{$lastmod}</lastmod>\n  </url>\n";
    }
    $xml .= "</urlset>\n";
    return $xml;
}

function generateRssXml($settings) {
    $renderer  = new MikanBoxRenderer($settings);
    $siteUrl   = rtrim($renderer->getSiteUrl(), '/');
    $siteTitle = htmlspecialchars($settings['site_name'] ?? 'mikanBox');
    $siteDesc  = htmlspecialchars($settings['description'] ?? '');
    $allPosts  = getSortedPostIds();
    $items = [];
    foreach ($allPosts as $pageId) {
        $data = loadData(POSTS_DIR, $pageId);
        if (!in_array($data['status'] ?? '', ['public_dynamic', 'public_static'])) continue;
        $items[] = ['id' => $pageId, 'data' => $data];
    }
    usort($items, fn($a, $b) => strcmp($b['data']['updated_at'] ?? '', $a['data']['updated_at'] ?? ''));
    $items = array_slice($items, 0, 20);
    $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<rss version=\"2.0\">\n<channel>\n";
    $xml .= "  <title>{$siteTitle}</title>\n  <link>{$siteUrl}/</link>\n";
    $xml .= "  <description>{$siteDesc}</description>\n  <lastBuildDate>" . date('r') . "</lastBuildDate>\n";
    foreach ($items as $item) {
        $title   = htmlspecialchars($item['data']['title'] ?? $item['id']);
        $desc    = htmlspecialchars($item['data']['description'] ?? '');
        $link    = htmlspecialchars($siteUrl . '/' . ($item['id'] === 'index' ? '' : $item['id']));
        $pubDate = date('r', strtotime($item['data']['updated_at'] ?? date('Y-m-d H:i:s')));
        $xml .= "  <item>\n    <title>{$title}</title>\n    <link>{$link}</link>\n";
        $xml .= "    <description>{$desc}</description>\n    <pubDate>{$pubDate}</pubDate>\n";
        $xml .= "    <guid>{$link}</guid>\n  </item>\n";
    }
    $xml .= "</channel>\n</rss>\n";
    return $xml;
}

function generatePodcastXml($settings) {
    $renderer  = new MikanBoxRenderer($settings);
    $siteUrl   = rtrim($renderer->getSiteUrl(), '/');
    $siteTitle = htmlspecialchars($settings['site_name'] ?? 'mikanBox');
    $siteDesc  = htmlspecialchars($settings['description'] ?? '');
    $author    = $siteTitle;
    $ogpImage  = $settings['ogp_image'] ?? '';
    if (!empty($ogpImage) && !preg_match('/^https?:\/\//', $ogpImage)) {
        $ogpImage = $siteUrl . '/media/' . ltrim($ogpImage, '/');
    }
    $allPosts = getSortedPostIds();
    $items = [];
    foreach ($allPosts as $pageId) {
        $data = loadData(POSTS_DIR, $pageId);
        if (!in_array($data['status'] ?? '', ['public_dynamic', 'public_static'])) continue;
        $cats = array_filter(array_map('trim', explode(',', $data['category'] ?? '')));
        if (!in_array('podcast', $cats)) continue;
        $items[] = ['id' => $pageId, 'data' => $data];
    }
    usort($items, fn($a, $b) => strcmp($b['data']['updated_at'] ?? '', $a['data']['updated_at'] ?? ''));
    $xml  = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    $xml .= "<rss version=\"2.0\" xmlns:itunes=\"http://www.itunes.com/dtds/podcast-1.0.dtd\">\n<channel>\n";
    $xml .= "  <title>{$siteTitle}</title>\n  <link>{$siteUrl}/</link>\n";
    $xml .= "  <description>{$siteDesc}</description>\n  <language>ja</language>\n";
    $xml .= "  <lastBuildDate>" . date('r') . "</lastBuildDate>\n";
    $xml .= "  <itunes:author>{$author}</itunes:author>\n";
    $xml .= "  <itunes:summary>{$siteDesc}</itunes:summary>\n";
    if (!empty($ogpImage)) $xml .= "  <itunes:image href=\"" . htmlspecialchars($ogpImage) . "\"/>\n";
    $xml .= "  <itunes:explicit>false</itunes:explicit>\n";
    foreach ($items as $item) {
        $data    = $item['data'];
        $content = $data['content_md'] ?? '';
        $dataGet = function($key) use ($content) {
            preg_match('/\{\{DATA:' . $key . '(?::GHOST)?\}\}([^{]+)\{\{\/DATA\}\}/i', $content, $m);
            return trim($m[1] ?? '');
        };
        $audioFile   = $dataGet('AUDIO_FILE');
        if (empty($audioFile)) continue;
        if (!preg_match('/^https?:\/\//', $audioFile)) $audioFile = $siteUrl . '/media/' . $audioFile;
        $duration    = $dataGet('DURATION');
        $fileSize    = (int)$dataGet('FILE_SIZE');
        $episodeNum  = $dataGet('EPISODE_NUM');
        $season      = $dataGet('SEASON');
        $subtitle    = $dataGet('SUBTITLE');
        $episodeType = $dataGet('EPISODE_TYPE') ?: 'full';
        $explicit    = $dataGet('EXPLICIT') ?: 'false';
        $epImage     = $dataGet('EPISODE_IMAGE');
        if (!empty($epImage) && !preg_match('/^https?:\/\//', $epImage)) {
            $epImage = $siteUrl . '/media/' . $epImage;
        }
        $ext     = strtolower(pathinfo($audioFile, PATHINFO_EXTENSION));
        $mime    = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav'][$ext] ?? 'audio/mpeg';
        $title   = htmlspecialchars($data['title'] ?? $item['id']);
        $desc    = htmlspecialchars($data['description'] ?? '');
        $link    = htmlspecialchars($siteUrl . '/' . $item['id']);
        $pubDate = date('r', strtotime($data['updated_at'] ?? date('Y-m-d H:i:s')));
        $xml .= "  <item>\n    <title>{$title}</title>\n    <link>{$link}</link>\n";
        $xml .= "    <description>{$desc}</description>\n    <pubDate>{$pubDate}</pubDate>\n";
        $xml .= "    <guid>{$link}</guid>\n";
        $xml .= "    <enclosure url=\"" . htmlspecialchars($audioFile) . "\" length=\"{$fileSize}\" type=\"{$mime}\"/>\n";
        if (!empty($duration))    $xml .= "    <itunes:duration>{$duration}</itunes:duration>\n";
        if (!empty($subtitle))    $xml .= "    <itunes:subtitle>" . htmlspecialchars($subtitle) . "</itunes:subtitle>\n";
        if (!empty($episodeNum))  $xml .= "    <itunes:episode>{$episodeNum}</itunes:episode>\n";
        if (!empty($season))      $xml .= "    <itunes:season>{$season}</itunes:season>\n";
        if (!empty($epImage))     $xml .= "    <itunes:image href=\"" . htmlspecialchars($epImage) . "\"/>\n";
        $xml .= "    <itunes:episodeType>{$episodeType}</itunes:episodeType>\n";
        $xml .= "    <itunes:explicit>{$explicit}</itunes:explicit>\n";
        $xml .= "    <itunes:summary>{$desc}</itunes:summary>\n  </item>\n";
    }
    $xml .= "</channel>\n</rss>\n";
    return $xml;
}

function resolveMediaPath($url) {
    if (empty($url)) return '';
    // images/ で始まる場合は media/ に置換
    if (strpos($url, 'images/') === 0) {
        return 'media/' . substr($url, 7);
    }
    // 外部URL, 絶対パス, または既に media/ で始まる場合はそのまま
    if (preg_match('/^(?:https?:\/\/|\/|media\/)/', $url)) {
        return $url;
    }
    return 'media/' . $url;
}
