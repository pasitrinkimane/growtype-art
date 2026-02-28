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
        $base_url = "https://image.pollinations.ai/prompt/";

        // Pollinations recently started returning Cloudflare 530 errors for any query parameters.
        // We still build the full URL first, but fallback to a minimal URL without query params
        // if the response is not an image.
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

        $query_data = array_merge($default_query_data, $params['data'] ?? []);

        $urls_to_try = [];
        $urls_to_try[] = $base_url . urlencode($params['prompt']) . '?' . http_build_query($query_data);
        // Fallback without any query parameters – current public endpoint still serves an image this way.
        $urls_to_try[] = $base_url . urlencode($params['prompt']);

        $headers = [
            "Content-Type: application/json",
            "User-Agent: PHP-cURL"
        ];

        foreach ($urls_to_try as $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $error = curl_error($ch);

            curl_close($ch);

            if ($error) {
                // Try next URL on network errors
                continue;
            }

            // If we didn't get a 200, try next URL before failing
            if ($http_code !== 200) {
                continue;
            }

            // If response is JSON, treat it as an error payload
            $decoded = json_decode($response, true);
            if ($decoded !== null) {
                return [
                    'success' => false,
                    'errors' => [
                        [
                            'message' => $decoded['error'] ?? $decoded['message'] ?? 'Pollinations returned JSON instead of an image',
                            'http_code' => $http_code,
                        ]
                    ]
                ];
            }

            // Accept only image content types
            if ($content_type && strpos($content_type, 'image/') === 0) {
                return [
                    'success' => true,
                    'imageBase64' => base64_encode($response)
                ];
            }
        }

        // If all attempts failed, return a clear error instead of invalid base64
        return [
            'success' => false,
            'errors' => [
                [
                    'message' => 'Pollinations did not return an image (HTTP ' . ($http_code ?? 'n/a') . ').',
                    'http_code' => $http_code ?? 0
                ]
            ]
        ];
    }
}
