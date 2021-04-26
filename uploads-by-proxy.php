<?php
/*
Plugin Name: Uploads by Proxy
Plugin URI: http://github.com/pdclark/uploads-by-proxy
Author: Paul Clark
Author URI: http://profiles.wordpress.org/pdclark
Description: Load images from production site if missing in development environment. Activate by using either <code>define('WP_SITEURL', 'http://development-domain.com');</code> or <code>define('UBP_SITEURL', 'http://live-domain.com/wordpress');</code> in wp-config.php.
Version: 1.2.0
*/

/**
 * Used for deactivating the plugin here or in class-ubp-helpers.php if requirements aren't met.
 */
define( 'UBP_PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/class-ubp-helpers.php';

if ( 'production' !== wp_get_environment_type() ) {
	add_action( 'admin_init', 'UBP_Helpers::requirements_check' );
	add_filter( '404_template', 'UBP_Helpers::init_404_template' );
}
