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

class Aiease_Base extends Growtype_Art_Generator_Base
{
    public function get_provider_key()
    {
        return Growtype_Art_Crud::AIEASE_KEY;
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

        $headers = [
            "authorization: JWT $token",
            "Content-Type: application/json"
        ];

        $data = [
            "gen_type" => "art_v1",
            "art_v1_extra_data" => [
                "prompt" => $params['prompt'],
                "style_id" => 1,
                "size" => "9-16",
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        error_log('Growtype Art - Aiease Base: Raw Response: ' . $response);

        $result = json_decode($response, true);

        if (isset($result['result']['task_id'])) {
            return [
                'status' => 'pending',
                'task_id' => $result['result']['task_id'],
                'message' => 'Generation started',
                'original_response' => $result
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

            if (isset($generation['result']['data']['results']) && !empty($generation['result']['data']['results'])) {

                $args['generation_id'] = $generations_id;

                // Save using local override
                $saved_generations = $this->save_generations($generation['result']['data']['results'], $model_id, $args);

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
        }

        return $saved_generations;
    }

    function get_generation($token, $generation_id)
    {
        $url = "https://www.aiease.ai/api/api/id_photo/task-info?task_id=" . $generation_id;
        $headers = [
            "authorization: JWT $token",
            "Content-Type: application/json"
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    public function delete_external_generation($token, $prompt_id)
    {
        $prompt_id = (int)$prompt_id;
        $url = "https://www.aiease.ai/api/api/id_photo/history/$prompt_id/1";
        $headers = [
            "authorization: JWT $token",
            "Content-Type: application/json"
        ];
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    public function generate_image($params = [])
    {
        return $this->generate_image_sync($params);
    }
}

