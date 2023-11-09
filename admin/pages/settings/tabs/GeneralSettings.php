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

class GeneralSettings
{
    public function __construct()
    {
        add_action('admin_init', array ($this, 'admin_settings'));

        add_filter('growtype_ai_admin_settings_tabs', array ($this, 'settings_tab'));
    }

    function settings_tab($tabs)
    {
        $tabs['general'] = 'General';

        return $tabs;
    }

    function admin_settings()
    {
        /**
         *
         */
        register_setting(
            'growtype_ai_settings_general',
            'growtype_ai_images_saving_location'
        );

        add_settings_field(
            'growtype_ai_images_saving_location',
            'Images saving location',
            array ($this, 'growtype_ai_images_saving_location_callback'),
            Growtype_Ai_Admin::SETTINGS_PAGE_NAME,
            'growtype_ai_image_generating_settings'
        );

        /**
         *
         */
        register_setting(
            'growtype_ai_settings_general',
            'growtype_ai_bundle_ids'
        );

        add_settings_field(
            'growtype_ai_bundle_ids',
            'Bundle ids (separated by comma)',
            array ($this, 'growtype_ai_bundle_ids_callback'),
            Growtype_Ai_Admin::SETTINGS_PAGE_NAME,
            'growtype_ai_image_generating_settings'
        );
    }

    /**
     *
     */
    function growtype_ai_images_saving_location_callback()
    {
        $value = get_option('growtype_ai_images_saving_location');

        $options = [
            'locally' => 'locally',
            'cloudinary' => 'cloudinary',
        ];
        ?>
        <select name="growtype_ai_images_saving_location">
            <?php foreach ($options as $key => $option) : ?>
                <option value="<?php echo $key; ?>" <?php echo $value == $key ? 'selected' : ''; ?>>
                    <?php echo $option; ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    /**
     *
     */
    function growtype_ai_bundle_ids_callback()
    {
        $value = preg_replace("/\s+/", "", get_option('growtype_ai_bundle_ids'));
        ?>
        <input type="text" class="regular-text ltr" name="growtype_ai_bundle_ids" value="<?php echo $value ?>"/>
        <?php
    }
}


