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
    const PAGE_NAME = 'growtype-ai-models';
    const POST_TYPE = 'growtype_ai_models';

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
            add_action('admin_menu', array ($this, 'admin_menu_pages'));

            /**
             * Load methods
             */
            add_action('admin_init', array ($this, 'add_options_settings'));

            /**
             * Load settings
             */
            $this->load_settings();
        }
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in growtype_ai as all of the hooks are defined
         * in that particular class.
         *
         * The growtype_ai will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_style($this->growtype_ai, plugin_dir_url(__FILE__) . 'css/growtype-ai-admin.css', array (), $this->version, 'all');

    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {

        /**
         * This function is provided for demonstration purposes only.
         *
         * An instance of this class should be passed to the run() function
         * defined in Growtype_Ai_Loader as all of the hooks are defined
         * in that particular class.
         *
         * The Growtype_Ai_Loader will then create the relationship
         * between the defined hooks and the functions defined in this
         * class.
         */

        wp_enqueue_script($this->growtype_ai, plugin_dir_url(__FILE__) . 'js/growtype-ai-admin.js', array ('jquery'), $this->version, false);

    }

    /**
     * Register the options page with the Wordpress menu.
     */
    function admin_menu_pages()
    {

        /**
         * Data
         */
        add_menu_page(
            __('Growtype Ai', 'growtype-ai'),
            __('Growtype Ai', 'growtype-ai'),
            'manage_options',
            'growtype-ai-models',
            array ($this, 'growtype_ai_models')
        );

        /**
         * Options
         */
        add_submenu_page(
            'growtype-ai-models',
            'Settings',
            'Settings',
            'manage_options',
            'growtype-ai-settings',
            array ($this, 'growtype_ai_settings'),
            1
        );
    }

    function growtype_ai_models()
    {
    }

    /**
     * @param $current
     * @return void
     */
    function growtype_ai_settings_tabs($current = 'login')
    {
        $tabs['general'] = 'General';

        if (class_exists('woocompress')) {
            $tabs['woocommerce'] = 'Woocommerce';
        }

        echo '<div id="icon-themes" class="icon32"><br></div>';
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $tab => $name) {
            $class = ($tab == $current) ? ' nav-tab-active' : '';
            echo "<a class='nav-tab$class' href='?page=growtype-ai-settings&tab=$tab'>$name</a>";

        }
        echo '</h2>';
    }

    /**
     * @return void
     */
    function growtype_ai_settings()
    {
        if (isset($_GET['page']) && $_GET['page'] == 'growtype-ai-settings') { ?>

            <div class="wrap">

                <h1>Growtype - AI settings</h1>

                <?php
                if (isset($_GET['updated']) && 'true' == esc_attr($_GET['updated'])) {
                    echo '<div class="updated" ><p>Theme Settings Updated.</p></div>';
                }

                if (isset ($_GET['tab'])) {
                    $this->growtype_ai_settings_tabs($_GET['tab']);
                } else {
                    $this->growtype_ai_settings_tabs();
                }
                ?>

                <form id="growtype_ai_main_settings" method="post" action="options.php">
                    <?php

                    if (isset ($_GET['tab'])) {
                        $tab = $_GET['tab'];
                    } else {
                        $tab = 'general';
                    }

                    switch ($tab) {
                        case 'general':
                            settings_fields('growtype_ai_settings');

                            echo '<h3>Leonardo AI settings</h3>';

                            echo '<table class="form-table">';
                            do_settings_fields('growtype-ai-settings', 'growtype_ai_leonardoai_settings');
                            echo '</table>';

                            echo '<h3>Cloudinary settings</h3>';

                            echo '<table class="form-table">';
                            do_settings_fields('growtype-ai-settings', 'growtype_ai_cloudinary_settings');
                            echo '</table>';

                            echo '<h3>Image generating settings</h3>';

                            echo '<table class="form-table">';
                            do_settings_fields('growtype-ai-settings', 'growtype_ai_image_generating_settings');
                            echo '</table>';

                            break;
                    }

                    if ($tab !== 'examples') {
                        submit_button();
                    }

                    ?>
                </form>
            </div>

            <?php
        }
    }

    /**
     * Load the required methods for this plugin.
     *
     */
    public function add_options_settings()
    {
        /**
         * Leonardo ai settings
         */
        include_once 'partials/settings/LeonardoAiSettings.php';

        $GeneralSettings = new LeonardoAiSettings();
        $GeneralSettings->general_content();

        /**
         * Cloudinary settings
         */
        include_once 'partials/settings/CloudinarySettings.php';

        $GeneralSettings = new CloudinarySettings();
        $GeneralSettings->general_content();

        /**
         * Image generating settings
         */
        include_once 'partials/settings/ImageGeneratingSettings.php';

        $GeneralSettings = new ImageGeneratingSettings();
        $GeneralSettings->general_content();

    }

    /**
     * @return void
     */
    private function load_settings()
    {
        /**
         * Result
         */
        require_once GROWTYPE_AI_PATH . 'admin/methods/model/growtype-ai-admin-model.php';
        $this->loader = new Growtype_Ai_Admin_Model();
    }
}
