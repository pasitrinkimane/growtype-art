<?php

namespace partials;

use Extract_Image_Colors_Job;
use Growtype_Art_Crud;
use Growtype_Art_Database;
use Growtype_Art_Database_Crud;
use Exception;
use Growtype_Cron_Jobs;

class Perchance_Base
{
    public static function api_key()
    {
        return \Growtype_Auth::credentials('perchance');
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

        $params = [
            'prompt' => $formatted_prompt,
            'token' => $this->get_access_token($api_group_key)
        ];

        $generation_details = $this->generate_image_init($params);

        d($generation_details);

        error_log('generate_model_image: ' . json_encode($generation_details));

        if (!isset($generation_details['result']['task_id'])) {
            if (isset($_GET['page']) && !empty($_GET['page'])) {
                Growtype_Cron_Jobs::create('generate-model', json_encode([
                    'provider' => Growtype_Art_Crud::PERCHANCE_KEY,
                    'model_id' => $model_id
                ]), 30);

                return [
                    'success' => false,
                    'message' => sprintf('Failed to generate image for model %s. Added to queue. Message: %s', $model_id, $generation_details['message'] ?? ''),
                ];
            } else {
                return [
                    'success' => false,
                    'message' => sprintf('Image is still generating. Model %s. Message: %s.', $model_id, $generation_details['message'] ?? ''),
                ];
            }
        }

        Growtype_Cron_Jobs::create_if_not_exists('retrieve-model', json_encode([
            'provider' => Growtype_Art_Crud::PERCHANCE_KEY,
            'api_group_key' => $api_group_key,
            'model_id' => $model_id,
            'generation_id' => $generation_details['imageId'],
            'prompt' => $formatted_prompt,
        ]), 30);

        return [
            'success' => true,
            'image_prompt' => $formatted_prompt,
        ];
    }

    public function get_access_token($api_group_key)
    {
        return self::api_key()[$api_group_key]['user_key'] ?? '';
    }

    public function generate_image_init($params)
    {
        $defaultParams = [
            'seed' => -1,
            'resolution' => '512x768',
            'guidanceScale' => 7,
            'channel' => 'free-nsfw-ai-generator',
            'subChannel' => 'public',
            'negativePrompt' => ', worst quality, bad lighting, cropped, blurry, low-quality, deformed, text, poorly drawn, bad art, bad angle, boring, low-resolution, worst quality, bad composition, terrible lighting, bad anatomy, ugly, amputee, deformed'
        ];

        // Merge default params with provided params
        $finalParams = array_merge($defaultParams, $params);

        // Add additional required parameters
        $finalParams['userKey'] = '211690c2c7ad83edbd46d7d391292cdf91d97f4d61a0ab9e99df8ac6619af6b8';
        $finalParams['adAccessCode'] = 'c5b0fbff366e353306f892089f84133fa1dee63d66b8adddacb6092fe3bbca75';
//        $finalParams['requestId'] = $this->generateRequestId();
//        $finalParams['v'] = $this->generateVersionHash();
//        $finalParams['__cacheBust'] = $this->generateCacheBustValue();

        // Prepare URL with query parameters
        $url = 'https://image-generation.perchance.org/api/generate' . '?' . http_build_query($finalParams);

        // Initialize cURL
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: PostmanRuntime/7.43.0',
            'Accept: */*',
            'Cache-Control: no-cache',
            'Host: image-generation.perchance.org',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Content-Length: 0'
        ]);

        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Close cURL
        curl_close($ch);

        d($response);

        // Process and return response
        if ($httpCode == 200) {
            return json_decode($response, true);
        } else {
            throw new Exception("API request failed with HTTP code: $httpCode");
        }

        d($responseData);

        return [
            "http_code" => $http_code,
            "response" => json_decode($response, true)
        ];
    }

    public function retrieve_generations($model_id, $generations_ids, $args = [])
    {
        $token = $this->get_access_token($args['api_group_key']);

        $generations = [];
        foreach ($generations_ids as $generations_id) {
            $generations = $this->get_generation($token, $generations_id);

            if (isset($generations['result']['data']['results']) && !empty($generations['result']['data']['results'])) {

//                error_log('retrieve_generations: ' . json_encode($generations));

                /**
                 * Save generation
                 */
                $saved_generations = $this->save_generations($generations['result']['data']['results'], $model_id, $args);

                /**
                 * Delete generation from Leonardo.ai
                 */
                foreach ($saved_generations as $saved_generation) {
                    $delete_external_generation = $this->delete_external_generation($token, $generations_id);

                    error_log('delete_external_generation: ' . json_encode($delete_external_generation));
                }
            }
        }

        return $generations;
    }

    function save_generations($generations, $model_id, $args)
    {
        $saved_generations = [];
        foreach ($generations as $generation) {

            if (empty($generation['origin'])) {
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

