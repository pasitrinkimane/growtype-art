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

class Replicate_Base extends Growtype_Art_Generator_Base
{
    public function __construct()
    {
    }

    public function get_provider_key()
    {
        return Growtype_Art_Crud::REPLICATE_KEY;
    }

    public function get_default_model($params = [])
    {
        return $params['model'] ?? 'black-forest-labs/flux-2-dev';
    }

    public function get_models()
    {
        return [
            'black-forest-labs/flux-2-dev' => [
                'label'    => 'Flux 2 Dev',
                'is_nsfw'  => false,
                'rating'   => 9,
                'cost_usd' => 0.003,
            ],
            'prunaai/p-image' => [
                'label'    => 'Prun AI / P-Image',
                'is_nsfw'  => true,
                'rating'   => 8,
                'cost_usd' => 0.002,
            ],
            'prunaai/p-image-edit' => [
                'label'    => 'Prun AI / P-Image Edit',
                'is_nsfw'  => true,
                'rating'   => 8,
                'cost_usd' => 0.002,
            ],
        ];
    }

    public function get_model_url($model_slug)
    {
        return "https://api.replicate.com/v1/models/{$model_slug}/predictions";
    }


    // ──────────────────────────────────────────────
    // Image Generation (via Replicate predictions API)
    // ──────────────────────────────────────────────

    /**
     * Declarative per-model configuration.
     *
     * Keys:
     *   image_key        – param name used to pass reference image(s) to the API
     *   aspect_ratio     – default aspect_ratio when NO reference image is provided (null = omit)
     *   ref_aspect_ratio – default aspect_ratio when a reference image IS provided (null = omit)
     *   extra_defaults   – any additional fields to inject when not already set by the caller
     *
     * To add a new model: add one entry here. No logic changes needed.
     */
    private static function get_model_config(string $model_slug): array
    {
        $configs = [
            'black-forest-labs/flux-2-dev' => [
                'image_key'         => 'input_images',
                'aspect_ratio'      => null,
                'ref_aspect_ratio'  => 'match_input_image',
                'ref_exclude_fields'=> ['width', 'height', 'num_outputs', 'disable_safety_checker'],
                'extra_defaults'    => [
                    'output_format' => 'jpg',
                ],
            ],
            'prunaai/p-image' => [
                'image_key'         => 'images',   // expects array of images
                'aspect_ratio'      => 'custom',   // valid: 1:1 16:9 9:16 4:3 3:4 3:2 2:3 custom
                'ref_aspect_ratio'  => 'custom',   // same restriction; 'match_input_image' NOT valid
                'ref_exclude_fields'=> [],
                'extra_defaults'    => [],
            ],
            'prunaai/p-image-edit' => [
                // p-image-edit does NOT accept 'custom'; valid values:
                // match_input_image | 1:1 | 16:9 | 9:16 | 4:3 | 3:4 | 3:2 | 2:3
                'image_key'         => 'images',
                'aspect_ratio'      => '1:1',             // no ref-image case
                'ref_aspect_ratio'  => 'match_input_image', // ref-image case
                'ref_exclude_fields'=> ['width', 'height'], // incompatible with match_input_image
                'extra_defaults'    => ['megapixels' => '2'],
            ],
        ];

        // Default config for all other models
        return $configs[$model_slug] ?? [
            'image_key'         => 'image',
            'aspect_ratio'      => null,                // don't send unless caller specifies
            'ref_aspect_ratio'  => 'match_input_image', // safe default for most flux-style models
            'ref_exclude_fields'=> [],
            'extra_defaults'    => [],
        ];
    }

    public function generate_image_init($params)
    {
        $model_slug = $params['model'] ?? 'black-forest-labs/flux-2-dev';
        $url        = $this->get_model_url($model_slug);
        $token      = $params['token'];
        $config     = self::get_model_config($model_slug);

        // ── Base input ────────────────────────────────────────────────────────
        $input = [
            'prompt'                 => $params['prompt'],
            'width'                  => $params['width']          ?? self::DEFAULT_IMAGE_DIMENSIONS['width'],
            'height'                 => $params['height']         ?? self::DEFAULT_IMAGE_DIMENSIONS['height'],
            'go_fast'                => $params['go_fast']        ?? true,
            'output_format'          => $params['output_format']  ?? ($config['extra_defaults']['output_format'] ?? 'webp'),
            'output_quality'         => $params['output_quality'] ?? 80,
            'num_outputs'            => $params['num_outputs']    ?? 1,
            'disable_safety_checker' => $params['disable_safety_checker'] ?? true,
        ];

        // ── Model-specific extra defaults (e.g. megapixels for p-image-edit) ─
        foreach ($config['extra_defaults'] as $field => $value) {
            if (!isset($input[$field])) {
                $input[$field] = $value;
            }
        }

        // ── Reference images ──────────────────────────────────────────────────
        // Normalize singular → plural so callers can use either form
        $reference_image_urls = $params['reference_image_urls'] ?? [];
        if (empty($reference_image_urls) && !empty($params['reference_image_url'])) {
            $reference_image_urls = (array) $params['reference_image_url'];
        }
        // Model config is authoritative for the API field name (e.g. 'images', 'image').
        // $params['image_key'] is treated as a semantic hint only — it must NOT override
        // the model config for explicitly configured models, because the caller may use
        // 'reference_images' as a logical name while the API actually expects 'images'.
        // To customise the key for a new model, add it to get_model_config() instead.
        $image_key = $config['image_key'];

        if (!empty($reference_image_urls)) {
            // Plural key → full array; singular key → first URL only
            $input[$image_key] = str_contains($image_key, 'images')
                ? $reference_image_urls
                : $reference_image_urls[0];

            $default_ar = $config['ref_aspect_ratio'];

            // Strip fields that are incompatible with this model's reference-image mode
            foreach ($config['ref_exclude_fields'] ?? [] as $field) {
                unset($input[$field]);
            }
        } else {
            $default_ar = $config['aspect_ratio'];
        }

        // ── aspect_ratio: explicit caller param > model default > omit ────────
        if (isset($params['aspect_ratio'])) {
            $input['aspect_ratio'] = $params['aspect_ratio'];
        } elseif ($default_ar !== null) {
            $input['aspect_ratio'] = $default_ar;
        }

        // ── Forward any extra caller params not already set ───────────────────
        // (lora_weights, guidance, num_inference_steps, etc.)
        $internal_keys = [
            'prompt', 'width', 'height', 'go_fast', 'output_format', 'output_quality',
            'num_outputs', 'disable_safety_checker', 'image_key', 'reference_image_urls',
            'reference_image_url', 'aspect_ratio', 'token', 'model_id', 'model',
            'save_to_db', 'providers', 'types', 'images_amount', 'reference_files',
            'prompt_params', 'generation_id', 'segmind_model', 'api_group_key',
            'source', 'created_by',
        ];
        foreach ($params as $key => $value) {
            if (!in_array($key, $internal_keys, true) && !isset($input[$key])) {
                $input[$key] = $value;
            }
        }

        error_log('Growtype Art - Replicate params: ' . json_encode(array_keys($params)));
        error_log('Growtype Art - Replicate input: ' . json_encode($input));

        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
            'Prefer'        => 'wait',
        ];

        $body = json_encode(['input' => $input]);
        error_log('Growtype Art - Replicate body: ' . $body);

        $wp_response = wp_remote_post($url, [
            'headers' => $headers,
            'body'    => $body,
            'timeout' => 180,
        ]);

        if (is_wp_error($wp_response)) {
            return [
                'success' => false,
                'message' => $wp_response->get_error_message(),
            ];
        }

        $response  = wp_remote_retrieve_body($wp_response);
        $http_code = (int) wp_remote_retrieve_response_code($wp_response);

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
                '_request_payload' => $input,
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
                '_request_payload' => $input,
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

        $wp_response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
        ]);

        if (is_wp_error($wp_response)) {
            return [];
        }

        $response = wp_remote_retrieve_body($wp_response);

        $data = json_decode($response, true);
        $status = $data['status'] ?? 'unknown';

        if (empty($data) || in_array($status, ['starting', 'processing'], true)) {
            throw new Exception(sprintf('Replicate prediction %s is still %s.', $generation_id, $status));
        }

        if (in_array($status, ['failed', 'canceled'], true)) {
            if (empty($args['model']) && empty($args['video_model'])) {
                $args['model'] = $this->get_default_model($args);
            }
            do_action('growtype_art_generation_failed', $model_id, array_merge(['generation_id' => $generation_id], $args), $data['error'] ?? 'Prediction failed', $this->get_provider_key());
            return []; // Do not throw exception so the cron job is cleared
        }

        if ($status !== 'succeeded' || !isset($data['output'])) {
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
                $access_token  = $this->get_random_access_token();

                $max_attempts = 60; // 60 × 5s = 5 min max
                $attempt      = 0;
                $status       = 'unknown';

                do {
                    $poll_response = wp_remote_get(
                        "https://api.replicate.com/v1/predictions/{$prediction_id}",
                        [
                            'headers' => [
                                'Authorization' => "Bearer {$access_token}",
                                'Content-Type'  => 'application/json',
                            ],
                            'timeout' => 30,
                        ]
                    );

                    $data   = !is_wp_error($poll_response)
                        ? json_decode(wp_remote_retrieve_body($poll_response), true)
                        : [];
                    $status = $data['status'] ?? 'unknown';

                    if ($status === 'succeeded' || $status === 'failed' || $status === 'canceled') {
                        break;
                    }

                    $attempt++;
                    if ($attempt >= $max_attempts) {
                        error_log(sprintf('Replicate polling timeout after %d attempts for prediction %s', $max_attempts, $prediction_id));
                        break;
                    }

                    sleep(5); // wait 5 seconds before checking again
                } while (true);

                if ($status === 'succeeded') {

                    error_log(sprintf('Job finish successfully: %s', print_r($data, true)));

                    $generation['output'] = $data['output'];
                } else {
                    error_log(sprintf('❌ Job did not finish successfully: %s', print_r($data, true)));
                    continue;
                }
            }

            $model = growtype_art_get_model_details($model_id);

            // Normalize output: some models return a string URL, others an array
            $output_url = is_array($generation['output'])
                ? ($generation['output'][0] ?? null)
                : $generation['output'];

            if (empty($output_url)) {
                error_log('Replicate save_generations: empty output URL for prediction.');
                continue;
            }

            $image_folder   = $model['image_folder'];
            $image_location = growtype_art_get_images_saving_location();

            // Re-initialize $image each iteration to prevent property leakage
            $image = [
                'folder'       => $image_folder,
                'location'     => $image_location,
                'url'          => $output_url,
                'meta_details' => [
                    [
                        'key'   => 'generation_id',
                        'value' => $params['generation_id'],
                    ],
                    [
                        'key'   => 'provider',
                        'value' => Growtype_Art_Crud::REPLICATE_KEY,
                    ],
                    [
                        'key'   => 'prompt',
                        'value' => $params['prompt'],
                    ],
                ],
            ];

            if (isset($params['types'])) {
                foreach ($params['types'] as $type) {
                    $image['meta_details'][] = [
                        'key'   => $type,
                        'value' => 1,
                    ];
                }
            }

            $saved_image = Growtype_Art_Crud::save_image($image);

            if (empty($saved_image) || isset($saved_image['error']) || !isset($saved_image['id'])) {
                error_log('save generations output error: ' . print_r($saved_image, true));
                if (empty($params['video_model'])) {
                    $params['video_model'] = 'wan-video/wan-2.2-i2v-fast';
                }
                do_action('growtype_art_generation_failed', $model_id, array_merge(['asset_type' => 'video'], $params), $saved_image['error'] ?? 'save_image failed', $this->get_provider_key());
                continue;
            }

            /**
             * Assign image to model
             */
            Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::MODEL_IMAGE_TABLE, [
                'model_id' => $model_id,
                'image_id' => $saved_image['id'],
            ]);

            $saved_generations[] = [
                'url'           => $saved_image['details']['url'],
                'image_id'      => $saved_image['id'],
                'generation_id' => $params['generation_id'],
                'image_prompt'  => $params['prompt'],
            ];

            $reference_image_id = $params['reference_image']['id'] ?? null;
            if (empty($reference_image_id) && !empty($params['reference_image']['url'])) {
                $reference_image_id = growtype_art_get_image_id_by_url($params['reference_image']['url']);
            }

            if (!empty($reference_image_id)) {
                Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                    'image_id'   => $reference_image_id,
                    'meta_key'   => 'video_url_image_id_' . $saved_image['id'],
                    'meta_value' => $saved_image['details']['url'],
                ]);

                Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                    'image_id'   => $saved_image['id'],
                    'meta_key'   => 'parent_image_id',
                    'meta_value' => $reference_image_id,
                ]);
            }

            // Trigger logger action for successful generation
            if (empty($params['video_model'])) {
                $params['video_model'] = 'wan-video/wan-2.2-i2v-fast';
            }

            do_action('growtype_art_generation_success', $saved_image, $model_id, array_merge(['asset_type' => 'video'], $params), $this->get_provider_key());
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
