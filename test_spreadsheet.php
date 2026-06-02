<?php
// test_spreadsheet.php
$path = __DIR__ . '/PhpSpreadsheet/vendor/autoload.php';
echo "Path: $path<br>";
echo "Exists: " . (file_exists($path) ? 'YES' : 'NO') . "<br>";

if (file_exists($path)) {
    require_once $path;
    echo "PhpSpreadsheet loaded successfully!";
} else {
    echo "Check if vendor folder exists inside PhpSpreadsheet folder";
}
?>