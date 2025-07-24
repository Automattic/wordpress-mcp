<?php //phpcs:ignore
declare( strict_types=1 );

namespace Automattic\WordpressMcp\Tools;

use Automattic\WordpressMcp\Core\RegisterMcpTool;

/**
 * Class for managing MCP Users Tools functionality.
 */
class McpUsersTools {

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
				'name'        => 'wp_users_search',
				'description' => 'Search and filter WordPress users with pagination',
				'type'        => 'read',
				'rest_alias'  => array(
					'route'                   => '/wp/v2/users',
					'method'                  => 'GET',
					'inputSchemaReplacements' => array(
						'properties' => array(
							'has_published_posts' => array(
								'items'   => null, // this will remove the array from the schema.
								'default' => false,
							),
						),
						'required'   => array(
							'context',
						),
					),
				),
				'annotations' => array(
					'title'         => 'Search Users',
					'readOnlyHint'  => true,
					'openWorldHint' => false,
				),
			)
		);

		new RegisterMcpTool(
			array(
				'name'        => 'wp_get_user',
				'description' => 'Get a WordPress user by ID',
				'type'        => 'read',
				'rest_alias'  => array(
					'route'  => '/wp/v2/users/(?P<id>[\d]+)',
					'method' => 'GET',
				),
				'annotations' => array(
					'title'         => 'Get User',
					'readOnlyHint'  => true,
					'openWorldHint' => false,
				),
			)
		);

		new RegisterMcpTool(
			array(
				'name'        => 'wp_add_user',
				'description' => 'Add a new WordPress user',
				'type'        => 'create',
				'rest_alias'  => array(
					'route'                   => '/wp/v2/users',
					'method'                  => 'POST',
					'inputSchemaReplacements' => array(
						'properties' => array(
							'locale'  => null,
							'context' => array(
								'type' => 'string',
								'enum' => array( 'edit' ),
							),
						),
						'required'   => array(
							'context',
						),
					),
				),
				'annotations' => array(
					'title'           => 'Add User',
					'readOnlyHint'    => false,
					'destructiveHint' => false,
					'idempotentHint'  => false,
					'openWorldHint'   => false,
				),
			)
		);

		new RegisterMcpTool(
			array(
				'name'        => 'wp_update_user',
				'description' => 'Update a WordPress user by ID',
				'type'        => 'update',
				'rest_alias'  => array(
					'route'  => '/wp/v2/users/(?P<id>[\d]+)',
					'method' => 'PUT',
				),
				'annotations' => array(
					'title'           => 'Update User',
					'readOnlyHint'    => false,
					'destructiveHint' => false,
					'idempotentHint'  => true,
					'openWorldHint'   => false,
				),
			)
		);

		new RegisterMcpTool(
			array(
				'name'        => 'wp_delete_user',
				'description' => 'Delete a WordPress user by ID',
				'type'        => 'delete',
				'rest_alias'  => array(
					'route'  => '/wp/v2/users/(?P<id>[\d]+)',
					'method' => 'DELETE',
				),
				'annotations' => array(
					'title'           => 'Delete User',
					'readOnlyHint'    => false,
					'destructiveHint' => true,
					'idempotentHint'  => true,
					'openWorldHint'   => false,
				),
			)
		);

		// Get current user.
		new RegisterMcpTool(
			array(
				'name'        => 'wp_get_current_user',
				'description' => 'Get the current logged-in user',
				'type'        => 'read',
				'rest_alias'  => array(
					'route'  => '/wp/v2/users/me',
					'method' => 'GET',
				),
				'annotations' => array(
					'title'         => 'Get Current User',
					'readOnlyHint'  => true,
					'openWorldHint' => false,
				),
			)
		);

		// Update current user.
		new RegisterMcpTool(
			array(
				'name'        => 'wp_update_current_user',
				'description' => 'Update the current logged-in user',
				'type'        => 'update',
				'rest_alias'  => array(
					'route'  => '/wp/v2/users/me',
					'method' => 'PUT',
				),
				'annotations' => array(
					'title'           => 'Update Current User',
					'readOnlyHint'    => false,
					'destructiveHint' => false,
					'idempotentHint'  => true,
					'openWorldHint'   => false,
				),
			)
		);
	}
}
