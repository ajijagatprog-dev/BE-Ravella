<?php
try {
    require 'public/index.php';
} catch (\Throwable $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
