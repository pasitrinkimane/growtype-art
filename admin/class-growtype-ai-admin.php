<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Growtype_Ai
 * @subpackage growtype_ai/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Growtype_Ai
 * @subpackage growtype_ai/admin
 * @author     Your Name <email@example.com>
 */
class Growtype_Ai_Admin
{
    const DELETE_NONCE = 'growtype_ai_delete_item';
    const SETTINGS_PAGE_NAME = 'growtype-ai-settings';
    const MODELS_PAGE_NAME = 'growtype-ai-models';
    const POST_TYPE = 'growtype_ai_models';
    const GROWTYPE_AI_SETTINGS_DEFAULT_TAB = 'general';

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $growtype_ai The ID of this plugin.
     */
    private $growtype_ai;

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
     * @param string $growtype_ai The name of this plugin.
     * @param string $version The version of this plugin.
     * @since    1.0.0
     */
    public function __construct($growtype_ai, $version)
    {
        $this->growtype_ai = $growtype_ai;
        $this->version = $version;

        if (is_admin()) {
            /**
             * Load methods
             */
            add_action('init', array ($this, 'add_pages'));
        }

        /**
         * Ajax
         */
        add_action('wp_ajax_remove_image', array ($this, 'remove_image_callback'));
        add_action('wp_ajax_featured_image', array ($this, 'featured_image_callback'));
    }

    function remove_image_callback()
    {
        $image_id = $_POST['image_id'];

        Growtype_Ai_Crud::delete_image($image_id);

        return wp_send_json(
            [
                'message' => __('Success', 'growtype')
            ], 200);
    }


    function featured_image_callback()
    {
        $image_id = $_POST['image_id'];

        $image_details = growtype_ai_get_image_details($image_id);

        $is_featured = !(isset($image_details['settings']['is_featured']) ? $image_details['settings']['is_featured'] : false);

        Growtype_Ai_Database_Crud::update_records(Growtype_Ai_Database::IMAGE_SETTINGS_TABLE,
            [
                [
                    'key' => 'image_id',
                    'values' => [$image_id]
                ]
            ],
            [
                'reference_key' => 'meta_key',
                'update_value' => 'meta_value'
            ],
            [
                'is_featured' => $is_featured
            ]
        );

        return wp_send_json(
            [
                'is_featured' => $is_featured,
                'message' => __('Success', 'growtype')
            ], 200);
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        wp_enqueue_style($this->growtype_ai, plugin_dir_url(__FILE__) . 'css/growtype-ai-admin.css', array (), time(), 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script($this->growtype_ai, plugin_dir_url(__FILE__) . 'js/growtype-ai-admin.js', array ('jquery'), time(), false);
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
        require GROWTYPE_AI_PATH . '/admin/pages/growtype-ai-admin-pages.php';
        new Growtype_Ai_Admin_Pages();
    }
}
