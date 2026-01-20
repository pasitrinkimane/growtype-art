<?php

namespace partials;

require GROWTYPE_ART_PATH . '/vendor/autoload.php';
require_once GROWTYPE_ART_PATH . '/includes/methods/crud/Growtype_Art_Generator_Base.php';

use Growtype_Art_Crud;
use Growtype_Art_Generator_Base;
use Exception;

class Perchance_Base extends Growtype_Art_Generator_Base
{
    public function get_provider_key()
    {
        return Growtype_Art_Crud::PERCHANCE_KEY;
    }

    public function api_key()
    {
        return [
            'default' => [
                'api_key' => 'not-needed'
            ]
        ];
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

        $result = json_decode($response, true);

        if ($httpCode == 200 && !empty($result)) {
             return [
                 'success' => true,
                 'data' => [$result]
             ];
        }

        return [
            'success' => false,
            'message' => "API request failed with HTTP code: $httpCode",
            'errors' => [['message' => $response]]
        ];
    }
}

