<?php

declare(strict_types=1);

namespace Primera;

use duncan3dc\Laravel\BladeInstance;
use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;

// defined('WPINC') || exit;

final class PrimeraBladeInstance extends BladeInstance
{

    private $_path;
    private $_cache;
    private $_compiler;

    /**
     * Create a new instance of the blade view factory.
     *
     * @param string $path The default path for views
     * @param string $cache The default path for cached php
     */
    public function __construct(string $path, string $cache)
    {
        $this->_path = $path;
        $this->_cache = $cache;

        parent::__construct($path, $cache);
    }

    /**
    * Get the internal compiler in use.
    *
    * @return BladeCompiler
    */
    private function _getCompiler(): BladeCompiler
    {
        if ($this->_compiler) {
            return $this->_compiler;
        }

        if (! is_dir($this->_cache)) {
            wp_mkdir_p($this->_cache);
        }

        $this->_compiler = new BladeCompiler(new Filesystem(), $this->_cache);

        return $this->_compiler;
    }

    /**
     * Register an include alias directive.
     *
     * @param  string  $path
     * @param  string|null  $alias
     * @return  $this
     */
    public function include(string $path, string $alias=null)
    {
        $this
            ->_getCompiler()
            ->include($path, $alias);

        return $this;
    }

    /**
     * Register a component alias directive.
     *
     * @param  string  $path
     * @param  string|null  $alias
     * @return  $this
     */
    public function component(string $path, string $alias=null)
    {
        $this
            ->_getCompiler()
            ->component($path, $alias);

        return $this;
    }

    /**
    * Compile the given Blade template contents.
    *
    * @param  string  $value
    * @return  string
    */
    public function compileString(string $value)
    {
        return $this
            ->getCompiler()
            ->compileString($value);
    }
}
