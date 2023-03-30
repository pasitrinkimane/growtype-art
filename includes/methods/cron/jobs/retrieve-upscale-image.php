<?php

/**
 * Try to retrieve the image
 */
$get_url = $job_payload['upscaled_image']['urls']['get'];

$replicate = new Replicate();

$retrieve = $replicate->real_esrgan_retrieve($get_url);

$output = $retrieve['output'];

error_log(print_r([
    'action' => 'retrieved',
    'url' => $output
], true));

if (!empty($output)) {
    try {
        /**
         * Compress image
         */
        $resmush = new Resmush();

        $img_url = $resmush->compress_online($output);

        if (empty($img_url)) {
            Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
                'exception' => 'Image not compressed',
                'reserved' => 0
            ], $job['id']);

            return;
        }

        error_log(print_r([
            'action' => 'compressed',
            'url' => $img_url,
            'public_id' => $job_payload['original_image']['public_id'],
        ], true));

        $cloudinary = new Cloudinary_Crud();

        $public_id = $job_payload['original_image']['public_id'];

        $cloudinary->upload_asset($img_url, [
            'public_id' => $public_id
        ]);

        $cloudinary->add_context($public_id, [
            'real_esrgan' => 'true',
            'compressed' => 'true'
        ]);

        $image_id = $job_payload['original_image']['id'];

        $image = growtype_ai_get_image_details($image_id);

        if (!isset($image['settings']['real_esrgan'])) {
            Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                'image_id' => $image_id,
                'meta_key' => 'real_esrgan',
                'meta_value' => 'true',
            ]);
        }

        if (!isset($image['settings']['compressed'])) {
            Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                'image_id' => $image_id,
                'meta_key' => 'compressed',
                'meta_value' => 'true',
            ]);
        }

        Growtype_Ai_Database_Crud::delete_records(Growtype_Ai_Database::MODEL_JOBS_TABLE, [$job['id']]);
    } catch (Exception $e) {
        Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
            'exception' => $e->getMessage(),
            'reserved' => 0
        ], $job['id']);
    }
} else {
    Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
        'exception' => 'Not generated yet',
        'reserved' => 0
    ], $job['id']);
}

sleep(5);
