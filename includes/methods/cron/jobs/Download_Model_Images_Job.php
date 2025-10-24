<?php

class Download_Model_Images_Job
{
    public function run($job)
    {
        $job_payload = json_decode($job['payload'], true);

        $models = [
            [
                'id' => $job_payload['model_id']
            ]
        ];

        $cloudinary = new Cloudinary_Crud();

        $saving_location = 'locally';

        foreach ($models as $model) {
            $images = growtype_art_get_model_images_grouped($model['id'])['original'] ?? [];

            if (!empty($images)) {
                foreach ($images as $image) {

                    if ($image['location'] === $saving_location) {
                        continue;
                    }

                    $cloudinary_public_id = growtype_art_get_cloudinary_public_id($image);

                    if (empty($cloudinary_public_id)) {
                        throw new Exception('no public id');
                    }

                    $cd_img = $cloudinary->get_asset($cloudinary_public_id, [
                        'colors' => true
                    ]);

                    if (!isset($cd_img['asset_id'])) {
                        Growtype_Art_Database_Crud::delete_records(Growtype_Art_Database::IMAGES_TABLE, [$image['id']]);
                        continue;
                    }

                    $colors = $cd_img['colors'];
                    $predominant = $cd_img['predominant'];

                    if (!isset($image['settings']['colors'])) {
                        Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                            'image_id' => $image['id'],
                            'meta_key' => 'colors',
                            'meta_value' => json_encode($colors)
                        ]);
                    }

                    if (!isset($image['settings']['predominant_colors'])) {
                        Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                            'image_id' => $image['id'],
                            'meta_key' => 'predominant_colors',
                            'meta_value' => json_encode($predominant),
                        ]);
                    }

                    growtype_art_save_external_file([
                        'location' => $saving_location,
                        'url' => $cd_img['url'],
                        'name' => $image['name'],
                        'extension' => $cd_img['format'],
                    ], $image['folder']);

                    $cloudinary->delete_asset([$cloudinary_public_id]);

                    Growtype_Art_Database_Crud::update_record(Growtype_Art_Database::IMAGES_TABLE, [
                        'location' => $saving_location
                    ], $image['id']);

                    sleep(5);
                }
            } else {
                throw new Exception('No images');
            }
        }
    }
}
