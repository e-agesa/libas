<?php
/**
 * Libas TMS - Web Deployment Script
 * Access: https://libas.saifypos.org/deploy.php
 * DELETE THIS FILE AFTER DEPLOYMENT!
 */

// Security: simple token check
$SECRET = 'libas2026deploy';
if (($_GET['key'] ?? '') !== $SECRET) {
    http_response_code(403);
    die('Access denied. Use ?key=libas2026deploy');
}

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><title>Libas Deploy</title>
<style>body{font-family:monospace;background:#1a1a2e;color:#0f0;padding:20px;font-size:14px;}
pre{white-space:pre-wrap;}.err{color:#f55;}.ok{color:#0f0;}.warn{color:#ff0;}.info{color:#0af;}
h2{color:#fff;border-bottom:1px solid #333;padding-bottom:5px;}</style></head><body>';
echo '<h1>Libas TMS - Deployment</h1>';

$baseDir = dirname(__DIR__); // goes up from public/ to project root
chdir($baseDir);

function run($cmd, $label = '') {
    if ($label) echo "<h2>$label</h2>";
    echo "<pre>";
    $output = [];
    $code = 0;
    exec($cmd . ' 2>&1', $output, $code);
    $text = implode("\n", $output);
    if ($code === 0) {
        echo "<span class='ok'>$text</span>";
    } else {
        echo "<span class='err'>$text</span>";
    }
    echo "</pre>";
    return $code === 0;
}

// ======== CONFIG ========
$config = [
    'app_url'  => 'https://libas.saifypos.org',
    'db_name'  => 'saifyposorg_libas',
    'db_user'  => 'saifyposorg_libas',
    'db_pass'  => 'CHANGE_THIS',  // <-- SET YOUR CPANEL DB PASSWORD
    'db_host'  => '127.0.0.1',
    'db_port'  => '3306',
];

// Check if password was set
if ($config['db_pass'] === 'CHANGE_THIS') {
    echo '<pre class="warn">WARNING: You need to set the DB password first!

1. Go to cPanel File Manager
2. Navigate to libas.saifypos.org/public/deploy.php
3. Edit the file and change CHANGE_THIS on line ~48 to your actual DB password
4. Save and reload this page</pre>';
    echo '<p class="info">You can also pass it as URL param: ?key=libas2026deploy&dbpass=YOUR_PASSWORD</p>';

    if (isset($_GET['dbpass']) && $_GET['dbpass'] !== '') {
        $config['db_pass'] = $_GET['dbpass'];
        echo '<pre class="ok">Using password from URL parameter</pre>';
    } else {
        die('</body></html>');
    }
}

echo "<pre class='info'>Project root: $baseDir\nPHP: " . PHP_VERSION . "\nOS: " . PHP_OS . "</pre>";

// Step 1: Create .env
echo "<h2>[1/7] Environment File</h2>";
$envFile = $baseDir . '/.env';
if (!file_exists($envFile)) {
    $env = "APP_NAME=\"Libas TMS\"
APP_ENV=production
APP_KEY=
APP_DEBUG=true
APP_URL={$config['app_url']}

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=single
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST={$config['db_host']}
DB_PORT={$config['db_port']}
DB_DATABASE={$config['db_name']}
DB_USERNAME={$config['db_user']}
DB_PASSWORD={$config['db_pass']}

SESSION_DRIVER=file
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync

CACHE_STORE=file

MAIL_MAILER=log
MAIL_FROM_ADDRESS=\"noreply@saifypos.org\"
MAIL_FROM_NAME=\"\${APP_NAME}\"

VITE_APP_NAME=\"\${APP_NAME}\"
";
    file_put_contents($envFile, $env);
    echo "<pre class='ok'>.env created successfully</pre>";
} else {
    echo "<pre class='info'>.env already exists - skipping</pre>";
}

// Step 2: Generate APP_KEY
run('php artisan key:generate --force', '[2/7] Generate APP_KEY');

// Step 3: Directory permissions
echo "<h2>[3/7] Directory Permissions</h2>";
$dirs = ['storage', 'storage/logs', 'storage/framework', 'storage/framework/cache',
         'storage/framework/sessions', 'storage/framework/views', 'bootstrap/cache'];
foreach ($dirs as $dir) {
    $full = $baseDir . '/' . $dir;
    if (!is_dir($full)) {
        mkdir($full, 0775, true);
        echo "<pre class='ok'>Created: $dir</pre>";
    }
    @chmod($full, 0775);
}
// Create .gitignore in storage dirs if missing
foreach (['storage/logs', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views'] as $d) {
    $gf = $baseDir . '/' . $d . '/.gitignore';
    if (!file_exists($gf)) {
        @mkdir(dirname($gf), 0775, true);
        file_put_contents($gf, "*\n!.gitignore\n");
    }
}
echo "<pre class='ok'>Permissions set</pre>";

// Step 4: Run migrations
run('php artisan migrate --force', '[4/7] Run Migrations');

// Step 5: Seed database
run('php artisan db:seed --force', '[5/7] Seed Database');

// Step 6: Storage link
run('php artisan storage:link --force', '[6/7] Storage Link');

// Step 7: Cache
echo "<h2>[7/7] Cache Config</h2>";
run('php artisan config:clear');
run('php artisan route:clear');
run('php artisan view:clear');

echo '<hr>';
echo '<h2 style="color:#0f0;">Deployment Complete!</h2>';
echo '<pre class="ok">
Site:  ' . $config['app_url'] . '
Login: admin@libas.test / password

NEXT STEPS:
1. Test the site: <a href="' . $config['app_url'] . '" style="color:#0af;">' . $config['app_url'] . '</a>
2. Login: <a href="' . $config['app_url'] . '/login" style="color:#0af;">' . $config['app_url'] . '/login</a>
3. Change admin password after login
4. Set APP_DEBUG=false in .env once everything works
5. Run config:cache and route:cache once stable
6. DELETE THIS FILE (deploy.php) for security!
</pre>';
echo '</body></html>';
