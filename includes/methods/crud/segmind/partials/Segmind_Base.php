<?php

namespace partials;

require_once GROWTYPE_ART_PATH . '/includes/methods/crud/Growtype_Art_Generator_Base.php';

use Growtype_Art_Crud;
use Growtype_Art_Database;
use Growtype_Art_Database_Crud;
use Growtype_Art_Generator_Base;

class Segmind_Base extends Growtype_Art_Generator_Base
{
    public function get_provider_key()
    {
        return Growtype_Art_Crud::SEGMIND_KEY;
    }

    public function get_access_token($api_group_key)
    {
        $api_keys = $this->api_key();
        return $api_keys[$api_group_key]['x_api_key'] ?? $api_keys[$api_group_key]['api_key'] ?? '';
    }

    public function get_models()
    {
        return [
            'seedream-4' => [
                'url' => 'https://api.segmind.com/v1/seedream-4',
                'is_nsfw' => false,
            ],
            'fast-flux-schnell' => [
                'url' => 'https://api.segmind.com/v1/fast-flux-schnell',
                'is_nsfw' => false,
            ],
            'p-image' => [
                'url' => 'https://api.segmind.com/v1/p-image',
                'is_nsfw' => false,
            ],
            'qwen-image-fast' => [
                'url' => 'https://api.segmind.com/v1/qwen-image-fast',
                'is_nsfw' => false,
            ],
            'ssd-1b' => [
                'url' => 'https://api.segmind.com/v1/ssd-1b',
                'is_nsfw' => false,
            ],
            'z-image-turbo' => [
                'url' => 'https://api.segmind.com/v1/z-image-turbo',
                'test_url' => 'https://www.segmind.com/models/z-image-turbo',
                'is_nsfw' => true,
            ],
            'sdxl1.0-txt2img' => [
                'url' => 'https://api.segmind.com/v1/sdxl1.0-txt2img',
                'is_nsfw' => false,
            ],
        ];
    }

    public function get_model_url($model_slug)
    {
        $models = $this->get_models();
        return $models[$model_slug]['url'] ?? "https://api.segmind.com/v1/$model_slug";
    }

    public function is_nsfw_model($model_slug)
    {
        $models = $this->get_models();
        return $models[$model_slug]['is_nsfw'] ?? false;
    }

    public function generate_image_init($params)
    {
        $model_id = $params['model_id'] ?? null;
        $model_details = (!empty($model_id)) ? growtype_art_get_model_details($model_id) : [];
        $model_images = (!empty($model_id)) ? (growtype_art_get_model_images_grouped($model_id, 500)['original'] ?? []) : [];
        
        $formatted_prompt = isset($params['prompt']) && !empty($params['prompt']) ? $params['prompt'] : '';
        if (empty($formatted_prompt) && !empty($model_details)) {
            $formatted_prompt = growtype_art_model_format_prompt($model_details['prompt'], $model_id);
        }

        $image_input = [];
        if (isset($params['reference_image_urls']) && !empty($params['reference_image_urls'])) {
            $image_input = $params['reference_image_urls'];
        }

        if (empty($image_input) && !empty($model_images)) {
            foreach ($model_images as $model_image) {
                if (isset($model_image['settings']['is_cover']) && $model_image['settings']['is_cover']) {
                    $image_url = growtype_art_get_image_url($model_image['id']);
                    $image_url = growtype_art_image_get_alternative_format($image_url, 'jpg', true);
                    array_push($image_input, $image_url);
                }
            }

            if (empty($image_input)) {
                $model_image = $model_images[0];
                $image_url = growtype_art_get_image_url($model_image['id']);
                $image_url = growtype_art_image_get_alternative_format($image_url, 'jpg', true);
                array_push($image_input, $image_url);
            }
        }

        $model_slug = $params['model'] ?? $model_details['settings']['core_model'] ?? 'seedream-4';
        $url = $this->get_model_url($model_slug);
        $is_nsfw = $this->is_nsfw_model($model_slug);

        $generating_settings = [
            "prompt" => $formatted_prompt,
            "width" => $params['width'] ?? self::DEFAULT_IMAGE_DIMENSIONS['width'],
            "height" => $params['height'] ?? self::DEFAULT_IMAGE_DIMENSIONS['height'],
            "seed" => $params['seed'] ?? -1,
            "max_images" => 1,
            "aspect_ratio" => $params['aspect_ratio'] ?? "3:4",
            "disable_safety_checker" => $params['disable_safety_checker'] ?? $is_nsfw,
        ];

        // Model specific settings
        if ($model_slug === 'seedream-4') {
            $generating_settings['size'] = $params['size'] ?? '1K';
            $generating_settings['sequential_image_generation'] = 'disabled';
            if (!empty($image_input)) {
                $generating_settings['image_input'] = $image_input;
            }
        } elseif ($model_slug === 'qwen-image-fast') {
            $generating_settings['steps'] = $params['steps'] ?? 8;
            $generating_settings['guidance'] = $params['guidance'] ?? 1;
            $generating_settings['image_format'] = $params['image_format'] ?? 'png';
            $generating_settings['quality'] = $params['quality'] ?? 90;
            $generating_settings['base64'] = $params['base64'] ?? false;
            unset($generating_settings['max_images']);
            unset($generating_settings['disable_safety_checker']);
        }

        $token = $params['token'];

        $headers = [
            "Content-Type: application/json",
            "x-api-key: $token",
        ];

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($generating_settings));
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        error_log(sprintf('Growtype Art - Segmind (%s): Raw Response: %s', $model_slug, $response));

        if (!empty($response) && is_string($response)) {
            $response_decoded = json_decode($response, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                 if (isset($response_decoded['error']) || isset($response_decoded['message'])) {
                      return [
                        'success' => false,
                        'message' => $response_decoded['error'] ?? $response_decoded['message']
                      ];
                 }
                 return ['data' => $response_decoded]; 
            }
        }
        
        return [
            'success' => true,
            'imageBase64' => base64_encode($response)
        ];
    }
}

