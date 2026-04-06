<?php
/**
 * mikanBox Front-end Controller
 */

$core_dir = 'mikanBox';
foreach ([$core_dir, 'admin', 'system'] as $dir) {
    if (file_exists(__DIR__ . '/' . $dir . '/config.php')) {
        $core_dir = $dir;
        break;
    }
}
define('CORE_DIR', __DIR__ . '/' . $core_dir);

require_once CORE_DIR . '/config.php';
require_once CORE_DIR . '/lib/functions.php';
require_once CORE_DIR . '/lib/renderer.php';

// 1. サイトベースパスを確定（index.phpの場所から確実に算出）
$basePath = dirname($_SERVER['SCRIPT_NAME']);
if ($basePath === DIRECTORY_SEPARATOR || $basePath === '.') $basePath = '';
$GLOBALS['mikanbox_settings']['_site_base'] = $basePath ? rtrim($basePath, '/') . '/' : '/';

// 2. Request Acquisition
$pageId = isset($_GET['page']) ? $_GET['page'] : '';

// 2.5 Cache Control for Dynamic Rendering (ensure latest content is always shown)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if ($pageId === '' || $pageId === 'index.php') {
    $reqUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    // Safety: If the requested path exists as a real file/folder at the root,
    // let the server handle it (required for admin accessing / mikanBox etc.)
    if ($reqUri !== '/' && \file_exists(__DIR__ . $reqUri)) {
        return false;
    }

    $path = str_replace($basePath, '', $reqUri);
    $path = str_replace('index.php', '', $path);
    $path = trim($path, '/');
    $pageId = ($path !== '') ? $path : 'index';

    // Strip .html extension if present to match internal data IDs
    if (str_ends_with($pageId, '.html')) {
        $pageId = substr($pageId, 0, -5);
    }
}

// 3. API endpoint: /api/{pageId}
if (str_starts_with($pageId, 'api/')) {
    $targetId = substr($pageId, 4);
    $targetData = loadData(POSTS_DIR, $targetId);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    if (!$targetData) {
        http_response_code(404);
        echo json_encode(['error' => 'not found']); exit;
    }
    if (($targetData['status'] ?? '') !== 'db') {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']); exit;
    }
    $renderer = new MikanBoxRenderer($GLOBALS['mikanbox_settings']);
    echo json_encode($renderer->getPageDataBlocks($targetId), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// 4. XML Feeds
if ($pageId === 'sitemap.xml') {
    header('Content-Type: application/xml; charset=utf-8');
    echo generateSitemapXml($GLOBALS['mikanbox_settings']); exit;
}
if ($pageId === 'rss.xml') {
    header('Content-Type: application/rss+xml; charset=utf-8');
    echo generateRssXml($GLOBALS['mikanbox_settings']); exit;
}
if ($pageId === 'podcast.xml') {
    header('Content-Type: application/rss+xml; charset=utf-8');
    echo generatePodcastXml($GLOBALS['mikanbox_settings']); exit;
}

// 5. Rendering
$renderer = new MikanBoxRenderer($GLOBALS['mikanbox_settings']);
echo $renderer->render($pageId);
