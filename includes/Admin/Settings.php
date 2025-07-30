<?php //phpcs:ignore
declare(strict_types=1);

namespace Automattic\WordpressMcp\Admin;

use Automattic\WordpressMcp\Core\WpMcp;

/**
 * Class Settings
 * Handles the MCP settings page in WordPress admin.
 */
class Settings {
	/**
	 * The option name in the WordPress options table.
	 */
	const OPTION_NAME = 'wordpress_mcp_settings';

	/**
	 * The tool states option name.
	 */
	const TOOL_STATES_OPTION = 'wordpress_mcp_tool_states';

	/**
	 * Initialize the settings page.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_wordpress_mcp_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_wordpress_mcp_toggle_tool', array( $this, 'ajax_toggle_tool' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WORDPRESS_MCP_PATH . 'wordpress-mcp.php' ), array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Add the settings page to the WordPress admin menu.
	 */
	public function add_settings_page(): void {
		add_options_page(
			__( 'MCP', 'wordpress-mcp' ),
			__( 'MCP', 'wordpress-mcp' ),
			'manage_options',
			'wordpress-mcp-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register the settings and their sanitization callbacks.
	 */
	public function register_settings(): void {
		register_setting(
			'wordpress_mcp_settings',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);
	}

	/**
	 * Checks if WordPress Feature API is available.
	 *
	 * @return bool True if WP Feature API is available, false otherwise.
	 */
	private function is_feature_api_available(): bool {
		return defined( 'WP_FEATURE_API_VERSION' );
	}

	/**
	 * Enqueue scripts and styles for the React app.
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_scripts( string $hook ): void {
		if ( 'settings_page_wordpress-mcp-settings' !== $hook ) {
			return;
		}

		$asset_file = include WORDPRESS_MCP_PATH . 'build/index.asset.php';

		// Enqueue our React app.
		wp_enqueue_script(
			'wordpress-mcp-settings',
			WORDPRESS_MCP_URL . 'build/index.js',
			$asset_file['dependencies'],
			$asset_file['version'],
			true
		);

		// Enqueue the WordPress components styles CSS.
		wp_enqueue_style(
			'wp-components',
			includes_url( 'css/dist/components/style.css' ),
			array(),
			$asset_file['version'],
		);

		// Enqueue the WordPress MCP settings CSS.
		wp_enqueue_style(
			'wp-mcp-settings',
			WORDPRESS_MCP_URL . 'build/style-index.css',
			array(),
			$asset_file['version'],
		);

		// Localize the script with data needed by the React app.
		wp_localize_script(
			'wordpress-mcp-settings',
			'wordpressMcpSettings',
			array(
				'apiUrl'              => rest_url( 'wordpress-mcp/v1/settings' ),
				'nonce'               => wp_create_nonce( 'wordpress_mcp_settings' ),
				'settings'            => get_option( self::OPTION_NAME, array() ),
				'toolStates'          => get_option( self::TOOL_STATES_OPTION, array() ),
				'featureApiAvailable' => $this->is_feature_api_available(),
				'pluginUrl'           => WORDPRESS_MCP_URL,
				'siteUrl'             => home_url(),
				'strings'             => array(
					'enableMcp'                         => __( 'Enable MCP functionality', 'wordpress-mcp' ),
					'enableMcpDescription'              => __( 'Toggle to enable or disable the MCP plugin functionality.', 'wordpress-mcp' ),
					'enableFeaturesAdapter'             => __( 'Enable WordPress Features Adapter', 'wordpress-mcp' ),
					'enableFeaturesAdapterDescription'  => __( 'Enable or disable the WordPress Features Adapter. This option only works when MCP is enabled.', 'wordpress-mcp' ),
					'enableCreateTools'                 => __( 'Enable Create Tools', 'wordpress-mcp' ),
					'enableCreateToolsDescription'      => __( 'Allow create operations via tools.', 'wordpress-mcp' ),
					'enableUpdateTools'                 => __( 'Enable Update Tools', 'wordpress-mcp' ),
					'enableUpdateToolsDescription'      => __( 'Allow update operations via tools.', 'wordpress-mcp' ),
					'enableDeleteTools'                 => __( 'Enable Delete Tools', 'wordpress-mcp' ),
					'enableDeleteToolsDescription'      => __( '⚠️ CAUTION: Allow deletion operations via tools.', 'wordpress-mcp' ),
					'enableRestApiCrudTools'            => __( '🧪 Enable REST API CRUD Tools (EXPERIMENTAL)', 'wordpress-mcp' ),
					'enableRestApiCrudToolsDescription' => __( '⚠️ EXPERIMENTAL FEATURE: Enable or disable the generic REST API CRUD tools for accessing WordPress endpoints. This is experimental functionality that may change or be removed in future versions. When enabled, all tools that are a rest_alias or have the disabled_by_rest_crud flag will be disabled.', 'wordpress-mcp' ),
					'saveSettings'                      => __( 'Save Settings', 'wordpress-mcp' ),
					'settingsSaved'                     => __( 'Settings saved successfully!', 'wordpress-mcp' ),
					'settingsError'                     => __( 'Error saving settings. Please try again.', 'wordpress-mcp' ),
					// translators: %1$s: tool name, %2$s: action (enabled/disabled).
					'toolEnabled'                       => __( 'Tool %1$s has been %2$s.', 'wordpress-mcp' ),
					// translators: %1$s: tool name, %2$s: action (enabled/disabled).
					'toolDisabled'                      => __( 'Tool %1$s has been %2$s.', 'wordpress-mcp' ),
				),
			)
		);
	}

	/**
	 * AJAX handler for saving settings.
	 */
	public function ajax_save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wordpress-mcp' ) ) );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wordpress_mcp_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce. Please refresh the page and try again.', 'wordpress-mcp' ) ) );
		}

		// Sanitize the settings input.
		$settings_raw = isset( $_POST['settings'] ) ? sanitize_text_field( wp_unslash( $_POST['settings'] ) ) : '{}';
		$settings     = $this->sanitize_settings( json_decode( $settings_raw, true ) );
		update_option( self::OPTION_NAME, $settings );

		wp_send_json_success( array( 'message' => __( 'Settings saved successfully!', 'wordpress-mcp' ) ) );
	}

	/**
	 * Sanitize the settings before saving.
	 *
	 * @param array $input The input array.
	 * @return array The sanitized input array.
	 */
	public function sanitize_settings( array $input ): array {
		$sanitized = array();

		if ( isset( $input['enabled'] ) ) {
			$sanitized['enabled'] = (bool) $input['enabled'];
		} else {
			$sanitized['enabled'] = false;
		}

		if ( isset( $input['features_adapter_enabled'] ) ) {
			$sanitized['features_adapter_enabled'] = (bool) $input['features_adapter_enabled'];
		} else {
			$sanitized['features_adapter_enabled'] = false;
		}

		if ( isset( $input['enable_create_tools'] ) ) {
			$sanitized['enable_create_tools'] = (bool) $input['enable_create_tools'];
		} else {
			$sanitized['enable_create_tools'] = false;
		}

		if ( isset( $input['enable_update_tools'] ) ) {
			$sanitized['enable_update_tools'] = (bool) $input['enable_update_tools'];
		} else {
			$sanitized['enable_update_tools'] = false;
		}

		if ( isset( $input['enable_delete_tools'] ) ) {
			$sanitized['enable_delete_tools'] = (bool) $input['enable_delete_tools'];
		} else {
			$sanitized['enable_delete_tools'] = false;
		}

		if ( isset( $input['enable_rest_api_crud_tools'] ) ) {
			$sanitized['enable_rest_api_crud_tools'] = (bool) $input['enable_rest_api_crud_tools'];
		} else {
			$sanitized['enable_rest_api_crud_tools'] = false;
		}

		return $sanitized;
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div id="wordpress-mcp-settings-app"></div>
		</div>
		<?php
	}

	/**
	 * Add settings link to plugin actions.
	 *
	 * @param array $actions An array of plugin action links.
	 * @return array
	 */
	public function plugin_action_links( array $actions ): array {
		$settings_link = '<a href="' . admin_url( 'options-general.php?page=wordpress-mcp-settings' ) . '">' . __( 'Settings', 'wordpress-mcp' ) . '</a>';
		array_unshift( $actions, $settings_link );
		return $actions;
	}

	/**
	 * AJAX handler for toggling tool state.
	 */
	public function ajax_toggle_tool(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wordpress-mcp' ) ) );
		}

		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wordpress_mcp_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid nonce. Please refresh the page and try again.', 'wordpress-mcp' ) ) );
		}

		$tool_name = isset( $_POST['tool'] ) ? sanitize_text_field( wp_unslash( $_POST['tool'] ) ) : '';
		$enabled   = isset( $_POST['tool_enabled'] ) ? filter_var( wp_unslash( $_POST['tool_enabled'] ), FILTER_VALIDATE_BOOLEAN ) : false;

		if ( empty( $tool_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Tool name is required.', 'wordpress-mcp' ) ) );
		}

		$success = $this->toggle_tool( $tool_name, $enabled );

		if ( ! $success ) {
			wp_send_json_error( array( 'message' => __( 'Failed to toggle tool state.', 'wordpress-mcp' ) ) );
		}

		wp_send_json_success(
			array(
				'message' => sprintf(
					// translators: %1$s: tool name, %2$s: action (enabled/disabled).
					__( 'Tool %1$s has been %2$s.', 'wordpress-mcp' ),
					$tool_name,
					$enabled ? __( 'enabled', 'wordpress-mcp' ) : __( 'disabled', 'wordpress-mcp' )
				),
			)
		);
	}

	/**
	 * Toggle a tool's state.
	 *
	 * @param string $tool_name The name of the tool to toggle.
	 * @param bool   $enabled   Whether the tool should be enabled.
	 * @return bool Whether the operation was successful.
	 */
	public function toggle_tool( string $tool_name, bool $enabled ): bool {
		$tool_states               = get_option( self::TOOL_STATES_OPTION, array() );
		$tool_states[ $tool_name ] = $enabled;
		try {
			update_option( self::TOOL_STATES_OPTION, $tool_states, 'no' );
		} catch ( \Exception $e ) {
			error_log( 'Failed to update tool states option: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return false;
		}
		return true;
	}
}
