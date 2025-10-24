<?php

namespace partials;

use Growtype_Art_Crud;
use Growtype_Art_Database;
use Growtype_Art_Database_Crud;

class Writecream_Base
{
    public function generate_model_image($model_id, $params = [])
    {
        $model = growtype_art_get_model_details($model_id);

        $prompt = isset($params['prompt']) && !empty($params['prompt']) ? $params['prompt'] : $model['prompt'];

        $formatted_prompt = growtype_art_model_format_prompt($prompt, $model_id);

        $params['prompt'] = $formatted_prompt;
        $params['generation_id'] = wp_generate_password(52, false);

        $generation_details = $this->generate_image_init($params);

        if (empty($generation_details) || isset($generation_details['status']) && $generation_details['status'] === 'error') {
            return [
                'success' => false,
                'message' => $generation_details['message'] ?? 'Something went wrong',
            ];
        }

        $response = $this->save_generations($generation_details, $model_id, $params);

        return [
            'success' => true,
            'generations' => $response,
            'message' => sprintf('Successfully generated. Prompt: %s', $prompt)
        ];
    }

    public function generate_image_init($params)
    {
        // Define base API URL
        $base_url = "https://1yjs1yldj7.execute-api.us-east-1.amazonaws.com/default/ai_image";

        // Check if the required 'prompt' parameter exists
        if (!isset($params['prompt']) || empty($params['prompt'])) {
            return [
                'errors' => [
                    [
                        'message' => "Missing or empty 'prompt' parameter.",
                        'http_code' => 400
                    ]
                ]
            ];
        }

        // Default query parameters
        $default_query_data = [
            'prompt' => $params['prompt'],
            'aspect_ratio' => '2:3'
        ];

        // Merge user-provided parameters with defaults
        $query_data = array_merge($default_query_data, $params['data'] ?? []);

        // Build the final query string
        $query_string = http_build_query($query_data);

        // Final API URL with dynamic parameters
        $api_url = $base_url . '?' . $query_string;

        // Set headers
        $headers = [
            "Content-Type: application/json",
            "User-Agent: PHP-cURL"
        ];

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);

        // Execute the request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        // Close cURL connection
        curl_close($ch);

        // Handle cURL errors
        if ($error) {
            return [
                'errors' => [
                    [
                        'message' => "cURL Error: " . $error,
                        'http_code' => $http_code
                    ]
                ]
            ];
        }

        // Ensure response is not empty
        if (empty($response)) {
            return [
                'errors' => [
                    [
                        'message' => "Empty response from server",
                        'http_code' => $http_code
                    ]
                ]
            ];
        }

        // Decode JSON response (if applicable)
        $decoded_response = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded_response;
        }

        return $response;
    }

    function save_generations($generation, $model_id, $params)
    {
        $model = growtype_art_get_model_details($model_id);

        $image_folder = $model['image_folder'];
        $image_location = growtype_art_get_images_saving_location();

        $image['folder'] = $image_folder;
        $image['location'] = $image_location;
        $image['url'] = $generation['image_link'];
        $image['meta_details'] = [
            [
                'key' => 'generation_id',
                'value' => $params['generation_id']
            ],
            [
                'key' => 'provider',
                'value' => Growtype_Art_Crud::WRITECREAM_KEY
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

        do_action('growtype_art_model_update', $model_id);

        return $saved_generations;
    }

    public function generate_image($params = [])
    {
        $prompt = $params['prompt'] ?? '';

        $generation_details = $this->generate_image_init($params);

        if (empty($generation_details) || isset($generation_details['error'])) {
            return [
                'success' => false,
                'message' => $generation_details['message'] ?? 'Something went wrong',
            ];
        }

        $response = $this->save_image_generations($generation_details, $params);

        return [
            'success' => true,
            'generations' => $response,
            'message' => sprintf('Successfully generated. Prompt: %s', $prompt)
        ];
    }

    function save_image_generations($generation, $params)
    {
        $image_location = growtype_art_get_images_saving_location();

        $image['folder'] = 'without_model';
        $image['location'] = $image_location;
        $image['url'] = $generation['image_link'];

        $saved_image = Growtype_Art_Crud::save_image($image, false);

        return $saved_image;
    }
}

