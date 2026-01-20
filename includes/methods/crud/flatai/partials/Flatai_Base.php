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

class Flatai_Base extends Growtype_Art_Generator_Base
{
    public function get_provider_key()
    {
        return Growtype_Art_Crud::FLATAI_KEY;
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
        $url = 'https://flatai.org/ai-image-generator-free-no-signup/';
        $html = file_get_contents($url);

        $nonce = null;
        if (preg_match('/"nonce":"([a-f0-9]{10})"/', $html, $matches)) {
            $nonce = $matches[1];
        }

        $url = 'https://flatai.org/wp-admin/admin-ajax.php';

        $data = [
            'action' => 'ai_generate_image',
            'nonce' => $nonce,
            'prompt' => $params['prompt'],
            'aspect_ratio' => '9:16',
//            'cf_turnstile'
        ];

        $boundary = uniqid();
        $delimiter = '--------------------------' . $boundary;
        $postData = '';
        foreach ($data as $key => $value) {
            $postData .= "--$delimiter\r\n";
            $postData .= "Content-Disposition: form-data; name=\"$key\"\r\n\r\n$value\r\n";
        }
        $postData .= "--$delimiter--\r\n";

        $userAgents = [
            "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36",
            "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Safari/605.1.15",
            "Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148",
            "Mozilla/5.0 (Linux; Android 10; SM-G975F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/111.0.0.0 Mobile Safari/537.36",
        ];

        $acceptLanguages = [
            "en-US,en;q=0.9",
            "fr-FR,fr;q=0.8",
            "de-DE,de;q=0.7",
            "es-ES,es;q=0.6",
        ];

        $proxies = [
            "200.174.198.86:8888",
            "112.109.18.164:8080",
            "201.71.137.90:5128",
        ];

        $maxRetries = count($proxies); // Retry up to the number of proxies
        $attempt = 0;

        while ($attempt < $maxRetries) {
            $randomUserAgent = $userAgents[array_rand($userAgents)];
            $randomAcceptLanguage = $acceptLanguages[array_rand($acceptLanguages)];
            $randomProxy = $proxies[$attempt];
            $useProxy = rand(0, 1) === 1;

            $headers = [
                "Content-Type: multipart/form-data; boundary=$delimiter",
                "Accept: */*",
                "Accept-Encoding: gzip, deflate, br, zstd",
                "Accept-Language: $randomAcceptLanguage",
                "Cache-Control: no-cache",
                "Origin: https://flatai.org",
                "Referer: https://flatai.org/ai-image-generator-free-no-signup/?utm_source=chatgpt.com",
                "User-Agent: $randomUserAgent",
                "X-Requested-With: XMLHttpRequest",
            ];

            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20); // Set timeout to 10 seconds

            // Use proxy
            if ($useProxy) {
                curl_setopt($ch, CURLOPT_PROXY, $randomProxy);
                error_log("Using proxy: $randomProxy");
            } else {
                error_log("No proxy used for this request.");
            }

            try {
                $response = curl_exec($ch);

                if (curl_errno($ch)) {
                    throw new Exception('CURL Error: ' . curl_error($ch));
                }

                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($httpCode >= 400) {
                    throw new Exception('HTTP Error: ' . $httpCode);
                }

                curl_close($ch);

                $decoded = @gzdecode($response);

                // If the response is valid, return the decoded data
                if ($decoded !== false) {
                    error_log(sprintf('Successfully decoded %s', $randomProxy));

                    $json_decoded = json_decode($decoded, true);
                    // Standardize result for base class
                    if (isset($json_decoded['data']['images'])) {
                        return [
                            'success' => true,
                            'generations' => array_map(function($img) { return ['url' => $img]; }, $json_decoded['data']['images'])
                        ];
                    }
                     return [
                        'success' => false,
                        'message' => $json_decoded['data']['message'] ?? 'Unknown error'
                    ];

                } else {
                    throw new Exception('Failed to decode response');
                }
            } catch (Exception $e) {
                // Log the error and try the next proxy
                error_log(sprintf('Proxy %s. Error: %s', $randomProxy, $e->getMessage()));
                curl_close($ch);
                $attempt++; // Move to the next proxy
            }
        }

        // If all proxies fail, return an error
        return [
            'success' => false,
            'message' => 'All proxy attempts failed. Please try again later.',
        ];
    }
}

