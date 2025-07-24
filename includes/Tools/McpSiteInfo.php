<?php //phpcs:ignore
declare( strict_types=1 );

namespace Automattic\WordpressMcp\Tools;

use Automattic\WordpressMcp\Core\RegisterMcpTool;
use Automattic\WordpressMcp\Utils\ActiveThemeInfo;
use Automattic\WordpressMcp\Utils\PluginsInfo;
use Automattic\WordpressMcp\Utils\UsersInfo;
use stdClass;

/**
 * Class McpGetSiteInfo
 *
 * @package Automattic\WordpressMcp\Tools
 */
class McpSiteInfo {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wordpress_mcp_init', array( $this, 'register_tools' ) );
	}

	/**
	 * Register the tools.
	 */
	public function register_tools(): void {
		new RegisterMcpTool(
			array(
				'name'                => 'get_site_info',
				'description'         => 'Provides detailed information about the WordPress site like site name, url, description, admin email, plugins, themes, users, and more',
				'type'                => 'read',
				'inputSchema'         => array(
					'type' => 'object',
				),
				'callback'            => array( $this, 'get_site_info' ),
				'permission_callback' => array( $this, 'permission_callback' ),
				'annotations'         => array(
					'title'         => 'Get Site Info',
					'readOnlyHint'  => true,
					'openWorldHint' => false,
				),
			)
		);
	}

	/**
	 * Get the site info.
	 *
	 * @return array
	 */
	public function get_site_info(): array {

		return array(
			'site_name'        => get_bloginfo( 'name' ),
			'site_url'         => get_bloginfo( 'url' ),
			'site_description' => get_bloginfo( 'description' ),
			'site_admin_email' => get_bloginfo( 'admin_email' ),
			'plugins'          => ( new PluginsInfo() )->get_plugins_info(),
			'themes'           => array(
				'active' => ( new ActiveThemeInfo() )->get_theme_info(),
				'all'    => wp_get_themes(),
			),
			'users'            => ( new UsersInfo() )->get_user_info(),
		);
	}

	/**
	 * Permissions callback.
	 *
	 * @return bool
	 */
	public function permission_callback(): bool {
		return current_user_can( 'manage_options' );
	}
}
