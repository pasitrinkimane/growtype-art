<?php

class Retrieve_Video_Generation_Job
{
    public function run($job)
    {
        $job_payload = json_decode($job['payload'], true);
        $prediction_id = $job_payload['prediction_id'];
        $model_id = $job_payload['model_id'];
        $params = $job_payload['params'];
        $generation_id = $params['generation_id'] ?? '';
        $prompt = $params['prompt'] ?? '';
        $reference_image = $params['reference_image'] ?? null;

        $replicate_base = new partials\Replicate_Base();
        $access_token = $replicate_base::get_random_access_token();

        // Poll Replicate for prediction status
        $curl = curl_init();
        $max_attempts = 60; // 5 minutes max
        $attempts = 0;

        error_log(sprintf('Retrieve_Video_Generation_Job: Polling prediction %s', $prediction_id));

        do {
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.replicate.com/v1/predictions/$prediction_id",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    "Authorization: Token $access_token",
                    "Content-Type: application/json"
                ],
            ]);

            $response = curl_exec($curl);
            $data = json_decode($response, true);
            $status = $data['status'] ?? 'unknown';

            if ($status === 'succeeded' || $status === 'failed' || $status === 'canceled') {
                break;
            }

            sleep(5);
            $attempts++;
        } while ($attempts < $max_attempts);

        curl_close($curl);

        if ($status !== 'succeeded') {
            error_log(sprintf('Retrieve_Video_Generation_Job: Job did not succeed. Status: %s, Data: %s', $status, print_r($data, true)));
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
            throw new Exception('Failed to save video');
        }

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
