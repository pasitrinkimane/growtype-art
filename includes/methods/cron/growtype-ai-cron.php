<?php

class Growtype_Ai_Cron
{
    const GROWTYPE_AI_BUNDLE_JOBS_CRON = 'growtype_ai_bundle_jobs';
    const GROWTYPE_AI_COMPRESS_IMAGES_JOB_CRON = 'growtype_ai_compress_images';

    public function __construct()
    {
        add_filter('growtype_cron_load_jobs', [$this, 'growtype_cron_load_jobs'], 10);

        add_action(self::GROWTYPE_AI_BUNDLE_JOBS_CRON, array ($this, 'generate_bundle_jobs'));

        add_action(self::GROWTYPE_AI_COMPRESS_IMAGES_JOB_CRON, array ($this, 'generate_compress_images_job'));

        add_action('wp_loaded', array (
            $this,
            'cron_activation'
        ));
    }

    function cron_activation()
    {
        if (!wp_next_scheduled(self::GROWTYPE_AI_BUNDLE_JOBS_CRON)) {
            wp_schedule_event(time(), 'hourly', self::GROWTYPE_AI_BUNDLE_JOBS_CRON);
        }

        if (!wp_next_scheduled(self::GROWTYPE_AI_COMPRESS_IMAGES_JOB_CRON)) {
            wp_schedule_event(time(), 'hourly', self::GROWTYPE_AI_COMPRESS_IMAGES_JOB_CRON);
        }
    }

    function growtype_cron_load_jobs($jobs)
    {
        $jobs = array_merge($jobs, [
            'extract-image-colors' => [
                'classname' => 'Extract_Image_Colors_Job',
                'path' => GROWTYPE_AI_PATH . '/includes/methods/cron/jobs/Extract_Image_Colors_Job.php',
            ],
            'generate-image-content' => [
                'classname' => 'Generate_Image_Content_Job',
                'path' => GROWTYPE_AI_PATH . '/includes/methods/cron/jobs/Generate_Image_Content_Job.php',
            ],
            'optimize-database' => [
                'classname' => 'Optimize_Database_Job',
                'path' => GROWTYPE_AI_PATH . '/includes/methods/cron/jobs/Optimize_Database_Job.php',
            ],
            'retrieve-model' => [
                'classname' => 'Retrieve_Model_Job',
                'path' => GROWTYPE_AI_PATH . '/includes/methods/cron/jobs/Retrieve_Model_Job.php',
            ],
            'upscale-image' => [
                'classname' => 'Upscale_Image_Job',
                'path' => GROWTYPE_AI_PATH . '/includes/methods/cron/jobs/Upscale_Image_Job.php',
            ],
            'upscale-image-local' => [
                'classname' => 'Upscale_Image_Local_Job',
                'path' => GROWTYPE_AI_PATH . '/includes/methods/cron/jobs/Upscale_Image_Local_Job.php',
            ],
            'retrieve-upscale-image' => [
                'classname' => 'Retrieve_Upscale_Image_Job',
                'path' => GROWTYPE_AI_PATH . '/includes/methods/cron/jobs/Retrieve_Upscale_Image_Job.php',
            ],
            'generate-model-content' => [
                'classname' => 'Generate_Model_Content_Job',
                'path' => GROWTYPE_AI_PATH . '/includes/methods/cron/jobs/Generate_Model_Content_Job.php',
            ],
            'generate-model' => [
                'classname' => 'Generate_Model_Job',
                'path' => GROWTYPE_AI_PATH . '/includes/methods/cron/jobs/Generate_Model_Job.php',
            ],
            'download-model-images' => [
                'classname' => 'Download_Model_Images_Job',
                'path' => GROWTYPE_AI_PATH . '/includes/methods/cron/jobs/Download_Model_Images_Job.php',
            ],
            'download-cloudinary-folder' => [
                'classname' => 'Download_Cloudinary_Folder_Job',
                'path' => GROWTYPE_AI_PATH . '/includes/methods/cron/jobs/Download_Cloudinary_Folder_Job.php',
            ],
            'model-assign-categories' => [
                'classname' => 'Model_Assign_Categories_Job',
                'path' => GROWTYPE_AI_PATH . '/includes/methods/cron/jobs/Model_Assign_Categories_Job.php',
            ],
            'compress-images' => [
                'classname' => 'Compress_Images_Job',
                'path' => GROWTYPE_AI_PATH . '/includes/methods/cron/jobs/Compress_Images_Job.php',
            ],
            'generate-meal-plan' => [
                'classname' => 'Generate_Meal_Plan_Job',
                'path' => GROWTYPE_AI_PATH . '/includes/methods/cron/jobs/Generate_Meal_Plan_Job.php',
            ],
            'generate-meal' => [
                'classname' => 'Generate_Meal_Job',
                'path' => GROWTYPE_AI_PATH . '/includes/methods/cron/jobs/Generate_Meal_Job.php',
            ],
        ]);

        return $jobs;
    }

    function generate_bundle_jobs()
    {
        $bundle_ids = explode(',', get_option('growtype_ai_bundle_ids'));

        if (empty($bundle_ids)) {
            return;
        }

        $delay = 0;
        foreach ($bundle_ids as $bundle_id) {
            $existing_content = growtype_ai_get_model_single_setting($bundle_id, 'generatable_images_limit');

            if (!empty($existing_content)) {
                $model_images = growtype_ai_get_model_images($bundle_id);
                if (!empty($existing_content['meta_value']) && count($model_images) >= (int)$existing_content['meta_value']) {
                    continue;
                }
            }

            if (empty($bundle_id)) {
                continue;
            }

            growtype_cron_init_job('generate-model', json_encode(['model_id' => $bundle_id]), $delay);

            $delay += 20;
        }
    }

    function generate_compress_images_job()
    {
        $images_limit = 40;
        $models = growtype_ai_get_featured_in_group_images(['talkiemate'], ['admin', 'external_user']);

        $images_ids = [];
        foreach ($models as $model) {
            $images = growtype_ai_get_model_images($model['id']);

            foreach ($images as $image) {
                if (isset($image['settings']['compressed'])) {
                    continue;
                }

                array_push($images_ids, $image['id']);

                if (count($images_ids) > $images_limit) {
                    break;
                }
            }

            if (count($images_ids) > $images_limit) {
                break;
            }
        }

        if (empty($images_ids)) {
            return;
        }

        $delay = 10;

        growtype_cron_init_job('compress-images', json_encode(['images_ids' => $images_ids]), $delay);
    }
}
