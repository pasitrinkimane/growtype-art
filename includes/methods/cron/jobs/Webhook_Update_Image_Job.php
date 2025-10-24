<?php

class Webhook_Update_Image_Job
{
    public function run($job)
    {
        $job_payload = json_decode($job['payload'], true);

        $image_id = isset($job_payload['image_id']) ? $job_payload['image_id'] : null;

        if (empty($image_id)) {
            return;
        }

        $model_details = growtype_art_get_image_model_details($image_id);

        $model_id = $model_details['id'] ?? null;

        /**
         * Fire webhook
         */
        if (!empty($model_id)) {
            $characters = growtype_art_get_featured_in_group_models([
                'models_ids' => [$model_id],
            ]);

            $characters = array_values($characters);

            $featured_in_domains = json_decode($model_details['settings']['featured_in'], true);
            $featured_in_domains = !empty($featured_in_domains) ? $featured_in_domains : [];

            foreach ($featured_in_domains as $featured_in_domain) {
                $url = 'https://' . $featured_in_domain . '.com/wp-json/artgenerator/v1/character/update';

                $credentials = [
                    'user' => getenv(strtoupper($featured_in_domain) . '_WP_REST_USER'),
                    'password' => getenv(strtoupper($featured_in_domain) . '_WP_REST_PASSWORD'),
                ];

                try {
                    Growtype_Art_Api::fire_webhook($url, $characters, $credentials);
                } catch (Exception $e) {
                    throw new Exception($e->getMessage());
                }
            }
        }
    }
}
