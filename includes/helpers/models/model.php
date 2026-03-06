<?php

/**
 * @param $params
 * @return string
 */
function growtype_art_get_default_prompt_template($params)
{
    if (is_string($params)) {
        $params = json_decode($params, true);
    }

    $style = $params['character_style'][0] ?? $params['character_style'] ?? 'realistic';

    if ($style === 'anime') {
        $template = "High quality, 8K Ultra HD, By Yves Di, anime, a beautiful {character_ethnicity} ethnicity {character_gender} {character_occupation} {character_age} years old who looks like {character_title}, light {character_eye_color} eyes, {character_hair_style} {character_hair_color} hair, {prompt_variables}, high quality, 8K Ultra HD, 3D effect, A digital illustration of {character_style} style, soft {character_style} tones, Atmosphere like Kyoto Animation, luminism, three dimensional effect, luminism, 3d render, octane render, Isometric, awesome full color, delicate and anime character expressions";
    } else {
        $template = "High quality, professional, {character_style} photograph of a {character_ethnicity} ethnicity {character_gender} {character_occupation} at {character_age} years old, {prompt_variables}, {character_hair_style} {character_hair_color} hair, {character_eye_color} eyes, a refined and proportionate nose, full and balanced lips, high and well-defined cheekbones, a gracefully sculpted jawline, narrow depth of field, film photography";
    }

    return $template;
}


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
    $images = growtype_art_get_model_images_grouped($model_id, 1000)[$type] ?? [];

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

    $model_details = !empty($model_id) ? growtype_art_get_model_details($model_id) : [];

    if (empty($model_details)) {
        return $prompt;
    }

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
 * @param $params
 * @return array|mixed|string|string[]
 */
function growtype_art_format_prompt_with_params($prompt, $params)
{
    if (empty($prompt) || empty($params)) {
        return $prompt;
    }

    if (is_string($params)) {
        $params = json_decode($params, true);
    }

    if (empty($params)) {
        return $prompt;
    }

    $key_mapping = [
        'gender' => 'character_gender',
        'age' => 'character_age',
        'ethnicity' => 'character_ethnicity',
        'eye_color' => 'character_eye_color',
        'eyes_color' => 'character_eye_color',
        'hair_style' => 'character_hair_style',
        'hair_color' => 'character_hair_color',
        'body_shape' => 'character_body_type',
        'body_type' => 'character_body_type',
        'breast_size' => 'character_breast_size',
        'butt_size' => 'character_butt_size',
        'occupation' => 'character_occupation',
        'character_style' => 'character_style',
        'personality' => 'character_personality',
        'hobbies' => 'character_hobbies',
        'relationship' => 'character_relationship',
        'marital_status' => 'character_relationship',
    ];

    $formatted_params = [];
    foreach ($params as $key => $value) {
        $adjusted_key = $key_mapping[$key] ?? $key;

        $final_value = '';
        if (is_array($value)) {
            if ($key === 'age' && isset($value[0]) && strpos($value[0], '-') !== false) {
                $age = explode('-', $value[0]);
                $final_value = rand((int)$age[0], (int)$age[1]);
            } else {
                $final_value = $value[0]['value'] ?? $value[0] ?? '';
            }
        } else {
            $final_value = $value;
        }

        if (is_string($final_value) || is_numeric($final_value)) {
            $formatted_params[$adjusted_key] = (string)$final_value;
        }
    }

    foreach ($formatted_params as $key => $value) {
        $prompt = str_ireplace('{' . $key . '}', $value, $prompt);
    }

    return $prompt;
}

/**
 * Helper to find image ID by its URL in our custom table.
 */
function growtype_art_get_image_id_by_url($url)
{
    if (empty($url)) {
        return null;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . Growtype_Art_Database::IMAGES_TABLE;

    // Try to find the image by URL. URL usually contains the name and extension.
    $image_name = pathinfo($url, PATHINFO_FILENAME);
    
    // We can also try a direct match if we store the full URL or a relative path.
    // However, looking at the schema, we store 'name', 'extension', 'folder'.
    // A better way would be to query by the filename if it's unique enough or use a more robust lookup.
    
    // For now, let's try to match by name as it's often unique in our system.
    $image_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_name WHERE name = %s LIMIT 1",
        $image_name
    ));

    return $image_id;
}

/**
 * ─────────────────────────────────────────────────────────────────────────────
 * PROVIDER LOOP REFACTORING
 * ─────────────────────────────────────────────────────────────────────────────
 */

/**
 * Helper to parse a provider entry and prepare generator data.
 */
function growtype_art_get_provider_executor_data($entry, $params, $global_models = [])
{
    $name = is_string($entry) ? $entry : ($entry['provider'] ?? '');
    if (empty($name)) return null;

    $class_name = sprintf('\partials\%s_Base', ucfirst($name));
    if (!class_exists($class_name)) return null;

    $models = [];
    if (is_array($entry) && (isset($entry['model']) || isset($entry['models']))) {
        $models_raw = $entry['models'] ?? $entry['model'];
        $models = is_array($models_raw) ? $models_raw : [$models_raw];
    } else {
        $models = !empty($global_models) ? $global_models : [null];
    }

    $iteration_params = $params;
    if (is_array($entry)) {
        foreach ($entry as $key => $val) {
            if ($key !== 'provider' && $key !== 'model' && $key !== 'models') {
                $iteration_params[$key] = $val;
            }
        }
    }

    return [
        'name' => $name,
        'class_name' => $class_name,
        'models' => $models,
        'params' => $iteration_params
    ];
}

/**
 * Standard loop to try multiple providers/models.
 */
function growtype_art_execute_with_fallback($providers, $params, $callback, $global_models = [])
{
    $generate_details = ['success' => false];

    foreach ($providers as $entry) {
        $executor = growtype_art_get_provider_executor_data($entry, $params, $global_models);
        if (!$executor) continue;

        $crud = new $executor['class_name']();

        foreach ($executor['models'] as $model_slug) {
            $iteration_params = $executor['params'];
            if ($model_slug !== null) {
                $iteration_params['segmind_model'] = $model_slug;
                $iteration_params['model'] = $model_slug;
            }

            try {
                $current_details = $callback($crud, $iteration_params, $model_slug);
            } catch (Exception $e) {
                $current_details = ['success' => false, 'message' => $e->getMessage()];
            } catch (Error $e) {
                $current_details = ['success' => false, 'message' => $e->getMessage()];
            }

            if ($current_details['success']) {
                $current_details['provider'] = $executor['name'];
                $current_details['model_used'] = $model_slug;
                return $current_details;
            }

            if (empty($generate_details['message']) || !$generate_details['success']) {
                $generate_details = $current_details;
                $generate_details['provider'] = $executor['name'];
                $generate_details['failed_model'] = $model_slug;
            }
        }
    }

    return $generate_details;
}

/**
 * Generate image for a model.
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

    return growtype_art_execute_with_fallback($providers, $params, function ($crud, $p) use ($model_id) {
        return $crud->generate_model_image($model_id, $p);
    });
}

/**
 * Generate video for a model.
 */
function growtype_art_generate_model_video($model_id, $params = [])
{
    $providers = $params['providers'] ?? [];
    if (empty($providers)) {
        $providers = Growtype_Art_Crud::API_GENERATE_VIDEO_PROVIDERS;
    }

    return growtype_art_execute_with_fallback($providers, $params, function ($crud, $p) use ($model_id) {
        return $crud->generate_model_video($model_id, $p);
    });
}

/**
 * Generate general image without specific model.
 */
function growtype_art_generate_image($params = [])
{
    $providers = $params['providers'] ?? [];
    $models = $params['models'] ?? [];

    if (empty($providers)) {
        $providers = Growtype_Art_Crud::PROVIDERS_TO_INSTANTLY_GENERATE_IMAGES;
    }

    if (isset($params['reference_image_url']) && !isset($params['reference_image_urls'])) {
        $params['reference_image_urls'] = [$params['reference_image_url']];
    }

    return growtype_art_execute_with_fallback($providers, $params, function ($crud, $p) {
        return $crud->generate_image($p);
    }, $models);
}
