<?php

namespace partials;

require_once GROWTYPE_ART_PATH . '/includes/methods/crud/Growtype_Art_Generator_Base.php';

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
            
            // Standardize response for Base class
             if (isset($decoded['data'])) {
                $generations = [];
                foreach ($decoded['data'] as $item) {
                     if (isset($item['b64_json'])) {
                         $generations[] = ['imageBase64' => $item['b64_json']];
                     }
                }
                if (!empty($generations)) {
                    return ['success' => true, 'generations' => $generations];
                }
             }

            $decoded['success'] = true;
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

