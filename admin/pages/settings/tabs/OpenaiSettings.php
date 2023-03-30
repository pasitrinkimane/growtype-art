<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    growtype_quiz
 * @subpackage growtype_quiz/admin/partials
 */

class OpenaiSettings
{
    public function __construct()
    {
        add_action('admin_init', array ($this, 'admin_settings'));

        add_filter('growtype_ai_admin_settings_tabs', array ($this, 'settings_tab'));
    }

    function settings_tab($tabs)
    {
        $tabs['openai'] = 'Openai';

        return $tabs;
    }

    function admin_settings()
    {
        /**
         *
         */
        register_setting(
            'growtype_ai_settings_openai',
            'growtype_ai_openai_api_key'
        );

        add_settings_field(
            'growtype_ai_openai_api_key',
            'Api key',
            array ($this, 'growtype_ai_openai_api_key_callback'),
            Growtype_Ai_Admin::SETTINGS_PAGE_NAME,
            'growtype_ai_openai_settings'
        );
    }

    /**
     *
     */
    function growtype_ai_openai_api_key_callback()
    {
        $value = preg_replace("/\s+/", "", get_option('growtype_ai_openai_api_key'));
        ?>
        <input type="text" class="regular-text ltr" name="growtype_ai_openai_api_key" value="<?php echo $value ?>"/>
        <?php
    }
}


