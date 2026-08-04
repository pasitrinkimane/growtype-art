<?php

defined('ABSPATH') || exit;

/**
 * WordPress hook registration for the content generator.
 */
class Growtype_Art_Admin_Content_Generator_Hooks
{
    /**
     * Register all WordPress hooks.
     */
    public static function register(): void
    {
        // Admin page
        add_action('admin_menu',            [self::class, 'admin_menu_pages']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_scripts']);

        // AJAX handlers
        add_action('wp_ajax_growtype_art_admin_generate_content',   ['Growtype_Art_Admin_Content_Generator_Ajax', 'handle_generate_content']);
        add_action('wp_ajax_growtype_art_admin_get_content_models', ['Growtype_Art_Admin_Content_Generator_Lookup', 'handle_get_models']);
        add_action('wp_ajax_growtype_art_admin_get_recent',         ['Growtype_Art_Admin_Content_Generator_Recent', 'handle_get']);
        add_action('wp_ajax_growtype_art_admin_search_characters',  ['Growtype_Art_Admin_Content_Generator_Characters', 'handle_search']);
    }

    /**
     * Register the admin submenu page.
     */
    public static function admin_menu_pages(): void
    {
        add_submenu_page(
            'growtype-art',
            __('Generator', 'growtype-art'),
            __('Generator', 'growtype-art'),
            'manage_options',
            Growtype_Art_Admin_Content_Generator::PAGE_NAME,
            ['Growtype_Art_Admin_Content_Generator_Page', 'render'],
            85
        );
    }

    /**
     * Enqueue WP media library on the generator page only.
     */
    public static function enqueue_scripts(string $hook): void
    {
        if (strpos($hook, Growtype_Art_Admin_Content_Generator::PAGE_NAME) === false) {
            return;
        }
        wp_enqueue_media();
    }
}
