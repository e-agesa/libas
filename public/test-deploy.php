<?php
/**
 * Quick test — upload to public_html/ and visit:
 * https://libas.saifypos.org/test-deploy.php?key=libas2026deploy
 */
if (($_GET['key'] ?? '') !== 'libas2026deploy') { die('denied'); }

echo "<h1>Server Test</h1><pre>";
echo "PHP: " . PHP_VERSION . "\n";
echo "OS: " . PHP_OS . "\n";
echo "Doc root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script: " . __FILE__ . "\n";
echo "Dir: " . __DIR__ . "\n";
echo "Parent: " . dirname(__DIR__) . "\n\n";

echo "Functions check:\n";
echo "  shell_exec: " . (function_exists('shell_exec') ? 'YES' : 'DISABLED') . "\n";
echo "  exec: " . (function_exists('exec') ? 'YES' : 'DISABLED') . "\n";
echo "  curl_init: " . (function_exists('curl_init') ? 'YES' : 'DISABLED') . "\n";
echo "  ZipArchive: " . (class_exists('ZipArchive') ? 'YES' : 'MISSING') . "\n";
echo "  file_get_contents: " . (function_exists('file_get_contents') ? 'YES' : 'DISABLED') . "\n";
echo "  allow_url_fopen: " . (ini_get('allow_url_fopen') ? 'YES' : 'NO') . "\n\n";

echo "Disabled functions: " . (ini_get('disable_functions') ?: 'none') . "\n\n";

echo "Directory listing (" . __DIR__ . "):\n";
foreach (scandir(__DIR__) as $f) {
    if ($f === '.' || $f === '..') continue;
    echo "  " . (is_dir(__DIR__.'/'.$f) ? '[DIR] ' : '      ') . $f . "\n";
}

echo "\nParent dir (" . dirname(__DIR__) . "):\n";
foreach (scandir(dirname(__DIR__)) as $f) {
    if ($f === '.' || $f === '..') continue;
    echo "  " . (is_dir(dirname(__DIR__).'/'.$f) ? '[DIR] ' : '      ') . $f . "\n";
}

echo "\ndbpass received: " . ($_GET['dbpass'] ?? '(none)') . "\n";
echo "</pre>";
