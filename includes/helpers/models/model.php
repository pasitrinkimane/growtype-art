<?php

function growtype_art_default_model_id_to_duplicate()
{
    return '5581';
}

function growtype_art_admin_duplicate_model($model_id)
{
    $existing_model_details = growtype_art_get_model_details($model_id);

    $reference_id = growtype_art_generate_reference_id();

    $new_model_id = Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::MODELS_TABLE, [
        'prompt' => $existing_model_details['prompt'],
        'negative_prompt' => $existing_model_details['negative_prompt'],
        'reference_id' => $reference_id,
        'provider' => $existing_model_details['provider'],
        'image_folder' => Growtype_Art_Crud::IMAGES_FOLDER_NAME . '/' . $reference_id
    ]);

    $model_settings = $existing_model_details['settings'];

    foreach ($model_settings as $key => $value) {
        $existing_content = growtype_art_get_model_single_setting($new_model_id, $key);

        if (!empty($existing_content)) {
            continue;
        }

        Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::MODEL_SETTINGS_TABLE, [
            'model_id' => $new_model_id,
            'meta_key' => $key,
            'meta_value' => $value
        ]);
    }

    return $new_model_id;
}

function growtype_art_admin_update_model_settings($model_id, $model_settings, $allowed_keys = [])
{
    foreach ($model_settings as $meta_key => $meta_value) {
        $existing_content = growtype_art_get_model_single_setting($model_id, $meta_key);

        if (!empty($allowed_keys)) {
            if (empty($existing_content) && !in_array($meta_key, $allowed_keys)) {
                continue;
            }
        }

        if ($meta_value === "true") {
            $meta_value = 1;
        } elseif ($meta_value === "false") {
            $meta_value = 0;
        }

        if (isset($existing_content['id'])) {
            Growtype_Art_Database_Crud::update_record(Growtype_Art_Database::MODEL_SETTINGS_TABLE, [
                'model_id' => $model_id,
                'meta_key' => $meta_key,
                'meta_value' => $meta_value,
            ], $existing_content['id']);
        } else {
            Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::MODEL_SETTINGS_TABLE, [
                'model_id' => $model_id,
                'meta_key' => $meta_key,
                'meta_value' => $meta_value,
            ]);
        }
    }
}

function growtype_art_admin_update_bundle_keys($keys, $action)
{
    $bundle_ids = explode(',', preg_replace('/\s+/', '', get_option('growtype_art_bundle_ids')));

    if ($action === 'add') {
        $bundle_ids = array_unique(array_merge($bundle_ids, $keys));
    }

    if ($action === 'remove') {
        $bundle_ids = array_unique(array_diff($bundle_ids, $keys));
    }

    update_option('growtype_art_bundle_ids', implode(',', array_filter($bundle_ids)));
}

function growtype_art_get_model_images_group_stats($model_id, $type = 'original')
{
    $images = growtype_art_get_model_images_grouped($model_id)[$type] ?? [];

    $nsfw = 0;
    $is_featured = 0;
    $is_cover = 0;
    $is_naked = 0;
    foreach ($images as $image) {
        if (isset($image['settings']['nsfw']) && $image['settings']['nsfw']) {
            $nsfw++;
        }
        if (isset($image['settings']['is_featured']) && $image['settings']['is_featured']) {
            $is_featured++;
        }
        if (isset($image['settings']['is_cover']) && $image['settings']['is_cover']) {
            $is_cover++;
        }
        if (isset($image['settings']['nudity']) && $image['settings']['nudity']) {
            $is_naked++;
        }
    }

    return [
        'total' => count($images),
        'nsfw' => $nsfw,
        'featured' => $is_featured,
        'cover' => $is_cover,
        'naked' => $is_naked,
    ];
}

/**
 * @param $prompt
 * @param $model_id
 * @return array|mixed|string|string[]
 */
function growtype_art_model_format_prompt($prompt, $model_id)
{
    if (empty($prompt)) {
        return '';
    }

    $model_details = growtype_art_get_model_details($model_id);
    $prompt_variables = isset($model_details['settings']['prompt_variables']) ? $model_details['settings']['prompt_variables'] : null;
    $prompt_variables = !empty($prompt_variables) ? explode('|', $prompt_variables) : null;

    if (str_contains($prompt, '{prompt_variables}')) {
        if (!empty($prompt_variables)) {
            $rendom_promp_variable_key = array_rand($prompt_variables, 1);
            $prompt = str_replace('{prompt_variables}', strtoupper($prompt_variables[$rendom_promp_variable_key]), $prompt);
        } else {
            $prompt = str_replace('{prompt_variables}', '', $prompt);
        }
    }

    foreach ($model_details['settings'] as $key => $setting) {
        if (strpos($key, 'character') !== false) {
            $prompt = str_replace('{' . $key . '}', strtoupper($setting), $prompt);
        }
    }

    return $prompt;
}

/**
 * @param $prompt
 * @param $model_id
 * @return array|mixed|string|string[]
 */
function growtype_art_generate_model_image($model_id, $params = [])
{
    $providers = $params['providers'] ?? [];
    $model_provider = growtype_art_get_model_details($model_id)['provider'] ?? '';

    if (empty($providers)) {
        $providers = Growtype_Art_Crud::API_GENERATE_IMAGE_PROVIDERS;
        shuffle($providers);

        if (in_array($model_provider, $providers)) {
            $providers = array_diff($providers, [$model_provider]);
            array_unshift($providers, $model_provider);
        }
    }

    $generate_details = [
        'success' => false
    ];

    foreach ($providers as $provider) {
        if ($provider === 'writecream') {
            $provider = 'runware';
        }

        $provider_class_name = sprintf('\partials\%s_Base', ucfirst($provider));

        if (class_exists($provider_class_name)) {
            $crud = new $provider_class_name();

            $generate_details = $crud->generate_model_image($model_id, $params);

//            error_log(sprintf('GENERATING MODEL IMAGE for provider: %s. Details are: %s', strtoupper($provider), print_r($generate_details, true)));

            if ($generate_details['success']) {
                $generate_details['provider'] = $provider;
                break;
            }
        }
    }

    return $generate_details;
}

/**
 * @param $prompt
 * @param $model_id
 * @return array|mixed|string|string[]
 */
function growtype_art_generate_model_video($model_id, $params = [])
{
    $providers = $params['providers'] ?? [];
    $model_provider = growtype_art_get_model_details($model_id)['provider'] ?? '';

    if (empty($providers)) {
        $providers = Growtype_Art_Crud::API_GENERATE_VIDEO_PROVIDERS;
    }

    $generate_details = [
        'success' => false
    ];

    foreach ($providers as $provider) {
        $provider_class_name = sprintf('\partials\%s_Base', ucfirst($provider));

        if (class_exists($provider_class_name)) {
            $crud = new $provider_class_name();

            $generate_details = $crud->generate_model_video($model_id, $params);

            if ($generate_details['success']) {
                $generate_details['provider'] = $provider;
                break;
            }
        }
    }

    return $generate_details;
}

/**
 * @param $prompt
 * @param $model_id
 * @return array|mixed|string|string[]
 */
function growtype_art_generate_image($params = [])
{
    $providers = $params['providers'] ?? [];

    if (empty($providers)) {
        $providers = Growtype_Art_Crud::API_GENERATE_IMAGE_PROVIDERS;

        shuffle($providers);
    }

    $generate_details = [
        'success' => false
    ];

    foreach ($providers as $provider) {
        if ($provider === 'writecream') {
            $provider = 'runware';
        }

        $provider_class_name = sprintf('\partials\%s_Base', ucfirst($provider));

        if (class_exists($provider_class_name)) {
            $crud = new $provider_class_name();

            $generate_details = $crud->generate_image($params);

            if ($generate_details['success']) {
                $generate_details['provider'] = $provider;
                break;
            }
        }
    }

    return $generate_details;
}
