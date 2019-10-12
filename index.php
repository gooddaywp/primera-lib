<?php

namespace Primera;

use duncan3dc\Laravel\BladeInstance;

defined('WPINC') || exit;

function primera(array $config=[]): BladeInstance
{
    static $primera;

    if (! $primera instanceof BladeInstance) {
        $primera = (new Primera($config))->getBladeInstance();
    }

    return $primera;
}

primera([
    'viewsDir' => get_theme_file_path('source/views/'),
    'cacheDir' => trailingslashit(wp_get_upload_dir()['basedir']).'blade-cache',
]);
primera()->component('components.navbar');
// NOTE: For AJAX use:
// primera()->render($template, $data);

