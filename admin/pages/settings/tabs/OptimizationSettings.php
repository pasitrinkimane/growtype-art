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

class OptimizationSettings
{
    public function __construct()
    {
        add_action('admin_init', array ($this, 'admin_settings'));

        add_filter('growtype_ai_admin_settings_tabs', array ($this, 'settings_tab'));
    }

    function settings_tab($tabs)
    {
        $tabs['optimization'] = 'Optimization';

        return $tabs;
    }

    function admin_settings()
    {
        /**
         *
         */
        register_setting(
            'growtype_ai_settings_optimization',
            'growtype_ai_optimization_clean_duplicate_settings'
        );

        add_settings_field(
            'growtype_ai_optimization_clean_duplicate_settings',
            'Clean duplicate settings (clean db records)',
            array ($this, 'growtype_ai_optimization_input'),
            Growtype_Ai_Admin::SETTINGS_PAGE_NAME,
            'growtype_ai_optimization_settings',
            [
                'name' => 'growtype_ai_optimization_clean_duplicate_settings'
            ]
        );

        /**
         *
         */
        register_setting(
            'growtype_ai_settings_optimization',
            'growtype_ai_optimization_clean_duplicate_images'
        );

        add_settings_field(
            'growtype_ai_optimization_clean_duplicate_images',
            'Clean duplicate images (clean db records)',
            array ($this, 'growtype_ai_optimization_input'),
            Growtype_Ai_Admin::SETTINGS_PAGE_NAME,
            'growtype_ai_optimization_settings',
            [
                'name' => 'growtype_ai_optimization_clean_duplicate_images',
                'amount' => count(Growtype_Ai_Database_Crud::get_records(Growtype_Ai_Database::IMAGES_TABLE))
            ]
        );


        /**
         *
         */
        register_setting(
            'growtype_ai_settings_optimization',
            'growtype_ai_optimization_sync_local_images'
        );

        add_settings_field(
            'growtype_ai_optimization_sync_local_images',
            'Sync local images (check and upload local images)',
            array ($this, 'growtype_ai_optimization_input'),
            Growtype_Ai_Admin::SETTINGS_PAGE_NAME,
            'growtype_ai_optimization_settings',
            [
                'name' => 'growtype_ai_optimization_sync_local_images'
            ]
        );

        /**
         *
         */
        register_setting(
            'growtype_ai_settings_optimization',
            'growtype_ai_optimization_sync_models'
        );

        add_settings_field(
            'growtype_ai_optimization_sync_models',
            'Sync models (sync models with images)',
            array ($this, 'growtype_ai_optimization_input'),
            Growtype_Ai_Admin::SETTINGS_PAGE_NAME,
            'growtype_ai_optimization_settings',
            [
                'name' => 'growtype_ai_optimization_sync_models'
            ]
        );

        /**
         *
         */
        register_setting(
            'growtype_ai_settings_optimization',
            'growtype_ai_optimization_optimize_all_images'
        );

        add_settings_field(
            'growtype_ai_optimization_optimize_all_images',
            'Optimize images (upscale and compress images)',
            array ($this, 'growtype_ai_optimization_input'),
            Growtype_Ai_Admin::SETTINGS_PAGE_NAME,
            'growtype_ai_optimization_settings',
            [
                'name' => 'growtype_ai_optimization_optimize_all_images'
            ]
        );

        /**
         *
         */
        register_setting(
            'growtype_ai_settings_optimization',
            'growtype_ai_optimization_get_images_colors'
        );

        add_settings_field(
            'growtype_ai_optimization_get_images_colors',
            'Get images colors',
            array ($this, 'growtype_ai_optimization_input'),
            Growtype_Ai_Admin::SETTINGS_PAGE_NAME,
            'growtype_ai_optimization_settings',
            [
                'name' => 'growtype_ai_optimization_get_images_colors'
            ]
        );

        /**
         *
         */
        register_setting(
            'growtype_ai_settings_optimization',
            'growtype_ai_optimization_model_assign_categories'
        );

        add_settings_field(
            'growtype_ai_optimization_model_assign_categories',
            'Assign categories',
            array ($this, 'growtype_ai_optimization_input'),
            Growtype_Ai_Admin::SETTINGS_PAGE_NAME,
            'growtype_ai_optimization_settings',
            [
                'name' => 'growtype_ai_optimization_model_assign_categories'
            ]
        );
    }

    /**
     *
     */
    function growtype_ai_optimization_input($args)
    {
        ?>
        <input type="checkbox" class="regular-text ltr" name="<?php echo $args['name'] ?>"/>
        <?php

        if (isset($args['amount'])) {
            echo '<p>Amount: ' . $args['amount'] . '</p>';
        }
    }
}


