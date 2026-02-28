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

    // ──────────────────────────────────────────────
    // API Key Helpers
    // ──────────────────────────────────────────────

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

    // ──────────────────────────────────────────────
    // Shared Private Helpers (DRY)
    // ──────────────────────────────────────────────

    /**
     * Prepare and format prompt, optionally using model context.
     */
    private function prepare_prompt($prompt, $model_id = null, $prompt_params = null)
    {
        if ($model_id) {
            $formatted = growtype_art_model_format_prompt($prompt, $model_id);
        } else {
            $formatted = (empty($prompt) && $prompt_params)
                ? growtype_art_get_default_prompt_template($prompt_params)
                : $prompt;
        }

        if ($prompt_params) {
            $formatted = growtype_art_format_prompt_with_params($formatted, $prompt_params);
        }

        error_log('Prepared prompt: ' . print_r($formatted, true));

        return $formatted;
    }

    /**
     * Select a random API group key and return [api_group_key, token].
     *
     * @return array{api_group_key: string, token: string}|null Null if no keys available.
     */
    private function select_api_credentials()
    {
        $api_keys = $this->api_key();

        if (empty($api_keys)) {
            return null;
        }

        $available_keys = array_keys($api_keys);
        $api_group_key = $available_keys[array_rand($available_keys)];

        return [
            'api_group_key' => $api_group_key,
            'token' => $this->get_access_token($api_group_key),
        ];
    }

    /**
     * Normalize generation results from various provider formats into a flat list.
     */
    private function normalize_generations($generation_result)
    {
        if (isset($generation_result['generations'])) {
            return $generation_result['generations'];
        }

        if (isset($generation_result['data'])) {
            return $generation_result['data'];
        }

        if (isset($generation_result['imageBase64'])) {
            return [['imageBase64' => $generation_result['imageBase64']]];
        }

        return [];
    }

    /**
     * Handle a pending generation result: optionally create a cron job and return pending response.
     *
     * @return array|null Pending response array, or null if not a pending result.
     */
    private function handle_pending_result($generation_result, $model_id, $formatted_prompt, $api_group_key)
    {
        if (!isset($generation_result['status']) || $generation_result['status'] !== 'pending') {
            return null;
        }

        $task_id = $generation_result['task_id'] ?? $generation_result['generation_id'] ?? null;

        if (!$task_id) {
            return null;
        }

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
            'is_pending' => true,
        ];
    }

    /**
     * Strip sensitive fields (e.g. token) from params before passing to storage/logging.
     */
    private function sanitize_params($params)
    {
        unset($params['token']);
        return $params;
    }

    // ──────────────────────────────────────────────
    // Public Generation Methods
    // ──────────────────────────────────────────────

    /**
     * Generate an image for an existing model. Always saves to DB.
     */
    public function generate_model_image($model_id, $params = [])
    {
        $model = growtype_art_get_model_details($model_id);

        if (empty($model)) {
            if (!empty($params['prompt_params'])) {
                $model = []; // Proceed with empty model if prompt params allow construction
            } else {
                 return [
                    'success' => false,
                    'message' => 'Model information not found'
                ];
            }
        }

        $prompt = (!empty($params['prompt'])) ? $params['prompt'] : ($model['prompt'] ?? '');

        if (empty($prompt) && !empty($params['prompt_params'])) {
            $prompt = growtype_art_get_default_prompt_template($params['prompt_params']);
        }

        $formatted_prompt = $this->prepare_prompt($prompt, $model_id, $params['prompt_params'] ?? null);

        $credentials = $this->select_api_credentials();

        if (!$credentials) {
            return [
                'success' => false,
                'message' => sprintf('Empty API keys. Model %s.', $model_id),
            ];
        }

        $params['token'] = $credentials['token'];
        $params['prompt'] = $formatted_prompt;
        $params['generation_id'] = wp_generate_password(52, false);
        $params['model_id'] = $model_id;

        $generation_result = $this->generate_image_init($params);

        if (empty($generation_result) || isset($generation_result['errors']) || (isset($generation_result['success']) && !$generation_result['success'])) {
            return [
                'success' => false,
                'message' => $generation_result['message'] ?? $generation_result['errors'][0]['message'] ?? 'Something went wrong',
            ];
        }

        $pending = $this->handle_pending_result($generation_result, $model_id, $formatted_prompt, $credentials['api_group_key']);

        if ($pending) {
            return $pending;
        }

        $generations_list = $this->normalize_generations($generation_result);
        $response = $this->save_generations($generations_list, $model_id, $this->sanitize_params($params));

        return [
            'success' => true,
            'generations' => $response,
            'message' => sprintf('Successfully generated. Prompt: %s', $prompt),
        ];
    }

    /**
     * Generate an image (standalone or for a model). Supports save_to_db flag.
     */
    public function generate_image($params = [])
    {
        $prompt = $params['prompt'] ?? '';
        $model_id = $params['model_id'] ?? null;
        $prompt_params = $params['prompt_params'] ?? null;

        $formatted_prompt = $this->prepare_prompt($prompt, $model_id, $prompt_params);

        $credentials = $this->select_api_credentials();

        if (!$credentials) {
            return [
                'success' => false,
                'message' => 'Empty API keys.',
            ];
        }

        $params['token'] = $credentials['token'];
        $params['prompt'] = $formatted_prompt;
        $params['generation_id'] = wp_generate_password(52, false);

        $generation_result = $this->generate_image_init($params);

        if (empty($generation_result) || isset($generation_result['errors']) || (isset($generation_result['success']) && !$generation_result['success'])) {
            error_log(sprintf('Growtype Art - Generation init failed. Params: %s. Result: %s', print_r($params, true), print_r($generation_result, true)));

            return [
                'success' => false,
                'message' => $generation_result['message'] ?? $generation_result['errors'][0]['message'] ?? 'Something went wrong',
            ];
        }

        $pending = $this->handle_pending_result($generation_result, $model_id, $formatted_prompt, $credentials['api_group_key']);

        if ($pending) {
            return $pending;
        }

        $generations_list = $this->normalize_generations($generation_result);
        $response = $this->save_generations($generations_list, $model_id, $this->sanitize_params($params));

        if (empty($response) && !empty($generations_list)) {
            error_log(sprintf('Growtype Art - save_generations returned empty for %s despite having generations_list.', $this->get_provider_key()));
        }

        return [
            'success' => true,
            'generations' => $response,
            'message' => sprintf('Successfully generated. Prompt: %s', $formatted_prompt),
        ];
    }

    // ──────────────────────────────────────────────
    // Save / Persist
    // ──────────────────────────────────────────────

    /**
     * Save generated images to disk (and optionally to DB).
     *
     * Controlled by $params['save_to_db'] (default: true).
     * When false: images are saved to disk and URLs are returned, but no DB records are created.
     */
    public function save_generations($generations, $model_id = null, $params = [])
    {
        $save_to_db = $params['save_to_db'] ?? true;
        $saved_generations = [];

        foreach ($generations as $generation) {
            $image_location = growtype_art_get_images_saving_location();

            if ($model_id) {
                $model = growtype_art_get_model_details($model_id);
                $image_folder = $model['image_folder'];
            } else {
                $image_folder = 'nomodel';
            }

            $image = [
                'folder' => $image_folder,
                'location' => $image_location,
                'meta_details' => [
                    [
                        'key' => 'generation_id',
                        'value' => $generation['generation_id'] ?? $generation['id'] ?? $params['generation_id'] ?? $params['task_id'] ?? '',
                    ],
                    [
                        'key' => 'provider',
                        'value' => $this->get_provider_key(),
                    ],
                    [
                        'key' => 'prompt',
                        'value' => $params['prompt'] ?? '',
                    ],
                ],
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
                        'value' => 1,
                    ];
                }
            }

            // Automatically add extra meta from generation object
            foreach ($generation as $key => $value) {
                if (!in_array($key, ['imageBase64', 'imageURL', 'url', 'status', 'index', 'info'])) {
                    $image['meta_details'][] = [
                        'key' => $key,
                        'value' => is_array($value) ? json_encode($value) : (!empty($value) ? $value : '0'),
                    ];
                }
            }

            $saved_image = Growtype_Art_Crud::save_image($image, $save_to_db);

            if (empty($saved_image) || isset($saved_image['error'])) {
                error_log(sprintf('save_generations error for provider %s: %s', $this->get_provider_key(), json_encode($saved_image)));
                continue;
            }

            // DB-only operations: model-image link, compression, hooks
            if ($save_to_db && isset($saved_image['id']) && $model_id) {
                Growtype_Art_Database_Crud::insert_record(Growtype_Art_Database::MODEL_IMAGE_TABLE, [
                    'model_id' => $model_id,
                    'image_id' => $saved_image['id'],
                ]);

                growtype_art_compress_existing_image($saved_image['id']);
            }

            $saved_generations[] = [
                'url' => $saved_image['details']['url'] ?? '',
                'image_id' => $saved_image['id'] ?? null,
                'generation_id' => $generation['generation_id'] ?? $generation['id'] ?? $params['generation_id'] ?? $params['task_id'] ?? '',
                'image_prompt' => $params['prompt'] ?? '',
            ];
        }

        if ($save_to_db && $model_id) {
            do_action('growtype_art_model_update', $model_id);
        }

        return $saved_generations;
    }

    // ──────────────────────────────────────────────
    // Synchronous Generation (with polling)
    // ──────────────────────────────────────────────

    /**
     * Generate an image synchronously, polling for results if the provider is async.
     * Retries with next API token on 401 Unauthorized.
     */
    public function generate_image_sync($params)
    {
        try {
            $prompt = $params['prompt'] ?? '';
            $model_id = $params['model_id'] ?? null;
            $prompt_params = $params['prompt_params'] ?? null;

            $formatted_prompt = $this->prepare_prompt($prompt, $model_id, $prompt_params);

            $api_keys = $this->api_key();

            if (empty($api_keys)) {
                return ['success' => false, 'message' => 'Empty API keys.'];
            }

            $keys_to_try = array_keys($api_keys);
            shuffle($keys_to_try);

            $last_result = null;

            foreach ($keys_to_try as $api_group_key) {
                $params['token'] = $this->get_access_token($api_group_key);
                $params['prompt'] = $formatted_prompt;
                $params['generation_id'] = wp_generate_password(52, false);
                $params['api_group_key'] = $api_group_key;

                $generation_result = $this->generate_image_init($params);

                // If unauthorized, try the next token
                if (isset($generation_result['success']) && $generation_result['success'] === false && strpos($generation_result['message'] ?? '', 'Unauthorized') !== false) {
                    error_log(sprintf('Growtype Art - %s: Token %s failed with 401. Retrying with next token...', ucfirst($this->get_provider_key()), $api_group_key));
                    continue;
                }

                // Handle pending/polling
                if (isset($generation_result['status']) && $generation_result['status'] === 'pending' && (isset($generation_result['task_id']) || isset($generation_result['generation_id']))) {
                    $task_id = $generation_result['task_id'] ?? $generation_result['generation_id'];
                    $result = $this->poll_for_result($model_id, $task_id, $api_group_key, $params['prompt']);

                    if ($result) {
                        return $result;
                    }

                    return [
                        'success' => false,
                        'message' => 'Timeout: Image was not generated in time.',
                    ];
                }

                if (isset($generation_result['success']) && $generation_result['success']) {
                    return $generation_result;
                }

                $last_result = $generation_result;
            }

            return $last_result ?? [
                'success' => false,
                'message' => 'Something went wrong while trying all available tokens.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Poll for async generation results.
     *
     * @return array|null Success response with generations, or null on timeout.
     */
    private function poll_for_result($model_id, $task_id, $api_group_key, $prompt, $max_attempts = 20, $interval = 6)
    {
        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            sleep($interval);

            $generations = $this->retrieve_generations($model_id, [$task_id], [
                'api_group_key' => $api_group_key,
                'prompt' => $prompt,
            ]);

            error_log(sprintf('Growtype Art - %s: Polling attempt %d for %s.', ucfirst($this->get_provider_key()), $attempt, $task_id));

            if (!empty($generations)) {
                // Flatten nested array if needed
                $flat = (isset($generations[0]) && is_array($generations[0]) && array_values($generations[0]) === $generations[0] && isset($generations[0][0]) && is_array($generations[0][0]))
                    ? $generations[0]
                    : $generations;

                return [
                    'success' => true,
                    'generations' => $flat,
                    'message' => 'Image generated successfully',
                ];
            }
        }

        return null;
    }
}
