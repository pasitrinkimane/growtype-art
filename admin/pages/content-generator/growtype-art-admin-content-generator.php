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

    public static function get_image_size_presets(): array
    {
        return apply_filters('growtype_art_content_generator_image_size_presets', [
            'default' => [
                'label'        => __('Default — 3:4 (768×1024)', 'growtype-art'),
                'width'        => 768,
                'height'       => 1024,
                'aspect_ratio' => '3:4',
            ],
            'auto' => [
                'label'        => __('Auto — Model recommended dimensions', 'growtype-art'),
                'width'        => null,
                'height'       => null,
                'aspect_ratio' => null,
            ],
            'square' => [
                'label'        => __('Square — 1:1 (1024×1024)', 'growtype-art'),
                'width'        => 1024,
                'height'       => 1024,
                'aspect_ratio' => '1:1',
            ],
            'portrait' => [
                'label'        => __('Portrait — 3:4 (768×1024)', 'growtype-art'),
                'width'        => 768,
                'height'       => 1024,
                'aspect_ratio' => '3:4',
            ],
            'story' => [
                'label'        => __('Story / Mobile — 9:16 (~768×1344)', 'growtype-art'),
                'width'        => 768,
                'height'       => 1344,
                'aspect_ratio' => '9:16',
            ],
            'portrait-2-3' => [
                'label'        => __('Portrait — 2:3 (768×1152)', 'growtype-art'),
                'width'        => 768,
                'height'       => 1152,
                'aspect_ratio' => '2:3',
            ],
            'landscape' => [
                'label'        => __('Landscape — 4:3 (1024×768)', 'growtype-art'),
                'width'        => 1024,
                'height'       => 768,
                'aspect_ratio' => '4:3',
            ],
            'widescreen' => [
                'label'        => __('Widescreen — 16:9 (~1344×768)', 'growtype-art'),
                'width'        => 1344,
                'height'       => 768,
                'aspect_ratio' => '16:9',
            ],
            'landscape-3-2' => [
                'label'        => __('Landscape — 3:2 (1152×768)', 'growtype-art'),
                'width'        => 1152,
                'height'       => 768,
                'aspect_ratio' => '3:2',
            ],
            'custom' => [
                'label'        => __('Custom dimensions', 'growtype-art'),
                'width'        => null,
                'height'       => null,
                'aspect_ratio' => 'custom',
            ],
        ]);
    }

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
