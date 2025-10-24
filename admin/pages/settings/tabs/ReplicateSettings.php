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
    public function __construct()
    {
        add_action('admin_init', array ($this, 'admin_settings'));

        add_filter('growtype_art_admin_settings_tabs', array ($this, 'settings_tab'));
    }

    function settings_tab($tabs)
    {
        $tabs['replicate'] = 'Replicate';

        return $tabs;
    }

    function admin_settings()
    {
        /**
         *
         */
        register_setting(
            'growtype_art_settings_replicate',
            'growtype_art_replicate_api_key'
        );

        add_settings_field(
            'growtype_art_replicate_api_key',
            'Api key',
            array ($this, 'growtype_art_replicate_api_key_callback'),
            Growtype_Art_Admin::SETTINGS_PAGE_NAME,
            'growtype_art_replicate_settings'
        );

        /**
         *
         */
        register_setting(
            'growtype_art_settings_replicate',
            'growtype_art_replicate_upscale_uploaded_images'
        );

        add_settings_field(
            'growtype_art_replicate_upscale_uploaded_images',
            'Upscale uploaded images',
            array ($this, 'growtype_art_replicate_upscale_uploaded_images_callback'),
            Growtype_Art_Admin::SETTINGS_PAGE_NAME,
            'growtype_art_replicate_settings'
        );
    }

    /**
     *
     */
    function growtype_art_replicate_api_key_callback()
    {
        $value = preg_replace("/\s+/", "", get_option('growtype_art_replicate_api_key'));
        ?>
        <input type="text" class="regular-text ltr" name="growtype_art_replicate_api_key" value="<?php echo $value ?>"/>
        <?php
    }

    /**
     *
     */
    function growtype_art_replicate_upscale_uploaded_images_callback()
    {
        $value = get_option('growtype_art_replicate_upscale_uploaded_images');
        ?>
        <input type="checkbox" class="regular-text ltr" name="growtype_art_replicate_upscale_uploaded_images" <?php checked($value, 1) ?> value="1"/>
        <?php
    }
}


