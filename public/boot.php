<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
echo "PHP: " . phpversion() . "<br>";
echo "Step 1: Loading autoload...<br>";
require __DIR__.'/vendor/autoload.php';
echo "Step 2: Loading bootstrap...<br>";
try {
    $app = require_once __DIR__.'/bootstrap/app.php';
    echo "Step 3: Booted!<br>";
} catch (\Throwable $e) {
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "<br>";
}