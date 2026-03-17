<?php

namespace partials;

require_once GROWTYPE_ART_PATH . '/includes/methods/crud/Growtype_Art_Generator_Base.php';

use Growtype_Art_Crud;
use Growtype_Art_Generator_Base;

class Xai_Base extends Growtype_Art_Generator_Base
{
    public function get_provider_key()
    {
        return Growtype_Art_Crud::XAI_KEY;
    }

    public function generate_image_init($params)
    {
        $url = 'https://api.x.ai/v1/images/edits';
        $api_key = $params['token'];

        if (!empty($params['token']) && $params['token'] !== 'not-needed') {
            $api_key = $params['token'];
        }

        $image_url = '';
        if (!empty($params['image_urls']) && is_array($params['image_urls'])) {
            $image_url = $params['image_urls'][0];
        } elseif (!empty($params['reference_image_urls']) && is_array($params['reference_image_urls'])) {
            $image_url = $params['reference_image_urls'][0];
        } elseif (!empty($params['init_image']) && is_string($params['init_image'])) {
            $image_url = $params['init_image'];
        } elseif (!empty($params['image_url']) && is_string($params['image_url'])) {
            $image_url = $params['image_url'];
        }

        $payload = [
            'model' => $params['model'] ?? 'grok-imagine-image',
            'prompt' => $params['prompt'] ?? '',
            'aspect_ratio' => "2:3"
        ];

        if (!empty($image_url)) {
            $payload['image'] = [
                'url' => $image_url,
                'type' => 'image_url'
            ];
        }

        $headers = [
            "Authorization: Bearer {$api_key}",
            "Content-Type: application/json"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'message' => "cURL Error: " . $error
            ];
        }

        $decoded = json_decode($response, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            if ($http_code >= 400) {
                return [
                    'success' => false,
                    'message' => $decoded['error']['message'] ?? $decoded['error'] ?? "HTTP Error: $http_code"
                ];
            }

            if (isset($decoded['data']) && is_array($decoded['data'])) {
                $generations = [];
                foreach ($decoded['data'] as $item) {
                    if (isset($item['url'])) {
                        $generations[] = ['url' => $item['url']];
                    } elseif (isset($item['b64_json'])) {
                        $generations[] = ['imageBase64' => $item['b64_json']];
                    }
                }
                if (!empty($generations)) {
                    return ['success' => true, 'generations' => $generations];
                }
            }

            $decoded['success'] = true;
            return $decoded;
        }

        return [
            'success' => false,
            'raw_response' => $response,
            'message' => 'Invalid JSON response from API'
        ];
    }
}
