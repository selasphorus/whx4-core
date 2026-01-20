<?php
/**
 * Plugin Name:       WHx4-Core
 * Description:       A WordPress plugin for core functionality used by WHx4, Bkkp, SDG, etc.
 * Dependencies:      
 * Requires Plugins:  advanced-custom-fields-pro
 * Version:           1.0
 * Author:            atc
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       whx4-core
 *
 * @package           whx4-core
 */

declare(strict_types=1);

namespace atc\WXC;

// Prevent direct access
if ( !defined( 'ABSPATH' ) ) exit;

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

// v1 designed using ACF PRO Blocks, Post Types, Options Pages, Taxonomies and more.
// v2 OOP version WIP

define( 'WXC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
//define( 'WXC_PLUGIN_DIR', WP_PLUGIN_DIR. '/wxc/' ); //define( 'WXC_PLUGIN_DIR', __DIR__ );
define( 'WXC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WXC_PLUGIN_BLOCKS', WXC_PLUGIN_DIR . '/blocks/' );
// Some constants were previously defined via Plugin.php protected function defineConstants(): void {} and called via boot
// -- perhaps revisit this alternate approach to constants if things get too messy here.
define( 'WXC_TEXTDOMAIN', 'wxc' );
define( 'WXC_VERSION', '2.0.0' );
define( 'WXC_DEBUG', true ); // tft!

// Via Composer
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

//
use atc\WXC\Plugin;
use atc\WXC\PostTypes\PostUtils;

// Test Module
use atc\WXC\Modules\Supernatural\SupernaturalModule as Supernatural;

// Modules
use atc\WXC\Modules\Admin\AdminModule as Admin;

// Init
add_filter( 'wxc_register_modules', function( array $modules ) {
    //error_log( 'wxc_register_modules fired' );
    return array_merge( $modules, [
        'admin' 		=> Admin::class,
        'supernatural' 	=> Supernatural::class,
    ]);
});

add_filter( 'wxc_registered_field_keys', function() {
    if ( ! function_exists( 'acf_get_local_fields' ) ) {
        return [];
    }

    $fields = acf_get_local_fields();
    $keys = [];

    foreach ( $fields as $field ) {
        if ( isset( $field['key'] ) ) {
            $keys[] = $field['key'];
        }
    }

    return $keys;
});

// TODO -- move this elsewhere, perhaps?
// Add post_type query var to edit_post_link so as to be able to selectively load plugins via plugins-corral MU plugin
//add_filter( 'get_edit_post_link', 'add_post_type_query_var', 10, 3 );
function add_post_type_query_var( $url, $post_id, $context )
{
    $post_type = get_post_type( $post_id );

    // TODO: consider whether to add query_arg only for certain CPTS?
    if ( $post_type && !empty($post_type) ) { $url = add_query_arg( 'post_type', $post_type, $url ); }

    return $url;
}

// Once plugins are loaded, boot everything up
add_action( 'plugins_loaded', function() {
    Plugin::getInstance()->boot();
}, 20 ); // Use a priority high enough to allow addons to hook before it runs

// On activation, set up post types and capabilities
register_activation_hook( __FILE__, function() {
    $plugin = Plugin::getInstance();
    $plugin->boot();
});

// Activate the following after EM events have been migrated and the EM plugin has been deactivated
/*
add_filter( 'wxc_events_post_type_slug', function() {
    return 'event';
});
*/
// Deactivation
register_deactivation_hook( __FILE__, function() {
    $plugin = Plugin::getInstance();
    // WIP: cleanup on deactivation
    //$plugin->removePostTypeCapabilities();
});

// Global Wrapper Functions for theme access
// WIP!!!

function wxc_devmode_active( $arr_qvar_vals = [] ) {
    return atc\WXC\WXC_Environment::devmode($arr_qvar_vals);
}

function wxc_devsite() {
    return atc\WXC\WXC_Environment::devsite();
}

/* ***** TODO: Move most or all of the following away into classes ***** */

// Function to check for main dev/admin user
function wxc_queenbee() 
{
	$current_user = wp_get_current_user();
	$username = $current_user->user_login;
	$useremail = $current_user->user_email;
	//
    if ( $username == 'stcdev' || $useremail == "birdhive@gmail.com" ) {
    	return true;
    } else {
    	return false;
    }
}



/* +~+~+ Misc Functions WIP +~+~+ */

//add_action( 'init', 'wxc_redirect');
function wxc_redirect() 
{

	// If /events/ with query args and limit is set to 1, then see if there's a matching event and redirect to that event
	// /events/?scope=future&category=sunday-recital-series&limit=1&dev=events
	// /music/the-sunday-recital-series/upcoming-sunday-recital/
	//$current_url = home_url( add_query_arg( array(), $wp->request ) );

	if ( $wp->request == "/events" && get_query_var('limit') == "1") {

        // Run EM search based on query vars
        // Redirect to next single event record matching scope etc.

        //wp_redirect( site_url('/de/') );
        //exit;
    }
}


/**
 * Explode list using "," and ", ".
 *
 * @param string $string String to split up.
 * @return array Array of string parts.
 */
// Move this to Text utility class? TBD -- need to make sure this fcn is available to other plugins
function wxc_att_explode ( $string = '' ) 
{
	$string = str_replace( ', ', ',', $string );
	return explode( ',', $string );
}
