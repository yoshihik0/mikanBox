<?php
// ==========================================
// mikanBox 共通ユーティリティ関数
// flat版・sqlite版で完全に同一の関数のみを集約したファイル。
// mikanBox-flat と mikanBox-sqlite の lib/functions.php から require される。
// このファイルは shared/lib/functions-common.php が正本（sync.shで同期）。
// ==========================================

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
 * Sanitize $id and resolve it to an absolute path under $baseDir, verified via
 * realpath() containment — not a fragile str_replace('..','',...) blacklist,
 * which only strips literal ".." substrings and can't catch every traversal
 * shape. realpath() resolves the path fully (including symlinks) and confirms
 * it still lives inside $baseDir before any file is touched.
 * @param string $baseDir  Directory the resolved path must stay within
 * @param string $id       Untrusted record/file ID
 * @param string $suffix   Filename suffix, e.g. '.json'
 * @param bool   $createParent  Create the parent directory if missing (save use case)
 * @return string|false Absolute safe path, or false if $id is invalid/escapes $baseDir
 */
function resolveSafeDataPath($baseDir, $id, $suffix = '.json', $createParent = false) {
    if (!is_string($id) || $id === '' || strpos($id, "\0") !== false) return false;
    $id = ltrim($id, '/\\');
    $id = preg_replace('/[^a-zA-Z0-9_\-\/\.]/', '', $id);
    if ($id === '' || $id === '.' || $id === '..') return false;

    $realBase = realpath($baseDir);
    if ($realBase === false) return false;

    $candidate = $baseDir . '/' . $id . $suffix;
    $parentDir = dirname($candidate);
    if ($createParent && !is_dir($parentDir)) {
        @mkdir($parentDir, 0777, true);
    }
    $realParent = realpath($parentDir);
    if ($realParent === false) return false; // 親ディレクトリが存在しない = 対象なし

    // 実パスがbaseDir配下に収まっているか確認（シンボリックリンク等での脱出も防ぐ）
    if ($realParent !== $realBase && strpos($realParent . DIRECTORY_SEPARATOR, $realBase . DIRECTORY_SEPARATOR) !== 0) {
        return false;
    }

    return $realParent . '/' . basename($candidate);
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

    // セレクタ全体を :global(...) で囲んだ場合はスコープ外へ出す。
    // 例: :global(.hero_box) -> .hero_box
    // 部分指定（:global(.foo) .bar）は意図的にサポートしない。
    $scopeSelector = function($selector) use ($prefix) {
        $sel = trim($selector);
        if (empty($sel)) return '';

        if (preg_match('/^:global\((.+)\)$/s', $sel, $matches)) {
            return trim($matches[1]);
        }

        if (strpos($sel, '@') === 0) return $sel;
        if ($sel === ':root' || $sel === 'body' || $sel === 'html') {
            return $prefix . ' ' . ltrim($sel, ':');
        }
        return $prefix . ' ' . $sel;
    };

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
                    $scopedSelector = $scopeSelector($selector);
                    if ($scopedSelector !== '') $scopedSelectors[] = $scopedSelector;
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
                         $scopedSelector = $scopeSelector($is);
                         if ($scopedSelector !== '') $scopedInner[] = $scopedSelector;
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

        // Protect fenced code blocks (```...```) from the table pre-processor below.
        // Table conversion runs on the whole text before the per-line loop can tell
        // whether a line is inside a code fence, so without this a table written as a
        // literal syntax example inside ``` would get converted to <table> first, and
        // the code-block handler would then show those already-converted tags as
        // escaped text instead of the original "| cell |" syntax the user typed.
        $codeBlockMap = [];
        $text = preg_replace_callback('/^```[^\n]*\n(.*?)\n```[ \t]*$/ms', function($m) use (&$codeBlockMap) {
            $ph = "\x02CODEBLOCK" . count($codeBlockMap) . "\x03";
            $codeBlockMap[$ph] = $m[0];
            return $ph;
        }, $text);
        // 閉じられていないフェンス（``` の閉じ忘れ）が残っていれば、その開始行から末尾までを保護する。
        // 閉じたフェンスは上で置換済みなので、この時点で残る ``` 行は未閉鎖の開始行だけ。
        $text = preg_replace_callback('/^```[^\n]*\n[\s\S]*$/m', function($m) use (&$codeBlockMap) {
            $ph = "\x02CODEBLOCK" . count($codeBlockMap) . "\x03";
            $codeBlockMap[$ph] = $m[0];
            return $ph;
        }, $text);

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
                // マッチが末尾の改行まで消費しているので復元する。
                // 復元しないと、テーブル直後の行が<table>...</table>と同一行に結合されてしまい、
                // 後続のMarkdown行（見出し等）が変換されないまま巻き込まれる。
                $trailingNewline = str_ends_with($m[3], "\n") ? "\n" : '';
                return "<table><thead>{$thead}</thead><tbody>{$tbody}</tbody></table>" . $trailingNewline;
            },
            $text
        );

        // Restore the protected code blocks, unmodified, before the per-line loop runs.
        foreach ($codeBlockMap as $ph => $original) {
            $text = str_replace($ph, $original, $text);
        }

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
                if (strpos($content, "\n") !== false) {
                    // {.class} や {#id} だけの末尾行は属性指定なので前の改行を除去して同行に結合
                    $content = preg_replace('/\n(\s*\{[.#][^\{\}]+\}\s*)$/', ' $1', $content);
                    // HTMLタグの境界（>の後、<の前）には<br>を挿入しない。
                    // parseInline() より前に行うことで、**太字**や[リンク](url)がインラインHTML
                    // （</strong>や</a>など）に変換された後の閉じタグに巻き込まれて、
                    // その後の改行への<br>挿入が誤って抑制されるのを防ぐ。
                    $content = preg_replace('/(?<!>)\n(?!<)/', "<br>\n", $content);
                }
                $content = $this->parseInline($content);
                // タグプレースホルダー（{{NAV_CARDS}}等の保護形）単独の段落は<p>で包まない。
                // ブロック要素へ展開されるタグが<p>の中に入るとinvalid HTMLになり、
                // ブラウザの自動修復で空の<p>が残ってレイアウトを乱すため。
                $isTagOnly = preg_match('/^\s*\x{200B}?<!--MKNTG[a-zA-Z0-9+\/=]+-->\x{200B}?\s*$/u', $content);
                if ($paragraphStartedAfterBlank && $followedByBlank && !$isTagOnly) {
                    $result[] = $applyAttributes($content, 'p');
                } else {
                    $result[] = trim($content);
                }
                $currentParagraph = [];
            }
        };

        // HTMLブロックとして認識するブロックレベルタグ
        $htmlBlockTags = 'a|address|article|aside|blockquote|body|button|canvas|caption|col|colgroup|dd|details|dialog|div|dl|dt|fieldset|figcaption|figure|footer|form|h[1-6]|head|header|hr|html|iframe|legend|li|link|main|menu|meta|nav|noscript|ol|optgroup|option|p|pre|script|section|select|source|span|style|summary|table|tbody|td|tfoot|th|thead|title|tr|ul|video|audio';

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
                // 行内で完結しているHTML（閉じタグ・自己終了・コメント終端で終わる行、
                // または void 要素のみの行）はブロックモードに入らない。
                // 入ってしまうと、<div>x</div> やテーブル変換結果のような1行完結HTMLの
                // 直後に空行なしで書かれたMarkdown行が生テキストのまま巻き込まれる。
                $selfContained = preg_match('/(?:<\/(?:' . $htmlBlockTags . ')\s*>|\/>|-->)\s*$/i', $line)
                    || preg_match('/^\s*<(?:hr|link|meta|source|col)\b[^>]*>\s*$/i', $line);
                if (!$selfContained) {
                    $inHtmlBlock = true;
                }
                $prevWasBlank = false;
                continue;
            }

            // Markdownブロック要素（見出し・引用・リスト等）
            // → 前後の段落を <p> ありで閉じ、自身は境界として扱う
            if (preg_match('/^\s*>(?:\s|　)?(.*)/', $line, $matches)) {
                $closeParagraph(true);
                $result[] = $applyAttributes($this->parseInline($matches[1]), 'blockquote');
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
                $result[] = '<ul>' . $applyAttributes($this->parseInline($matches[1]), 'li') . '</ul>';
                $prevWasBlank = true;
            } elseif (preg_match('/^\s*\d+\.(?:\s|　)+(.*)/', $line, $matches)) {
                $closeParagraph(true);
                $result[] = '<ol>' . $applyAttributes($this->parseInline($matches[1]), 'li') . '</ol>';
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

        // 閉じ忘れのコードフェンスを末尾で閉じる（<pre><code>が開いたまま後続HTMLを壊さないように）
        if ($inCodeBlock) {
            $result[] = '</code></pre>';
        }

        $output = implode("\n", $result);
        $output = preg_replace('/<\/ul>\n<ul>/', "\n", $output);
        $output = preg_replace('/<\/ol>\n<ol>/', "\n", $output);
        // 連続する引用行は1つのblockquoteに結合する（段落内の改行と同様に<br>でつなぐ）
        $output = preg_replace('/<\/blockquote>\n<blockquote>/', "<br>\n", $output);

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
            $map[$ph] = '<img src="' . $url . '" alt="' . $m[1] . '"' . mediaImgAttrs($url) . '>';
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
        // 入れ子対応: 内容に [ ] を含まないもの（最内側）から順にプレースホルダー化し、
        // 変化がなくなるまで反復する。プレースホルダーはブラケットを含まないため、
        // 次の周回で外側の [ ... ]{...} がマッチする。これにより
        // [[save]{.material-symbols-outlined}保存]{.m-btn .m-btn-blue} のような
        // アイコン入りボタン等の入れ子スパンが書ける。
        $prev = null;
        $guard = 0;
        while ($prev !== $text && $guard++ < 10) {
            $prev = $text;
            $text = preg_replace_callback('/\[([^\[\]]+)\]\{([^}]+)\}/', function($m) use (&$map) {
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
        }

        // 4. Restore placeholders
        // 逆順で復元する: 後から作られたプレースホルダー（外側のスパン等）のHTMLの中に、
        // 先に作られたプレースホルダー（インラインコード・リンク・内側のスパン）が
        // 含まれることがあるため、外側から復元しないと生のプレースホルダーが残る。
        foreach (array_reverse($map, true) as $ph => $html) {
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

/**
 * Resolves the destination filename for media uploads.
 * If a category is specified and the filename does not match ^[a-zA-Z0-9]+_ (e.g. g_, news_, blog_),
 * it prefixes the filename with the category name followed by an underscore.
 * If a file with the same name already exists in the media folder, it appends a suffix (_1, _2, etc.)
 * before the extension to avoid overwriting.
 * 
 * @param string $filename Original uploaded filename (or base name)
 * @param string $category Current active category (can be empty)
 * @return string Resolved filename
 */
function resolveMediaSaveName($filename, $category) {
    // 1. Sanitize filename (remove directory traversal etc.)
    $filename = basename($filename);
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $filename);
    
    // Extract name and extension
    $ext = pathinfo($filename, PATHINFO_EXTENSION);
    $name = pathinfo($filename, PATHINFO_FILENAME);
    if (empty($name)) {
        $name = 'upload';
    }
    
    // 2. Auto-prefix category if appropriate (No-Double-Prefix Rule: ^[a-zA-Z0-9]+_)
    if (!empty($category) && !preg_match('/^[a-zA-Z0-9]+_/', $filename)) {
        $name = $category . '_' . $name;
    }
    
    // 3. Resolve duplicates
    $baseName = $name;
    $targetFilename = $ext !== '' ? $baseName . '.' . $ext : $baseName;
    $mediaDir = MEDIA_DIR;
    
    $counter = 1;
    while (file_exists($mediaDir . '/' . $targetFilename)) {
        $targetFilename = ($ext !== '') ? ($baseName . '_' . $counter . '.' . $ext) : ($baseName . '_' . $counter);
        $counter++;
    }
    
    return $targetFilename;
}

/**
 * Strip dangerous content from an uploaded SVG file before saving it.
 * SVG can embed <script>, event handlers, and javascript: URIs, which would
 * otherwise execute in the browser when the file is opened directly.
 */
function sanitizeSvgContent($content) {
    $content = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $content);
    $content = preg_replace('/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]*)/i', '', $content);
    $content = preg_replace('/href\s*=\s*["\']javascript:[^"\']*["\']/i', 'href="#"', $content);
    return $content;
}

// ==========================================
// メディア画像の自動処理
// （アップロード時のリサイズ / WebP変換と、出力時の img 属性付与）
// ==========================================

/**
 * Convert a php.ini shorthand size ("256M", "1G") into bytes.
 */
function mediaParseIniBytes($val) {
    $val = trim((string)$val);
    if ($val === '') return 0;
    $unit = strtolower($val[strlen($val) - 1]);
    $num = (float)$val;
    switch ($unit) {
        case 'g': $num *= 1024; // fall through
        case 'm': $num *= 1024; // fall through
        case 'k': $num *= 1024;
    }
    return (int)$num;
}

/**
 * GD holds images as uncompressed bitmaps, so the memory it needs depends on
 * the pixel count, not the file size: a 10000x10000 image costs ~400MB however
 * small its JPEG happens to be. Upload limits are often set to hundreds of
 * megabytes, so such images do arrive, and loading one blindly would abort the
 * request with a fatal error. Estimate first and let the caller skip instead.
 */
function mediaImageFitsInMemory($srcW, $srcH, $dstW, $dstH) {
    $limit = mediaParseIniBytes(ini_get('memory_limit'));
    if ($limit <= 0) return true; // -1 means unlimited
    $needed = (($srcW * $srcH) + ($dstW * $dstH)) * 4 * 1.2; // 4 bytes/px + GD overhead
    $available = $limit - memory_get_usage(true);
    return $needed < ($available * 0.8);
}

/**
 * Post-process a freshly uploaded image according to the upload options:
 * optionally cap its long edge, and optionally re-encode it as WebP.
 *
 * Both are opt-in and destructive - the original bytes are not kept. That is
 * deliberate: keeping variants around would multiply the files that have to be
 * backed up, synced and garbage collected, which is exactly the WordPress
 * behaviour this deliberately avoids.
 *
 * Converting to WebP changes the extension, and therefore the filename that
 * pages will reference. This is safe here only because the file has just been
 * uploaded and nothing links to it yet; never run this over existing media.
 *
 * Returns ['filename' => final name, 'skipped' => bool, 'changed' => bool].
 */
function mediaProcessUploadedImage($filename, $maxDim, $toWebp) {
    $result = ['filename' => $filename, 'skipped' => false, 'changed' => false];

    $path = MEDIA_DIR . '/' . $filename;
    if (!file_exists($path) || !function_exists('imagecreatefromjpeg')) return $result;

    // GIF is excluded because GD reads only the first frame and cannot write
    // animated WebP, so re-encoding one would silently destroy the animation.
    // SVG is excluded because rasterising a vector is a downgrade.
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) return $result;

    $info = @getimagesize($path);
    if (!$info) return $result;
    $srcW = (int)$info[0];
    $srcH = (int)$info[1];
    $type = $info[2];
    if ($srcW < 1 || $srcH < 1) return $result;

    $toWebp = $toWebp && function_exists('imagewebp') && $type !== IMAGETYPE_WEBP;

    // Resolve the target size before the memory check so the estimate covers
    // both the source and the destination bitmap.
    $dstW = $srcW;
    $dstH = $srcH;
    if ($maxDim > 0 && max($srcW, $srcH) > $maxDim) {
        $scale = $maxDim / max($srcW, $srcH);
        $dstW = max(1, (int)round($srcW * $scale));
        $dstH = max(1, (int)round($srcH * $scale));
    }
    $needsResize = ($dstW !== $srcW || $dstH !== $srcH);
    if (!$needsResize && !$toWebp) return $result;

    if (!mediaImageFitsInMemory($srcW, $srcH, $dstW, $dstH)) {
        // Keep the upload, drop the optimisation.
        $result['skipped'] = true;
        return $result;
    }

    switch ($type) {
        case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($path); break;
        case IMAGETYPE_PNG:  $src = @imagecreatefrompng($path); break;
        case IMAGETYPE_WEBP: $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null; break;
        default: $src = null;
    }
    if (!$src) return $result;

    $keepAlpha = ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP);
    if ($needsResize) {
        $dst = imagecreatetruecolor($dstW, $dstH);
        if ($keepAlpha) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
    } else {
        $dst = $src;
        if ($keepAlpha) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
    }

    $outName = $filename;
    $ok = false;
    if ($toWebp) {
        $outName = resolveMediaSaveName(pathinfo($filename, PATHINFO_FILENAME) . '.webp', '');
        $outPath = MEDIA_DIR . '/' . $outName;
        // PNG sources are usually screenshots or diagrams, where lossy
        // re-encoding smears text and thin lines, so keep those lossless.
        $quality = ($type === IMAGETYPE_PNG && defined('IMG_WEBP_LOSSLESS')) ? IMG_WEBP_LOSSLESS : 82;
        $ok = @imagewebp($dst, $outPath, $quality);
        if ($ok && !$needsResize && filesize($outPath) >= filesize($path)) {
            // A straight conversion that made the file bigger is not worth the
            // renamed file; keep the original.
            @unlink($outPath);
            $ok = false;
            $outName = $filename;
        } elseif ($ok) {
            @unlink($path);
        }
    } else {
        switch ($type) {
            case IMAGETYPE_JPEG: $ok = @imagejpeg($dst, $path, 85); break;
            case IMAGETYPE_PNG:  $ok = @imagepng($dst, $path); break;
            case IMAGETYPE_WEBP: $ok = @imagewebp($dst, $path); break;
        }
    }

    if ($ok) {
        $result['filename'] = $outName;
        $result['changed'] = true;
    }
    return $result;
}

/**
 * Reset the per-page image state. Called once per rendered page so that the
 * "first image" rule below is scoped to the page, not to the whole build.
 */
function mediaResetImageFlow() {
    $GLOBALS['mikanbox_img_large_seen'] = false;
}

/**
 * Resolve an image URL used in page content to a readable local media file,
 * or null when it is remote, missing, or a format with no pixel dimensions.
 */
function mediaLocalPathForUrl($url) {
    if (preg_match('#^(?:https?:)?//#i', $url)) return null;
    $name = basename((string)parse_url($url, PHP_URL_PATH));
    if ($name === '' || strtolower(pathinfo($name, PATHINFO_EXTENSION)) === 'svg') return null;
    $path = MEDIA_DIR . '/' . $name;
    return file_exists($path) ? $path : null;
}

/**
 * Extra <img> attributes for a media URL.
 *
 * width/height are the intrinsic pixel size: the browser can then reserve the
 * right box before the bytes arrive, which removes the layout shift as images
 * pop in.
 *
 * The loading hint is deliberately not uniform. Lazy-loading the first large
 * image would delay the one most likely to be the LCP element, since lazy
 * images are skipped by the preload scanner - so the first one is fetched
 * eagerly and only later ones are deferred. Small images (logos, icons,
 * dividers) get no hint at all: deferring a few KB saves nothing and only adds
 * a render delay.
 */
function mediaImgAttrs($url) {
    $path = mediaLocalPathForUrl($url);
    if ($path === null) return '';
    $info = @getimagesize($path);
    if (!$info) return '';

    $w = (int)$info[0];
    $h = (int)$info[1];
    if ($w < 1 || $h < 1) return '';

    $attrs = ' width="' . $w . '" height="' . $h . '"';
    if ($w < 200 && $h < 200) return $attrs;

    if (empty($GLOBALS['mikanbox_img_large_seen'])) {
        $GLOBALS['mikanbox_img_large_seen'] = true;
        $attrs .= ' fetchpriority="high"';
    } else {
        $attrs .= ' loading="lazy" decoding="async"';
    }
    return $attrs;
}

// ==========================================
// ログイン・パスワードリセットのブルートフォース対策
// （試行回数制限。data/login_attempts.json にIP単位で記録、flockで排他制御）
// ==========================================
const LOGIN_MAX_ATTEMPTS       = 10;   // この回数失敗するとロック
const LOGIN_LOCKOUT_SECONDS    = 900;  // ロック時間（15分）
const LOGIN_ATTEMPT_WINDOW_SEC = 1800; // この時間失敗がなければカウントをリセット（30分）

function loginAttemptsFilePath() {
    return DATA_DIR . '/login_attempts.json';
}

function readLoginAttempts() {
    $file = loginAttemptsFilePath();
    if (!file_exists($file)) return [];
    $fp = @fopen($file, 'r');
    if (!$fp) return [];
    flock($fp, LOCK_SH);
    $data = json_decode(stream_get_contents($fp), true) ?: [];
    flock($fp, LOCK_UN);
    fclose($fp);
    return $data;
}

function writeLoginAttempts(array $data) {
    $file = loginAttemptsFilePath();
    $fp = @fopen($file, 'c+');
    if (!$fp) return;
    flock($fp, LOCK_EX);
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
    flock($fp, LOCK_UN);
    fclose($fp);
}

/**
 * ロック中なら残り秒数を返す。ロックされていなければ 0。
 * $identifier は用途ごとに分ける（例: IP + "|login" / IP + "|reset"）。
 */
function checkLoginRateLimit($identifier) {
    $data  = readLoginAttempts();
    $entry = $data[$identifier] ?? null;
    if ($entry && !empty($entry['locked_at'])) {
        $remain = ($entry['locked_at'] + LOGIN_LOCKOUT_SECONDS) - time();
        if ($remain > 0) return $remain;
    }
    return 0;
}

/** 失敗を記録する。閾値に達したらロックを開始する。 */
function recordLoginFailure($identifier) {
    $data  = readLoginAttempts();
    $entry = $data[$identifier] ?? ['count' => 0, 'first_at' => time(), 'locked_at' => 0];

    // ロック時間が過ぎていれば解除してカウントリセット
    if (!empty($entry['locked_at']) && time() > $entry['locked_at'] + LOGIN_LOCKOUT_SECONDS) {
        $entry = ['count' => 0, 'first_at' => time(), 'locked_at' => 0];
    }
    // 一定時間失敗がなければウィンドウをリセット
    if (empty($entry['locked_at']) && (time() - ($entry['first_at'] ?? 0)) > LOGIN_ATTEMPT_WINDOW_SEC) {
        $entry = ['count' => 0, 'first_at' => time(), 'locked_at' => 0];
    }

    $entry['count']++;
    if ($entry['count'] >= LOGIN_MAX_ATTEMPTS) {
        $entry['locked_at'] = time();
    }
    $data[$identifier] = $entry;

    // 古いエントリを間引き（24時間以上前でロック中でないもの）
    $cutoff = time() - 86400;
    foreach ($data as $k => $v) {
        if (empty($v['locked_at']) && ($v['first_at'] ?? 0) < $cutoff) {
            unset($data[$k]);
        }
    }
    writeLoginAttempts($data);
}

/** ログイン・リセット成功時に呼び、その識別子の失敗カウントを消す。 */
function clearLoginAttempts($identifier) {
    $data = readLoginAttempts();
    if (isset($data[$identifier])) {
        unset($data[$identifier]);
        writeLoginAttempts($data);
    }
}

function getClientIp() {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}
