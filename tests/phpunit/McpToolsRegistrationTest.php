<?php
/**
 * Tests for the RegisterMcpTool class.
 *
 * @package WordPressMcp
 * @subpackage Tests
 */

namespace Automattic\WordpressMcp\Tests;

use Automattic\WordpressMcp\Core\RegisterMcpTool;
use WP_UnitTestCase;
use Automattic\WordpressMcp\Core\WpMcp;
use WP_REST_Request;
use WP_User;

/**
 * Test cases for the RegisterMcpTool class.
 */
class McpToolsRegistrationTest extends WP_UnitTestCase {

	/**
	 * The MCP instance.
	 *
	 * @var WpMcp
	 */
	private WpMcp $mcp;

	/**
	 * The admin user.
	 *
	 * @var WP_User
	 */
	private WP_User $admin_user;

	/**
	 * Set up the test.
	 */
	public function setUp(): void {
		parent::set_up();

		// Create an admin user.
		$this->admin_user = $this->factory->user->create_and_get(
			array(
				'role' => 'administrator',
			)
		);

		// Get the MCP instance.
		$this->mcp = WPMCP();

		// Initialize the REST API and MCP.
		do_action( 'rest_api_init' );
	}

	/**
	 * Test the tools/list endpoint.
	 */
	public function test_list_tools_endpoint(): void {
		// Create a REST request.
		$request = new \WP_REST_Request( 'POST', '/wp/v2/wpmcp' );

		// Set the request body as JSON.
		$request->set_body(
			wp_json_encode(
				array(
					'method' => 'tools/list',
				)
			)
		);

		// Set content type header.
		$request->add_header( 'Content-Type', 'application/json' );

		// Set the current user.
		wp_set_current_user( $this->admin_user->ID );

		// Dispatch the request.
		$response = rest_do_request( $request );

		// Check the response.
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'tools', $response->get_data() );
		$this->assertIsArray( $response->get_data()['tools'] );
	}

	/**
	 * Test that RegisterMcpTool throws an exception when created outside wordpress_mcp_init hook.
	 */
	public function test_register_mcp_tool_throws_exception_outside_hook() {
		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'RegisterMcpTool can only be used within the wordpress_mcp_init action.' );

		new RegisterMcpTool(
			array(
				'name'                => 'test_tool',
				'description'         => 'Test tool description',
				'type'                => 'read',
				'callback'            => function () {},
				'permission_callback' => function () {
					return true; },
				'inputSchema'         => array(
					'type'       => 'object',
					'properties' => array(),
				),
			)
		);
	}

	/**
	 * Test that RegisterMcpTool can be created within wordpress_mcp_init hook.
	 */
	public function test_register_mcp_tool_works_inside_hook() {
		$tool_created = false;

		add_action(
			'wordpress_mcp_init',
			function () use ( &$tool_created ) {
				try {
					new RegisterMcpTool(
						array(
							'name'                => 'test_tool',
							'description'         => 'Test tool description',
							'type'                => 'read',
							'callback'            => function () {},
							'permission_callback' => function () {
								return true; },
							'inputSchema'         => array(
								'type'       => 'object',
								'properties' => array(),
							),
						)
					);
					$tool_created = true;
				} catch ( \Exception $e ) {
					$this->fail( 'RegisterMcpTool should not throw an exception when created within wordpress_mcp_init hook' );
				}
			}
		);

		do_action( 'wordpress_mcp_init' );

		$this->assertTrue( $tool_created, 'Tool should be created successfully within the hook' );
	}


		/**
		 * Test the tools/call endpoint with a valid tool.
		 */
	public function test_call_tool_endpoint_with_valid_tool(): void {
		// Register a test tool within the wordpress_mcp_init action.
		add_action(
			'wordpress_mcp_init',
			function () {
				new RegisterMcpTool(
					array(
						'name'                => 'test_tool',
						'description'         => 'A test tool',
						'type'                => 'read',
						'callback'            => function () {
							return array( 'success' => true );
						},
						'permission_callback' => function () {
							return current_user_can( 'manage_options' );
						},
						'inputSchema'         => array(
							'type'       => 'object',
							'properties' => array(
								'param1' => array( 'type' => 'string' ),
							),
						),
					)
				);
			}
		);

		do_action( 'wordpress_mcp_init' );

		// Create a REST request.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );

		// Set the request body as JSON.
		$request->set_body(
			wp_json_encode(
				array(
					'method' => 'tools/call',
					'name'   => 'test_tool',
				)
			)
		);

		// Set content type header.
		$request->add_header( 'Content-Type', 'application/json' );

		// Set the current user.
		wp_set_current_user( $this->admin_user->ID );

		// Dispatch the request.
		$response = rest_do_request( $request );

		// Check the response.
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'content', $response->get_data() );
		$this->assertIsArray( $response->get_data()['content'] );
		$this->assertCount( 1, $response->get_data()['content'] );
		$this->assertEquals( 'text', $response->get_data()['content'][0]['type'] );
		$this->assertEquals( '{"success":true}', $response->get_data()['content'][0]['text'] );
	}

	/**
	 * Test the tools/call endpoint with an invalid tool.
	 */
	public function test_call_tool_endpoint_with_invalid_tool(): void {
		// Create a REST request.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );
		$request->set_body_params(
			array(
				'method' => 'tools/call',
				'name'   => 'non_existent_tool',
			)
		);

		// Set the current user.
		wp_set_current_user( $this->admin_user->ID );

		// Dispatch the request.
		$response = rest_do_request( $request );

		// Check the response.
		$this->assertEquals( 400, $response->get_status() );
		$this->assertArrayHasKey( 'code', $response->get_data() );
		$this->assertEquals( 'invalid_request', $response->get_data()['code'] );
	}

	/**
	 * Test the tools/call endpoint with a tool that requires permissions.
	 */
	public function test_call_tool_endpoint_with_permissions(): void {
		add_action(
			'wordpress_mcp_init',
			function () {
				// Register a test tool with permissions.
				new RegisterMcpTool(
					array(
						'name'                => 'permission_tool',
						'description'         => 'A tool that requires permissions',
						'type'                => 'read',
						'callback'            => function () {
							return array( 'success' => true );
						},
						'permission_callback' => function () {
							return current_user_can( 'manage_options' );
						},
						'inputSchema'         => array(
							'type'       => 'object',
							'properties' => array(
								'param1' => array( 'type' => 'string' ),
							),
						),
					)
				);
			}
		);

		do_action( 'wordpress_mcp_init' );

		// Create a REST request.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );
		$request->set_body_params(
			array(
				'method' => 'tools/call',
				'name'   => 'permission_tool',
			)
		);

		// Set the current user to a non-admin user.
		$non_admin_user = $this->factory->user->create_and_get(
			array(
				'role' => 'subscriber',
			)
		);
		wp_set_current_user( $non_admin_user->ID );

		// Dispatch the request.
		$response = rest_do_request( $request );

		// Check the response.
		$this->assertEquals( 403, $response->get_status() );
		$this->assertArrayHasKey( 'code', $response->get_data() );
		$this->assertEquals( 'rest_forbidden', $response->get_data()['code'] );
	}

	/**
	 * Test the tools/call endpoint with a tool that returns an image.
	 */
	public function test_call_tool_endpoint_with_image_response(): void {

		add_action(
			'wordpress_mcp_init',
			function () {
				// Register a test tool that returns an image.
				new RegisterMcpTool(
					array(
						'name'                => 'image_tool',
						'description'         => 'A tool that returns an image',
						'type'                => 'read',
						'callback'            => function () {
							return array(
								'type'     => 'image',
								'results'  => 'fake_image_data',
								'mimeType' => 'image/png',
							);
						},
						'permission_callback' => function () {
							return current_user_can( 'manage_options' );
						},
						'inputSchema'         => array(
							'type'       => 'object',
							'properties' => array(
								'param1' => array( 'type' => 'string' ),
							),
						),
					)
				);
			}
		);

		do_action( 'wordpress_mcp_init' );

		// Create a REST request.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );

		// Set the request body as JSON.
		$request->set_body(
			wp_json_encode(
				array(
					'method' => 'tools/call',
					'name'   => 'image_tool',
				)
			)
		);

		// Set content type header.
		$request->add_header( 'Content-Type', 'application/json' );

		// Set the current user.
		wp_set_current_user( $this->admin_user->ID );

		// Dispatch the request.
		$response = rest_do_request( $request );

		// Check the response.
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'content', $response->get_data() );
		$this->assertIsArray( $response->get_data()['content'] );
		$this->assertCount( 1, $response->get_data()['content'] );
		$this->assertEquals( 'image', $response->get_data()['content'][0]['type'] );
		$this->assertEquals( 'fake_image_data', base64_decode( $response->get_data()['content'][0]['data'] ) );
		$this->assertEquals( 'image/png', $response->get_data()['content'][0]['mimeType'] );
	}

	/**
	 * Test the tools/call endpoint with a tool that has a REST API alias.
	 */
	public function test_call_tool_endpoint_with_rest_alias(): void {
		// Register a test tool with a REST API alias.
		add_action(
			'wordpress_mcp_init',
			function () {
				new RegisterMcpTool(
					array(
						'name'        => 'rest_alias_tool',
						'description' => 'A tool with a REST API alias',
						'type'        => 'read',
						'rest_alias'  => array(
							'method' => 'GET',
							'route'  => '/wp/v2/posts',
						),
					)
				);
			}
		);

		do_action( 'wordpress_mcp_init' );

		// Create a REST request.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );

		// Set the request body as JSON.
		$request->set_body(
			wp_json_encode(
				array(
					'method' => 'tools/call',
					'name'   => 'rest_alias_tool',
				)
			)
		);

		// Set content type header.
		$request->add_header( 'Content-Type', 'application/json' );

		// Set the current user.
		wp_set_current_user( $this->admin_user->ID );

		// Dispatch the request.
		$response = rest_do_request( $request );

		// Check the response.
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'content', $response->get_data() );
		$this->assertIsArray( $response->get_data()['content'] );
		$this->assertCount( 1, $response->get_data()['content'] );
		$this->assertEquals( 'text', $response->get_data()['content'][0]['type'] );
	}

	/**
	 * Test the tools/call endpoint with a tool that has input parameters.
	 */
	public function test_call_tool_endpoint_with_input_parameters(): void {
		// Register a test tool with input parameters.
		add_action(
			'wordpress_mcp_init',
			function () {
				new RegisterMcpTool(
					array(
						'name'                => 'input_tool',
						'description'         => 'A tool with input parameters',
						'type'                => 'read',
						'callback'            => function ( $params ) {
							return array(
								'success' => true,
								'params'  => $params,
							);
						},
						'permission_callback' => function () {
							return current_user_can( 'manage_options' );
						},
						'inputSchema'         => array(
							'type'       => 'object',
							'properties' => array(
								'param1' => array(
									'type'        => 'string',
									'description' => 'First parameter',
								),
								'param2' => array(
									'type'        => 'integer',
									'description' => 'Second parameter',
								),
							),
							'required'   => array( 'param1' ),
						),
					)
				);
			}
		);

		do_action( 'wordpress_mcp_init' );

		// Create a REST request.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );

		// Set the request body as JSON.
		$request->set_body(
			wp_json_encode(
				array(
					'method'    => 'tools/call',
					'name'      => 'input_tool',
					'arguments' => array(
						'param1' => 'value1',
						'param2' => 42,
					),
				)
			)
		);

		// Set content type header.
		$request->add_header( 'Content-Type', 'application/json' );

		// Set the current user.
		wp_set_current_user( $this->admin_user->ID );

		// Dispatch the request.
		$response = rest_do_request( $request );

		// Check the response.
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'content', $response->get_data() );
		$this->assertIsArray( $response->get_data()['content'] );
		$this->assertCount( 1, $response->get_data()['content'] );
		$this->assertEquals( 'text', $response->get_data()['content'][0]['type'] );
		$this->assertStringContainsString( 'value1', $response->get_data()['content'][0]['text'] );
		$this->assertStringContainsString( '42', $response->get_data()['content'][0]['text'] );
	}

	/**
	 * Test the tools/call endpoint with a tool that has required input parameters.
	 */
	public function test_call_tool_endpoint_with_required_input_parameters(): void {

		add_action(
			'wordpress_mcp_init',
			function () {
				// Register a test tool with required input parameters.
				new RegisterMcpTool(
					array(
						'name'                => 'required_input_tool',
						'description'         => 'A tool with required input parameters',
						'type'                => 'read',
						'callback'            => function ( $params ) {
							return array(
								'success' => true,
								'params'  => $params,
							);
						},
						'permission_callback' => function () {
							return current_user_can( 'manage_options' );
						},
						'inputSchema'         => array(
							'type'       => 'object',
							'properties' => array(
								'param1' => array(
									'type'        => 'string',
									'description' => 'First parameter',
								),
								'param2' => array(
									'type'        => 'integer',
									'description' => 'Second parameter',
								),
							),
							'required'   => array( 'param1', 'param2' ),
						),
					)
				);
			}
		);

		do_action( 'wordpress_mcp_init' );

		// Create a REST request with missing required parameter.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );
		$request->set_body_params(
			array(
				'method' => 'tools/call',
				'name'   => 'required_input_tool',
				'params' => array(
					'param1' => 'value1',
					// param2 is missing
				),
			)
		);

		// Set the current user.
		wp_set_current_user( $this->admin_user->ID );

		// Dispatch the request.
		$response = rest_do_request( $request );

		// Check the response.
		$this->assertEquals( 400, $response->get_status() );
		$this->assertArrayHasKey( 'code', $response->get_data() );
		$this->assertEquals( 'invalid_request', $response->get_data()['code'] );
	}

	/**
	 * Test the tools/call endpoint with a tool that has a disabled type.
	 */
        public function test_call_tool_endpoint_with_disabled_type(): void {
		// Disable create tools in settings.
		update_option(
			'wordpress_mcp_settings',
			array(
				'enabled'             => true,
				'enable_create_tools' => false,
			)
		);

		add_action(
			'wordpress_mcp_init',
			function () {
				// Register a test tool with a disabled type.
				new RegisterMcpTool(
					array(
						'name'                => 'create_tool',
						'description'         => 'A tool with a disabled type',
						'type'                => 'create',
						'callback'            => function () {
							return array( 'success' => true );
						},
						'permission_callback' => function () {
							return current_user_can( 'manage_options' );
						},
						'inputSchema'         => array(
							'type'       => 'object',
							'properties' => array(
								'param1' => array( 'type' => 'string' ),
							),
						),
					)
				);
			}
		);

		do_action( 'wordpress_mcp_init' );

		// Create a REST request.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );
		$request->set_body_params(
			array(
				'method' => 'tools/call',
				'name'   => 'create_tool',
			)
		);

		// Set the current user.
		wp_set_current_user( $this->admin_user->ID );

		// Dispatch the request.
		$response = rest_do_request( $request );

		// Check the response.
		$this->assertEquals( 400, $response->get_status() );
		$this->assertArrayHasKey( 'code', $response->get_data() );
                $this->assertEquals( 'invalid_request', $response->get_data()['code'] );
        }

        /**
         * Test the tools/call endpoint with a tool disabled via settings.
         */
        public function test_call_tool_endpoint_with_disabled_tool(): void {
                update_option(
                        'wordpress_mcp_settings',
                        array(
                                'enabled'       => true,
                                'enabled_tools' => array(),
                        )
                );

                add_action(
                        'wordpress_mcp_init',
                        function () {
                                new RegisterMcpTool(
                                        array(
                                                'name'                => 'disabled_tool',
                                                'description'         => 'A disabled tool',
                                                'type'                => 'read',
                                                'callback'            => function () {
                                                        return array( 'success' => true );
                                                },
                                                'permission_callback' => function () {
                                                        return current_user_can( 'manage_options' );
                                                },
                                                'inputSchema'         => array(
                                                        'type'       => 'object',
                                                        'properties' => array(),
                                                ),
                                        )
                                );
                        }
                );

                do_action( 'wordpress_mcp_init' );

                $request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );
                $request->set_body_params(
                        array(
                                'method' => 'tools/call',
                                'name'   => 'disabled_tool',
                        )
                );

                wp_set_current_user( $this->admin_user->ID );

                $response = rest_do_request( $request );

                $this->assertEquals( 403, $response->get_status() );
                $this->assertArrayHasKey( 'code', $response->get_data() );
                $this->assertEquals( 'tool_disabled', $response->get_data()['code'] );
        }

	/**
	 * Test the tools/call endpoint with a tool that has a non-existent REST API route.
	 */
	public function test_call_tool_endpoint_with_non_existent_rest_route(): void {
		// Register a test tool with a non-existent REST API route.
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'The route /wp/v2/non_existent_route with method GET does not exist.' );

		add_action(
			'wordpress_mcp_init',
			function () {
				new RegisterMcpTool(
					array(
						'name'        => 'non_existent_route_tool',
						'description' => 'A tool with a non-existent REST API route',
						'type'        => 'read',
						'rest_alias'  => array(
							'method' => 'GET',
							'route'  => '/wp/v2/non_existent_route',
						),
					)
				);
			}
		);

		do_action( 'wordpress_mcp_init' );
	}

	/**
	 * Test the tools/call endpoint with a tool that has a non-existent REST API method.
	 */
	public function test_call_tool_endpoint_with_non_existent_rest_method(): void {
		// Register a test tool with a non-existent REST API method.
		$this->expectException( \InvalidArgumentException::class );
		$this->expectExceptionMessage( 'The method must be one of the following: GET, POST, PUT, PATCH, DELETE.' );

		add_action(
			'wordpress_mcp_init',
			function () {
				new RegisterMcpTool(
					array(
						'name'        => 'non_existent_method_tool',
						'description' => 'A tool with a non-existent REST API method',
						'type'        => 'read',
						'rest_alias'  => array(
							'method' => 'NON_EXISTENT_METHOD',
							'route'  => '/wp/v2/posts',
						),
					)
				);
			}
		);

		do_action( 'wordpress_mcp_init' );
	}
}
