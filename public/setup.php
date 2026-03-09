<?php
/**
 * Libas TMS - Initial Setup (no dependencies needed)
 * This script installs composer deps, creates .env, and runs artisan commands.
 * Access: https://libas.saifypos.org/setup.php?key=libas2026deploy
 * DELETE THIS FILE AFTER SETUP!
 */

$SECRET = 'libas2026deploy';
if (($_GET['key'] ?? '') !== $SECRET) {
    http_response_code(403);
    die('Access denied. Use ?key=libas2026deploy');
}

set_time_limit(300);
header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><title>Libas Setup</title>
<style>body{font-family:monospace;background:#1a1a2e;color:#0f0;padding:20px;font-size:14px;}
pre{white-space:pre-wrap;background:#111;padding:10px;border-radius:4px;margin:5px 0;}
.err{color:#f55;}.ok{color:#0f0;}.warn{color:#ff0;}.info{color:#0af;}
h2{color:#fff;border-bottom:1px solid #333;padding-bottom:5px;margin-top:20px;}</style></head><body>';
echo '<h1 style="color:#fff;">Libas TMS - Server Setup</h1>';
ob_flush(); flush();

$baseDir = dirname(__DIR__);
$publicDir = __DIR__;
chdir($baseDir);

$dbpass = $_GET['dbpass'] ?? 'CHANGE_THIS';

echo "<pre class='info'>Project root: $baseDir\nPHP version: " . PHP_VERSION . "\nOS: " . PHP_OS . "</pre>";
ob_flush(); flush();

// ========== STEP 1: Create .env ==========
echo "<h2>[1/6] Create .env</h2>";
$envFile = $baseDir . '/.env';
if (!file_exists($envFile)) {
    $env = 'APP_NAME="Libas TMS"
APP_ENV=production
APP_KEY=
APP_DEBUG=true
APP_URL=https://libas.saifypos.org

LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=saifyposorg_libas
DB_USERNAME=saifyposorg_libas
DB_PASSWORD=' . $dbpass . '

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
CACHE_STORE=file

MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@saifypos.org"
MAIL_FROM_NAME="${APP_NAME}"

VITE_APP_NAME="${APP_NAME}"
';
    file_put_contents($envFile, $env);
    echo "<pre class='ok'>.env created</pre>";
} else {
    echo "<pre class='info'>.env exists - skipping</pre>";
}
ob_flush(); flush();

// ========== STEP 2: Create storage dirs ==========
echo "<h2>[2/6] Storage directories</h2>";
$dirs = [
    'storage/app/public',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache',
];
foreach ($dirs as $d) {
    $path = $baseDir . '/' . $d;
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
        echo "<pre class='ok'>Created $d</pre>";
    }
    @chmod($path, 0775);
}
// gitignore files
foreach ($dirs as $d) {
    $gi = $baseDir . '/' . $d . '/.gitignore';
    if (!file_exists($gi)) {
        @file_put_contents($gi, "*\n!.gitignore\n");
    }
}
echo "<pre class='ok'>All storage dirs ready</pre>";
ob_flush(); flush();

// ========== STEP 3: Install Composer ==========
echo "<h2>[3/6] Install Composer dependencies</h2>";
if (!is_dir($baseDir . '/vendor')) {
    // Download composer
    if (!file_exists($baseDir . '/composer.phar')) {
        echo "<pre class='info'>Downloading composer.phar...</pre>";
        ob_flush(); flush();
        $composerInstaller = file_get_contents('https://getcomposer.org/installer');
        file_put_contents($baseDir . '/composer-setup.php', $composerInstaller);
        $out = shell_exec("cd $baseDir && php composer-setup.php 2>&1");
        echo "<pre>$out</pre>";
        @unlink($baseDir . '/composer-setup.php');
    }

    echo "<pre class='info'>Running composer install (this may take 1-2 minutes)...</pre>";
    ob_flush(); flush();
    $out = shell_exec("cd $baseDir && php composer.phar install --no-dev --optimize-autoloader --no-interaction 2>&1");
    echo "<pre>$out</pre>";
} else {
    echo "<pre class='info'>vendor/ exists - skipping</pre>";
}
ob_flush(); flush();

// ========== STEP 4: Artisan commands ==========
echo "<h2>[4/6] Generate APP_KEY</h2>";
$out = shell_exec("cd $baseDir && php artisan key:generate --force 2>&1");
echo "<pre>$out</pre>";
ob_flush(); flush();

echo "<h2>[5/6] Run Migrations & Seed</h2>";
$out = shell_exec("cd $baseDir && php artisan migrate --force 2>&1");
echo "<pre>$out</pre>";
ob_flush(); flush();

$out = shell_exec("cd $baseDir && php artisan db:seed --force 2>&1");
echo "<pre>$out</pre>";
ob_flush(); flush();

// ========== STEP 6: Storage link ==========
echo "<h2>[6/6] Storage link</h2>";
$target = $baseDir . '/storage/app/public';
$link = $publicDir . '/storage';
if (!is_link($link) && !is_dir($link)) {
    $out = shell_exec("cd $baseDir && php artisan storage:link --force 2>&1");
    echo "<pre>$out</pre>";
} else {
    echo "<pre class='info'>Storage link exists</pre>";
}

// Cleanup
@unlink($baseDir . '/composer.phar');

echo '<hr style="border-color:#333;">';
echo '<h2 style="color:#0f0;">Setup Complete!</h2>';
echo '<pre class="ok">
Test the site:  <a href="https://libas.saifypos.org" style="color:#0af;">https://libas.saifypos.org</a>
Admin login:    <a href="https://libas.saifypos.org/login" style="color:#0af;">https://libas.saifypos.org/login</a>
                admin@libas.test / password

NEXT STEPS:
1. Test the site works
2. Delete this file (setup.php) for security
3. Delete deploy.php too
4. Set APP_DEBUG=false in .env when stable
</pre>';
echo '</body></html>';
