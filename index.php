<?php

use Primera\Primera;

defined('ABSPATH') || exit;

function primera($arg=null)
{
    static $primera;

    if (! $primera instanceof Primera) {
        $primera = new Primera((array) $arg);
    }

    switch ($arg) {
        case 'blade':
            return $primera->getBladeInstance();
        case 'env':
            return $primera->getDotenv();
        default:
            return $primera;
    }
}
