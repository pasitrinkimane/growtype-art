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

class Aiease_Base extends Growtype_Art_Generator_Base
{
    public function get_provider_key()
    {
        return Growtype_Art_Crud::AIEASE_KEY;
    }

    public function get_default_model($params = [])
    {
        return $params['model'] ?? 'art_v1';
    }

    public function get_access_token($api_group_key)
    {
        $api_keys = $this->api_key();
        return $api_keys[$api_group_key]['jwt_token'] ?? $api_keys[$api_group_key]['api_key'] ?? '';
    }

    public function generate_image_init($params)
    {
        $url = "https://www.aiease.ai/api/api/gen/text2img";
        $token = $params['token'];


        $data = [
            "gen_type" => "art_v1",
            "art_v1_extra_data" => [
                "prompt" => $params['prompt'],
                "style_id" => 1,
                "size" => str_replace(':', '-', $params['aspect_ratio'] ?? '9:16'),
            ]
        ];

        $wp_response = wp_remote_post($url, [
            'headers' => [
                'authorization' => "JWT $token",
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode($data),
            'timeout' => 60,
        ]);

        $response = is_wp_error($wp_response) ? '' : wp_remote_retrieve_body($wp_response);

        error_log('Growtype Art - Aiease Base: Raw Response: ' . $response);

        $result = json_decode($response, true);

        if (isset($result['result']['task_id'])) {
            return [
                'status'           => 'pending',
                'task_id'          => $result['result']['task_id'],
                'message'          => 'Generation started',
                'original_response'=> $result,
                '_request_payload' => array_merge(['_url' => $url], $data),
                // Thread payload through cron so it's available when the image is saved
                'cron_payload'     => [
                    '_provider_request_payload' => array_merge(['_url' => $url], $data),
                ],
            ];
        }

        return [
            'success' => false,
            'message' => $result['message'] ?? 'Unknown error',
            'errors' => [['message' => $result['message'] ?? 'Unknown error']]
        ];
    }

    public function retrieve_generations($model_id, $generations_ids, $args = [])
    {
        // Fix: Use array if passed logical single ID, though typecast usually handles it?
        // The Job passes `generation_id` as single string usually, but this method signature expects array or string?
        // Base cron job logic: `retrieve_generations` called with ARRAY of IDs in `Retrieve_Model_Job`?
        // Let's check `Retrieve_Model_Job.php`:
        // $generations = $crud->retrieve_generations($job_payload['model_id'], [$job_payload['generation_id']], ...);
        // It passes an ARRAY of 1 ID.

        $token = $this->get_access_token($args['api_group_key']);

        $generations = [];
        foreach ($generations_ids as $generations_id) {
            $generation = $this->get_generation($token, $generations_id);
            $data = $generation['result']['data'] ?? [];
            $status = $data['status'] ?? '';

            if (isset($data['results']) && !empty($data['results'])) {

                $args['generation_id'] = $generations_id;

                // Save using local override
                $saved_generations = $this->save_generations($data['results'], $model_id, $args);

                // External delete
                foreach ($saved_generations as $saved_generation) {
                     // Check if NSFW failure
                    if (isset($saved_generation['success']) && $saved_generation['success'] === false) {
                         // NSFW detected, retry with NSFW providers?
                         // This logic was in previous file, keeping it.
                        $generate_details = growtype_art_generate_model_image($model_id, [
                            'providers' => Growtype_Art_Crud::NSFW_PROVIDERS,
                            'prompt' => $args['prompt']
                        ]);
                    }

                    $this->delete_external_generation($token, $generations_id);
                }
                
                $generations[] = $saved_generations;
            } elseif (in_array($status, ['failed', 'error', 'fail'], true) || (isset($generation['code']) && $generation['code'] !== 200)) {
                if (empty($args['model'])) {
                    $args['model'] = $this->get_default_model($args);
                }
                do_action('growtype_art_generation_failed', $model_id, array_merge(['generation_id' => $generations_id], $args), $generation['message'] ?? 'Aiease task failed', $this->get_provider_key());
            } else {
                throw new Exception(sprintf('Aiease generation %s is still processing (status: %s).', $generations_id, is_scalar($status) ? $status : json_encode($status)));
            }
        }

        return $generations;
    }

    // OVERRIDE Base save_generations to keep NSFW logic and custom meta
    // Note: Signature should match Base if possible, but Base is: save_generations($generations, $model_id = null, $params)
    // Original Aiease: save_generations($generations, $model_id, $args)
    // We will align signature to Base: $params is $args
    function save_generations($generations, $model_id = null, $params = [])
    {
        $saved_generations = [];
        foreach ($generations as $generation) {

            if (empty($generation['origin'])) {
                continue;
            }

            if (isset($generation['nsfw']) && $generation['nsfw']) {
                $saved_generations[] = [
                    'success' => false, // Marker for NSFW failure
                    'nsfw' => true
                ];
                error_log(sprintf('aiease generator. Sensitive image. %s', print_r($generation, true)));
                if (empty($params['model'])) {
                    $params['model'] = $this->get_default_model($params);
                }
                do_action('growtype_art_generation_failed', $model_id, $params, 'NSFW image detected by provider', $this->get_provider_key());
                continue;
            }

            // Standard logic
            if ($model_id) {
                $model = growtype_art_get_model_details($model_id);
                $image_folder = $model['image_folder'];
            } else {
                $image_folder = Growtype_Art_Crud::IMAGES_FOLDER_NAME . '/public';
            }
            
            $image_location = growtype_art_get_images_saving_location();

            $image['folder'] = $image_folder;
            $image['location'] = $image_location;
            $image['url'] = $generation['origin'];

            $image['meta_details'] = [
                [
                    'key' => 'generation_id',
                    'value' => $params['generation_id'] ?? ''
                ],
                [
                    'key' => 'provider',
                    'value' => Growtype_Art_Crud::AIEASE_KEY
                ],
                [
                    'key' => 'prompt',
                    'value' => $params['prompt'] ?? ''
                ]
            ];

            // Aiease specific meta extraction
            foreach ($generation as $key => $value) {
                if (!in_array($key, ['realWidth', 'realHeight', 'status', 'index', 'info'])) {
                    array_push($image['meta_details'], [
                        'key' => $key,
                        'value' => is_array($value) ? json_encode($value) : (!empty($value) ? $value : '0')
                    ]);
                }
            }

            $saved_image = Growtype_Art_Crud::save_image($image);

            if (empty($saved_image) || isset($saved_image['error']) || !isset($saved_image['id'])) {
                error_log('save_generations: ' . json_encode($saved_image));
                if (empty($params['model'])) {
                    $params['model'] = $this->get_default_model($params);
                }
                do_action('growtype_art_generation_failed', $model_id, $params, $saved_image['error'] ?? 'save_image failed', $this->get_provider_key());
                continue;
            }

            if ($model_id) {
                Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::MODEL_IMAGE_TABLE, [
                    'model_id' => $model_id,
                    'image_id' => $saved_image['id']
                ]);
                
                growtype_art_compress_existing_image($saved_image['id']);
                do_action('growtype_art_model_update', $model_id);
            }

            $saved_generations[] = [
                'success' => true,
                'image_id' => $saved_image['id'],
                'url' => $saved_image['details']['url'] ?? '',
            ];

            // Trigger logger action for successful generation
            if (empty($params['model'])) {
                $params['model'] = $this->get_default_model($params);
            }
            do_action('growtype_art_generation_success', $saved_image, $model_id, $params, $this->get_provider_key());
        }

        return $saved_generations;
    }

    function get_generation($token, $generation_id)
    {
        $url = "https://www.aiease.ai/api/api/id_photo/task-info?task_id=" . $generation_id;
        $wp_response = wp_remote_get($url, [
            'headers' => [
                'authorization' => "JWT $token",
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
        ]);

        $response = is_wp_error($wp_response) ? '{}' : wp_remote_retrieve_body($wp_response);

        return json_decode($response, true);
    }

    public function delete_external_generation($token, $prompt_id)
    {
        $prompt_id = (int)$prompt_id;
        $url = "https://www.aiease.ai/api/api/id_photo/history/$prompt_id/1";
        $wp_response = wp_remote_request($url, [
            'method'  => 'DELETE',
            'headers' => [
                'authorization' => "JWT $token",
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 15,
        ]);

        $response = is_wp_error($wp_response) ? '{}' : wp_remote_retrieve_body($wp_response);

        return json_decode($response, true);
    }

    public function generate_image($params = [])
    {
        return $this->generate_image_sync($params);
    }
}
