<?php //phpcs:ignore
declare(strict_types=1);

namespace Automattic\WordpressMcp\Resources;

use Automattic\WordpressMcp\Core\RegisterMcpResource;

/**
 * Class GeneralSiteInfo
 *
 * Resource for retrieving WordPress site information.
 * Provides access to site details, plugins, themes, and user information.
 *
 * @package Automattic\WordpressMcp\Resources
 */
class McpGeneralSiteInfo {

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
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
				'uri'         => 'WordPress://site-info',
				'name'        => 'site-info',
				'description' => 'Provides general information about the WordPress site including site details, plugins, themes, and users',
				'mimeType'    => 'application/json',
			),
			array( $this, 'get_site_info' )
		);
	}

	/**
	 * Get the site info.
	 *
	 * @return array
	 */
	public function get_site_info(): array {

		$site_info = array(
			'site_name'         => get_bloginfo( 'name' ),
			'site_url'          => get_bloginfo( 'url' ),
			'site_description'  => get_bloginfo( 'description' ),
			'site_admin_email'  => get_bloginfo( 'admin_email' ),
			'wordpress_version' => get_bloginfo( 'version' ),
			'language'          => get_bloginfo( 'language' ),
			'timezone'          => wp_timezone_string(),
			'active_plugins'    => get_option( 'active_plugins' ),
			'all_plugins'       => get_plugins(),
			'all_themes'        => wp_get_themes(),
			'active_theme'      => wp_get_theme(),
			'users-count'       => array(
				'total' => count( get_users() ),
				'roles' => array_count_values(
					array_map(
						function ( $user ) {
							return $user->roles[0];
						},
						get_users()
					)
				),
			),
		);

		return $site_info;
	}
}
