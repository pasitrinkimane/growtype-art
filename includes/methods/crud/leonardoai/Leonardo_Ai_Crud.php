<?php

require GROWTYPE_AI_PATH . '/vendor/autoload.php';

use React\EventLoop\Loop;

class Leonardo_Ai_Crud
{
    const MODELS_FOLDER_NAME = 'models';

    public static function user_credentials()
    {
        return [
            '1' => [
                'cookie' => get_option('growtype_ai_leonardo_cookie'),
                'user_id' => get_option('growtype_ai_leonardo_user_id'),
                'id_token' => get_option('growtype_ai_leonardo_id_token')
            ],
            '2' => [
                'cookie' => get_option('growtype_ai_leonardo_cookie_2'),
                'user_id' => get_option('growtype_ai_leonardo_user_id_2'),
                'id_token' => get_option('growtype_ai_leonardo_id_token_2')
            ],
            '3' => [
                'cookie' => get_option('growtype_ai_leonardo_cookie_3'),
                'user_id' => get_option('growtype_ai_leonardo_user_id_3'),
                'id_token' => get_option('growtype_ai_leonardo_id_token_3')
            ],
            '4' => [
                'cookie' => get_option('growtype_ai_leonardo_cookie_4'),
                'user_id' => get_option('growtype_ai_leonardo_user_id_4'),
                'id_token' => get_option('growtype_ai_leonardo_id_token_4')
            ],
            '5' => [
                'cookie' => get_option('growtype_ai_leonardo_cookie_5'),
                'user_id' => get_option('growtype_ai_leonardo_user_id_5'),
                'id_token' => get_option('growtype_ai_leonardo_id_token_5')
            ],
            '6' => [
                'cookie' => get_option('growtype_ai_leonardo_cookie_6'),
                'user_id' => get_option('growtype_ai_leonardo_user_id_6'),
                'id_token' => get_option('growtype_ai_leonardo_id_token_6')
            ],
            '7' => [
                'cookie' => get_option('growtype_ai_leonardo_cookie_7'),
                'user_id' => get_option('growtype_ai_leonardo_user_id_7'),
                'id_token' => get_option('growtype_ai_leonardo_id_token_7')
            ]
        ];
    }

    function get_user_credentials($user_nr)
    {
        if (empty($user_nr)) {
            $user_nr = 1;
        }

        return self::user_credentials()[$user_nr];
    }

    public function generate_model($model_id = null)
    {
        $generation_details = $this->generate_model_image($model_id);

        growtype_ai_init_job('retrieve-model', json_encode([
            'user_nr' => $generation_details['user_nr'],
            'amount' => 1,
            'model_id' => $model_id,
            'generation_id' => $generation_details['generation_id'],
            'image_prompt' => $generation_details['image_prompt'],
        ]), 60);

        return $generation_details;
    }

    public function generate_model_image($model_id)
    {
        $credentials = $this->user_credentials();

        $users = array_keys($credentials);

        shuffle($users);

        $generation_id = null;
        foreach ($users as $user_nr) {
            $token = $this->retrieve_access_token($user_nr);

            if (empty($token)) {
                throw new Exception('Empty token. User nr: ' . $user_nr);
            }

            $credentials = [
                'token' => $token,
                'user_nr' => $user_nr
            ];

            try {
                $image_generating = $this->init_image_generating($credentials, $model_id);
                $generation_id = $image_generating['generation_id'];
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'not enough tokens') !== false) {
                    continue;
                }
            }

            if (!empty($generation_id)) {
                break;
            }
        }


        if (empty($generation_id)) {
            throw new Exception('No generationId');
        }

        return [
            'generation_id' => $generation_id,
            'image_prompt' => isset($image_generating['image_prompt']) ? $image_generating['image_prompt'] : null,
            'user_nr' => $user_nr
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

    public function retrieve_single_generation($model_id, $user_nr, $generation_id)
    {
        $token = $this->get_access_token($user_nr);

        $generations = $this->get_generations($token, 30, $user_nr);

        if (empty($generations)) {
            throw new Exception('Empty generations. Token: ' . $token);
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

//        error_log(print_r($generations, true));

        if (!empty($generations)) {
            /**
             * Save generation
             */
            $this->save_generations($generations, $model_id);

            /**
             * Delete generation from Leonardo.ai
             */
            $this->delete_external_generations($token, $generations);
        }

        return $generations;
    }

    public function get_access_token($user_nr = null)
    {
        $token = $this->retrieve_access_token($user_nr);

        if (empty($token)) {
            throw new Exception('No token user_nr: ' . $user_nr);
        }

        return $token;
    }

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

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return $responceData;
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

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return isset($responceData['accessToken']) ? $responceData['accessToken'] : null;
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
            $model_details = growtype_ai_get_model_details($model_id);

            $image_prompt = $model_details['prompt'];
            $prompt_variables = isset($model_details['settings']['prompt_variables']) ? $model_details['settings']['prompt_variables'] : null;
            $prompt_variables = !empty($prompt_variables) ? explode('|', $prompt_variables) : null;

            if (!empty($prompt_variables) && str_contains($image_prompt, '{prompt_variable}')) {
                $rendom_promp_variable_key = array_rand($prompt_variables, 1);

                $image_prompt = str_replace('{prompt_variable}', $prompt_variables[$rendom_promp_variable_key], $image_prompt);
            }

            $leonardoMagic = isset($model_details['settings']['leonardo_magic']) && !$model_details['settings']['leonardo_magic'] ? false : true;
            $alchemy = isset($model_details['settings']['alchemy']) && $model_details['settings']['alchemy'] ? true : false;

            $default_args = [
                'prompt' => $image_prompt,
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
                'leonardoMagic' => $leonardoMagic,
                'public' => true,
                'tiling' => isset($model_details['settings']['tiling']) && !$model_details['settings']['tiling'] ? false : true,
                'alchemy' => $alchemy,
                'weighting' => 1,
                'poseToImage' => isset($model_details['settings']['pose_to_image']) && $model_details['settings']['pose_to_image'] ? true : false,
                'poseToImageType' => isset($model_details['settings']['pose_to_image_type']) && !empty($model_details['settings']['pose_to_image_type']) ? $model_details['settings']['pose_to_image_type'] : 'POSE',
                'highResolution' => $leonardoMagic ? true : false,
                'highContrast' => isset($model_details['settings']['high_contrast']) && $model_details['settings']['high_contrast'] ? true : false,
                'expandedDomain' => true,
                'contrastRatio' => 0.5,
                'photoReal' => isset($model_details['settings']['photoReal']) && $model_details['settings']['photoReal'] ? true : false,
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
                $default_args['init_generation_image_id'] = $model_details['settings']['init_generation_image_id'];
            }

            $parameters = [
                'operationName' => 'CreateSDGenerationJob',
                'variables' => [
                    'arg1' => empty($custom_args) ? $default_args : $custom_args
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

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        $generation_id = isset($responceData['data']['sdGenerationJob']['generationId']) ? $responceData['data']['sdGenerationJob']['generationId'] : null;

        if (empty($generation_id)) {
            $message = isset($responceData['errors'][0]['message']) ? $responceData['errors'][0]['message'] : '';

            $ignored_messages = [
                'not enough tokens',
            ];

            if ($message === 'maximum trial alchemy generations' || $message === 'FREE plan user NOT permitted to use Alchemy Generation') {
                $default_args['alchemy'] = false;
                return $this->init_image_generating($credentials, $model_id, $default_args);
            }

            if (!in_array($message, $ignored_messages)) {
                throw new Exception(json_encode($responceData));
            }
        }

        return [
            'generation_id' => $generation_id,
            'image_prompt' => $image_prompt,
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

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return $responceData;
    }

    function get_generations($token, $amount = 10, $user_nr = null)
    {
        $url = 'https://api.leonardo.ai/v1/graphql';

        $user_id = $this->get_user_credentials($user_nr)['user_id'];

//        $parameters = '{
//    "operationName": "GetAIGenerationFeed",
//    "variables": {
//        "where": {
//            "userId": {
//                "_eq": "' . $user_id . '"
//            },
//            "canvasRequest": {
//                "_eq": false
//            }
//        },
//        "userId": "' . $user_id . '"
//    },
//    "query": "query GetAIGenerationFeed($where: generations_bool_exp = {}, $userId: uuid!) {\n  generations(limit: ' . $amount . ', order_by: [{createdAt: desc}], where: $where) {\n    guidanceScale\n    inferenceSteps\n    modelId\n    scheduler\n    coreModel\n    sdVersion\n    prompt\n    negativePrompt\n    id\n    status\n    quantity\n    createdAt\n    imageHeight\n    imageWidth\n    presetStyle\n    sdVersion\n    seed\n    tiling\n    initStrength\n    user {\n      username\n      id\n      __typename\n    }\n    custom_model {\n      id\n      userId\n      name\n      modelHeight\n      modelWidth\n      __typename\n    }\n    init_image {\n      id\n      url\n      __typename\n    }\n    generated_images(order_by: [{url: desc}]) {\n      id\n      url\n      likeCount\n      generated_image_variation_generics(order_by: [{createdAt: desc}]) {\n        url\n        status\n        createdAt\n        id\n        transformType\n        __typename\n      }\n      user_liked_generated_images(limit: 1, where: {userId: {_eq: $userId}}) {\n        generatedImageId\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}"
//}';

        $parameters = '{
   "operationName":"GetAIGenerationFeed",
   "variables":{
      "where":{
         "userId":{
            "_eq": "' . $user_id . '"
         },
         "canvasRequest":{
            "_eq":false
         }
      },
      "offset":0,
      "limit":' . $amount . '
   },
   "query":"query GetAIGenerationFeed($where: generations_bool_exp = {}, $userId: uuid, $limit: Int, $offset: Int = 0) {\n  generations(\n    limit: $limit\n    offset: $offset\n    order_by: [{createdAt: desc}]\n    where: $where\n  ) {\n    alchemy\n    contrastRatio\n    highResolution\n    guidanceScale\n    inferenceSteps\n    modelId\n    scheduler\n    coreModel\n    sdVersion\n    prompt\n    negativePrompt\n    id\n    status\n    quantity\n    createdAt\n    imageHeight\n    imageWidth\n    presetStyle\n    sdVersion\n    public\n    seed\n    tiling\n    initStrength\n    highContrast\n    promptMagic\n    promptMagicVersion\n    promptMagicStrength\n    imagePromptStrength\n    expandedDomain\n    photoReal\n    photoRealStrength\n    nsfw\n    user {\n      username\n      id\n      __typename\n    }\n    custom_model {\n      id\n      userId\n      name\n      modelHeight\n      modelWidth\n      __typename\n    }\n    init_image {\n      id\n      url\n      __typename\n    }\n    generated_images(order_by: [{url: desc}]) {\n      id\n      url\n      likeCount\n      nsfw\n      generated_image_variation_generics(order_by: [{createdAt: desc}]) {\n        url\n        status\n        createdAt\n        id\n        transformType\n        upscale_details {\n          oneClicktype\n          isOneClick\n          id\n          variationId\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    generation_elements {\n      id\n      lora {\n        akUUID\n        name\n        description\n        urlImage\n        baseModel\n        weightDefault\n        weightMin\n        weightMax\n        __typename\n      }\n      weightApplied\n      __typename\n    }\n    __typename\n  }\n}"
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

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return isset($responceData['data']['generations']) ? $responceData['data']['generations'] : null;
    }

    function get_user_feed($user_nr, $args)
    {
        $token = $this->retrieve_access_token($user_nr);

        return $this->get_feed_images($token, $args);
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
      "createdAt": {
        "_lt": "2023-10-17T06:54:35.171Z"
      },
      "generation": {
        "status": {
          "_eq": "COMPLETE"
        },
        "prompt": {
          "_ilike": "%' . $args['search'] . '%"
        },
        "public": {
          "_eq": true
        },
        "canvasRequest": {
          "_eq": false
        },
        "category": {},
        "_or": [
          {
            "promptMagic": {
              "_eq": false
            }
          },
          {
            "initStrength": {
              "_is_null": true
            }
          },
          {
            "photoReal": {
              "_eq": false
            }
          }
        ]
      },
      "nsfw": {
        "_eq": true
      }
    },
    "limit": 50,
    "offset": ' . $args['offset'] . ',
    "userId": "e5e50ec2-0190-4a75-9241-8c464261347c"
  },
  "query": "query GetFeedImages($where: generated_images_bool_exp, $limit: Int, $userId: uuid!, $order_by: [generated_images_order_by!] = [{createdAt: desc}], $offset: Int) {\n  generated_images(\n    where: $where\n    limit: $limit\n    order_by: $order_by\n    offset: $offset\n  ) {\n    ...FeedParts\n    __typename\n  }\n}\n\nfragment FeedParts on generated_images {\n  createdAt\n  id\n  url\n  user_liked_generated_images(limit: 1, where: {userId: {_eq: $userId}}) {\n    generatedImageId\n    __typename\n  }\n  user {\n    username\n    id\n    __typename\n  }\n  generation {\n    id\n    alchemy\n    contrastRatio\n    highResolution\n    prompt\n    negativePrompt\n    imageWidth\n    imageHeight\n    sdVersion\n    modelId\n    coreModel\n    guidanceScale\n    inferenceSteps\n    seed\n    scheduler\n    tiling\n    highContrast\n    promptMagic\n    promptMagicVersion\n    imagePromptStrength\n    custom_model {\n      id\n      name\n      userId\n      modelHeight\n      modelWidth\n      __typename\n    }\n    generation_elements {\n      id\n      lora {\n        akUUID\n        name\n        description\n        urlImage\n        baseModel\n        weightDefault\n        weightMin\n        weightMax\n        __typename\n      }\n      weightApplied\n      __typename\n    }\n    initStrength\n    category\n    public\n    nsfw\n    photoReal\n    __typename\n  }\n  generated_image_variation_generics(order_by: [{createdAt: desc}]) {\n    url\n    id\n    status\n    transformType\n    upscale_details {\n      oneClicktype\n      __typename\n    }\n    __typename\n  }\n  likeCount\n  __typename\n}"
}';

        $parameters = json_decode($parameters);

        if (isset($args['model_id']) && !empty($args['model_id'])) {
            $parameters->variables->where->generation->modelId = [
                '_eq' => $args['model_id']
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

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return isset($responceData['data']['generated_images']) ? $responceData['data']['generated_images'] : null;
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

        $responceData = (!is_wp_error($response)) ? json_decode($body, true) : null;

        return $responceData;
    }

    /**
     * @param $generations
     * @param $existing_model_id
     * @return void
     */
    function save_generations($generations, $existing_model_id = null)
    {
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

            $reference_id = growtype_ai_generate_reference_id();

            if (!empty($existing_model_id)) {
                $model = growtype_ai_get_model_details($existing_model_id);
                $reference_id = $model['reference_id'];

                if (empty($reference_id)) {
                    $reference_id = growtype_ai_generate_reference_id();
                }
            }

            foreach ($generations_group as $generation) {
                $image_folder = isset($model['image_folder']) ? $model['image_folder'] : self::MODELS_FOLDER_NAME . '/' . $reference_id;
                $image_location = growtype_ai_get_images_saving_location();

                $existing_models = Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::MODELS_TABLE, [
                    [
                        'key' => 'reference_id',
                        'values' => [$reference_id],
                    ]
                ]);

                if (empty($existing_models)) {
                    $model_id = Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::MODELS_TABLE, [
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
                    ];

                    if (isset($generation['modelId']) && !empty($generation['modelId']) && !$model_settings['photoReal']) {
                        $model_settings['model_id'] = $generation['modelId'];
                    }

                    if (isset($generation['initStrength']) && !empty($generation['initStrength'])) {
                        $model_settings['init_strength'] = $generation['initStrength'];
                    }

                    foreach ($model_settings as $key => $value) {

                        $existing_content = growtype_ai_get_model_single_setting($model_id, $key);

                        if (!empty($existing_content)) {
                            continue;
                        }

                        Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
                            'model_id' => $model_id,
                            'meta_key' => $key,
                            'meta_value' => $value
                        ]);
                    }

//                    $openai_crud = new Openai_Crud();
//                    $openai_crud->format_models(null, false, $model_id);
                } else {
                    $model_id = $existing_models[0]['id'];
                }

                foreach ($generation['generated_images'] as $image) {
                    $image['imageWidth'] = isset($generation['imageWidth']) ? $generation['imageWidth'] : null;
                    $image['imageHeight'] = isset($generation['imageHeight']) ? $generation['imageHeight'] : null;
                    $image['folder'] = $image_folder;
                    $image['location'] = $image_location;

                    $image['meta_details'] = [];
                    foreach ($generation as $key => $value) {
                        if (!in_array($key, ['imageHeight', 'imageWidth', 'public', 'status', 'expandedDomain', '__typename'])) {
                            array_push($image['meta_details'], [
                                'key' => $key,
                                'value' => is_array($value) ? json_encode($value) : (!empty($value) ? $value : '0')
                            ]);
                        }
                    }

                    $saved_image = Growtype_Ai_Crud::save_image($image);

                    if (empty($saved_image)) {
                        continue;
                    }

                    /**
                     * Assign image to model
                     */
                    Growtype_Ai_Database_Crud::insert_record(Growtype_Ai_Database::MODEL_IMAGE_TABLE, [
                        'model_id' => $model_id,
                        'image_id' => $saved_image['id']
                    ]);

                    /**
                     * Generate image content
                     */
//                    $openai_crud = new Openai_Crud();
//                    $openai_crud->format_image($saved_image['id']);

                    /**
                     * Update cloudinary image details
                     */
                    if ($image['location'] === 'cloudinary') {
                        $cloudinary_crud = new Cloudinary_Crud();
                        $cloudinary_crud->update_cloudinary_image_details($saved_image['id']);
                    }

//                    growtype_ai_init_job('upscale-image', json_encode([
//                        'image_id' => $saved_image['id'],
//                    ]), 5);

                    /**
                     * Get image colors
                     */
                    Extract_Image_Colors_Job::update_image_colors_groups($saved_image['id']);

                    /**
                     * Compress image
                     */
                    growtype_ai_compress_existing_image($saved_image['id']);

                    sleep(2);
                }
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


