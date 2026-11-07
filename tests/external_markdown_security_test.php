<?php
$root = dirname(__DIR__);
$lib = is_file($root . '/lib/external-markdown.php') ? $root . '/lib' : $root . '/mikanBox/lib';
define('MEDIA_DIR', sys_get_temp_dir() . '/mikanbox-external-test-no-media');
require $lib . '/functions-common.php';
function checkExternal($ok, $message) { if (!$ok) throw new RuntimeException($message); }
foreach (['127.0.0.1','10.0.0.1','169.254.169.254','100.64.0.1','224.0.0.1','0.0.0.0','192.168.1.2','198.18.0.1','::1','::ffff:127.0.0.1','fc00::1','fe80::1','2001:db8::1','2002:7f00:1::'] as $ip) checkExternal(!mikanBoxExternalPublicIp($ip), 'Must reject ' . $ip);
foreach (['1.1.1.1','8.8.8.8','2606:4700:4700::1111'] as $ip) checkExternal(mikanBoxExternalPublicIp($ip), 'Public address rejected: ' . $ip);
foreach (['file:///etc/passwd','http://127.0.0.1/','http://[::1]/','http://user:pass@1.1.1.1/','http://localhost/'] as $url) checkExternal(mikanBoxExternalTarget($url) === null, 'Unsafe target accepted');
checkExternal(!mikanBoxExternalMarkdownEnabled(['external_markdown_enabled'=>false]), 'Explicit OFF');
checkExternal(mikanBoxExternalMarkdownEnabled(['external_markdown_enabled'=>true]), 'Explicit ON');
checkExternal(!mikanBoxExternalMarkdownEnabled([]), 'New site defaults OFF');
$source = '<iframe srcdoc="&lt;script&gt;parent.pwned=1&lt;/script&gt;"></iframe>' . "\n" . '<svg onload="alert(1)"></svg>' . "\n" . '{{POST_MD:private}}' . "\n" . '[click](javascript:alert(1))';
$html = (new MikanBoxMarkdown())->text(mikanBoxExternalMarkdownText($source));
checkExternal(!preg_match('/<(?:iframe|script|svg)\b/i', $html), 'External executable HTML survived');
checkExternal(!str_contains($html, '{{POST_MD'), 'External text became a template');
checkExternal(!str_contains($html, 'href="javascript:'), 'Unsafe Markdown URL');
checkExternal(str_contains((new MikanBoxMarkdown())->text(mikanBoxExternalMarkdownText('# Heading')), '<h1>Heading</h1>'), 'Markdown still works');
checkExternal(mikanBoxFetchExternalMarkdown('http://127.0.0.1/') === null, 'Internal fetch must fail before connecting');
echo "External Markdown address, content and setting checks passed.\n";
