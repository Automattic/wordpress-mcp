<?php //phpcs:ignore
/**
 * Class McpWordPressRestApi
 *
 * Registers generic MCP tools for CRUD actions on any WordPress REST API endpoint.
 *
 * @package Automattic\WordpressMcp\Tools
 */
declare( strict_types=1 );

namespace Automattic\WordpressMcp\Tools;

use Automattic\WordpressMcp\Core\RegisterMcpTool;
use WP_REST_Request;

/**
 * Class McpWordPressRestApi
 *
 * Registers generic MCP tools for CRUD actions on any WordPress REST API endpoint.
 *
 * @package Automattic\WordpressMcp\Tools
 */
class McpWordPressRestApi {
	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wordpress_mcp_init', array( $this, 'register_tools' ) );
	}

	/**
	 * Register generic CRUD tools for a given REST API endpoint.
	 *
	 * Example usage: You can extend this to register tools for any custom endpoint.
	 */
	public function register_tools(): void {
		// Example: Register CRUD tools for a custom endpoint '/wp/v2/example'.
		// To use for other endpoints, duplicate and adjust the route/method/name/description as needed.

		new RegisterMcpTool(
			array(
				'name'        => 'wp_available_tools',
				'description' => 'List of avaialbe WordPress REST API tools, use this to get the route and method for the tool you want to run',
				'type'        => 'read',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
					'required'   => new \stdClass(),
				),
				'callback'    => array( $this, 'get_available_tools' ),
			)
		);

		new RegisterMcpTool(
			array(
				'name'        => 'wp_tool_details',
				'description' => 'Get details of a WordPress REST API tool',
				'type'        => 'read',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'route'  => array( 'type' => 'string' ),
						'method' => array(
							'type' => 'string',
							'enum' => array( 'GET', 'POST', 'PATCH', 'DELETE' ),
						),
					),
					'required'   => array( 'route', 'method' ),
				),
				'callback'    => array( $this, 'get_tool_details' ),
			)
		);

		new RegisterMcpTool(
			array(
				'name'        => 'wp_rest_run_tool',
				'description' => 'Run a WordPress REST API tool',
				'type'        => 'read',
				'inputSchema' => array(
					'type'       => 'object',
					'properties' => array(
						'route'  => array( 'type' => 'string' ),
						'method' => array(
							'type' => 'string',
							'enum' => array( 'GET', 'POST', 'PATCH', 'DELETE' ),
						),
						'data'   => array( 'type' => 'object' ),
					),
					'required'   => array( 'route', 'method' ),
				),
				'callback'    => array( $this, 'handle_tool_run_request' ),
			)
		);
	}

	/**
	 * Handle a REST API request.
	 *
	 * @param array $data The request data.
	 * @return array The response data.
	 */
	public function handle_tool_run_request( array $data ): array {
		$route  = $data['route'];
		$method = $data['method'];
		$data   = $data['data'];

		$rest_request = new WP_REST_Request( $method, $route );
		$rest_request->set_body_params( $data );
		$response = rest_do_request( $rest_request );
		return $response->get_data();
	}

	/**
	 * Get all routes and methods from the WordPress REST API.
	 *
	 * @return array The routes and methods.
	 */
	public function get_available_tools(): array {
		// content.text.result[key]
		// get all routes and methods from the WordPress rest api.
		$routes = rest_get_server()->get_routes();
		foreach ( $routes as $route => $methods ) {
			foreach ( $methods as $the_methods ) {
				$result[] = array(
					'route'  => $route,
					'method' => key( $the_methods['methods'] ),
				);
			}
		}
		return $result;
	}

	/**
	 * Get details of a WordPress REST API tool.
	 *
	 * @param array $data The request data.
	 * @return array|null The response data.
	 */
	public function get_tool_details( array $data ): array {
		$route  = $data['route'];
		$method = $data['method'];

		$routes = rest_get_server()->get_routes();
		foreach ( $routes as $route => $methods ) {
			foreach ( $methods as $method => $args ) {
				if ( $route === $route && $method === $method ) {
					return $args;
				}
			}
		}
		return array();
	}
}
