<?php

namespace partials;

use Extract_Image_Colors_Job;
use Growtype_Art_Crud;
use Growtype_Art_Database;
use Growtype_Art_Database_Crud;
use Exception;
use Growtype_Cron_Jobs;

class Runware_Base
{
    public function __construct()
    {
    }

    public static function api_key()
    {
        return \Growtype_Auth::credentials('runware');
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

        if (empty($generation_details) || isset($generation_details['errors'])) {
            return [
                'success' => false,
                'message' => $generation_details['errors'][0]['message'] ?? 'Something went wrong',
            ];
        }

        $response = $this->save_generations($generation_details['data'], $model_id, $params);

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
        $generating_settings = [
            "taskType" => "imageInference",
            "taskUUID" => "5315d42f-9072-41f5-9f0f-9c6a2a205aa5", // Generate a unique ID for each request
            "positivePrompt" => $params['prompt'],
            "model" => "rundiffusion:130@100", // Adjust model if necessary
            "width" => 768,
            "height" => 1024,
            "numberResults" => 1,
            "outputFormat" => "WEBP",
            "steps" => 33,
            "CFGScale" => 3,
            "scheduler" => "Euler Beta",
            "outputType" => ["URL"],
            "includeCost" => false,
        ];

        if (isset($params['model_id'])) {
            $model_details = growtype_art_get_model_details($params['model_id']);

            if ($model_details['settings']['character_style'] === 'anime') {
                $generating_settings = [
                    "taskType" => "imageInference",
                    "taskUUID" => "5315d42f-9072-41f5-9f0f-9c6a2a205aa5", // Generate a unique ID for each request
                    "positivePrompt" => $params['prompt'],
                    "model" => "runware:97@1", // Adjust model if necessary
                    "width" => 768,
                    "height" => 1024,
                    "steps" => 30,
                    "CFGScale" => 2.8,
                    "scheduler" => "Default",
                    "numberResults" => 1,
                    "outputFormat" => "WEBP",
                    "outputType" => ["URL"],
                    "includeCost" => false,
                ];
            }

            if ($model_details['settings']['core_model'] === 'bfl:2@1') {
                $generating_settings = [
                    "taskType" => "imageInference",
                    "taskUUID" => "5315d42f-9072-41f5-9f0f-9c6a2a205aa5", // Generate a unique ID for each request
                    "positivePrompt" => $params['prompt'],
                    "model" => "bfl:2@1", // Adjust model if necessary
                    "width" => 768,
                    "height" => 1024,
                    "numberResults" => 1,
                    "outputFormat" => "WEBP",
                    "outputType" => ["URL"],
                    "includeCost" => false,
                ];
            }
        }

        $token = $params['token'];

        $url = "https://api.runware.ai/v1";

        $headers = [
            "Content-Type: application/json",
            "User-Agent: PHP-cURL",
        ];

        $data = [
            [
                "taskType" => "authentication",
                "apiKey" => $token
            ],
            $generating_settings
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

        $response_decoded = json_decode($response, true);

        if (empty($response_decoded)) {
            $response_decoded = [
                'errors' => [
                    [
                        'message' => $response
                    ]
                ]
            ];
        }

        return $response_decoded;
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
            $image['url'] = $generation['imageURL'];
            $image['meta_details'] = [
                [
                    'key' => 'generation_id',
                    'value' => $params['generation_id']
                ],
                [
                    'key' => 'provider',
                    'value' => Growtype_Art_Crud::RUNWARE_KEY
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

