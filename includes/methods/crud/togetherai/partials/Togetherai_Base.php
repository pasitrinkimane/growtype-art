<?php

namespace partials;

require_once GROWTYPE_ART_PATH . '/includes/methods/base/Growtype_Art_Generator_Base.php';

use Growtype_Art_Crud;
use Growtype_Art_Database;
use Growtype_Art_Database_Crud;
use Growtype_Art_Generator_Base;

class Togetherai_Base extends Growtype_Art_Generator_Base
{
    public function get_provider_key()
    {
        return Growtype_Art_Crud::TOGETHERAI_KEY;
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
            'Authorization' => "Bearer {$api_key}",
            'Content-Type'  => 'application/json',
        ];

        $wp_response = wp_remote_post($url, [
            'headers' => $headers,
            'body'    => $json_payload,
            'timeout' => 60,
        ]);

        // Handle WP errors
        if (is_wp_error($wp_response)) {
            return [
                'success' => false,
                'errors'  => [
                    [
                        'message'   => $wp_response->get_error_message(),
                        'http_code' => 0,
                    ]
                ]
            ];
        }

        $http_code = (int) wp_remote_retrieve_response_code($wp_response);
        $response  = wp_remote_retrieve_body($wp_response);

        // Decode and return response
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            
            // Standardize response for Base class
             if (isset($decoded['data'])) {
                $generations = [];
                foreach ($decoded['data'] as $item) {
                     if (isset($item['b64_json'])) {
                         $generations[] = ['imageBase64' => $item['b64_json']];
                     }
                }
                if (!empty($generations)) {
                    return [
                        'success'          => true,
                        'generations'      => $generations,
                        '_request_payload' => array_merge(['_url' => $url], $payload),
                    ];
                }
             }

            $decoded['success']          = true;
            $decoded['_request_payload'] = array_merge(['_url' => $url], $payload);
            return $decoded; // Fallback or errors
        } else {
            return [
                'success' => false,
                'raw_response' => $response,
                'error' => 'Invalid JSON response from API'
            ];
        }
    }
}

