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
