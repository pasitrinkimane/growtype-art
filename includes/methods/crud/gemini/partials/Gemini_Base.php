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

class Gemini_Base extends Growtype_Art_Generator_Base
{
    public function __construct()
    {
    }

    public function get_provider_key()
    {
        return Growtype_Art_Crud::GEMINI_KEY;
    }

    public function get_models()
    {
        return [
            'gemini-2.5-flash-image-preview' => [
                'is_nsfw' => false,
            ],
        ];
    }

    public function generate_image_init($params)
    {
        $model_id = $params['model_id'] ?? null;
        $model = (!empty($model_id)) ? growtype_art_get_model_details($model_id) : [];
        $model_images = (!empty($model_id)) ? (growtype_art_get_model_images_grouped($model_id, 200)['original'] ?? []) : [];
        
        $formatted_prompt = isset($params['prompt']) && !empty($params['prompt']) ? $params['prompt'] : '';
        
        if (empty($formatted_prompt) && !empty($model)) {
             $formatted_prompt = growtype_art_model_format_prompt($model['prompt'], $model_id);
        }

        $image_input = [];
        if (isset($params['reference_image_urls']) && !empty($params['reference_image_urls'])) {
            $image_input = $params['reference_image_urls'];
        }

        if (empty($image_input) && !empty($model_images)) {
            foreach ($model_images as $model_image) {
                if (isset($model_image['settings']['is_cover']) && $model_image['settings']['is_cover']) {
                    $image_url = growtype_art_get_image_url($model_image['id']);
                    $image_url = growtype_art_image_get_alternative_format($image_url, 'jpg', true);
                    array_push($image_input, $image_url);
                }
            }

            if (empty($image_input)) {
                $model_image = $model_images[0];
                $image_url = growtype_art_get_image_url($model_image['id']);
                $image_url = growtype_art_image_get_alternative_format($image_url, 'jpg', true);
                array_push($image_input, $image_url);
            }
        }

        $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image-preview:generateContent?key=' . $params['token'];

        $body = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $formatted_prompt],
                    ]
                ]
            ],
            "generationConfig" => [
                "responseModalities" => ["IMAGE"]
            ]
        ];

        foreach ($image_input as $image_input_single) {
            $base64Image = base64_encode(file_get_contents($image_input_single));

            $body['contents'][0]['parts'][] = [
                "inlineData" => [
                    "mimeType" => "image/jpeg",
                    "data" => $base64Image
                ]
            ];
        }

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            return [
                'errors' => [
                    [
                        'message' => 'API request failed: ' . $response->get_error_message(),
                    ]
                ]
            ];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);

        $imageBase64 = '';
        if (
            isset($data['candidates'][0]['content']['parts'][0]['inlineData']['data'])
        ) {
            $imageBase64 = $data['candidates'][0]['content']['parts'][0]['inlineData']['data'];
        }

        if (empty($imageBase64)) {
            $finishReason = $data['candidates'][0]['finishReason'] ?? 'Unknown';
            $message = $finishReason;

            if ($finishReason === 'PROHIBITED_CONTENT') {
                $message = 'Reason: Prohibited content.';
            }

            return [
                'errors' => [
                    [
                        'message' => 'Generating failed. Prompt: ' . $params['prompt'] . '. ' . $message,
                    ]
                ]
            ];
        }

        return [
            'imageBase64' => $imageBase64
        ];
    }
}
