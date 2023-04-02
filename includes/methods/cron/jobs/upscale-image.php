<?php

try {
    $image_id = isset($job_payload['image_id']) ? $job_payload['image_id'] : null;

    if (empty($image_id)) {
        return;
    }

    $image = growtype_ai_get_image_details($image_id);

    if (empty($image)) {
        return;
    }

    $max_width = isset($job_payload['max_width']) ? $job_payload['max_width'] : 1280;

    $image_path = growtype_ai_get_image_path($image_id);

    $size = getimagesize($image_path);

    if ($size[0] < $max_width) {

        shell_exec('cd ' . GROWTYPE_AI_PATH . 'resources/plugins/realesrgan; sh run.sh ' . $image_path . ' ' . $image_path . ' 2  2>&1');

        if (!isset($image['settings']['real_esrgan'])) {
            Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                'image_id' => $image_id,
                'meta_key' => 'real_esrgan',
                'meta_value' => 'true',
            ]);
        }

        $size = getimagesize($image_path);

        Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::IMAGES_TABLE, [
            'width' => $size[0],
            'height' => $size[1],
        ], $image['id']);

        $resmush = new Resmush();
        $img_url = $resmush->compress(growtype_ai_get_image_path($image['id']));

        if (!isset($image['settings']['compressed'])) {
            Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                'image_id' => $image['id'],
                'meta_key' => 'compressed',
                'meta_value' => 'true',
            ]);
        }
    }

    /**
     * Delete job
     */
    Growtype_Ai_Database_Crud::delete_records(Growtype_Ai_Database::MODEL_JOBS_TABLE, [$job['id']]);
} catch (Exception $e) {
    Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
        'reserved' => 0,
        'exception' => $e->getMessage(),
    ], $job['id']);
}

