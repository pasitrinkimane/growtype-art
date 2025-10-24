<?php

namespace partials;

use Extract_Image_Colors_Job;
use Growtype_Art_Crud;
use Growtype_Art_Database;
use Growtype_Art_Database_Crud;
use Exception;
use Growtype_Cron_Jobs;

class Pollinations_Base
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

        $response = $this->save_model_generations($generation_details, $model_id, $params);

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

        $response = $this->save_image_generations($generation_details, $params);

        return [
            'success' => true,
            'generations' => $response,
            'message' => sprintf('Successfully generated. Prompt: %s', $prompt)
        ];
    }

    public function generate_image_init($params)
    {
        // Define base API URL
        $base_url = "https://image.pollinations.ai/prompt/";

        // Default query parameters
        $default_query_data = [
            "model" => "flux",
            "seed" => (int)Growtype_Art_Crud::generate_seed(),
            "width" => 768,
            "height" => 1024,
            "enhance" => "true",
            "refine" => "true",
            "nologo" => "true",
            "private" => "true",
            "safe" => "false",
        ];

//        d($default_query_data);

        // Merge user-provided parameters with defaults
        $query_data = array_merge($default_query_data, $params['data'] ?? []);

        // Construct final URL
        $url = $base_url . urlencode($params['prompt']) . "?" . http_build_query($query_data);

        // Set headers
        $headers = [
            "Content-Type: application/json",
            "User-Agent: PHP-cURL"
        ];

        // Initialize cURL
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPGET, true); // Explicitly set GET request
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        // Execute the request
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        // Close cURL connection
        curl_close($ch);

        // Handle timeout or other cURL errors
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

        // Check if response is valid JSON
        if (json_decode($response, true) === null) {
            $data = [
                'success' => true,
                'img_exif' => $response
            ];
        } else {
            $data = json_decode($response, true);
            $data['success'] = true; // Add success flag to response
        }

        return $data;
    }

    function save_model_generations($generation, $model_id, $params)
    {
        $model = growtype_art_get_model_details($model_id);

        $image_folder = $model['image_folder'];
        $image_location = growtype_art_get_images_saving_location();

        $image['folder'] = $image_folder;
        $image['location'] = $image_location;
        $image['content'] = $generation['img_exif'];
        $image['meta_details'] = [
            [
                'key' => 'generation_id',
                'value' => $params['generation_id']
            ],
            [
                'key' => 'provider',
                'value' => Growtype_Art_Crud::POLLINATIONS_KEY
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

    function save_image_generations($generation, $params)
    {
        $image_location = growtype_art_get_images_saving_location();

        $image['folder'] = 'without_model';
        $image['location'] = $image_location;
        $image['content'] = $generation['img_exif'];

        $saved_image = Growtype_Art_Crud::save_image($image, false);

        return $saved_image;
    }
}

