<?php

namespace partials;

use Growtype_Art_Crud;
use Growtype_Art_Database;
use Growtype_Art_Database_Crud;

class Fal_Base
{
    public static function api_key()
    {
        return \Growtype_Auth::credentials('fal');
    }

    public function generate_model_image($model_id, $params = [])
    {
//        dd([
//            $model_id,
//            $params
//        ]);

        $model = growtype_art_get_model_details($model_id);

        $prompt = isset($params['prompt']) && !empty($params['prompt']) ? $params['prompt'] : $model['prompt'];

        $formatted_prompt = growtype_art_model_format_prompt($prompt, $model_id);

        $api_keys = self::api_key();

        if (empty($api_keys)) {
            return [
                'success' => false,
                'message' => sprintf('Empty API keys. Model %s.', $model_id),
            ];
        }

        $api_group_key = array_keys($api_keys)[array_rand(array_keys(self::api_key()))];

        $params['token'] = $this->get_access_token($api_group_key);
        $params['prompt'] = $formatted_prompt;
        $params['generation_id'] = wp_generate_password(52, false);
        $params['model_id'] = $model_id;

        $model = growtype_art_get_model_details($model_id);
        $model_images = growtype_art_get_model_images_grouped($model_id, 200)['original'] ?? [];

        $image_input = [];
        if (isset($params['reference_image_urls']) && !empty($params['reference_image_urls'])) {
            $image_input = $params['reference_image_urls'];
        }

        if (empty($image_input) && !empty($model_images)) {
            foreach ($model_images as $model_image) {
                if (isset($model_image['settings']['is_cover']) && $model_image['settings']['is_cover']) {
                    $image_url = growtype_art_get_image_url($model_image['id']);
                    $image_url = growtype_art_image_get_alternative_format($image_url, 'jpg', true);
                    array_push($image_input, $image_url);
                    break;
                }
            }

            if (empty($image_input)) {
                $model_image = $model_images[0];
                $image_url = growtype_art_get_image_url($model_image['id']);
                $image_url = growtype_art_image_get_alternative_format($image_url, 'jpg', true);
                array_push($image_input, $image_url);
            }
        }

//        $image_input_formatted = [];
//        foreach ($image_input as $image_input_single) {
//
//            str_replace()
//
//            $image_input_formatted[] = $image_input_single;
//        }

        $params['image_urls'] = $image_input;

        $generation_details = $this->generate_image_init($params);

        $request_id = $generation_details['data']['request_id'] ?? '';

        if (empty($request_id) || isset($generation_details['errors'])) {
            return [
                'success' => false,
                'message' => 'Empty $request_id',
            ];
        }

//        ddd($generation_details);
//        ddd($request_id);

        $generation_details = self::poll_fal_request($request_id);

        if (!$generation_details['success']) {
            return [
                'success' => false,
                'message' => 'Empty generations',
            ];
        }

//        ddd($generation_details);

        $response = $this->save_generations($generation_details['images'], $model_id, $params);

//        d($response);

        return [
            'success' => true,
            'generations' => $response,
            'message' => sprintf('Successfully generated. Prompt: %s', $prompt)
        ];
    }

    function poll_fal_request($request_id)
    {
        $url = 'https://queue.fal.run/fal-ai/flux-2/requests/' . $request_id;

        $max_attempts = 20;
        $interval = 7; // seconds

        for ($i = 1; $i <= $max_attempts; $i++) {

            $response = wp_remote_get($url, [
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'message' => 'fal_request failed',
                ];
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

//            dd($data);

            // Completed
            if (!empty($data['images'])) {
                return [
                    'success' => true,
                    'images' => $data['images']
                ];
            }

            // Still running → wait
            if ($i < $max_attempts) {
                sleep($interval);
            }
        }

        return [
            'success' => false,
            'message' => 'Request not completed after 10 attempts',
        ];
    }

    public function get_access_token($api_group_key)
    {
        return self::api_key()[$api_group_key]['api_key'] ?? '';
    }

    public function generate_image_init($params)
    {
        $apiKey = $params['token'];
        $url = 'https://queue.fal.run/fal-ai/flux-2/edit';

        $postData = [
            'prompt' => $params['prompt'],
            'image_urls' => $params['image_urls'] ?? [],// Array of input image URLs
            'image_size' => $params['image_size'] ?? ['width' => 768, 'height' => 1024],
            'num_images' => $params['num_images'] ?? 1,
            'enable_safety_checker' => $params['enable_safety_checker'] ?? false,
            'guidance_scale' => $params['guidance_scale'] ?? 7.5,
            'num_inference_steps' => $params['num_inference_steps'] ?? 32
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Key $apiKey",
            "Content-Type: application/json",
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['error' => true, 'message' => "CURL Error: $error"];
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            return ['error' => true, 'message' => "HTTP Error: $httpCode"];
        }

        $decoded = json_decode($response, true);
        if (!$decoded) {
            return ['error' => true, 'message' => 'Failed to decode API response'];
        }

        $requestId = $decoded['request_id'] ?? null;

        return [
            'success' => true,
            'data' => $decoded,
            'request_id' => $requestId
        ];
    }

    function save_generations($generations, $model_id, $params)
    {
        $saved_generations = [];
        foreach ($generations as $generation) {
            $model = growtype_art_get_model_details($model_id);

            $image_folder = $model['image_folder'];
            $image_location = growtype_art_get_images_saving_location();

            $image['folder'] = $image_folder;
            $image['location'] = $image_location;
            $image['url'] = $generation['url'];
            $image['meta_details'] = [
                [
                    'key' => 'generation_id',
                    'value' => $params['generation_id']
                ],
                [
                    'key' => 'provider',
                    'value' => Growtype_Art_Crud::FAL_KEY
                ],
                [
                    'key' => 'prompt',
                    'value' => $params['prompt']
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
                error_log('save_generations: ' . json_encode($saved_image));
                continue;
            }

            /**
             * Assign image to model
             */
            Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::MODEL_IMAGE_TABLE, [
                'model_id' => $model_id,
                'image_id' => $saved_image['id']
            ]);

            $saved_generations[] = [
                'url' => $saved_image['details']['url'],
                'image_id' => $saved_image['id'],
                'generation_id' => $params['generation_id'],
                'image_prompt' => $params['prompt'],
            ];
        }

        do_action('growtype_art_model_update', $model_id);

        return $saved_generations;
    }
}

