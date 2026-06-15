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

class Runware_Base extends Growtype_Art_Generator_Base
{
    public function __construct()
    {
    }

    public function get_provider_key()
    {
        return Growtype_Art_Crud::RUNWARE_KEY;
    }

    public function get_models()
    {
        return [
            'rundiffusion:130@100' => [
                'is_nsfw'  => true,
                'cost_usd' => 0.002,
            ],
            'runware:97@1' => [
                'is_nsfw'  => true,
                'cost_usd' => 0.002,
            ],
            'bfl:2@1' => [
                'is_nsfw'  => true,
                'cost_usd' => 0.004,
            ],
        ];
    }

    public function generate_image_init($params)
    {
        $generating_settings = [
            "taskType" => "imageInference",
            "taskUUID" => "5315d42f-9072-41f5-9f0f-9c6a2a205aa5", // Generate a unique ID for each request
            "positivePrompt" => $params['prompt'],
            "model" => "rundiffusion:130@100", // Adjust model if necessary
            "width" => $params['width'] ?? self::DEFAULT_IMAGE_DIMENSIONS['width'],
            "height" => $params['height'] ?? self::DEFAULT_IMAGE_DIMENSIONS['height'],
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
                    "width" => $params['width'] ?? self::DEFAULT_IMAGE_DIMENSIONS['width'],
                    "height" => $params['height'] ?? self::DEFAULT_IMAGE_DIMENSIONS['height'],
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
                    "width" => $params['width'] ?? self::DEFAULT_IMAGE_DIMENSIONS['width'],
                    "height" => $params['height'] ?? self::DEFAULT_IMAGE_DIMENSIONS['height'],
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
            'Content-Type' => 'application/json',
        ];

        $data = [
            [
                'taskType' => 'authentication',
                'apiKey'   => $token,
            ],
            $generating_settings,
        ];

        $wp_response = wp_remote_post($url, [
            'headers' => $headers,
            'body'    => json_encode($data),
            'timeout' => 60,
        ]);

        if (is_wp_error($wp_response)) {
            return [
                'errors' => [['message' => $wp_response->get_error_message()]],
            ];
        }

        $response         = wp_remote_retrieve_body($wp_response);
        $response_decoded = json_decode($response, true);

        if (empty($response_decoded)) {
            $response_decoded = [
                'errors' => [['message' => $response]],
            ];
        }

        if (is_array($response_decoded)) {
            $response_decoded['_request_payload'] = array_merge(
                ['_url' => $url],
                $generating_settings
            );
        }

        return $response_decoded;
    }
}

