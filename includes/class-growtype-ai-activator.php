<?php

/**
 * Fired during plugin activation
 *
 * @link       http://example.com
 * @since      1.0.0
 *
 * @package    Growtype_Ai
 * @subpackage growtype_ai/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Growtype_Ai
 * @subpackage growtype_ai/includes
 * @author     Your Name <email@example.com>
 */
class Growtype_Ai_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
        global $wp_rewrite;
        $wp_rewrite->flush_rules();
	}

}
