<?php

class Retrieve_Faceswap_Image_Job
{
    public static function initiate($model_id, $limit = 10)
    {
        $face_swap_photos = growtype_art_get_model_single_setting($model_id, 'face_swap_photos');
        $model_images_original = growtype_art_get_model_images_grouped($model_id)['original'] ?? [];

        $counter = 0;
        foreach ($model_images_original as $image) {
            $target_image_id = $image['id'];

            if (isset($image['settings']['faceswap'])) {
                continue;
            }

            if ($counter > $limit) {
                continue;
            }

            $existing_faceswaped_image = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                [
                    'key' => 'meta_key',
                    'value' => 'original_image_id',
                ],
                [
                    'key' => 'meta_value',
                    'value' => $target_image_id,
                ]
            ], 'where');

            if (!empty($existing_faceswaped_image)) {
                continue;
            }

            if (isset($face_swap_photos['meta_value']) && !empty($face_swap_photos['meta_value'])) {
                $faceswap_images = json_decode($face_swap_photos['meta_value'], true);

                if (!empty($faceswap_images)) {
                    foreach ($faceswap_images as $faceswap_image_url) {
                        try {
                            $replicate_crud = new Replicate_Crud();
                            $replicate_crud->faceswap($target_image_id, $faceswap_image_url);
                            $counter++;

                            /**
                             * Update original image
                             */
                            Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                                'image_id' => $target_image_id,
                                'meta_key' => 'faceswap',
                                'meta_value' => 'true',
                            ]);
                        } catch (Exception $e) {
                            throw new Exception($e);
                        }
                    }
                }
            }
        }
    }

    public function run($job)
    {
        $job_payload = json_decode($job['payload'], true);
        $get_url = $job_payload['response']['urls']['get'];
        $replicate = new Replicate_Crud();
        $retrieve = $replicate->retrieve_generation($get_url);
        $output = $retrieve['output'] ?? '';

        if (empty($output)) {
            throw new Exception('Not generated yet');
        }

        try {
            $original_image_id = $job_payload['original_image_id'];

            if (!empty($original_image_id)) {
                $image = growtype_art_get_image_details($original_image_id);

                if (!empty($image)) {
                    $image_folder = $image['folder'];
                    $swap_img_name = $job_payload['swap_image_url'] ?? '';
                    $swap_img_name = !empty($swap_img_name) ? pathinfo(basename($swap_img_name), PATHINFO_FILENAME) : '';

                    $save_img_data = [
                        'url' => $output,
                        'folder' => $image_folder . '/faceswap',
                        'name' => self::image_name($image['name'], $swap_img_name),
                        'meta_details' => [
                            [
                                'key' => 'generated_image_id',
                                'value' => $job_payload['response']['id'] ?? ''
                            ],
                            [
                                'key' => 'original_image_id',
                                'value' => $job_payload['original_image_id']
                            ],
                            [
                                'key' => 'faceswap',
                                'value' => 'true'
                            ]
                        ]
                    ];

                    $saved_image = Growtype_Art_Crud::save_image($save_img_data);

                    if (empty($saved_image) || isset($saved_image['error']) || !isset($saved_image['id'])) {
                        error_log('save_generations: ' . json_encode($saved_image));
                        throw new Exception('Failed to save image');
                    }

                    /**
                     * Assign image to model
                     */
                    Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::MODEL_IMAGE_TABLE, [
                        'model_id' => $image['model_id'],
                        'image_id' => $saved_image['id']
                    ]);
                }
            }
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public static function image_name($image_name, $swap_img_name)
    {
        return sprintf('%s-%s-faceswap', $image_name, $swap_img_name);
    }
}
