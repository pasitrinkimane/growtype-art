<?php

namespace partials;

require_once GROWTYPE_ART_PATH . '/includes/methods/crud/Growtype_Art_Generator_Base.php';

use Growtype_Art_Crud;
use Growtype_Art_Database;
use Growtype_Art_Database_Crud;
use Exception;
use Growtype_Cron_Jobs;
use Growtype_Art_Generator_Base;

class Piclumen_Base extends Growtype_Art_Generator_Base
{
    public function get_provider_key()
    {
        return Growtype_Art_Crud::PICLUMEN_KEY;
    }

    public function get_models()
    {
        return [
            'piclumen-default' => [
                'is_nsfw' => false,
            ],
        ];
    }

    public function generate_image_init($params)
    {
        $token = $params['token'];
        error_log(sprintf('Growtype Art - Piclumen Base: Token length: %d, Prefix: %s', strlen($token), substr($token, 0, 10)));

        $url = "https://api.piclumen.com/api/gen/create";

        $headers = [
            "authorization: $token",
            "platform: Web",
            "Content-Type: application/json;charset=UTF-8",
            "Accept: application/json",
            "Origin: https://www.piclumen.com",
            "Referer: https://www.piclumen.com/",
            "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36",
            "Cache-Control: no-cache",
        ];

        $data = [
            "model_id" => "23887bba-507e-4249-a0e3-6951e4027f2b",
            "prompt" => $params['prompt'],
            "negative_prompt" => "",
            "resolution" => [
                "width" => $params['width'] ?? self::DEFAULT_IMAGE_DIMENSIONS['width'],
                "height" => $params['height'] ?? self::DEFAULT_IMAGE_DIMENSIONS['height'],
                "batch_size" => 1
            ],
            "seed" => Growtype_Art_Crud::generate_seed(),
            "steps" => 6,
            "cfg" => 1,
            "sampler_name" => "euler",
            "scheduler" => "normal",
            "denoise" => 1,
            "hires_fix_denoise" => 0.5,
            "hires_scale" => 2,
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        error_log(sprintf('Growtype Art - Piclumen Base: HTTP Code: %s, Error: %s, Raw Response: %s', $http_code, $error, $response));

        if ($http_code === 401) {
            return [
                'success' => false,
                'message' => 'Unauthorized: Please check your Piclumen API token.',
                'errors' => [['message' => 'Unauthorized: Please check your Piclumen API token.']]
            ];
        }

        $result = json_decode($response, true);

        if (isset($result['data']['markId'])) {
            return [
                'status' => 'pending',
                'task_id' => $result['data']['markId'],
                'message' => 'Generation started',
                'original_response' => $result
            ];
        }

        $error_message = $result['message'] ?? $result['error'] ?? 'Unknown error (HTTP ' . $http_code . ')';

        return [
            'success' => false,
            'message' => $error_message,
            'errors' => [['message' => $error_message]]
        ];
    }

    public function retrieve_generations($model_id, $generations_ids, $args = [])
    {
        $token = $this->get_access_token($args['api_group_key'] ?? 'default');
        $generations = $this->get_generations($token, $generations_ids);

        if (isset($generations['data']) && !empty($generations['data'])) {
            error_log('retrieve_generations: ' . json_encode($generations));

            $generations_list = [];
            foreach ($generations['data'] as $generation) {
                if (empty($generation['img_urls'])) {
                    continue;
                }

                foreach ($generation['img_urls'] as $img_data) {
                    // Check for NSFW
                    if (isset($img_data['sensitive']) && $img_data['sensitive'] === 'NSFW') {
                        error_log(sprintf('Piclumen generator: NSFW detected. Prompt ID: %s, Img Name: %s', $generation['promptId'], $img_data['imgName']));
                        
                        // Retry with NSFW providers if needed
                        growtype_art_generate_model_image($model_id, [
                            'providers' => Growtype_Art_Crud::NSFW_PROVIDERS,
                            'prompt' => $args['prompt'] ?? ''
                        ]);

                        $this->delete_external_generation($token, $generation['promptId'], $img_data['imgName']);
                        continue;
                    }

                    // Map to standard format for base save_generations
                    $mapped = array_merge($generation, $img_data);
                    $mapped['url'] = $img_data['imgUrl'];
                    $mapped['generation_id'] = $generation['markId'];
                    $generations_list[] = $mapped;
                }
            }

            if (empty($generations_list)) {
                return [];
            }

            /**
             * Save generations using base method
             */
            $saved_generations = $this->save_generations($generations_list, $model_id, $args);

            /**
             * Cleanup external images
             */
            foreach ($generations_list as $gen) {
                $this->delete_external_generation($token, $gen['promptId'], $gen['imgName']);
            }

            return $saved_generations;
        }

        return [];
    }

    public function get_generations($token, $generations_ids)
    {
        $url = "https://api.piclumen.com/api/task/batch-process-task";

        $headers = [
            "authorization: $token",
            "platform: Web",
            "Content-Type: application/json",
            "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36",
            "Accept: */*",
            "Cache-Control: no-cache",
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($generations_ids));
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    public function delete_external_generation($token, $prompt_id, $img_name)
    {
        $url = "https://api.piclumen.com/api/img/delete";

        $headers = [
            "authorization: $token",
            "platform: Web",
            "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36",
            "Accept: */*",
            "Cache-Control: no-cache",
            "Content-Type: multipart/form-data"
        ];

        $fields = [
            "promptId" => $prompt_id,
            "imgName" => $img_name
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    public function generate_image($params = [])
    {
        return $this->generate_image_sync($params);
    }
}
