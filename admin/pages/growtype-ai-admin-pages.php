<?php

class Growtype_Ai_Admin_Pages
{
    public function __construct()
    {
        add_action('admin_menu', array ($this, 'admin_menu_pages'));

        $this->load_pages();
    }

    /**
     * Register the options page with the Wordpress menu.
     */
    function admin_menu_pages()
    {
        /**
         * Main
         */
        add_menu_page(
            __('Dashboard', 'growtype-ai'),
            __('Growtype Ai', 'growtype-ai'),
            'manage_options',
            'growtype-ai',
            array ($this, 'growtype_ai')
        );
    }

    function growtype_ai()
    {

    }

    public function load_pages()
    {
        /**
         * Models
         */
        require_once 'images/growtype-ai-admin-images.php';
        new Growtype_Ai_Admin_Images();

        /**
         * Models
         */
        require_once 'models/growtype-ai-admin-models.php';
        new Growtype_Ai_Admin_Models();

        /**
         * Settings
         */
        require_once 'settings/growtype-ai-admin-settings.php';
        new Growtype_Ai_Admin_Settings();
    }
}
