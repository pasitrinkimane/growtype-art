<?php

try {
    $model_id = $job_payload['model_id'];

    error_log('model_id: ' . $model_id);

    $model = growtype_ai_get_model_details($model_id);

    if (empty($model)) {

        Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
            'exception' => 'empty model',
            'reserved' => 0
        ], $job['id']);

        exit();
    }

    $saving_location = 'locally';

    $cloudinary = new Cloudinary_Crud();

    $assets = $cloudinary->get_assets([
        'resource_type' => 'image',
        'type' => 'upload',
        'prefix' => $model['image_folder'],
        'max_results' => 1000,
        'tags' => true
    ]);

    $cd_images = $assets['resources'];

    if (empty($cd_images)) {

        error_log('empty $cd_images: ' . print_r($cd_images, true));

        $cloudinary->delete_folder($model['image_folder']);

        Growtype_Ai_Database_Crud::delete_records(Growtype_Ai_Database::MODEL_JOBS_TABLE, [$job['id']]);

        exit();
    }

    $cloudinary_public_ids = [];

//d($cd_images);

    foreach ($cd_images as $cd_image) {

//    error_log('cd_image: ' . print_r($cd_image, true));

        $cd_image_folder = $cd_image['folder'];
        $cd_image_name = str_replace($cd_image['folder'] . '/', '', $cd_image['public_id']);

//    $image_folder = 'leonardoai/ae89042f398532edfa87ae3d308a5849';
//    $image_name = '63fd125bb09f3';

        $existing_image = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::IMAGES_TABLE, [
            [
                'key' => 'name',
                'values' => [$cd_image_name]
            ]
        ]);

//        d($existing_image);

        if (empty($existing_image)) {
            $image_id = Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::IMAGES_TABLE, [
                'name' => $cd_image_name,
                'extension' => $cd_image['format'],
                'width' => $cd_image['width'],
                'height' => $cd_image['height'],
                'location' => $saving_location,
                'folder' => $cd_image['folder']
            ]);

            Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::MODEL_IMAGE_TABLE, ['model_id' => $model_id, 'image_id' => $image_id]);

            if (!empty($cd_image['tags'])) {
                Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                    'image_id' => $image_id,
                    'meta_key' => 'tags',
                    'meta_value' => json_encode($cd_image['tags'])
                ]);
            }
        } else {
            Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::IMAGES_TABLE, [
                'extension' => $cd_image['format'],
                'location' => $saving_location,
            ], $existing_image[0]['id']);
        }

        growtype_ai_save_file([
            'location' => $saving_location,
            'url' => $cd_image['url'],
            'name' => $cd_image_name,
            'extension' => $cd_image['format'],
        ], $cd_image['folder']);

        array_push($cloudinary_public_ids, $cd_image['public_id']);
    }

    error_log('delete_asset: ' . print_r($cloudinary_public_ids, true));

    $cloudinary_public_ids_groups = array_chunk($cloudinary_public_ids, 100);

    foreach ($cloudinary_public_ids_groups as $group) {
        $cloudinary->delete_asset($group);

        sleep(1);
    }

    error_log('delete_folder: ' . print_r($model['image_folder'], true));

    $cloudinary->delete_folder($model['image_folder']);

    /**
     * Wait aliitle bit before deleting the job
     */
    sleep(5);

    Growtype_Ai_Database_Crud::delete_records(Growtype_Ai_Database::MODEL_JOBS_TABLE, [$job['id']]);
} catch (Exception $e) {
    Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
        'exception' => $e->getMessage(),
        'reserved' => 0
    ], $job['id']);
}
