<?php

class Model_Assign_Categories_Job
{
    public function run($job)
    {
        $job_payload = json_decode($job['payload'], true);

        $model_id = $job_payload['model_id'];
        $model = growtype_ai_get_model_details($model_id);

        if (isset($model['settings']) && !isset($model['settings']['categories']) && isset($model['settings']['tags']) && !empty($model['settings']['tags'])) {
            $tags = $model['settings']['tags'];
            $tags = json_decode($tags, true);

            ddd($model);
            ddd($tags);

            $existing_categories = growtype_ai_get_art_categories();

            $assigned_categories = [];
            foreach ($tags as $tag) {
                foreach ($existing_categories as $category => $values) {
                    $formatted_category = strtolower($category);

                    if (str_contains($formatted_category, $tag)) {
                        $assigned_categories[$category] = [];
                    }
                }
            }

            d($assigned_categories);

            if (!empty($assigned_categories)) {
                Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
                    'model_id' => $model_id,
                    'meta_key' => 'categories',
                    'meta_value' => json_encode($assigned_categories),
                ]);
            }
        }
    }
}
