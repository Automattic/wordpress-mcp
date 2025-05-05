<?php //phpcs:ignore
/**
 * Class for managing MCP Pages Tools functionality.
 *
 * @package Automattic\WordpressMcp\Tools
 */
declare( strict_types=1 );

namespace Automattic\WordpressMcp\Tools;

use Automattic\WordpressMcp\Core\RegisterMcpTool;

/**
 * Class McpPagesTools
 *
 * Registers MCP tools for managing WordPress pages.
 */
class McpPagesTools {

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
		// Search and filter pages.
		new RegisterMcpTool(
			array(
				'name'        => 'wp_pages_search',
				'description' => 'Search and filter WordPress pages with pagination',
				'type'        => 'read',
				'rest_alias'  => array(
					'route'  => '/wp/v2/pages',
					'method' => 'GET',
				),
			)
		);

		// Get a page by ID.
		new RegisterMcpTool(
			array(
				'name'        => 'wp_get_page',
				'description' => 'Get a WordPress page by ID',
				'type'        => 'read',
				'rest_alias'  => array(
					'route'  => '/wp/v2/pages/(?P<id>[\\d]+)',
					'method' => 'GET',
				),
			)
		);

		// Add a new page.
		new RegisterMcpTool(
			array(
				'name'        => 'wp_add_page',
				'description' => 'Add a new WordPress page',
				'type'        => 'create',
				'rest_alias'  => array(
					'route'                   => '/wp/v2/pages',
					'method'                  => 'POST',
					'inputSchemaReplacements' => array(
						'properties' => array(
							'title'   => array(
								'type' => 'string',
							),
							'content' => array(
								'type'        => 'string',
								'description' => 'The content of the page in a valid Gutenberg block format',
							),
							'excerpt' => array(
								'type' => 'string',
							),
						),
						'required'   => array(
							'title',
							'content',
						),
					),
				),
			)
		);

		// Update a page by ID.
		new RegisterMcpTool(
			array(
				'name'        => 'wp_update_page',
				'description' => 'Update a WordPress page by ID',
				'type'        => 'update',
				'rest_alias'  => array(
					'route'  => '/wp/v2/pages/(?P<id>[\\d]+)',
					'method' => 'PUT',
				),
			)
		);

		// Delete a page by ID.
		new RegisterMcpTool(
			array(
				'name'        => 'wp_delete_page',
				'description' => 'Delete a WordPress page by ID',
				'type'        => 'delete',
				'rest_alias'  => array(
					'route'  => '/wp/v2/pages/(?P<id>[\\d]+)',
					'method' => 'DELETE',
				),
			)
		);
	}
}
