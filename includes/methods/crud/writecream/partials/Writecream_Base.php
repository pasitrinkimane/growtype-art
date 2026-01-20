<?php

namespace partials;

require_once GROWTYPE_ART_PATH . '/includes/methods/crud/Growtype_Art_Generator_Base.php';

use Growtype_Art_Crud;
use Growtype_Art_Database;
use Growtype_Art_Database_Crud;
use Growtype_Art_Generator_Base;

class Writecream_Base extends Growtype_Art_Generator_Base
{
    public function get_provider_key()
    {
        return Growtype_Art_Crud::WRITECREAM_KEY;
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

        error_log(sprintf('Growtype Art - Writecream Response: Code: %s, Error: %s, Body: %s', $http_code, $error, $response));

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
        
        // Standardize response for Base class
        if (json_last_error() === JSON_ERROR_NONE) {
            if (isset($decoded_response['image_link'])) {
                return [
                    'success' => true,
                    'generations' => [
                        ['imageURL' => $decoded_response['image_link']]
                    ]
                ];
            }
             if (isset($decoded_response['status']) && $decoded_response['status'] === 'error') {
                   // Fall through to allow error checking in Base class if needed or just return it
             }
             return $decoded_response;
        }

        // If not JSON, it might be raw or error string? Unlikely given provided code.
        return $response;
    }
}

