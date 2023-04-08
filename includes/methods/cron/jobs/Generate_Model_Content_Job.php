<?php

class Generate_Model_Content_Job
{
    public function run($job_payload)
    {
        $existing_content = growtype_ai_get_model_single_setting($job_payload['model_id'], $job_payload['meta_key']);

        $openai_crud = new Openai_Crud();
        $new_content = $openai_crud->generate_content($job_payload['prompt'], $job_payload['meta_key']);

        if (empty($new_content)) {
            throw new Exception('Empty content');
        }

        if ($job_payload['encode']) {
            $new_content = json_decode($new_content, true);
            $new_content = json_encode($new_content);
        } else {
            $new_content = str_replace('"', "", $new_content);
        }

        if (empty($new_content)) {
            throw new Exception('Empty content');
        }

        /**
         * tags
         */
        if (!empty($existing_content)) {
            Growtype_Ai_Database_Crud::update_record(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
                'model_id' => $job_payload['model_id'],
                'meta_key' => $job_payload['meta_key'],
                'meta_value' => $new_content,
            ], $existing_content['id']);
        } else {
            Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
                'model_id' => $job_payload['model_id'],
                'meta_key' => $job_payload['meta_key'],
                'meta_value' => $new_content,
            ]);
        }
    }
}
