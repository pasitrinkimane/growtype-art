<?php

namespace partials;

use Extract_Image_Colors_Job;
use Growtype_Art_Crud;
use Growtype_Art_Database;
use Growtype_Art_Database_Crud;
use Exception;
use Growtype_Cron_Jobs;

class Aiease_Base
{
    public static function api_key()
    {
        return \Growtype_Auth::credentials('aiease');
    }

    public function generate_model_image($model_id = null)
    {
        $model = growtype_art_get_model_details($model_id);

        $formatted_prompt = growtype_art_model_format_prompt($model['prompt'], $model_id);

        $api_keys = self::api_key();

        if (empty($api_keys)) {
            return [
                'success' => false,
                'message' => sprintf('Empty API keys. Model %s.', $model_id),
            ];
        }

        $api_group_key = array_keys($api_keys)[array_rand(array_keys(self::api_key()))];

        $token = $this->get_access_token($api_group_key);

        $params = [
            'prompt' => $formatted_prompt,
            'token' => $token
        ];

        $generation_details = $this->generate_image_init($params);

        $params_urlencoded = urlencode(json_encode($params));

//        error_log(sprintf('Generating image. Details: %s. Group key: %s', json_encode($generation_details), $api_group_key));

        if (!isset($generation_details['result']['task_id'])) {
            if (isset($_GET['page']) && !empty($_GET['page'])) {
                Growtype_Cron_Jobs::create('generate-model', json_encode([
                    'provider' => Growtype_Art_Crud::AIEASE_KEY,
                    'model_id' => $model_id
                ]), 30);

                return [
                    'success' => false,
                    'message' => sprintf('Failed to generate image for model %s. Params: %s. Message: %s', $model_id, $params_urlencoded, $generation_details['message'] ?? ''),
                ];
            } else {
                return [
                    'success' => false,
                    'message' => sprintf('Image is still generating. Model %s. Params: %s. Message: %s.', $model_id, $params_urlencoded, $generation_details['message'] ?? ''),
                ];
            }
        }

        Growtype_Cron_Jobs::create_if_not_exists('retrieve-model', json_encode([
            'provider' => Growtype_Art_Crud::AIEASE_KEY,
            'api_group_key' => $api_group_key,
            'model_id' => $model_id,
            'generation_id' => $generation_details['result']['task_id'],
            'prompt' => $formatted_prompt,
        ]), 30);

        return [
            'success' => true,
            'generations' => [
                [
                    'generation_id' => $generation_details['result']['task_id'] ?? ''
                ]
            ],
            'message' => sprintf('Successfully generated. Params: %s', $params_urlencoded)
        ];
    }

    public function get_access_token($api_group_key)
    {
        return self::api_key()[$api_group_key]['jwt_token'] ?? '';
    }

    public function generate_image_init($params)
    {
        $url = "https://www.aiease.ai/api/api/gen/text2img";
        $token = $params['token'];

// Request headers
        $headers = [
            "authorization: JWT $token",
            "Content-Type: application/json"
        ];

// Request body
        $data = [
            "gen_type" => "art_v1",
            "art_v1_extra_data" => [
                "prompt" => $params['prompt'],
                "style_id" => 1,
                "size" => "9-16",
            ]
        ];

// Initialize cURL session
        $ch = curl_init($url);

// Set cURL options
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

//        d([
//            $params,
//            $response,
//            $error
//        ]);

// Output results
        return json_decode($response, true);
    }

    public function retrieve_generations($model_id, $generations_ids, $args = [])
    {
        $token = $this->get_access_token($args['api_group_key']);

        $generations = [];
        foreach ($generations_ids as $generations_id) {
            $generation = $this->get_generation($token, $generations_id);

            $generations[] = $generation;

            if (isset($generation['result']['data']['results']) && !empty($generation['result']['data']['results'])) {

                error_log('retrieve_generations: ' . json_encode($generation));

                $args['generation_id'] = $generations_id;

                /**
                 * Save generation
                 */
                $saved_generations = $this->save_generations($generation['result']['data']['results'], $model_id, $args);

                /**
                 * Delete generation from Leonardo.ai
                 */
                foreach ($saved_generations as $saved_generation) {
                    if (isset($saved_generation['success']) && !$saved_generation['success']) {
                        $generate_details = growtype_art_generate_model_image($model_id, [
                            'providers' => Growtype_Art_Crud::NSFW_PROVIDERS,
                            'prompt' => $args['prompt']
                        ]);

                        error_log(sprintf('NSFW generating. Response: %s', print_r($generate_details, true)));
                    }

                    $delete_external_generation = $this->delete_external_generation($token, $generations_id);

                    error_log('delete_external_generation: ' . json_encode($delete_external_generation));
                }
            }
        }

        return [
            'success' => true,
            'generations' => $generations
        ];
    }

    function save_generations($generations, $model_id, $args)
    {
        $saved_generations = [];
        foreach ($generations as $generation) {

            if (empty($generation['origin'])) {
                continue;
            }

            if (isset($generation['nsfw']) && $generation['nsfw']) {
                $saved_generations[] = [
                    'success' => false
                ];

                error_log(sprintf('aiease generator. Sensitive image. %s', print_r($generation, true)));
                continue;
            }

            $model = growtype_art_get_model_details($model_id);

            $image_folder = $model['image_folder'];
            $image_location = growtype_art_get_images_saving_location();

            $image['folder'] = $image_folder;
            $image['location'] = $image_location;
            $image['url'] = $generation['origin'];

            $image['meta_details'] = [
                [
                    'key' => 'generation_id',
                    'value' => $args['generation_id']
                ],
                [
                    'key' => 'provider',
                    'value' => Growtype_Art_Crud::AIEASE_KEY
                ],
                [
                    'key' => 'prompt',
                    'value' => $args['prompt']
                ]
            ];

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

            /**
             * Assign image to model
             */
            Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::MODEL_IMAGE_TABLE, [
                'model_id' => $model_id,
                'image_id' => $saved_image['id']
            ]);

            /**
             * Get image colors
             */
//            Extract_Image_Colors_Job::update_image_colors_groups($saved_image['id']);

            /**
             * Compress image
             */
            growtype_art_compress_existing_image($saved_image['id']);

            sleep(2);

            $saved_generations[] = [
                'image_id' => $saved_image['id']
            ];

            do_action('growtype_art_model_update', $model_id);
        }

        return $saved_generations;
    }

    function get_generation($token, $generation_id)
    {
        $url = "https://www.aiease.ai/api/api/id_photo/task-info?task_id=" . $generation_id;

// Request headers
        $headers = [
            "authorization: JWT $token",
            "Content-Type: application/json"
        ];

// Initialize cURL session
        $ch = curl_init($url);

// Set cURL options
        curl_setopt($ch, CURLOPT_HTTPGET, true); // Use GET instead of POST
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

// Output results
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
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return json_decode($response, true);
    }
}

