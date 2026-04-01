<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class UploadServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     * Override PHP upload limits at runtime so large files (images/videos) can be uploaded
     * without needing to modify php.ini with sudo.
     */
    public function boot(): void
    {
        ini_set('upload_max_filesize', '256M');
        ini_set('post_max_size', '260M');
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', '300');
        ini_set('max_input_time', '300');
    }
}
