<?php

namespace Primera;

use Exception;
use duncan3dc\Laravel\BladeInstance;

defined('ABSPATH') || exit;

function primera($config=null)
{
    static $primera;

    if (! $primera instanceof BladeInstance) {
        $primera = new Primera($config ?? []);
    }

    switch ($config ?? '') {
        case 'blade':
            return $primera->getBladeInstance();
    }

    return $primera;
}

// DEMO USAGE:
// primera([
//     'viewsDir' => get_theme_file_path('source/views/'),
//     'cacheDir' => trailingslashit(wp_get_upload_dir()['basedir']).'blade-cache',
// ]);
// primera()->component('components.navbar');

// NOTE: To render views via AJAX use:
// primera()->render($template, $data);

