<?php

defined('ABSPATH') || exit;

/**
 * Character-related handlers for the content generator.
 *
 * Usage: Growtype_Art_Admin_Content_Generator_Characters::handle_search()
 */
class Growtype_Art_Admin_Content_Generator_Characters
{
    /**
     * Format a raw character row from the DB into a compact {id, label, slug}
     * object for the autocomplete dropdown.
     */
    public static function format_option(array $character): array
    {
        $raw = !empty($character['character_title'])
            ? $character['character_title']
            : (!empty($character['slug']) ? $character['slug'] : ($character['prompt'] ?? ''));

        $label = mb_strlen($raw) > 80 ? mb_substr($raw, 0, 80) . '…' : $raw;

        return [
            'id'    => (int)$character['id'],
            'label' => $label ?: 'Character #' . $character['id'],
            'slug'  => $character['slug'] ?? '',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX: search characters
    // ─────────────────────────────────────────────────────────────────────────

    public static function handle_search(): void
    {
        check_ajax_referer('growtype_art_admin', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.']);
        }

        global $wpdb;

        $query = sanitize_text_field(wp_unslash($_POST['q'] ?? ''));
        if ($query === '') {
            wp_send_json_success(['characters' => []]);
        }

        $like = '%' . $wpdb->esc_like($query) . '%';

        $characters = $wpdb->get_results($wpdb->prepare(
            "SELECT m.id, m.prompt,
                    MAX(CASE WHEN s.meta_key = 'character_title' THEN s.meta_value END) AS character_title,
                    MAX(CASE WHEN s.meta_key = 'slug' THEN s.meta_value END) AS slug
             FROM {$wpdb->prefix}growtype_art_models AS m
             LEFT JOIN {$wpdb->prefix}growtype_art_model_settings AS s ON s.model_id = m.id
             GROUP BY m.id
             HAVING CAST(m.id AS CHAR) LIKE %s
                OR character_title LIKE %s
                OR slug LIKE %s
             ORDER BY m.id DESC
             LIMIT 20",
            $like,
            $like,
            $like
        ), ARRAY_A) ?: [];

        $characters = array_map([self::class, 'format_option'], $characters);

        wp_send_json_success(['characters' => $characters]);
    }
}
