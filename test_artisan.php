<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
var_dump(class_exists(\Illuminate\Foundation\Console\ServeCommand::class));
