<?php

/**
 * Fired during plugin deactivation
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Growtype_Ai
 * @subpackage growtype_ai/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Growtype_Ai
 * @subpackage growtype_ai/includes
 * @author     Your Name <email@example.com>
 */
class Growtype_Ai_Deactivator
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
         * Cron
         */
        $timestamp = wp_next_scheduled(Growtype_Ai_Cron::GROWTYPE_AI_JOBS_CRON);
        wp_unschedule_event($timestamp, Growtype_Ai_Cron::GROWTYPE_AI_JOBS_CRON);

        $timestamp = wp_next_scheduled(Growtype_Ai_Cron::GROWTYPE_AI_BUNDLE_JOBS_CRON);
        wp_unschedule_event($timestamp, Growtype_Ai_Cron::GROWTYPE_AI_BUNDLE_JOBS_CRON);
    }

}
