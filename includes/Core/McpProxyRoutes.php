<?php //phpcs:ignore
declare(strict_types=1);

namespace Automattic\WordpressMcp\Core;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use Automattic\WordpressMcp\Utils\HandleToolsCall;
use Automattic\WordpressMcp\Utils\HandlePromptGet;

/**
 * Class McpProxyRoutes
 *
 * Registers REST API routes for the Model Context Protocol (MCP) proxy.
 */
class McpProxyRoutes {

	/**
	 * The WordPress MCP instance.
	 *
	 * @var WpMcp
	 */
	private WpMcp $mcp;

	/**
	 * Initialize the class and register routes
	 *
	 * @param WpMcp $mcp The WordPress MCP instance.
	 */
	public function __construct( WpMcp $mcp ) {
		$this->mcp = $mcp;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all MCP proxy routes
	 */
	public function register_routes(): void {

		// Check if MCP is enabled in settings.
		$options = get_option( 'wordpress_mcp_settings', array() );
		$enabled = isset( $options['enabled'] ) && $options['enabled'];

		// If MCP is disabled, don't register routes.
		if ( ! $enabled ) {
			return;
		}

		// Single endpoint for all MCP operations.
		register_rest_route(
			'wp/v2',
			'/wpmcp',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_request' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);
	}

	/**
	 * Check if the user has permission to access the MCP API
	 *
	 * @return bool|WP_Error
	 */
       public function check_permission( WP_REST_Request $request ): WP_Error|bool {
               // Check if MCP is enabled in settings.
               $options = get_option( 'wordpress_mcp_settings', array() );
               $enabled = isset( $options['enabled'] ) && $options['enabled'];

		// If MCP is disabled, deny access.
		if ( ! $enabled ) {
			return new WP_Error(
				'mcp_disabled',
				'MCP functionality is currently disabled.',
				array( 'status' => 403 )
			);
		}

               if ( ! is_user_logged_in() ) {
                       return new WP_Error(
                               'rest_forbidden',
                               __( 'You are not currently logged in.', 'wordpress-mcp' ),
                               array( 'status' => rest_authorization_required_code() )
                       );
               }

               $params = $request->get_json_params();
               $method = $params['method'] ?? '';

               if ( 'tools/call' === $method && isset( $params['name'] ) ) {
                       $callbacks = $this->mcp->get_tools_callbacks();
                       $tool_name = $params['name'];
                       if ( isset( $callbacks[ $tool_name ]['permission_callback'] ) && is_callable( $callbacks[ $tool_name ]['permission_callback'] ) ) {
                               $args = $params['arguments'] ?? array();
                               if ( ! call_user_func( $callbacks[ $tool_name ]['permission_callback'], $args ) ) {
                                       return new WP_Error(
                                               'rest_forbidden',
                                               __( 'You do not have permission to perform this action.', 'wordpress-mcp' ),
                                               array( 'status' => 403 )
                                       );
                               }
                       }
               }

               return true;
       }

	/**
	 * Handle all MCP requests
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_request( WP_REST_Request $request ): WP_Error|WP_REST_Response {
		$params = $request->get_json_params();

		if ( empty( $params ) || ! isset( $params['method'] ) ) {
			return new WP_Error(
				'invalid_request',
				'Invalid request: method parameter is required',
				array( 'status' => 400 )
			);
		}

		$method = $params['method'];

		// Route the request to the appropriate handler based on the method.
		return match ( $method ) {
			'init' => $this->init( $params ),
			'tools/list' => $this->list_tools( $params ),
			'tools/call' => $this->call_tool( $params ),
			'resources/list' => $this->list_resources( $params ),
			'resources/templates/list' => $this->list_resource_templates( $params ),
			'resources/read' => $this->read_resource( $params ),
			'resources/subscribe' => $this->subscribe_resource( $params ),
			'resources/unsubscribe' => $this->unsubscribe_resource( $params ),
			'prompts/list' => $this->list_prompts( $params ),
			'prompts/get' => $this->get_prompt( $params ),
			'logging/setLevel' => $this->set_logging_level( $params ),
			'completion/complete' => $this->complete( $params ),
			'roots/list' => $this->list_roots( $params ),
			default => new WP_Error(
				'invalid_method',
				'Invalid method: ' . $method,
				array( 'status' => 400 )
			),
		};
	}

	/**
	 * Initialize the MCP server
	 *
	 * @param array $params Request parameters.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function init( array $params ): WP_Error|WP_REST_Response {
		// @todo: the name should be editable from the admin page
		$server_info = array(
			'name'    => 'WordPress MCP Server',
			'version' => '1.0.0',
		);

		// @todo: add capabilities based on your implementation
		$capabilities = array(
			'tools'      => array(
				'list' => true,
				'call' => true,
			),
			'resources'  => array(
				'list' => true,
			),
			'prompts'    => array(
				'list' => true,
				'get'  => true,
			),
			'logging'    => array(
				'setLevel' => true,
			),
			'completion' => array(
				'complete' => true,
			),
			'roots'      => array(
				'list' => true,
			),
		);

		return rest_ensure_response(
			array(
				'serverInfo'   => $server_info,
				'capabilities' => $capabilities,
			)
		);
	}

	/**
	 * List available tools
	 *
	 * @param array $params Request parameters.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
       public function list_tools( array $params ): WP_Error|WP_REST_Response {

               $tools            = $this->mcp->get_tools();
               $tools_callbacks  = $this->mcp->get_tools_callbacks();
               $filtered_tools   = array();

               foreach ( $tools as $tool ) {
                       $name = $tool['name'];
                       $allowed = true;
                       if ( isset( $tools_callbacks[ $name ]['permission_callback'] ) && is_callable( $tools_callbacks[ $name ]['permission_callback'] ) ) {
                               $allowed = (bool) call_user_func( $tools_callbacks[ $name ]['permission_callback'], array() );
                       }

                       if ( $allowed ) {
                               $filtered_tools[] = $tool;
                       }
               }

               return rest_ensure_response(
                       array(
                               'tools' => array_values( $filtered_tools ),
                       )
               );
       }

	/**
	 * Call a tool
	 *
	 * @param array $params Request parameters.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function call_tool( array $params ): WP_Error|WP_REST_Response {
		if ( ! isset( $params['name'] ) ) {
			return new WP_Error(
				'missing_parameter',
				'Missing required parameter: name',
				array( 'status' => 400 )
			);
		}

               // Implement a tool calling logic here.
               $result = HandleToolsCall::run( $params );

               if ( isset( $result['error'] ) ) {
                       $message = $result['error']['message'] ?? __( 'Unknown error', 'wordpress-mcp' );
                       $status  = 400;
                       $code    = 'invalid_request';
                       if ( str_contains( strtolower( $message ), 'permission denied' ) ) {
                               $status = 403;
                               $code   = 'rest_forbidden';
                       }

                       return new WP_Error( $code, $message, array( 'status' => $status ) );
               }

		$response = array(
			'content' => array(
				array(
					'type' => 'text',
				),
			),
		);

		// @todo: add support for EmbeddedResource schema.ts:619.
		if ( isset( $result['type'] ) && 'image' === $result['type'] ) {
			$response['content'][0]['type'] = 'image';
			$response['content'][0]['data'] = base64_encode( $result['results'] ); //phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

			// @todo: improve this ?!.
			$response['content'][0]['mimeType'] = $result['mimeType'] ?? 'image/png';
		} else {
			$response['content'][0]['text'] = wp_json_encode( $result );
		}

		return rest_ensure_response(
			$response
		);
	}

	/**
	 * List resources
	 *
	 * @param array $params Request parameters.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_resources( array $params ): WP_Error|WP_REST_Response {

		// Get the registered resources from the MCP instance.
		$resources = array_values( $this->mcp->get_resources() );

		return rest_ensure_response(
			array(
				'resources'  => $resources,
				'nextCursor' => '',
			)
		);
	}

	/**
	 * List resource templates
	 *
	 * @param array $params Request parameters.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_resource_templates( array $params ): WP_Error|WP_REST_Response {

		// Implement resource template listing logic here.
		$templates = array();

		return rest_ensure_response(
			array(
				'templates'  => $templates,
				'nextCursor' => '',
			)
		);
	}

	/**
	 * Read a resource
	 *
	 * @param array $params Request parameters.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function read_resource( array $params ): WP_Error|WP_REST_Response {
		if ( ! isset( $params['uri'] ) ) {
			return new WP_Error(
				'missing_parameter',
				'Missing required parameter: uri',
				array( 'status' => 400 )
			);
		}

		// Implement resource reading logic here.
		$uri                = $params['uri'];
		$resource_callbacks = $this->mcp->get_resource_callbacks();

		if ( ! isset( $resource_callbacks[ $uri ] ) ) {
			return new WP_Error(
				'resource_not_found',
				'Resource not found: ' . $uri,
				array( 'status' => 404 )
			);
		}

		$callback = $resource_callbacks[ $uri ];
		$content  = call_user_func( $callback, $params );

		$resource = $this->mcp->get_resources()[ $uri ];

		return rest_ensure_response(
			array(
				'contents' => array(
					array(
						'uri'      => $uri,
						'mimeType' => $resource['mimeType'],
						'text'     => wp_json_encode( $content ),
					),
				),
			)
		);
	}

	/**
	 * Subscribe to a resource
	 *
	 * @param array $params Request parameters.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function subscribe_resource( array $params ): WP_Error|WP_REST_Response {
		if ( ! isset( $params['uri'] ) ) {
			return new WP_Error(
				'missing_parameter',
				'Missing required parameter: uri',
				array( 'status' => 400 )
			);
		}

		// Implement resource subscription logic here.
		$uri = $params['uri'];

		return rest_ensure_response(
			array(
				'subscriptionId' => 'sub_' . md5( $uri ),
			)
		);
	}

	/**
	 * Unsubscribe from a resource
	 *
	 * @param array $params Request parameters.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function unsubscribe_resource( array $params ): WP_Error|WP_REST_Response {
		if ( ! isset( $params['subscriptionId'] ) ) {
			return new WP_Error(
				'missing_parameter',
				'Missing required parameter: subscriptionId',
				array( 'status' => 400 )
			);
		}

		// @todo: Implement resource unsubscription logic here.

		return rest_ensure_response(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * List prompt.
	 *
	 * @param array $params
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_prompts( array $params ): WP_Error|WP_REST_Response {
		return rest_ensure_response(
			array(
				'prompts' => array_values( $this->mcp->get_prompts() ),
			)
		);
	}

	/**
	 * Get a prompt
	 *
	 * @param array $params Request parameters.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_prompt( array $params ): WP_Error|WP_REST_Response {
		if ( ! isset( $params['name'] ) ) {
			return new WP_Error(
				'missing_parameter',
				'Missing required parameter: name',
				array( 'status' => 400 )
			);
		}

		// Get the prompt by name.
		$prompt_name = $params['name'];
		$prompt      = $this->mcp->get_prompt_by_name( $prompt_name );

		if ( ! $prompt ) {
			return new WP_Error(
				'prompt_not_found',
				'Prompt not found: ' . $prompt_name,
				array( 'status' => 404 )
			);
		}

		// Get the arguments for the prompt.
		$arguments = $params['arguments'] ?? array();
		$messages  = $this->mcp->get_prompt_messages( $prompt_name );

		return rest_ensure_response(
			HandlePromptGet::run( $prompt, $messages, $arguments )
		);
	}

	/**
	 * Set the logging level
	 *
	 * @param array $params Request parameters.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function set_logging_level( array $params ): WP_Error|WP_REST_Response {
		if ( ! isset( $params['level'] ) ) {
			return new WP_Error(
				'missing_parameter',
				'Missing required parameter: level',
				array( 'status' => 400 )
			);
		}

		// @todo: Implement logging level setting logic here.

		return rest_ensure_response(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * Complete a request
	 *
	 * @param array $params Request parameters.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function complete( array $params ): WP_Error|WP_REST_Response {
		// Implement completion logic here.

		return rest_ensure_response(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * List roots
	 *
	 * @param array $params Request parameters.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function list_roots( array $params ): WP_Error|WP_REST_Response {
		// Implement roots listing logic here.
		$roots = array();

		return rest_ensure_response(
			array(
				'roots' => $roots,
			)
		);
	}
}
