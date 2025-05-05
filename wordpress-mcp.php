<?php
/**
 * Plugin name: WordPress MCP
 * Description: A plugin to manage content on a WordPress site.
 * Version: 0.1.7
 * Author: Ovidiu Galatan <ovidiu.galatan@a8c.com>
 * Author URI: https://automattic.com
 * Text Domain: wordpress-mcp
 * Domain Path: /languages
 * Requires PHP: 8.0
 *
 * @package WordPress MCP
 */

declare(strict_types=1);

use Automattic\WordpressMcp\Core\WpMcp;
use Automattic\WordpressMcp\Core\McpProxyRoutes;
use Automattic\WordpressMcp\Admin\Settings;

define( 'WORDPRESS_MCP_VERSION', '0.1.8' );
define( 'WORDPRESS_MCP_PATH', plugin_dir_path( __FILE__ ) );
define( 'WORDPRESS_MCP_URL', plugin_dir_url( __FILE__ ) );

require_once WORDPRESS_MCP_PATH . 'includes/autoload.php';

/**
 * Get the WordPress MCP instance.
 *
 * @return WpMcp
 */
function WPMCP() { // phpcs:ignore
	return WpMcp::instance();
}

/**
 * Initialize the plugin.
 */
function init_wordpress_mcp() {
	$mcp = WPMCP();

	// Initialize the REST route.
	new McpProxyRoutes( $mcp );

	// Initialize the settings page.
	new Settings();
}

// Initialize the plugin on plugins_loaded to ensure all dependencies are available.
add_action( 'plugins_loaded', 'init_wordpress_mcp' );
