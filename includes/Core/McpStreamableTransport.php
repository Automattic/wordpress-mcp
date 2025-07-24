<?php //phpcs:ignore
/**
 * The WordPress MCP Streamable HTTP Transport class.
 *
 * @package WordPressMcp
 */

namespace Automattic\WordpressMcp\Core;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * The WordPress MCP Streamable HTTP Transport class.
 * Uses JSON-RPC 2.0 format for direct streamable connections.
 */
class McpStreamableTransport extends McpTransportBase {

	/**
	 * The request ID.
	 *
	 * @var int
	 */
	private int $request_id = 0;

	/**
	 * Initialize the class and register routes
	 *
	 * @param WpMcp $mcp The WordPress MCP instance.
	 */
	public function __construct( WpMcp $mcp ) {
		parent::__construct( $mcp );
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all MCP proxy routes
	 */
	public function register_routes(): void {
		// If MCP is disabled, don't register routes.
		if ( ! $this->is_mcp_enabled() ) {
			return;
		}

		// Single endpoint for all MCP operations.
		register_rest_route(
			'wp/v2',
			'/wpmcp/streamable',
			array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => array( $this, 'handle_request' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Check if the user has permission to access the MCP API
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return bool|WP_Error
	 */
	public function check_permission( $request = null ): WP_Error|bool {
		// If MCP is disabled, deny access.
		if ( ! $this->is_mcp_enabled() ) {
			return new WP_Error(
				'mcp_disabled',
				'MCP functionality is currently disabled.',
				array( 'status' => 403 )
			);
		}

		// Check if request is authenticated.
		$auth_result = apply_filters( 'wpmcp_authenticate_request', null, $request );

		// Also check OAuth Passport if available and no other auth method succeeded.
		if ( empty( $auth_result ) && function_exists( 'oauth_passport_get_current_token' ) ) {
			// OAuth Passport will handle authentication through WordPress core filters.
			// Just check if user is logged in (OAuth Passport sets current user).
			if ( is_user_logged_in() ) {
				$auth_result = true;
			}
		}

		if ( empty( $auth_result ) ) {
			return new WP_Error(
				'unauthorized',
				'Authentication required',
				array( 'status' => 401 )
			);
		}

		// If authentication filter authenticated the user, allow access.
		if ( true === $auth_result ) {
			return true;
		}

		// Fall back to checking if the user is logged in (for cookie auth).
		if ( is_user_logged_in() ) {
			return true;
		}

		// No valid authentication found.
		return new WP_Error(
			'unauthorized',
			'Authentication required. Please provide a valid JWT or OAuth token.',
			array( 'status' => 401 )
		);
	}

	/**
	 * Handle the HTTP request
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function handle_request( WP_REST_Request $request ) {
		// Handle preflight requests.
		if ( 'OPTIONS' === $request->get_method() ) {
			return new WP_REST_Response( null, 204 );
		}

		$method = $request->get_method();

		if ( 'POST' === $method ) {
			return $this->handle_post_request( $request );
		}

		// Return 405 for unsupported methods.
		return new WP_REST_Response(
			McpErrorHandler::create_error_response( 0, McpErrorHandler::INVALID_REQUEST, 'Method not allowed' ),
			405
		);
	}

	/**
	 * Handle POST requests
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	private function handle_post_request( $request ) {
		try {
			// Validate Accept header - client MUST include both content types.
			$accept_header = $request->get_header( 'accept' );
			if ( ! $accept_header ||
				strpos( $accept_header, 'application/json' ) === false ||
				strpos( $accept_header, 'text/event-stream' ) === false ) {
				return new WP_REST_Response(
					McpErrorHandler::invalid_accept_header( 0 ),
					400
				);
			}

			// Validate content type - be more flexible with content-type headers.
			$content_type = $request->get_header( 'content-type' );
			if ( $content_type && strpos( $content_type, 'application/json' ) === false ) {
				return new WP_REST_Response(
					McpErrorHandler::invalid_content_type( 0 ),
					400
				);
			}

			// Get the JSON-RPC message(s) - can be single message or array batch.
			$body = $request->get_json_params();
			if ( null === $body ) {
				return new WP_REST_Response(
					McpErrorHandler::parse_error( 0, 'Invalid JSON in request body' ),
					400
				);
			}

			// Handle both single messages and batched arrays.
			$messages                       = is_array( $body ) && isset( $body[0] ) ? $body : array( $body );
			$has_requests                   = false;
			$has_notifications_or_responses = false;

			// Validate all messages and categorize them.
			foreach ( $messages as $message ) {
				$validation_result = McpErrorHandler::validate_jsonrpc_message( $message );
				if ( true !== $validation_result ) {
					return new WP_REST_Response( $validation_result, 400 );
				}

				// Check if it's a request (has id and method) or notification/response.
				if ( isset( $message['method'] ) && isset( $message['id'] ) ) {
					$has_requests = true;
				} else {
					$has_notifications_or_responses = true;
				}
			}

			// If only notifications or responses, return 202 Accepted with no body.
			if ( $has_notifications_or_responses && ! $has_requests ) {
				return new WP_REST_Response( null, 202 );
			}

			// Process requests and return JSON response.
			$results        = array();
			$has_initialize = false;
			foreach ( $messages as $message ) {
				if ( isset( $message['method'] ) && isset( $message['id'] ) ) {
					$this->request_id = (int) $message['id'];
					if ( 'initialize' === $message['method'] ) {
						$has_initialize = true;
					}
					$results[] = $this->process_message( $message );
				}
			}

			// Return single result or batch.
			$response_body = count( $results ) === 1 ? $results[0] : $results;

			$headers = array(
				'Content-Type'                 => 'application/json',
				'Access-Control-Allow-Origin'  => '*',
				'Access-Control-Allow-Methods' => 'OPTIONS, GET, POST, PUT, PATCH, DELETE',
			);

			return new WP_REST_Response( $response_body, 200, $headers );
		} catch ( \Throwable $exception ) {
			// Handle any unexpected exceptions.
			McpErrorHandler::log_error( 'Unexpected error in handle_post_request', array( 'exception' => $exception->getMessage() ) );
			return new WP_REST_Response(
				McpErrorHandler::handle_exception( $exception, $this->request_id ),
				500
			);
		}
	}

	/**
	 * Process a JSON-RPC message
	 *
	 * @param array $message The JSON-RPC message.
	 * @return array
	 */
	private function process_message( array $message ): array {
		$this->request_id = (int) $message['id'];
		$params           = $message['params'] ?? array();

		// Route the request using the base class.
		$result = $this->route_request( $message['method'], $params, $this->request_id );

		// Check if the result contains an error.
		if ( isset( $result['error'] ) ) {
			return $this->format_error_response( $result, $this->request_id );
		}

		return $this->format_success_response( $result, $this->request_id );
	}

	/**
	 * Create a method not found error (JSON-RPC 2.0 format)
	 *
	 * @param string $method The method that was not found.
	 * @param int    $request_id The request ID.
	 * @return array
	 */
	protected function create_method_not_found_error( string $method, int $request_id ): array {
		return array(
			'error' => McpErrorHandler::method_not_found( $request_id, $method )['error'],
		);
	}

	/**
	 * Handle exceptions that occur during request processing (JSON-RPC 2.0 format)
	 *
	 * @param \Throwable $exception The exception.
	 * @param int        $request_id The request ID.
	 * @return array
	 */
	protected function handle_exception( \Throwable $exception, int $request_id ): array {
		return McpErrorHandler::handle_exception( $exception, $request_id );
	}

	/**
	 * Format a successful response (JSON-RPC 2.0 format)
	 *
	 * @param array $result The result data.
	 * @param int   $request_id The request ID.
	 * @return array
	 */
	protected function format_success_response( array $result, int $request_id = 0 ): array {
		$response = array(
			'jsonrpc' => '2.0',
			'id'      => $request_id,
			'result'  => $result,
		);

		return $response;
	}

	/**
	 * Format an error response (JSON-RPC 2.0 format)
	 *
	 * @param array $error The error data.
	 * @param int   $request_id The request ID.
	 * @return array
	 */
	protected function format_error_response( array $error, int $request_id = 0 ): array {
		if ( isset( $error['error'] ) ) {
			return array(
				'jsonrpc' => '2.0',
				'id'      => $request_id,
				'error'   => $error['error'],
			);
		}

		// If it's not already a proper error response, make it one.
		return McpErrorHandler::internal_error( $request_id, 'Invalid error response format' );
	}
}
