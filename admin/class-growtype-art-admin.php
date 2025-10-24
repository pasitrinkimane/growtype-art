<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Growtype_Art
 * @subpackage growtype_art/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Growtype_Art
 * @subpackage growtype_art/admin
 * @author     Your Name <email@example.com>
 */
class Growtype_Art_Admin
{
    const DELETE_NONCE = 'growtype_art_delete_item';
    const SETTINGS_PAGE_NAME = 'growtype-art-settings';
    const MODELS_PAGE_NAME = 'growtype-art-models';
    const POST_TYPE = 'growtype_art_models';
    const SETTINGS_DEFAULT_TAB = 'general';

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $growtype_art The ID of this plugin.
     */
    private $growtype_art;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    /**
     * Traits
     */

    /**
     * Initialize the class and set its properties.
     *
     * @param string $growtype_art The name of this plugin.
     * @param string $version The version of this plugin.
     * @since    1.0.0
     */
    public function __construct($growtype_art, $version)
    {
        $this->growtype_art = $growtype_art;
        $this->version = $version;

        if (is_admin()) {
            /**
             * Load methods
             */
            add_action('init', array ($this, 'add_pages'));
        }
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        wp_enqueue_style($this->growtype_art, plugin_dir_url(__FILE__) . 'css/growtype-art-admin.css', array (), time(), 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script($this->growtype_art, plugin_dir_url(__FILE__) . 'js/growtype-art-admin.js', array ('jquery'), time(), false);
    }

    /**
     * Load the required methods for this plugin.
     *
     */
    public function add_pages()
    {
        /**
         * Plugin settings
         */
        require GROWTYPE_ART_PATH . '/admin/pages/growtype-art-admin-pages.php';
        new Growtype_Art_Admin_Pages();
    }
}
