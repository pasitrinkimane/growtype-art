<?php

defined('ABSPATH') || exit;

/**
 * Recent/history AJAX handler for the content generator.
 *
 * Usage: Growtype_Art_Admin_Content_Generator_Recent::handle_get()
 */
class Growtype_Art_Admin_Content_Generator_Recent
{
    /**
     * Return refreshed recent-table HTML via AJAX.
     */
    public static function handle_get(): void
    {
        check_ajax_referer('growtype_art_admin', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.']);
        }

        $html = Growtype_Art_Admin_History::render_recent(5);
        wp_send_json_success(['html' => $html]);
    }
}
