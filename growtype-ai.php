<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://growtype.com/
 * @since             1.0.0
 * @package           growtype_ai
 *
 * @wordpress-plugin
 * Plugin Name:       Growtype - AI
 * Plugin URI:        http://growtype.com/
 * Description:       Advanced AI for modern websites.
 * Version:           1.0.0
 * Author:            Growtype
 * Author URI:        http://growtype.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       growtype-ai
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('GROWTYPE_AI_VERSION', '1.0.4');

/**
 * Plugin text domain
 */
define('GROWTYPE_AI_TEXT_DOMAIN', 'growtype-ai');

/**
 * Plugin dir path
 */
define('GROWTYPE_AI_PATH', plugin_dir_path(__FILE__));

/**
 * Plugin url
 */
define('GROWTYPE_AI_URL', plugin_dir_url(__FILE__));

/**
 * Plugin url public
 */
define('GROWTYPE_AI_URL_PUBLIC', plugin_dir_url(__FILE__) . 'public/');

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-growtype-ai-activator.php
 */
function activate_growtype_ai()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-growtype-ai-activator.php';
    Growtype_Ai_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-growtype-ai-deactivator.php
 */
function deactivate_growtype_ai()
{
    require_once plugin_dir_path(__FILE__) . 'includes/class-growtype-ai-deactivator.php';
    Growtype_Ai_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_growtype_ai');
register_deactivation_hook(__FILE__, 'deactivate_growtype_ai');

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'includes/class-growtype-ai.php';

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path(__FILE__) . 'database/class-growtype-ai-database.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_growtype_ai()
{
    $plugin = new Growtype_Ai();
    $plugin->run();
}

run_growtype_ai();
