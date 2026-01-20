<?php

namespace partials;

require_once GROWTYPE_ART_PATH . '/includes/methods/crud/Growtype_Art_Generator_Base.php';

use Growtype_Art_Crud;
use Growtype_Art_Database;
use Growtype_Art_Database_Crud;
use Growtype_Art_Generator_Base;

class Freeflux_Base extends Growtype_Art_Generator_Base
{
    public function get_provider_key()
    {
        return Growtype_Art_Crud::FREEFLUX_KEY;
    }
    public function api_key()
    {
        return [
            'default' => [
                'api_key' => 'not-needed'
            ]
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

        // Return standardized format (content -> imageBase64)
        return [
            'imageBase64' => $response_data['result']
        ];
    }
}

