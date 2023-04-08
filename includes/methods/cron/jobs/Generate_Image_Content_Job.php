<?php

class Generate_Image_Content_Job
{
    public function run($job_payload)
    {
        $model = growtype_ai_get_image_model_details($job_payload['image_id']);

        if (empty($model)) {
            throw new Exception('Empty model for image');
        }

        $image = growtype_ai_get_image_details($job_payload['image_id']);
        $tags = !empty($model) && isset($model['settings']['tags']) && !empty($model['settings']['tags']) ? json_decode($model['settings']['tags'], true) : [];
        $title = !empty($model) ? $model['settings']['title'] : null;
        $description = !empty($model) ? $model['settings']['description'] : null;

        if (!isset($image['settings']['caption']) && empty($image['settings']['caption'])) {
            $openai_crud = new Openai_Crud();
            $alt_title = $openai_crud->generate_content($title, 'alt-title');

            if (!empty($alt_title)) {
                $alt_title = str_replace('"', "", $alt_title);
                $alt_title = str_replace("'", "", $alt_title);

                Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                    'image_id' => $job_payload['image_id'],
                    'meta_key' => 'caption',
                    'meta_value' => $alt_title,
                ]);
            }
        }

        if (!isset($image['settings']['alt_text']) && empty($image['settings']['alt_text'])) {
            $openai_crud = new Openai_Crud();
            $alt_description = $openai_crud->generate_content($description, 'alt-description');

            if (!empty($alt_description)) {
                $alt_description = str_replace('"', "", $alt_description);

                Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                    'image_id' => $job_payload['image_id'],
                    'meta_key' => 'alt_text',
                    'meta_value' => $alt_description,
                ]);
            }
        }

        if (!isset($image['settings']['tags']) && empty($image['settings']['tags'])) {
            Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                'image_id' => $job_payload['image_id'],
                'meta_key' => 'tags',
                'meta_value' => !empty($tags) ? json_encode($tags) : null,
            ]);
        }

//    $cloudinary_crud = new Cloudinary_Crud();
//    $cloudinary_crud->update_cloudinary_image_details($job_payload['image_id']);
    }
}
