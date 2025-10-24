<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Growtype_Art
 * @subpackage growtype_art/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Growtype_Art
 * @subpackage growtype_art/public
 * @author     Your Name <email@example.com>
 */
class Growtype_Art_Public
{

    const AJAX_ACTION = 'growtype_art';

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
     * Initialize the class and set its properties.
     *
     * @param string $growtype_art The name of the plugin.
     * @param string $version The version of this plugin.
     * @since    1.0.0
     */
    public function __construct($growtype_art, $version)
    {
        $this->growtype_art = $growtype_art;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        wp_enqueue_style($this->growtype_art, GROWTYPE_ART_URL_PUBLIC . 'styles/growtype-art.css', array (), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script($this->growtype_art, GROWTYPE_ART_URL_PUBLIC . 'scripts/growtype-art.js', array ('jquery'), $this->version, true);

        $ajax_url = admin_url('admin-ajax.php');

        if (class_exists('QTX_Translator')) {
            $ajax_url = admin_url('admin-ajax.php' . '?lang=' . qtranxf_getLanguage());
        }

        wp_localize_script($this->growtype_art, 'growtype_art_ajax', array (
            'url' => $ajax_url,
            'nonce' => wp_create_nonce('ajax-nonce'),
            'action' => self::AJAX_ACTION
        ));
    }

}
