<?php

namespace partials;

use Extract_Image_Colors_Job;
use Growtype_Art_Crud;
use Growtype_Art_Database;
use Growtype_Art_Database_Crud;
use Exception;
use Growtype_Cron_Jobs;

class Piclumen_Base
{
    public function __construct()
    {
    }

    public static function api_key()
    {
        return \Growtype_Auth::credentials('piclumen');
    }

    public function generate_model_image($model_id = null)
    {
        $model = growtype_art_get_model_details($model_id);

        if (!isset($model['prompt']) || empty($model['prompt'])) {
            error_log('Empty prompt. Model ' . $model_id);

            return [
                'success' => false,
                'message' => sprintf('Empty prompt. Model %s.', $model_id),
            ];
        }

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

        if (!isset($generation_details['data'])) {
            if (isset($_GET['page']) && !empty($_GET['page'])) {
                Growtype_Cron_Jobs::create_if_not_exists('generate-model', json_encode([
                    'provider' => Growtype_Art_Crud::PICLUMEN_KEY,
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
            'provider' => Growtype_Art_Crud::PICLUMEN_KEY,
            'api_group_key' => $api_group_key,
            'amount' => 1,
            'model_id' => $model_id,
            'generation_id' => $generation_details['data']['markId']
        ]), 30);

        return [
            'success' => true,
            'generations' => [
                [
                    'generation_id' => $generation_details['data']['markId']
                ]
            ],
            'message' => sprintf('Successfully generated. Params: %s', $params_urlencoded)
        ];
    }

    public function get_access_token($api_group_key)
    {
        return self::api_key()[$api_group_key]['token'] ?? '';
    }

    public function generate_image_init($params)
    {
        $token = $params['token'];

        $url = "https://api.piclumen.com/api/gen/create";

// Request headers
        $headers = [
            "authorization: $token",
            "platform: Web",
            "Content-Type: application/json",
            "User-Agent: PostmanRuntime/7.43.0",
            "Accept: */*",
            "Cache-Control: no-cache",
            "Postman-Token: 70ff0a52-cc4b-422f-8692-3895eb81d0a1",
            "Accept-Encoding: gzip, deflate, br",
            ":path: /api/gen/create",
            ":method: POST",
            ":authority: api.piclumen.com",
            ":scheme: https"
        ];

// Request body
        $data = [
            "model_id" => "23887bba-507e-4249-a0e3-6951e4027f2b",
            "prompt" => $params['prompt'],
            "negative_prompt" => "",
            "resolution" => [
                "width" => 768,
                "height" => 1024,
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
//            "multi_img2img_info" => [
//                "style_list" => [
//                    [
//                        'img_url' => 'https://content.nsfwspace.com/app/uploads/growtype-ai-uploads/models/6d3cf12137d8a969435c9a6922b1b303/65a4af279b5f5_3328277961554970979_64399143371.webp',
//                        'style' => 'characterRefer',
//                        'weight' => 0.9,
//                    ]
//                ]
//            ],
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

        $generations = $this->get_generations($token, $generations_ids);

        if (isset($generations['data']) && !empty($generations['data'])) {

            error_log('retrieve_generations: ' . json_encode($generations));

            $filtered_prompts = array_filter(array_pluck($generations['data'], 'promptId'), function ($value) {
                return !is_null($value);
            });

            error_log('filtered_prompts: ' . json_encode($filtered_prompts));

            if (empty($filtered_prompts)) {
                throw new Exception('Not yet generated: ' . json_encode($generations));
            }

            /**
             * Save generation
             */
            $saved_generations = $this->save_generations($generations['data'], $model_id);

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

                error_log('saved_generation: ' . json_encode($saved_generation));
                $this->delete_external_generation($token, $saved_generation['promptId'], $saved_generation['imgName']);
            }
        }

        return $generations;
    }

    function save_generations($generations, $model_id)
    {
        $saved_generations = [];
        foreach ($generations as $generation) {

            if (empty($generation['img_urls'])) {
                continue;
            }

            foreach ($generation['img_urls'] as $img_url) {

                if (isset($img_url['sensitive']) && $img_url['sensitive'] === 'NSFW') {
                    $saved_generations[] = [
                        'success' => false,
                        'promptId' => $generation['promptId'],
                        'imgName' => $img_url['imgName'],
                    ];

                    error_log(sprintf('piclumen generator. Sensitive image. %s', print_r($img_url, true)));
                    continue;
                }

                $model = growtype_art_get_model_details($model_id);

                $image_folder = $model['image_folder'];
                $image_location = growtype_art_get_images_saving_location();

                $image['imageWidth'] = $img_url['realWidth'];
                $image['imageHeight'] = $img_url['realHeight'];
                $image['folder'] = $image_folder;
                $image['location'] = $image_location;
                $image['url'] = $img_url['imgUrl'];

                $image['meta_details'] = [
                    [
                        'key' => 'generation_id',
                        'value' => $generation['markId']
                    ],
                    [
                        'key' => 'provider',
                        'value' => Growtype_Art_Crud::PICLUMEN_KEY
                    ]
                ];

                foreach ($generation as $key => $value) {
                    if (!in_array($key, ['realWidth', 'realHeight', 'status', 'index', 'info', 'markId'])) {
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
//                Extract_Image_Colors_Job::update_image_colors_groups($saved_image['id']);

                /**
                 * Compress image
                 */
                growtype_art_compress_existing_image($saved_image['id']);

                sleep(2);

//                d([
//                    $saved_image,
//                    $image,
//                    $model,
//                    $generations,
//                    $model_id,
//                    $image
//                ]);

                $saved_generations[] = [
                    'promptId' => $generation['promptId'],
                    'imgName' => $img_url['imgName'],
                ];
            }

            do_action('growtype_art_model_update', $model_id);
        }

        return $saved_generations;
    }

    function get_generations($token, $generations_ids)
    {
        $url = "https://api.piclumen.com/api/task/batch-process-task";

// Request headers
        $headers = [
            "authorization: $token",
            "platform: Web",
            "Content-Type: application/json",
            "User-Agent: PostmanRuntime/7.43.0",
            "Accept: */*",
            "Cache-Control: no-cache",
            "Postman-Token: dbf2e2a8-5688-43f6-b81e-ea22b018025f",
            "Host: api.piclumen.com",
            "Accept-Encoding: gzip, deflate, br",
            "Connection: keep-alive"
        ];

// Request body
        $data = $generations_ids;

// Initialize cURL session
        $ch = curl_init($url);

// Set cURL options
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

// Output results
        return json_decode($response, true);
    }

    public function delete_external_generation($token, $prompt_id, $img_name)
    {
        $url = "https://api.piclumen.com/api/img/delete";

// Request headers
        $headers = [
            "authorization: $token",
            "platform: Web",
            "User-Agent: PostmanRuntime/7.43.0",
            "Accept: */*",
            "Cache-Control: no-cache",
            "Content-Type: multipart/form-data"
        ];

// Request body
        $fields = [
            "promptId" => $prompt_id,
            "imgName" => $img_name
        ];

// Initialize cURL session
        $ch = curl_init($url);

// Set cURL options
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

//        error_log('delete_external_generation: ' . print_r([$fields, $response], true));

// Output results
        return json_decode($response, true);
    }
}

