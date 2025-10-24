<?php

namespace partials;

use Extract_Image_Colors_Job;
use Growtype_Art_Crud;
use Growtype_Art_Database;
use Growtype_Art_Database_Crud;
use Exception;
use Growtype_Cron_Jobs;

class Replicate_Base
{
    public function __construct()
    {
    }

    public static function api_key()
    {
        return \Growtype_Auth::credentials('replicate');
    }

    public static function get_random_access_token()
    {
        $api_keys = self::api_key();

        if (empty($api_keys)) {
            return null;
        }

        $api_group_key = array_keys($api_keys)[array_rand(array_keys(self::api_key()))];

        return self::get_access_token($api_group_key);
    }

    public function generate_model_video($model_id, $params = [])
    {
        error_log('Generating video from image started!');

        $model = growtype_art_get_model_details($model_id);

        $prompt = isset($params['prompt']) && !empty($params['prompt']) ? $params['prompt'] : $model['prompt'];

        $formatted_prompt = growtype_art_model_format_prompt($prompt, $model_id);

        $access_token = self::get_random_access_token();

        if (empty($access_token)) {
            return [
                'success' => false,
                'message' => sprintf('Empty API keys. Model %s.', $model_id),
            ];
        }

        $params['token'] = $access_token;
        $params['prompt'] = $formatted_prompt;
        $params['generation_id'] = wp_generate_password(52, false);
        $params['model_id'] = $model_id;

        $generation_details = $this->img_to_video($params);

        if (empty($generation_details) || isset($generation_details['errors'])) {
            return [
                'success' => false,
                'message' => $generation_details['errors'][0]['message'] ?? 'Something went wrong',
            ];
        }

        $response = $this->save_generations([$generation_details], $model_id, $params);

        return [
            'success' => true,
            'generations' => $response,
            'message' => sprintf('Successfully generated. Prompt: %s', $prompt)
        ];
    }

    public static function get_access_token($api_group_key)
    {
        return self::api_key()[$api_group_key]['api_key'] ?? '';
    }

    function save_generations($generations, $model_id, $params)
    {
        $saved_generations = [];
        foreach ($generations as $generation) {

            if (!isset($generation['output'])) {
                error_log(sprintf('Output not found. Trying to get again: %s', print_r($generation, true)));

                $prediction_id = $generation['id'];
                $access_token = self::get_random_access_token();

                $curl = curl_init();

                do {
                    curl_setopt_array($curl, [
                        CURLOPT_URL => "https://api.replicate.com/v1/predictions/$prediction_id",
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_HTTPHEADER => [
                            "Authorization: Token $access_token",
                            "Content-Type: application/json"
                        ],
                    ]);

                    $response = curl_exec($curl);
                    $data = json_decode($response, true);

                    $status = $data['status'] ?? 'unknown';

                    if ($status === 'succeeded' || $status === 'failed' || $status === 'canceled') {
                        break;
                    }

                    sleep(5); // wait 5 seconds before checking again
                } while (true);

                curl_close($curl);

                if ($status === 'succeeded') {

                    error_log(sprintf('Job finish successfully: %s', print_r($data, true)));

                    $generation['output'] = $data['output'];
                } else {
                    error_log(sprintf('❌ Job did not finish successfully: %s', print_r($data, true)));
                    continue;
                }
            }

            $model = growtype_art_get_model_details($model_id);

            $image_folder = $model['image_folder'];
            $image_location = growtype_art_get_images_saving_location();

            $image['folder'] = $image_folder;
            $image['location'] = $image_location;
            $image['url'] = $generation['output'];
            $image['meta_details'] = [
                [
                    'key' => 'generation_id',
                    'value' => $params['generation_id']
                ],
                [
                    'key' => 'provider',
                    'value' => Growtype_Art_Crud::REPLICATE_KEY
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
                error_log('save generations output error: ' . print_r($saved_image, true));
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

            Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                'image_id' => $params['reference_image']['id'],
                'meta_key' => 'video_url_image_id_' . $saved_image['id'],
                'meta_value' => $saved_image['details']['url'],
            ]);

            Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                'image_id' => $saved_image['id'],
                'meta_key' => 'parent_image_id',
                'meta_value' => $params['reference_image']['id'],
            ]);
        }

        do_action('growtype_art_model_update', $model_id);

        return $saved_generations;
    }

    public function img_to_video($params)
    {
        $url = 'https://api.replicate.com/v1/models/wan-video/wan-2.2-i2v-fast/predictions';

        $data = [
            'headers' => [
                'Authorization' => 'Bearer ' . $params['token'],
                'Content-Type' => 'application/json',
                'Prefer' => 'wait',
            ],
            'body' => wp_json_encode([
                'input' => [
                    'image' => $params['reference_image']['url'],
                    'prompt' => $params['prompt'],
                    'go_fast' => true,
                    'num_frames' => 81,
                    'resolution' => '480p',
                    'sample_shift' => 12,
                    'frames_per_second' => 16,
                    'interpolate_output' => true,
                    'lora_scale_transformer' => 1,
                    'lora_scale_transformer_2' => 1,
                ],
            ]),
            'method' => 'POST',
            'data_format' => 'body',
            'timeout' => 120,
        ];

        $response = wp_remote_post($url, $data);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    public function faceswap($original_image_id, $swap_image_url)
    {
        $target_image_url = growtype_art_get_image_url($original_image_id);

//        $cloudinary_crud = new Cloudinary_Crud();
//
//        $target_image_cloudinary = $cloudinary_crud->upload_asset($target_image_url, [
//            'folder' => 'faceswap'
//        ]);
//
//        $swap_image_cloudinary = $cloudinary_crud->upload_asset($swap_image_url, [
//            'folder' => 'faceswap'
//        ]);

        $response = $this->faceswap_generate($target_image_url, $swap_image_url);

        Growtype_Cron_Jobs::create_if_not_exists('retrieve-faceswap-image', json_encode([
            'response' => $response,
            'original_image_id' => $original_image_id,
            'swap_image_url' => $swap_image_url
        ]), 10);
    }

    public function faceswap_generate($target_image, $swap_image)
    {
        $url = 'https://api.replicate.com/v1/predictions';

        $data = array (
            'headers' => array (
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Token ' . $this->api_key,
            ),
            'body' => '{
  "version": "9a4298548422074c3f57258c5d544497314ae4112df80d116f0d2109e843d20d",
  "input": {
    "swap_image": "' . $swap_image . '",
    "target_image": "' . $target_image . '"
  }
}',
            'method' => 'POST',
            'data_format' => 'body',
        );

        $response = wp_remote_post($url, $data);

        $body = wp_remote_retrieve_body($response);

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return $responceData;
    }

    public function upscale($upscale_img_url, $original_image)
    {
        $response = $this->real_esrgan_generate($upscale_img_url);

        Growtype_Cron_Jobs::create_if_not_exists('retrieve-upscale-image', json_encode([
            'response' => $response,
            'original_image' => $original_image
        ]), 10);
    }

    public function real_esrgan_generate($img_url, $scale = 1.2)
    {
        $url = 'https://api.replicate.com/v1/predictions';

        $response = wp_remote_post($url, array (
            'headers' => array (
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Token ' . $this->api_key,
            ),
            'body' => '{
  "version": "42fed1c4974146d4d2414e2be2c5277c7fcf05fcc3a73abf41610695738c1d7b",
  "input": {
    "image": "' . $img_url . '",
    "scale": "' . $scale . '",
    "face_enhance": "false"
  }
}',
            'method' => 'POST',
            'data_format' => 'body',
        ));

        $body = wp_remote_retrieve_body($response);

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return $responceData;
    }

    public function retrieve_generation($url)
    {
        $response = wp_remote_post($url, array (
            'headers' => array (
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Token ' . $this->api_key,
            ),
            'method' => 'GET'
        ));

        $body = wp_remote_retrieve_body($response);

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return $responceData;
    }
}

