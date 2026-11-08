<?php
ob_start();

/**
 * mikanBox Front-end Controller
 */

// The core folder ships as mikanBox/ and may be renamed after installation.
// Set it here when you rename it; the updater rewrites this line with the folder
// it is running from, so the value survives system updates.
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

// Deny data before any routing or real-file bypass, including router requests
// whose SCRIPT_NAME points at the requested static file instead of index.php.
$securityDocRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? ''));
$securityDataRoot = realpath(DATA_DIR);
if ($securityDocRoot && $securityDataRoot && str_starts_with($securityDataRoot, rtrim($securityDocRoot, '/\\') . '/')) {
    $securityDataUrl = '/' . trim(substr($securityDataRoot, strlen($securityDocRoot)), '/');
    $securitySegments = [];
    foreach (explode('/', rawurldecode((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'))) as $segment) {
        if ($segment === '..') array_pop($securitySegments);
        elseif ($segment !== '' && $segment !== '.') $securitySegments[] = $segment;
    }
    $securityRequestPath = '/' . implode('/', $securitySegments);
    if (strcasecmp($securityRequestPath, $securityDataUrl) === 0 || stripos($securityRequestPath, $securityDataUrl . '/') === 0) {
        http_response_code(404); if (ob_get_length()) ob_clean(); echo 'Not Found'; exit;
    }
}

// Old SQLite import backups are private even before the storage repair completes.
$legacyImportPath = rawurldecode((string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
if (preg_match('~/' . preg_quote(basename(CORE_DIR), '~') . '/import(?:/|$)~i', $legacyImportPath)) {
    http_response_code(404); if (ob_get_length()) ob_clean(); echo 'Not Found'; exit;
}

// Administration MCP clean URL. Match before base-path normalization so this
// also works behind a subdirectory and with PHP's built-in test router.
$rawRequestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$adminMcpPattern = '#/' . preg_quote(trim($core_dir, '/'), '#') . '/mcp/?$#';
if (preg_match($adminMcpPattern, $rawRequestPath) === 1) {
    define('MIKANBOX_ADMIN_MCP_ROUTE', true);
    require CORE_DIR . '/mcp.php';
    exit;
}

// 1. サイトベースパスを確定（index.phpの場所から確実に算出）
$basePath = dirname($_SERVER['SCRIPT_NAME']);
if ($basePath === DIRECTORY_SEPARATOR || $basePath === '.') $basePath = '';
$GLOBALS['mikanbox_settings']['_site_base'] = $basePath ? rtrim($basePath, '/') . '/' : '/';

// Public component JavaScript: /component-js/Test.js. The query form also
// works on servers without clean-URL rewriting: index.php?component_js=Test.
$assetPath = $rawRequestPath;
if ($basePath !== '' && str_starts_with($assetPath, rtrim($basePath, '/') . '/')) {
    $assetPath = substr($assetPath, strlen(rtrim($basePath, '/')));
}
$assetPath = ltrim($assetPath, '/');
if (isset($_GET['component_js']) || str_starts_with($assetPath, 'component-js/')) {
    $id = $_GET['component_js'] ?? null;
    if ($id === null && preg_match('~\Acomponent-js/([A-Za-z0-9_-]+)\.js\z~', $assetPath, $assetMatch)) {
        $id = $assetMatch[1];
    }
    header('Content-Type: text/javascript; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-cache');
    if (ob_get_length()) ob_clean();
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
        header('Allow: GET, HEAD');
        http_response_code(405);
        exit;
    }
    $js = mikanBoxComponentJavaScript($id);
    if ($js === null) {
        http_response_code(404);
        exit;
    }
    $etag = '"' . hash('sha256', $js) . '"';
    header('ETag: ' . $etag);
    if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') echo $js;
    exit;
}

// 2. Request Acquisition
$pageId = isset($_GET['page']) ? $_GET['page'] : '';

// 2.5 Cache Control for Dynamic Rendering (ensure latest content is always shown)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if ($pageId === '' || $pageId === 'index.php') {
    $reqUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    $requestPath = rawurldecode((string)$reqUri);
    if ($basePath !== '' && str_starts_with($requestPath, rtrim($basePath, '/') . '/')) {
        $requestPath = substr($requestPath, strlen(rtrim($basePath, '/')));
    }
    $requestPath = trim($requestPath, '/');

    // Defense in depth: when the active data directory is beside index.php,
    // never hand its URL to the web server, even if .htaccess is unavailable.
    // If migration was skipped to preserve a legacy page with the same ID,
    // DATA_DIR still lives under CORE_DIR and the page remains routable.
    $activeDataDirName = defined('DATA_DIR') && str_starts_with(rtrim(DATA_DIR, '/\\'), __DIR__ . '/')
        ? substr(rtrim(DATA_DIR, '/\\'), strlen(__DIR__) + 1) : null;
    if ($activeDataDirName !== null
        && (strcasecmp($requestPath, $activeDataDirName) === 0
            || stripos($requestPath, $activeDataDirName . '/') === 0)) {
        http_response_code(404);
        if (ob_get_length()) ob_clean();
        echo 'Not Found';
        exit;
    }

    // Router compatibility (including PHP's built-in server): existing files
    // such as /mikanBox/public-mcp.php must bypass the front controller.
    $documentRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
    $requestedFile = $documentRoot . '/' . ltrim((string)$reqUri, '/');
    if ($documentRoot !== '' && is_file($requestedFile)) {
        return false;
    }


    // Safety: If the requested path exists as a real file/folder at the root,
    // let the server handle it (required for admin accessing / mikanBox etc.)
    if ($requestPath !== '' && \file_exists(__DIR__ . '/' . $requestPath)) {
        return false;
    }

    $path = $requestPath;
    $path = str_replace('index.php', '', $path);
    $path = trim($path, '/');
    $pageId = ($path !== '') ? $path : 'index';
}

// 2.8 Public read-only MCP endpoint: /mcp
// The administration MCP remains under /mikanBox/mcp and requires an API key.
if ($pageId === 'mcp') {
    define('MIKANBOX_PUBLIC_MCP_ROUTE', true);
    require CORE_DIR . '/public-mcp.php';
    exit;
}

// 3. API endpoint for mikanBox DB pages: /api/{pageId}.json, and the older alias /api/{pageId}.
if (str_starts_with($pageId, 'api/')) {
    $targetId = substr($pageId, 4);
    if (str_ends_with($targetId, '.json') && loadData(POSTS_DIR, $targetId) === null) $targetId = substr($targetId, 0, -5);
    $targetData = loadData(POSTS_DIR, $targetId);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Access-Control-Allow-Origin: *');
    if (!$targetData) {
        http_response_code(404);
        if (ob_get_length()) ob_clean();
        echo json_encode(['error' => 'not found']); exit;
    }
    // Output format "mikanBox DB" and published (a draft stops the API like any other page).
    $jsonOutput = (new MikanBoxRenderer($GLOBALS['mikanbox_settings']))->renderApi($targetId, $targetData);
    if ($jsonOutput === null) {
        http_response_code(403);
        if (ob_get_length()) ob_clean();
        echo json_encode(['error' => 'forbidden']); exit;
    }
    if (ob_get_length()) ob_clean();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') echo $jsonOutput;
    exit;
}

// 4. XML Feeds
if ($pageId === 'sitemap.xml') {
    header('Content-Type: application/xml; charset=utf-8');
    $xml = generateSitemapXml($GLOBALS['mikanbox_settings']);
    if (ob_get_length()) ob_clean();
    echo $xml; exit;
}
if ($pageId === 'rss.xml') {
    header('Content-Type: application/rss+xml; charset=utf-8');
    $xml = generateRssXml($GLOBALS['mikanbox_settings']);
    if (ob_get_length()) ob_clean();
    echo $xml; exit;
}
if ($pageId === 'podcast.xml') {
    header('Content-Type: application/rss+xml; charset=utf-8');
    $xml = generatePodcastXml($GLOBALS['mikanbox_settings']);
    if (ob_get_length()) ob_clean();
    echo $xml; exit;
}

// 4.5 Output formats. Page /id has everything with an extension at /id.<extension>:
// /id.html (HTML in the .html URL style), /id.md (Markdown version), /id.json etc. (data pages).
// A Markdown-only page also answers at /id; a data page answers only at its extension.
$requestedExtension = null;
if (preg_match('~\A(.+)\.(html|md|json|xml|csv|txt|js|css)\z~', $pageId, $extensionMatch)) {
    // A legacy page whose own ID contains a dot keeps its URL.
    $legacyDottedPage = $extensionMatch[2] !== 'html' && loadData(POSTS_DIR, $pageId) !== null && loadData(POSTS_DIR, $extensionMatch[1]) === null;
    if (!$legacyDottedPage) {
        $pageId = $extensionMatch[1];
        $requestedExtension = $extensionMatch[2];
    }
}
$renderer = new MikanBoxRenderer($GLOBALS['mikanbox_settings']);
$pageRecord = loadData(POSTS_DIR, $pageId);
$pageFormat = mikanBoxPageOutputFormat($pageRecord);
if ($requestedExtension === null) {
    $serve = !$pageRecord ? 'html' : ($pageFormat === 'md' ? 'md' : (mikanBoxFormatHasHtml($pageFormat) ? 'html' : 'none'));
} elseif ($requestedExtension === 'html') {
    $serve = ($pageRecord && !mikanBoxFormatHasHtml($pageFormat)) ? 'none' : 'html';
} elseif ($requestedExtension === 'md') {
    $serve = ($pageRecord && mikanBoxFormatHasMarkdown($pageFormat)) ? 'md' : 'none';
} else {
    $serve = ($pageRecord && $pageFormat === $requestedExtension) ? 'data' : 'none';
}
if ($serve === 'md' || $serve === 'data') {
    $body = $serve === 'md' ? $renderer->renderMarkdown($pageId) : $renderer->renderData($pageId);
    if ($body !== null) {
        header('Content-Type: ' . mikanBoxContentTypeForExtension($serve === 'md' ? 'md' : $pageFormat));
        header('X-Content-Type-Options: nosniff');
        if (ob_get_length()) ob_clean();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') echo $body;
        exit;
    }
    $serve = 'none';
}
if ($serve === 'none') {
    http_response_code(404);
    if ($requestedExtension !== null && $requestedExtension !== 'html') {
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        if (ob_get_length()) ob_clean();
        echo 'Not Found';
        exit;
    }
    // An HTML address that does not hold HTML shows the site's 404 page.
    $pageId = '404';
}

// 5. Rendering
$output = $renderer->render($pageId);

// Clean up any accidental output (whitespace, etc.) from logic/includes
if (ob_get_length()) {
    ob_clean();
}
echo $output;
