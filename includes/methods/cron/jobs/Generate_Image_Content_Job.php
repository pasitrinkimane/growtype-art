<?php

class Generate_Image_Content_Job
{
    public function run($job)
    {
        $job_payload = json_decode($job['payload'], true);

        $image_id = $job_payload['image_id'] ?? '';

        if (empty($image_id)) {
            throw new Exception('Empty image id');
        }

        $regenerate_content = isset($job_payload['regenerate_content']) ? $job_payload['regenerate_content'] : false;

        $model = growtype_art_get_image_model_details($image_id);

        if (empty($model)) {
            throw new Exception('Empty model for image');
        }

        $image = growtype_art_get_image_details($image_id);

        $tags = isset($image['prompt']) ? $image['prompt'] : null;
        $title = isset($model['settings']['title']) ? $model['settings']['title'] : null;
        $description = isset($model['settings']['description']) ? $model['settings']['description'] : null;
        $prompt = isset($model['prompt']) ? $model['prompt'] : null;

        $required_fields = [
            'caption' => [
                'generate' => true,
                'content' => $title
            ],
            'alt_text' => [
                'generate' => true,
                'content' => $description
            ],
            'tags' => [
                'generate' => true,
                'content' => $tags
            ]
        ];

        foreach ($required_fields as $meta_key => $field) {
            if (!isset($image['settings'][$meta_key])) {
                Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                    'image_id' => $image['id'],
                    'meta_key' => $meta_key,
                    'meta_value' => '',
                ]);
            }
        }

        /**
         * Get image details again
         */
        $image = growtype_art_get_image_details($image['id']);

        foreach ($required_fields as $meta_key => $field) {
            if ($regenerate_content || empty($image['settings'][$meta_key])) {
                $openai_crud = new Openai_Base_Image();

                $content = $field['content'];

                if (isset($field['generate']) && $field['generate']) {
                    $content = $openai_crud->generate_text_content($prompt, $meta_key);
                }

                if (!empty($content)) {
                    $image_setting = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                        [
                            'key' => 'image_id',
                            'value' => $image['id'],
                        ],
                        [
                            'key' => 'meta_key',
                            'value' => $meta_key,
                        ]
                    ], 'where');

                    if (!empty($image_setting)) {
                        Growtype_Art_Database_Crud::update_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                            'meta_value' => $content,
                        ], $image_setting[0]['id']);
                    }
                }
            }
        }

//    $cloudinary_crud = new Cloudinary_Crud();
//    $cloudinary_crud->update_cloudinary_image_details($job_payload['image_id']);
    }
}
