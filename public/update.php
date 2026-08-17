<?php
/**
 * Libas TMS — one-shot updater.
 *
 * Pulls the current `main` branch from GitHub and refreshes the application
 * files in place, then runs migrations and clears caches.
 *
 * HOW TO USE
 *   1. Upload this single file into the site's document root (public_html/).
 *   2. Visit: https://libasulanwar.com/update.php?key=libas2026deploy
 *   3. When it reports SUCCESS, DELETE this file (there is a button/link at the
 *      end, or remove it in cPanel File Manager).
 *
 * SAFETY
 *   - Never touches .env, storage/, vendor/, or public/report/_review/
 *     (your config, uploads, sessions, installed packages and client comments).
 *   - Only copies files IN; it never deletes anything that is not being replaced.
 *   - Requires the secret key above to run at all.
 */

$SECRET = 'libas2026deploy';

if (($_GET['key'] ?? '') !== $SECRET) {
    http_response_code(403);
    die('Access denied.');
}

set_time_limit(900);
ini_set('memory_limit', '512M');
header('Content-Type: text/html; charset=utf-8');

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Libas — Update</title><style>
body{font-family:ui-monospace,Consolas,monospace;background:#0E1418;color:#d7d2c8;padding:24px;line-height:1.6;font-size:14px}
pre{white-space:pre-wrap;background:#141C21;padding:10px 14px;border-radius:4px;margin:6px 0;border-left:3px solid #333}
.ok{border-left-color:#66C08A;color:#9fe0b8}.err{border-left-color:#E27070;color:#f0a5a5}
.warn{border-left-color:#D8A64B;color:#e8c88a}.info{border-left-color:#CBA25C}
h1,h2{color:#fff}h2{border-bottom:1px solid #2a343a;padding-bottom:6px;margin-top:26px;font-size:16px}
a{color:#CBA25C}
</style></head><body><h1>Libas TMS — Update</h1>';

function say($msg, $class = 'info') {
    echo "<pre class='" . $class . "'>" . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "</pre>";
    @ob_flush(); @flush();
}

/* ---------- 1. Work out the layout ---------- */
echo '<h2>[1/6] Locate the installation</h2>';

$publicDir = __DIR__;
$projectRoot = null;

// Most reliable source of truth: the live index.php states where the framework
// lives. On this host the app root is a SIBLING of the document root
// (public_html/index.php requires ../libas/vendor/autoload.php), so guessing
// "one level up" would be wrong.
$indexFile = $publicDir . '/index.php';
if (is_file($indexFile)) {
    $indexSrc = (string) file_get_contents($indexFile);
    if (preg_match('#__DIR__\s*\.\s*[\'"]([^\'"]*?)/vendor/autoload\.php[\'"]#', $indexSrc, $m)) {
        $candidate = realpath($publicDir . $m[1]);
        if ($candidate && is_file($candidate . '/artisan') && is_dir($candidate . '/app')) {
            $projectRoot = $candidate;
            say('Detected app root from index.php: ' . $m[1], 'ok');
        }
    }
}

// Fallbacks: the conventional layouts.
if (!$projectRoot) {
    $candidates = [dirname($publicDir), $publicDir, dirname(dirname($publicDir))];
    foreach (glob(dirname($publicDir) . '/*', GLOB_ONLYDIR) ?: [] as $sibling) {
        $candidates[] = $sibling; // e.g. ~/libas next to ~/public_html
    }
    foreach ($candidates as $candidate) {
        if ($candidate && is_file($candidate . '/artisan') && is_dir($candidate . '/app')) {
            $projectRoot = $candidate;
            break;
        }
    }
}

if (!$projectRoot) {
    say('Could not find the Laravel installation (no artisan/app next to this file).', 'err');
    say('Upload this file into the folder that contains index.php (public_html/) and try again.', 'warn');
    die('</body></html>');
}

// If the script sits in the project root itself, public/ is a real subfolder.
$publicTarget = ($projectRoot === $publicDir) ? $publicDir . '/public' : $publicDir;

say("Document root : {$publicDir}");
say("Project root  : {$projectRoot}");
say("Public target : {$publicTarget}");
say('PHP ' . PHP_VERSION);

/* ---------- 2. Download ---------- */
echo '<h2>[2/6] Download latest code from GitHub</h2>';

$repoUrl = 'https://github.com/e-agesa/libas/archive/refs/heads/main.zip';
$tmpZip  = $projectRoot . '/_update-' . date('YmdHis') . '.zip';

$zipData = false;
if (function_exists('curl_init')) {
    $ch = curl_init($repoUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 300,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Libas-Updater',
    ]);
    $zipData = curl_exec($ch);
    $code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err     = curl_error($ch);
    curl_close($ch);
    if ($code !== 200 || !$zipData) {
        say("cURL failed (HTTP {$code}) {$err}", 'warn');
        $zipData = false;
    }
}
if ($zipData === false) {
    $ctx = stream_context_create(['http' => ['timeout' => 300, 'user_agent' => 'Libas-Updater']]);
    $zipData = @file_get_contents($repoUrl, false, $ctx);
}
if ($zipData === false) {
    say('Could not download the code from GitHub. Check the server has outbound internet access.', 'err');
    die('</body></html>');
}

file_put_contents($tmpZip, $zipData);
say('Downloaded ' . round(strlen($zipData) / 1048576, 1) . ' MB', 'ok');
unset($zipData);

/* ---------- 3. Extract ---------- */
echo '<h2>[3/6] Extract</h2>';

if (!class_exists('ZipArchive')) {
    @unlink($tmpZip);
    say('PHP ZipArchive extension is not available on this server.', 'err');
    die('</body></html>');
}

$extractDir = $projectRoot . '/_update-tmp';
if (is_dir($extractDir)) {
    rrmdir($extractDir);
}
@mkdir($extractDir, 0755, true);

$zip = new ZipArchive();
if ($zip->open($tmpZip) !== true) {
    @unlink($tmpZip);
    say('Could not open the downloaded zip.', 'err');
    die('</body></html>');
}
$zip->extractTo($extractDir);
$zip->close();
@unlink($tmpZip);

$src = $extractDir . '/libas-main';
if (!is_dir($src)) {
    // fall back to whatever single folder the archive produced
    foreach (glob($extractDir . '/*', GLOB_ONLYDIR) as $dir) { $src = $dir; break; }
}
if (!is_dir($src . '/app')) {
    rrmdir($extractDir);
    say('Unexpected archive layout — app/ not found.', 'err');
    die('</body></html>');
}
say('Extracted to ' . $src, 'ok');

/* ---------- 4. Copy files in ---------- */
echo '<h2>[4/6] Update application files</h2>';

$copied = 0;
$skipped = [];

// Deliberately surgical: only the directories this release actually changes.
// config/, composer.*, artisan and vendor/ are left alone — dependencies have
// not changed, so replacing them could only ever break a working server.
$rootPaths = ['app', 'bootstrap', 'database', 'resources', 'routes'];

foreach ($rootPaths as $rel) {
    $from = $src . '/' . $rel;
    $to   = $projectRoot . '/' . $rel;
    if (!file_exists($from)) { $skipped[] = $rel; continue; }
    $copied += copy_path($from, $to);
    say("updated  {$rel}");
}

// public/ contents go to the document root (public_html), preserving _review data.
$fromPublic = $src . '/public';
if (is_dir($fromPublic)) {
    foreach (scandir($fromPublic) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        // Never ship the deploy helpers. Also never touch the live server's
        // entry point or rewrite rules — they are host-specific and working,
        // and nothing in this release changes them.
        $keepServerCopy = [
            'index.php', '.htaccess', 'favicon.ico', 'robots.txt',
            'update.php', 'bootstrap-deploy.php', 'deploy.php', 'setup.php',
            'test-deploy.php', 'test.php', 'fix-env.php', 'fix-vendor.php',
        ];
        if (in_array($entry, $keepServerCopy, true)) {
            continue;
        }
        $copied += copy_path($fromPublic . '/' . $entry, $publicTarget . '/' . $entry, ['_review']);
        say("updated  public/{$entry}");
    }
}

say("{$copied} files written", 'ok');
if ($skipped) {
    say('not present in archive (skipped): ' . implode(', ', $skipped), 'warn');
}

rrmdir($extractDir);

/* ---------- 5. Migrate + clear caches ---------- */
echo '<h2>[5/6] Migrations &amp; caches</h2>';

if (function_exists('opcache_reset')) {
    @opcache_reset();
    say('opcache reset');
}

$artisanRan = false;

// Preferred: run artisan in-process (works even where shell_exec is disabled).
if (is_file($projectRoot . '/vendor/autoload.php')) {
    try {
        require $projectRoot . '/vendor/autoload.php';
        $app = require $projectRoot . '/bootstrap/app.php';
        $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();

        foreach ([
            ['migrate', ['--force' => true]],
            ['config:clear', []],
            ['route:clear', []],
            ['view:clear', []],
            ['storage:link', []],
        ] as [$cmd, $params]) {
            try {
                $kernel->call($cmd, $params);
                $out = trim($kernel->output());
                say("artisan {$cmd}\n" . ($out !== '' ? $out : 'done'), 'ok');
            } catch (Throwable $e) {
                say("artisan {$cmd} — " . $e->getMessage(), 'warn');
            }
        }
        $artisanRan = true;
    } catch (Throwable $e) {
        say('In-process artisan failed: ' . $e->getMessage(), 'warn');
    }
} else {
    say('vendor/autoload.php not found — packages are not installed on the server.', 'err');
}

// Fallback: shell.
if (!$artisanRan && function_exists('shell_exec')) {
    $root = escapeshellarg($projectRoot);
    foreach (['migrate --force', 'config:clear', 'route:clear', 'view:clear'] as $cmd) {
        $out = @shell_exec("cd {$root} && php artisan {$cmd} 2>&1");
        say("artisan {$cmd}\n" . trim((string) $out));
    }
    $artisanRan = true;
}

if (!$artisanRan) {
    say('Could not run migrations automatically. Run "php artisan migrate --force" from cPanel Terminal.', 'warn');
}

/* ---------- 6. Verify ---------- */
echo '<h2>[6/6] Verify</h2>';

$checks = [
    'Invoice fix (new interface bundle)' => is_file($publicTarget . '/build/manifest.json')
        && strpos((string) file_get_contents($publicTarget . '/build/manifest.json'), 'Create-') !== false,
    'Client report page'                 => is_file($publicTarget . '/report/status-report.html'),
    'Report body (outside web root)'     => is_file($projectRoot . '/resources/reports/review-response.html'),
    'Ridhaa data migration present'      => is_file($projectRoot . '/database/migrations/2026_08_16_000001_seed_ridhaa_fabric.php'),
];

$allOk = true;
foreach ($checks as $label => $ok) {
    say(($ok ? '[OK]   ' : '[MISS] ') . $label, $ok ? 'ok' : 'err');
    $allOk = $allOk && $ok;
}

echo '<h2>' . ($allOk ? 'SUCCESS' : 'FINISHED WITH WARNINGS') . '</h2>';
say("Check the invoice screen : https://libasulanwar.com/invoices/create", 'ok');
say("Check the client report  : https://libasulanwar.com/report/status-report.html  (password: libas2026)", 'ok');
echo '<pre class="warn">NOW DELETE THIS FILE — <a href="?key=' . htmlspecialchars($SECRET, ENT_QUOTES, 'UTF-8') . '&amp;selfdestruct=1">click here to remove update.php</a></pre>';

if (($_GET['selfdestruct'] ?? '') === '1') {
    @unlink(__FILE__);
    say(is_file(__FILE__) ? 'Could not delete update.php — remove it in File Manager.' : 'update.php deleted.', is_file(__FILE__) ? 'err' : 'ok');
}

echo '</body></html>';

/* ---------- helpers ---------- */

function copy_path($from, $to, array $preserve = []) {
    $count = 0;
    if (is_file($from)) {
        @mkdir(dirname($to), 0755, true);
        if (@copy($from, $to)) $count++;
        return $count;
    }
    if (!is_dir($from)) return 0;

    @mkdir($to, 0755, true);
    foreach (scandir($from) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if (in_array($entry, $preserve, true)) continue; // keep live data (e.g. _review)
        $count += copy_path($from . '/' . $entry, $to . '/' . $entry, $preserve);
    }
    return $count;
}

function rrmdir($dir) {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $dir . '/' . $entry;
        is_dir($path) ? rrmdir($path) : @unlink($path);
    }
    @rmdir($dir);
}
