<?php
// Markdown のブロック分割・目印の挿入・HTMLの後処理（本体の描画処理は、そのまま使う）

/** Markdown を、空行区切りのブロックに分ける（コードフェンスと {{DATA…}} の範囲は1ブロック） */
function mbv_split_blocks(string $src): array {
    $lines = explode("\n", $src); $blocks = []; $cur = null; $fence = null; $dataDepth = 0;
    $isListStart = static fn($l) => preg_match('/^\s*([-*+]|\d+[.)])\s/', $l);
    $flush = function () use (&$cur, &$blocks) { if ($cur) { $blocks[] = $cur; $cur = null; } };
    foreach ($lines as $i => $l) {
        if ($fence !== null) {
            $cur['end'] = $i;
            if (preg_match('/^\s*' . preg_quote($fence[0], '/') . '{' . $fence[1] . ',}\s*$/', $l)) { $fence = null; $flush(); }
            continue;
        }
        if ($dataDepth > 0) {
            $cur['end'] = $i;
            $dataDepth += preg_match_all('/\{\{DATA(?:ROW)?:[^}]*\}\}/', $l) - preg_match_all('/\{\{\/DATA(?:ROW)?\}\}/', $l);
            if ($dataDepth <= 0) { $dataDepth = 0; $flush(); }
            continue;
        }
        if (trim($l) === '') { if ($cur) $cur['pendingBlank'] = true; continue; }
        if ($cur && !empty($cur['pendingBlank'])) {
            $cont = ($cur['list'] ?? false) && ($isListStart($l) || preg_match('/^\s{2,}\S/', $l));
            if ($cont) { $cur['pendingBlank'] = false; $cur['end'] = $i; continue; }
            $flush();
        }
        if (!$cur) $cur = ['start' => $i, 'end' => $i, 'list' => (bool)$isListStart($l)]; else $cur['end'] = $i;
        if (preg_match('/^\s*(`{3,}|~{3,})/', $l, $m)) { $fence = [$m[1][0], strlen($m[1])]; }
        elseif (preg_match_all('/\{\{DATA(?:ROW)?:[^}]*\}\}/', $l, $o1)) {
            $dataDepth = count($o1[0]) - preg_match_all('/\{\{\/DATA(?:ROW)?\}\}/', $l);
            if ($dataDepth <= 0) $dataDepth = 0;
        }
    }
    $flush();
    foreach ($blocks as &$b) $b['text'] = implode("\n", array_slice($lines, $b['start'], $b['end'] - $b['start'] + 1));
    return $blocks;
}

/** 各ブロックの前後に、目印 <!--fv:開始-終了--> と <!--/fv--> を入れた Markdown を返す（ブロックの間にはもともと空行がある） */
function mbv_inject_markers(string $md): string {
    $lines = explode("\n", $md); $blocks = mbv_split_blocks($md); $out = []; $i = 0;
    foreach ($blocks as $b) {
        while ($i < $b['start']) { $out[] = $lines[$i++]; }
        $out[] = '<!--fv:' . $b['start'] . '-' . $b['end'] . '-->';
        $out[] = '';
        while ($i <= $b['end']) { $out[] = $lines[$i++]; }
        $out[] = '';
        $out[] = '<!--/fv-->';
    }
    while ($i < count($lines)) { $out[] = $lines[$i++]; }
    return implode("\n", $out);
}

/** Markdown が目印を <p> で包んだ分を外す。data-ln は、ブラウザ側が、目印の間の要素に付ける */
function mbv_apply_markers(string $html): string {
    return preg_replace('~<p>\s*(<!--/?fv(?::\d+-\d+)?-->)\s*</p>\s*~', '$1', $html);
}

/** HTML の開閉が釣り合っているか（途中のブロックを動かす・消すとHTMLが壊れるのを防ぐ） */
function mbv_html_balanced(string $text): bool {
    if (preg_match('/^\s*(`{3,}|~{3,})/', $text)) return true;
    $text = preg_replace('~<!--.*?-->~s', '', $text);
    $stack = [];
    if (!preg_match_all('~<(/?)(div|section|article|aside|header|footer|main|nav|ul|ol|table|details|figure|form|blockquote|pre|span|a|p)\b[^>]*?(/?)>~i', $text, $m, PREG_SET_ORDER)) return true;
    foreach ($m as $t) {
        $name = strtolower($t[2]);
        if ($t[3] === '/') continue;
        if ($t[1] === '/') { if (!$stack || array_pop($stack) !== $name) return false; }
        else $stack[] = $name;
    }
    return !$stack;
}
