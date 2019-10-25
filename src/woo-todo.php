<?php

// TODO: Integrate:

declare(strict_types=1);

namespace App\woocommerce;

use App\Classes\BladeOne;
use Illuminate\Support\Collection;

\defined('ABSPATH') || exit;

\add_filter('wc_get_template', __NAMESPACE__ . '\\_filter_woocommerce_template', 10, 5);
\add_action('woocommerce_before_template_part', __NAMESPACE__ . '\\_render_woocommerce_blade_template', 10, 4);

function _render_woocommerce_blade_template($template_name, $template_path, $located, $args)
{
    if (! apply_filters('primera/renderWooBladeTemplate/bool', false)) {
        return;
    }

    $template = $template_name;
    $template_dir = dirname($template_name);

    $data = \collect(\get_body_class())->reduce(function($data, $class) use ($template) {
        return \apply_filters( "primera/template/{$class}/data", $data, $template );
    }, []);

    // Merging args and data. Args will override controller data.
    $data = wp_parse_args($args, $data);

    /** Remove .blade.php/.blade/.php from template and gets it's basename. */
    $template_name = basename(preg_replace('#\.(blade\.?)?(php)?$#', '', ltrim($template_name)));

    // NOTE: More modes can be found within BladeOne.
    if (! empty(WP_DEBUG)) {
        $bladeoneMode = BladeOne::MODE_DEBUG;
    } else {
        $bladeoneMode = BladeOne::MODE_AUTO;
    }

    $viewsDir = \get_theme_file_path("source/views/woocommerce/{$template_dir}");
    $cacheDir = \trailingslashit( \wp_get_upload_dir()['basedir'] ).'blade-cache';
    $bladeone = new BladeOne( $viewsDir, $cacheDir, $bladeoneMode );

    $bladeone->setBaseUrl("https://beedelightful.com/");

    echo $bladeone->run($template_name, $data);
}

function _filter_woocommerce_template($template, $template_name, $args, $template_path, $default_path)
{
    $paths = \apply_filters('primera/filterWooTemplate/paths', [
        'source/views/woocommerce',
    ]);

    $template_name = \preg_replace('#\.?(php)?$#', '', ltrim($template_name));

    $paths = array_map(function($path) use ($template_name) {
        $path = untrailingslashit(get_theme_file_path($path));
        return [
            "{$path}/{$template_name}.blade.php",
            "{$path}/{$template_name}.php",
        ];
    }, $paths);

    foreach ($paths as $file_paths) {
        foreach ($file_paths as $file) {
            if (\file_exists($file)) {
                // Allows the blade template render function to render.
                add_filter('primera/renderWooBladeTemplate/bool', '__return_true');
                // Return path to empty file if blade template exists.
                return \get_theme_file_path('source/index.php');
            }
        }
    }

    // Disallows the blade template render function to render.
    add_filter('primera/renderWooBladeTemplate/bool', '__return_false');

    return $template;
}
