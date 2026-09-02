<?php

namespace partials;

require_once GROWTYPE_ART_PATH . '/includes/methods/base/Growtype_Art_Generator_Base.php';

use Growtype_Art_Crud;
use Growtype_Art_Generator_Base;

class Xai_Base extends Growtype_Art_Generator_Base
{
    public function get_provider_key()
    {
        return Growtype_Art_Crud::XAI_KEY;
    }

    public function get_provider_label(): string
    {
        return 'xAI (Grok)';
    }

    public function get_text_models(): array
    {
        return [
            'grok-3-mini' => 'Grok 3 Mini',
            'grok-3'      => 'Grok 3',
            'grok-2'      => 'Grok 2',
            'grok-beta'   => 'Grok Beta',
        ];
    }

    /**
     * Generate text content via the xAI Chat Completions API.
     * Reuses get_access_token() inherited from Growtype_Art_Generator_Base.
     */
    public function generate_text_content(string $prompt, string $model = 'grok-3-mini'): ?string
    {
        $credentials = $this->api_key();

        if (empty($credentials)) {
            error_log('Growtype Art xAI - No API credentials found.');
            return null;
        }

        $first_group = array_key_first($credentials);
        $api_key     = $this->get_access_token($first_group);

        if (empty($api_key)) {
            error_log('Growtype Art xAI - API key is empty.');
            return null;
        }

        $response = wp_remote_post('https://api.x.ai/v1/chat/completions', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body'    => wp_json_encode([
                'model'       => $model,
                'messages'    => [
                    ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens'  => 4000,
            ]),
            'timeout' => 90,
        ]);

        if (is_wp_error($response)) {
            error_log('Growtype Art xAI - Request error: ' . $response->get_error_message());
            return null;
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($decoded['error'])) {
            error_log('Growtype Art xAI - API error: ' . json_encode($decoded['error']));
            return null;
        }

        return $decoded['choices'][0]['message']['content'] ?? null;
    }

    public function get_models(): array
    {
        return [
            'grok-imagine-image' => [
                'label'                    => 'Grok Imagine Image',
                'supports_reference_image' => true,
                'cost_usd'                 => 0.02,
            ],
            'grok-imagine-image-quality' => [
                'label'                    => 'Grok Imagine Image Quality',
                'supports_reference_image' => true,
                'cost_usd'                 => null,
                'cost_label'               => '$0.05 (1K) / $0.07 (2K)',
            ],
            'grok-imagine-image-pro' => [
                'label'                    => 'Grok Imagine Image Pro',
                'supports_reference_image' => true,
                'cost_usd'                 => null,
                'cost_label'               => '$0.05 (1K) / $0.07 (2K)',
            ],
            'grok-imagine-image-edit' => [
                'label'                    => 'Grok Imagine Image Edit',
                'supports_reference_image' => true,
                'cost_usd'                 => null,
                'cost_label'               => 'from $0.04/output + $0.01/input image',
            ],
        ];
    }

    public function generate_image_init($params)
    {
        $selected_model = $params['model'] ?? 'grok-imagine-image';
        $is_edit_mode = $selected_model === 'grok-imagine-image-edit';
        $api_model = $is_edit_mode ? 'grok-imagine-image-2.0' : $selected_model;
        $url = 'https://api.x.ai/v1/images/generations';
        $api_key = $params['token'];

        if (!empty($params['token']) && $params['token'] !== 'not-needed') {
            $api_key = $params['token'];
        }

        $image_urls = [];
        if (!empty($params['image_urls']) && is_array($params['image_urls'])) {
            $image_urls = $params['image_urls'];
        } elseif (!empty($params['reference_image_urls']) && is_array($params['reference_image_urls'])) {
            $image_urls = $params['reference_image_urls'];
        } elseif (!empty($params['reference_image_url']) && is_string($params['reference_image_url'])) {
            $image_urls = [$params['reference_image_url']];
        } elseif (!empty($params['init_image']) && is_string($params['init_image'])) {
            $image_urls = [$params['init_image']];
        } elseif (!empty($params['image_url']) && is_string($params['image_url'])) {
            $image_urls = [$params['image_url']];
        }

        $image_urls = array_values(array_filter($image_urls, function ($url) {
            return is_string($url) && filter_var($url, FILTER_VALIDATE_URL);
        }));

        if ($is_edit_mode && empty($image_urls)) {
            return [
                'success' => false,
                'message' => 'Grok Imagine Image Edit requires a reference image.',
            ];
        }

        if (!empty($image_urls)) {
            $url = 'https://api.x.ai/v1/images/edits';
        } else {
            $url = 'https://api.x.ai/v1/images/generations';
        }

        $payload = [
            'model'        => $api_model,
            'prompt'       => $params['prompt'] ?? '',
            'aspect_ratio' => $params['aspect_ratio'] ?? (!empty($image_urls) ? 'auto' : '2:3'),
        ];

        if (count($image_urls) === 1) {
            $payload['image'] = ['url' => $image_urls[0]];
        } elseif (count($image_urls) > 1) {
            $payload['images'] = array_map(function ($image_url) {
                return [
                    'type' => 'image_url',
                    'url' => $image_url,
                ];
            }, array_slice($image_urls, 0, 5));
        }

        $headers = [
            'Authorization' => "Bearer {$api_key}",
            'Content-Type'  => 'application/json',
        ];

        $max_retries = 0;
        $retry_count = 0;

        do {
            $wp_response = wp_remote_post($url, [
                'headers' => $headers,
                'body'    => json_encode($payload),
                'timeout' => 120,
            ]);

            if (is_wp_error($wp_response)) {
                $error_msg = $wp_response->get_error_message();
                error_log('XAI - Request Error: ' . $error_msg);
                return [
                    'success' => false,
                    'message' => $error_msg,
                ];
            }

            $http_code = (int) wp_remote_retrieve_response_code($wp_response);
            $response  = wp_remote_retrieve_body($wp_response);
            $decoded   = json_decode($response, true);

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
                        return [
                            'success'          => true,
                            'generations'      => $generations,
                            '_request_payload' => array_merge(['_url' => $url], $payload),
                        ];
                    }
                }

                $decoded['success']          = true;
                $decoded['_request_payload'] = array_merge(['_url' => $url], $payload);
                return $decoded;
            }

            error_log('XAI - JSON Decode Error: ' . json_last_error_msg() . ' - Response: ' . $response);

            return [
                'success'      => false,
                'raw_response' => $response,
                'message'      => 'Invalid JSON response from API'
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
