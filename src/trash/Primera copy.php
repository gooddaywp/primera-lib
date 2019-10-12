<?php

declare(strict_types=1);

namespace Primera;

use Illuminate\Support\Collection;
use Illuminate\View\Compilers\BladeCompiler;
use Brain\Hierarchy\Hierarchy;
use Sober\Controller\Loader;
use duncan3dc\Laravel\BladeInstance;
use Primera\Directives;
// use Primera\PrimeraBladeInstance;
// use Illuminate\Support\Facades\Blade;

// defined('WPINC') || exit;

class PrimeraCOPY
{
    public $viewsDir;
    public $cacheDir;


    public $primeraBladeInstance;

    /**
     * Create a new instance of the blade view factory.
     *
     * @param string $path The default path for views
     * @param string $cache The default path for cached php
     * @param DirectivesInterface $directives
     */
    public function __construct(string $path, string $cache)
    {
        $this->viewsDir = $path;
        $this->cacheDir = $cache;
        // $this->primeraBladeInstance = new PrimeraBladeInstance($path, $cache);
        $directives = new Directives;
        $this->duncanBlade = new BladeInstance($this->viewsDir, $this->cacheDir, $directives);

        // Force delete cached files if in debug mode.
        if (! empty(WP_DEBUG)) {
            $files = glob(trailingslashit($this->cacheDir) . '/*');
            foreach ($files as $file) {
                is_file($file) && unlink($file);
            }
        }

        // Inject controllers.
        add_action('init', [$this, '_injectControllers']);

        // Filter template hierarchy.
        collect([
            'index', '404', 'archive', 'author', 'category', 'tag', 'taxonomy', 'date', 'home',
            'frontpage', 'page', 'paged', 'search', 'single', 'singular', 'attachment', 'embed'
        ])->map(function($type) {
            add_filter("{$type}_template_hierarchy", [$this, '_filterTemplateHierarchy']);
        });

        // Render Blade templates.
        add_filter('template_include', [$this, '_renderBladeTemplates'], PHP_INT_MAX);

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
        // return $this->primeraBladeInstance;
        return $this->duncanBlade;
    }

    /**
    * Inject controllers.
    */
    public function _injectControllers()
    {
        // Run WordPress hierarchy class.
        $hierarchy = new Hierarchy;

        // Run Loader class and pass on WordPress hierarchy class.
        $loader = new Loader($hierarchy);

        // Loop over each class
        foreach ($loader->getClassesToRun() as $class) {

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
    public function _renderBladeTemplates($template) {

        // collect(['get_header','wp_head'])->each(function ($tag) {
        //     ob_start();
        //     do_action($tag);
        //     $output = ob_get_clean();
        //     remove_all_actions($tag);
        //     add_action($tag, function () use ($output) {
        //         echo $output;
        //     });
        // });

        $data = collect(get_body_class())->reduce(function($data, $class) use ($template) {
            return apply_filters("primera/template/{$class}/data", $data, $template);
        }, []);

        if ($template) {

            /** Remove .blade.php/.blade/.php from template and gets it's basename. */
            $template = basename(preg_replace('#\.(blade\.?)?(php)?$#', '', ltrim($template)));

            // $directives = new Directives;
            // $this->duncanBlade = new BladeInstance($this->viewsDir, $this->cacheDir, $directives);
            echo $this->duncanBlade->render($template, $data);

            // Echo the rendered template.
            // echo $this->primeraBladeInstance->render($template, $data);

            // Always returns path to empty file.
            return get_theme_file_path('./index.php');
        }

        return $template;
    }




    private function x____registerDirectives(BladeInterface $blade): BladeInterface
    {
        $blade->directive('high', function() {
            return "<?php echo 'HIGH'; ?>";
        });
        $blade->directive('endhigh', function () {
            return "<?php echo 'ENDHIGH' ?>";
        });
    }

    private function x____registerIncludes(BladeInterface $blade): BladeInterface
    {
        $blade->include('components.footer', 'footer');
    }

    private function x____registerComponents(BladeInterface $blade): BladeInterface
    {
        $blade->component('components.navbar', 'navbar');
    }
}
