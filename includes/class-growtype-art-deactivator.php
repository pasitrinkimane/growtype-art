<?php

/**
 * Fired during plugin deactivation
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Growtype_Art
 * @subpackage growtype_art/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Growtype_Art
 * @subpackage growtype_art/includes
 * @author     Your Name <email@example.com>
 */
class Growtype_Art_Deactivator
{

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function deactivate()
    {
        global $wp_rewrite;
        $wp_rewrite->flush_rules();

        /**
         * Cron jobs
         */
        $timestamp = wp_next_scheduled(Growtype_Art_Cron::GROWTYPE_ART_BUNDLE_JOBS_CRON);
        wp_unschedule_event($timestamp, Growtype_Art_Cron::GROWTYPE_ART_BUNDLE_JOBS_CRON);

        $timestamp = wp_next_scheduled(Growtype_Art_Cron::GROWTYPE_ART_COMPRESS_IMAGES_JOB_CRON);
        wp_unschedule_event($timestamp, Growtype_Art_Cron::GROWTYPE_ART_COMPRESS_IMAGES_JOB_CRON);
    }

}
