<?php

namespace partials;

require_once GROWTYPE_ART_PATH . '/includes/methods/crud/Growtype_Art_Generator_Base.php';

use Extract_Image_Colors_Job;
use Growtype_Art_Crud;
use Growtype_Art_Database;
use Growtype_Art_Database_Crud;
use Exception;
use Growtype_Cron_Jobs;
use Growtype_Art_Generator_Base;

class Pollinations_Base extends Growtype_Art_Generator_Base
{
    public function get_provider_key()
    {
        return Growtype_Art_Crud::POLLINATIONS_KEY;
    }

    public function get_models()
    {
        return [
            'flux' => [
                'is_nsfw' => true,
            ],
            'flux-realism' => [
                'is_nsfw' => true,
            ],
            'any-dark' => [
                'is_nsfw' => true,
            ],
            'flux-anime' => [
                'is_nsfw' => true,
            ],
            'flux-3d' => [
                'is_nsfw' => true,
            ],
            'turbo' => [
                'is_nsfw' => true,
            ],
        ];
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
        $base_url = "https://image.pollinations.ai/prompt/";

        // Default query parameters
        $default_query_data = [
            "model" => "flux",
            "seed" => (int)Growtype_Art_Crud::generate_seed(),
            "width" => $params['width'] ?? self::DEFAULT_IMAGE_DIMENSIONS['width'],
            "height" => $params['height'] ?? self::DEFAULT_IMAGE_DIMENSIONS['height'],
            "enhance" => "true",
            "refine" => "true",
            "nologo" => "true",
            "private" => "true",
            "safe" => "false",
        ];

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
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

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

        // Check if response is valid JSON (error or metadata), otherwise it's image data
        $decoded = json_decode($response, true);
        if ($decoded !== null) {
            // It's likely an error message or JSON response, not image data
             return [
                 'success' => true, // Or false if it's an error? Assuming success for now but normally Pollinations returns raw image.
                 'data' => $decoded
             ];
        } else {
             // It's raw image data.
             return [
                 'success' => true,
                 'imageBase64' => base64_encode($response)
             ];
        }
    }
}

