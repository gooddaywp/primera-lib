<?php

declare(strict_types=1);

namespace Primera;

use Illuminate\Support\Collection;
use Brain\Hierarchy\Hierarchy;
use Sober\Controller\Loader;
use duncan3dc\Laravel\BladeInstance;

defined('WPINC') || exit;

class Primera
{
    public $viewsDir;
    public $cacheDir;
    public $cssDir;
    public $jsDir;
    private $controllerLoader;
    private $bladeInstance;

    /**
     * Create a new instance of the blade view factory.
     *
     * @param string $path The default path for views
     * @param string $cache The default path for cached php
     * @param DirectivesInterface $directives
     */
    public function __construct(array $config=[])
    {
        $config = wp_parse_args($config, [
            'viewsDir' => get_theme_file_path('source/views/'),
            'cacheDir' => trailingslashit(wp_get_upload_dir()['basedir']).'blade-cache',
            'cssDir' => get_theme_file_path('public/css/'),
            'jsDir' => get_theme_file_path('public/js/'),
        ]);

        $this->viewsDir = (string) $config['viewsDir'];
        $this->cacheDir = (string) $config['cacheDir'];
        $this->cssDir = (string) $config['cssDir'];
        $this->jsDir = (string) $config['jsDir'];

        $this->controllerLoader = new Loader(new Hierarchy);
        $this->bladeInstance = new BladeInstance($this->viewsDir, $this->cacheDir);

        // Force delete cached files if in debug mode.
        if (! empty(WP_DEBUG)) {
            $files = glob(trailingslashit($this->cacheDir) . '/*');
            foreach ($files as $file) {
                is_file($file) && @unlink($file);
            }
        }

        $this->_registerDirectives();
        // $this->_registerComponents();

        // Inject controllers.
        add_action('init', [$this, '_registerControllers']);

        // Filter template hierarchy.
        collect([
            'index', '404', 'archive', 'author', 'category', 'tag', 'taxonomy', 'date', 'home',
            'frontpage', 'page', 'paged', 'search', 'single', 'singular', 'attachment', 'embed'
        ])->map(function($type) {
            add_filter("{$type}_template_hierarchy", [$this, '_filterTemplateHierarchy']);
        });

        // Render Blade templates.
        add_filter('template_include', [$this, '_renderBladeTemplate'], PHP_INT_MAX - 1);

        // Enqueue template scripts.
        add_action('wp_enqueue_script', [$this, '_enqueueTemplateScripts'], PHP_INT_MAX - 1);

        /**
         * Updates the `$post` variable on each iteration of the loop.
         * Note: updated value is only available for subsequently loaded views, such as partials
         */
        // add_action('the_post', function ($post) {
        //     sage('blade')->share('post', $post);
        // });
    }

    public function getBladeInstance()
    {
        return $this->bladeInstance;
    }

    public function _registerDirectives()
    {
        $this->getBladeInstance()->directive('dump', function($args) {
            // echo 'Line ' . __LINE__ . ' in ' . __FILE__;
            // $backtrace = debug_backtrace();
            return "<?php dump({$args}); ?>";
        });

        $this->getBladeInstance()->directive('dd', function($args) {
            return "<?php dump({$args}); die(1); ?>";
        });
    }

    /**
    * Inject controllers.
    */
    public function _registerControllers()
    {
        // Loop over each class.
        foreach ($this->controllerLoader->getClassesToRun() as $class) {

            $controller = new $class;

            // Set the params required for template param
            $controller->__setParams();

            // Determine template location to expose data
            $filterTag = "primera/template/{$controller->__getTemplateParam()}-data/data";

            // Pass data to filter
            add_filter( $filterTag, function( $data ) use ( $class ) {

                // Recreate the class so that $post is included.
                $controller = new $class;

                // Params
                $controller->__setParams();

                // Lifecycle
                $controller->__before();

                // Data
                $controller->__setData( $data );

                // Lifecycle
                $controller->__after();

                // Return
                return $controller->__getData();

            }, 10 );
        }
    }

    /**
    * Filter templates to locate blade templates before WP default.
    * @param string|string[] $templates Possible template files
    * @return array
    */
    public function _filterTemplateHierarchy($templates)
    {
        $paths = apply_filters('primera/filterTemplates/paths', [
            'views',
            'source/views'
        ]);

        $paths_pattern = "#^(" . implode('|', $paths) . ")/#";

        return collect($templates)
            ->map(function($template) use ($paths_pattern) {
                /** Remove .blade.php/.blade/.php from template names */
                $template = preg_replace('#\.(blade\.?)?(php)?$#', '', ltrim($template));

                /** Remove partial $paths from the beginning of template names */
                if (strpos($template, '/')) {
                    $template = preg_replace($paths_pattern, '', $template);
                }

                return $template;
            })
            ->flatMap(function($template) use ($paths) {
                return collect($paths)
                    ->flatMap(function($path) use ($template) {
                        return [
                            "{$path}/{$template}.blade.php",
                            "{$path}/{$template}.php",
                        ];
                    })
                    ->concat([
                        "{$template}.blade.php",
                        "{$template}.php",
                    ]);
            })
            ->filter()
            ->unique()
            ->all();
    }

    /**
    * Filter template include to render custom templates.
    */
    public function _renderBladeTemplate($template): string
    {
        // collect(['get_header','wp_head'])->each(function ($tag) {
        //     ob_start();
        //     do_action($tag);
        //     $output = ob_get_clean();
        //     remove_all_actions($tag);
        //     add_action($tag, function () use ($output) {
        //         echo $output;
        //     });
        // });

        // Get registered controller based on body class.
        $data = collect(get_body_class())->reduce(function($data, $class) use ($template) {
            return apply_filters("primera/template/{$class}/data", $data, $template);
        }, []);

        if ($template) {

            /** Remove .blade.php/.blade/.php from template and gets it's basename. */
            $template = basename(preg_replace('#\.(blade\.?)?(php)?$#', '', ltrim($template)));

            // Display blade template.
            echo $this->bladeInstance->render($template, $data);

            // Always returns path to empty file.
            return get_theme_file_path('./index.php');
        }

        return $template;
    }

    /**
    * Enqueue template scripts.
    */
    public function _enqueueTemplateScripts()
    {
        // File name (same as blade template name).
        $fileName = str_replace(['.blade','.php'], '', basename($GLOBALS['template']));

        if (file_exists($path = get_theme_file_path("public/css/{$fileName}.css"))) {
            wp_enqueue_style(
                $fileName,
                get_theme_file_uri("public/css/{$fileName}.css"),
                [],
                filemtime($path)
            );
        }

        if (file_exists($path = get_theme_file_path("public/js/{$fileName}.js"))) {
            wp_enqueue_script(
                $fileName,
                get_theme_file_uri("public/js/{$fileName}.js"),
                [],
                filemtime($path)
            );
            wp_script_add_data( $fileName, 'defer', true );
        }
    }
}
