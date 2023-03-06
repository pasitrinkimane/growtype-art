<?php

class Growtype_Ai_Cron
{
    const GROWTYPE_AI_JOBS_CRON = 'growtype_ai_jobs';
    const GROWTYPE_AI_BUNDLE_JOBS_CRON = 'growtype_ai_bundle_jobs';

    const  RETRIEVE_JOBS_LIMIT = 3;
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
            wp_schedule_event(time(), 'everyminute', self::GROWTYPE_AI_JOBS_CRON);
        }

        if (!wp_next_scheduled(self::GROWTYPE_AI_BUNDLE_JOBS_CRON)) {
            wp_schedule_event(time(), 'every5minute', self::GROWTYPE_AI_BUNDLE_JOBS_CRON);
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

            growtype_ai_init_generate_image_job(json_encode(['model_id' => $model['id']]));

            sleep(5);
        }
    }
}
