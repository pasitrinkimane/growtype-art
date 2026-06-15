<?php

class Retrieve_Video_Generation_Job
{
    public function run($job)
    {
        $job_payload = json_decode($job['payload'], true);
        $prediction_id = $job_payload['prediction_id'];
        $model_id = $job_payload['model_id'];
        $params = $job_payload['params'];
        $params['video_model'] = $params['video_model'] ?? 'wan-video/wan-2.2-i2v-fast';
        $generation_id = $params['generation_id'] ?? '';
        $prompt = $params['prompt'] ?? '';
        $reference_image = $params['reference_image'] ?? null;

        $replicate_base = new partials\Replicate_Base();
        $access_token = $replicate_base->get_random_access_token();

        $max_attempts = 60; // 5 minutes max
        $attempts = 0;

        error_log(sprintf('Retrieve_Video_Generation_Job: Polling prediction %s', $prediction_id));

        do {
            $wp_response = wp_remote_get(
                "https://api.replicate.com/v1/predictions/$prediction_id",
                [
                    'headers' => [
                        'Authorization' => "Token $access_token",
                        'Content-Type'  => 'application/json',
                    ],
                    'timeout' => 15,
                ]
            );

            if (is_wp_error($wp_response)) {
                error_log('Retrieve_Video_Generation_Job: wp_remote_get error: ' . $wp_response->get_error_message());
                sleep(5);
                $attempts++;
                continue;
            }

            $data   = json_decode(wp_remote_retrieve_body($wp_response), true);
            $status = $data['status'] ?? 'unknown';

            if ($status === 'succeeded' || $status === 'failed' || $status === 'canceled') {
                break;
            }

            sleep(5);
            $attempts++;
        } while ($attempts < $max_attempts);

        if ($status !== 'succeeded') {
            error_log(sprintf('Retrieve_Video_Generation_Job: Job did not succeed. Status: %s, Data: %s', $status, print_r($data, true)));
            do_action('growtype_art_generation_failed', $model_id, array_merge([
                'prompt' => $prompt,
                'generation_id' => $generation_id,
                'asset_type' => 'video',
            ], $params), $data['error'] ?? $status, Growtype_Art_Crud::REPLICATE_KEY);
            
            if (in_array($status, ['failed', 'canceled'], true)) {
                return; // Permanent failure: exit cleanly so the cron job is cleared
            }
            
            throw new Exception('Video generation failed: ' . ($data['error'] ?? $status));
        }

        error_log(sprintf('Retrieve_Video_Generation_Job: Job finished successfully. Output: %s', print_r($data['output'], true)));

        // Save the video
        $model = growtype_art_get_model_details($model_id);
        $image_folder = $model['image_folder'];
        $image_location = growtype_art_get_images_saving_location();

        $image = [
            'folder' => $image_folder,
            'location' => $image_location,
            'url' => $data['output'],
            'meta_details' => [
                [
                    'key' => 'generation_id',
                    'value' => $generation_id
                ],
                [
                    'key' => 'provider',
                    'value' => Growtype_Art_Crud::REPLICATE_KEY
                ],
                [
                    'key' => 'prompt',
                    'value' => $prompt
                ]
            ]
        ];

        if (isset($params['types'])) {
            foreach ($params['types'] as $type) {
                $image['meta_details'][] = [
                    'key' => $type,
                    'value' => 1
                ];
            }
        }

        $saved_image = Growtype_Art_Crud::save_image($image);

        if (empty($saved_image) || isset($saved_image['error']) || !isset($saved_image['id'])) {
            error_log('Retrieve_Video_Generation_Job: save_image error: ' . print_r($saved_image, true));
            do_action('growtype_art_generation_failed', $model_id, array_merge([
                'prompt' => $prompt,
                'generation_id' => $generation_id,
                'asset_type' => 'video',
            ], $params), $saved_image['error'] ?? 'save_image failed', Growtype_Art_Crud::REPLICATE_KEY);
            throw new Exception('Failed to save video');
        }

        // Trigger logger action for successful video generation
        do_action('growtype_art_generation_success', $saved_image, $model_id, array_merge([
            'prompt' => $prompt,
            'generation_id' => $generation_id,
            'asset_type' => 'video',
        ], $params), Growtype_Art_Crud::REPLICATE_KEY);

        // Link to model
        Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::MODEL_IMAGE_TABLE, [
            'model_id' => $model_id,
            'image_id' => $saved_image['id']
        ]);

        // Link to reference image
        $reference_image_id = $reference_image['id'] ?? null;
        if (empty($reference_image_id) && !empty($reference_image['url'])) {
            $reference_image_id = growtype_art_get_image_id_by_url($reference_image['url']);
        }

        if (!empty($reference_image_id)) {
            Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                'image_id' => $reference_image_id,
                'meta_key' => 'video_url_image_id_' . $saved_image['id'],
                'meta_value' => $saved_image['details']['url'],
            ]);

            Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                'image_id' => $saved_image['id'],
                'meta_key' => 'parent_image_id',
                'meta_value' => $reference_image_id,
            ]);
        }

        do_action('growtype_art_model_update', $model_id);

        error_log(sprintf('Retrieve_Video_Generation_Job: Video saved. Image ID: %s, URL: %s', $saved_image['id'], $saved_image['details']['url']));
    }
}
