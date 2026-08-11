<?php
// Visit: regnocloud.com/courtly/debug.php
// DELETE after debugging!

echo '<h2>PHP Version</h2>';
echo 'You have PHP <strong>' . phpversion() . '</strong><br>';
echo 'Laravel 11 needs PHP 8.2+. You need: <strong>' . (version_compare(phpversion(), '8.2', '>=') ? '✅ OK' : '❌ UPGRADE PHP') . '</strong>';

echo '<h2>Required Extensions</h2>';
$required = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'fileinfo', 'ctype', 'tokenizer', 'xml', 'curl', 'json'];
foreach ($required as $ext) {
    $ok = extension_loaded($ext);
    echo ($ok ? '✅' : '❌') . " $ext<br>";
}

echo '<h2>Writable Directories</h2>';
$dirs = [
    '../storage' => 'storage/',
    '../storage/framework/views' => 'storage/framework/views/',
    '../storage/framework/cache' => 'storage/framework/cache/',
    '../storage/framework/sessions' => 'storage/framework/sessions/',
    '../storage/logs' => 'storage/logs/',
    '../bootstrap/cache' => 'bootstrap/cache/',
];
foreach ($dirs as $path => $label) {
    $ok = is_writable($path);
    echo ($ok ? '✅' : '❌') . " $label " . ($ok ? '' : '(chmod 775)') . "<br>";
}

echo '<h2>Loaded config files</h2>';
echo file_exists('../.env') ? '✅ .env exists<br>' : '❌ .env missing<br>';
echo file_exists('../vendor/autoload.php') ? '✅ vendor/autoload.php exists<br>' : '❌ vendor/ missing<br>';
