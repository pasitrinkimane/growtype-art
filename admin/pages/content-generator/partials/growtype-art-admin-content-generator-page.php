<?php

defined('ABSPATH') || exit;

/**
 * Page renderer for the content generator.
 *
 * Assembles all data and delegates to Form / Scripts / Styles.
 */
class Growtype_Art_Admin_Content_Generator_Page
{
    public static function render(): void
    {
        $valid_types = ['text', 'image', 'video', 'audio'];
        $providers_by_type = [
            'text'  => Growtype_Art_Crud::get_text_providers_with_models(),
            'image' => Growtype_Art_Crud::get_image_providers_with_models(),
            'video' => Growtype_Art_Crud::get_video_providers_with_models(),
            'audio' => [],
        ];

        $reuse_prompt        = isset($_GET['reuse_prompt'])       ? sanitize_textarea_field(wp_unslash($_GET['reuse_prompt']))   : '';
        $reuse_provider      = isset($_GET['reuse_provider'])     ? sanitize_text_field(wp_unslash($_GET['reuse_provider']))     : '';
        $reuse_model         = isset($_GET['reuse_model'])        ? sanitize_text_field(wp_unslash($_GET['reuse_model']))        : '';
        $reuse_type          = isset($_GET['reuse_type'])         ? sanitize_text_field(wp_unslash($_GET['reuse_type']))         : '';
        $reuse_character_id  = isset($_GET['reuse_character_id']) ? (int)$_GET['reuse_character_id']                            : 0;
        $reuse_image         = isset($_GET['reuse_image'])        ? esc_url_raw(wp_unslash($_GET['reuse_image']))                : '';

        global $wpdb;
        $all_characters = $wpdb->get_results($wpdb->prepare(
            "SELECT m.id, m.prompt,
                    MAX(CASE WHEN s.meta_key = 'character_title' THEN s.meta_value END) AS character_title,
                    MAX(CASE WHEN s.meta_key = 'slug' THEN s.meta_value END) AS slug
             FROM {$wpdb->prefix}growtype_art_models AS m
             LEFT JOIN {$wpdb->prefix}growtype_art_model_settings AS s ON s.model_id = m.id
             GROUP BY m.id
             ORDER BY m.id DESC
             LIMIT %d",
            500
        ), ARRAY_A) ?: [];

        if ($reuse_character_id && !in_array($reuse_character_id, array_map('intval', array_column($all_characters, 'id')), true)) {
            $reuse_character = $wpdb->get_row($wpdb->prepare(
                "SELECT m.id, m.prompt,
                        MAX(CASE WHEN s.meta_key = 'character_title' THEN s.meta_value END) AS character_title,
                        MAX(CASE WHEN s.meta_key = 'slug' THEN s.meta_value END) AS slug
                 FROM {$wpdb->prefix}growtype_art_models AS m
                 LEFT JOIN {$wpdb->prefix}growtype_art_model_settings AS s ON s.model_id = m.id
                 WHERE m.id = %d
                 GROUP BY m.id
                 LIMIT 1",
                $reuse_character_id
            ), ARRAY_A);

            if (!empty($reuse_character)) {
                array_unshift($all_characters, $reuse_character);
            }
        }

        $characters_json = json_encode(
            array_map(function ($c) {
                return Growtype_Art_Admin_Content_Generator_Characters::format_option($c);
            }, $all_characters),
            JSON_HEX_TAG | JSON_HEX_AMP
        );

        $reuse_character_label = '';
        if ($reuse_character_id) {
            foreach ($all_characters as $c) {
                if ((int)$c['id'] === $reuse_character_id) {
                    $raw = !empty($c['character_title']) ? $c['character_title'] : (!empty($c['slug']) ? $c['slug'] : ($c['prompt'] ?? ''));
                    $reuse_character_label = mb_strlen($raw) > 80 ? mb_substr($raw, 0, 80) . '…' : ($raw ?: 'Character #' . $c['id']);
                    break;
                }
            }
        }

        $first_type     = in_array($reuse_type, $valid_types, true) ? $reuse_type : 'image';
        $first_prov_key = array_key_first($providers_by_type[$first_type]) ?? '';
        if ($reuse_provider && isset($providers_by_type[$first_type][$reuse_provider])) {
            $first_prov_key = $reuse_provider;
        }
        $default_models = $providers_by_type[$first_type][$first_prov_key]['models'] ?? [];
        $nonce          = wp_create_nonce('growtype_art_admin');
        $js_all_providers = json_encode($providers_by_type, JSON_HEX_TAG | JSON_HEX_AMP);

        Growtype_Art_Admin_Content_Generator_Styles::render();

        Growtype_Art_Admin_Content_Generator_Form::render([
            'first_type'            => $first_type,
            'reuse_character_label' => $reuse_character_label,
            'reuse_character_id'    => $reuse_character_id,
            'reuse_image'           => $reuse_image,
            'providers_by_type'     => $providers_by_type,
            'first_prov_key'        => $first_prov_key,
            'default_models'        => $default_models,
        ]);

        Growtype_Art_Admin_Content_Generator_Scripts::render([
            'nonce'              => $nonce,
            'js_all_providers'   => $js_all_providers,
            'characters_json'    => $characters_json,
            'first_type'         => $first_type,
            'reuse_prompt'       => $reuse_prompt,
            'reuse_provider'     => $reuse_provider,
            'reuse_model'        => $reuse_model,
            'reuse_character_id' => $reuse_character_id,
        ]);
    }
}
