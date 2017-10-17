<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://igerry.com
 * @since             1.0.0
 * @package           Wpcampaign
 *
 * @wordpress-plugin
 * Plugin Name:       WPCampaign
 * Plugin URI:        https://igerry.com/project/wpcampaign
 * Description:       Send email and sms messages to ActiveCampaign users.
 * Version:           0.0.1
 * Author:            Gerry Ilagan
 * Author URI:        https://igerry.com
 * License:           GPL-3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       wpcampaign
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'PLUGIN_NAME_VERSION', '0.0.1' );

define("ACTIVECAMPAIGN_URL", "");
define("ACTIVECAMPAIGN_API_KEY", "");
require_once(dirname(__FILE__) . "/activecampaign-api-php/ActiveCampaign.class.php");

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wpcampaign-activator.php
 */
function activate_wpcampaign() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcampaign-activator.php';
	Wpcampaign_Activator::activate();
}
/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wpcampaign-deactivator.php
 */
function deactivate_wpcampaign() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcampaign-deactivator.php';
	Wpcampaign_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wpcampaign' );
register_deactivation_hook( __FILE__, 'deactivate_wpcampaign' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-wpcampaign.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wpcampaign() {

	$plugin = new Wpcampaign();
	$plugin->run();

}
run_wpcampaign();


