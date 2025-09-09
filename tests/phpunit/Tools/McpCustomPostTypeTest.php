<?php
/**
 * Test class for McpCustomPostsTools
 *
 * @package Automattic\WordpressMcp\Tests\Tools
 */

namespace Automattic\WordpressMcp\Tests\Tools;

use Automattic\WordpressMcp\Core\WpMcp;
use Automattic\WordpressMcp\Tools\McpCustomPostsTools;
use WP_UnitTestCase;
use WP_REST_Request;
use WP_User;

/**
 * Test class for McpCustomPostsTools
 */
final class McpCustomPostTypeTest extends WP_UnitTestCase {

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
	public function set_up(): void {
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
	 * Test the wp_list_post_types tool.
	 */
	public function test_wp_list_post_types_tool(): void {
		// Create a REST request.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );

		// Set the request body as JSON.
		$request->set_body(
			wp_json_encode(
				array(
					'method' => 'tools/call',
					'name'   => 'wp_list_post_types',
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
	 * Test the wp_cpt_search tool.
	 */
	public function test_wp_cpt_search_tool(): void {
		// Create a test custom post type.
		register_post_type(
			'test_cpt',
			array(
				'public' => true,
				'label'  => 'Test CPT',
			)
		);

		// Create a test post.
		$post_id = $this->factory->post->create(
			array(
				'post_type'    => 'test_cpt',
				'post_title'   => 'Test CPT Post',
				'post_content' => 'Test CPT Content',
				'post_status'  => 'publish',
			)
		);

		// Create a REST request.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );

		// Set the request body as JSON.
		$request->set_body(
			wp_json_encode(
				array(
					'method'    => 'tools/call',
					'name'      => 'wp_cpt_search',
					'arguments' => array(
						'post_type' => 'test_cpt',
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
		$this->assertStringContainsString( 'Test CPT Post', $response->get_data()['content'][0]['text'] );
	}

	/**
	 * Test the wp_get_cpt tool.
	 */
	public function test_wp_get_cpt_tool(): void {
		// Create a test custom post type.
		register_post_type(
			'test_cpt',
			array(
				'public' => true,
				'label'  => 'Test CPT',
			)
		);

		// Create a test post.
		$post_id = $this->factory->post->create(
			array(
				'post_type'    => 'test_cpt',
				'post_title'   => 'Test CPT Post',
				'post_content' => 'Test CPT Content',
				'post_status'  => 'publish',
			)
		);

		// Create a REST request.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );

		// Set the request body as JSON.
		$request->set_body(
			wp_json_encode(
				array(
					'method'    => 'tools/call',
					'name'      => 'wp_get_cpt',
					'arguments' => array(
						'post_type' => 'test_cpt',
						'id'        => $post_id,
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
		$this->assertStringContainsString( 'Test CPT Post', $response->get_data()['content'][0]['text'] );
	}

	/**
	 * Test the wp_add_cpt tool.
	 */
	public function test_wp_add_cpt_tool(): void {
		// Create a test custom post type.
		register_post_type(
			'test_cpt',
			array(
				'public' => true,
				'label'  => 'Test CPT',
			)
		);

		// Create a REST request.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );

		// Set the request body as JSON.
		$request->set_body(
			wp_json_encode(
				array(
					'method'    => 'tools/call',
					'name'      => 'wp_add_cpt',
					'arguments' => array(
						'post_type' => 'test_cpt',
						'title'     => 'New Test CPT Post',
						'content'   => '<!-- wp:paragraph --><p>New Test CPT Content</p><!-- /wp:paragraph -->',
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
		$this->assertStringContainsString( 'New Test CPT Post', $response->get_data()['content'][0]['text'] );
	}

	/**
	 * Test the wp_update_cpt tool.
	 */
	public function test_wp_update_cpt_tool(): void {
		// Create a test custom post type.
		register_post_type(
			'test_cpt',
			array(
				'public' => true,
				'label'  => 'Test CPT',
			)
		);

		// Create a test post.
		$post_id = $this->factory->post->create(
			array(
				'post_type'    => 'test_cpt',
				'post_title'   => 'Original Test CPT Post',
				'post_content' => 'Original Test CPT Content',
				'post_status'  => 'publish',
			)
		);

		// Create a REST request.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );

		// Set the request body as JSON.
		$request->set_body(
			wp_json_encode(
				array(
					'method'    => 'tools/call',
					'name'      => 'wp_update_cpt',
					'arguments' => array(
						'post_type' => 'test_cpt',
						'id'        => $post_id,
						'title'     => 'Updated Test CPT Post',
						'content'   => '<!-- wp:paragraph --><p>Updated Test CPT Content</p><!-- /wp:paragraph -->',
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
		$this->assertStringContainsString( 'Updated Test CPT Post', $response->get_data()['content'][0]['text'] );
	}

	/**
	 * Test the wp_delete_cpt tool.
	 */
	public function test_wp_delete_cpt_tool(): void {
		// Create a test custom post type.
		register_post_type(
			'test_cpt',
			array(
				'public' => true,
				'label'  => 'Test CPT',
			)
		);

		// Create a test post.
		$post_id = $this->factory->post->create(
			array(
				'post_type'    => 'test_cpt',
				'post_title'   => 'Test CPT Post to Delete',
				'post_content' => 'Test CPT Content to Delete',
				'post_status'  => 'publish',
			)
		);

		// Create a REST request.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );

		// Set the request body as JSON.
		$request->set_body(
			wp_json_encode(
				array(
					'method'    => 'tools/call',
					'name'      => 'wp_delete_cpt',
					'arguments' => array(
						'post_type' => 'test_cpt',
						'id'        => $post_id,
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

		// Verify the post is deleted.
		$post = get_post( $post_id );
		$this->assertNull( $post );
	}
}
