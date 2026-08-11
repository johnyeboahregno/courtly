<?php
// Fix permissions
$dirs = ['storage', 'storage/framework', 'storage/framework/views', 'storage/framework/cache', 'storage/framework/sessions', 'storage/logs', 'bootstrap/cache'];
foreach ($dirs as $d) {
    $ok = @chmod(__DIR__.'/'.$d, 0777);
    echo $d . ': ' . ($ok ? 'OK' : 'FAILED') . "<br>";
}
echo "Done. Now delete this file.";
