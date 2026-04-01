<?php
try {
    ini_set('display_errors', 1);
    require __DIR__.'/index.php';
} catch (\Throwable $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
