<?php

namespace partials;

use Growtype_Art_Crud;
use Growtype_Art_Database;
use Growtype_Art_Database_Crud;

class Segmind_Base
{

    public static function api_key()
    {
        return \Growtype_Auth::credentials('segmind');
    }

    public function generate_model_image($model_id, $params = [])
    {
        $model = growtype_art_get_model_details($model_id);

        $prompt = isset($params['prompt']) && !empty($params['prompt']) ? $params['prompt'] : $model['prompt'];

        $formatted_prompt = growtype_art_model_format_prompt($prompt, $model_id);

        $api_keys = self::api_key();

        if (empty($api_keys)) {
            return [
                'success' => false,
                'message' => sprintf('Empty API keys. Model %s.', $model_id),
            ];
        }

        $api_group_key = array_keys($api_keys)[array_rand(array_keys(self::api_key()))];

        $params['token'] = $this->get_access_token($api_group_key);
        $params['prompt'] = $formatted_prompt;
        $params['generation_id'] = wp_generate_password(52, false);
        $params['model_id'] = $model_id;

        $generation_details = $this->generate_image_init($params);

        if (empty($generation_details) || isset($generation_details['error'])) {
            return [
                'success' => false,
                'message' => $generation_details['error'] ?? 'Something went wrong',
            ];
        }

        $response = $this->save_generations([$generation_details], $model_id, $params);

        return [
            'success' => true,
            'generations' => $response,
            'message' => sprintf('Successfully generated. Prompt: %s', $prompt)
        ];
    }

    public function get_access_token($api_group_key)
    {
        return self::api_key()[$api_group_key]['api_key'] ?? '';
    }

    public function generate_image_init($params)
    {
        $model_id = $params['model_id'];
        $model = growtype_art_get_model_details($model_id);
        $model_images = growtype_art_get_model_images_grouped($model_id, 500)['original'] ?? [];
        $formatted_prompt = isset($params['prompt']) && !empty($params['prompt']) ? $params['prompt'] : growtype_art_model_format_prompt($model['prompt'], $model_id);

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
//                    break;
                }
            }

            if (empty($image_input)) {
                $model_image = $model_images[0];
                $image_url = growtype_art_get_image_url($model_image['id']);
                $image_url = growtype_art_image_get_alternative_format($image_url, 'jpg', true);
                array_push($image_input, $image_url);
            }
        }

        $generating_settings = [
            "size" => "2K",
            "width" => 1024,
            "height" => 1024,
            "max_images" => 1,
//            "aspect_ratio" => "match_input_image",
            "sequential_image_generation" => "disabled",
            "image_input" => $image_input,
            "prompt" => $formatted_prompt,
        ];

        $url = "https://api.segmind.com/v1/seedream-4";

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

        if (!empty($response) && is_string($response)) {
            $response_decoded = json_decode($response, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $response_decoded;
            }
        }

        return $response;
    }

    function save_generations($generations, $model_id, $params)
    {
        $saved_generations = [];
        foreach ($generations as $generation) {
            $model = growtype_art_get_model_details($model_id);

            $image_folder = $model['image_folder'];
            $image_location = growtype_art_get_images_saving_location();

            $image['folder'] = $image_folder;
            $image['location'] = $image_location;
            $image['content'] = $generation;
            $image['meta_details'] = [
                [
                    'key' => 'generation_id',
                    'value' => $params['generation_id']
                ],
                [
                    'key' => 'provider',
                    'value' => Growtype_Art_Crud::SEGMIND_KEY
                ],
                [
                    'key' => 'prompt',
                    'value' => $params['prompt']
                ]
            ];

            if (isset($params['types'])) {
                foreach ($params['types'] as $type) {
                    $image['meta_details'][] = [
                        'key' => $type,
                        'value' => 1
                    ];
                }
            }

            $saved_image = Growtype_Art_Crud::save_image($image);

            if (empty($saved_image) || isset($saved_image['error']) || !isset($saved_image['id'])) {
                error_log('save_generations: ' . json_encode($saved_image));
                continue;
            }

            /**
             * Assign image to model
             */
            Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::MODEL_IMAGE_TABLE, [
                'model_id' => $model_id,
                'image_id' => $saved_image['id']
            ]);

            $saved_generations[] = [
                'url' => $saved_image['details']['url'],
                'image_id' => $saved_image['id'],
                'generation_id' => $params['generation_id'],
                'image_prompt' => $params['prompt'],
            ];
        }

        do_action('growtype_art_model_update', $model_id);

        return $saved_generations;
    }
}

