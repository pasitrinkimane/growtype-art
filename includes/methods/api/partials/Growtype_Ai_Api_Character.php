<?php

use partials\Leonardo_Ai_Base;

require GROWTYPE_AI_PATH . '/vendor/autoload.php';

class Growtype_Ai_Api_Character
{
    const FEATURED_IN_CHARACTERS_TRANSIENT_KEY = 'growtype_ai_api_featured_in_characters';

    public function __construct()
    {
        $this->load_methods();

        add_action('rest_api_init', array (
            $this,
            'register_routes'
        ));

        /**
         * Clear transient
         */
        add_action('growtype_ai_model_update', array ($this, 'growtype_ai_model_adjust_callback'));
        add_action('growtype_ai_model_delete', array ($this, 'growtype_ai_model_adjust_callback'));
        add_action('growtype_ai_model_image_delete', array ($this, 'growtype_ai_image_adjust_callback'));
        add_action('growtype_ai_model_image_update', array ($this, 'growtype_ai_image_adjust_callback'));
    }

    function load_methods()
    {
    }

    function register_routes()
    {
        $permission = current_user_can('manage_options');

        register_rest_route('growtype-ai/v1', 'retrieve/characters/(?P<featured_in>\w+)/', array (
            'methods' => ['GET', 'POST'],
            'callback' => array (
                $this,
                'retrieve_characters_callback'
            ),
            'permission_callback' => function ($user) use ($permission) {
                return true;
            }
        ));

        register_rest_route('growtype-ai/v1', 'generate/character', array (
            'methods' => 'POST',
            'callback' => array (
                $this,
                'generate_character_callback'
            ),
            'permission_callback' => function ($user) use ($permission) {
                return true;
            }
        ));

        register_rest_route('growtype-ai/v1', 'update/character/settings', array (
            'methods' => 'POST',
            'callback' => array (
                $this,
                'update_character_settings_callback'
            ),
            'permission_callback' => function ($user) use ($permission) {
                return true;
            }
        ));
    }

    function growtype_ai_model_adjust_callback($model_id)
    {
        self::growtype_ai_character_delete_featured_in_transient();
    }

    function growtype_ai_image_adjust_callback($image_id)
    {
        self::growtype_ai_character_delete_featured_in_transient();
    }

    public static function growtype_ai_character_delete_featured_in_transient()
    {
        $featured_in_options = growtype_ai_get_model_featured_in_options();
        $model_users_options = growtype_ai_get_model_users_options();

        delete_transient(self::FEATURED_IN_CHARACTERS_TRANSIENT_KEY . '_' . implode('_', array_keys($featured_in_options)) . '_' . implode('_', array_keys($model_users_options)));

        foreach ($featured_in_options as $featured_in_key => $option) {
            delete_transient(self::FEATURED_IN_CHARACTERS_TRANSIENT_KEY . '_' . $featured_in_key . '_' . implode('_', array_keys($model_users_options)));

            foreach ($model_users_options as $model_user_key => $model_users_option) {
                delete_transient(self::FEATURED_IN_CHARACTERS_TRANSIENT_KEY . '_' . $featured_in_key . '_' . $model_user_key);
            }
        }
    }

    function retrieve_characters_callback($data)
    {
        $params = $data->get_params();

        $featured_in_groups = isset($params['featured_in']) ? [$params['featured_in']] : [];
        $unique_hashes = isset($params['unique_hashes']) ? $params['unique_hashes'] : [];
        $created_by_options = isset($params['created_by']) ? explode(',', stripslashes($params['created_by'])) : ['admin'];
        $include_with_empty_images = isset($params['include_with_empty_images']) ? $params['include_with_empty_images'] : false;

        foreach ($featured_in_groups as $featured_in_group) {
            $options = growtype_ai_get_model_featured_in_options();

            if (!in_array($featured_in_group, array_keys($options))) {
                return wp_send_json([
                    'data' => 'Invalid featured_in',
                ], 400);
            }
        }

        if (empty($featured_in_groups)) {
            return wp_send_json([
                'data' => 'Missing featured_in',
            ], 400);
        }

        if ($include_with_empty_images) {
            self::growtype_ai_character_delete_featured_in_transient();
        }

        $transient_key = '';
        $response = [];
        if (empty($unique_hashes)) {
            $transient_key = self::FEATURED_IN_CHARACTERS_TRANSIENT_KEY . '_' . implode('_', $featured_in_groups) . '_' . implode('_', $created_by_options);
            $response = get_transient($transient_key);
        }

        if (empty($response)) {
            $return_data = growtype_ai_get_featured_in_group_images($featured_in_groups, $created_by_options, $unique_hashes);

            $response = [];
            foreach ($return_data as $return_data_single) {
//                if ((isset($return_data_single['cover_images']) && $return_data_single['cover_images'] < 1) || (isset($return_data_single['featured_images_count']) && $return_data_single['featured_images_count'] < 3)) {
//                    continue;
//                }

                if (!$include_with_empty_images && (int)$return_data_single['public_images_count'] < 2) {
                    continue;
                }

                array_push($response, $return_data_single);
            }

            if (!empty($response) && !empty($transient_key)) {
                set_transient($transient_key, $response, 60 * 60 * 24);
            }
        }

        return wp_send_json($response, 200);
    }

    function generate_character_callback($data)
    {
        $params = $data->get_params();

        $dublicated_character_id = isset($params['dublicated_model_id']) ? $params['dublicated_model_id'] : '4783';
        $unique_hash = isset($params['unique_hash']) ? $params['unique_hash'] : null;
        $featured_in = isset($params['featured_in']) ? explode(',', $params['featured_in']) : [];

        /**
         * Character details
         */
        $character_details = isset($params['character_details']) ? json_decode($params['character_details'], true) : [];
        $character_details = self::map_character_details($character_details);

        /**
         * Check if model id is set
         */
        if (isset($character_details['character_id']) && !empty($character_details['character_id'])) {
            $dublicated_character_id = $character_details['character_id'];
        }

        $character_details['created_by_unique_hash'] = $unique_hash;
        $character_details['featured_in'] = json_encode($featured_in);
        $character_details['created_by'] = 'external_user';
        $character_details['generatable_images_limit'] = '4';

        if (empty($unique_hash) || empty($character_details)) {
            return wp_send_json([
                'data' => 'Missing data',
            ], 400);
        }

        $settings = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
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
                    'data' => 'Already created',
                ], 200);
            }
        }

        /**
         * Clone model
         */
        $new_model_id = growtype_ai_admin_duplicate_model($dublicated_character_id);

        growtype_ai_admin_update_model_settings($new_model_id, $character_details, [
            'created_by_unique_hash',
            'created_by',
            'generatable_images_limit',
            'featured_in',
            'model_id',
            'prompt_variables',
        ]);

        /**
         * Update prompt
         */
        if (isset($character_details['prompt']) && !empty($character_details['prompt'])) {
            Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::MODELS_TABLE, [
                "prompt" => $character_details['prompt']
            ], $new_model_id);
        }

        /**
         * Add to bundle
         */
        growtype_ai_admin_update_bundle_keys([$new_model_id], 'add');

        $crud = new Leonardo_Ai_Base();
        for ($i = 0; $i < 2; $i++) {
            $generate_details = $crud->generate_model($new_model_id);
        }

        return wp_send_json([
            'data' => 'Created',
        ], 200);
    }

    public static function map_character_details($character_external_details)
    {
        $faker = Faker\Factory::create();

        $character_details = [];
        $character_details['character_age'] = 30;
        $character_details['character_eye_color'] = 'blue';
        $character_details['character_hair_style'] = 'straight';
        $character_details['character_hair_color'] = 'white';
        $character_details['character_style'] = 'realistic';
        $character_details['character_ethnicity'] = 'caucasian';
        $character_details['character_body_type'] = 'hourglass';
        $character_details['character_breast_size'] = 'medium';
        $character_details['character_butt_size'] = 'medium';

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
            ];

            $adjusted_key = $key_mapping[$key] ?? $key;

            if (!is_array($user_detail) || in_array($key, ['relationship', 'personality'])) {
                continue;
            }

            if ($user_detail[0] === 'other') {
                $character_details[$adjusted_key] = $user_detail[1]['value'] ?? '';
            } else {
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
                    $character_details[$adjusted_key] = self::sanitize_character_title($user_detail[0]['value'] ?? '');
                } else {
                    $character_details[$adjusted_key] = $user_detail[0] ?? '';
                }
            }

            if ($adjusted_key === 'character_clothing') {
                $character_details['prompt_variables'] = 'wearing ' . implode('| wearing ', explode(',', $character_details[$adjusted_key]));
            }
        }

        $character_details['character_gender'] = isset($character_details['character_gender']) ? $character_details['character_gender'] : 'female';
        $character_details['prompt_variables'] = isset($character_details['prompt_variables']) ? $character_details['prompt_variables'] : '';

        if (empty($character_details['character_title'])) {
            $character_details['character_title'] = $faker->firstName($character_details['character_gender']) . ' ' . $faker->lastName($character_details['character_gender']);
        }

        $character_details['slug'] = growtype_ai_format_character_slug($character_details['character_title']);
        $character_details['character_nationality'] = growtype_ai_get_random_character_nationality();
        $character_details['character_occupation'] = isset($character_details['character_occupation']) && !empty($character_details['character_occupation']) ? $character_details['character_occupation'] : growtype_ai_get_character_ocupation();
        $character_details['character_hobbies'] = implode(', ', growtype_ai_get_random_amount_of_character_hobies(mt_rand(1, 3)));
        $character_details['character_height'] = $character_details['character_gender'] === 'male' ? rand(170, 200) : rand(160, 190);
        $character_details['character_weight'] = $character_details['character_gender'] === 'male' ? rand(60, 90) : rand(40, 60);
        $character_details['character_dreams'] = growtype_ai_get_random_character_dream();
        $character_details['character_description'] = growtype_ai_get_random_character_description();
        $character_details['character_introduction'] = '';
        $character_details['character_intro_message'] = '';
        $character_details['character_location'] = growtype_ai_get_character_location();

        $nsfw = false;
        $needles = ['naked', 'nude'];
        foreach ($needles as $needle) {
            if (
                strpos(strtolower($character_details['prompt_variables']), $needle) !== false
                || strpos(strtolower($character_details['character_clothing']), $needle) !== false
            ) {
                $nsfw = true;
                break;
            }
        }

        if (isset($character_details['character_style']) && $character_details['character_style'] === 'anime') {
            $character_details['model_id'] = '2067ae52-33fd-4a82-bb92-c2c55e7d2786';
        }

        if (isset($character_details['character_occupation']) && !empty($character_details['character_occupation'])) {
            $character_details['character_occupation'] = str_replace('_', ' ', $character_details['character_occupation']);
        }

        if ($character_details['character_gender'] === 'male') {
            $character_details['prompt'] = sprintf("A highly detailed {character_style} photograph of a {character_gender} man {character_ethnicity} {character_occupation} who looks like %s at {character_age} years old, {prompt_variable}, who has {character_hair_style} {character_hair_color} hair, large and expressive {character_eye_color} eyes, a refined and proportionate nose, full and balanced lips, high and well-defined cheekbones, a gracefully sculpted jawline, {character_body_shape} body, narrow depth of field, film photography",
                $character_details['character_title']);
        } else {
            $character_details['prompt'] = sprintf("A highly detailed photograph of {character_style} {character_gender} woman {character_ethnicity} {character_occupation}, who is {character_age} years old, who looks like %s, who has {character_hair_style} {character_hair_color} hair, {prompt_variable}, {character_body_shape} body, {character_breast_size} breast, {character_butt_size} butt, narrow depth of field, film photography",
                $character_details['character_title']);
        }

        if ($character_details['character_style'] === 'anime') {
            if ($character_details['character_gender'] === 'male') {
                $character_details['prompt'] = sprintf("high quality, 8K Ultra HD, By Yves Di, a beautiful {character_ethnicity} man {character_gender} {character_occupation} {character_age} years old who looks like %s, light {character_eye_color} eyes, {character_hair_style} {character_hair_color} hair, {prompt_variable}, high quality, 8K Ultra HD, 3D effect, A digital illustration of {character_style} style, soft {character_style} tones, Atmosphere like Kyoto Animation, luminism, three dimensional effect, luminism, 3d render, octane render, Isometric, awesome full color, delicate and anime character expressions",
                    $character_details['character_title']);
            } else {
                $character_details['prompt'] = sprintf("high quality, 8K Ultra HD, By Yves Di, a beautiful {character_ethnicity} woman {character_gender} {character_occupation} {character_age} years old who looks like %s, light {character_eye_color} eyes, {character_hair_style} {character_hair_color} hair, {character_breast_size} breast, {character_butt_size} butt, {prompt_variable}, high quality, 8K Ultra HD, 3D effect, A digital illustration of {character_style} style, soft {character_style} tones, Atmosphere like Kyoto Animation, luminism, three dimensional effect, luminism, 3d render, octane render, Isometric, awesome full color, delicate and anime character expressions",
                    $character_details['character_title']);
            }
        }

        if ($nsfw) {
            $character_details['prompt'] = sprintf("Mature Content,Adult Entertainment,X-Rated Material, a highly detailed photograph of an unclothed beautiful {character_nationality} model {character_gender} in her {character_age} {character_name} undressing poses for instagram {prompt_variable}, {character_ethnicity} skin, lean body, {character_hair_style} {character_hair_color} hair, ((distance)), ((wide-shot)), (({character_eye_color}-eyes)), Ethnicity [ {character_nationality} ]], super sexy, natural lighting, ultra photorealistic, photograph, 35mm, f/2, posing towards the camera front view, 8K, no clothes, no bra, no panty, slim, sexy, seductive, no garments, bare sexy body",
                $character_details['character_title']);
        }

        if ($nsfw) {
            $character_details['character_id'] = '4138';
            $character_details['prompt_variables'] = '';
        }

        return $character_details;
    }

    public static function sanitize_character_title($string)
    {
        // Check if the string is empty or has less than 10 characters
        if (empty($string) || strlen($string) < 9) {
            return '';
        }

        // Check if the string contains only alphabetical characters and spaces
        if (!preg_match('/^[a-zA-Z ]+$/', $string)) {
            return '';
        }

        // Check if there is at least one space in the string
        if (strpos($string, ' ') === false) {
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

        $unique_hash = isset($params['unique_hash']) ? $params['unique_hash'] : null;
        $model_id = isset($params['model_id']) ? $params['model_id'] : null;
        $settings = isset($params['settings']) ? $params['settings'] : null;

        if (empty($unique_hash) || empty($settings) || empty($model_id)) {
            return wp_send_json([
                'success' => false,
                'message' => 'Missing data',
            ], 400);
        }

        $model = growtype_ai_get_model_details($model_id);

        if (empty($model)) {
            return wp_send_json([
                'success' => false,
                'message' => 'Invalid model',
            ], 400);
        }

        if ($model['settings']['created_by_unique_hash'] !== $unique_hash) {
            return wp_send_json([
                'success' => false,
                'message' => 'Invalid unique hash',
            ], 400);
        }

        growtype_ai_admin_update_model_settings($model_id, $settings, [
            'model_is_private',
        ]);

        return wp_send_json([
            'success' => true,
            'message' => 'Character settings updated',
        ], 200);
    }
}
