<?php
/**
 * Plugin Name:       IteroChat
 * Plugin URI:        https://iterochat.com
 * Description:       Connect your WordPress site to IteroChat and add the AI customer support chat widget. No code required.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Iterosys
 * Author URI:        https://iterosys.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain:       iterochat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ITEROCHAT_VERSION', '0.1.0' );
define( 'ITEROCHAT_PLUGIN_FILE', __FILE__ );
define( 'ITEROCHAT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ITEROCHAT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ITEROCHAT_CLIENT_ID', 'wordpress' );
define( 'ITEROCHAT_SCOPE', 'widget:connect' );
define( 'ITEROCHAT_OPTION', 'iterochat_connection' );

require_once ITEROCHAT_PLUGIN_DIR . 'includes/config.php';
require_once ITEROCHAT_PLUGIN_DIR . 'includes/options.php';
require_once ITEROCHAT_PLUGIN_DIR . 'includes/class-iterochat-oauth.php';
require_once ITEROCHAT_PLUGIN_DIR . 'includes/admin.php';
require_once ITEROCHAT_PLUGIN_DIR . 'includes/frontend.php';
