<?php

defined('ABSPATH') || exit;

/**
 * Lookup / utility AJAX handlers for the content generator.
 *
 * Usage: Growtype_Art_Admin_Content_Generator_Lookup::handle_get_models()
 */
class Growtype_Art_Admin_Content_Generator_Lookup
{
    /**
     * Return models for a given text provider (legacy endpoint).
     */
    public static function handle_get_models(): void
    {
        check_ajax_referer('growtype_art_admin', '_ajax_nonce');

        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $all      = Growtype_Art_Crud::get_text_providers_with_models();
        $models   = $all[$provider]['models'] ?? [];

        wp_send_json_success(['models' => $models]);
    }
}
