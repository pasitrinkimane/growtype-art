<?php

namespace partials;

require GROWTYPE_ART_PATH . '/vendor/autoload.php';

use Cloudinary_Crud;
use Exception;
use Extract_Image_Colors_Job;
use Growtype_Art_Crud;
use Growtype_Art_Database;
use Growtype_Art_Database_Crud;
use Growtype_Cron_Jobs;

class Leonardoai_Base
{
    const MODELS_FOLDER_NAME = 'models';

    public static function user_credentials($user_nr = null)
    {
        $creds = [
            '1' => [
                'cookie' => get_option('growtype_art_leonardo_cookie'),
                'user_id' => get_option('growtype_art_leonardo_user_id'),
                'id_token' => get_option('growtype_art_leonardo_id_token')
            ],
            '2' => [
                'cookie' => get_option('growtype_art_leonardo_cookie_2'),
                'user_id' => get_option('growtype_art_leonardo_user_id_2'),
                'id_token' => get_option('growtype_art_leonardo_id_token_2')
            ],
            '3' => [
                'cookie' => get_option('growtype_art_leonardo_cookie_3'),
                'user_id' => get_option('growtype_art_leonardo_user_id_3'),
                'id_token' => get_option('growtype_art_leonardo_id_token_3'),
                'username' => get_option('growtype_art_leonardo_username_3'),
                'password' => get_option('growtype_art_leonardo_password_3'),
            ],
            '4' => [
                'cookie' => get_option('growtype_art_leonardo_cookie_4'),
                'user_id' => get_option('growtype_art_leonardo_user_id_4'),
                'id_token' => get_option('growtype_art_leonardo_id_token_4')
            ],
            '5' => [
                'cookie' => get_option('growtype_art_leonardo_cookie_5'),
                'user_id' => get_option('growtype_art_leonardo_user_id_5'),
                'id_token' => get_option('growtype_art_leonardo_id_token_5')
            ],
            '6' => [
                'cookie' => get_option('growtype_art_leonardo_cookie_6'),
                'user_id' => get_option('growtype_art_leonardo_user_id_6'),
                'id_token' => get_option('growtype_art_leonardo_id_token_6')
            ],
            '7' => [
                'cookie' => get_option('growtype_art_leonardo_cookie_7'),
                'user_id' => get_option('growtype_art_leonardo_user_id_7'),
                'id_token' => get_option('growtype_art_leonardo_id_token_7')
            ],
            '8' => [
                'cookie' => get_option('growtype_art_leonardo_cookie_8'),
                'user_id' => get_option('growtype_art_leonardo_user_id_8'),
                'id_token' => get_option('growtype_art_leonardo_id_token_8')
            ],
            '9' => [
                'cookie' => get_option('growtype_art_leonardo_cookie_9'),
                'user_id' => get_option('growtype_art_leonardo_user_id_9'),
                'id_token' => get_option('growtype_art_leonardo_id_token_9')
            ],
            '10' => [
                'cookie' => get_option('growtype_art_leonardo_cookie_10'),
                'user_id' => get_option('growtype_art_leonardo_user_id_10'),
                'id_token' => get_option('growtype_art_leonardo_id_token_10')
            ],
            '11' => [
                'cookie' => get_option('growtype_art_leonardo_cookie_11'),
                'user_id' => get_option('growtype_art_leonardo_user_id_11'),
                'id_token' => get_option('growtype_art_leonardo_id_token_11')
            ],
            '12' => [
                'cookie' => get_option('growtype_art_leonardo_cookie_12'),
                'user_id' => get_option('growtype_art_leonardo_user_id_12'),
                'id_token' => get_option('growtype_art_leonardo_id_token_12')
            ],
//            '13' => [
//                'cookie' => get_option('growtype_art_leonardo_cookie_13'),
//                'user_id' => get_option('growtype_art_leonardo_user_id_13'),
//                'id_token' => get_option('growtype_art_leonardo_id_token_13')
//            ]
        ];

        if (!empty($user_nr)) {
            return $creds[$user_nr];
        }

        return $creds;
    }

    function get_user_credentials($user_nr)
    {
        if (empty($user_nr)) {
            $user_nr = 1;
        }

        return self::user_credentials()[$user_nr] ?? self::user_credentials()[1];
    }

    public function generate_model_image($model_id = null, $params = [])
    {
        $generation_details = $this->generate_model_image_init($model_id, $params);

        if (empty($generation_details) || $generation_details['success'] === false) {
            return $generation_details;
        }

        foreach ($generation_details['generations'] as $generation) {
            Growtype_Cron_Jobs::create_if_not_exists('retrieve-model', json_encode([
                'provider' => Growtype_Art_Crud::LEONARDOAI_KEY,
                'user_nr' => $generation['user_nr'],
                'amount' => 1,
                'model_id' => $model_id,
                'generation_id' => $generation['generation_id'],
                'image_prompt' => $generation['image_prompt'],
                'post_id' => $generation['post_id'] ?? '',
            ]), 60);
        }

        return $generation_details;
    }

    public function generate_model_image_init($model_id, $params = [])
    {
        $leonardoai_settings_user_nr = growtype_art_get_model_single_setting($model_id, 'leonardoai_settings_user_nr');
        $leonardoai_settings_user_nr = $leonardoai_settings_user_nr['meta_value'] ?? '';

        if (!empty($leonardoai_settings_user_nr)) {
            $users = [$leonardoai_settings_user_nr];
        } else {
            $available_credentials = $this->user_credentials();
            $users = array_keys($available_credentials);
            shuffle($users);
        }

        $generation_id = null;
        $error_messages = [];
        foreach ($users as $user_nr) {
            $token = $this->retrieve_access_token($user_nr);

            if (empty($token)) {
                error_log(sprintf('Leonardo.ai. Empty token. User nr: %s', $user_nr));
                continue;
            }

            $credentials = [
                'token' => $token,
                'user_nr' => $user_nr
            ];

            try {
                $image_generating = $this->init_image_generating($credentials, $model_id, $params);
                $generation_id = $image_generating['generation_id'];
            } catch (Exception $e) {
                $error_messages[] = [
                    'message' => $e->getMessage(),
                    'user_nr' => $user_nr
                ];

                if (strpos($e->getMessage(), 'not enough tokens') !== false) {
                    continue;
                }
            }

            if (!empty($generation_id)) {
                break;
            }
        }

        if (empty($generation_id)) {
            if (php_sapi_name() === 'cli' || defined('STDIN')) {
                throw new Exception(print_r($error_messages, true));
            } else {
                error_log('generate_model_image_init error: ' . json_encode($error_messages));

                return [
                    'success' => false,
                    'message' => json_encode($error_messages),
                ];
            }
        }

        return [
            'success' => true,
            'generations' => [
                [
                    'generation_id' => $generation_id,
                    'image_prompt' => isset($image_generating['image_prompt']) ? $image_generating['image_prompt'] : null,
                    'user_nr' => $user_nr,
                    'post_id' => isset($image_generating['post_id']) ? $image_generating['post_id'] : null,
                ]
            ]
        ];
    }

    public function retrieve_models($amount, $model_id = null, $user_nr = null)
    {
        $token = $this->get_access_token($user_nr);

        $generations = $this->get_generations($token, $amount, $user_nr);

        if (empty($generations)) {
            return null;
        }

        $this->save_generations($generations, $model_id);

        $this->delete_external_generations($token, $generations);

        return $generations;
    }

    public function retrieve_generations($model_id, $generation_id, $args = [])
    {
        $user_nr = $args['user_nr'];

        $token = $this->get_access_token($user_nr);

        $generations = $this->get_generations($token, 20, $user_nr);

//        error_log('Retrieve_Model_Job generations: ' . json_encode($generations) . ' model_id: ' . $model_id . ' generation_id: ' . $generation_id . ' args: ' . json_encode($args) . ' token: ' . $token);

        if (empty($generations)) {
            throw new Exception('Not yet generated. Token: ' . $token);
        }

        $single_generation = '';
        foreach ($generations as $generation) {
            if ($generation['id'] === $generation_id) {
                $single_generation = $generation;
                break;
            }
        }

        if (empty($single_generation)) {
            throw new Exception('No generation');
        }

        if (isset($single_generation['status']) && $single_generation['status'] === 'PENDING') {
            throw new Exception('PENDING generation');
        }

        $generations = [$single_generation];

//        error_log('Retrieve_Model_Job generations: ' . json_encode($generations));

        if (!empty($generations)) {
            /**
             * Save generation
             */
            $this->save_generations($generations, $model_id, $args);

            /**
             * Delete generation from Leonardo.ai
             */
            $this->delete_external_generations($token, $generations);
        }

        return $generations;
    }

    /**
     * @param $user_nr
     * @return mixed
     */
    public function get_access_token($user_nr = null)
    {
        $token = $this->retrieve_access_token($user_nr);

        if (empty($token)) {
            throw new Exception('No token user_nr: ' . $user_nr);
        }

        return $token;
    }

    /**
     * @param $cookie
     * @return mixed|null
     */
    function get_login_details($cookie)
    {
        $url = 'https://app.leonardo.ai/api/auth/me';

        $response = wp_remote_post($url, array (
            'headers' => array (
                'cookie' => $cookie,
            ),
            'method' => 'GET',
            'data_format' => 'body',
        ));

        $body = wp_remote_retrieve_body($response);

        $response_data = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return $response_data;
    }

    function retrieve_access_token($user_nr = 1)
    {
        $cookie = $this->get_user_credentials($user_nr)['cookie'];

        $url = 'https://app.leonardo.ai/api/auth/session';

        $response = wp_remote_post($url, array (
            'headers' => array (
                'cookie' => $cookie,
            ),
            'method' => 'GET',
            'data_format' => 'body',
        ));

        $body = wp_remote_retrieve_body($response);

        $response_data = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return isset($response_data['accessToken']) ? $response_data['accessToken'] : null;
    }

    function return_generation($token, $generation_id)
    {
        $generations = $this->get_generations($token);

        foreach ($generations as $generation) {
            if ($generation['id'] === $generation_id && isset($generation['generated_images']) && !empty($generation['generated_images'])) {
                return $generation;
            }
        }

        return null;
    }

    public function retrieve_generation($token, $generation_id)
    {
        $timer = Loop::addPeriodicTimer(7, function () use ($token, $generation_id) {
            $latest_generation = $this->return_generation($token, $generation_id);

            if (!empty($latest_generation)) {
                Loop::stop();
                d($latest_generation);
            }
        });

        Loop::addTimer(28, function () use ($timer) {
            Loop::cancelTimer($timer);
        });
    }

    public function init_image_generating($credentials, $model_id = null, $custom_args = null)
    {
        $url = 'https://api.leonardo.ai/v1/graphql';

        if (!empty($model_id)) {
            $model_details = growtype_art_get_model_details($model_id);

            if (empty($model_details)) {
                throw new Exception('No model details');
            }

            $leonardo_magic = isset($model_details['settings']['leonardo_magic']) && !$model_details['settings']['leonardo_magic'] ? false : true;
            $alchemy = isset($model_details['settings']['alchemy']) && $model_details['settings']['alchemy'] ? true : false;

            $default_args = [
                'prompt' => $model_details['prompt'],
                'negative_prompt' => $model_details['negative_prompt'],
                'nsfw' => true,
                'num_images' => 1,
                'width' => (int)$model_details['settings']['image_width'],
                'height' => (int)$model_details['settings']['image_height'],
                'num_inference_steps' => floatval($model_details['settings']['num_inference_steps']),
                'guidance_scale' => floatval($model_details['settings']['guidance_scale']),
                'init_strength' => floatval($model_details['settings']['init_strength']),
                'sd_version' => $model_details['settings']['sd_version'],
                'modelId' => $model_details['settings']['model_id'],
                'presetStyle' => $model_details['settings']['preset_style'],
                'scheduler' => $model_details['settings']['scheduler'],
                'leonardoMagic' => $leonardo_magic,
                'public' => true,
                'tiling' => isset($model_details['settings']['tiling']) && !$model_details['settings']['tiling'] ? false : true,
                'alchemy' => $alchemy,
                'weighting' => 1,
                'poseToImage' => isset($model_details['settings']['pose_to_image']) && $model_details['settings']['pose_to_image'] ? true : false,
                'poseToImageType' => isset($model_details['settings']['pose_to_image_type']) && !empty($model_details['settings']['pose_to_image_type']) ? $model_details['settings']['pose_to_image_type'] : 'POSE',
                'highResolution' => $leonardo_magic ? true : false,
                'highContrast' => isset($model_details['settings']['high_contrast']) && $model_details['settings']['high_contrast'] ? true : false,
                'expandedDomain' => true,
                'contrastRatio' => 0.5,
                'photoReal' => isset($model_details['settings']['photoReal']) && $model_details['settings']['photoReal'] ? true : false,
                'controlnets' => [],
                'elements' => [],
            ];

            if (isset($default_args['leonardoMagic']) && $default_args['leonardoMagic']) {
                $default_args['leonardoMagicVersion'] = 'v2';
            }

            if (isset($model_details['settings']['image_prompts']) && !empty($model_details['settings']['image_prompts'])) {
                $default_args['imagePrompts'] = explode(',', $model_details['settings']['image_prompts']);
            }

            if (isset($model_details['settings']['image_prompt_weight']) && !empty($model_details['settings']['image_prompt_weight'])) {
                $default_args['imagePromptWeight'] = floatval($model_details['settings']['image_prompt_weight']);
            }

            if (isset($model_details['settings']['init_generation_image_id']) && !empty($model_details['settings']['init_generation_image_id'])) {
                $default_args['init_image_id'] = $model_details['settings']['init_generation_image_id'];
            }

            if (!empty($custom_args)) {
                $default_args = array_merge($default_args, $custom_args);
            }

            $default_args['prompt'] = growtype_art_model_format_prompt($default_args['prompt'], $model_id);

            /**
             * Check if data should be collected from posts
             */
            if (isset($model_details['settings']['post_type_to_collect_data_from']) && !empty($model_details['settings']['post_type_to_collect_data_from'])) {
                $posts = get_posts([
                    'post_type' => $model_details['settings']['post_type_to_collect_data_from'],
                    'post_status' => ['draft', 'publish'],
                    'numberposts' => -1
                ]);

                $post_id = null;
                $post_exists = false;
                foreach ($posts as $post) {
                    $images_ids = get_post_meta($post->ID, 'growtype_art_images_ids', true);
                    $images_ids = !empty($images_ids) ? json_decode($images_ids, true) : [];

                    if (empty($images_ids) || count($images_ids) < 3) {
                        $post_id = $post->ID;

                        $post_title = explode('-', $post->post_title);
                        $post_title = $post_title[0];
                        $default_args['prompt'] = str_replace('{post_title}', $post_title, $default_args['prompt']);
                        update_post_meta($post->ID, 'growtype_art_images_generating', true);

                        $post_exists = true;

                        break;
                    }
                }

                if (!$post_exists) {
                    $posts = array_shuffle($posts);
                    $post = $posts[0];

                    $post_title = explode('-', $post->post_title);
                    $post_title = $post_title[0];
                    $default_args['prompt'] = str_replace('{post_title}', $post_title, $default_args['prompt']);
                    update_post_meta($post->ID, 'growtype_art_images_generating', true);
                }
            }

            $parameters = [
                'operationName' => 'CreateSDGenerationJob',
                'variables' => [
                    'arg1' => $default_args
                ],
                'query' => 'mutation CreateSDGenerationJob($arg1: SDGenerationInput!) { sdGenerationJob(arg1: $arg1) { generationId __typename }}'
            ];

            $parameters = json_encode($parameters);
        } else {
            $parameters = '{
   "operationName":"CreateSDGenerationJob",
   "variables":{
      "arg1":{
         "prompt":"Sunset mountain",
         "negative_prompt":"Palm tree, beach, Sea,",
         "nsfw":true,
         "num_images":1,
         "width":512,
         "height":512,
         "num_inference_steps":30,
         "guidance_scale":7,
         "init_strength":0.5,
         "sd_version":"v1_5",
         "modelId":"fc42c4b3-1b19-44b7-b9fa-4d3d018af689",
         "presetStyle":"NONE",
         "scheduler":"EULER_DISCRETE",
         "leonardoMagic":false,
         "public":false,
         "tiling":false
      }
   },
   "query":"mutation CreateSDGenerationJob($arg1: SDGenerationInput!) {\n  sdGenerationJob(arg1: $arg1) {\n    generationId\n    __typename\n  }\n}"
}';
        }

        $response = wp_remote_post($url, array (
            'headers' => array (
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer ' . $credentials['token'],
            ),
            'body' => $parameters,
            'method' => 'POST',
            'data_format' => 'body',
        ));

        $body = wp_remote_retrieve_body($response);

        $response_data = (!is_wp_error($response)) ? json_decode($body, true) : null;

        $generation_id = isset($response_data['data']['sdGenerationJob']['generationId']) ? $response_data['data']['sdGenerationJob']['generationId'] : null;

        if (empty($generation_id)) {
            $message = isset($response_data['errors'][0]['message']) ? $response_data['errors'][0]['message'] : '';

            $ignored_messages = [
                'not enough tokens',
            ];

            if ($message === 'maximum trial alchemy generations' || $message === 'FREE plan user NOT permitted to use Alchemy Generation') {
                $default_args['alchemy'] = false;
                return $this->init_image_generating($credentials, $model_id, $default_args);
            }

            if (!in_array($message, $ignored_messages)) {
                throw new Exception(json_encode($response_data));
            }
        }

        return [
            'generation_id' => $generation_id,
            'image_prompt' => $default_args['prompt'],
            'post_id' => isset($post_id) ? $post_id : null,
            'message' => $message ?? '',
        ];
    }

    function get_user_details($token, $userSub)
    {
        $url = 'https://api.leonardo.ai/v1/graphql';

        $parameters = '{
   "operationName":"GetUserDetails",
   "variables":{
      "userSub":"' . $userSub . '"
   },
   "query":"query GetUserDetails($userSub: String) {\n  users(where: {user_details: {auth0Id: {_eq: $userSub}}}) {\n    id\n    username\n    user_details {\n      auth0Email\n      plan\n      paidTokens\n      subscriptionTokens\n      subscriptionModelTokens\n      subscriptionGptTokens\n      interests\n      showNsfw\n      __typename\n    }\n    __typename\n  }\n}"
}';

        $response = wp_remote_post($url, array (
            'headers' => array (
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer ' . $token,
            ),
            'body' => $parameters,
            'method' => 'POST',
            'data_format' => 'body',
        ));

        $body = wp_remote_retrieve_body($response);

        $response_data = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return $response_data;
    }

    function get_generations($token, $amount = 10, $user_nr = null)
    {
        $url = 'https://api.leonardo.ai/v1/graphql';

        $user_id = $this->get_user_credentials($user_nr)['user_id'];

        $parameters = '{
  "operationName": "GetAIGenerationFeed",
  "variables": {
    "where": {
      "userId": {
        "_eq": "' . $user_id . '"
      },
      "canvasRequest": {
        "_eq": false
      }
    },
    "offset": 0,
    "limit":' . $amount . '
  },
  "query": "query GetAIGenerationFeed($where: generations_bool_exp = {}, $userId: uuid, $limit: Int, $offset: Int = 0) {\n  generations(\n    limit: $limit\n    offset: $offset\n    order_by: [{createdAt: desc}]\n    where: $where\n  ) {\n    alchemy\n    contrastRatio\n    highResolution\n    guidanceScale\n    inferenceSteps\n    modelId\n    scheduler\n    coreModel\n    sdVersion\n    prompt\n    negativePrompt\n    id\n    status\n    quantity\n    createdAt\n    imageHeight\n    imageWidth\n    presetStyle\n    sdVersion\n    public\n    seed\n    tiling\n    initStrength\n    imageToImage\n    highContrast\n    promptMagic\n    promptMagicVersion\n    promptMagicStrength\n    imagePromptStrength\n    expandedDomain\n    motion\n    photoReal\n    photoRealStrength\n    nsfw\n    user {\n      username\n      id\n      __typename\n    }\n    custom_model {\n      id\n      userId\n      name\n      modelHeight\n      modelWidth\n      __typename\n    }\n    init_image {\n      id\n      url\n      __typename\n    }\n    generated_images(order_by: [{url: desc}]) {\n      id\n      url\n      motionGIFURL\n      motionMP4URL\n      likeCount\n      nsfw\n      generated_image_variation_generics(order_by: [{createdAt: desc}]) {\n        url\n        status\n        createdAt\n        id\n        transformType\n        upscale_details {\n          alchemyRefinerCreative\n          alchemyRefinerStrength\n          oneClicktype\n          isOneClick\n          id\n          variationId\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    generation_elements {\n      id\n      lora {\n        akUUID\n        name\n        description\n        urlImage\n        baseModel\n        weightDefault\n        weightMin\n        weightMax\n        __typename\n      }\n      weightApplied\n      __typename\n    }\n    generation_controlnets(order_by: {controlnetOrder: asc}) {\n      id\n      weightApplied\n      controlnet_definition {\n        akUUID\n        displayName\n        displayDescription\n        controlnetType\n        __typename\n      }\n      controlnet_preprocessor_matrix {\n        id\n        preprocessorName\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}"
}';

        $response = wp_remote_post($url, array (
            'headers' => array (
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer ' . $token,
            ),
            'body' => $parameters,
            'method' => 'POST',
            'data_format' => 'body',
        ));

        $body = wp_remote_retrieve_body($response);

        $response_data = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return isset($response_data['data']['generations']) ? $response_data['data']['generations'] : null;
    }

    /**
     * @param $token
     * @param $amount
     * @param $user_nr
     * @return mixed|null
     */
    function get_feed_images($token, $args)
    {
        $url = 'https://api.leonardo.ai/v1/graphql';

//        $parameters = '{
//  "operationName": "GetFeedImages",
//  "variables": {
//    "order_by": [
//      {
//        "trendingScore": "desc"
//      },
//      {
//        "createdAt": "desc"
//      }
//    ],
//    "where": {
//      "createdAt": {
//        "_lt": "2023-10-17T06:54:35.171Z"
//      },
//      "generation": {
//        "status": {
//          "_eq": "COMPLETE"
//        },
//        "prompt": {
//          "_ilike": "%' . $args['search'] . '%"
//        },
//        "public": {
//          "_eq": true
//        },
//        "canvasRequest": {
//          "_eq": false
//        },
//        "category": {},
//        "_or": [
//          {
//            "promptMagic": {
//              "_eq": false
//            }
//          },
//          {
//            "initStrength": {
//              "_is_null": true
//            }
//          },
//          {
//            "photoReal": {
//              "_eq": false
//            }
//          }
//        ]
//      },
//      "nsfw": {
//        "_eq": true
//      }
//    },
//    "limit": 50,
//    "offset": ' . $args['offset'] . ',
//    "userId": "e5e50ec2-0190-4a75-9241-8c464261347c"
//  },
//  "query": "query GetFeedImages($where: generated_images_bool_exp, $limit: Int, $userId: uuid!, $order_by: [generated_images_order_by!] = [{createdAt: desc}], $offset: Int) {\n  generated_images(\n    where: $where\n    limit: $limit\n    order_by: $order_by\n    offset: $offset\n  ) {\n    ...FeedParts\n    __typename\n  }\n}\n\nfragment FeedParts on generated_images {\n  createdAt\n  id\n  url\n  user_liked_generated_images(limit: 1, where: {userId: {_eq: $userId}}) {\n    generatedImageId\n    __typename\n  }\n  user {\n    username\n    id\n    __typename\n  }\n  generation {\n    id\n    alchemy\n    contrastRatio\n    highResolution\n    prompt\n    negativePrompt\n    imageWidth\n    imageHeight\n    sdVersion\n    modelId\n    coreModel\n    guidanceScale\n    inferenceSteps\n    seed\n    scheduler\n    tiling\n    highContrast\n    promptMagic\n    promptMagicVersion\n    imagePromptStrength\n    custom_model {\n      id\n      name\n      userId\n      modelHeight\n      modelWidth\n      __typename\n    }\n    generation_elements {\n      id\n      lora {\n        akUUID\n        name\n        description\n        urlImage\n        baseModel\n        weightDefault\n        weightMin\n        weightMax\n        __typename\n      }\n      weightApplied\n      __typename\n    }\n    initStrength\n    category\n    public\n    nsfw\n    photoReal\n    __typename\n  }\n  generated_image_variation_generics(order_by: [{createdAt: desc}]) {\n    url\n    id\n    status\n    transformType\n    upscale_details {\n      oneClicktype\n      __typename\n    }\n    __typename\n  }\n  likeCount\n  __typename\n}"
//}';


        $parameters = '{
  "operationName": "GetFeedImages",
  "variables": {
    "order_by": [
      {
        "trendingScore": "desc"
      },
      {
        "createdAt": "desc"
      }
    ],
    "where": {
      "createdAt": {},
      "generation": {
        "status": {
          "_eq": "COMPLETE"
        },
        "public": {
          "_eq": true
        },
        "canvasRequest": {
          "_eq": false
        },
        "universalUpscaler": {
          "_is_null": true
        },
        "category": {},
        "imageToImage": {
          "_eq": false
        }
      },
      "nsfw": {
        "_eq": false
      },
      "motionMP4URL": {
        "_is_null": false
      },
      "likeCount": {
        "_gt": 2
      }
    },
    "limit": 50,
    "userId": "e5e50ec2-0190-4a75-9241-8c464261347c",
    "isLoggedIn": true,
    "offset": ' . $args['offset'] . '
  },
  "query": "query GetFeedImages($where: generated_images_bool_exp, $limit: Int, $userId: uuid, $isLoggedIn: Boolean!, $order_by: [generated_images_order_by!] = [{createdAt: desc}], $offset: Int) {\n  generated_images(\n    where: $where\n    limit: $limit\n    order_by: $order_by\n    offset: $offset\n  ) {\n    ...FeedParts\n    __typename\n  }\n}\n\nfragment FeedParts on generated_images {\n  createdAt\n  id\n  url\n  motionMP4URL\n  motionGIFURL\n  ... @include(if: $isLoggedIn) {\n    ...UserLikedGeneratedImages\n    __typename\n  }\n  user {\n    username\n    id\n    __typename\n  }\n  generation {\n    id\n    alchemy\n    contrastRatio\n    highResolution\n    prompt\n    negativePrompt\n    imageWidth\n    imageHeight\n    sdVersion\n    modelId\n    coreModel\n    guidanceScale\n    inferenceSteps\n    seed\n    scheduler\n    tiling\n    highContrast\n    promptMagic\n    promptMagicVersion\n    imagePromptStrength\n    custom_model {\n      id\n      name\n      userId\n      modelHeight\n      modelWidth\n      __typename\n    }\n    generation_elements {\n      id\n      lora {\n        akUUID\n        name\n        description\n        urlImage\n        baseModel\n        weightDefault\n        weightMin\n        weightMax\n        __typename\n      }\n      weightApplied\n      __typename\n    }\n    generation_controlnets(order_by: {controlnetOrder: asc}) {\n      id\n      weightApplied\n      controlnet_definition {\n        akUUID\n        displayName\n        displayDescription\n        controlnetType\n        __typename\n      }\n      controlnet_preprocessor_matrix {\n        id\n        preprocessorName\n        __typename\n      }\n      __typename\n    }\n    initStrength\n    category\n    public\n    nsfw\n    photoReal\n    imageToImage\n    __typename\n  }\n  generated_image_variation_generics(order_by: [{createdAt: desc}]) {\n    url\n    id\n    status\n    transformType\n    upscale_details {\n      id\n      alchemyRefinerCreative\n      alchemyRefinerStrength\n      oneClicktype\n      __typename\n    }\n    __typename\n  }\n  likeCount\n  __typename\n}\n\nfragment UserLikedGeneratedImages on generated_images {\n  user_liked_generated_images(limit: 1, where: {userId: {_eq: $userId}}) {\n    generatedImageId\n    __typename\n  }\n  __typename\n}"
}';

        $parameters = json_decode($parameters);

        if (isset($args['model_id']) && !empty($args['model_id'])) {
            $parameters->variables->where->generation->modelId = [
                '_eq' => $args['model_id']
            ];
        }

        if (isset($args['search']) && !empty($args['search'])) {
            $parameters->variables->where->generation->prompt = [
                '_ilike' => "%" . $args['search'] . "%"
            ];
        }

        $parameters = json_encode($parameters);

        $response = wp_remote_post($url, array (
            'headers' => array (
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer ' . $token,
            ),
            'body' => $parameters,
            'method' => 'POST',
            'data_format' => 'body',
        ));

        $body = wp_remote_retrieve_body($response);

        $response_data = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return isset($response_data['data']['generated_images']) ? $response_data['data']['generated_images'] : null;
    }

    function delete_generation($token, $id)
    {
        $url = 'https://api.leonardo.ai/v1/graphql';

        $parameters = '{"operationName":"DeleteGeneration","variables":{"id":"' . $id . '"},"query":"mutation DeleteGeneration($id: uuid!) {\n  delete_generations_by_pk(id: $id) {\n    id\n    __typename\n  }\n}"}';

        $response = wp_remote_post($url, array (
            'headers' => array (
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer ' . $token,
            ),
            'body' => $parameters,
            'method' => 'POST',
            'data_format' => 'body',
        ));

        $body = wp_remote_retrieve_body($response);

        $response_data = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return $response_data;
    }

    /**
     * @param $generations
     * @param $existing_model_id
     * @return void
     */
    function save_generations($generations, $existing_model_id, $args = [])
    {
        $auto_check_for_nsfw = growtype_art_get_model_single_setting($existing_model_id, 'auto_check_for_nsfw');
        $auto_check_for_nsfw = !empty($auto_check_for_nsfw) ? filter_var($auto_check_for_nsfw['meta_value'], FILTER_VALIDATE_BOOLEAN) : false;

        /**
         * Group generations by unique key
         */
        $grouped_generations = [];
        foreach ($generations as $generation) {
            $unique_key = implode('-', [
                'grouped',
                $generation['modelId'],
                $generation['guidanceScale'],
                preg_replace('/\s+/', '_', trim(substr($generation['prompt'], 0, 100))),
                $generation['inferenceSteps'],
                $generation['scheduler'],
                $generation['coreModel'],
                $generation['sdVersion'],
                $generation['presetStyle'],
                $generation['tiling'],
            ]);

            $grouped_generations[trim($unique_key)][] = $generation;
        }

        foreach ($grouped_generations as $generations_group) {
            $reference_id = growtype_art_generate_reference_id();

            if (!empty($existing_model_id)) {
                $model = growtype_art_get_model_details($existing_model_id);
                $reference_id = $model['reference_id'];

                if (empty($reference_id)) {
                    $reference_id = growtype_art_generate_reference_id();
                }
            }

            foreach ($generations_group as $generation) {
                $image_folder = isset($model['image_folder']) ? $model['image_folder'] : self::MODELS_FOLDER_NAME . '/' . $reference_id;
                $image_location = growtype_art_get_images_saving_location();

                $existing_models = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::MODELS_TABLE, [
                    [
                        'key' => 'reference_id',
                        'values' => [$reference_id],
                    ]
                ]);

                $model_id = $existing_models[0]['id'] ?? null;

                /**
                 * Try to find existing models
                 */
                if (empty($model_id)) {
                    $existing_models_settings = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::MODEL_SETTINGS_TABLE, [
                        [
                            'key' => 'meta_key',
                            'value' => 'model_id',
                        ],
                        [
                            'key' => 'meta_value',
                            'value' => $generation['modelId'],
                        ]
                    ], 'where');

                    foreach ($existing_models_settings as $existing_model_settings) {
                        $existing_models = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::MODELS_TABLE, [
                            [
                                'key' => 'id',
                                'values' => [$existing_model_settings['model_id']],
                            ]
                        ]);

                        foreach ($existing_models as $existing_model) {
                            $formatted_prompt = growtype_art_model_format_prompt($existing_model['prompt'], $existing_model['id']);

                            if (trim($generation['prompt']) === trim($formatted_prompt)) {
                                $model_id = $existing_model['id'];
                                break;
                            }
                        }

                        if (!empty($model_id)) {
                            break;
                        }
                    }
                }

                if (empty($model_id)) {
                    $model_id = Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::MODELS_TABLE, [
                        'prompt' => $generation['prompt'],
                        'negative_prompt' => !empty($generation['negativePrompt']) ? $generation['negativePrompt'] : 'watermark, watermarked, disfigured, ugly, grain, low resolution, deformed, blurred, bad anatomy, badly drawn face, extra limb, ugly, badly drawn arms, missing limb, floating limbs, detached limbs, deformed arms, out of focus, disgusting, badly drawn, disfigured, tile, badly drawn arms, badly drawn legs, badly drawn face, out of frame, extra limbs, deformed, body out of frame, grainy, clipped, bad proportion, cropped image, blur haze',
                        'reference_id' => $reference_id,
                        'provider' => self::MODELS_FOLDER_NAME,
                        'image_folder' => $image_folder
                    ]);

                    $model_settings = [
                        'guidance_scale' => $generation['guidanceScale'],
                        'inference_steps' => $generation['inferenceSteps'],
                        'scheduler' => $generation['scheduler'],
                        'core_model' => $generation['coreModel'],
                        'sd_version' => $generation['sdVersion'],
                        'tiling' => $generation['tiling'],
                        'init_strength' => $generation['initStrength'],
                        'image_width' => $generation['imageWidth'],
                        'image_height' => $generation['imageHeight'],
                        'num_inference_steps' => $generation['inferenceSteps'],
                        'preset_style' => $generation['presetStyle'],
                        'leonardo_magic' => isset($generation['leonardoMagic']) ? $generation['leonardoMagic'] : null,
                        'alchemy' => isset($generation['alchemy']) ? $generation['alchemy'] : true,
                        'image_prompts' => isset($generation['imagePrompts']) ? implode(',', $generation['imagePrompts']) : null,
                        'image_prompt_weight' => isset($generation['imagePromptWeight']) ? $generation['imagePromptWeight'] : null,
                        'pose_to_image' => isset($generation['poseToImage']) ? $generation['poseToImage'] : false,
                        'pose_to_image_type' => isset($generation['poseToImageType']) ? $generation['poseToImageType'] : 'POSE',
                        'photoReal' => isset($generation['photoReal']) ? $generation['photoReal'] : false,
                        'created_by' => 'admin',
                    ];

                    if (isset($generation['modelId']) && !empty($generation['modelId']) && !$model_settings['photoReal']) {
                        $model_settings['model_id'] = $generation['modelId'];
                    }

                    if (isset($generation['initStrength']) && !empty($generation['initStrength'])) {
                        $model_settings['init_strength'] = $generation['initStrength'];
                    }

                    foreach ($model_settings as $key => $value) {
                        $existing_content = growtype_art_get_model_single_setting($model_id, $key);

                        if (!empty($existing_content)) {
                            continue;
                        }

                        Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::MODEL_SETTINGS_TABLE, [
                            'model_id' => $model_id,
                            'meta_key' => $key,
                            'meta_value' => $value
                        ]);
                    }
                }

                foreach ($generation['generated_images'] as $image) {
                    /**
                     * Check if same image exists
                     */
                    if (isset($image['meta_details']['id'])) {
                        $existing_unique_hash = Growtype_Art_Database_Crud::get_records(Growtype_Art_Database::IMAGE_SETTINGS_TABLE, [
                            [
                                'key' => 'meta_key',
                                'value' => 'id',
                            ],
                            [
                                'key' => 'meta_value',
                                'value' => $image['meta_details']['id'],
                            ]
                        ], 'where');

                        if (!empty($existing_unique_hash)) {
                            continue;
                        }
                    }

                    $image['imageWidth'] = isset($generation['imageWidth']) ? $generation['imageWidth'] : null;
                    $image['imageHeight'] = isset($generation['imageHeight']) ? $generation['imageHeight'] : null;
                    $image['folder'] = $image_folder;
                    $image['location'] = $image_location;

                    $image['meta_details'] = [
                        [
                            'key' => 'generation_id',
                            'value' => $image['id']
                        ],
                        [
                            'key' => 'provider',
                            'value' => Growtype_Art_Crud::LEONARDOAI_KEY
                        ]
                    ];

                    foreach ($generation as $key => $value) {
                        if (!in_array($key, ['imageHeight', 'imageWidth', 'public', 'status', 'expandedDomain', '__typename'])) {

                            if ($key === 'nsfw' && !$auto_check_for_nsfw) {
                                $value = '0';
                            }

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
                     * assign image to post
                     */
                    if (isset($args['post_id']) && !empty($args['post_id'])) {
                        $existing_images = get_post_meta($args['post_id'], 'growtype_art_images_ids', true);
                        $existing_images = !empty($existing_images) ? json_decode($existing_images, true) : [];
                        $existing_images = !empty($existing_images) ? $existing_images : [];
                        array_push($existing_images, $saved_image['id']);
                        update_post_meta($args['post_id'], 'growtype_art_images_ids', json_encode($existing_images));
                        update_post_meta($args['post_id'], 'growtype_art_images_generating', false);
                    }

                    /**
                     * Update cloudinary image details
                     */
                    if ($image['location'] === 'cloudinary') {
                        $cloudinary_crud = new Cloudinary_Crud();
                        $cloudinary_crud->update_cloudinary_image_details($saved_image['id']);
                    }

                    /**
                     * Get image colors
                     */
                    Extract_Image_Colors_Job::update_image_colors_groups($saved_image['id']);

                    /**
                     * Compress image
                     */
                    growtype_art_compress_existing_image($saved_image['id']);

                    sleep(2);
                }

                do_action('growtype_art_model_update', $model_id);
            }
        }
    }

    function delete_external_generations($token, $generations)
    {
        foreach ($generations as $generation) {
            $this->delete_generation($token, $generation['id']);
        }
    }
}


