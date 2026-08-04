<?php

defined('ABSPATH') || exit;

/**
 * Growtype_Art_Admin_Content_Generator
 *
 * Admin page at: wp-admin/admin.php?page=growtype-art-content-generator
 */
class Growtype_Art_Admin_Content_Generator
{
    const PAGE_NAME = 'growtype-art-content-generator';

    public function __construct()
    {
        $this->load_partials();
        Growtype_Art_Admin_Content_Generator_Hooks::register();
    }

    private function load_partials(): void
    {
        $dir   = __DIR__ . '/partials';
        $files = [
            'growtype-art-admin-content-generator-page.php',
            'growtype-art-admin-content-generator-hooks.php',
            'growtype-art-admin-content-generator-recent.php',
            'growtype-art-admin-content-generator-lookup.php',
            'growtype-art-admin-content-generator-characters.php',
            'growtype-art-admin-content-generator-ajax.php',
            'growtype-art-admin-content-generator-form.php',
            'growtype-art-admin-content-generator-scripts.php',
            'growtype-art-admin-content-generator-styles.php',
        ];
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }
}
