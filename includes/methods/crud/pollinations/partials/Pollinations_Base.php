<?php

namespace partials;

require_once GROWTYPE_ART_PATH . '/includes/methods/base/Growtype_Art_Generator_Base.php';

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
                'is_nsfw'  => true,
                'cost_usd' => 0.000, // free tier
            ],
            'flux-realism' => [
                'is_nsfw'  => true,
                'cost_usd' => 0.000,
            ],
            'any-dark' => [
                'is_nsfw'  => true,
                'cost_usd' => 0.000,
            ],
            'flux-anime' => [
                'is_nsfw'  => true,
                'cost_usd' => 0.000,
            ],
            'flux-3d' => [
                'is_nsfw'  => true,
                'cost_usd' => 0.000,
            ],
            'turbo' => [
                'is_nsfw'  => true,
                'cost_usd' => 0.000,
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


        foreach ($urls_to_try as $url) {
            $wp_response = wp_remote_get($url, [
                'headers' => ['Accept' => '*/*'],
                'timeout' => 30,
            ]);

            if (is_wp_error($wp_response)) {
                // Try next URL on network errors
                continue;
            }

            $http_code    = (int) wp_remote_retrieve_response_code($wp_response);
            $response     = wp_remote_retrieve_body($wp_response);
            $content_type = wp_remote_retrieve_header($wp_response, 'content-type');

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
                    'success'          => true,
                    'imageBase64'      => base64_encode($response),
                    '_request_payload' => array_merge(['_url' => $url], $query_data),
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
