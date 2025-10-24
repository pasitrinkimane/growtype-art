<?php

use partials\Leonardoai_Base;
use partials\Piclumen_Base;

class Retrieve_Model_Job
{
    public function run($job)
    {
        $job_payload = json_decode($job['payload'], true);

        try {
            $provider_class_name = sprintf('\partials\%s_Base', ucfirst($job_payload['provider']));

            if ($job_payload['provider'] === Growtype_Art_Crud::LEONARDOAI_KEY) {
                $leonardoai_base = new Leonardoai_Base();

                $generations = $leonardoai_base->retrieve_generations($job_payload['model_id'], $job_payload['generation_id'], [
                    'user_nr' => $job_payload['user_nr'],
                    'post_id' => $job_payload['post_id'] ?? ''
                ]);

                error_log('Retrieve_Model_Job generations: ' . json_encode($generations));

                $faceswap_new_uploads = growtype_art_get_model_single_setting($job_payload['model_id'], 'faceswap_new_uploads');
                $faceswap_new_uploads = !empty($faceswap_new_uploads) ? $faceswap_new_uploads['meta_value'] : false;

                if ($faceswap_new_uploads) {
                    Retrieve_Faceswap_Image_Job::initiate($job_payload['model_id']);
                }
            } elseif (class_exists($provider_class_name)) {
                $crud = new $provider_class_name();
                $generations = $crud->retrieve_generations($job_payload['model_id'], [$job_payload['generation_id']], [
                    'prompt' => $job_payload['prompt'] ?? '',
                    'user_nr' => $job_payload['user_nr'] ?? '',
                    'post_id' => $job_payload['post_id'] ?? '',
                    'api_group_key' => $job_payload['api_group_key'] ?? '',
                ]);
                error_log(sprintf('Retrieve_Model_Job %s generations: %s', $job_payload['provider'], json_encode($generations)));
            }
        } catch (Exception $e) {
            error_log('Retrieve_Model_Job error: ' . $e->getMessage());

            /**
             * Update available_at time
             */
            $available_at = date('Y-m-d H:i:s', strtotime(wp_date('Y-m-d H:i:s')) + 90);

            Growtype_Cron_Crud::update_record(Growtype_Cron_Database::JOBS_TABLE, [
                'available_at' => $available_at,
            ], $job['id']);

            throw new Exception($e->getMessage());
        }
    }
}
