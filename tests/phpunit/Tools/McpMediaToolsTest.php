<?php
/**
 * Test class for McpMediaTools
 *
 * @package Automattic\WordpressMcp\Tests\Tools
 */

namespace Automattic\WordpressMcp\Tests\Tools;

use Automattic\WordpressMcp\Core\WpMcp;
use Automattic\WordpressMcp\Tools\McpMediaTools;
use WP_UnitTestCase;
use WP_REST_Request;
use WP_User;

/**
 * Test class for McpMediaTools
 */
final class McpMediaToolsTest extends WP_UnitTestCase {

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

		// Enable MCP in settings
		update_option(
			'wordpress_mcp_settings',
			array(
				'enabled' => true,
			)
		);

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
	 * Test the wp_list_media tool.
	 */
	public function test_wp_list_media_tool(): void {
		// Create a test media attachment.
		$attachment_id = $this->factory->attachment->create_upload_object(
			__DIR__ . '/../../assets/test-image.jpeg',
		);

		// Create a REST request.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );

		// Set the request body as JSON.
		$request->set_body(
			wp_json_encode(
				array(
					'method' => 'tools/call',
					'name'   => 'wp_list_media',
				)
			)
		);

		// Set content type header.
		$request->add_header( 'Content-Type', 'application/json' );

		// Set the current user.
		wp_set_current_user( $this->admin_user->ID );

		// Dispatch the request.
		$response = rest_do_request( $request );

		// delete image after test (avoid duplicate media).
		wp_delete_attachment( $attachment_id, true );

		// Check the response.
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'content', $response->get_data() );
		$this->assertIsArray( $response->get_data()['content'] );

		// Get the media items from the response.
		$media_items = json_decode( $response->get_data()['content'][0]['text'], true );
		$media_item  = $media_items[0];

		// Assert media data.
		$this->assertStringContainsString( 'test-image', $media_item['title']['rendered'] );
	}

	/**
	 * Test the wp_get_media tool.
	 */
	public function test_wp_get_media_tool(): void {
		// Create a test media attachment.
		$attachment_id = $this->factory->attachment->create_upload_object(
			__DIR__ . '/../../assets/test-image.jpeg',
		);

		// Create a REST request.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );

		// Set the request body as JSON.
		$request->set_body(
			wp_json_encode(
				array(
					'method'    => 'tools/call',
					'name'      => 'wp_get_media',
					'arguments' => array(
						'id' => $attachment_id,
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

		// delete image after test (avoid duplicate media).
		wp_delete_attachment( $attachment_id, true );

		// Check the response.
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'content', $response->get_data() );
		$this->assertIsArray( $response->get_data()['content'] );
		$this->assertCount( 1, $response->get_data()['content'] );
		$this->assertEquals( 'text', $response->get_data()['content'][0]['type'] );
		$this->assertStringContainsString( 'test-image', $response->get_data()['content'][0]['text'] );
	}

	/**
	 * Test the wp_get_media_file tool.
	 */
	public function test_wp_get_media_file_tool(): void {
		// Create a test media attachment.
		$attachment_id = $this->factory->attachment->create_upload_object(
			__DIR__ . '/../../assets/test-image.jpeg',
		);

		// Create a REST request.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );

		// Set the request body as JSON.
		$request->set_body(
			wp_json_encode(
				array(
					'method'    => 'tools/call',
					'name'      => 'wp_get_media_file',
					'arguments' => array(
						'id' => $attachment_id,
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

		// delete image after test (avoid duplicate media).
		wp_delete_attachment( $attachment_id, true );

		// Check the response.
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'content', $response->get_data() );
		$this->assertIsArray( $response->get_data()['content'] );
		$this->assertCount( 1, $response->get_data()['content'] );
		$this->assertEquals( 'image', $response->get_data()['content'][0]['type'] );
		$this->assertArrayHasKey( 'data', $response->get_data()['content'][0] );
		$this->assertEquals( 'image/jpeg', $response->get_data()['content'][0]['mimeType'] );
	}

	/**
	 * Test the wp_upload_media tool.
	 */
	public function test_wp_upload_media_tool(): void {
		// Create a test image file.
		$test_image_path = __DIR__ . '/../../assets/test-image.jpeg';
		$test_image_data = file_get_contents( $test_image_path );
		$base64_data     = base64_encode( $test_image_data );

		// Create a REST request.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );

		// Set the request body as JSON.
		$request->set_body(
			wp_json_encode(
				array(
					'method'    => 'tools/call',
					'name'      => 'wp_upload_media',
					'arguments' => array(
						'file'        => $base64_data,
						'title'       => 'Uploaded Test Image',
						'description' => 'Uploaded Test Image Content',
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

		// Get the uploaded attachment ID for cleanup.
		$response_text_content = json_decode( $response->get_data()['content'][0]['text'], true );

		// Delete image after test (avoid duplicate media).
		if ( isset( $response_text_content['id'] ) ) {
			wp_delete_attachment( $response_text_content['id'], true );
		}

		// Check the response.
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'content', $response->get_data() );
		$this->assertIsArray( $response->get_data()['content'] );
		$this->assertCount( 1, $response->get_data()['content'] );
		$this->assertEquals( 'text', $response->get_data()['content'][0]['type'] );
		$this->assertStringContainsString( 'Uploaded-Test-Image', $response->get_data()['content'][0]['text'] );
	}

	/**
	 * Test the wp_upload_media tool with complete metadata.
	 */
	public function test_wp_upload_media_tool_with_metadata(): void {
		// Create a test image file.
		$test_image_path = __DIR__ . '/../../assets/test-image.jpeg';
		$test_image_data = file_get_contents( $test_image_path );
		$base64_data     = base64_encode( $test_image_data );

		// Create a REST request.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );

		// Set the request body as JSON with complete metadata.
		$request->set_body(
			wp_json_encode(
				array(
					'method'    => 'tools/call',
					'name'      => 'wp_upload_media',
					'arguments' => array(
						'file'        => $base64_data,
						'title'       => 'Complete Metadata Test',
						'caption'     => 'Test image caption',
						'description' => 'Test image description',
						'alt_text'    => 'Test image alt text',
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

		// Get the uploaded attachment ID for cleanup.
		$response_text_content = json_decode( $response->get_data()['content'][0]['text'], true );

		// Delete image after test (avoid duplicate media).
		if ( isset( $response_text_content['id'] ) ) {
			wp_delete_attachment( $response_text_content['id'], true );
		}

		// Check the response.
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'content', $response->get_data() );
		$this->assertIsArray( $response->get_data()['content'] );
		$this->assertCount( 1, $response->get_data()['content'] );
		$this->assertEquals( 'text', $response->get_data()['content'][0]['type'] );
		
		// Verify metadata was preserved in the response.
		$this->assertStringContainsString( 'Complete-Metadata-Test', $response->get_data()['content'][0]['text'] );
	}

	/**
	 * Test the wp_upload_media_pre_callback directly for multipart handling.
	 */
	public function test_wp_upload_media_pre_callback_multipart_format(): void {
		$media_tools = new \Automattic\WordpressMcp\Tools\McpMediaTools();
		
		// Create test data.
		$test_image_data = 'fake-image-data-for-testing';
		$base64_data     = base64_encode( $test_image_data );
		
		$args = array(
			'file'        => $base64_data,
			'title'       => 'Test Title',
			'alt_text'    => 'Test Alt Text',
			'caption'     => 'Test Caption',
			'description' => 'Test Description',
		);

		// Call the pre-callback.
		$result = $media_tools->wp_upload_media_pre_callback( $args );

		// Verify multipart structure.
		$this->assertArrayHasKey( 'headers', $result );
		$this->assertArrayHasKey( 'Content-Type', $result['headers'] );
		$this->assertStringContainsString( 'multipart/form-data; boundary=', $result['headers']['Content-Type'][0] );
		
		$this->assertArrayHasKey( 'body', $result );
		$this->assertStringContainsString( 'Content-Disposition: form-data; name="file"', $result['body'] );
		$this->assertStringContainsString( 'Content-Disposition: form-data; name="title"', $result['body'] );
		$this->assertStringContainsString( 'Content-Disposition: form-data; name="alt_text"', $result['body'] );
		$this->assertStringContainsString( 'Content-Disposition: form-data; name="caption"', $result['body'] );
		$this->assertStringContainsString( 'Content-Disposition: form-data; name="description"', $result['body'] );
		
		// Verify args are cleared.
		$this->assertEmpty( $result['args'] );
	}

	/**
	 * Test the wp_update_media tool.
	 */
	public function test_wp_update_media_tool(): void {
		// Create a test media attachment.
		$attachment_id = $this->factory->attachment->create_upload_object(
			__DIR__ . '/../../assets/test-image.jpeg',
		);

		// Create a REST request.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );

		// Set the request body as JSON.
		$request->set_body(
			wp_json_encode(
				array(
					'method'    => 'tools/call',
					'name'      => 'wp_update_media',
					'arguments' => array(
						'id'          => $attachment_id,
						'title'       => 'Updated Test Image',
						'description' => 'Updated Test Image Content',
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

		// delete image after test (avoid duplicate media).
		wp_delete_attachment( $attachment_id, true );

		// Check the response.
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'content', $response->get_data() );
		$this->assertIsArray( $response->get_data()['content'] );
		$this->assertCount( 1, $response->get_data()['content'] );
		$this->assertEquals( 'text', $response->get_data()['content'][0]['type'] );
		$this->assertStringContainsString( 'Updated Test Image', $response->get_data()['content'][0]['text'] );
	}

	/**
	 * Test the wp_delete_media tool.
	 */
	public function test_wp_delete_media_tool(): void {
		// Create a test media attachment.
		$attachment_id = $this->factory->attachment->create_upload_object(
			__DIR__ . '/../../assets/test-image.jpeg',
		);

		// Create a REST request.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );

		// Set the request body as JSON.
		$request->set_body(
			wp_json_encode(
				array(
					'method'    => 'tools/call',
					'name'      => 'wp_delete_media',
					'arguments' => array(
						'id'    => $attachment_id,
						'force' => true,
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

		// delete image after test (avoid duplicate media).
		wp_delete_attachment( $attachment_id, true );

		// Check the response.
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'content', $response->get_data() );
		$this->assertIsArray( $response->get_data()['content'] );
		$this->assertCount( 1, $response->get_data()['content'] );
		$this->assertEquals( 'text', $response->get_data()['content'][0]['type'] );

		// Verify the attachment is deleted.
		$attachment = get_post( $attachment_id );
		$this->assertNull( $attachment );
	}



	/**
	 * Test the wp_search_media tool.
	 */
	public function test_wp_search_media_tool(): void {
		// Create a test media attachment.
		$attachment_id = $this->factory->attachment->create_upload_object(
			__DIR__ . '/../../assets/test-image.jpeg',
		);

		// Update the attachment title to make it searchable
		wp_update_post(
			array(
				'ID'         => $attachment_id,
				'post_title' => 'Searchable Test Image',
			)
		);

		// Create a REST request.
		$request = new WP_REST_Request( 'POST', '/wp/v2/wpmcp' );

		// Set the request body as JSON.
		$request->set_body(
			wp_json_encode(
				array(
					'method'    => 'tools/call',
					'name'      => 'wp_search_media',
					'arguments' => array(
						'search' => 'test image',
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

		// delete image after test (avoid duplicate media).
		wp_delete_attachment( $attachment_id, true );

		// Check the response.
		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'content', $response->get_data() );
		$this->assertIsArray( $response->get_data()['content'] );
		$this->assertCount( 1, $response->get_data()['content'] );
		$this->assertEquals( 'text', $response->get_data()['content'][0]['type'] );
		$this->assertStringContainsString( 'Searchable Test Image', $response->get_data()['content'][0]['text'] );
	}
}
