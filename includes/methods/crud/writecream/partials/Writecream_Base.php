<?php

namespace partials;

require_once GROWTYPE_ART_PATH . '/includes/methods/base/Growtype_Art_Generator_Base.php';

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
            'Content-Type' => 'application/json',
        ];

        $wp_response = wp_remote_get($api_url, [
            'headers' => $headers,
            'timeout' => 30,
        ]);

        $http_code = is_wp_error($wp_response) ? 0 : (int) wp_remote_retrieve_response_code($wp_response);
        $response  = is_wp_error($wp_response) ? '' : wp_remote_retrieve_body($wp_response);
        $error     = is_wp_error($wp_response) ? $wp_response->get_error_message() : '';

        error_log(sprintf('Growtype Art - Writecream Response: Code: %s, Error: %s, Body: %s', $http_code, $error, $response));

        // Handle errors
        if ($error) {
            return [
                'errors' => [
                    [
                        'message'   => $error,
                        'http_code' => $http_code,
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
                    'success'          => true,
                    'generations'      => [
                        ['imageURL' => $decoded_response['image_link']]
                    ],
                    '_request_payload' => array_merge(['_url' => $api_url], $query_data),
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

