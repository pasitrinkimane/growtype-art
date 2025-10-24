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
    public function __construct()
    {
        add_action('admin_init', array ($this, 'admin_settings'));

        add_filter('growtype_art_admin_settings_tabs', array ($this, 'settings_tab'));
    }

    function settings_tab($tabs)
    {
        $tabs['cloudinary'] = 'Cloudinary';

        return $tabs;
    }

    function admin_settings()
    {
        /**
         *
         */
        register_setting(
            'growtype_art_settings_cloudinary',
            'growtype_art_cloudinary_cloudname'
        );

        add_settings_field(
            'growtype_art_cloudinary_cloudname',
            'Cloudname',
            array ($this, 'growtype_art_cloudinary_cloudname_callback'),
            Growtype_Art_Admin::SETTINGS_PAGE_NAME,
            'growtype_art_cloudinary_settings'
        );

        /**
         *
         */
        register_setting(
            'growtype_art_settings_cloudinary',
            'growtype_art_cloudinary_apikey'
        );

        add_settings_field(
            'growtype_art_cloudinary_apikey',
            'ApiKey',
            array ($this, 'growtype_art_cloudinary_apikey_callback'),
            Growtype_Art_Admin::SETTINGS_PAGE_NAME,
            'growtype_art_cloudinary_settings'
        );

        /**
         *
         */
        register_setting(
            'growtype_art_settings_cloudinary',
            'growtype_art_cloudinary_apisecret'
        );

        add_settings_field(
            'growtype_art_cloudinary_apisecret',
            'ApiSecret',
            array ($this, 'growtype_art_cloudinary_apisecret_callback'),
            Growtype_Art_Admin::SETTINGS_PAGE_NAME,
            'growtype_art_cloudinary_settings'
        );
    }

    /**
     *
     */
    function growtype_art_cloudinary_cloudname_callback()
    {
        $value = preg_replace("/\s+/", "", get_option('growtype_art_cloudinary_cloudname'));
        ?>
        <input type="text" class="regular-text ltr" name="growtype_art_cloudinary_cloudname" value="<?php echo $value ?>"/>
        <?php
    }

    /**
     *
     */
    function growtype_art_cloudinary_apikey_callback()
    {
        $value = preg_replace("/\s+/", "", get_option('growtype_art_cloudinary_apikey'));
        ?>
        <input type="text" class="regular-text ltr" name="growtype_art_cloudinary_apikey" value="<?php echo $value ?>"/>
        <?php
    }

    /**
     *
     */
    function growtype_art_cloudinary_apisecret_callback()
    {
        $value = preg_replace("/\s+/", "", get_option('growtype_art_cloudinary_apisecret'));
        ?>
        <input type="text" class="regular-text ltr" name="growtype_art_cloudinary_apisecret" value="<?php echo $value ?>"/>
        <?php
    }
}


