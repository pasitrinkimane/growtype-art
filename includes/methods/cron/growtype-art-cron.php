<?php

class Growtype_Art_Cron
{
    const GROWTYPE_ART_BUNDLE_JOBS_CRON = 'growtype_art_bundle_jobs';
    const GROWTYPE_ART_COMPRESS_IMAGES_JOB_CRON = 'growtype_art_compress_images';

    public function __construct()
    {
        add_filter('growtype_cron_load_jobs', [$this, 'growtype_cron_load_jobs'], 10);

        add_action(self::GROWTYPE_ART_BUNDLE_JOBS_CRON, array ($this, 'generate_bundle_jobs'));

        add_action(self::GROWTYPE_ART_COMPRESS_IMAGES_JOB_CRON, array ($this, 'generate_compress_images_job'));

        add_action('wp_loaded', array (
            $this,
            'cron_activation'
        ));
    }

    function cron_activation()
    {
        if (!wp_next_scheduled(self::GROWTYPE_ART_BUNDLE_JOBS_CRON)) {
            wp_schedule_event(time(), 'hourly', self::GROWTYPE_ART_BUNDLE_JOBS_CRON);
        }

        if (!wp_next_scheduled(self::GROWTYPE_ART_COMPRESS_IMAGES_JOB_CRON)) {
            wp_schedule_event(time(), 'hourly', self::GROWTYPE_ART_COMPRESS_IMAGES_JOB_CRON);
        }
    }

    function growtype_cron_load_jobs($jobs)
    {
        $jobs = array_merge($jobs, [
            'extract-image-colors' => [
                'classname' => 'Extract_Image_Colors_Job',
                'path' => GROWTYPE_ART_PATH . '/includes/methods/cron/jobs/Extract_Image_Colors_Job.php',
            ],
            'generate-image-content' => [
                'classname' => 'Generate_Image_Content_Job',
                'path' => GROWTYPE_ART_PATH . '/includes/methods/cron/jobs/Generate_Image_Content_Job.php',
            ],
            'optimize-database' => [
                'classname' => 'Optimize_Database_Job',
                'path' => GROWTYPE_ART_PATH . '/includes/methods/cron/jobs/Optimize_Database_Job.php',
            ],
            'retrieve-model' => [
                'classname' => 'Retrieve_Model_Job',
                'path' => GROWTYPE_ART_PATH . '/includes/methods/cron/jobs/Retrieve_Model_Job.php',
            ],
            'upscale-image' => [
                'classname' => 'Upscale_Image_Job',
                'path' => GROWTYPE_ART_PATH . '/includes/methods/cron/jobs/Upscale_Image_Job.php',
            ],
            'upscale-image-local' => [
                'classname' => 'Upscale_Image_Local_Job',
                'path' => GROWTYPE_ART_PATH . '/includes/methods/cron/jobs/Upscale_Image_Local_Job.php',
            ],
            'retrieve-upscale-image' => [
                'classname' => 'Retrieve_Upscale_Image_Job',
                'path' => GROWTYPE_ART_PATH . '/includes/methods/cron/jobs/Retrieve_Upscale_Image_Job.php',
            ],
            'retrieve-faceswap-image' => [
                'classname' => 'Retrieve_Faceswap_Image_Job',
                'path' => GROWTYPE_ART_PATH . '/includes/methods/cron/jobs/Retrieve_Faceswap_Image_Job.php',
            ],
            'generate-model-content' => [
                'classname' => 'Generate_Model_Content_Job',
                'path' => GROWTYPE_ART_PATH . '/includes/methods/cron/jobs/Generate_Model_Content_Job.php',
            ],
            'generate-model' => [
                'classname' => 'Generate_Model_Job',
                'path' => GROWTYPE_ART_PATH . '/includes/methods/cron/jobs/Generate_Model_Job.php',
            ],
            'download-model-images' => [
                'classname' => 'Download_Model_Images_Job',
                'path' => GROWTYPE_ART_PATH . '/includes/methods/cron/jobs/Download_Model_Images_Job.php',
            ],
            'download-cloudinary-folder' => [
                'classname' => 'Download_Cloudinary_Folder_Job',
                'path' => GROWTYPE_ART_PATH . '/includes/methods/cron/jobs/Download_Cloudinary_Folder_Job.php',
            ],
            'model-assign-categories' => [
                'classname' => 'Model_Assign_Categories_Job',
                'path' => GROWTYPE_ART_PATH . '/includes/methods/cron/jobs/Model_Assign_Categories_Job.php',
            ],
            'compress-images' => [
                'classname' => 'Compress_Images_Job',
                'path' => GROWTYPE_ART_PATH . '/includes/methods/cron/jobs/Compress_Images_Job.php',
            ],
            'generate-meal-plan' => [
                'classname' => 'Generate_Meal_Plan_Job',
                'path' => GROWTYPE_ART_PATH . '/includes/methods/cron/jobs/Generate_Meal_Plan_Job.php',
            ],
            'generate-meal' => [
                'classname' => 'Generate_Meal_Job',
                'path' => GROWTYPE_ART_PATH . '/includes/methods/cron/jobs/Generate_Meal_Job.php',
            ],
            'webhook-update-image' => [
                'classname' => 'Webhook_Update_Image_Job',
                'path' => GROWTYPE_ART_PATH . '/includes/methods/cron/jobs/Webhook_Update_Image_Job.php',
            ],
            'webhook-update-model' => [
                'classname' => 'Webhook_Update_Model_Job',
                'path' => GROWTYPE_ART_PATH . '/includes/methods/cron/jobs/Webhook_Update_Model_Job.php',
            ],
        ]);

        return $jobs;
    }

    function generate_bundle_jobs()
    {
        $bundle_ids = explode(',', get_option('growtype_art_bundle_ids'));

        if (empty($bundle_ids)) {
            return;
        }

        $delay = 0;
        foreach ($bundle_ids as $bundle_id) {
            $generatable_images_limit = growtype_art_get_model_single_setting($bundle_id, 'generatable_images_limit');
            $generatable_images_limit = isset($generatable_images_limit['meta_value']) && !empty($generatable_images_limit['meta_value']) ? (int)$generatable_images_limit['meta_value'] : 3;

            $model_images = growtype_art_get_model_images_grouped($bundle_id)['original'] ?? [];

            if (count($model_images) >= $generatable_images_limit) {
                continue;
            }

            if (empty($bundle_id)) {
                continue;
            }

            $model = growtype_art_get_model_details($bundle_id);

            if (!isset($model['provider'])) {
                return;
            }

            Growtype_Cron_Jobs::create_if_not_exists('generate-model', json_encode([
                'model_id' => $bundle_id,
                'provider' => $model['provider']
            ]), $delay);

            $delay += 20;
        }
    }

    function generate_compress_images_job()
    {
        global $wpdb;

        $images_limit = 20;

        $query = "
    SELECT s.image_id
    FROM {$wpdb->prefix}growtype_art_image_settings AS s
    LEFT JOIN {$wpdb->prefix}growtype_art_image_settings AS sub
    ON s.image_id = sub.image_id
       AND sub.meta_key = 'compressed'
       AND sub.meta_value = 'true'
    WHERE sub.image_id IS NULL
    GROUP BY s.image_id
    ORDER BY s.image_id DESC
    LIMIT $images_limit
";

        $results = $wpdb->get_results($query, ARRAY_A);

        $images_ids = array_pluck($results, 'image_id');

        growtype_cron_init_job('compress-images', json_encode(['images_ids' => $images_ids]), 10);
    }
}
