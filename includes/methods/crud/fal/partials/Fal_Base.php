<?php

namespace partials;

require_once GROWTYPE_ART_PATH . '/includes/methods/base/Growtype_Art_Generator_Base.php';

use Growtype_Art_Crud;
use Growtype_Art_Database;
use Growtype_Art_Database_Crud;
use Growtype_Art_Generator_Base;

class Fal_Base extends Growtype_Art_Generator_Base
{
    public function get_provider_key()
    {
        return Growtype_Art_Crud::FAL_KEY;
    }

    public function get_models()
    {
        return [
            'flux-2/edit' => [
                'is_nsfw'  => true,
                'cost_usd' => 0.005,
            ],
        ];
    }

    public function generate_image_init($params)
    {
        error_log('Fal_Base params: ' . print_r($params, true));
        $apiKey = $params['token'];
        $url = 'https://queue.fal.run/fal-ai/flux-2/edit';

        $postData = [
            'prompt' => $params['prompt'],
            'image_urls' => !empty($params['image_urls']) ? $params['image_urls'] : ($params['reference_image_urls'] ?? []),// Array of input image URLs
            'image_size' => $params['image_size'] ?? self::DEFAULT_IMAGE_DIMENSIONS,
            'num_images' => $params['num_images'] ?? 1,
            'enable_safety_checker' => $params['enable_safety_checker'] ?? false,
            'guidance_scale' => $params['guidance_scale'] ?? 7.5,
            'num_inference_steps' => $params['num_inference_steps'] ?? 32
        ];

        $wp_response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => "Key $apiKey",
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode($postData),
            'timeout' => 60,
        ]);

        if (is_wp_error($wp_response)) {
            return ['success' => false, 'message' => $wp_response->get_error_message()];
        }

        $httpCode = (int) wp_remote_retrieve_response_code($wp_response);
        $response = wp_remote_retrieve_body($wp_response);

        error_log('Growtype Art - Fal Base: Raw Response: ' . $response);

        if ($httpCode >= 400) {
            return ['success' => false, 'message' => "HTTP Error: $httpCode"];
        }

        $decoded = json_decode($response, true);
        if (!$decoded) {
            error_log('Growtype Art - Fal Base: Failed to decode API response: ' . $response);
            return ['success' => false, 'message' => 'Failed to decode API response'];
        }

        if (isset($decoded['detail'])) {
            return ['success' => false, 'message' => $decoded['detail']];
        }

        $requestId = $decoded['request_id'] ?? null;
        error_log('Growtype Art - Fal Base: Request ID: ' . $requestId);

        if (!$requestId) {
             return ['success' => false, 'message' => 'No request_id returned'];
        }

        // Poll for results
        $poll_result = $this->poll_fal_request($requestId);

        if (!$poll_result['success']) {
            return $poll_result;
        }

        // Standardize output
        $generations = [];
        if (isset($poll_result['images'])) {
            foreach ($poll_result['images'] as $img) {
                if (isset($img['url'])) {
                    $generations[] = ['url' => $img['url']];
                }
            }
        }

        if (empty($generations)) {
            return ['success' => false, 'message' => 'No images generated'];
        }

        return [
            'success'          => true,
            'generations'      => $generations,
            '_request_payload' => array_merge(['_url' => $url], $postData),
        ];
    }

    function poll_fal_request($request_id)
    {
        $url = 'https://queue.fal.run/fal-ai/flux-2/requests/' . $request_id;

        $max_attempts = 20;
        $interval = 7; // seconds

        for ($i = 1; $i <= $max_attempts; $i++) {

            $response = wp_remote_get($url, [
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'message' => 'fal_request failed',
                ];
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            error_log('Growtype Art - Fal Base: Polling attempt ' . $i . ' for ' . $request_id . '. Response: ' . $body);

            // Completed
            // Completed
            if (isset($data['detail'])) {
                $detail = is_array($data['detail']) ? json_encode($data['detail']) : $data['detail'];

                if (strpos($detail, 'still in progress') !== false) {
                    // fall through to sleep
                } else {
                    $message = $detail;
                    if (is_array($data['detail']) && isset($data['detail'][0]['msg'])) {
                        $message = $data['detail'][0]['msg'];
                    }
                    return [
                        'success' => false,
                        'message' => $message,
                    ];
                }
            }

            if (!empty($data['images'])) {
                return [
                    'success' => true,
                    'images' => $data['images']
                ];
            }

            // Still running → wait
            if ($i < $max_attempts) {
                sleep($interval);
            }
        }

        return [
            'success' => false,
            'message' => 'Request not completed after 10 attempts',
        ];
    }

    public function get_access_token($api_group_key)
    {
        return self::api_key()[$api_group_key]['api_key'] ?? '';
    }
}

