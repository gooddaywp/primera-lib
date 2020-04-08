# Primera Package

Funtional, but currently under development.

DEMO USAGE:
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

