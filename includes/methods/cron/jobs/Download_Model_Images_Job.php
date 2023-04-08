<?php

class Download_Model_Images_Job
{
    public function run($job_payload)
    {
        $models = [
            [
                'id' => $job_payload['model_id']
            ]
        ];

        $cloudinary = new Cloudinary_Crud();

        $saving_location = 'locally';

//                        d($models);

        foreach ($models as $model) {

            $images = growtype_ai_get_model_images($model['id']);

//                            d($images);

            if (!empty($images)) {
                foreach ($images as $image) {

                    if ($image['location'] === $saving_location) {
                        continue;
                    }

                    $cloudinary_public_id = growtype_ai_get_cloudinary_public_id($image);

                    if (empty($cloudinary_public_id)) {
                        throw new Exception('no public id');
                    }

                    $cd_img = $cloudinary->get_asset($cloudinary_public_id, [
                        'colors' => true
                    ]);

                    if (!isset($cd_img['asset_id'])) {
                        Growtype_Ai_Database_Crud::delete_records(Growtype_Ai_Database::IMAGES_TABLE, [$image['id']]);
                        continue;
                    }

                    $colors = $cd_img['colors'];
                    $predominant = $cd_img['predominant'];

                    if (!isset($image['settings']['colors'])) {
                        Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                            'image_id' => $image['id'],
                            'meta_key' => 'colors',
                            'meta_value' => json_encode($colors)
                        ]);
                    }

                    if (!isset($image['settings']['predominant_colors'])) {
                        Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                            'image_id' => $image['id'],
                            'meta_key' => 'predominant_colors',
                            'meta_value' => json_encode($predominant),
                        ]);
                    }

                    growtype_ai_save_file([
                        'location' => $saving_location,
                        'url' => $cd_img['url'],
                        'name' => $image['name'],
                        'extension' => $cd_img['format'],
                    ], $image['folder']);

//                                    d('done');

                    $cloudinary->delete_asset([$cloudinary_public_id]);

                    Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::IMAGES_TABLE, [
                        'location' => $saving_location
                    ], $image['id']);

                    sleep(5);

//                                    d('done image');
                }
            } else {
                throw new Exception('No images');
            }
        }
    }
}
