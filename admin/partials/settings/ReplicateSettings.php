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

class ReplicateSettings
{
    public function general_content()
    {
        /**
         *
         */
        register_setting(
            'growtype_ai_settings',
            'growtype_ai_replicate_api_key'
        );

        add_settings_field(
            'growtype_ai_replicate_api_key',
            'Api key',
            array ($this, 'growtype_ai_replicate_api_key_callback'),
            'growtype-ai-settings',
            'growtype_ai_replicate_settings'
        );

        /**
         *
         */
        register_setting(
            'growtype_ai_settings',
            'growtype_ai_replicate_enabled'
        );

        add_settings_field(
            'growtype_ai_replicate_enabled',
            'Enabled',
            array ($this, 'growtype_ai_replicate_enabled_callback'),
            'growtype-ai-settings',
            'growtype_ai_replicate_settings'
        );
    }

    /**
     *
     */
    function growtype_ai_replicate_api_key_callback()
    {
        $value = preg_replace("/\s+/", "", get_option('growtype_ai_replicate_api_key'));
        ?>
        <input type="text" class="regular-text ltr" name="growtype_ai_replicate_api_key" value="<?php echo $value ?>"/>
        <?php
    }

    /**
     *
     */
    function growtype_ai_replicate_enabled_callback()
    {
        $value = get_option('growtype_ai_replicate_enabled');
        ?>
        <input type="checkbox" class="regular-text ltr" name="growtype_ai_replicate_enabled" <?php checked($value, 1) ?> value="1"/>
        <?php
    }
}


