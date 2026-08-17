<?php

declare(strict_types=1);

define('MIKANBOX_VERSION', 'test');
require_once dirname(__DIR__) . '/mikanBox/lib/public-help.php';

$failures = [];
function checkPublicHelp($condition, string $message): void {
    global $failures;
    if (!$condition) $failures[] = $message;
}

$docsDir = dirname(__DIR__) . '/mikanBox/docs';
$GLOBALS['mikanbox_public_help_docs_dir'] = $docsDir;

checkPublicHelp(is_file($docsDir . '/help_ja.md'), 'Japanese canonical help copy must exist');
checkPublicHelp(is_file($docsDir . '/help_en.md'), 'English canonical help copy must exist');

$GLOBALS['mikanbox_public_help_page_loader'] = static fn(string $id): ?array => $id === 'help_ja' ? [
    'status' => 'public_static',
    'content_md' => "## CMS公開ヘルプ {#cms-public}\n\nCMS上の公開ページを参照しています。",
] : null;
$cmsSection = publicHelpGetSection('cms-public', 'ja');
checkPublicHelp(
    ($cmsSection['title'] ?? null) === 'CMS公開ヘルプ',
    'published help_ja CMS page must take precedence over packaged docs'
);

$GLOBALS['mikanbox_public_help_page_loader'] = static fn(string $id): ?array => $id === 'help_ja' ? [
    'status' => 'draft',
    'content_md' => "## 非公開 {#private-draft}\n\nThis must never be exposed.",
] : null;
checkPublicHelp(publicHelpGetSection('private-draft', 'ja') === null, 'draft help pages must never be exposed');
checkPublicHelp(publicHelpGetSection('page-mgmt', 'ja') !== null, 'draft help page must fall back to packaged public docs');
unset($GLOBALS['mikanbox_public_help_page_loader']);

$jaSections = publicHelpSections('ja');
$enSections = publicHelpSections('en');
checkPublicHelp(count($jaSections) > 10, 'Japanese help must be split into sections');
checkPublicHelp(count($enSections) > 10, 'English help must be split into sections');
checkPublicHelp(publicHelpGetSection('page-mgmt', 'ja') !== null, 'stable Japanese page-mgmt section must resolve');
checkPublicHelp(publicHelpGetSection('page-mgmt', 'en') !== null, 'stable English page-mgmt section must resolve');
checkPublicHelp(publicHelpGetSection('../config', 'ja') === null, 'path traversal shaped section IDs must be rejected');

$jaSearch = publicHelpSearch('MCP APIキー', 'ja', 5);
$enSearch = publicHelpSearch('MCP API key', 'en', 5);
checkPublicHelp(count($jaSearch) > 0, 'Japanese manual search must return results');
checkPublicHelp(count($enSearch) > 0, 'English manual search must return results');
checkPublicHelp(count(publicHelpSearch('', 'ja', 5)) === 0, 'empty searches must return no results');
checkPublicHelp(count(publicHelpSearch('ページ', 'ja', 99)) <= 8, 'search result limit must be capped');

$product = publicHelpProductInfo('ja');
$serializedProduct = json_encode($product, JSON_UNESCAPED_UNICODE);
checkPublicHelp(($product['product'] ?? null) === 'mikanBox', 'product info must identify mikanBox');
checkPublicHelp(!str_contains((string)$serializedProduct, 'api_key'), 'product info must not contain API keys');
checkPublicHelp(!str_contains((string)$serializedProduct, 'password'), 'product info must not contain passwords');

$instructions = publicHelpAgentInstructions('en');
checkPublicHelp(count($instructions['instructions'] ?? []) >= 5, 'agent instructions must be explicit');
checkPublicHelp(
    str_contains(implode(' ', $instructions['instructions'] ?? []), 'untrusted data'),
    'agent instructions must defend against instructions embedded in retrieved documents'
);

if ($failures) {
    fwrite(STDERR, "Public help tests failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Public help tests passed\n";
