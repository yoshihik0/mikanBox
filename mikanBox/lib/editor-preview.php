<?php
/** Render the submitted editor state without writing a page or revision. */
function mikanBoxRenderEditorPreview(array $settings, array $input): void {
    $text = static function (string $key, string $default = '') use ($input): string {
        return isset($input[$key]) && is_string($input[$key]) ? $input[$key] : $default;
    };
    $id = trim($text('id')) ?: 'preview';
    if (strlen($id) > 200 || !preg_match('~\A[A-Za-z0-9_-]+(?:/[A-Za-z0-9_-]+)*\z~', $id)) {
        http_response_code(400); echo 'Invalid page ID.'; return;
    }
    $oldId = $text('old_id');
    $current = $oldId !== '' ? loadData(POSTS_DIR, $oldId) : null;
    $data = [
        'title' => $text('title'), 'category' => trim($text('category')),
        'status' => $text('status', 'draft'), 'description' => $text('description'),
        'keywords' => $text('keywords'), 'memo' => $text('memo'),
        'ogp_image' => $text('ogp_image'), 'content_md' => $text('content_md'),
        'css' => $text('css'), 'js' => $text('js'), 'is_html' => isset($input['is_html']),
        'wrapper_comp' => $text('wrapper_comp') ?: '_layout',
        'exclude_tracking' => isset($input['tracking_option_present']) ? isset($input['exclude_tracking']) : !empty($current['exclude_tracking']),
        'sort_order' => (int)$text('sort_order'), 'updated_at' => $text('updated_at'),
    ];
    $base = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/mikanBox/admin.php'));
    $settings['_site_base'] = ($base === '/' || $base === '.') ? '/' : rtrim($base, '/') . '/';
    $renderer = new MikanBoxRenderer($settings);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    header('Content-Security-Policy: sandbox allow-scripts allow-forms allow-popups allow-modals');
    echo $renderer->render($id, $data);
}
