<?php //phpcs:ignore
/**
 * Resources method handlers for MCP requests.
 *
 * @package WordPressMcp
 */

namespace Automattic\WordpressMcp\RequestMethodHandlers;

use Automattic\WordpressMcp\Core\WpMcp;
use Automattic\WordpressMcp\Core\McpErrorHandler;

/**
 * Handles resources-related MCP methods.
 */
class ResourcesHandler {
	/**
	 * The WordPress MCP instance.
	 *
	 * @var WpMcp
	 */
	private WpMcp $mcp;

	/**
	 * Constructor.
	 *
	 * @param WpMcp $mcp The WordPress MCP instance.
	 */
	public function __construct( WpMcp $mcp ) {
		$this->mcp = $mcp;
	}

	/**
	 * Check if user has permission to access resources.
	 *
	 * @return array|null Returns error array if permission denied, null if allowed.
	 */
	private function check_permission(): ?array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'error' => array(
					'code'    => 'rest_forbidden',
					'message' => 'You do not have permission to access resources.',
					'data'    => array( 'status' => 403 ),
				),
			);
		}
		return null;
	}

	/**
	 * Handle the resources/list request.
	 *
	 * @return array
	 */
	public function list_resources(): array {
		$permission_error = $this->check_permission();
		if ( $permission_error ) {
			return $permission_error;
		}

		// Get the registered resources from the MCP instance.
		$resources = array_values( $this->mcp->get_resources() );

		return array(
			'resources' => $resources,
		);
	}

	/**
	 * Handle the resources/templates/list request.
	 *
	 * @return array
	 */
	public function list_resource_templates(): array {
		$permission_error = $this->check_permission();
		if ( $permission_error ) {
			return $permission_error;
		}

		// Implement resource template listing logic here.
		$templates = array();

		return array(
			'templates' => $templates,
		);
	}

	/**
	 * Handle the resources/read request.
	 *
	 * @param array $params Request parameters.
	 * @return array
	 */
	public function read_resource( array $params ): array {
		$permission_error = $this->check_permission();
		if ( $permission_error ) {
			return $permission_error;
		}

		// Handle both direct params and nested params structure.
		$request_params = $params['params'] ?? $params;

		if ( ! isset( $request_params['uri'] ) ) {
			return array(
				'error' => McpErrorHandler::missing_parameter( 0, 'uri' )['error'],
			);
		}

		// Implement resource reading logic here.
		$uri                = $request_params['uri'];
		$resource_callbacks = $this->mcp->get_resource_callbacks();

		if ( ! isset( $resource_callbacks[ $uri ] ) ) {
			return array(
				'error' => McpErrorHandler::resource_not_found( 0, $uri )['error'],
			);
		}

		try {
			$callback = $resource_callbacks[ $uri ];
			$content  = call_user_func( $callback, $request_params );

			$resource = $this->mcp->get_resources()[ $uri ];

			return array(
				'contents' => array(
					array(
						'uri'      => $uri,
						'mimeType' => $resource['mimeType'],
						'text'     => wp_json_encode( $content ),
					),
				),
			);
		} catch ( \Throwable $exception ) {
			McpErrorHandler::log_error(
				'Error reading resource',
				array(
					'uri'       => $uri,
					'exception' => $exception->getMessage(),
				)
			);
			return array(
				'error' => McpErrorHandler::internal_error( 0, 'Failed to read resource' )['error'],
			);
		}
	}

	/**
	 * Handle the resources/subscribe request.
	 *
	 * @param array $params Request parameters.
	 * @return array
	 */
	public function subscribe_resource( array $params ): array {
		$permission_error = $this->check_permission();
		if ( $permission_error ) {
			return $permission_error;
		}

		// Handle both direct params and nested params structure.
		$request_params = $params['params'] ?? $params;

		if ( ! isset( $request_params['uri'] ) ) {
			return array(
				'error' => McpErrorHandler::missing_parameter( 0, 'uri' )['error'],
			);
		}

		// Implement resource subscription logic here.
		$uri = $request_params['uri'];

		return array(
			'subscriptionId' => 'sub_' . md5( $uri ),
		);
	}

	/**
	 * Handle the resources/unsubscribe request.
	 *
	 * @param array $params Request parameters.
	 * @return array
	 */
	public function unsubscribe_resource( array $params ): array {
		$permission_error = $this->check_permission();
		if ( $permission_error ) {
			return $permission_error;
		}

		// Handle both direct params and nested params structure.
		$request_params = $params['params'] ?? $params;

		if ( ! isset( $request_params['subscriptionId'] ) ) {
			return array(
				'error' => McpErrorHandler::missing_parameter( 0, 'subscriptionId' )['error'],
			);
		}

		// @todo: Implement resource unsubscription logic here.

		return array(
			'success' => true,
		);
	}
}
