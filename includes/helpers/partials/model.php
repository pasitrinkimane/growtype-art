<?php

function growtype_ai_admin_duplicate_model($model_id)
{
    $existing_model_details = growtype_ai_get_model_details($model_id);

    $reference_id = growtype_ai_generate_reference_id();

    $new_model_id = Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::MODELS_TABLE, [
        'prompt' => $existing_model_details['prompt'],
        'negative_prompt' => $existing_model_details['negative_prompt'],
        'reference_id' => $reference_id,
        'provider' => $existing_model_details['provider'],
        'image_folder' => Growtype_Ai_Crud::IMAGES_FOLDER_NAME . '/' . $reference_id
    ]);

    $model_settings = $existing_model_details['settings'];

    foreach ($model_settings as $key => $value) {
        $existing_content = growtype_ai_get_model_single_setting($new_model_id, $key);

        if (!empty($existing_content)) {
            continue;
        }

        Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
            'model_id' => $new_model_id,
            'meta_key' => $key,
            'meta_value' => $value
        ]);
    }

    return $new_model_id;
}

function growtype_ai_admin_update_model_settings($model_id, $model_settings, $allowed_keys = [])
{
    foreach ($model_settings as $meta_key => $meta_value) {
        $existing_content = growtype_ai_get_model_single_setting($model_id, $meta_key);

        if (empty($existing_content) && !in_array($meta_key, $allowed_keys)) {
            continue;
        }

        if ($meta_value === "true") {
            $meta_value = 1;
        } elseif ($meta_value === "false") {
            $meta_value = 0;
        }

        if (isset($existing_content['id'])) {
            Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
                'model_id' => $model_id,
                'meta_key' => $meta_key,
                'meta_value' => $meta_value,
            ], $existing_content['id']);
        } else {
            Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
                'model_id' => $model_id,
                'meta_key' => $meta_key,
                'meta_value' => $meta_value,
            ]);
        }
    }
}

function growtype_ai_admin_update_bundle_keys($key, $action)
{
    $bundle_ids = explode(',', preg_replace('/\s+/', '', get_option('growtype_ai_bundle_ids')));

    if ($action === 'add') {
        $bundle_ids = array_unique(array_merge($bundle_ids, $key));
    }

    if ($action === 'remove') {
        $bundle_ids = array_unique(array_diff($bundle_ids, $key));
    }

    update_option('growtype_ai_bundle_ids', implode(',', array_filter($bundle_ids)));
}
