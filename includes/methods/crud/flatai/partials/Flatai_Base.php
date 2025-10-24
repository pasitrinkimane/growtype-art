<?php

namespace partials;

use Extract_Image_Colors_Job;
use Growtype_Art_Crud;
use Growtype_Art_Database;
use Growtype_Art_Database_Crud;
use Exception;
use Growtype_Cron_Jobs;

class Flatai_Base
{

    public function generate_model_image($model_id, $params = [])
    {
        if (!isset($params['prompt'])) {
            $model = growtype_art_get_model_details($model_id);
            $formatted_prompt = growtype_art_model_format_prompt($model['prompt'], $model_id);

            $params['prompt'] = $formatted_prompt;
        }

        $params['generation_id'] = wp_generate_password(52, false);

        $generation_details = $this->generate_image_init($params);

        if ($generation_details['success'] === false) {
            return [
                'success' => false,
                'message' => $generation_details['data']['message'],
            ];
        }

//        error_log('generation_details: ' . json_encode($generation_details));

        if (!isset($generation_details['data']['images'])) {
            if (isset($_GET['page']) && !empty($_GET['page'])) {
                Growtype_Cron_Jobs::create('generate-model', json_encode([
                    'provider' => Growtype_Art_Crud::FLATAI_KEY,
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

        $response = $this->save_generations($generation_details['data']['images'], $model_id, $params);

        return [
            'success' => true,
            'generations' => $response
        ];
    }

    function save_generations($generations, $model_id, $params)
    {
        $saved_generations = [];
        foreach ($generations as $generation) {

//            error_log(sprintf('Generation %s', print_r($generation, true)));

            $model = growtype_art_get_model_details($model_id);

            $image_folder = $model['image_folder'];
            $image_location = growtype_art_get_images_saving_location();

            $image['folder'] = $image_folder;
            $image['location'] = $image_location;
            $image['url'] = $generation;
            $image['meta_details'] = [
                [
                    'key' => 'generation_id',
                    'value' => $params['generation_id']
                ],
                [
                    'key' => 'provider',
                    'value' => Growtype_Art_Crud::FLATAI_KEY
                ],
                [
                    'key' => 'prompt',
                    'value' => $params['prompt']
                ]
            ];

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
             * Compress image
             */
            growtype_art_compress_existing_image($saved_image['id']);

            sleep(2);

            $saved_generations[] = [
                'image_id' => $saved_image['id'],
                'generation_id' => $params['generation_id'],
            ];

            do_action('growtype_art_model_update', $model_id);
        }

        return $saved_generations;
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

                    return json_decode($decoded, true);
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
            'error' => true,
            'message' => 'All proxy attempts failed. Please try again later.',
        ];
    }
}

