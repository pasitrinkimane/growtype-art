<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Growtype_Art
 * @subpackage growtype_art/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Growtype_Art
 * @subpackage growtype_art/admin
 * @author     Your Name <email@example.com>
 */
class Growtype_Art_Admin
{
    const DELETE_NONCE = 'growtype_art_delete_item';
    const SETTINGS_PAGE_NAME = 'growtype-art-settings';
    const MODELS_PAGE_NAME = 'growtype-art-models';
    const POST_TYPE = 'growtype_art_models';
    const SETTINGS_DEFAULT_TAB = 'general';

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $growtype_art The ID of this plugin.
     */
    private $growtype_art;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    /**
     * Traits
     */

    /**
     * Initialize the class and set its properties.
     *
     * @param string $growtype_art The name of this plugin.
     * @param string $version The version of this plugin.
     * @since    1.0.0
     */
    public function __construct($growtype_art, $version)
    {
        $this->growtype_art = $growtype_art;
        $this->version = $version;

        if (is_admin()) {
            /**
             * Load methods
             */
            add_action('init', array ($this, 'add_pages'));

            add_action('wp_ajax_growtype_art_admin_generate_character_ideas', array($this, 'generate_character_ideas_callback'));
            
            add_action('wp_ajax_growtype_art_admin_generate_single_character', array($this, 'generate_single_character_callback'));

            add_action('wp_ajax_growtype_art_admin_delete_model', array($this, 'delete_model_callback'));
        }
    }

    /**
     * @return void
     */
    function delete_model_callback()
    {
        check_ajax_referer('growtype_art_admin', '_ajax_nonce');

        $model_id = intval($_POST['model_id'] ?? 0);

        if (empty($model_id)) {
            wp_send_json_error(['message' => 'Model ID is missing.']);
        }

        Growtype_Art_Database_Crud::delete_records(Growtype_Art_Database::MODELS_TABLE, $model_id);
        growtype_art_admin_update_bundle_keys([$model_id], 'remove');

        wp_send_json_success(['message' => 'Character deleted.']);
    }

    /**
     * @return void
     */
    function generate_single_character_callback()
    {
        check_ajax_referer('growtype_art_admin', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access.']);
        }

        $_POST = stripslashes_deep($_POST);

        $prompt = $_POST['prompt'] ?? $_POST['name'] ?? '';
        $style = $_POST['style'] ?? 'realistic';
        $prompt_focus    = $_POST['prompt_focus'] ?? 'single';
        $provider        = $_POST['provider'] ?? Growtype_Art_Crud::DEFAULT_IMAGE_PROVIDER;
        $theme_hint      = $_POST['theme_hint'] ?? '';
        $categories      = $_POST['categories'] ?? [];

        if (empty($prompt)) {
            wp_send_json_error(['message' => 'Prompt is missing.']);
        }


        // Parse featured_in from POST (multi-select sends featured_in[] array)
        $featured_in = [];
        if (!empty($_POST['featured_in']) && is_array($_POST['featured_in'])) {
            $featured_in = array_map('sanitize_text_field', $_POST['featured_in']);
        }

        // Ask LLM to derive all character details from the prompt in one shot.
        $llm_params = growtype_art_generate_character_params_from_prompt($prompt, $style, $featured_in, $provider, $prompt_focus, $theme_hint);

        // Allow manual override if provided (from the "Adjust Details" modal)
        if (!empty($_POST['character_details_override'])) {
            $override = is_array($_POST['character_details_override']) ? $_POST['character_details_override'] : json_decode($_POST['character_details_override'], true);
            if (is_array($override)) {
                $llm_params = array_merge($llm_params ?: [], $override);
            }
        }

        if (empty($llm_params)) {
            wp_send_json_error(['message' => 'LLM failed to generate character details.']);
        }

        $character_title = $llm_params['character_title'] ?? '';
        
        // Calculate base slug without auto-suffix to check for duplicates
        $clean_string = preg_replace('/[^\w\s-]/', '', $character_title);
        $base_slug = strtolower(str_replace(' ', '-', $clean_string));
        
        $overwrite = !empty($_POST['overwrite']);
        $force_new = !empty($_POST['force_new']);
        
        global $wpdb;
        $matching_slugs = $wpdb->get_results($wpdb->prepare(
            "SELECT model_id FROM " . $wpdb->prefix . Growtype_Art_Database::MODEL_SETTINGS_TABLE . " WHERE meta_key = 'slug' AND (meta_value = %s OR meta_value LIKE %s)",
            $base_slug,
            $wpdb->esc_like($base_slug) . '%'
        ), ARRAY_A);
        $existing_model_id = !empty($matching_slugs) ? $matching_slugs[0]['model_id'] : null;

        if ($existing_model_id && !$overwrite && !$force_new) {
            $existing_characters = [];
            foreach ($matching_slugs as $row) {
                $mid = $row['model_id'];
                $det = growtype_art_get_model_details($mid);
                $settings = $det['settings'] ?? [];
                
                $image_url = '';
                $images = growtype_art_get_model_images_grouped($mid, 1);
                $latest_image = !empty($images['original']) ? reset($images['original']) : null;
                if ($latest_image) {
                    $image_url = growtype_art_get_image_url($latest_image['id']);
                }

                $existing_characters[] = [
                    'id'        => $mid,
                    'title'     => $settings['character_title'] ?? '(untitled)',
                    'slug'      => $settings['slug'] ?? '',
                    'image_url' => $image_url,
                ];
            }
            wp_send_json_error([
                'code'              => 'duplicate_slug',
                'existing'          => $existing_characters,
                'generated_details' => $llm_params,
            ]);
        }

        // Determine the final slug based on user choice
        if ($overwrite && $existing_model_id) {
            $slug = $base_slug;
        } else {
            // This helper will automatically add a suffix if the base slug exists
            $slug = growtype_art_format_character_slug($character_title);
        }

        // Build the enriched prompt (LLM may refine it).
        $final_prompt = $llm_params['prompt'] ?? $prompt;


        $tags = $llm_params['character_tags'] ?? '';
        if (!empty($theme_hint)) {
            $tag_list = array_map('trim', explode(',', $tags));
            if (!in_array(strtolower($theme_hint), array_map('strtolower', $tag_list))) {
                $tags = !empty($tags) ? $tags . ', ' . $theme_hint : $theme_hint;
            }
        }

        $create_params = [
            'prompt'                    => $final_prompt,
            'character_title'           => $character_title,
            'generate_images_initially' => true,
            'provider'                  => $provider,
            'created_by'                => $_POST['created_by'] ?? 'admin',
            'in_bundle'                 => false,
            'featured_in'               => $featured_in,
            'character_details'         => $llm_params,
            'slug'                      => $slug,
            'tags'                      => $tags,
            'categories'                => $categories,
        ];

        if ($overwrite && $existing_model_id) {
            $create_params['model_id'] = $existing_model_id;
        }

        $result = Growtype_Art_Api_Character::create_character($create_params);

        if (empty($result['success'])) {
            wp_send_json_error(['message' => $result['message'] ?? 'Character creation failed.']);
        }

        $model_id = $result['model_id'] ?? null;
        $details = $result['character_details'] ?? [];
        
        // Fetch the generated image URL
        $image_url = '';
        if ($model_id) {
            $images = growtype_art_get_model_images_grouped($model_id, 1);
            $latest_image = !empty($images['original']) ? reset($images['original']) : null;
            if ($latest_image) {
                $image_url = growtype_art_get_image_url($latest_image['id']);
            }
        }

        wp_send_json_success([
            'title'         => $details['character_title']   ?? $llm_params['character_title'] ?? $prompt,
            'occupation'    => $details['character_occupation'] ?? '',
            'description'   => $details['character_description'] ?? '',
            'model_id'      => $model_id,
            'image_url'     => $image_url,
            'slug'          => $slug,
            'create_params' => $create_params,
        ]);
    }

    /**
     * @return void
     */
    function generate_character_ideas_callback()
    {
        check_ajax_referer('growtype_art_admin', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized access.']);
        }

        $_POST = stripslashes_deep($_POST);

        $style = $_POST['style'] ?? 'realistic';
        $theme = $_POST['theme'] ?? 'popular and trending';
        $prompt_focus = $_POST['prompt_focus'] ?? 'single';
        $gen_template = $_POST['gen_template'] ?? 'default';

        $api_key = Openai_Base::api_key();

        if (empty($api_key)) {
            wp_send_json_error(['message' => 'OpenAI API key is missing. Please check your settings.']);
        }

        if ($prompt_focus === 'multiple') {
            if ($gen_template === 'universe') {
                $prompt = "Generate ONE specific example of characters from the same universe using the format 'Universe Name: Character 1, Character 2, Character 3'. Theme: $theme. Style: $style. Return ONLY the formatted string (e.g. 'Marvel Cinematic Universe: Iron Man, Captain America, Thor'). No extra text.";
            } else {
                $prompt = "Generate a list of 10-15 famous group names or duo/trio/team concepts for AI art generation (e.g. 'The Avengers', 'Charlie's Angels', 'Power Rangers'). Style: $style. Theme: $theme. Return a comma separated list of group names only, no descriptions.";
            }
        } else {
            $theme_context = !empty($theme) ? "Theme: $theme." : '';
            $prompt = "Write exactly ONE AI image generation prompt for a single character. Format: [Character name] in a [art style], [atmospheric/symbolic details], [background description], [color palette], [lighting], [quality tags]. $theme_context Style: $style. Return only the prompt text. No lists. No numbering. No alternatives. No extra text.";
        }
        
        $response = Openai_Base::generate_content($prompt);

        if (empty($response)) {
            wp_send_json_error(['message' => 'OpenAI returned an empty response.']);
        }

        wp_send_json_success(['ideas' => $response]);
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        wp_enqueue_style($this->growtype_art, plugin_dir_url(__FILE__) . 'css/growtype-art-admin.css', array (), time(), 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script($this->growtype_art, plugin_dir_url(__FILE__) . 'js/growtype-art-admin.js', array ('jquery'), time(), false);
    }

    /**
     * Load the required methods for this plugin.
     *
     */
    public function add_pages()
    {
        /**
         * Plugin settings
         */
        require GROWTYPE_ART_PATH . '/admin/pages/growtype-art-admin-pages.php';
        new Growtype_Art_Admin_Pages();
    }
}
