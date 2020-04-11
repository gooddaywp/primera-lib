<?php

declare(strict_types=1);

namespace Primera;

use Illuminate\Support\Collection;
use Brain\Hierarchy\Hierarchy;
use Sober\Controller\Loader;
use duncan3dc\Laravel\BladeInstance;

defined('ABSPATH') || exit;

class Primera
{
    public $viewsDir;
    public $cacheDir;
    public $cssDir;
    public $jsDir;
    private $blankFileIncludePath;
    private $controllerLoader;
    private $bladeInstance;

    // TODO:
    // https://docs.easydigitaldownloads.com/article/1216-moving-edd-templates-to-your-theme
    // https://github.com/easydigitaldownloads/easy-digital-downloads/blob/master/includes/template-functions.php#L744-L766
    // Since the single download posts are using the default WP templates (e.g. `single-download.php`),
    // they won't need to be filtered.
    // public function _filterEddTemplateInclude() {}
    // public function _displayEddBladeTemplate() {}

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
<<<<<<< HEAD
        defined('WP_DEBUG') && WP_DEBUG && $this->clearBladeTemplateCache();
=======
        defined(WP_DEBUG) && WP_DEBUG && $this->clearBladeTemplateCache();
>>>>>>> 22a52c422d55b629b9d201b6fe3b6c07e6c2bca1

        $this->_registerDirectives();
        // $this->_registerComponents();

        // Enqueue template scripts.
        add_action('wp_enqueue_scripts', [$this, '_enqueueTemplateScripts'], PHP_INT_MAX - 1);

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

        // Refresh global $post variable for each new loop iteration.
        add_action('the_post', [$this, '_refreshPostGlobal'], PHP_INT_MAX);

        // Allow defer/async attributes on enqueued/registered scripts.
        add_filter('script_loader_tag', [$this, '_filterScriptLoaderTag'], 10, 2);

        // TODO: Investigate if needed to output buffer echoed plugin hooks.
        // collect(['get_header','wp_head'])->each(function ($tag) {
        //     ob_start();
        //     do_action($tag);
        //     $output = ob_get_clean();
        //     remove_all_actions($tag);
        //     add_action($tag, function () use ($output) {
        //         echo $output;
        //     });
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
        // return preg_replace('#\.(blade\.?)?(php)?$#', '', ltrim($file_path));
        return trim(str_replace(['.blade','.php'], '', $file_path));
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

        return $this->getBladeInstance()->render($template_name, $template_data);
    }

    public function bladeTemplateExists(string $template_name, string $template_dir=''): bool
    {
        $template_name = basename($this->removeBladeFileExt($template_name));

        $template_dir = $template_dir ?: $this->viewsDir;

        // Format paths to search for blade templates.
        $template_paths = [
            "{$template_dir}/{$template_name}.blade.php",
            "{$template_dir}/{$template_name}.php",
        ];

        if (is_file($template_paths[0]) || is_file($template_paths[1])) {
            return true;
        }

        return false;
    }

    public function _registerDirectives()
    {
        $this->getBladeInstance()->directive('dump', function($args) {
            // echo 'Line ' . __LINE__ . ' in ' . __FILE__;
            // $backtrace = debug_backtrace();
            return '<?php dump(' . $args . '); ?>';
        });

        $this->getBladeInstance()->directive('dd', function($args) {
            return '<?php dump(' . $args . '); die(1); ?>';
        });
    }

    /**
    * Enqueue template scripts.
    */
    public function _enqueueTemplateScripts()
    {
        // NOTE: File name is same as blade template name but without `.blade.php` extension.
        $fileName = basename($this->removeBladeFileExt($GLOBALS['template']));

        if (file_exists($path = get_theme_file_path("public/css/{$fileName}.css"))) {

            $fileUrl = apply_filters(
                'primera/template/script-file-url',
                get_theme_file_uri("public/css/{$fileName}.css"),
                $fileName,
                'css'
            );
            $fileVersion = apply_filters(
                'primera/template/script-file-version',
                filemtime($path),
                $path,
                'css'
            );
            wp_enqueue_style($fileName, $fileUrl, [], $fileVersion);
        }

        if (file_exists($path = get_theme_file_path("public/js/{$fileName}.js"))) {

            $fileUrl = apply_filters(
                'primera/template/script-file-url',
                get_theme_file_uri("public/js/{$fileName}.js"),
                $fileName,
                'js'
            );
            $fileVersion = apply_filters(
                'primera/template/script-file-version',
                filemtime($path),
                $path,
                'js'
            );
            wp_enqueue_script($fileName, $fileUrl, [], $fileVersion);
            wp_script_add_data(
                $fileName,
                apply_filters('primera/template/js-file-defer-or-async', 'defer'),
                true
            );
        }
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

    // NOTE: See function `wc_get_template` in `woocommerce/includes/wc-core-functions.php:L207`.
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
    * NOTE: See function `wc_get_template` in `woocommerce/includes/wc-core-functions.php`.
    */
    public function _displayWordPressBladeTemplate($template): string
    {
        // Only proceed if Blade template exists.
        if (! $this->bladeTemplateExists($template)) {
            return $template;
        }

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

    /**
    * Updates the `$post` variable on each iteration of the loop.
    * NOTE: Updated value is only available for subsequently loaded views, such as partials.
    */
    public function _refreshPostGlobal()
    {
        $this->getBladeInstance()->share('post', get_post());
    }

    /**
    * Adds async/defer attributes to enqueued/registered scripts.
    *
    * If #12009 lands in WordPress, this function can no-op since it would be handled in core.
    *
    * Source: https://github.com/wprig/wprig/blob/master/dev/inc/template-functions.php#L41
    *
    * @since 1.0
    * @link https://core.trac.wordpress.org/ticket/12009
    * @param string $tag The script tag.
    * @param string $handle The script handle.
    * @return array
    */
    function _filterScriptLoaderTag($tag, $handle)
    {
        foreach (['async', 'defer'] as $attr) {

            if (! wp_scripts()->get_data($handle, $attr)) {
                continue;
            }

            // Prevent adding attribute when already added in #12009.
            if (! preg_match(":\s$attr(=|>|\s):", $tag)) {
                $tag = preg_replace(':(?=></script>):', " $attr", $tag, 1);
            }

            // Only allow async or defer, not both.
            break;
        }

        return $tag;
    }
}
