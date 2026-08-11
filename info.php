<?php
echo 'PHP: ' . phpversion() . '<br><br>';

$checks = [
    '../storage/' => 'storage/',
    '../storage/framework/cache/data/' => 'storage/framework/cache/',
    '../storage/framework/views/' => 'storage/framework/views/',
    '../storage/logs/' => 'storage/logs/',
    '../bootstrap/cache/' => 'bootstrap/cache/',
    '../vendor/autoload.php' => 'vendor/autoload.php',
    '../.env' => '.env',
];

foreach ($checks as $path => $label) {
    $exists = file_exists($path);
    $writable = $exists && is_writable($path);
    $status = !$exists ? 'MISSING' : ($writable ? 'OK' : 'NOT WRITABLE');
    $color = $status === 'OK' ? 'green' : 'red';
    echo "<span style='color:$color'>$label: $status</span><br>";
}

echo '<br>If anything is NOT WRITABLE, use your hosting control panel file manager<br>';
echo 'to set permissions (chmod) to 775 or 777 on those folders.<br>';
