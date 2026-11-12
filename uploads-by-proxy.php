<?php
/**
 * Plugin Name: Uploads by Proxy
 * Plugin URI: https://github.com/morgandigital/uploads-by-proxy
 * Author: Paul Clark, Morgan Digital
 * Author URI: https://www.morgan.fi/
 * Description: Load images from production site if missing in development environment. Set the production address with <code>define( 'UBP_SITEURL', 'https://www.example.com' );</code> in wp-config.php.
 * Version: 2.0.0
 * Requires at least: 5.5
 * Requires PHP: 8.1
 *
 * @package Uploads_By_Proxy
 */

namespace Morgan\Uploads_By_Proxy;

defined( 'ABSPATH' ) || exit;

define( 'UBP_PLUGIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-admin-notices.php';
require_once __DIR__ . '/includes/class-path.php';
require_once __DIR__ . '/includes/class-proxy.php';

if ( 'production' !== wp_get_environment_type() ) {
	add_action( 'admin_init', array( Admin_Notices::class, 'requirements_check' ) );
	add_filter( '404_template', array( Proxy::class, 'handle_404' ) );
}
