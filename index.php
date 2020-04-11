<?php

use Primera\Primera;

defined('ABSPATH') || exit;

function primera($arg=null): Primera
{
    static $primera;

    if (! $primera instanceof Primera) {
        $primera = new Primera((array) $arg);
    }

    switch ($arg ?? '') {
        case 'blade':
            return $primera->getBladeInstance();
        default:
            return $primera;
    }
}
