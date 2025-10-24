<?php

namespace partials;

use Growtype_Art_Crud;
use Growtype_Art_Database;
use Growtype_Art_Database_Crud;

class Togetherai_Base
{
    public function generate_model_image($model_id, $params = [])
    {
        $model = growtype_art_get_model_details($model_id);

        $prompt = isset($params['prompt']) && !empty($params['prompt']) ? $params['prompt'] : $model['prompt'];

        $formatted_prompt = growtype_art_model_format_prompt($prompt, $model_id);

        $params['prompt'] = $formatted_prompt;
        $params['generation_id'] = wp_generate_password(52, false);

        $generation_details = $this->generate_image_init($params);

        if (empty($generation_details) || isset($generation_details['errors']) || !$generation_details['success']) {
            return [
                'success' => false,
                'message' => $generation_details['errors'][0]['message'] ?? 'Something went wrong',
            ];
        }

        $response = $this->save_model_generations($generation_details['data'], $model_id, $params);

        return [
            'success' => true,
            'generations' => $response,
            'message' => sprintf('Successfully generated. Prompt: %s', $prompt)
        ];
    }

    public function generate_image($params = [])
    {
        $prompt = $params['prompt'] ?? '';

        $generation_details = $this->generate_image_init($params);

        if (empty($generation_details) || isset($generation_details['error']) || !$generation_details['success']) {
            return [
                'success' => false,
                'message' => $generation_details['message'] ?? 'Something went wrong',
            ];
        }

        $response = $this->save_image_generations($generation_details['data'], $params);

        return [
            'success' => true,
            'generations' => $response,
            'message' => sprintf('Successfully generated. Prompt: %s', $prompt)
        ];
    }

    public function generate_image_init($params)
    {
        // API endpoint
        $url = "https://api.together.xyz/v1/images/generations";

        // Your Together API key
        $api_key = "0f934af70db66b4d1ecb4f193f0cc2a5b7f94c3990f74cf0444a8ed19ebf35cd";

        // Default request body
        $default_payload = [
            "model" => "black-forest-labs/FLUX.1-schnell-Free",
            "prompt" => $params['prompt'] ?? "A futuristic robot in a cyberpunk city",
            "width" => 768,
            "height" => 1024,
            "steps" => 4,
            "n" => 1,
            "response_format" => "b64_json",
            "stop" => []
        ];

        // Merge user-passed overrides
        $payload = array_merge($default_payload, $params['data'] ?? []);

        // Convert payload to JSON
        $json_payload = json_encode($payload);

        // Set headers
        $headers = [
            "Authorization: Bearer {$api_key}",
            "Content-Type: application/json",
            "User-Agent: PHP-cURL"
        ];

        // Initialize cURL
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true); // POST request
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);

        // Execute request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        // Close connection
        curl_close($ch);

        // Handle cURL errors
        if ($error) {
            return [
                'success' => false,
                'errors' => [
                    [
                        'message' => "cURL Error: " . $error,
                        'http_code' => $http_code
                    ]
                ]
            ];
        }

        // Decode and return response
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $decoded['success'] = true;
            return $decoded;
        } else {
            return [
                'success' => false,
                'raw_response' => $response,
                'error' => 'Invalid JSON response from API'
            ];
        }
    }

    function save_model_generations($generations, $model_id, $params)
    {
        $model = growtype_art_get_model_details($model_id);

        $image_folder = $model['image_folder'];
        $image_location = growtype_art_get_images_saving_location();

        foreach ($generations as $generation) {
            $image['folder'] = $image_folder;
            $image['location'] = $image_location;
            $image['content'] = $generation['b64_json'];
            $image['meta_details'] = [
                [
                    'key' => 'generation_id',
                    'value' => $params['generation_id']
                ],
                [
                    'key' => 'provider',
                    'value' => Growtype_Art_Crud::TOGETHERAI_KEY
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
                return [];
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

    function save_image_generations($generations, $params)
    {
        $image_location = growtype_art_get_images_saving_location();

        foreach ($generations as $generation) {
            $image['folder'] = 'without_model';
            $image['location'] = $image_location;
            $image['content'] = $generation['b64_json'];

            $saved_image = Growtype_Art_Crud::save_image($image, false);
        }

        return $saved_image;
    }
}

