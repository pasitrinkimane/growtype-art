<?php

defined('ABSPATH') || exit;

/**
 * AJAX handlers for the content generator.
 */
class Growtype_Art_Admin_Content_Generator_Ajax
{
    // ─────────────────────────────────────────────────────────────────────────
    // Shared helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolve a provider key to its *_Base class name (namespaced or plain).
     */
    private static function resolve_provider_class(string $provider): ?string
    {
        $namespaced = sprintf('\partials\%s_Base', ucfirst($provider));
        $plain      = sprintf('%s_Base',           ucfirst($provider));

        return class_exists($namespaced) ? $namespaced : (class_exists($plain) ? $plain : null);
    }

    /**
     * Validate provider + fallback model, returning ['provider' => ..., 'model' => ..., 'label' => ...].
     */
    private static function resolve_provider_and_model(string $type, array $providers, string $provider, string $model): array
    {
        $provider = $provider ?: (array_key_first($providers) ?? '');

        if (empty($provider) || !isset($providers[$provider])) {
            wp_send_json_error(['message' => 'Unknown ' . $type . ' provider: ' . esc_html($provider)]);
        }

        if (empty($model)) {
            $model = array_key_first($providers[$provider]['models'] ?? []) ?? '';
        }

        return [
            'provider' => $provider,
            'model'    => $model,
            'label'    => $providers[$provider]['label'] ?? ucfirst($provider),
        ];
    }

    /**
     * Build the standard generation params array.
     */
    private static function build_generate_params(
        string $prompt,
        string $model,
        ?int   $character_id,
        bool   $compress_image,
        ?string $image_size = 'default',
        int $custom_width = 768,
        int $custom_height = 1024
    ): array {
        $params = [
            'prompt'        => $prompt,
            'model'         => $model,
            'model_id'      => $character_id,
            'save_to_db'    => true,
            'enforce_output_dimensions' => true,
            'source'        => Growtype_Art_Generation_Logger::SOURCE_ADMIN,
            'created_by'    => wp_get_current_user()->user_login,
            'skip_compress' => !$compress_image,
        ];

        if ($image_size === null) {
            return $params;
        }

        if ($image_size === 'auto') {
            $params['enforce_output_dimensions'] = false;
            return $params;
        }

        $presets = Growtype_Art_Admin_Content_Generator::get_image_size_presets();
        $preset  = $presets[$image_size] ?? ($presets['default'] ?? []);

        if ($image_size === 'custom') {
            $preset['width']  = self::normalize_dimension($custom_width, 768);
            $preset['height'] = self::normalize_dimension($custom_height, 1024);
            $preset['aspect_ratio'] = 'custom';
        }

        foreach (['width', 'height', 'aspect_ratio'] as $key) {
            if (isset($preset[$key]) && $preset[$key] !== '') {
                $params[$key] = $preset[$key];
            }
        }

        return $params;
    }

    private static function normalize_dimension(int $value, int $fallback): int
    {
        $value = $value > 0 ? $value : $fallback;
        $value = max(256, min(4096, $value));

        return (int) (round($value / 16) * 16);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX: generate content (text / image / video)
    // ─────────────────────────────────────────────────────────────────────────

    public static function handle_generate_content(): void
    {
        check_ajax_referer('growtype_art_admin', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access.']);
        }

        $_POST = stripslashes_deep($_POST);

        $content_type         = sanitize_text_field($_POST['content_type'] ?? 'text');
        $provider             = sanitize_text_field($_POST['provider'] ?? '');
        $model                = sanitize_text_field($_POST['model']    ?? '');
        $prompt               = sanitize_textarea_field($_POST['prompt'] ?? '');
        $character_id         = !empty($_POST['character_id']) ? (int)$_POST['character_id'] : null;
        $reference_image_url  = !empty($_POST['reference_image_url']) ? esc_url_raw(wp_unslash($_POST['reference_image_url'])) : null;
        $compress_image       = !empty($_POST['compress_image']);
        $remove_background    = !empty($_POST['remove_background']);
        $image_size           = sanitize_key($_POST['image_size'] ?? 'default');
        $custom_width         = absint($_POST['custom_width'] ?? 768);
        $custom_height        = absint($_POST['custom_height'] ?? 1024);

        if (empty($prompt)) {
            wp_send_json_error(['message' => 'Prompt is missing.']);
        }

        switch ($content_type) {

            case 'text':
                $providers = Growtype_Art_Crud::get_text_providers_with_models();
                $resolved  = self::resolve_provider_and_model('text', $providers, $provider, $model);
                $content   = self::generate_text($resolved['provider'], $prompt, $resolved['model']);

                if ($content === null) {
                    wp_send_json_error(['message' => 'Provider returned an empty response. Check your API key in Settings.']);
                }

                wp_send_json_success([
                    'content_type'   => 'text',
                    'content'        => $content,
                    'provider'       => $resolved['provider'],
                    'provider_label' => $resolved['label'],
                    'model'          => $resolved['model'],
                ]);
                break;

            case 'image':
            case 'video':
                self::generate_media($content_type, $provider, $model, $prompt, $character_id, $reference_image_url, $compress_image, $remove_background, $image_size, $custom_width, $custom_height);
                break;

            default:
                wp_send_json_error(['message' => 'Unsupported content type: ' . esc_html($content_type)]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Text generation dispatcher
    // ─────────────────────────────────────────────────────────────────────────

    private static function generate_text(string $provider, string $prompt, string $model): ?string
    {
        $class = self::resolve_provider_class($provider);
        if (!$class) {
            error_log('Growtype Art Generator - Base class not found for provider: ' . $provider);
            return null;
        }

        $instance = new $class();

        if (method_exists($instance, 'generate_chat_content')) {
            return $instance->generate_chat_content($prompt, $model);
        }

        if (method_exists($instance, 'generate_text_content')) {
            return $instance->generate_text_content($prompt, $model);
        }

        error_log('Growtype Art Generator - No text generation method found for: ' . $provider);
        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Media generation (image / video)
    // ─────────────────────────────────────────────────────────────────────────

    private static function generate_media(
        string  $type,
        string  $provider,
        string  $model,
        string  $prompt,
        ?int    $character_id,
        ?string $reference_image_url = null,
        bool    $compress_image = true,
        bool    $remove_background = false,
        string  $image_size = 'default',
        int     $custom_width = 768,
        int     $custom_height = 1024
    ): void {
        $provider_getters = [
            'image' => [Growtype_Art_Crud::class, 'get_image_providers_with_models'],
            'video' => [Growtype_Art_Crud::class, 'get_video_providers_with_models'],
        ];

        if (!isset($provider_getters[$type])) {
            wp_send_json_error(['message' => 'Unsupported media type: ' . esc_html($type)]);
        }

        $providers = call_user_func($provider_getters[$type]);
        $resolved  = self::resolve_provider_and_model($type, $providers, $provider, $model);

        $class = self::resolve_provider_class($resolved['provider']);
        if (!$class) {
            wp_send_json_error(['message' => 'Provider class not found: ' . esc_html($resolved['provider'])]);
        }

        $generate_params = self::build_generate_params(
            $prompt,
            $resolved['model'],
            $character_id,
            $compress_image,
            $type === 'image' ? $image_size : null,
            $custom_width,
            $custom_height
        );

        if ($type === 'image' && $image_size === 'auto' && $resolved['provider'] === 'replicate') {
            $generate_params['_auto_dimensions'] = true;
        }

        if (!empty($reference_image_url)) {
            $generate_params['reference_image_url']  = $reference_image_url;
            $generate_params['reference_image_urls'] = [$reference_image_url];
        }

        $result = (new $class())->generate_image($generate_params);

        if (empty($result['success'])) {
            wp_send_json_error(['message' => $result['message'] ?? ucfirst($type) . ' generation failed.']);
        }

        $urls = array_column($result['generations'] ?? [], 'url');

        if ($remove_background && !empty($result['generations'])) {
            foreach ($result['generations'] as $gen) {
                if (!empty($gen['image_id'])) {
                    growtype_art_remove_image_background($gen['image_id']);
                }
            }
        }

        wp_send_json_success([
            'content_type'   => $type,
            'urls'           => $urls,
            'provider'       => $resolved['provider'],
            'provider_label' => $resolved['label'],
            'model'          => $resolved['model'],
        ]);
    }
}
