<?php

use partials\Leonardoai_Base;

require GROWTYPE_ART_PATH . '/vendor/autoload.php';

class Growtype_Art_Api_Character
{
    const FEATURED_IN_CHARACTERS_TRANSIENT_KEY = 'growtype_art_api_featured_in_characters';

    public function __construct()
    {
        $this->load_methods();

        add_action('rest_api_init', array (
            $this,
            'register_routes'
        ));

        /**
         * Model actions
         */
        add_action('growtype_art_model_update', array ($this, 'growtype_art_model_adjust_callback'));
        add_action('growtype_art_model_delete', array ($this, 'growtype_art_model_adjust_callback'));

        /**
         * Image actions
         */
        add_action('growtype_art_model_image_delete', array ($this, 'growtype_art_image_adjust_callback'));
        add_action('growtype_art_model_image_update', array ($this, 'growtype_art_image_adjust_callback'));
        add_action('growtype_art_model_image_save', array ($this, 'growtype_art_image_adjust_callback'));
    }

    function load_methods()
    {
    }

    function register_routes()
    {
        register_rest_route('growtype-art/v1', '/retrieve/characters/(?P<featured_in>[a-zA-Z0-9_-]+)/', array (
            'methods' => WP_REST_Server::READABLE,
            'callback' => array ($this, 'retrieve_characters_callback'),
            'permission_callback' => array ($this, 'permission_check_callback')
        ));

        register_rest_route('growtype-art/v1', 'retrieve/characters/(?P<featured_in>\w+)/', array (
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array ($this, 'retrieve_characters_callback'),
            'permission_callback' => array ($this, 'permission_check_callback')
        ));

        register_rest_route('growtype-art/v1', 'retrieve/character/(?P<featured_in>\w+)/(?P<id>\d+)', array (
            'methods' => WP_REST_Server::READABLE,
            'callback' => array ($this, 'retrieve_character_callback'),
            'permission_callback' => array ($this, 'permission_check_callback')
        ));

        register_rest_route('growtype-art/v1', 'generate/character', array (
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array ($this, 'generate_character_callback'),
            'permission_callback' => array ($this, 'permission_check_callback')
        ));

        register_rest_route('growtype-art/v1', 'generate/image', array (
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array ($this, 'generate_image_callback'),
            'permission_callback' => array ($this, 'permission_check_callback')
        ));

        register_rest_route('growtype-art/v1', 'generate/character/image', array (
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array ($this, 'generate_character_image_callback'),
            'permission_callback' => array ($this, 'permission_check_callback')
        ));

        register_rest_route('growtype-art/v1', 'generate/character/video', array (
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array ($this, 'generate_character_video_callback'),
            'permission_callback' => array ($this, 'permission_check_callback')
        ));

        register_rest_route('growtype-art/v1', 'update/character/settings', array (
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array ($this, 'update_character_settings_callback'),
            'permission_callback' => array ($this, 'permission_check_callback')
        ));
    }

    function permission_check_callback(WP_REST_Request $request)
    {
        $current_user = wp_get_current_user();

        if ($current_user->ID === 0) {
            return new WP_Error(
                'rest_not_authenticated',
                __('You are not authenticated.'),
                ['status' => 401]
            );
        }

        if (!in_array($current_user->user_login, ['admin'])) {
            return new WP_Error(
                'rest_forbidden',
                __('You are not authorized to access this resource.'),
                ['status' => 403]
            );
        }

        return true;
    }

    function growtype_art_model_adjust_callback($model_id)
    {
        self::growtype_art_character_delete_featured_in_transient();

        Growtype_Cron_Jobs::create_if_not_exists('webhook-update-model', json_encode(['model_id' => $model_id]), 10);
    }

    function growtype_art_image_adjust_callback($image_id)
    {
        self::growtype_art_character_delete_featured_in_transient();

//        Growtype_Cron_Jobs::create_if_not_exists('webhook-update-image', json_encode(['image_id' => $image_id]), 10);
    }

    public static function growtype_art_character_delete_featured_in_transient()
    {
        global $wpdb;

        $transient_prefix = '_transient_' . self::FEATURED_IN_CHARACTERS_TRANSIENT_KEY;

        $transients = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like($transient_prefix) . '%'
            )
        );

        foreach ($transients as $transient) {
            $transient_name = str_replace('_transient_', '', $transient);
            delete_transient($transient_name);
        }
    }

    function retrieve_characters_callback($data)
    {
        $params = $data->get_params();

        $model_ids = isset($params['ids']) ? $params['ids'] : [];

        if (is_string($model_ids)) {
            $model_ids = explode(',', $model_ids);
        }

        $featured_in_groups = isset($params['featured_in']) ? [$params['featured_in']] : [];
        $unique_hashes = isset($params['unique_hashes']) ? $params['unique_hashes'] : [];
        $tags = isset($params['tags']) ? $params['tags'] : [];
        $created_by_options = isset($params['created_by']) ? $params['created_by'] : [];

        if (is_string($created_by_options)) {
            $created_by_options = explode(',', $created_by_options);
        }

        $include_with_empty_images = isset($params['include_with_empty_images']) ? $params['include_with_empty_images'] : false;
        $limit = isset($params['limit']) ? $params['limit'] : 10;
        $offset = isset($params['offset']) ? $params['offset'] : 0;
        $character_occupation = isset($params['character_occupation']) ? $params['character_occupation'] : '';
        $ignore_transient = isset($params['ignore_transient']) ? $params['ignore_transient'] : false;
        $characters_slugs = isset($params['characters_slugs']) ? $params['characters_slugs'] : [];
        $optimized_images = isset($params['optimized_images']) ? filter_var($params['optimized_images'], FILTER_VALIDATE_BOOLEAN) : true;

        foreach ($featured_in_groups as $featured_in_group) {
            $options = growtype_art_get_model_featured_in_options();

            if (!in_array($featured_in_group, array_keys($options))) {
                return wp_send_json([
                    'success' => false,
                    'message' => 'Invalid featured_in',
                ], 400);
            }
        }

        if (empty($featured_in_groups)) {
            return wp_send_json([
                'success' => false,
                'message' => 'Missing featured_in',
            ], 400);
        }

        foreach ($created_by_options as $created_by_option) {
            if (!in_array($created_by_option, ['admin', 'external_user'])) {
                return wp_send_json([
                    'success' => false,
                    'message' => 'Invalid created_by',
                ], 400);
            }
        }

        if ($include_with_empty_images) {
            self::growtype_art_character_delete_featured_in_transient();
        }

        $transient_hash = md5(implode('_', $featured_in_groups) . '_' . implode('_', $created_by_options) . '_' . implode('_', $characters_slugs));
        $transient_key = self::FEATURED_IN_CHARACTERS_TRANSIENT_KEY . '_' . $limit . '_' . $offset . '_' . $transient_hash;

        if (!empty($tags)) {
            $transient_key .= '_' . implode('_', $tags);
        }

        $characters = [];
        if (empty($unique_hashes) && !$ignore_transient) {
            $characters = get_transient($transient_key);

            if (is_array($characters) && empty($characters)) {
                return wp_send_json([
                    'success' => false,
                    'message' => 'Empty characters'
                ], 200);
            }
        }

        if (empty($characters) || $ignore_transient) {
            if (!empty($unique_hashes)) {
                $unique_hashes = array_unique(array_values($unique_hashes));
            }

            try {
                $group_params = [
                    'models_ids' => $model_ids,
                    'groups' => $featured_in_groups,
                    'created_by_options' => $created_by_options,
                    'unique_hashes' => $unique_hashes,
                    'limit' => $limit,
                    'offset' => $offset,
                    'characters_slugs' => $characters_slugs,
                    'optimized_images' => $optimized_images,
                    'character_occupation' => $character_occupation,
                    'tags' => $tags,
                ];

                $return_data = growtype_art_get_featured_in_group_models($group_params);
            } catch (Exception $e) {
                return wp_send_json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }

            $characters = [];
            foreach ($return_data as $return_data_single) {
                $total_images = array_merge($return_data_single['cover_images'] ?? [], $return_data_single['featured_images'] ?? [], $return_data_single['public_images'] ?? [], $return_data_single['naked_images'] ?? [], $return_data_single['erotic_images'] ?? []);

                if (!$include_with_empty_images && count($total_images) < 2) {
                    continue;
                }

                array_push($characters, $return_data_single);
            }

            if (!empty($transient_key)) {
                set_transient($transient_key, $characters, 60 * 60 * 24);
            }
        }

        if (empty($characters)) {
            return wp_send_json([
                'success' => false,
                'message' => 'Empty characters',
                'transient_key' => $transient_key,
                'params' => $params,
            ], 400);
        }

        if (!empty($model_ids)) {
            $characters_filtered = [];
            foreach ($characters as $key => $character) {
                if (!isset($character['id']) || !in_array($character['id'], $model_ids)) {
                    continue;
                }

                $characters_filtered[] = $character;
            }

            $characters = $characters_filtered;
        }

        return wp_send_json([
            'success' => true,
            'params' => [
                'characters' => $characters,
            ],
            'message' => 'Characters retrieved successfully',
        ], 200);
    }

    function retrieve_character_callback($data)
    {
        $params = $data->get_params();

        $character_id = isset($params['id']) ? $params['id'] : null;

        if (empty($character_id)) {
            return wp_send_json([
                'success' => false,
                'message' => 'Missing character id',
            ], 400);
        }

        d($character_id);
    }

    function generate_character_callback($data)
    {
        $params = $data->get_params();

        $dublicated_character_id = isset($params['dublicated_model_id']) ? $params['dublicated_model_id'] : growtype_art_default_model_id_to_duplicate();
        $unique_hash = isset($params['unique_hash']) ? $params['unique_hash'] : null;
        $featured_in = isset($params['featured_in']) ? explode(',', $params['featured_in']) : [];
        $faceswap_new_uploads = filter_var($params['faceswap_new_uploads'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $faceswap_type = isset($params['faceswap_type']) ? $params['faceswap_type'] : '';
        $leonardoai_settings_user_nr = isset($params['leonardoai_settings_user_nr']) ? $params['leonardoai_settings_user_nr'] : '';
        $generatable_images_limit = isset($params['generatable_images_limit']) ? $params['generatable_images_limit'] : '';
        $created_by = isset($params['created_by']) ? $params['created_by'] : 'external_user';
        $add_to_bundle = filter_var($params['add_to_bundle'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $generate_images_initially = filter_var($params['generate_images_initially'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $custom_assets = isset($params['custom_assets']) ? $params['custom_assets'] : [];
        $crop_percent = isset($params['crop_percent']) ? $params['crop_percent'] : null;
        $slug = isset($params['slug']) ? $params['slug'] : null;
        $character_title = isset($params['character_title']) ? $params['character_title'] : null;
        $generate_character_details = isset($params['generate_character_details']) ? $params['generate_character_details'] : false;
        $character_details = isset($params['character_details']) ? json_decode($params['character_details'], true) : [];

        if ($generate_character_details) {
            $generate_character_details = growtype_art_generate_character_details($character_title);
            $character_details = array_merge($character_details, $generate_character_details);
        }

        if (!empty($slug)) {
            $existing_model = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::MODEL_SETTINGS_TABLE, [
                [
                    'key' => 'meta_key',
                    'value' => 'slug',
                ],
                [
                    'key' => 'meta_value',
                    'value' => $slug,
                ]
            ], 'where');

            $model_id = $existing_model[0]['model_id'] ?? null;
        }

        if (!isset($model_id) || empty($model_id)) {
            /**
             * Set character details
             */
            $character_details['faceswap_new_uploads'] = $faceswap_new_uploads;
            $character_details['faceswap_type'] = $faceswap_type;

            if (!empty($leonardoai_settings_user_nr)) {
                $character_details['leonardoai_settings_user_nr'] = $leonardoai_settings_user_nr;
            }

            /**
             * Adjust
             */
            $character_details = self::adjust_character_details($character_details);

            /**
             * Check if model id is set
             */
            if (isset($character_details['character_id']) && !empty($character_details['character_id'])) {
                $dublicated_character_id = $character_details['character_id'];
            }

            $character_details['created_by_unique_hash'] = $unique_hash;
            $character_details['featured_in'] = json_encode($featured_in);
            $character_details['created_by'] = $created_by;
            $character_details['generatable_images_limit'] = !empty($generatable_images_limit) ? $generatable_images_limit : ($faceswap_new_uploads ? '2' : '3');
            $character_details['generatable_images_limit'] = $character_details['generatable_images_limit'] < 50 ? $character_details['generatable_images_limit'] : '50';
            $character_details['priority_level'] = 0;

            if (!empty($character_title)) {
                $character_details['character_title'] = $character_title;
            }

            if (empty($character_details)) {
                return wp_send_json([
                    'success' => false,
                    'message' => 'Missing data',
                ], 400);
            }

            if (!empty($unique_hash)) {
                $settings = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::MODEL_SETTINGS_TABLE, [
                    [
                        'key' => 'meta_key',
                        'values' => ['created_by_unique_hash'],
                    ],
                    [
                        'key' => 'meta_value',
                        'values' => [$unique_hash],
                    ]
                ]);

                /**
                 * Check if unique hash already exists
                 */
                if (!empty($settings)) {
                    $existing_hashes = array_pluck($settings, 'meta_value');

                    if (in_array($unique_hash, $existing_hashes)) {
                        return wp_send_json([
                            'success' => false,
                            'message' => 'Already created',
                        ], 501);
                    }
                }
            }

            /**
             * Clone model
             */
            $model_id = growtype_art_admin_duplicate_model($dublicated_character_id);

            $details_to_update = [
                'auto_check_for_nsfw',
                'categories',
                'tags',
                'leonardoai_settings_user_nr',
                'created_by_unique_hash',
                'created_by',
                'generatable_images_limit',
                'featured_in',
                'model_id',
                'prompt_variables',
                'character_style',
                'character_hair_color',
                'character_hair_style',
                'character_eye_color',
                'character_ethnicity',
                'character_breast_size',
                'character_butt_size',
                'character_title',
                'character_occupation',
                'character_location',
                'character_gpt_personality_extension',
                'character_intro_message',
                'character_can_answer_to_questions',
                'character_popular_topics_to_discuss',
                'face_swap_photos',
                'faceswap_new_uploads',
                'priority_level',
            ];

            if (!empty($slug)) {
                $character_details['slug'] = $slug;
                $details_to_update[] = 'slug';
            }

            growtype_art_admin_update_model_settings($model_id, $character_details, $details_to_update);

            /**
             * Update prompt
             */
            if (isset($character_details['prompt']) && !empty($character_details['prompt'])) {
                Growtype_Art_Database_Crud::update_record(Growtype_Art_Database::MODELS_TABLE, [
                    "prompt" => $character_details['prompt']
                ], $model_id);
            }

            Growtype_Art_Database_Crud::update_record(Growtype_Art_Database::MODELS_TABLE, [
                "provider" => Growtype_Art_Crud::NSFW_PROVIDERS[array_rand(Growtype_Art_Crud::NSFW_PROVIDERS)]
            ], $model_id);

            /**
             * Add to bundle
             */
            if ($add_to_bundle) {
                growtype_art_admin_update_bundle_keys([$model_id], 'add');
            }

            if ($generate_images_initially) {
                $crud = new Leonardoai_Base();
                for ($i = 0; $i < 2; $i++) {
                    $generate_details = $crud->generate_model_image($model_id);
                    sleep(2);
                }
            }
        }

        if (!empty($custom_assets)) {
            $model = growtype_art_get_model_details($model_id);

            foreach ($custom_assets as $key => $value) {
                $saved_image = Growtype_Art_Crud::save_image([
                    'url' => $value,
                    'folder' => $model['image_folder'],
                ], true, $crop_percent);

                if (empty($saved_image) || isset($saved_image['error']) || !isset($saved_image['id'])) {
                    error_log('save_generations: ' . json_encode($saved_image));
                    continue;
                }

                /**
                 * Assign image to model
                 */
                Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::MODEL_IMAGE_TABLE, [
                    'model_id' => $model_id,
                    'image_id' => $saved_image['id']
                ]);
            }
        }

        return wp_send_json([
            'success' => true,
            'message' => 'Model updated',
            'model_id' => $model_id,
            'character_details' => isset($character_details) ? json_encode($character_details) : [],
        ], 200);
    }

    function generate_image_callback($data)
    {
        $params = $data->get_params();

        $generate_details = growtype_art_generate_image($params);

        return wp_send_json($generate_details, 200);
    }

    public static function adjust_character_details($character_external_details)
    {
        $faker = Faker\Factory::create();

        $character_details = [];
        $character_details['auto_check_for_nsfw'] = 0;
        $character_details['character_age'] = 30;
        $character_details['character_eye_color'] = 'blue';
        $character_details['character_hair_style'] = '';
        $character_details['character_hair_color'] = '';
        $character_details['character_style'] = 'realistic';
        $character_details['character_ethnicity'] = 'caucasian';
        $character_details['character_body_type'] = 'hourglass';
        $character_details['character_breast_size'] = 'medium';
        $character_details['character_butt_size'] = 'medium';
        $character_details['character_gpt_personality_extension'] = '';

        foreach ($character_external_details as $key => $user_detail) {
            $key_mapping = [
                'age' => 'character_age',
                'ethnicity' => 'character_ethnicity',
                'eyes_color' => 'character_eye_color',
                'hair_style' => 'character_hair_style',
                'hair_color' => 'character_hair_color',
                'body_type' => 'character_body_type',
                'breast_size' => 'character_breast_size',
                'butt_size' => 'character_butt_size',
                'occupation' => 'character_occupation',
                'clothing' => 'character_clothing',
                'personality' => 'character_personality',
                'relationship' => 'character_relationship',
            ];

            $adjusted_key = $key_mapping[$key] ?? $key;

//            if (!is_array($user_detail) || in_array($key, ['relationship', 'personality'])) {
//                continue;
//            }

            if (isset($user_detail[0]) && $user_detail[0] === 'other') {
                $character_details[$adjusted_key] = $user_detail[1]['value'] ?? '';
            } elseif (is_array($user_detail)) {
                if ($key === 'age') {
                    $age = '30';

                    if (strpos($user_detail[0], '-') !== false) {
                        $age = explode('-', $user_detail[0]);
                        $age = rand((int)$age[0], (int)$age[1]);
                    }

                    $character_details[$adjusted_key] = $age;
                } elseif ($key === 'occupation') {
                    $character_details[$adjusted_key] = str_replace('_', ' ', $user_detail[0]);
                } elseif ($key === 'character_title') {
                    $character_title = $user_detail[0]['value'] ?? '';

                    if (!empty($character_title)) {
                        $character_details[$adjusted_key] = self::sanitize_character_title($character_title);
                    }
                } else {
                    $character_details[$adjusted_key] = $user_detail[0] ?? '';
                }
            } else {
                $character_details[$adjusted_key] = $user_detail;
            }

            if (isset($character_details[$adjusted_key]) && !empty($character_details[$adjusted_key])) {
                $character_details[$adjusted_key] = trim($character_details[$adjusted_key]);
            }

            if ($adjusted_key === 'character_clothing') {
                $character_details['prompt_variables'] = 'wearing ' . implode('| wearing ', explode(',', $character_details[$adjusted_key]));

                $needles = self::get_naked_needles();
                foreach ($needles as $needle) {
                    if (strpos(strtolower($character_details['prompt_variables']), $needle) !== false) {
                        $character_details['prompt_variables'] = '';
                        break;
                    }
                }
            }

            if (in_array($adjusted_key, ['character_personality', 'character_relationship'])) {
                if ($adjusted_key === 'character_personality') {
                    $character_details['character_gpt_personality_extension'] .= 'You are a ' . $character_details[$adjusted_key] . ' personality type.';
                }
                if ($adjusted_key === 'character_relationship') {
                    $character_details['character_gpt_personality_extension'] .= 'You are in ' . $character_details[$adjusted_key] . ' relationship.';
                }
            }
        }

        $character_details['character_gender'] = isset($character_details['character_gender']) ? $character_details['character_gender'] : 'female';
        $character_details['prompt_variables'] = isset($character_details['prompt_variables']) ? $character_details['prompt_variables'] : '';

        if (empty($character_details['character_title'])) {

            $first_name = $faker->firstName($character_details['character_gender']);
            $last_name = $faker->lastName($character_details['character_gender']);

            if (strpos(strtolower($last_name), 'dick') !== false) {
                $last_name = $faker->lastName($character_details['character_gender']);
            }

            $character_details['character_title'] = $first_name . ' ' . $last_name;
        }

        $character_details['slug'] = growtype_art_format_character_slug($character_details['character_title']);
        $character_details['character_nationality'] = growtype_art_get_random_character_nationality();
        $character_details['character_occupation'] = isset($character_details['character_occupation']) && !empty($character_details['character_occupation']) ? $character_details['character_occupation'] : growtype_art_get_character_ocupation();
        $character_details['character_hobbies'] = implode(', ', growtype_art_get_random_amount_of_character_hobies(mt_rand(1, 3)));
        $character_details['character_height'] = $character_details['character_gender'] === 'male' ? rand(170, 200) : rand(160, 190);
        $character_details['character_weight'] = $character_details['character_gender'] === 'male' ? rand(60, 90) : rand(40, 60);
        $character_details['character_dreams'] = growtype_art_get_random_character_dream();
        $character_details['character_description'] = growtype_art_get_random_character_description();
        $character_details['character_introduction'] = '';
        $character_details['character_intro_message'] = '';
        $character_details['character_location'] = growtype_art_get_character_location();
        $character_details['tags'] = [];

        /**
         * Set main identification
         */

        $naked = false;
        if (isset($character_external_details['include_nsfw']) && $character_external_details['include_nsfw'][0] === 'yes') {
            $naked = true;
        } else {
//            $needles = self::get_naked_needles();
//            foreach ($needles as $needle) {
//                if (
//                    strpos(strtolower($character_details['prompt_variables']), $needle) !== false
//                    || (isset($character_details['character_clothing']) && strpos(strtolower($character_details['character_clothing']), $needle) !== false)
//                    || (isset($character_details['character_occupation']) && strpos(strtolower($character_details['character_occupation']), $needle) !== false)
//                ) {
//                    $naked = true;
//                    break;
//                }
//            }
        }

        if ($naked) {
            $character_details['tags'] = array_merge($character_details['tags'], ['naked', 'nsfw']);
        }

        $nsfw = false;
        $needles = self::get_nsfw_needles();
        foreach ($needles as $needle) {
            if (
                strpos(strtolower($character_details['prompt_variables']), $needle) !== false
                || (isset($character_details['character_clothing']) && strpos(strtolower($character_details['character_clothing']), $needle) !== false)
                || (isset($character_details['character_occupation']) && strpos(strtolower($character_details['character_occupation']), $needle) !== false)
            ) {
                $nsfw = true;
                break;
            }
        }

        if ($nsfw) {
            $character_details['tags'] = array_merge($character_details['tags'], ['nsfw']);
        }

        if ($nsfw || $naked) {
            $character_details['tags'] = array_merge($character_details['tags'], ['hot', 'sexy', 'erotic', 'adult', '18+', 'xxx']);
        }

        $is_anime = isset($character_details['character_style']) && $character_details['character_style'] === 'anime' ? true : false;

        if ($is_anime) {
            $character_details['tags'] = array_merge($character_details['tags'], ['manga', 'hentai', 'comics', 'anime', 'cosplay']);
            $character_details['categories'] = json_encode([
                "Anime & Manga" => [],
            ]);
        }

        /**
         * Set model id
         */
        if ($naked) {
            $character_details['model_id'] = '2067ae52-33fd-4a82-bb92-c2c55e7d2786';
        } elseif ($is_anime) {
            $character_details['model_id'] = '2067ae52-33fd-4a82-bb92-c2c55e7d2786';
        } else {
            $character_details['model_id'] = 'aa77f04e-3eec-4034-9c07-d0f619684628';
        }

        if (isset($character_details['character_occupation']) && !empty($character_details['character_occupation'])) {
            $character_details['character_occupation'] = str_replace('_', ' ', $character_details['character_occupation']);
        }

        if (isset($character_details['faceswap_new_uploads']) && $character_details['faceswap_new_uploads']) {
            if (isset($character_details['faceswap_type']) && $character_details['faceswap_type'] === 'headshot') {
                $character_details['prompt'] = "8k linkedin professional profile photo of {character_age} years old {character_gender} {character_ethnicity} ethnicity {character_title} in a suit with studio lighting, {character_hair_style} {character_hair_color} hair, who has {character_eye_color} eyes, bokeh, corporate portrait headshot photograph best corporate photography photo winner, meticulous detail, hyperrealistic, centered uncropped symmetrical beautiful and white background, narrow depth of field, film photography";
            }
        } else {
            $character_details['prompt'] = "High quality, professional, {character_style} photograph of a {character_ethnicity} ethnicity {character_gender} {character_occupation} who looks like {character_title} at {character_age} years old, {prompt_variables}, {character_hair_style} {character_hair_color} hair, {character_eye_color} eyes, a refined and proportionate nose, full and balanced lips, high and well-defined cheekbones, a gracefully sculpted jawline, narrow depth of field, film photography";

            if ($is_anime) {
                $character_details['prompt'] = "High quality, 8K Ultra HD, By Yves Di, anime, a beautiful {character_ethnicity} ethnicity {character_gender} {character_occupation} {character_age} years old who looks like {character_title}, light {character_eye_color} eyes, {character_hair_style} {character_hair_color} hair, {prompt_variables}, high quality, 8K Ultra HD, 3D effect, A digital illustration of {character_style} style, soft {character_style} tones, Atmosphere like Kyoto Animation, luminism, three dimensional effect, luminism, 3d render, octane render, Isometric, awesome full color, delicate and anime character expressions";
            }

            if ($naked) {
//                $character_details['prompt'] = "(((uncensored)))(((xxx)))(((nudity)))((({character_style} style))) Generate ((full body)) image in {character_style} style of no clothes {character_title} {character_age} years old {character_nationality} {character_gender} model wearing nothing, {character_breast_size} mammary glands, {character_eye_color} eyes, {character_hair_style} {character_hair_color} hair, natural lighting, 35mm, f/2, 8K. Ensure visible skin texture for a {character_style} style portrayal. Utilize a {character_style} style with a 50mm lens for a balanced composition.";
                $character_details['prompt'] = "((({character_style} style))) Generate ((full body)) image in {character_style} style of {character_title} {character_age} years old {character_ethnicity} ethnicity {character_nationality} {character_gender} model, {character_breast_size} mammary glands, {character_eye_color} eyes, {character_hair_style} {character_hair_color} hair, natural lighting, 35mm, f/2, 8K. Ensure visible skin texture for a {character_style} style portrayal. Utilize a {character_style} style with a 50mm lens for a balanced composition.";
            }
        }

        if (isset($character_external_details['user_photos']) && !empty($character_external_details['user_photos'])) {
            $character_details['face_swap_photos'] = json_encode($character_external_details['user_photos']['files'] ?? []);
        }

        if ($character_details['character_hair_style'] === 'bald') {
            $character_details['prompt'] = str_replace('{character_hair_style}', '(((has no hair, bald head)))', $character_details['prompt']);
            $character_details['prompt'] = str_replace('{character_hair_color} hair', '', $character_details['prompt']);
            $character_details['character_hair_color'] = '';
        }

        if ($character_details['character_age'] < 22) {
            $character_details['character_age'] = 22;
        }

        if (is_array($character_details['tags']) && !empty($character_details['tags'])) {
            $character_details['tags'] = json_encode($character_details['tags']);
        }

        return $character_details;
    }

    public static function get_naked_needles()
    {
        return ['naked', 'prostitute', 'dominatrix', 'lingerie', 'slut', 'nude', 'nothing', 'porn', 'pornstar', 'stripper', 'sex', 'no clothes', 'no bra', 'no panty', 'sexy', 'seductive', 'bare', 'erotic', 'naked', 'nude', 'nothing'];
    }

    public static function get_nsfw_needles()
    {
        return ['latex'];
    }

    public static function sanitize_character_title($string)
    {
        $faker = Faker\Factory::create();

        // Check if the string contains only alphabetical characters and spaces
        if (!preg_match('/^[a-zA-Z ]+$/', $string)) {
            return '';
        }

        // Check if there is at least one space in the string
        if (count(explode(' ', $string)) === 1) {
            $last_name = $faker->lastName();

            if (strpos(strtolower($last_name), 'dick') !== false) {
                $last_name = $faker->lastName();
            }

            $string .= ' ' . $last_name;
        }

        // Check if the string is empty or has less than 10 characters
        if (empty($string) || strlen($string) < 9) {
            return '';
        }

        // Replace underscores with spaces
        $string = str_replace('_', ' ', $string);

        // Capitalize the first letter of each word
        $string = ucwords($string);

        return $string;
    }

    function update_character_settings_callback($data)
    {
        $params = $data->get_params();

        $reference_id = isset($params['reference_id']) && !empty($params['reference_id']) ? $params['reference_id'] : null;
        $unique_hash = isset($params['unique_hash']) && !empty($params['unique_hash']) ? $params['unique_hash'] : null;
        $model_id = isset($params['model_id']) ? $params['model_id'] : null;
        $settings = isset($params['settings']) ? $params['settings'] : null;

        if (empty($settings) || empty($model_id)) {
            return wp_send_json([
                'success' => false,
                'message' => 'Missing data',
            ], 400);
        }

        if (empty($unique_hash) && empty($reference_id)) {
            return wp_send_json([
                'success' => false,
                'message' => 'Missing model credentials',
            ], 400);
        }

        $model = growtype_art_get_model_details($model_id);

        if (!empty($reference_id) && $model['reference_id'] !== $reference_id) {
            return wp_send_json([
                'success' => false,
                'message' => 'Wrong reference id',
            ], 400);
        }

        if (empty($model)) {
            return wp_send_json([
                'success' => false,
                'message' => 'Invalid model',
            ], 400);
        }

        if (isset($model['settings']['created_by_unique_hash']) && $model['settings']['created_by_unique_hash'] !== $unique_hash) {
            return wp_send_json([
                'success' => false,
                'message' => 'Invalid unique hash',
            ], 400);
        }

        /**
         * Update priority level
         */
        if (isset($settings['priority_level']) && !empty($settings['priority_level'])) {
            $priority_level = growtype_art_get_model_single_setting($model_id, 'priority_level');
            $priority_level = $priority_level['meta_value'] ?? 0;
            $settings['priority_level'] = (int)$priority_level + (int)$settings['priority_level'];
        }

        growtype_art_admin_update_model_settings($model_id, $settings, [
            'model_is_private',
            'priority_level',
        ]);

        return wp_send_json([
            'success' => true,
            'message' => 'Character settings updated',
        ], 200);
    }

    function generate_character_image_callback($data)
    {
        $params = $data->get_params();

        /**
         * Parameters
         */
        $unique_hash = isset($params['unique_hash']) ? $params['unique_hash'] : null;
        $model_id = isset($params['model_id']) ? $params['model_id'] : null;
        $prompt = isset($params['prompt']) ? $params['prompt'] : '';
        $providers = isset($params['providers']) ? $params['providers'] : [];
        $types = isset($params['types']) ? $params['types'] : [];
        $types = array_filter($types, function ($type) {
            return in_array($type, ['nsfw', 'nudity', 'porn', 'private']);
        });
        $user_id = isset($params['user_id']) ? $params['user_id'] : null;
        $reference_image_urls = isset($params['reference_image_urls']) ? $params['reference_image_urls'] : [];

//        shuffle($providers);

        if (empty($model_id) && !empty($unique_hash)) {
            $character = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::MODEL_SETTINGS_TABLE, [
                [
                    'key' => 'meta_key',
                    'value' => 'created_by_unique_hash',
                ],
                [
                    'key' => 'meta_value',
                    'value' => $unique_hash,
                ]
            ], 'where');

            $character = $character[0] ?? [];

            $model_id = $character['model_id'];
        }

        if (!empty($model_id)) {
            try {
                $model = growtype_art_get_model_details($model_id);
//                $prompt_details = $prompt . '. ' . $model['prompt'];
//                $prompt_details = substr($prompt_details, 0, 300);

                $prompt = !empty($prompt) ? $prompt : $model['prompt'];

                $generate_details = growtype_art_generate_model_image($model_id, [
                    'prompt' => $prompt,
                    'providers' => $providers,
                    'types' => $types,
                    'user_id' => $user_id,
                    'reference_image_urls' => $reference_image_urls,
                ]);

                if (empty($generate_details) || !$generate_details['success']) {
                    $generate_details['success'] = false;
                } else {
                    $generate_details['success'] = true;
                }

                return wp_send_json($generate_details, 200);
            } catch (Exception $e) {
                $message = json_decode($e->getMessage(), true);
                $message = $message['errors'][0]['message'] ?? $e->getMessage();

                return wp_send_json([
                    'success' => false,
                    'message' => $message,
                ], 200);
            }
        }

        return wp_send_json([
            'success' => false,
            'message' => 'Character is missing',
        ], 200);
    }

    function generate_character_video_callback($data)
    {
        $params = $data->get_params();

        $model_id = isset($params['model_id']) ? $params['model_id'] : null;
        $prompt = isset($params['prompt']) ? $params['prompt'] : '';
        $providers = isset($params['providers']) ? $params['providers'] : [];
        $reference_image = isset($params['reference_image']) ? $params['reference_image'] : null;

        if (!empty($model_id)) {
            try {
                $model = growtype_art_get_model_details($model_id);
                $prompt = !empty($prompt) ? $prompt : $model['prompt'];

                $generate_details = growtype_art_generate_model_video($model_id, [
                    'prompt' => $prompt,
                    'providers' => $providers,
                    'reference_image' => $reference_image,
                ]);

                if (empty($generate_details) || !$generate_details['success']) {
                    $generate_details['success'] = false;
                } else {
                    $generate_details['success'] = true;
                }

                return wp_send_json($generate_details, 200);
            } catch (Exception $e) {
                $message = json_decode($e->getMessage(), true);
                $message = $message['errors'][0]['message'] ?? $e->getMessage();

                return wp_send_json([
                    'success' => false,
                    'message' => $message,
                ], 200);
            }
        }

        return wp_send_json([
            'success' => false,
            'message' => 'Character is missing',
        ], 200);
    }
}
