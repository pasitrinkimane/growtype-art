<?php

abstract class Growtype_Art_Generator_Base
{
    const DEFAULT_IMAGE_DIMENSIONS = [
        'width' => 768,
        'height' => 1024,
    ];

    abstract public function generate_image_init($params);
    abstract public function get_provider_key();

    public function retrieve_generations($model_id, $generation_id, $args = [])
    {
        return [];
    }

    public function get_models()
    {
        return [];
    }

    public function api_key()
    {
        if (!class_exists('Growtype_Auth')) {
            error_log('Growtype Art - Fatal Error: Growtype_Auth class not found. Please ensure Growtype - Auth plugin is activated.');
            return [];
        }

        return \Growtype_Auth::credentials($this->get_provider_key());
    }

    public function get_access_token($api_group_key)
    {
        $keys = $this->api_key()[$api_group_key] ?? [];
        return $keys['api_key'] ?? $keys['token'] ?? $keys['jwt_token'] ?? $keys['x_api_key'] ?? $keys['x-api-key'] ?? '';
    }

    public function generate_model_image($model_id, $params = [])
    {
        $model = growtype_art_get_model_details($model_id);

        $prompt = isset($params['prompt']) && !empty($params['prompt']) ? $params['prompt'] : $model['prompt'];

        if (empty($prompt) && isset($params['prompt_params']) && !empty($params['prompt_params'])) {
            $prompt = growtype_art_get_default_prompt_template($params['prompt_params']);
        }

        $formatted_prompt = growtype_art_model_format_prompt($prompt, $model_id);

        if (isset($params['prompt_params']) && !empty($params['prompt_params'])) {
            $formatted_prompt = growtype_art_format_prompt_with_params($formatted_prompt, $params['prompt_params']);
        }

        $api_keys = $this->api_key();

        if (empty($api_keys)) {
            return [
                'success' => false,
                'message' => sprintf('Empty API keys. Model %s.', $model_id),
            ];
        }

        $api_group_key = array_keys($api_keys)[array_rand(array_keys($this->api_key()))];

        $params['token'] = $this->get_access_token($api_group_key);
        $params['prompt'] = $formatted_prompt;
        $params['generation_id'] = wp_generate_password(52, false);
        $params['model_id'] = $model_id;

        $generation_result = $this->generate_image_init($params);

        if (empty($generation_result) || isset($generation_result['errors'])) {
            return [
                'success' => false,
                'message' => $generation_result['errors'][0]['message'] ?? 'Something went wrong',
            ];
        }

        if (isset($generation_result['status']) && $generation_result['status'] === 'pending') {
            $task_id = $generation_result['task_id'] ?? $generation_result['generation_id'] ?? null;
            
            if ($task_id) {
                $cron_payload = [
                    'provider' => $this->get_provider_key(),
                    'model_id' => $model_id,
                    'generation_id' => $task_id,
                    'prompt' => $formatted_prompt,
                    'api_group_key' => $api_group_key,
                ];

                if (isset($generation_result['cron_payload']) && is_array($generation_result['cron_payload'])) {
                    $cron_payload = array_merge($cron_payload, $generation_result['cron_payload']);
                }

                \Growtype_Cron_Jobs::create_if_not_exists('retrieve-model', json_encode($cron_payload), 30);

                return [
                    'success' => true,
                    'message' => 'Generation pending...',
                    'task_id' => $task_id,
                    'is_pending' => true
                ];
            }
        }

        /**
         * Standardize generations list
         */
        $generations_list = [];
        if (isset($generation_result['generations'])) {
            $generations_list = $generation_result['generations'];
        } elseif (isset($generation_result['data'])) {
            $generations_list = $generation_result['data'];
        } elseif (isset($generation_result['imageBase64'])) {
            $generations_list = [['imageBase64' => $generation_result['imageBase64']]];
        }

        $response = $this->save_generations($generations_list, $model_id, $params);

        return [
            'success' => true,
            'generations' => $response,
            'message' => sprintf('Successfully generated. Prompt: %s', $prompt)
        ];
    }

    public function generate_image($params = [])
    {
        $prompt = isset($params['prompt']) ? $params['prompt'] : '';
        $model_id = isset($params['model_id']) ? $params['model_id'] : null;
        $prompt_params = isset($params['prompt_params']) ? $params['prompt_params'] : null;

        if ($model_id) {
            $formatted_prompt = growtype_art_model_format_prompt($prompt, $model_id);
        } else {
            if (empty($prompt) && $prompt_params) {
                $formatted_prompt = growtype_art_get_default_prompt_template($prompt_params);
            } else {
                $formatted_prompt = $prompt;
            }
        }

        if ($prompt_params) {
            $formatted_prompt = growtype_art_format_prompt_with_params($formatted_prompt, $prompt_params);
        }

        $api_keys = $this->api_key();

        if (empty($api_keys)) {
            return [
                'success' => false,
                'message' => 'Empty API keys.',
            ];
        }

        $api_group_key = array_keys($api_keys)[array_rand(array_keys($this->api_key()))];

        $params['token'] = $this->get_access_token($api_group_key);
        $params['prompt'] = $formatted_prompt;
        $params['generation_id'] = wp_generate_password(52, false);
        
        $generation_result = $this->generate_image_init($params);

        if (empty($generation_result) || isset($generation_result['errors']) || (isset($generation_result['success']) && !$generation_result['success'])) {
            error_log(sprintf('Growtype Art - Generation init failed for %s. Result: %s', $this->get_provider_key(), json_encode($generation_result)));
            return [
                'success' => false,
                'message' => $generation_result['message'] ?? $generation_result['errors'][0]['message'] ?? 'Something went wrong',
            ];
        }

        if (isset($generation_result['status']) && $generation_result['status'] === 'pending') {
            $task_id = $generation_result['task_id'] ?? $generation_result['generation_id'] ?? null;
            
            if ($task_id) {
                $cron_payload = [
                    'provider' => $this->get_provider_key(),
                    'model_id' => $model_id,
                    'generation_id' => $task_id,
                    'prompt' => $formatted_prompt,
                    'api_group_key' => $api_group_key,
                ];

                if (isset($generation_result['cron_payload']) && is_array($generation_result['cron_payload'])) {
                    $cron_payload = array_merge($cron_payload, $generation_result['cron_payload']);
                }

                \Growtype_Cron_Jobs::create_if_not_exists('retrieve-model', json_encode($cron_payload), 30);

                return [
                    'success' => true,
                    'message' => 'Generation pending...',
                    'task_id' => $task_id,
                    'is_pending' => true
                ];
            }
        }

        error_log(sprintf('Growtype Art - %s: generation_result: %s', $this->get_provider_key(), json_encode($generation_result)));

        $generations_list = [];
        if (isset($generation_result['generations'])) {
            $generations_list = $generation_result['generations'];
        } elseif (isset($generation_result['data'])) {
            $generations_list = $generation_result['data'];
        } elseif (isset($generation_result['imageBase64'])) {
            $generations_list = [['imageBase64' => $generation_result['imageBase64']]];
        }

        error_log(sprintf('Growtype Art - %s: generations_list: %s', $this->get_provider_key(), json_encode($generations_list)));

        $response = $this->save_generations($generations_list, $model_id, $params);

        if (empty($response) && !empty($generations_list)) {
            error_log(sprintf('Growtype Art - save_generations returned empty for %s despite having generations_list.', $this->get_provider_key()));
        }

        return [
            'success' => true,
            'generations' => $response,
            'message' => sprintf('Successfully generated. Prompt: %s', $formatted_prompt)
        ];
    }

    public function save_generations($generations, $model_id = null, $params = [])
    {
        $saved_generations = [];
        foreach ($generations as $generation) {
            $image_location = growtype_art_get_images_saving_location(); 
            
            if ($model_id) {
                $model = growtype_art_get_model_details($model_id);
                $image_folder = $model['image_folder'];
            } else {
                $image_folder = Growtype_Art_Crud::IMAGES_FOLDER_NAME . '/public';
            }

            $image = [
                'folder' => $image_folder,
                'location' => $image_location,
                'meta_details' => [
                    [
                        'key' => 'generation_id',
                        'value' => $generation['generation_id'] ?? $generation['id'] ?? $params['generation_id'] ?? $params['task_id'] ?? ''
                    ],
                    [
                        'key' => 'provider',
                        'value' => $this->get_provider_key()
                    ],
                    [
                        'key' => 'prompt',
                        'value' => $params['prompt']
                    ]
                ]
            ];

            // Normalize Content/URL
            if (isset($generation['imageBase64'])) {
                $image['content'] = $generation['imageBase64'];
            } elseif (isset($generation['imageURL'])) {
                $image['url'] = $generation['imageURL'];
            } elseif (isset($generation['url'])) {
                 $image['url'] = $generation['url'];
            }

            if (isset($params['types'])) {
                foreach ($params['types'] as $type) {
                    $image['meta_details'][] = [
                        'key' => $type,
                        'value' => 1
                    ];
                }
            }

            // Automaticaly add extra meta from generation object
            foreach ($generation as $key => $value) {
                if (!in_array($key, ['imageBase64', 'imageURL', 'url', 'status', 'index', 'info'])) {
                    $image['meta_details'][] = [
                        'key' => $key,
                        'value' => is_array($value) ? json_encode($value) : (!empty($value) ? $value : '0')
                    ];
                }
            }

            $saved_image = Growtype_Art_Crud::save_image($image);

            if (empty($saved_image) || isset($saved_image['error']) || !isset($saved_image['id'])) {
                error_log(sprintf('save_generations error for provider %s: %s', $this->get_provider_key(), json_encode($saved_image)));
                continue;
            }

            /**
             * Assign image to model if exists
             */
            if ($model_id) {
                Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::MODEL_IMAGE_TABLE, [
                    'model_id' => $model_id,
                    'image_id' => $saved_image['id']
                ]);

                growtype_art_compress_existing_image($saved_image['id']);
            }

            $saved_generations[] = [
                'url' => $saved_image['details']['url'],
                'image_id' => $saved_image['id'],
                'generation_id' => $generation['generation_id'] ?? $generation['id'] ?? $params['generation_id'] ?? $params['task_id'] ?? '',
                'image_prompt' => $params['prompt'],
            ];
        }

        if ($model_id) {
            do_action('growtype_art_model_update', $model_id);
        }

        return $saved_generations;
    }

    public function generate_image_sync($params)
    {
        try {
            /**
             * 1. Prepare Prompt & Params (Logic shared with generate_image)
             */
            $prompt = $params['prompt'] ?? '';
            $model_id = $params['model_id'] ?? null;
            $prompt_params = $params['prompt_params'] ?? null;

            if ($model_id) {
                $formatted_prompt = growtype_art_model_format_prompt($prompt, $model_id);
            } else {
                $formatted_prompt = (empty($prompt) && $prompt_params) ? growtype_art_get_default_prompt_template($prompt_params) : $prompt;
            }

            if ($prompt_params) {
                $formatted_prompt = growtype_art_format_prompt_with_params($formatted_prompt, $prompt_params);
            }

            /**
             * 2. Handle API Keys & Multi-Token Retry
             */
            $api_keys = $this->api_key();
            if (empty($api_keys)) {
                return ['success' => false, 'message' => 'Empty API keys.'];
            }

            $keys_to_try = array_keys($api_keys);
            shuffle($keys_to_try); // Randomize start order

            foreach ($keys_to_try as $api_group_key) {
                $params['token'] = $this->get_access_token($api_group_key);
                $params['prompt'] = $formatted_prompt;
                $params['generation_id'] = wp_generate_password(52, false);
                $params['api_group_key'] = $api_group_key;

                /**
                 * 3. Initiate Generation
                 */
                $generation_result = $this->generate_image_init($params);

                // If unauthorized, try the next token
                if (isset($generation_result['success']) && $generation_result['success'] === false && strpos($generation_result['message'] ?? '', 'Unauthorized') !== false) {
                    error_log(sprintf('Growtype Art - %s: Token %s failed with 401. Retrying with next token...', ucfirst($this->get_provider_key()), $api_group_key));
                    continue;
                }

                /**
                 * 4. Handle Pending/Polling logic
                 */
                if (isset($generation_result['status']) && $generation_result['status'] === 'pending' && (isset($generation_result['task_id']) || isset($generation_result['generation_id']))) {
                    $task_id = $generation_result['task_id'] ?? $generation_result['generation_id'];

                    $max_attempts = 20;
                    $attempts = 0;
                    $interval = 6; // seconds

                    while ($attempts < $max_attempts) {
                        sleep($interval);
                        $attempts++;

                        $generations = $this->retrieve_generations($model_id, [$task_id], [
                            'api_group_key' => $api_group_key,
                            'prompt' => $params['prompt']
                        ]);

                        error_log(sprintf('Growtype Art - %s: Polling attempt %d for %s.', ucfirst($this->get_provider_key()), $attempts, $task_id));

                        if (!empty($generations)) {
                            return [
                                'success' => true,
                                'generations' => (isset($generations[0]) && is_array($generations[0]) && array_values($generations[0]) === $generations[0] && isset($generations[0][0]) && is_array($generations[0][0])) ? $generations[0] : $generations,
                                'message' => 'Image generated successfully'
                            ];
                        }
                    }

                    return [
                        'success' => false,
                        'message' => 'Timeout: Image was not generated in time.'
                    ];
                }

                if (isset($generation_result['success']) && $generation_result['success']) {
                    return $generation_result;
                }

                $last_result = $generation_result;
            }

            return $last_result ?? [
                'success' => false,
                'message' => 'Something went wrong while trying all available tokens.'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
