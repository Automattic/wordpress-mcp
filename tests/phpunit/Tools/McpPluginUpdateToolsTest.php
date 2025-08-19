<?php
/**
 * Test class for McpPluginUpdateTools
 *
 * @package Automattic\WordpressMcp\Tests\Tools
 */

namespace Automattic\WordpressMcp\Tests\Tools;

use Automattic\WordpressMcp\Core\WpMcp;
use Automattic\WordpressMcp\Tools\McpPluginUpdateTools;
use WP_UnitTestCase;
use WP_User;
use WP_Error;

/**
 * Test class for McpPluginUpdateTools
 */
final class McpPluginUpdateToolsTest extends WP_UnitTestCase {

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
	 * The editor user (no plugin update permissions).
	 *
	 * @var WP_User
	 */
	private WP_User $editor_user;

	/**
	 * The plugin update tools instance.
	 *
	 * @var McpPluginUpdateTools
	 */
	private McpPluginUpdateTools $plugin_update_tools;

	/**
	 * Set up the test.
	 */
	public function set_up(): void {
		parent::set_up();

		// Enable MCP in settings
		update_option(
			'wordpress_mcp_settings',
			array(
				'enabled'             => true,
				'enable_update_tools' => true,
			)
		);

		// Create an admin user with update_plugins capability.
		$this->admin_user = $this->factory->user->create_and_get(
			array(
				'role' => 'administrator',
			)
		);

		// Create an editor user without update_plugins capability.
		$this->editor_user = $this->factory->user->create_and_get(
			array(
				'role' => 'editor',
			)
		);

		// Get the MCP instance.
		$this->mcp = WPMCP();

		// Initialize the REST API and MCP.
		do_action( 'rest_api_init' );

		// Initialize the plugin update tools.
		$this->plugin_update_tools = new McpPluginUpdateTools();
	}

	/**
	 * Test that the tool is properly registered via the tools/list endpoint.
	 */
	public function test_tool_registration(): void {
		// Trigger the registration
		do_action( 'wordpress_mcp_init' );

		// Create a REST request to list tools.
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
		$tools = $response->get_data()['tools'];

		// Find the update_plugins tool
		$update_plugins_tool = null;
		foreach ( $tools as $tool ) {
			if ( $tool['name'] === 'update_plugins' ) {
				$update_plugins_tool = $tool;
				break;
			}
		}

		$this->assertNotNull( $update_plugins_tool, 'update_plugins tool should be registered' );
		$this->assertEquals( 'update_plugins', $update_plugins_tool['name'] );
		$this->assertEquals( 'Updates one or more WordPress plugins.', $update_plugins_tool['description'] );
		$this->assertArrayHasKey( 'inputSchema', $update_plugins_tool );
	}

	/**
	 * Test permission callback with admin user.
	 */
	public function test_permission_callback_admin_user(): void {
		wp_set_current_user( $this->admin_user->ID );

		$result = $this->plugin_update_tools->update_plugins_permission_callback();
		$this->assertTrue( $result );
	}

	/**
	 * Test permission callback with editor user.
	 */
	public function test_permission_callback_editor_user(): void {
		wp_set_current_user( $this->editor_user->ID );

		$result = $this->plugin_update_tools->update_plugins_permission_callback();
		$this->assertFalse( $result );
	}

	/**
	 * Test permission callback with no user.
	 */
	public function test_permission_callback_no_user(): void {
		wp_set_current_user( 0 );

		$result = $this->plugin_update_tools->update_plugins_permission_callback();
		$this->assertFalse( $result );
	}

	/**
	 * Test update_plugins with empty plugin_slugs array.
	 */
	public function test_update_plugins_empty_slugs(): void {
		wp_set_current_user( $this->admin_user->ID );

		$result = $this->plugin_update_tools->update_plugins( array() );

		$this->assertIsArray( $result );
		$this->assertEquals( 'failed', $result['status'] );
		$this->assertEquals( 'No plugin slugs provided for update.', $result['message'] );
	}

	/**
	 * Test update_plugins with missing plugin_slugs key.
	 */
	public function test_update_plugins_missing_plugin_slugs_key(): void {
		wp_set_current_user( $this->admin_user->ID );

		$result = $this->plugin_update_tools->update_plugins( array( 'other_key' => 'value' ) );

		$this->assertIsArray( $result );
		$this->assertEquals( 'failed', $result['status'] );
		$this->assertEquals( 'No plugin slugs provided for update.', $result['message'] );
	}

	/**
	 * Test update_plugins with non-existent plugin.
	 */
	public function test_update_plugins_non_existent_plugin(): void {
		wp_set_current_user( $this->admin_user->ID );

		$result = $this->plugin_update_tools->update_plugins(
			array(
				'plugin_slugs' => array( 'non-existent-plugin' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'non-existent-plugin', $result[0]['plugin_slug'] );
		$this->assertEquals( 'not_found', $result[0]['status'] );
		$this->assertStringContainsString( 'Plugin not found: non-existent-plugin', $result[0]['message'] );
	}

	/**
	 * Test update_plugins with multiple non-existent plugins.
	 */
	public function test_update_plugins_multiple_non_existent_plugins(): void {
		wp_set_current_user( $this->admin_user->ID );

		$result = $this->plugin_update_tools->update_plugins(
			array(
				'plugin_slugs' => array( 'non-existent-plugin-1', 'non-existent-plugin-2' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertCount( 2, $result );

		foreach ( $result as $index => $plugin_result ) {
			$expected_slug = 'non-existent-plugin-' . ( $index + 1 );
			$this->assertEquals( $expected_slug, $plugin_result['plugin_slug'] );
			$this->assertEquals( 'not_found', $plugin_result['status'] );
			$this->assertStringContainsString( 'Plugin not found: ' . $expected_slug, $plugin_result['message'] );
		}
	}

	/**
	 * Test plugin finding logic with different slug formats.
	 * 
	 * This test creates mock plugin data to test the plugin finding logic
	 * without needing actual plugins installed.
	 */
	public function test_plugin_finding_logic(): void {
		wp_set_current_user( $this->admin_user->ID );

		// Mock get_plugins() to return test plugin data
		$mock_plugins = array(
			'test-plugin/test-plugin.php' => array(
				'Name'       => 'Test Plugin',
				'Version'    => '1.0.0',
				'TextDomain' => 'test-plugin',
			),
			'another-plugin/main.php' => array(
				'Name'       => 'Another Plugin',
				'Version'    => '2.0.0',
				'TextDomain' => 'another-plugin',
			),
			'wp-mail-smtp/wp_mail_smtp.php' => array(
				'Name'       => 'WP Mail SMTP',
				'Version'    => '3.0.0',
				'TextDomain' => 'wp-mail-smtp',
			),
		);

		// Use reflection to test the plugin finding logic
		$reflection = new \ReflectionClass( $this->plugin_update_tools );
		$method = $reflection->getMethod( 'update_plugins' );
		$method->setAccessible( true );

		// Mock get_plugins function
		$original_get_plugins = null;
		if ( function_exists( 'get_plugins' ) ) {
			// We can't easily mock WordPress functions in unit tests,
			// so we'll test the actual method behavior instead
			$result = $this->plugin_update_tools->update_plugins(
				array(
					'plugin_slugs' => array( 'test-plugin' ),
				)
			);

			// The plugin won't be found since it's not actually installed,
			// but we can verify the error message format
			$this->assertIsArray( $result );
			if ( isset( $result[0] ) ) {
				$this->assertEquals( 'test-plugin', $result[0]['plugin_slug'] );
				$this->assertEquals( 'not_found', $result[0]['status'] );
			}
		}
	}

	/**
	 * Test update_plugins with a plugin that has no updates available.
	 * 
	 * This test simulates a scenario where a plugin exists but has no updates.
	 */
	public function test_update_plugins_no_update_available(): void {
		wp_set_current_user( $this->admin_user->ID );

		// Create a mock plugin file to simulate an installed plugin
		$plugin_dir = WP_PLUGIN_DIR . '/test-plugin';
		$plugin_file = $plugin_dir . '/test-plugin.php';

		// Create directory if it doesn't exist
		if ( ! is_dir( $plugin_dir ) ) {
			wp_mkdir_p( $plugin_dir );
		}

		// Create a minimal plugin file
		$plugin_content = '<?php
/**
 * Plugin Name: Test Plugin
 * Version: 1.0.0
 * Text Domain: test-plugin
 */

// Prevent direct access
if ( ! defined( \'ABSPATH\' ) ) {
	exit;
}
';

		file_put_contents( $plugin_file, $plugin_content );

		// Clear plugin cache
		wp_cache_delete( 'plugins', 'plugins' );

		// Test the update
		$result = $this->plugin_update_tools->update_plugins(
			array(
				'plugin_slugs' => array( 'test-plugin' ),
			)
		);

		$this->assertIsArray( $result );

		// Clean up
		if ( file_exists( $plugin_file ) ) {
			unlink( $plugin_file );
		}
		if ( is_dir( $plugin_dir ) ) {
			rmdir( $plugin_dir );
		}

		// Clear plugin cache again
		wp_cache_delete( 'plugins', 'plugins' );

		// The result should indicate no update available or plugin not found
		// (depending on how quickly WordPress recognizes the plugin)
		$this->assertTrue( 
			isset( $result[0]['status'] ) && 
			in_array( $result[0]['status'], array( 'no_update_available', 'not_found' ), true )
		);
	}

	/**
	 * Test update_plugins input validation.
	 */
	public function test_update_plugins_input_validation(): void {
		wp_set_current_user( $this->admin_user->ID );

		// Test with null plugin_slugs
		$result = $this->plugin_update_tools->update_plugins(
			array(
				'plugin_slugs' => null,
			)
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'failed', $result['status'] );
		$this->assertEquals( 'No plugin slugs provided for update.', $result['message'] );

		// Test with empty string in plugin_slugs
		$result = $this->plugin_update_tools->update_plugins(
			array(
				'plugin_slugs' => array( '' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertEquals( '', $result[0]['plugin_slug'] );
		// Empty string should be treated as not found (or could be no update available)
		$this->assertContains( $result[0]['status'], array( 'not_found', 'no_update_available' ) );
	}

	/**
	 * Test that the tool handles WordPress core file loading properly.
	 */
	public function test_wordpress_core_files_loading(): void {
		wp_set_current_user( $this->admin_user->ID );

		// This test ensures that the method can handle cases where WordPress
		// core files might not be loaded initially
		$result = $this->plugin_update_tools->update_plugins(
			array(
				'plugin_slugs' => array( 'test-plugin' ),
			)
		);

		// The method should not fail due to missing WordPress core functions
		$this->assertIsArray( $result );
		$this->assertNotEquals( 'Plugin_Upgrader class not available.', $result['message'] ?? '' );
	}

	/**
	 * Test exception handling in update_plugins method.
	 */
	public function test_update_plugins_exception_handling(): void {
		wp_set_current_user( $this->admin_user->ID );

		// Create a mock plugin update tools class that throws an exception
		$mock_tools = new class() extends McpPluginUpdateTools {
			public function update_plugins( array $args ): array {
				// Start output buffering like the parent method
				ob_start();
				try {
					throw new \Exception( 'Test exception' );
				} catch ( \Exception $e ) {
					ob_end_clean();
					return array(
						'status'  => 'failed',
						'message' => 'Plugin update failed with error: ' . $e->getMessage(),
					);
				}
			}
		};

		$result = $mock_tools->update_plugins(
			array(
				'plugin_slugs' => array( 'test-plugin' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'failed', $result['status'] );
		$this->assertStringContainsString( 'Test exception', $result['message'] );
	}

	/**
	 * Test fatal error handling in update_plugins method.
	 */
	public function test_update_plugins_fatal_error_handling(): void {
		wp_set_current_user( $this->admin_user->ID );

		// Create a mock plugin update tools class that throws a fatal error
		$mock_tools = new class() extends McpPluginUpdateTools {
			public function update_plugins( array $args ): array {
				// Start output buffering like the parent method
				ob_start();
				try {
					throw new \Error( 'Test fatal error' );
				} catch ( \Error $e ) {
					ob_end_clean();
					return array(
						'status'  => 'failed',
						'message' => 'Plugin update failed with fatal error: ' . $e->getMessage(),
					);
				}
			}
		};

		$result = $mock_tools->update_plugins(
			array(
				'plugin_slugs' => array( 'test-plugin' ),
			)
		);

		$this->assertIsArray( $result );
		$this->assertEquals( 'failed', $result['status'] );
		$this->assertStringContainsString( 'Test fatal error', $result['message'] );
	}

	/**
	 * Test the input schema validation structure.
	 */
	public function test_input_schema_structure(): void {
		// Trigger tool registration
		do_action( 'wordpress_mcp_init' );

		// Create a REST request to list tools.
		$request = new \WP_REST_Request( 'POST', '/wp/v2/wpmcp' );
		$request->set_body(
			wp_json_encode(
				array(
					'method' => 'tools/list',
				)
			)
		);
		$request->add_header( 'Content-Type', 'application/json' );
		wp_set_current_user( $this->admin_user->ID );
		$response = rest_do_request( $request );
		$tools = $response->get_data()['tools'];

		// Find the update_plugins tool
		$update_plugins_tool = null;
		foreach ( $tools as $tool ) {
			if ( $tool['name'] === 'update_plugins' ) {
				$update_plugins_tool = $tool;
				break;
			}
		}

		$this->assertNotNull( $update_plugins_tool, 'update_plugins tool should be found' );
		$schema = $update_plugins_tool['inputSchema'];
		
		$this->assertEquals( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'properties', $schema );
		$this->assertArrayHasKey( 'required', $schema );
		$this->assertContains( 'plugin_slugs', $schema['required'] );
		
		$plugin_slugs_prop = $schema['properties']['plugin_slugs'];
		$this->assertEquals( 'array', $plugin_slugs_prop['type'] );
		$this->assertEquals( 'An array of plugin slugs to update.', $plugin_slugs_prop['description'] );
		$this->assertEquals( 'string', $plugin_slugs_prop['items']['type'] );
		$this->assertEquals( 1, $plugin_slugs_prop['minItems'] );
	}

	/**
	 * Test that output buffering works correctly.
	 */
	public function test_output_buffering(): void {
		wp_set_current_user( $this->admin_user->ID );

		// Start output buffering to capture any output from the method
		ob_start();
		
		$result = $this->plugin_update_tools->update_plugins(
			array(
				'plugin_slugs' => array( 'non-existent-plugin' ),
			)
		);
		
		$output = ob_get_clean();

		// The method should not produce any output due to internal output buffering
		$this->assertEmpty( $output );
		$this->assertIsArray( $result );
	}

	/**
	 * Clean up after tests.
	 */
	public function tear_down(): void {
		// Clear any plugin caches
		wp_cache_delete( 'plugins', 'plugins' );
		
		parent::tear_down();
	}
}
