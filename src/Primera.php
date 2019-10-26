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
    private $blankFileIncludePath;
    private $controllerLoader;
    private $bladeInstance;

    /**
     * Create a new instance.
     *
     * @param array $config The defaults.
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

        $this->blankFileIncludePath = trailingslashit(__DIR__) . 'index.php';
        $this->controllerLoader = new Loader(new Hierarchy);
        $this->bladeInstance = new BladeInstance($this->viewsDir, $this->cacheDir);

        // Force delete cached files if in debug mode.
        ! empty(WP_DEBUG) && $this->clearBladeTemplateCache();

        // Enqueue template scripts.
        add_action('wp_enqueue_script', [$this, '_enqueueTemplateScripts'], PHP_INT_MAX - 1);

        $this->_registerDirectives();
        // $this->_registerComponents();

        // Inject controllers.
        add_action('init', [$this, '_registerControllers']);

        // Filter WordPress template hierarchy.
        collect([
            'index', '404', 'archive', 'author', 'category', 'tag', 'taxonomy', 'date', 'home',
            'frontpage', 'page', 'paged', 'search', 'single', 'singular', 'attachment', 'embed'
        ])->map(function($type) {
            add_filter("{$type}_template_hierarchy", [$this, '_filterWordPressTemplateHierarchy']);
        });

        // Filter WooCommerce template include.
        add_filter('wc_get_template', [$this, '_filterWooCommerceTemplateInclude'], 10, 5);

        // Display WordPress Blade template.
        add_filter('template_include', [$this, '_displayWordPressBladeTemplate'], PHP_INT_MAX - 1);

        // Display WooCommerce Blade templates.
        add_action('woocommerce_before_template_part', [$this, '_displayWooCommerceBladeTemplate'], PHP_INT_MAX - 1, 4);

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

    public function clearBladeTemplateCache(): void
    {
        $files = glob(trailingslashit($this->cacheDir) . '*');
        foreach ($files as $file) {
            is_file($file) && @unlink($file);
        }
    }

    public function removeBladeFileExt(string $file_path): string
    {
        return preg_replace('#\.(blade\.?)?(php)?$#', '', ltrim($file_path));
    }

    /**
    * Get registered controller data based on body class.
    *
    * @param array $add_data Additional template data. Overrides controller data in case of duplicate keys.
    */
    public function getControllerData(string $template_name, array $add_data=[])
    {
        $controller_data = collect(get_body_class())->reduce(function($data, $class) use ($template_name) {
            return apply_filters("primera/template/{$class}/data", $data, $template_name);
        }, []);

        // The add_data can override controller_data.
        return wp_parse_args($add_data, $controller_data);
    }

    public function renderBladeTemplate(string $blade_template_path, array $add_data=[]): string
    {
        $template_name = basename($this->removeBladeFileExt($blade_template_path));

        $template_data = $this->getControllerData($template_name, $add_data);

        return $this->bladeInstance->render($template_name, $template_data);
    }

    public function bladeTemplateExists(string $template_name, string $template_dir=''): bool
    {
        $template_name = $this->removeBladeFileExt($template_name);

        $template_dir = $template_dir ?: $this->viewsDir;

        // Format paths to search for blade templates.
        $template_paths = [
            "{$template_dir}/{$template_name}.blade.php",
            "{$template_dir}/{$template_name}.php",
        ];

        if (file_exists($template_paths[0]) || file_exists($template_paths[1])) {
            return true;
        }

        return false;
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
            wp_script_add_data($fileName, 'defer', true);
        }
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

            // Set the params required for template param.
            $controller->__setParams();

            // Determine template location to expose data.
            $filterTag = "primera/template/{$controller->__getTemplateParam()}-data/data";

            // Pass data to filter
            add_filter($filterTag, function($data) use ($class) {

                // Recreate the class so that $post is included.
                $controller = new $class;

                // Params
                $controller->__setParams();

                // Lifecycle
                $controller->__before();

                // Data
                $controller->__setData($data);

                // Lifecycle
                $controller->__after();

                // Return
                return $controller->__getData();

            }, 10);
        }
    }

    /**
    * Filter templates to locate blade templates before WP default.
    * @param string|string[] $templates Possible template files
    * @return array
    */
    public function _filterWordPressTemplateHierarchy($templates)
    {
        $paths = [
            'views',
            'source/views'
        ];

        $paths_pattern = "#^(" . implode('|', $paths) . ")/#";

        return collect($templates)
            ->map(function($template) use ($paths_pattern) {

                $template = $this->removeBladeFileExt($template);

                // Remove partial $paths from the beginning of template names.
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

    public function _filterWooCommerceTemplateInclude($template, $template_name, $args, $template_path, $default_path)
    {
        // Return path to empty file if blade template exists.
        if ($this->bladeTemplateExists($template_name, "{$this->viewsDir}/woocommerce/")) {
            return $this->blankFileIncludePath;
        }

        return $template;
    }

    /**
    * Filter template include to render custom templates.
    */
    public function _displayWordPressBladeTemplate($template): string
    {
        // Only proceed if Blade template exists.
        if (! $this->bladeTemplateExists($template)) {
            return $template;
        }

        // collect(['get_header','wp_head'])->each(function ($tag) {
        //     ob_start();
        //     do_action($tag);
        //     $output = ob_get_clean();
        //     remove_all_actions($tag);
        //     add_action($tag, function () use ($output) {
        //         echo $output;
        //     });
        // });

        echo $this->renderBladeTemplate(basename($template));

        // Must return path to empty file.
        return $this->blankFileIncludePath;
    }

    public function _displayWooCommerceBladeTemplate($template_name, $template_path, $located, $args)
    {
        // Only proceed if the blank include file is being requested.
        if ($located != $this->blankFileIncludePath) {
            return;
        }

        $template_name = basename($this->removeBladeFileExt($template_name));

        echo $this->renderBladeTemplate($template_name, $args);
    }
}
