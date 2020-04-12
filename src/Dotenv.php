<?php

declare(strict_types=1);

namespace Primera;

use Dotenv\Dotenv as PhpDotenv;
use Illuminate\Support\Env;

defined('ABSPATH') || exit;

class Dotenv
{
    public $dotenv;

    public function __construct($envFile=null)
    {
        $this->dotenv = PhpDotenv::create(
            get_parent_theme_file_path(),
            $envFile
        );
        $this->dotenv->load();
    }

    public function getInstance(): PhpDotenv
    {
        return $this->dotenv;
    }

    public function get(string $key, $default=null)
    {
        $value = Env::get($key, $default);

        if (is_string($value)) {
            $value = trim($value);
            $value = $this->maybeFormatToArray($value);
        }

        return $value;
    }

    function maybeFormatToArray(string $str)
    {
        if (0 == strpos($str, '[') && substr($str, -1) == ']' && strpos($str, ',')) {
            $str = trim($str, '[]');
            $str = str_replace(["\r", "\n"], '', $str);
            $str = explode(',', $str);
            $str = array_map('trim', $str);
        }
        return $str;
    }
}
