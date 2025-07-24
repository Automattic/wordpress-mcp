<?php //phpcs:ignore
declare(strict_types=1);

namespace Automattic\WordpressMcp\Resources;

use Automattic\WordpressMcp\Core\RegisterMcpResource;
use Automattic\WordpressMcp\Utils\UsersInfo;

/**
 * Class UserInfoResource
 *
 * Resource for retrieving information about WordPress users.
 * Provides detailed information about registered users and their roles.
 *
 * @package Automattic\WordpressMcp\Resources
 */
class McpUserInfoResource {

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
				'uri'         => 'WordPress://user-info',
				'name'        => 'user-info',
				'description' => 'Provides detailed information about registered WordPress users and their roles',
				'mimeType'    => 'application/json',
			),
			array( $this, 'get_user_info' )
		);
	}

	/**
	 * Get information about WordPress users.
	 *
	 * @return array
	 */
	public function get_user_info(): array {
		return ( new UsersInfo() )->get_user_info();
	}
}
