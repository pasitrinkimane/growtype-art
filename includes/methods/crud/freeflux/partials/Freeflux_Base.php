<?php

namespace partials;

require_once GROWTYPE_ART_PATH . '/includes/methods/base/Growtype_Art_Generator_Base.php';

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

        $wp_response = wp_remote_post($base_url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => $post_data,
            'timeout' => 60,
        ]);

        $http_code = is_wp_error($wp_response) ? 0 : (int) wp_remote_retrieve_response_code($wp_response);
        $response  = is_wp_error($wp_response) ? '' : wp_remote_retrieve_body($wp_response);
        $error     = is_wp_error($wp_response) ? $wp_response->get_error_message() : '';

        // Handle errors
        if ($error || $http_code !== 200) {
            return [
                'errors' => [
                    [
                        'message'   => $error ?: "HTTP Code $http_code",
                        'http_code' => $http_code,
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
            'imageBase64'      => $response_data['result'],
            '_request_payload' => array_merge(['_url' => $base_url], $query_data),
        ];
    }
}

