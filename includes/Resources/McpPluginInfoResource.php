<?php //phpcs:ignore
declare(strict_types=1);

namespace Automattic\WordpressMcp\Resources;

use Automattic\WordpressMcp\Core\RegisterMcpResource;
use Automattic\WordpressMcp\Utils\PluginsInfo;

/**
 * Class PluginInfoResource
 *
 * Resource for retrieving information about active WordPress plugins.
 * Provides detailed information about each active plugin.
 *
 * @package Automattic\WordpressMcp\Resources
 */
class McpPluginInfoResource {

	/**
	 * PluginsInfo instance.
	 *
	 * @var PluginsInfo
	 */
	private $plugins_info;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->plugins_info = new PluginsInfo();
		add_action( 'wordpress_mcp_init', array( $this, 'register_resource' ) );
	}

	/**
	 * Register the resource.
	 *
	 * @return void
	 */
	public function register_resource(): void {
		new RegisterMcpResource(
			array(
				'uri'         => 'WordPress://plugin-info',
				'name'        => 'plugin-info',
				'description' => 'Provides detailed information about active WordPress plugins',
				'mimeType'    => 'application/json',
			),
			array( $this, 'get_plugin_info' )
		);
	}

	/**
	 * Get information about active plugins.
	 *
	 * @return array
	 */
	public function get_plugin_info(): array {
		// Return all plugin information without filtering.
		return $this->plugins_info->get_plugins_info();
	}
}
