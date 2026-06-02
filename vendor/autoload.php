<?php
// vendor/autoload.php
// Manual autoloader for PhpSpreadsheet

// Define the base directory for PhpSpreadsheet
$phpspreadsheet_base = __DIR__ . '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/';

// Manual autoload function
spl_autoload_register(function ($class) use ($phpspreadsheet_base) {
    // Project-specific namespace prefix
    $prefix = 'PhpOffice\\PhpSpreadsheet\\';
    
    // Does the class use the namespace prefix?
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No, move to the next registered autoloader
        return;
    }
    
    // Get the relative class name
    $relative_class = substr($class, $len);
    
    // Replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    $file = $phpspreadsheet_base . str_replace('\\', '/', $relative_class) . '.php';
    
    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Also include some common dependencies that might be needed
$common_dependencies = [
    'Psr/SimpleCache/CacheInterface.php',
    'Psr/SimpleCache/CacheException.php'
];

foreach ($common_dependencies as $dep) {
    $dep_file = __DIR__ . '/phpoffice/phpspreadsheet/src/PhpSpreadsheet/' . $dep;
    if (file_exists($dep_file)) {
        require_once $dep_file;
    }
}

// Include essential files manually to ensure they're loaded
$essential_files = [
    'Spreadsheet.php',
    'Writer/Xlsx.php',
    'Writer/BaseWriter.php',
    'IOFactory.php',
    'Worksheet/Worksheet.php',
    'Style/Style.php',
    'Style/Fill.php',
    'Style/Border.php',
    'Style/Alignment.php',
    'Style/Color.php',
    'Cell/Coordinate.php',
    'Cell/DataType.php',
    'Shared/File.php',
    'Shared/StringHelper.php',
    'Calculation/Calculation.php'
];

foreach ($essential_files as $file) {
    $full_path = $phpspreadsheet_base . $file;
    if (file_exists($full_path)) {
        require_once $full_path;
    }
}