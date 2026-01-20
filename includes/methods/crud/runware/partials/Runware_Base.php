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
                'is_nsfw' => true,
            ],
            'runware:97@1' => [
                'is_nsfw' => true,
            ],
            'bfl:2@1' => [
                'is_nsfw' => true,
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
}

