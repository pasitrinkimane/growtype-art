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

class CloudinarySettings
{
    public function general_content()
    {
        /**
         *
         */
        register_setting(
            'growtype_ai_settings',
            'growtype_ai_cloudinary_cloudname'
        );

        add_settings_field(
            'growtype_ai_cloudinary_cloudname',
            'Cloudname',
            array ($this, 'growtype_ai_cloudinary_cloudname_callback'),
            'growtype-ai-settings',
            'growtype_ai_cloudinary_settings'
        );

        /**
         *
         */
        register_setting(
            'growtype_ai_settings',
            'growtype_ai_cloudinary_apikey'
        );

        add_settings_field(
            'growtype_ai_cloudinary_apikey',
            'ApiKey',
            array ($this, 'growtype_ai_cloudinary_apikey_callback'),
            'growtype-ai-settings',
            'growtype_ai_cloudinary_settings'
        );

        /**
         *
         */
        register_setting(
            'growtype_ai_settings',
            'growtype_ai_cloudinary_apisecret'
        );

        add_settings_field(
            'growtype_ai_cloudinary_apisecret',
            'ApiSecret',
            array ($this, 'growtype_ai_cloudinary_apisecret_callback'),
            'growtype-ai-settings',
            'growtype_ai_cloudinary_settings'
        );
    }

    /**
     *
     */
    function growtype_ai_cloudinary_cloudname_callback()
    {
        $value = preg_replace("/\s+/", "", get_option('growtype_ai_cloudinary_cloudname'));
        ?>
        <input type="text" class="regular-text ltr" name="growtype_ai_cloudinary_cloudname" value="<?php echo $value ?>"/>
        <?php
    }

    /**
     *
     */
    function growtype_ai_cloudinary_apikey_callback()
    {
        $value = preg_replace("/\s+/", "", get_option('growtype_ai_cloudinary_apikey'));
        ?>
        <input type="text" class="regular-text ltr" name="growtype_ai_cloudinary_apikey" value="<?php echo $value ?>"/>
        <?php
    }

    /**
     *
     */
    function growtype_ai_cloudinary_apisecret_callback()
    {
        $value = preg_replace("/\s+/", "", get_option('growtype_ai_cloudinary_apisecret'));
        ?>
        <input type="text" class="regular-text ltr" name="growtype_ai_cloudinary_apisecret" value="<?php echo $value ?>"/>
        <?php
    }
}


