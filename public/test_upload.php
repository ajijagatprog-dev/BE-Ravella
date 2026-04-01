<?php header('Content-Type: application/json'); echo json_encode(['upload_max_filesize' => ini_get('upload_max_filesize'), 'post_max_size' => ini_get('post_max_size')]);
