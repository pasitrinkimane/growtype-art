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

class Replicate_Base extends Growtype_Art_Generator_Base
{
    public function __construct()
    {
    }

    public function get_provider_key()
    {
        return Growtype_Art_Crud::REPLICATE_KEY;
    }

    public function get_models()
    {
        return [
            'black-forest-labs/flux-2-dev' => [
                'is_nsfw' => false,
                'rating' => 9
            ],
        ];
    }

    public function get_model_url($model_slug)
    {
        return "https://api.replicate.com/v1/models/{$model_slug}/predictions";
    }

    public function get_random_access_token()
    {
        $api_keys = $this->api_key();

        if (empty($api_keys)) {
            return null;
        }

        $api_group_key = array_keys($api_keys)[array_rand(array_keys($api_keys))];

        return $this->get_access_token($api_group_key);
    }

    // ──────────────────────────────────────────────
    // Image Generation (via Replicate predictions API)
    // ──────────────────────────────────────────────

    public function generate_image_init($params)
    {
        $model_slug = $params['model'] ?? 'black-forest-labs/flux-2-dev';
        $url = $this->get_model_url($model_slug);
        $token = $params['token'];

        $input = [
            'prompt' => $params['prompt'],
            'width' => $params['width'] ?? self::DEFAULT_IMAGE_DIMENSIONS['width'],
            'height' => $params['height'] ?? self::DEFAULT_IMAGE_DIMENSIONS['height'],
            'go_fast' => $params['go_fast'] ?? true,
            'output_format' => $params['output_format'] ?? 'webp',
            'output_quality' => $params['output_quality'] ?? 80,
            'num_outputs' => $params['num_outputs'] ?? 1,
            'disable_safety_checker' => $params['disable_safety_checker'] ?? false,
        ];

        // Handle reference images for img2img (supports 'image', 'input_image', 'reference_images', etc.)
        $reference_image_urls = $params['reference_image_urls'] ?? [];
        $image_key = $params['image_key'] ?? 'image';
        if (!empty($reference_image_urls)) {
            // Plural keys (reference_images) get the full array, singular keys get first URL
            if (str_contains($image_key, 'images')) {
                $input[$image_key] = $reference_image_urls;
            } else {
                $input[$image_key] = $reference_image_urls[0];
            }
            $input['aspect_ratio'] = $params['aspect_ratio'] ?? 'match_input_image';
        } elseif (isset($params['aspect_ratio'])) {
            $input['aspect_ratio'] = $params['aspect_ratio'];
        }

        // Forward any extra model-specific params (lora_weights, guidance, megapixels, num_inference_steps, etc.)
        $internal_keys = ['prompt', 'width', 'height', 'go_fast', 'output_format', 'output_quality', 'num_outputs',
            'disable_safety_checker', 'image_key', 'reference_image_urls', 'reference_image_url', 'aspect_ratio',
            'token', 'model_id', 'model', 'save_to_db', 'providers', 'types', 'images_amount', 'reference_files',
            'prompt_params', 'generation_id', 'segmind_model', 'api_group_key'];
        foreach ($params as $key => $value) {
            if (!in_array($key, $internal_keys, true) && !isset($input[$key])) {
                $input[$key] = $value;
            }
        }

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Prefer: wait',
        ];

        $body = json_encode(['input' => $input]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!empty($error)) {
            return [
                'success' => false,
                'message' => 'cURL error: ' . $error,
            ];
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => 'Invalid JSON response',
            ];
        }

        // Check for API-level errors (Replicate uses 'detail' for 4xx, 'error' for other cases)
        if (isset($data['error'])) {
            return [
                'success' => false,
                'message' => $data['error'],
            ];
        }

        if (isset($data['detail'])) {
            error_log('Growtype Art - Replicate error: ' . print_r($data, true));
            return [
                'success' => false,
                'message' => $data['detail'],
            ];
        }

        if (!empty($http_code) && $http_code >= 400) {
            return [
                'success' => false,
                'message' => 'HTTP ' . $http_code . ': ' . ($data['title'] ?? $data['detail'] ?? 'Unknown error'),
            ];
        }

        $status = $data['status'] ?? 'unknown';

        if ($status === 'failed' || $status === 'canceled') {
            return [
                'success' => false,
                'message' => 'Generation ' . $status . ': ' . ($data['error'] ?? 'Unknown error'),
            ];
        }

        if ($status === 'processing' || $status === 'starting') {
            // Return pending status for polling
            return [
                'status' => 'pending',
                'task_id' => $data['id'],
                'generation_id' => $data['id'],
            ];
        }

        if ($status === 'succeeded' && isset($data['output'])) {
            $output = $data['output'];
            $urls = is_array($output) ? $output : [$output];

            $generations = [];
            foreach ($urls as $image_url) {
                if (is_string($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
                    $generations[] = [
                        'url' => $image_url,
                    ];
                }
            }

            if (empty($generations)) {
                return [
                    'success' => false,
                    'message' => 'No valid image URLs in output',
                ];
            }

            return [
                'generations' => $generations,
                'prediction_id' => $data['id'],
            ];
        }

        return [
            'success' => false,
            'message' => 'Unexpected response status: ' . $status,
        ];
    }

    public function retrieve_generations($model_id, $generation_ids, $args = [])
    {
        $generation_id = is_array($generation_ids) ? $generation_ids[0] : $generation_ids;
        $api_group_key = $args['api_group_key'] ?? null;
        $token = $api_group_key ? $this->get_access_token($api_group_key) : $this->get_random_access_token();

        if (empty($token)) {
            return [];
        }

        $url = "https://api.replicate.com/v1/predictions/{$generation_id}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        if (empty($data) || ($data['status'] ?? '') !== 'succeeded') {
            return [];
        }

        $output = $data['output'];
        $urls = is_array($output) ? $output : [$output];

        $generations = [];
        foreach ($urls as $image_url) {
            if (is_string($image_url) && filter_var($image_url, FILTER_VALIDATE_URL)) {
                $generations[] = [
                    'url' => $image_url,
                ];
            }
        }

        return $generations;
    }

    public function generate_model_video($model_id, $params = [])
    {
        error_log(sprintf('Generating video from image started! Params: %s', print_r($params, true)));

        $model = growtype_art_get_model_details($model_id);

        $prompt = isset($params['prompt']) && !empty($params['prompt']) ? $params['prompt'] : $model['prompt'];

        $formatted_prompt = growtype_art_model_format_prompt($prompt, $model_id);

        $access_token = $this->get_random_access_token();

        if (empty($access_token)) {
            return [
                'success' => false,
                'message' => sprintf('Empty API keys. Model %s.', $model_id),
            ];
        }

        $params['token'] = $access_token;

        if (is_string($params['reference_image'])) {
            $params['reference_image'] = ['url' => $params['reference_image']];
        }

        if (isset($params['reference_image']['url']) && !isset($params['reference_image']['id'])) {
            $params['reference_image']['id'] = growtype_art_get_image_id_by_url($params['reference_image']['url']);
        }
        $params['prompt'] = $formatted_prompt;
        $params['generation_id'] = wp_generate_password(52, false);
        $params['model_id'] = $model_id;

        // Resolve which Replicate video model to use:
        // 1. Explicit param  2. Model setting  3. Default
        $video_model = $params['video_model']
            ?? $model['video_model']
            ?? 'wan-video/wan-2.2-i2v-fast';

        $params['video_model'] = $video_model;

        $generation_details = $this->dispatch_video_model($video_model, $params);

        if (empty($generation_details) || isset($generation_details['error'])) {
            return [
                'success' => false,
                'message' => $generation_details['error'] ?? 'Something went wrong',
            ];
        }

        $prediction_id = $generation_details['id'] ?? null;

        if (empty($prediction_id)) {
            return [
                'success' => false,
                'message' => 'No prediction ID returned from Replicate',
            ];
        }

        // Queue a cron job to poll and save the video when ready
        Growtype_Cron_Jobs::create_if_not_exists('retrieve-video-generation', json_encode([
            'prediction_id' => $prediction_id,
            'model_id' => $model_id,
            'params' => $params,
        ]), 10);

        return [
            'success' => true,
            'prediction_id' => $prediction_id,
            'generation_id' => $params['generation_id'],
            'status' => $generation_details['status'] ?? 'processing',
            'message' => sprintf('Video generation queued. Prediction ID: %s', $prediction_id),
        ];
    }

    public function get_access_token($api_group_key)
    {
        return $this->api_key()[$api_group_key]['api_key'] ?? '';
    }

    public function save_generations($generations, $model_id = null, $params = [])
    {
        // If this is an image generation (url key), delegate to parent
        $first_gen = $generations[0] ?? null;
        if ($first_gen && isset($first_gen['url']) && !isset($first_gen['output'])) {
            return parent::save_generations($generations, $model_id, $params);
        }

        $saved_generations = [];
        foreach ($generations as $generation) {

            if (!isset($generation['output'])) {
                error_log(sprintf('Output not found. Trying to get again: %s', print_r($generation, true)));

                $prediction_id = $generation['id'];
                $access_token = $this->get_random_access_token();

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

            $reference_image_id = $params['reference_image']['id'] ?? null;
            if (empty($reference_image_id) && !empty($params['reference_image']['url'])) {
                $reference_image_id = growtype_art_get_image_id_by_url($params['reference_image']['url']);
            }

            if (!empty($reference_image_id)) {
                Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                    'image_id' => $reference_image_id,
                    'meta_key' => 'video_url_image_id_' . $saved_image['id'],
                    'meta_value' => $saved_image['details']['url'],
                ]);

                Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                    'image_id' => $saved_image['id'],
                    'meta_key' => 'parent_image_id',
                    'meta_value' => $reference_image_id,
                ]);
            }
        }

        do_action('growtype_art_model_update', $model_id);

        return $saved_generations;
    }

    /**
     * Dispatch video generation to the correct model handler.
     *
     * @param string $video_model  e.g. 'wan-video/wan-2.2-i2v-fast' or 'prunaai/p-video'
     * @param array  $params
     * @return array Replicate API response
     */
    public function dispatch_video_model(string $video_model, array $params): array
    {
        switch ($video_model) {
            case 'prunaai/p-video':
                return $this->prunaai_p_video($params);

            case 'wan-video/wan-2.2-i2v-fast':
            default:
                return $this->img_to_video($params);
        }
    }

    /**
     * prunaai/p-video  –  fast image-to-video model.
     * https://replicate.com/prunaai/p-video
     */
    public function prunaai_p_video(array $params): array
    {
        $url = 'https://api.replicate.com/v1/models/prunaai/p-video/predictions';

        $input = [
            'image'             => $params['reference_image']['url'],
            'prompt'            => $params['prompt'],
            'prompt_upsampling' => $params['prompt_upsampling'] ?? false,
        ];

        // Allow callers to pass any extra p-video-specific keys via params['video_input']
        if (!empty($params['video_input']) && is_array($params['video_input'])) {
            $input = array_merge($input, $params['video_input']);
        }

        $data = [
            'headers' => [
                'Authorization' => 'Bearer ' . $params['token'],
                'Content-Type'  => 'application/json',
            ],
            'body'        => wp_json_encode(['input' => $input]),
            'method'      => 'POST',
            'data_format' => 'body',
            'timeout'     => 120,
        ];

        $response = wp_remote_post($url, $data);

        if (is_wp_error($response)) {
            return ['error' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    /**
     * wan-video/wan-2.2-i2v-fast  –  original default model.
     * Also aliased as img_to_video() for backward compatibility.
     */
    public function img_to_video(array $params): array
    {
        $url = 'https://api.replicate.com/v1/models/wan-video/wan-2.2-i2v-fast/predictions';

        $input = [
            'image'                   => $params['reference_image']['url'],
            'prompt'                  => $params['prompt'],
            'go_fast'                 => $params['video_input']['go_fast'] ?? true,
            'num_frames'              => $params['video_input']['num_frames'] ?? 81,
            'resolution'              => $params['video_input']['resolution'] ?? '480p',
            'sample_shift'            => $params['video_input']['sample_shift'] ?? 12,
            'frames_per_second'       => $params['video_input']['frames_per_second'] ?? 16,
            'interpolate_output'      => $params['video_input']['interpolate_output'] ?? true,
            'lora_scale_transformer'  => $params['video_input']['lora_scale_transformer'] ?? 1,
            'lora_scale_transformer_2'=> $params['video_input']['lora_scale_transformer_2'] ?? 1,
            'disable_safety_checker'  => $params['video_input']['disable_safety_checker'] ?? true,
        ];

        // Allow callers to pass any extra wan-specific keys
        if (!empty($params['video_input']) && is_array($params['video_input'])) {
            $input = array_merge($input, $params['video_input']);
        }

        $data = [
            'headers' => [
                'Authorization' => 'Bearer ' . $params['token'],
                'Content-Type'  => 'application/json',
            ],
            'body'        => wp_json_encode(['input' => $input]),
            'method'      => 'POST',
            'data_format' => 'body',
            'timeout'     => 120,
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

        $token = $this->get_random_access_token();

        $data = array (
            'headers' => array (
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer ' . $token,
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

        $token = $this->get_random_access_token();

        $response = wp_remote_post($url, array (
            'headers' => array (
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer ' . $token,
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
        $token = $this->get_random_access_token();

        $response = wp_remote_get($url, array (
            'headers' => array (
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer ' . $token,
            ),
        ));

        $body = wp_remote_retrieve_body($response);

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return $responceData;
    }
}
