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

class EvaluationSettings
{
    public function __construct()
    {
        add_action('admin_init', array ($this, 'admin_settings'));

        add_filter('growtype_ai_admin_settings_tabs', array ($this, 'settings_tab'));
    }

    function settings_tab($tabs)
    {
        $tabs['evaluation'] = 'Evaluation';

        return $tabs;
    }

    function admin_settings()
    {
        /**
         *
         */
        register_setting(
            'growtype_ai_settings_evaluation',
            'growtype_ai_evaluation_image_colors'
        );

        add_settings_field(
            'growtype_ai_evaluation_image_colors',
            'Image colors (image_id)',
            array ($this, 'growtype_ai_evaluation_input'),
            Growtype_Ai_Admin::SETTINGS_PAGE_NAME,
            'growtype_ai_evaluation_settings',
            [
                'name' => 'growtype_ai_evaluation_image_colors'
            ]
        );
    }

    /**
     *
     */
    function growtype_ai_evaluation_input($args)
    {
        ?>
        <input type="text" class="regular-text ltr" name="<?php echo $args['name'] ?>"/>
        <?php
    }
}


