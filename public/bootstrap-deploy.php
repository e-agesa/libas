<?php
/**
 * Libas TMS - Bootstrap Deployment (Single File)
 *
 * Upload ONLY this file to the server's document root (public_html/).
 * It will download the full project from GitHub and set everything up.
 *
 * Usage: https://libas.saifypos.org/bootstrap-deploy.php?key=libas2026deploy&dbpass=YOUR_DB_PASSWORD
 *
 * DELETE THIS FILE AFTER SETUP!
 */

$SECRET = 'libas2026deploy';
if (($_GET['key'] ?? '') !== $SECRET) {
    http_response_code(403);
    die('Access denied.');
}

set_time_limit(600);
ini_set('memory_limit', '512M');
header('Content-Type: text/html; charset=utf-8');

echo '<!DOCTYPE html><html><head><title>Libas Bootstrap Deploy</title>
<style>
body{font-family:monospace;background:#1a1a2e;color:#0f0;padding:20px;font-size:14px;}
pre{white-space:pre-wrap;background:#111;padding:10px;border-radius:4px;margin:5px 0;}
.err{color:#f55;}.ok{color:#0f0;}.warn{color:#ff0;}.info{color:#0af;}
h2{color:#fff;border-bottom:1px solid #333;padding-bottom:5px;margin-top:20px;}
</style></head><body>';
echo '<h1 style="color:#fff;">Libas TMS - Bootstrap Deployment</h1>';

function status($msg, $class = 'info') {
    echo "<pre class='$class'>$msg</pre>";
    ob_flush(); flush();
}

function runCmd($cmd) {
    $out = shell_exec($cmd . ' 2>&1');
    return $out ?: '';
}

$dbpass = $_GET['dbpass'] ?? '';
if (!$dbpass) {
    echo "<pre class='err'>ERROR: You must provide dbpass parameter!

Usage: ?key=libas2026deploy&dbpass=YOUR_DATABASE_PASSWORD</pre>";
    die('</body></html>');
}

// Detect paths
$publicDir = __DIR__;
$baseDir = dirname($publicDir);

// Check if this is document root (public_html) or a subdomain folder
$isDocRoot = (basename($publicDir) === 'public_html' || basename($publicDir) === 'htdocs');

if ($isDocRoot) {
    // Script is in public_html directly — project root is one level up, public/ maps to here
    $projectRoot = $baseDir;
    status("Detected: Script is in document root ($publicDir)");
    status("Project root will be: $projectRoot");
} else {
    // Script might be in a subfolder or public/ folder
    $projectRoot = $baseDir;
    status("Document root: $publicDir");
    status("Project root: $projectRoot");
}

status("PHP version: " . PHP_VERSION . " | OS: " . PHP_OS);

// ========== STEP 1: Download from GitHub ==========
echo "<h2>[1/8] Download project from GitHub</h2>";

$repoUrl = 'https://github.com/e-agesa/libas/archive/refs/heads/main.zip';
$zipFile = $projectRoot . '/libas-main.zip';

if (is_dir($projectRoot . '/app') && is_dir($projectRoot . '/routes')) {
    status("Project files already exist — skipping download", 'info');
} else {
    status("Downloading from GitHub...");

    // Try file_get_contents first
    $ctx = stream_context_create(['http' => [
        'timeout' => 120,
        'user_agent' => 'PHP/Libas-Deploy'
    ]]);

    $zipData = @file_get_contents($repoUrl, false, $ctx);

    if ($zipData === false) {
        // Try curl
        status("file_get_contents failed, trying curl...", 'warn');
        $ch = curl_init($repoUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'PHP/Libas-Deploy',
        ]);
        $zipData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || !$zipData) {
            status("CURL failed: HTTP $httpCode - $curlErr", 'err');
            status("Fallback: Please upload the project zip manually to $projectRoot/libas-main.zip and reload this page", 'warn');
            die('</body></html>');
        }
    }

    $bytes = file_put_contents($zipFile, $zipData);
    status("Downloaded: " . round($bytes / 1024 / 1024, 1) . " MB", 'ok');
    unset($zipData); // free memory

    // ========== STEP 2: Extract ==========
    echo "<h2>[2/8] Extract project files</h2>";

    $zip = new ZipArchive();
    if ($zip->open($zipFile) === true) {
        $zip->extractTo($projectRoot);
        $zip->close();
        status("Extracted to $projectRoot", 'ok');

        // GitHub zips extract to a subfolder like "libas-main/"
        $extractedDir = $projectRoot . '/libas-main';
        if (is_dir($extractedDir)) {
            status("Moving files from libas-main/ to project root...");

            // Move all files from extracted dir to project root
            $items = scandir($extractedDir);
            $moved = 0;
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $src = $extractedDir . '/' . $item;
                $dst = $projectRoot . '/' . $item;

                // Don't overwrite this script or existing .env
                if ($item === 'bootstrap-deploy.php') continue;
                if ($item === '.env' && file_exists($dst)) continue;

                // Remove existing destination if it exists (for directories)
                if (is_dir($dst) && is_dir($src)) {
                    // Merge by using shell command
                    shell_exec("cp -rf " . escapeshellarg($src) . "/* " . escapeshellarg($dst) . "/ 2>&1");
                    shell_exec("cp -rf " . escapeshellarg($src) . "/.* " . escapeshellarg($dst) . "/ 2>/dev/null");
                } else {
                    @rename($src, $dst);
                }
                $moved++;
            }

            // Clean up extracted directory
            shell_exec("rm -rf " . escapeshellarg($extractedDir));
            status("Moved $moved items to project root", 'ok');
        }

        // Clean up zip
        @unlink($zipFile);
    } else {
        status("Failed to extract zip!", 'err');
        die('</body></html>');
    }
}

// Verify essential files exist
if (!file_exists($projectRoot . '/artisan')) {
    status("ERROR: artisan not found at $projectRoot/artisan — extraction may have failed", 'err');

    // Check if files are in a subdirectory
    $possibleDirs = glob($projectRoot . '/libas-*/artisan');
    if (!empty($possibleDirs)) {
        $foundDir = dirname($possibleDirs[0]);
        status("Found project in: $foundDir — moving files...", 'warn');
        shell_exec("cp -rf " . escapeshellarg($foundDir) . "/* " . escapeshellarg($projectRoot) . "/ 2>&1");
        shell_exec("cp -rf " . escapeshellarg($foundDir) . "/.* " . escapeshellarg($projectRoot) . "/ 2>/dev/null");
        shell_exec("rm -rf " . escapeshellarg($foundDir));
    } else {
        status("Cannot find Laravel artisan. Check the project structure.", 'err');
        die('</body></html>');
    }
}

status("artisan found — project files OK", 'ok');

// ========== STEP 3: Create .env ==========
echo "<h2>[3/8] Create .env</h2>";
$envFile = $projectRoot . '/.env';
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
    status(".env created with database credentials", 'ok');
} else {
    status(".env already exists — skipping", 'info');
}

// ========== STEP 4: Storage directories ==========
echo "<h2>[4/8] Storage directories</h2>";
$dirs = [
    'storage/app/public',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
    'bootstrap/cache',
];
foreach ($dirs as $d) {
    $path = $projectRoot . '/' . $d;
    if (!is_dir($path)) {
        mkdir($path, 0775, true);
    }
    @chmod($path, 0775);
}
foreach ($dirs as $d) {
    $gi = $projectRoot . '/' . $d . '/.gitignore';
    if (!file_exists($gi)) {
        @file_put_contents($gi, "*\n!.gitignore\n");
    }
}
status("All storage directories ready", 'ok');

// ========== STEP 5: Install Composer ==========
echo "<h2>[5/8] Install Composer dependencies</h2>";
chdir($projectRoot);

// Set HOME and COMPOSER_HOME — required on shared hosting
$homeDir = dirname($projectRoot); // /home/saifyposorg
putenv("HOME=$homeDir");
putenv("COMPOSER_HOME=$projectRoot/.composer");
if (!is_dir($projectRoot . '/.composer')) {
    mkdir($projectRoot . '/.composer', 0775, true);
}

$envPrefix = "HOME=$homeDir COMPOSER_HOME=" . escapeshellarg($projectRoot . '/.composer');

if (!is_dir($projectRoot . '/vendor')) {
    // Try downloading composer.phar directly (faster than installer)
    if (!file_exists($projectRoot . '/composer.phar')) {
        status("Downloading composer.phar...");

        $composerUrl = 'https://getcomposer.org/download/latest-stable/composer.phar';
        $ch = curl_init($composerUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'PHP/Libas-Deploy',
        ]);
        $pharData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $pharData) {
            file_put_contents($projectRoot . '/composer.phar', $pharData);
            chmod($projectRoot . '/composer.phar', 0755);
            status("composer.phar downloaded (" . round(strlen($pharData)/1024) . " KB)", 'ok');
            unset($pharData);
        } else {
            // Fallback: use installer
            status("Direct download failed (HTTP $httpCode), trying installer...", 'warn');
            $installerUrl = 'https://getcomposer.org/installer';
            $installer = @file_get_contents($installerUrl);
            if (!$installer) {
                $ch = curl_init($installerUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                $installer = curl_exec($ch);
                curl_close($ch);
            }
            file_put_contents($projectRoot . '/composer-setup.php', $installer);
            $out = runCmd("cd " . escapeshellarg($projectRoot) . " && $envPrefix php composer-setup.php");
            echo "<pre>$out</pre>";
            @unlink($projectRoot . '/composer-setup.php');
        }
    }

    $pharPath = escapeshellarg($projectRoot . '/composer.phar');
    if (file_exists($projectRoot . '/composer.phar')) {
        status("Running composer install (this may take 2-3 minutes)...");
        ob_flush(); flush();
        $out = runCmd("cd " . escapeshellarg($projectRoot) . " && $envPrefix php $pharPath install --no-dev --optimize-autoloader --no-interaction 2>&1");
        echo "<pre>" . htmlspecialchars(substr($out, -3000)) . "</pre>";
    } else {
        status("composer.phar not found!", 'err');
    }

    if (!is_dir($projectRoot . '/vendor')) {
        status("composer install may have failed — vendor/ still missing", 'err');
    } else {
        status("Composer dependencies installed!", 'ok');
    }
} else {
    status("vendor/ exists — skipping", 'info');
}

// ========== STEP 6: Artisan commands ==========
echo "<h2>[6/8] Generate APP_KEY</h2>";
$out = runCmd("cd " . escapeshellarg($projectRoot) . " && php artisan key:generate --force");
echo "<pre>$out</pre>";

echo "<h2>[7/8] Run Migrations & Seed</h2>";
$out = runCmd("cd " . escapeshellarg($projectRoot) . " && php artisan migrate --force");
echo "<pre>" . htmlspecialchars($out) . "</pre>";

if (strpos($out, 'ERROR') !== false || strpos($out, 'SQLSTATE') !== false) {
    status("Migration had errors — check database credentials and that the database exists in cPanel", 'err');
} else {
    status("Migrations complete", 'ok');

    $out = runCmd("cd " . escapeshellarg($projectRoot) . " && php artisan db:seed --force");
    echo "<pre>" . htmlspecialchars($out) . "</pre>";
}

// ========== STEP 8: Storage link ==========
echo "<h2>[8/8] Storage link & cleanup</h2>";
$link = $publicDir . '/storage';
if (basename($publicDir) === 'public_html') {
    // On cPanel, public_html IS the public dir, but storage link should point to ../storage/app/public
    if (!is_link($link) && !is_dir($link)) {
        $target = $projectRoot . '/storage/app/public';
        @symlink($target, $link);
        if (is_link($link)) {
            status("Storage symlink created", 'ok');
        } else {
            // Try artisan command
            $out = runCmd("cd " . escapeshellarg($projectRoot) . " && php artisan storage:link --force");
            echo "<pre>$out</pre>";
        }
    } else {
        status("Storage link exists", 'info');
    }
} else {
    $out = runCmd("cd " . escapeshellarg($projectRoot) . " && php artisan storage:link --force");
    echo "<pre>$out</pre>";
}

// Cleanup
@unlink($projectRoot . '/composer.phar');
@unlink($projectRoot . '/libas-main.zip');

echo '<hr style="border-color:#333;">';
echo '<h2 style="color:#0f0;">Bootstrap Deployment Complete!</h2>';
echo '<pre class="ok">
Test the site:  <a href="https://libas.saifypos.org" style="color:#0af;">https://libas.saifypos.org</a>
Admin login:    <a href="https://libas.saifypos.org/login" style="color:#0af;">https://libas.saifypos.org/login</a>
                admin@libas.test / password

IMPORTANT NEXT STEPS:
1. Test the site works
2. DELETE this file (bootstrap-deploy.php) immediately!
3. Also delete setup.php and deploy.php if they exist
4. Set APP_DEBUG=false in .env once stable
</pre>';
echo '</body></html>';
