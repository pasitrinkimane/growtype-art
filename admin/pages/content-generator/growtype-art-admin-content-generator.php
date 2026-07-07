<?php

defined('ABSPATH') || exit;

/**
 * Growtype_Art_Admin_Content
 *
 * Admin page at: wp-admin/admin.php?page=growtype-art-content
 *
 * Allows selecting a provider + model, entering a prompt, and generating text content.
 */
class Growtype_Art_Admin_Content
{
    const PAGE_NAME = 'growtype-art-content-generator';

    public function __construct()
    {
        add_action('admin_menu',            [$this, 'admin_menu_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_growtype_art_admin_generate_content',   [$this, 'generate_content_callback']);
        add_action('wp_ajax_growtype_art_admin_get_content_models', [$this, 'get_models_callback']);
        add_action('wp_ajax_growtype_art_admin_get_recent',         [$this, 'get_recent_callback']);
        add_action('wp_ajax_growtype_art_admin_search_characters',  [$this, 'search_characters_callback']);
    }

    /**
     * Enqueue WP media library on this page so wp.media() is available for the
     * Reference Image Browse button.
     */
    public function enqueue_scripts(string $hook): void
    {
        if (strpos($hook, self::PAGE_NAME) === false) {
            return;
        }
        wp_enqueue_media();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Menu registration
    // ─────────────────────────────────────────────────────────────────────────

    public function admin_menu_pages()
    {
        add_submenu_page(
            'growtype-art',
            __('Generator', 'growtype-art'),
            __('Generator', 'growtype-art'),
            'manage_options',
            self::PAGE_NAME,
            [$this, 'render_page'],
            85
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX: return models for selected provider
    // ─────────────────────────────────────────────────────────────────────────

    public function get_models_callback()
    {
        check_ajax_referer('growtype_art_admin', '_ajax_nonce');

        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $all      = Growtype_Art_Crud::get_text_providers_with_models();
        $models   = $all[$provider]['models'] ?? [];

        wp_send_json_success(['models' => $models]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // AJAX: return refreshed recent-table HTML
    // ─────────────────────────────────────────────────────────────────────────

    public function get_recent_callback()
    {
        check_ajax_referer('growtype_art_admin', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.']);
        }

        $html = Growtype_Art_Admin_History::render_recent(5);
        wp_send_json_success(['html' => $html]);
    }

    public function search_characters_callback()
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

        $characters = array_map([$this, 'format_character_option'], $characters);

        wp_send_json_success(['characters' => $characters]);
    }

    private function format_character_option(array $character): array
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
    // AJAX: generate content
    // ─────────────────────────────────────────────────────────────────────────

    public function generate_content_callback()
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

        if (empty($prompt)) {
            wp_send_json_error(['message' => 'Prompt is missing.']);
        }

        switch ($content_type) {

            case 'text':
                $all_providers = Growtype_Art_Crud::get_text_providers_with_models();
                $first_key     = array_key_first($all_providers);
                $provider      = $provider ?: ($first_key ?? 'openai');

                if (!isset($all_providers[$provider])) {
                    wp_send_json_error(['message' => 'Unknown text provider: ' . esc_html($provider)]);
                }

                if (empty($model)) {
                    $model = array_key_first($all_providers[$provider]['models'] ?? []) ?? '';
                }

                $content = $this->generate_via_provider($provider, $prompt, $model);

                if ($content === null) {
                    wp_send_json_error(['message' => 'Provider returned an empty response. Check your API key in Settings.']);
                }

                wp_send_json_success([
                    'content_type'   => 'text',
                    'content'        => $content,
                    'provider'       => $provider,
                    'provider_label' => $all_providers[$provider]['label'] ?? ucfirst($provider),
                    'model'          => $model,
                ]);
                break;

            case 'image':
            case 'video':
                $this->generate_media($content_type, $provider, $model, $prompt, $character_id, $reference_image_url);
                break;

            default:
                wp_send_json_error(['message' => 'Unsupported content type: ' . esc_html($content_type)]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Media generation (image / video / future types)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Provider-agnostic media generator (image, video — and any future type
     * that uses generate_image() under the hood).
     *
     * To add a new content type:
     *  1. Register its providers getter in $provider_getters below.
     *  2. Add the type to the JS tab list — no other PHP change needed.
     *
     * Calls wp_send_json_success() / wp_send_json_error() internally and
     * does NOT return — the response is sent immediately.
     */
    private function generate_media(
        string  $type,
        string  $provider,
        string  $model,
        string  $prompt,
        ?int    $character_id,
        ?string $reference_image_url = null
    ): void {
        // ── 1. Providers map — add new types here only ───────────────────────────
        $provider_getters = [
            'image' => [Growtype_Art_Crud::class, 'get_image_providers_with_models'],
            'video' => [Growtype_Art_Crud::class, 'get_video_providers_with_models'],
        ];

        if (!isset($provider_getters[$type])) {
            wp_send_json_error(['message' => 'Unsupported media type: ' . esc_html($type)]);
        }

        // ── 2. Load provider list ────────────────────────────────────────────
        $all_providers = call_user_func($provider_getters[$type]);
        $provider      = $provider ?: (array_key_first($all_providers) ?? '');

        if (empty($provider) || !isset($all_providers[$provider])) {
            wp_send_json_error(['message' => 'Unknown ' . $type . ' provider: ' . esc_html($provider)]);
        }

        if (empty($model)) {
            $model = array_key_first($all_providers[$provider]['models'] ?? []) ?? '';
        }

        // ── 3. Resolve provider class ──────────────────────────────────────────
        $namespaced = sprintf('\partials\%s_Base', ucfirst($provider));
        $plain      = sprintf('%s_Base',           ucfirst($provider));
        $class      = class_exists($namespaced) ? $namespaced : (class_exists($plain) ? $plain : null);

        if (!$class) {
            wp_send_json_error(['message' => 'Provider class not found: ' . esc_html($provider)]);
        }

        // ── 4. Generate & save ───────────────────────────────────────────────
        // save_to_db=true → normal pipeline: saves file, inserts into
        // growtype_art_images, links via growtype_art_model_image (attaches to
        // the character), and fires growtype_art_generation_success which
        // auto-logs to growtype_art_generations via the logger.
        $generate_params = [
            'prompt'     => $prompt,
            'model'      => $model,
            'model_id'   => $character_id,
            'save_to_db' => true,
            'source'     => Growtype_Art_Generation_Logger::SOURCE_ADMIN,
            'created_by' => wp_get_current_user()->user_login,
        ];

        // Pass reference image if provided (img2img / style transfer)
        if (!empty($reference_image_url)) {
            $generate_params['reference_image_url']  = $reference_image_url;
            $generate_params['reference_image_urls'] = [$reference_image_url];
        }

        $result = (new $class())->generate_image($generate_params);

        if (empty($result['success'])) {
            wp_send_json_error(['message' => $result['message'] ?? ucfirst($type) . ' generation failed.']);
        }

        $urls = array_column($result['generations'] ?? [], 'url');

        // ── 5. Respond ──────────────────────────────────────────────────────
        wp_send_json_success([
            'content_type'   => $type,
            'urls'           => $urls,
            'provider'       => $provider,
            'provider_label' => $all_providers[$provider]['label'] ?? ucfirst($provider),
            'model'          => $model,
        ]);
    }



    // ─────────────────────────────────────────────────────────────────────────
    // History logger helper
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Log a generation from the content generator to the history table.
     * Copies the first URL to the history folder so the thumbnail shows up.
     *
     * @param array $args { content_type, prompt, provider, model, model_id, urls }
     */
    private function log_generation(array $args): void
    {
        if (!class_exists('Growtype_Art_Generation_Logger')) {
            return;
        }

        $urls         = $args['urls'] ?? [];
        $first_url    = $urls[0] ?? '';
        $history_url  = '';

        // Copy to history folder (mirrors what the normal pipeline does)
        if (!empty($first_url) && function_exists('growtype_art_get_upload_dir')) {
            $history_dir = growtype_art_get_upload_dir('history');
            if (!file_exists($history_dir)) {
                wp_mkdir_p($history_dir);
            }

            // Try local path mapping first (avoids HTTP round-trip)
            $upload_info = wp_upload_dir();
            $base_url    = $upload_info['baseurl'] ?? '';
            $base_dir    = $upload_info['basedir'] ?? '';
            $local_path  = '';

            if ($base_url && $base_dir && strpos($first_url, $base_url) === 0) {
                $local_path = str_replace($base_url, $base_dir, $first_url);
                if (!file_exists($local_path)) {
                    $local_path = '';
                }
            }

            $ext      = strtolower(pathinfo(parse_url($first_url, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';
            $filename = wp_unique_filename($history_dir, uniqid('gc_') . '.' . $ext);
            $dest     = $history_dir . '/' . $filename;

            if ($local_path) {
                @copy($local_path, $dest);
            } else {
                $resp = wp_remote_get($first_url, ['timeout' => 30, 'sslverify' => false]);
                if (!is_wp_error($resp) && wp_remote_retrieve_response_code($resp) === 200) {
                    file_put_contents($dest, wp_remote_retrieve_body($resp));
                }
            }

            if (file_exists($dest)) {
                $history_url = str_replace($base_dir, $base_url, $dest);
            }
        }

        $current_user = wp_get_current_user();

        Growtype_Art_Generation_Logger::log([
            'model_id'     => $args['model_id'] ?: null,
            'prompt'       => $args['prompt'],
            'provider'     => $args['provider'],
            'status'       => Growtype_Art_Generation_Logger::STATUS_SUCCESS,
            'source'       => Growtype_Art_Generation_Logger::SOURCE_ADMIN,
            'created_by'   => $current_user->user_login ?: null,
            'content_type' => $args['content_type'],
            'meta'         => [
                'asset_type'       => $args['content_type'],
                'model_used'       => $args['model'],
                'history_file_url' => $history_url ?: $first_url,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Generation dispatcher — provider-agnostic
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Resolves the correct *_Base class for the given provider key
     * (same two-step lookup used by Growtype_Art_Crud::get_text_providers_with_models)
     * and delegates to its generate_text_content() method.
     *
     * Adding support for a new provider only requires:
     *   1. A *_Base class (namespaced or plain) with get_text_models() + generate_text_content()
     *   2. Adding the provider key to API_GENERATE_TEXT_PROVIDERS (or via filter)
     *
     * No changes needed here.
     */
    private function generate_via_provider(string $provider, string $prompt, string $model): ?string
    {
        $namespaced = sprintf('\partials\%s_Base', ucfirst($provider));
        $plain      = sprintf('%s_Base',           ucfirst($provider));

        if (class_exists($namespaced)) {
            $instance = new $namespaced();
        } elseif (class_exists($plain)) {
            $instance = new $plain();
        } else {
            error_log('Growtype Art Generator - Base class not found for provider: ' . $provider);
            return null;
        }

        // generate_chat_content() is used by Openai_Base to avoid a signature
        // conflict with Openai_Base_Image::generate_text_content($text, $type).
        // All other providers implement generate_text_content().
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
    // Page render
    // ─────────────────────────────────────────────────────────────────────────

    public function render_page()
    {
        // Collect provider maps for all content types
        $valid_types = ['text', 'image', 'video', 'audio'];
        $providers_by_type = [
            'text'  => Growtype_Art_Crud::get_text_providers_with_models(),
            'image' => Growtype_Art_Crud::get_image_providers_with_models(),
            'video' => Growtype_Art_Crud::get_video_providers_with_models(),
            'audio' => [], // reserved — no audio providers yet
        ];

        // Pre-fill when arriving from History → Reuse button
        $reuse_prompt        = isset($_GET['reuse_prompt'])       ? sanitize_textarea_field(wp_unslash($_GET['reuse_prompt']))   : '';
        $reuse_provider      = isset($_GET['reuse_provider'])     ? sanitize_text_field(wp_unslash($_GET['reuse_provider']))     : '';
        $reuse_model         = isset($_GET['reuse_model'])        ? sanitize_text_field(wp_unslash($_GET['reuse_model']))        : '';
        $reuse_type          = isset($_GET['reuse_type'])         ? sanitize_text_field(wp_unslash($_GET['reuse_type']))         : '';
        $reuse_character_id  = isset($_GET['reuse_character_id']) ? (int)$_GET['reuse_character_id']                            : 0;
        $reuse_image         = isset($_GET['reuse_image'])        ? esc_url_raw(wp_unslash($_GET['reuse_image']))                : '';

        // Fetch all characters for the dropdown — join settings to get character_title
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

        // Build compact JS lookup [{id, label}] with labels truncated to 80 chars
        $characters_json = json_encode(
            array_map(function ($c) {
                return $this->format_character_option($c);
            }, $all_characters),
            JSON_HEX_TAG | JSON_HEX_AMP
        );

        // Find the display label for a pre-selected character (from Reuse button)
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

        // Use the reuse_type as the initial tab if it's valid
        $first_type     = in_array($reuse_type, $valid_types, true) ? $reuse_type : 'image';
        $first_prov_key = array_key_first($providers_by_type[$first_type]) ?? '';
        // If reuse_provider exists in that type's list, use it as the initial key
        if ($reuse_provider && isset($providers_by_type[$first_type][$reuse_provider])) {
            $first_prov_key = $reuse_provider;
        }
        $default_models = $providers_by_type[$first_type][$first_prov_key]['models'] ?? [];
        $nonce          = wp_create_nonce('growtype_art_admin');

        // JSON-encode the full map for JS
        $js_all_providers = json_encode($providers_by_type, JSON_HEX_TAG | JSON_HEX_AMP);

        $this->render_styles();
        ?>

        <div class="wrap" id="gc-wrap">

            <h1 style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
                ✨ <?php esc_html_e('Generator', 'growtype-art'); ?>
            </h1>
            <p style="color:#64748b;margin-top:0;">
                <?php esc_html_e('Select content type, provider and model, enter your prompt, and generate AI-powered content.', 'growtype-art'); ?>
            </p>

            <!-- ── Content type toggle ─────────────────────────────────────── -->
            <div class="gc-type-toggle" id="gc-type-toggle">
                <button type="button" class="gc-type-btn<?php echo $first_type === 'image' ? ' active' : ''; ?>" data-type="image">🖼️ <?php esc_html_e('Image', 'growtype-art'); ?></button>
                <button type="button" class="gc-type-btn<?php echo $first_type === 'text'  ? ' active' : ''; ?>" data-type="text">✍️  <?php esc_html_e('Text',  'growtype-art'); ?></button>
                <button type="button" class="gc-type-btn<?php echo $first_type === 'video' ? ' active' : ''; ?>" data-type="video">🎬 <?php esc_html_e('Video', 'growtype-art'); ?></button>
                <button type="button" class="gc-type-btn<?php echo $first_type === 'audio' ? ' active' : ''; ?>" data-type="audio">🎵 <?php esc_html_e('Audio', 'growtype-art'); ?></button>
            </div>

            <!-- ── Generator form ──────────────────────────────────────────── -->
            <div class="gc-card">

                <!-- Character autocomplete -->
                <div class="gc-field" style="margin-bottom:16px;">
                    <label class="gc-label" for="gc-character-search">
                        <?php esc_html_e('Character', 'growtype-art'); ?>
                    </label>
                    <div class="gc-ac-wrap" id="gc-character-wrap">
                        <input type="text"
                               id="gc-character-search"
                               class="gc-input gc-ac-input"
                               placeholder="<?php esc_attr_e('Search characters…', 'growtype-art'); ?>"
                               autocomplete="off"
                               value="<?php echo esc_attr($reuse_character_label); ?>">
                        <input type="hidden" id="gc-character" value="<?php echo (int)$reuse_character_id; ?>">
                        <div id="gc-character-dropdown" class="gc-ac-dropdown" style="display:none;"></div>
                    </div>
                </div>

                <!-- Reference image (optional) -->
                <div class="gc-field gc-ref-image-field" id="gc-ref-image-wrap" style="margin-bottom:16px;">
                    <label class="gc-label">
                        <?php esc_html_e('Reference Image', 'growtype-art'); ?>
                        <span class="gc-label-hint"><?php esc_html_e('optional', 'growtype-art'); ?></span>
                    </label>
                    <div class="gc-ref-input-row">
                        <input type="text"
                               id="gc-reference-image-url"
                               class="gc-input"
                               placeholder="<?php esc_attr_e('Paste image URL or click Browse…', 'growtype-art'); ?>"
                               value="<?php echo esc_attr($reuse_image); ?>"
                               autocomplete="off">
                        <button type="button" id="gc-ref-browse" class="gc-btn-secondary gc-ref-browse-btn">
                            📁 <?php esc_html_e('Browse', 'growtype-art'); ?>
                        </button>
                    </div>
                    <div class="gc-ref-preview" id="gc-ref-preview"<?php if (!$reuse_image) echo ' style="display:none;"'; ?>>
                        <img id="gc-ref-thumb"
                             src="<?php echo esc_url($reuse_image); ?>"
                             alt="reference"
                             class="gc-ref-thumb"
                             onerror="this.style.opacity='.3'">
                        <div class="gc-ref-meta">
                            <span class="gc-ref-label" id="gc-ref-label"><?php echo esc_html($reuse_image ? basename(parse_url($reuse_image, PHP_URL_PATH)) : ''); ?></span>
                            <button type="button" id="gc-ref-remove" class="gc-ref-remove">✕ <?php esc_html_e('Remove', 'growtype-art'); ?></button>
                        </div>
                    </div>
                </div>

                <div class="gc-form-row">

                    <!-- Provider select -->
                    <div class="gc-field">
                        <label class="gc-label" for="gc-provider">
                            <?php esc_html_e('Provider', 'growtype-art'); ?>
                        </label>
                        <select id="gc-provider" class="gc-select">
                            <?php foreach ($providers_by_type[$first_type] as $key => $prov_data) : ?>
                                <option value="<?php echo esc_attr($key); ?>"
                                    <?php selected($key, $first_prov_key); ?>>
                                    <?php echo esc_html($prov_data['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Model input (datalist = free-text + registered suggestions) -->
                    <div class="gc-field">
                        <label class="gc-label" for="gc-model">
                            <?php esc_html_e('Model', 'growtype-art'); ?>
                        </label>
                        <input type="text"
                               id="gc-model"
                               class="gc-select"
                               list="gc-model-list"
                               placeholder="<?php esc_attr_e('Select or type a model slug…', 'growtype-art'); ?>"
                               autocomplete="off"
                               value="<?php echo esc_attr(array_key_first($default_models) ?? ''); ?>">
                        <datalist id="gc-model-list">
                            <?php foreach ($default_models as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                </div>

                <!-- No-providers notice (hidden by default) -->
                <div id="gc-no-providers" style="display:none;margin-top:14px;padding:12px 16px;background:#fef9c3;border:1px solid #fde047;border-radius:8px;font-size:13px;color:#713f12;">
                    <?php esc_html_e('No providers available for this content type yet.', 'growtype-art'); ?>
                </div>

                <!-- Prompt textarea -->
                <div class="gc-field" style="margin-top:16px;">
                    <label class="gc-label" for="gc-prompt">
                        <?php esc_html_e('Prompt', 'growtype-art'); ?>
                    </label>
                    <textarea id="gc-prompt" class="gc-textarea"
                              placeholder="<?php esc_attr_e('Enter your prompt here…', 'growtype-art'); ?>"
                              rows="6"></textarea>
                </div>

                <!-- Actions -->
                <div class="gc-actions">
                    <button id="gc-generate-btn" type="button" class="gc-btn-primary">
                        <span class="gc-btn-icon">⚡</span>
                        <span id="gc-btn-label"><?php esc_html_e('Generate', 'growtype-art'); ?></span>
                    </button>
                    <button id="gc-clear-btn" type="button" class="gc-btn-secondary">
                        <?php esc_html_e('Clear', 'growtype-art'); ?>
                    </button>
                </div>

            </div><!-- .gc-card -->

            <!-- ── Result area ─────────────────────────────────────────────── -->
            <!-- ── Loading overlay ────────────────────────────────────────── -->
            <div id="gc-loading" style="display:none;" class="gc-loading-overlay">
                <div class="gc-spinner"></div>
                <p><?php esc_html_e('Generating…', 'growtype-art'); ?></p>
            </div>

            <!-- ── Recent Generations ──────────────────────────────────────── -->
            <div id="gc-recent-wrap" class="gc-card gc-recent-card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                    <h2 style="margin:0;font-size:14px;font-weight:700;color:#1e293b;">
                        🕐 <?php esc_html_e('Recent Generations', 'growtype-art'); ?>
                    </h2>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . Growtype_Art_Admin_History::PAGE_NAME)); ?>"
                       style="font-size:12px;color:#6366f1;text-decoration:none;font-weight:600;">
                        <?php esc_html_e('View all →', 'growtype-art'); ?>
                    </a>
                </div>
                <div id="gc-recent-table">
                    <?php echo Growtype_Art_Admin_History::render_recent(5); ?>
                </div>
            </div>

            <!-- Shared gh-* table styles & modal/copy-btn JS -->
            <?php Growtype_Art_Admin_History::render_table_styles(); ?>
            <?php Growtype_Art_Admin_History::render_table_scripts(); ?>

            <!-- Recent card overflow wrapper -->
            <style>.gc-recent-card { overflow-x: auto; }</style>

        </div><!-- #gc-wrap -->

        <script>
        (function ($) {
            var nonce         = '<?php echo $nonce; ?>';
            var ajaxUrl       = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
            // { text: { provider: {label, models} }, image: {...}, video: {...}, audio: {} }
            var allProviders  = <?php echo $js_all_providers; ?>;
            var gcCharacters  = <?php echo $characters_json; ?>; // [{id, label}]
            var currentType   = '<?php echo esc_js($first_type); ?>';

            // ── Helpers ──────────────────────────────────────────────────────
            function getProvidersForType(type) {
                return allProviders[type] || {};
            }

            function repopulateProviders(type) {
                var provs  = getProvidersForType(type);
                var keys   = Object.keys(provs);
                var $pSel  = $('#gc-provider').empty();
                var $row   = $('.gc-form-row');
                var $none  = $('#gc-no-providers');

                if (!keys.length) {
                    $row.hide();
                    $none.show();
                    return;
                }

                $none.hide();
                $row.show();

                $.each(provs, function (key, data) {
                    $pSel.append('<option value="' + key + '">' + data.label + '</option>');
                });

                repopulateModels(keys[0]);
            }

            function repopulateModels(provider) {
                var provs  = getProvidersForType(currentType);
                var models = (provs[provider] && provs[provider].models) ? provs[provider].models : {};
                var $list  = $('#gc-model-list').empty();
                var $input = $('#gc-model');
                var keys   = Object.keys(models);

                $.each(models, function (key, label) {
                    $list.append('<option value="' + key + '">' + label + '</option>');
                });

                // Only reset value if current value isn't in new provider's list
                // (preserves reuse-injected values when switching providers)
                if (keys.length && !models[$input.val()]) {
                    $input.val(keys[0]);
                } else if (!keys.length) {
                    $input.val('');
                }
            }

            // ── Type toggle ──────────────────────────────────────────────────
            $('#gc-type-toggle .gc-type-btn').on('click', function () {
                var type = $(this).data('type');
                currentType = type;

                $(this).addClass('active').siblings().removeClass('active');
                repopulateProviders(type);
            });

            // ── Provider change ──────────────────────────────────────────────
            $('#gc-provider').on('change', function () {
                repopulateModels($(this).val());
            });

            // ── Reference image ───────────────────────────────────────────────
            (function () {
                var $urlInput = $('#gc-reference-image-url');
                var $preview  = $('#gc-ref-preview');
                var $thumb    = $('#gc-ref-thumb');
                var $label    = $('#gc-ref-label');

                function applyUrl(url) {
                    url = (url || '').trim();
                    $urlInput.val(url);
                    if (url) {
                        $thumb.attr('src', url);
                        try {
                            var parts = url.split('/');
                            $label.text(decodeURIComponent(parts[parts.length - 1] || url));
                        } catch(e) { $label.text(url); }
                        $preview.slideDown(150);
                    } else {
                        $preview.slideUp(150);
                        $thumb.attr('src', '');
                    }
                }

                // Live URL input
                $urlInput.on('input change', function () { applyUrl($(this).val()); });

                // Remove button
                $('#gc-ref-remove').on('click', function () { applyUrl(''); });

                // WP Media Library browse
                $('#gc-ref-browse').on('click', function () {
                    if (typeof wp === 'undefined' || !wp.media) {
                        var url = prompt('Paste image URL:');
                        if (url) applyUrl(url);
                        return;
                    }
                    var frame = wp.media({
                        title:    'Select Reference Image',
                        button:   { text: 'Use this image' },
                        multiple: false,
                        library:  { type: 'image' }
                    });
                    frame.on('select', function () {
                        var att = frame.state().get('selection').first().toJSON();
                        applyUrl(att.url);
                    });
                    frame.open();
                });
            }());

            // ── Character autocomplete ────────────────────────────────────────
            (function () {
                var $search   = $('#gc-character-search');
                var $hidden   = $('#gc-character');
                var $dropdown = $('#gc-character-dropdown');
                var focused   = -1;
                var searchTimer = null;
                var searchRequestId = 0;

                function renderItems(items) {
                    $dropdown.empty();
                    if (!items.length) { $dropdown.hide(); return; }
                    items.forEach(function (c, i) {
                        $('<div class="gc-ac-item">')
                            .text('ID: ' + c.id + ' \u2014 ' + c.label)
                            .attr('data-id', c.id)
                            .toggleClass('active', i === focused)
                            .on('mousedown', function (e) { e.preventDefault(); selectChar(c); })
                            .appendTo($dropdown);
                    });
                    $dropdown.show();
                }

                function filter(q) {
                    focused = -1;
                    if (!q) { $hidden.val(0); $dropdown.hide(); return; }
                    var ql = q.toLowerCase();
                    var matches = gcCharacters.filter(function (c) {
                        return String(c.id).indexOf(ql) !== -1
                            || c.label.toLowerCase().indexOf(ql) !== -1
                            || (c.slug && c.slug.toLowerCase().indexOf(ql) !== -1);
                    }).slice(0, 20);
                    renderItems(matches);

                    if (matches.length || q.length < 2) {
                        return;
                    }

                    clearTimeout(searchTimer);
                    var requestId = ++searchRequestId;

                    searchTimer = setTimeout(function () {
                        $.post(ajaxUrl, {
                            action: 'growtype_art_admin_search_characters',
                            _ajax_nonce: nonce,
                            q: q
                        }, function (res) {
                            if (requestId !== searchRequestId || $search.val() !== q) {
                                return;
                            }

                            if (res.success && res.data && Array.isArray(res.data.characters)) {
                                renderItems(res.data.characters);
                            }
                        });
                    }, 180);
                }

                function selectChar(c) {
                    $hidden.val(c.id);
                    $search.val('ID: ' + c.id + ' \u2014 ' + c.label);
                    $dropdown.hide();
                    focused = -1;
                }

                $search.on('input', function () { filter($(this).val()); });
                $search.on('focus', function () { if ($(this).val()) filter($(this).val()); });
                $search.on('keyup', function () { if (!$(this).val()) $hidden.val(0); });
                $search.on('keydown', function (e) {
                    var $items = $dropdown.find('.gc-ac-item');
                    if (e.key === 'ArrowDown') { focused = Math.min(focused + 1, $items.length - 1); $items.removeClass('active').eq(focused).addClass('active'); e.preventDefault(); }
                    else if (e.key === 'ArrowUp') { focused = Math.max(focused - 1, -1); $items.removeClass('active').eq(focused).addClass('active'); e.preventDefault(); }
                    else if (e.key === 'Enter' && focused >= 0) { $items.eq(focused).trigger('mousedown'); e.preventDefault(); }
                    else if (e.key === 'Escape') { $dropdown.hide(); }
                });
                $(document).on('click', function (e) {
                    if (!$(e.target).closest('#gc-character-wrap').length) $dropdown.hide();
                });
            }());

            // ── Generate ─────────────────────────────────────────────────────
            $('#gc-generate-btn').on('click', function () {
                var prompt = $('#gc-prompt').val().trim();
                if (!prompt) { alert('Please enter a prompt.'); return; }

                var provider = $('#gc-provider').val();
                var model    = $('#gc-model').val();

                $('#gc-loading').fadeIn(150);
                $('#gc-generate-btn').prop('disabled', true);

                $.ajax({
                    type: 'POST',
                    url:  ajaxUrl,
                    data: {
                        action:       'growtype_art_admin_generate_content',
                        _ajax_nonce:  nonce,
                        content_type: currentType,
                        provider:     provider,
                        model:        model,
                        prompt:       prompt,
                        character_id: parseInt($('#gc-character').val()) || 0,
                        reference_image_url: $('#gc-reference-image-url').val() || '',
                    },
                    success: function (res) {
                        $('#gc-loading').fadeOut(150);
                        $('#gc-generate-btn').prop('disabled', false);

                        if (!res.success) {
                            showNotice('error', res.data && res.data.message ? res.data.message : 'Something went wrong.');
                            return;
                        }

                        showNotice('success', '✅ Generated successfully!');

                        // Refresh the recent-table in-place
                        $.post(ajaxUrl, { action: 'growtype_art_admin_get_recent', _ajax_nonce: nonce }, function (r) {
                            if (r.success && r.data.html) {
                                $('#gc-recent-table').html(r.data.html);
                                $('html, body').animate({ scrollTop: $('#gc-recent-wrap').offset().top - 40 }, 300);
                            }
                        });
                    },
                    error: function () {
                        $('#gc-loading').fadeOut(150);
                        $('#gc-generate-btn').prop('disabled', false);
                        showNotice('error', 'Request failed. Check your connection.');
                    }
                });
            });

            // ── Clear ─────────────────────────────────────────────────────────
            $('#gc-clear-btn').on('click', function () {
                $('#gc-prompt').val('').focus();
                // Also reset reference image via the existing input handler
                $('#gc-reference-image-url').val('').trigger('input');
            });


            // ── Ctrl+Enter shortcut ───────────────────────────────────────────
            $('#gc-prompt').on('keydown', function (e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') $('#gc-generate-btn').trigger('click');
            });

            // ── Notice helper ─────────────────────────────────────────────────
            function showNotice(type, msg) {
                var bg = type === 'success' ? '#16a34a' : '#dc2626';
                $('.gc-notice-toast').remove();
                $('body').append('<div class="gc-notice-toast" style="position:fixed;top:50px;right:24px;background:' + bg + ';color:#fff;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:600;z-index:99999;box-shadow:0 4px 14px rgba(0,0,0,.2);">' + msg + '</div>');
                $('.gc-notice-toast').hide().fadeIn(200).delay(2500).fadeOut(300, function () { $(this).remove(); });
            }

            // ── Pre-fill from History → Reuse button ────────────────────────────
            var reusePrompt   = <?php echo json_encode($reuse_prompt); ?>;
            var reuseProvider = <?php echo json_encode($reuse_provider); ?>;
            var reuseModel    = <?php echo json_encode($reuse_model); ?>;
            var reuseType     = <?php echo json_encode($first_type); ?>;

            if (reusePrompt || reuseType !== 'text') {
                // 1. Activate the correct type tab (triggers provider/model repopulation)
                var $tab = $('#gc-type-toggle .gc-type-btn[data-type="' + reuseType + '"]');
                if ($tab.length) {
                    $tab.trigger('click');
                }

                // 2. After providers are repopulated, select the right provider
                if (reuseProvider && $('#gc-provider option[value="' + reuseProvider + '"]').length) {
                    $('#gc-provider').val(reuseProvider);
                    repopulateModels(reuseProvider);
                }

                // 3. Select the right model.
                // Input is now a free-text datalist field — just set the value directly.
                if (reuseModel) {
                    // Also add to datalist if not already suggested
                    if (!$('#gc-model-list option[value="' + reuseModel + '"]').length) {
                        $('#gc-model-list').append(new Option(reuseModel, reuseModel));
                    }
                    $('#gc-model').val(reuseModel);
                }

                // 4. Pre-select character
                var reuseCharId = <?php echo (int)$reuse_character_id; ?>;
                if (reuseCharId) {
                    $('#gc-character').val(reuseCharId);
                    var matched = gcCharacters.find(function (c) { return c.id === reuseCharId; });
                    if (matched) {
                        $('#gc-character-search').val('ID: ' + matched.id + ' — ' + matched.label);
                    }
                }

                // 5. Fill prompt
                if (reusePrompt) {
                    $('#gc-prompt').val(reusePrompt);
                }

                // 6. Scroll to form
                setTimeout(function () {
                    $('html, body').animate({ scrollTop: $('#gc-prompt').offset().top - 60 }, 250);
                    $('#gc-prompt').focus();
                }, 150);
            }
        }(jQuery));
        </script>

        <?php
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Styles
    // ─────────────────────────────────────────────────────────────────────────

    private function render_styles()
    {
        ?>
        <style>
        /* ── Card ──────────────────────────────────────── */
        .gc-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px 28px;
            margin-bottom: 20px;
            box-shadow: 0 1px 6px rgba(0,0,0,.05);
        }

        /* ── Form row ──────────────────────────────────── */
        .gc-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 640px) {
            .gc-form-row { grid-template-columns: 1fr; }
        }

        /* ── Field ─────────────────────────────────────── */
        .gc-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .gc-label {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .gc-select,
        .gc-textarea {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            color: #1e293b;
            background: #f8fafc;
            transition: border-color .15s, background .15s;
            width: 100%;
            box-sizing: border-box;
        }
        .gc-select { height: 38px; }
        .gc-select:focus,
        .gc-textarea:focus {
            outline: none;
            border-color: #6366f1;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(99,102,241,.12);
        }

        /* ── Character autocomplete ─────────────────────── */
        .gc-ac-wrap {
            position: relative;
        }
        .gc-input {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            color: #1e293b;
            background: #f8fafc;
            transition: border-color .15s, background .15s;
            width: 100%;
            box-sizing: border-box;
            height: 38px;
        }
        .gc-input:focus {
            outline: none;
            border-color: #6366f1;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(99,102,241,.12);
        }
        .gc-ac-dropdown {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0,0,0,.10);
            z-index: 9999;
            max-height: 240px;
            overflow-y: auto;
        }
        .gc-ac-item {
            padding: 9px 13px;
            font-size: 12px;
            color: #374151;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: background .1s;
        }
        .gc-ac-item:last-child { border-bottom: none; }
        .gc-ac-item:hover,
        .gc-ac-item.active {
            background: #eef2ff;
            color: #4338ca;
        }

        /* ── Reference image preview ────────────────────── */
        .gc-label-hint {
            font-size: 11px;
            font-weight: 400;
            color: #94a3b8;
            margin-left: 6px;
        }
        .gc-ref-input-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .gc-ref-input-row .gc-input { flex: 1; }
        .gc-ref-browse-btn {
            flex-shrink: 0;
            white-space: nowrap;
            height: 38px;
            padding: 0 14px;
            font-size: 12px;
        }
        .gc-ref-preview {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            margin-top: 10px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        .gc-ref-thumb {
            width: 56px;
            height: 56px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            flex-shrink: 0;
        }
        .gc-ref-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 0;
        }
        .gc-ref-label {
            font-size: 11px;
            color: #64748b;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 260px;
        }
        .gc-ref-remove {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 10px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 5px;
            border: 1px solid #fca5a5;
            background: #fff1f2;
            color: #dc2626;
            cursor: pointer;
            transition: background .12s, border-color .12s;
            width: fit-content;
        }
        .gc-ref-remove:hover { background: #fee2e2; border-color: #ef4444; }

        .gc-textarea {
            resize: vertical;
            min-height: 130px;
            font-family: inherit;
            line-height: 1.6;
        }

        /* ── Actions ───────────────────────────────────── */
        .gc-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 18px;
        }
        .gc-btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 0 20px;
            height: 40px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background .15s, transform .1s;
        }
        .gc-btn-primary:hover:not(:disabled) { background: #4f46e5; }
        .gc-btn-primary:active:not(:disabled) { transform: scale(.97); }
        .gc-btn-primary:disabled { opacity: .55; cursor: not-allowed; }
        .gc-btn-secondary {
            background: none;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 16px;
            height: 40px;
            font-size: 13px;
            color: #475569;
            cursor: pointer;
            transition: background .12s, border-color .12s;
        }
        .gc-btn-secondary:hover { background: #f1f5f9; border-color: #94a3b8; }
        .gc-btn-icon { font-size: 15px; }

        .gc-btn-icon-only {
            background: none;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
            color: #64748b;
            transition: background .12s, border-color .12s;
        }
        .gc-btn-icon-only:hover { background: #f1f5f9; border-color: #94a3b8; }

        /* ── Result card ───────────────────────────────── */
        .gc-result-card { border-color: #c7d2fe; }
        .gc-result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }
        .gc-result-title {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
        }
        .gc-result-meta {
            margin-left: 8px;
            font-size: 11px;
            color: #fff;
            background: #6366f1;
            padding: 2px 9px;
            border-radius: 50px;
            font-weight: 600;
        }
        .gc-result-content {
            font-size: 13px;
            line-height: 1.8;
            color: #1e293b;
            white-space: pre-wrap;
            word-break: break-word;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px 18px;
            max-height: 520px;
            overflow-y: auto;
        }
        .gc-word-count {
            margin-top: 8px;
            font-size: 11px;
            color: #94a3b8;
            text-align: right;
        }

        /* ── Loading overlay ───────────────────────────── */
        .gc-loading-overlay {
            position: fixed;
            inset: 0;
            background: rgba(255,255,255,.7);
            z-index: 9999;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            color: #6366f1;
            font-weight: 600;
            gap: 14px;
        }
        .gc-spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #e0e7ff;
            border-top-color: #6366f1;
            border-radius: 50%;
            animation: gc-spin .7s linear infinite;
        }
        @keyframes gc-spin { to { transform: rotate(360deg); } }

        /* ── Content-type toggle ───────────────────────── */
        .gc-type-toggle {
            display: flex;
            gap: 6px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .gc-type-btn {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 7px 18px;
            border: 1.5px solid #e2e8f0;
            border-radius: 50px;
            background: #fff;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: background .15s, border-color .15s, color .15s, box-shadow .15s;
            white-space: nowrap;
        }
        .gc-type-btn:hover {
            background: #f1f5f9;
            border-color: #94a3b8;
            color: #334155;
        }
        .gc-type-btn.active {
            background: #6366f1;
            border-color: #6366f1;
            color: #fff;
            box-shadow: 0 2px 8px rgba(99,102,241,.25);
        }

        /* ── Media result grids ─────────────────────────── */
        .gc-result-media {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            padding: 8px 0;
        }
        .gc-result-img {
            max-width: 260px;
            width: 100%;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            object-fit: cover;
            transition: transform .15s;
        }
        .gc-result-img:hover { transform: scale(1.02); }
        .gc-result-video {
            max-width: 420px;
            width: 100%;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        </style>
        <?php
    }
}
