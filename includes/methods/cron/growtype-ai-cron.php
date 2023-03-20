<?php

class Growtype_Ai_Cron
{
    const GROWTYPE_AI_JOBS_CRON = 'growtype_ai_jobs';
    const GROWTYPE_AI_BUNDLE_JOBS_CRON = 'growtype_ai_bundle_jobs';

    const  RETRIEVE_JOBS_LIMIT = 5;
    const  JOBS_ATTEMPTS_LIMIT = 4;

    public function __construct()
    {
        add_action(self::GROWTYPE_AI_JOBS_CRON, array ($this, 'process_jobs'));

        add_action(self::GROWTYPE_AI_BUNDLE_JOBS_CRON, array ($this, 'generate_random_jobs'));

        add_filter('cron_schedules', array ($this, 'cron_custom_intervals'));

        add_action('wp_loaded', array (
            $this,
            'cron_activation'
        ));
    }

    function cron_custom_intervals()
    {
        $schedules['every10seconds'] = array (
            'interval' => 10,
            'display' => __('Once Every 10 seconds')
        );

        $schedules['every20seconds'] = array (
            'interval' => 20,
            'display' => __('Once Every 20 seconds')
        );

        $schedules['every30seconds'] = array (
            'interval' => 30,
            'display' => __('Once Every 30 seconds')
        );

        $schedules['everyminute'] = array (
            'interval' => 60,
            'display' => __('Once Every Minute')
        );

        $schedules['every5minute'] = array (
            'interval' => 60 * 5,
            'display' => __('Once Every 5 Minute')
        );

        $schedules['every10minute'] = array (
            'interval' => 60 * 10,
            'display' => __('Once Every 10 Minute')
        );

        $schedules['every30minute'] = array (
            'interval' => 60 * 30,
            'display' => __('Once Every 30 Minute')
        );

        return $schedules;
    }

    function cron_activation()
    {
        if (!wp_next_scheduled(self::GROWTYPE_AI_JOBS_CRON)) {
            wp_schedule_event(time(), 'every10seconds', self::GROWTYPE_AI_JOBS_CRON);
        }

        if (!wp_next_scheduled(self::GROWTYPE_AI_BUNDLE_JOBS_CRON)) {
            wp_schedule_event(time(), 'every10minute', self::GROWTYPE_AI_BUNDLE_JOBS_CRON);
        }
    }

    function process_jobs()
    {
        $jobs = Growtype_Ai_Database::get_records(Growtype_Ai_Database::MODEL_JOBS_TABLE);

        foreach ($jobs as $job) {
            $job_date = $job['available_at'];
            $job_payload = json_decode($job['payload'], true);

            if ($job_date > wp_date('Y-m-d H:i:s')) {
                continue;
            }

            /**
             * Limit attempts
             */
            if ((int)$job['attempts'] > self::JOBS_ATTEMPTS_LIMIT - 1) {
                continue;
            }

            /**
             * If already reserved, skip
             */
            if ((int)$job['reserved'] === 1) {
                continue;
            }

            switch ($job['queue']) {
                case 'generate-model':
                    if (!$this->new_generate_job_is_available()) {
                        break;
                    }

                    Growtype_Ai_Database::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
                        'reserved' => 1,
                        'attempts' => (int)$job['attempts'] + 1,
                    ], $job['id']);

                    try {
                        $crud = new Leonardo_Ai_Crud();
                        $crud->generate_model($job_payload['model_id']);

                        /**
                         * Delete job
                         */
                        Growtype_Ai_Database::delete_records(Growtype_Ai_Database::MODEL_JOBS_TABLE, [$job['id']]);
                    } catch (Exception $e) {
                        Growtype_Ai_Database::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
                            'reserved' => 0,
                            'exception' => $e->getMessage(),
                        ], $job['id']);
                    }

                    break;
                case 'retrieve-model':

                    Growtype_Ai_Database::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
                        'reserved' => 1,
                        'attempts' => (int)$job['attempts'] + 1,
                    ], $job['id']);

                    try {
                        $crud = new Leonardo_Ai_Crud();

                        $crud->retrieve_single_generation($job_payload['model_id'], $job_payload['user_nr'], $job_payload['generation_id']);

                        /**
                         * Delete job
                         */
                        Growtype_Ai_Database::delete_records(Growtype_Ai_Database::MODEL_JOBS_TABLE, [$job['id']]);
                    } catch (Exception $e) {
                        Growtype_Ai_Database::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
                            'reserved' => 0,
                            'exception' => $e->getMessage(),
                        ], $job['id']);
                    }

                    sleep(5);

                    break;
                case 'retrieve-upscale-image':

                    Growtype_Ai_Database::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
                        'reserved' => 1,
                        'attempts' => (int)$job['attempts'] + 1,
                    ], $job['id']);

                    /**
                     * Try to retrieve the image
                     */
                    $get_url = $job_payload['upscaled_image']['urls']['get'];

                    $replicate = new Replicate();

                    $retrieve = $replicate->real_esrgan_retrieve($get_url);

                    $output = $retrieve['output'];

                    error_log(print_r([
                        'action' => 'retrieved',
                        'url' => $output
                    ], true));

                    if (!empty($output)) {
                        try {
                            /**
                             * Compress image
                             */
                            $resmush = new Resmush();

                            $img_url = $resmush->compress($output);

                            if (empty($img_url)) {
                                Growtype_Ai_Database::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
                                    'exception' => 'Image not compressed',
                                    'reserved' => 0
                                ], $job['id']);

                                break;
                            }

                            error_log(print_r([
                                'action' => 'compressed',
                                'url' => $img_url,
                                'public_id' => $job_payload['original_image']['public_id'],
                            ], true));

                            $cloudinary = new Cloudinary_Crud();

                            $public_id = $job_payload['original_image']['public_id'];

                            $cloudinary->upload_asset($img_url, [
                                'public_id' => $public_id
                            ]);

                            $cloudinary->add_context($public_id, [
                                'real_esrgan' => 'true',
                                'compressed' => 'true'
                            ]);

                            $image_id = $job_payload['original_image']['id'];

                            $image = growtype_ai_get_image_details($image_id);

                            if (!isset($image['settings']['real_esrgan'])) {
                                Growtype_Ai_Database::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                                    'image_id' => $image_id,
                                    'meta_key' => 'real_esrgan',
                                    'meta_value' => 'true',
                                ]);
                            }

                            if (!isset($image['settings']['compressed'])) {
                                Growtype_Ai_Database::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                                    'image_id' => $image_id,
                                    'meta_key' => 'compressed',
                                    'meta_value' => 'true',
                                ]);
                            }

                            Growtype_Ai_Database::delete_records(Growtype_Ai_Database::MODEL_JOBS_TABLE, [$job['id']]);
                        } catch (Exception $e) {
                            Growtype_Ai_Database::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
                                'exception' => $e->getMessage(),
                            ], $job['id']);

                            break;
                        }
                    } else {
                        Growtype_Ai_Database::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
                            'exception' => 'Not generated yet',
                            'reserved' => 0
                        ], $job['id']);

                        break;
                    }

                    sleep(5);

                    break;
                case 'generate-content-model':

                    Growtype_Ai_Database::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
                        'reserved' => 1,
                        'attempts' => (int)$job['attempts'] + 1,
                    ], $job['id']);

                    $existing_content = growtype_ai_get_model_single_setting($job_payload['model_id'], $job_payload['meta_key']);

                    $openai_crud = new Openai_Crud();
                    $new_content = $openai_crud->generate_content($job_payload['prompt'], $job_payload['meta_key']);

                    if (empty($new_content)) {
                        Growtype_Ai_Database::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
                            'exception' => 'empty content',
                            'reserved' => 0
                        ], $job['id']);

                        continue;
                    }

                    if ($job_payload['encode']) {
                        $new_content = json_decode($new_content, true);
                        $new_content = json_encode($new_content);
                    } else {
                        $new_content = str_replace('"', "", $new_content);
                    }

                    if (empty($new_content)) {
                        Growtype_Ai_Database::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
                            'exception' => 'empty content',
                            'reserved' => 0
                        ], $job['id']);

                        continue;
                    }

                    /**
                     * tags
                     */
                    if (!empty($existing_content)) {
                        Growtype_Ai_Database::update_record(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
                            'model_id' => $job_payload['model_id'],
                            'meta_key' => $job_payload['meta_key'],
                            'meta_value' => $new_content,
                        ], $existing_content['id']);
                    } else {
                        Growtype_Ai_Database::insert_record(Growtype_Ai_Database::MODEL_SETTINGS_TABLE, [
                            'model_id' => $job_payload['model_id'],
                            'meta_key' => $job_payload['meta_key'],
                            'meta_value' => $new_content,
                        ]);
                    }

                    Growtype_Ai_Database::delete_records(Growtype_Ai_Database::MODEL_JOBS_TABLE, [$job['id']]);

                    break;
                case 'generate-content-image':

                    Growtype_Ai_Database::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
                        'reserved' => 1,
                        'attempts' => (int)$job['attempts'] + 1,
                    ], $job['id']);

                    $model = growtype_ai_get_image_model_details($job_payload['image_id']);

                    if (empty($model)) {
                        Growtype_Ai_Database::update_record(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
                            'exception' => 'Empty model for image',
                            'reserved' => 0
                        ], $job['id']);

                        continue;
                    }

                    $tags = !empty($model) && isset($model['settings']['tags']) && !empty($model['settings']['tags']) ? json_decode($model['settings']['tags'], true) : [];
                    $title = !empty($model) ? $model['settings']['title'] : null;
                    $description = !empty($model) ? $model['settings']['description'] : null;

                    if (!isset($image['settings']['caption'])) {
                        $openai_crud = new Openai_Crud();
                        $alt_title = $openai_crud->generate_content($title, 'alt-title');

                        if (!empty($alt_title)) {
                            $alt_title = str_replace('"', "", $alt_title);
                            $alt_title = str_replace("'", "", $alt_title);

                            Growtype_Ai_Database::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                                'image_id' => $job_payload['image_id'],
                                'meta_key' => 'caption',
                                'meta_value' => $alt_title,
                            ]);
                        }
                    }

                    if (!isset($image['settings']['alt_text'])) {
                        $openai_crud = new Openai_Crud();
                        $alt_description = $openai_crud->generate_content($description, 'alt-description');

                        if (!empty($alt_description)) {
                            $alt_description = str_replace('"', "", $alt_description);

                            Growtype_Ai_Database::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                                'image_id' => $job_payload['image_id'],
                                'meta_key' => 'alt_text',
                                'meta_value' => $alt_description,
                            ]);
                        }
                    }

                    if (!isset($image['settings']['tags'])) {
                        Growtype_Ai_Database::insert_record(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE, [
                            'image_id' => $job_payload['image_id'],
                            'meta_key' => 'tags',
                            'meta_value' => !empty($tags) ? json_encode($tags) : null,
                        ]);
                    }

                    Growtype_Ai_Database::delete_records(Growtype_Ai_Database::MODEL_JOBS_TABLE, [$job['id']]);

                    break;
            }
        }
    }

    function new_generate_job_is_available()
    {
        $retrieve_jobs = Growtype_Ai_Database::get_records(Growtype_Ai_Database::MODEL_JOBS_TABLE, [
            [
                'key' => 'queue',
                'values' => ['retrieve-model'],
            ]
        ]);

        $expired_jobs = [];
        foreach ($retrieve_jobs as $retrieve_job) {
            if ((int)$retrieve_job['attempts'] === self::JOBS_ATTEMPTS_LIMIT) {
                array_push($expired_jobs, $retrieve_job['id']);
            }
        }

        $existing_retrieve_jobs_amount = count($retrieve_jobs) - count($expired_jobs);

        /**
         * Do not generate more than 5 retrieve jobs at the same time
         */
        if ($existing_retrieve_jobs_amount >= self::RETRIEVE_JOBS_LIMIT) {
            return false;
        }

        return true;
    }

    function generate_random_jobs()
    {
        $models = Growtype_Ai_Database::get_records(Growtype_Ai_Database::MODELS_TABLE);
        $bundle_ids = explode(',', get_option('growtype_ai_bundle_ids'));

        if (empty($bundle_ids)) {
            return;
        }

        foreach ($models as $model) {
            if (!in_array($model['id'], $bundle_ids)) {
                continue;
            }

            growtype_ai_init_job('generate-model', json_encode(['model_id' => $model['id']]));

            sleep(5);
        }
    }
}
