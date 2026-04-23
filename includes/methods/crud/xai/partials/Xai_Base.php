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

        if (!empty($image_url)) {
            $url = 'https://api.x.ai/v1/images/edits';
        } else {
            $url = 'https://api.x.ai/v1/images/generations';
        }

        $payload = [
            'model' => $params['model'] ?? 'grok-imagine-image',
            'prompt' => $params['prompt'] ?? '',
            'aspect_ratio' => $params['aspect_ratio'] ?? "2:3"
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

        $max_retries = 0;
        $retry_count = 0;

//        var_dump($payload);
//        die();

        do {
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
                error_log("XAI - cURL Error: " . $error);
                return [
                    'success' => false,
                    'message' => "cURL Error: " . $error
                ];
            }

//                    var_dump($response);
//        die();

            $decoded = json_decode($response, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                if ($http_code >= 400) {
                    $error_message = $decoded['error']['message'] ?? $decoded['error'] ?? 'Unknown error';
                    error_log("XAI - API Error: HTTP $http_code - " . $error_message);
                    error_log("XAI - Request URL: " . $url);
                    error_log("XAI - Request Payload: " . json_encode($payload));

                    // If moderation error and we have retries left, try again
                    if ($http_code == 400 && strpos($error_message, 'content moderation') !== false && $retry_count < $max_retries) {
                        $retry_count++;
                        error_log("XAI - Content moderation error. Cleaning prompt and retrying ($retry_count/$max_retries)...");
                        
                        // Clean the prompt for the second try
                        $payload['prompt'] = $this->clean_prompt($payload['prompt']);
                        continue;
                    }

                    return [
                        'success' => false,
                        'message' => $error_message
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

            error_log("XAI - JSON Decode Error: " . json_last_error_msg() . " - Response: " . $response);

            return [
                'success' => false,
                'raw_response' => $response,
                'message' => 'Invalid JSON response from API'
            ];
        } while ($retry_count <= $max_retries);
    }

    /**
     * Clean prompt by removing potentially problematic words that trigger moderation.
     */
    private function clean_prompt($prompt)
    {
        if (empty($prompt)) {
            return $prompt;
        }

        // Common words that might trigger moderation in strict environments
        $risky_words = [
            'naked', 'nude', 'nsfw', 'porn', 'sex', 'erotic', 'sexy', 'hot',
            'lingerie', 'underwear', 'bra', 'panties', 'bikini', 'thong',
            'blood', 'gore', 'violent', 'death', 'kill',
            'breast', 'breasts', 'butt', 'buttocks', 'vagina', 'penis', 'cock', 'pussy',
            'curves', 'curvy', 'teasing', 'sensual', 'suggestive', 'arching', 'bedroom',
            'cleavage', 'ass', 'thigh', 'pose', 'lingerie', 'thong'
        ];

        foreach ($risky_words as $word) {
            // Case-insensitive removal of the word (including common suffixes)
            $prompt = preg_replace('/\b' . preg_quote($word, '/') . '(s|ing|y|ie|ies)?\b/i', '', $prompt);
        }

        // Clean up double spaces resulting from removals
        $prompt = preg_replace('/\s+/', ' ', $prompt);
        $prompt = trim($prompt);

        return $prompt;
    }
}
