<?php

namespace partials;

use Growtype_Art_Crud;
use Growtype_Art_Database;
use Growtype_Art_Database_Crud;

class Freeflux_Base
{

    public function generate_model_image($model_id, $params = [])
    {
        $model = growtype_art_get_model_details($model_id);

        $prompt = isset($params['prompt']) && !empty($params['prompt']) ? $params['prompt'] : $model['prompt'];

        $formatted_prompt = growtype_art_model_format_prompt($prompt, $model_id);

        $params['prompt'] = $formatted_prompt;
        $params['generation_id'] = wp_generate_password(52, false);

        $generation_details = $this->generate_image_init($params);

        if (empty($generation_details) || isset($generation_details['errors'])) {
            return [
                'success' => false,
                'message' => $generation_details['errors'][0]['message'] ?? 'Something went wrong',
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
        $base_url = "https://api.freeflux.ai/v1/images/generate";

        // Default query parameters
        $default_query_data = [
            'prompt' => $params['prompt'],
            'model' => 'flux_1_schnell',
            'size' => '2_3',
            'lora' => null,
            'style' => 'no_style',
            'color' => 'no_color',
            'lighting' => 'no_lighting',
            'composition' => null
        ];

        // Merge user-provided parameters with defaults
        $query_data = array_merge($default_query_data, $params['data'] ?? []);

        // Convert query parameters to JSON
        $post_data = json_encode($query_data);

        // Set headers
        $headers = [
            "Content-Type: application/json",
            "User-Agent: PHP-cURL"
        ];

        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $base_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);

        // Execute the request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        // Close cURL connection
        curl_close($ch);

        // Handle cURL errors
        if ($error || $http_code !== 200) {
            return [
                'errors' => [
                    [
                        'message' => "API Error: " . ($error ?: "HTTP Code $http_code"),
                        'http_code' => $http_code
                    ]
                ]
            ];
        }

        // Decode response
        $response_data = json_decode($response, true);

        if (empty($response_data['result'])) {
            return [
                'errors' => [
                    [
                        'message' => "Invalid API response",
                        'http_code' => $http_code
                    ]
                ]
            ];
        }

        return [
            'content' => $response_data['result']
        ];
    }

    function save_generations($generation, $model_id, $params)
    {
        $model = growtype_art_get_model_details($model_id);

        $image_folder = $model['image_folder'];
        $image_location = growtype_art_get_images_saving_location();

        $image['folder'] = $image_folder;
        $image['location'] = $image_location;
        $image['content'] = $generation['content'];
        $image['meta_details'] = [
            [
                'key' => 'generation_id',
                'value' => $params['generation_id']
            ],
            [
                'key' => 'provider',
                'value' => Growtype_Art_Crud::FREEFLUX_KEY
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

        if (isset($params['user_id'])) {
            $image['meta_details'][] = [
                'key' => 'user_id',
                'value' => $params['user_id']
            ];
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

        growtype_art_compress_existing_image($saved_image['id']);

        do_action('growtype_art_model_update', $model_id);

        return $saved_generations;
    }
}

