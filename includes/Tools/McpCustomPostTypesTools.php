<?php //phpcs:ignore
declare( strict_types=1 );

namespace Automattic\WordpressMcp\Tools;

use Automattic\WordpressMcp\Core\RegisterMcpTool;

/**
 * Class for managing MCP Custom Post Types Tools functionality.
 */
class McpCustomPostTypesTools {

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
		// Get all registered post types.
		$post_types      = get_post_types( array( 'public' => true ), 'objects' );
		$post_type_names = array();

		foreach ( $post_types as $post_type ) {
			$post_type_names[] = strtolower( $post_type->labels->name );
		}

		$post_types_list = implode( ', ', $post_type_names );

		new RegisterMcpTool(
			array(
				'name'        => 'wp_list_post_types',
				'description' => 'List all available WordPress custom post types',
				'type'        => 'read',
				'rest_alias'  => array(
					'route'  => '/wp/v2/types',
					'method' => 'GET',
				),
				'annotations' => array(
					'title'         => 'List Post Types',
					'readOnlyHint'  => true,
					'openWorldHint' => false,
				),
			)
		);

		new RegisterMcpTool(
			array(
				'name'                  => 'wp_cpt_search',
				'description'           => 'Search and filter WordPress custom post types including ' . $post_types_list . ' with pagination',
				'type'                  => 'read',
				'callback'              => array( $this, 'search_custom_post_types' ),
				'permission_callback'   => array( $this, 'search_custom_post_types_permission_callback' ),
				'disabled_by_rest_crud' => true,
				'inputSchema'           => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array(
							'type'        => 'string',
							'description' => 'The custom post type to search',
						),
						'search'    => array(
							'type'        => 'string',
							'description' => 'Search term to look for in post titles and content',
						),
						'author'    => array(
							'type'        => 'integer',
							'description' => 'Filter by author ID',
						),
						'status'    => array(
							'type'        => 'string',
							'description' => 'Filter by post status (publish, draft, pending, etc.)',
						),
						'page'      => array(
							'type'        => 'integer',
							'description' => 'Page number for pagination (starts from 1)',
							'default'     => 1,
						),
						'per_page'  => array(
							'type'        => 'integer',
							'description' => 'Number of posts per page',
							'default'     => 10,
						),
					),
					'required'   => array(
						'post_type',
					),
				),
				'annotations'           => array(
					'title'         => 'Search Custom Post Types',
					'readOnlyHint'  => true,
					'openWorldHint' => false,
				),
			)
		);

		new RegisterMcpTool(
			array(
				'name'                  => 'wp_get_cpt',
				'description'           => 'Get a WordPress custom post type by ID',
				'type'                  => 'read',
				'callback'              => array( $this, 'get_custom_post_type' ),
				'permission_callback'   => array( $this, 'get_custom_post_type_permission_callback' ),
				'disabled_by_rest_crud' => true,
				'inputSchema'           => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array(
							'type'        => 'string',
							'description' => 'The custom post type to get',
						),
						'id'        => array(
							'type'        => 'integer',
							'description' => 'The ID of the post to get',
						),
					),
					'required'   => array(
						'post_type',
						'id',
					),
				),
				'annotations'           => array(
					'title'         => 'Get Custom Post Type',
					'readOnlyHint'  => true,
					'openWorldHint' => false,
				),
			)
		);

		new RegisterMcpTool(
			array(
				'name'                  => 'wp_add_cpt',
				'description'           => 'Add a new WordPress custom post type',
				'type'                  => 'create',
				'callback'              => array( $this, 'add_custom_post_type' ),
				'permission_callback'   => array( $this, 'add_custom_post_type_permission_callback' ),
				'disabled_by_rest_crud' => true,
				'inputSchema'           => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array(
							'type'        => 'string',
							'description' => 'The custom post type to create',
						),
						'title'     => array(
							'type'        => 'string',
							'description' => 'The title of the post',
						),
						'content'   => array(
							'type'        => 'string',
							'description' => 'The content of the post in a valid Guttenberg block format',
						),
						'excerpt'   => array(
							'type'        => 'string',
							'description' => 'The excerpt of the post',
						),
						'status'    => array(
							'type'        => 'string',
							'description' => 'The status of the post (publish, draft, pending, etc.)',
						),
					),
					'required'   => array(
						'post_type',
						'title',
						'content',
					),
				),
				'annotations'           => array(
					'title'           => 'Add Custom Post Type',
					'readOnlyHint'    => false,
					'destructiveHint' => false,
					'idempotentHint'  => false,
					'openWorldHint'   => false,
				),
			)
		);

		new RegisterMcpTool(
			array(
				'name'                  => 'wp_update_cpt',
				'description'           => 'Update a WordPress custom post type by ID',
				'type'                  => 'update',
				'callback'              => array( $this, 'update_custom_post_type' ),
				'permission_callback'   => array( $this, 'update_custom_post_type_permission_callback' ),
				'disabled_by_rest_crud' => true,
				'inputSchema'           => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array(
							'type'        => 'string',
							'description' => 'The custom post type to update',
						),
						'id'        => array(
							'type'        => 'integer',
							'description' => 'The ID of the post to update',
						),
						'title'     => array(
							'type'        => 'string',
							'description' => 'The title of the post',
						),
						'content'   => array(
							'type'        => 'string',
							'description' => 'The content of the post in a valid Guttenberg block format',
						),
						'excerpt'   => array(
							'type'        => 'string',
							'description' => 'The excerpt of the post',
						),
						'status'    => array(
							'type'        => 'string',
							'description' => 'The status of the post (publish, draft, pending, etc.)',
						),
					),
					'required'   => array(
						'post_type',
						'id',
					),
				),
				'annotations'           => array(
					'title'           => 'Update Custom Post Type',
					'readOnlyHint'    => false,
					'destructiveHint' => false,
					'idempotentHint'  => true,
					'openWorldHint'   => false,
				),
			)
		);

		new RegisterMcpTool(
			array(
				'name'                  => 'wp_delete_cpt',
				'description'           => 'Delete a WordPress custom post type by ID',
				'type'                  => 'delete',
				'callback'              => array( $this, 'delete_custom_post_type' ),
				'permission_callback'   => array( $this, 'delete_custom_post_type_permission_callback' ),
				'disabled_by_rest_crud' => true,
				'inputSchema'           => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array(
							'type'        => 'string',
							'description' => 'The custom post type to delete',
						),
						'id'        => array(
							'type'        => 'integer',
							'description' => 'The ID of the post to delete',
						),
					),
					'required'   => array(
						'post_type',
						'id',
					),
				),
				'annotations'           => array(
					'title'           => 'Delete Custom Post Type',
					'readOnlyHint'    => false,
					'destructiveHint' => true,
					'idempotentHint'  => true,
					'openWorldHint'   => false,
				),
			)
		);
	}

	/**
	 * Search custom post types.
	 *
	 * @param array $params The parameters.
	 * @return array
	 */
	public function search_custom_post_types( array $params ): array {
		$post_type = sanitize_text_field( $params['post_type'] );
		$page      = isset( $params['page'] ) ? max( 1, intval( $params['page'] ) ) : 1;
		$per_page  = isset( $params['per_page'] ) ? max( 1, intval( $params['per_page'] ) ) : 10;

		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'post_status'    => 'publish',
		);

		if ( ! empty( $params['search'] ) ) {
			$args['s'] = sanitize_text_field( $params['search'] );
		}

		if ( ! empty( $params['author'] ) ) {
			$args['author'] = intval( $params['author'] );
		}

		if ( ! empty( $params['status'] ) ) {
			$args['post_status'] = sanitize_text_field( $params['status'] );
		}

		$query = new \WP_Query( $args );
		return array(
			'results'  => $query->posts,
			'total'    => $query->found_posts,
			'pages'    => $query->max_num_pages,
			'page'     => $page,
			'per_page' => $per_page,
		);
	}

	/**
	 * Search custom post types permissions callback.
	 *
	 * @return bool
	 */
	public function search_custom_post_types_permission_callback(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Get a custom post type by ID.
	 *
	 * @param array $params The parameters.
	 * @return array
	 */
	public function get_custom_post_type( array $params ): array {
		$post = get_post( intval( $params['id'] ) );
		if ( ! $post || $post->post_type !== $params['post_type'] ) {
			return array(
				'error' => array(
					'code'    => -32000,
					'message' => 'Post not found',
				),
			);
		}
		return array( 'results' => $post );
	}

	/**
	 * Get custom post type permissions callback.
	 *
	 * @return bool
	 */
	public function get_custom_post_type_permission_callback(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Add a new custom post type.
	 *
	 * @param array $params The parameters.
	 * @return array
	 */
	public function add_custom_post_type( array $params ): array {
		$post_data = array(
			'post_type'    => sanitize_text_field( $params['post_type'] ),
			'post_title'   => sanitize_text_field( $params['title'] ),
			'post_content' => wp_kses_post( $params['content'] ),
			'post_status'  => 'draft',
		);

		if ( ! empty( $params['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_text_field( $params['excerpt'] );
		}

		if ( ! empty( $params['status'] ) ) {
			$post_data['post_status'] = sanitize_text_field( $params['status'] );
		}

		$post_id = wp_insert_post( $post_data );
		if ( is_wp_error( $post_id ) ) {
			return array(
				'error' => array(
					'code'    => -32000,
					'message' => $post_id->get_error_message(),
				),
			);
		}

		return array( 'results' => get_post( $post_id ) );
	}

	/**
	 * Add custom post type permissions callback.
	 *
	 * @return bool
	 */
	public function add_custom_post_type_permission_callback(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Update a custom post type.
	 *
	 * @param array $params The parameters.
	 * @return array
	 */
	public function update_custom_post_type( array $params ): array {
		$post = get_post( intval( $params['id'] ) );
		if ( ! $post || $post->post_type !== $params['post_type'] ) {
			return array(
				'error' => array(
					'code'    => -32000,
					'message' => 'Post not found',
				),
			);
		}

		$post_data = array(
			'ID' => $post->ID,
		);

		if ( ! empty( $params['title'] ) ) {
			$post_data['post_title'] = sanitize_text_field( $params['title'] );
		}

		if ( ! empty( $params['content'] ) ) {
			$post_data['post_content'] = wp_kses_post( $params['content'] );
		}

		if ( ! empty( $params['excerpt'] ) ) {
			$post_data['post_excerpt'] = sanitize_text_field( $params['excerpt'] );
		}

		if ( ! empty( $params['status'] ) ) {
			$post_data['post_status'] = sanitize_text_field( $params['status'] );
		}

		$post_id = wp_update_post( $post_data );
		if ( is_wp_error( $post_id ) ) {
			return array(
				'error' => array(
					'code'    => -32000,
					'message' => $post_id->get_error_message(),
				),
			);
		}

		return array( 'results' => get_post( $post_id ) );
	}

	/**
	 * Update custom post type permissions callback.
	 *
	 * @return bool
	 */
	public function update_custom_post_type_permission_callback(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Delete a custom post type.
	 *
	 * @param array $params The parameters.
	 * @return array
	 */
	public function delete_custom_post_type( array $params ): array {
		$post = get_post( intval( $params['id'] ) );
		if ( ! $post || $post->post_type !== $params['post_type'] ) {
			return array(
				'error' => array(
					'code'    => -32000,
					'message' => 'Post not found',
				),
			);
		}

		$result = wp_delete_post( $post->ID, true );
		if ( ! $result ) {
			return array(
				'error' => array(
					'code'    => -32000,
					'message' => 'Failed to delete post',
				),
			);
		}

		return array( 'results' => true );
	}

	/**
	 * Delete custom post type permissions callback.
	 *
	 * @return bool
	 */
	public function delete_custom_post_type_permission_callback(): bool {
		return current_user_can( 'edit_posts' );
	}
}
