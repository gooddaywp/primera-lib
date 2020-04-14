# Primera Package

Funtional, but currently under development. This package is part of the Primera WordPress theme.

For an integration example please see https://github.com/gooddaywp/primera#readme.

```php
// Init:
primera([
    'viewsDir' => get_theme_file_path('source/views/'),
    'cacheDir' => trailingslashit(wp_get_upload_dir()['basedir']).'blade-cache',
]);

// Add Blade component alias:
primera('blade')->component('components.navbar');

// Rendering views via AJAX:
primera('blade')->render($templateName, $dataArr);
```

For local development integrate the following settings into your theme's the composer.json file.

```json
{
    "require": {
        "marcwiest/primera-package": "@dev"
    },
    "repositories": {
        "dev-package": {
            "type": "path",
            "url": "~/code/primera-package",
            "options": {
                "symlink": true
            }
        }
    }
}
```
