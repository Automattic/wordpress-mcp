<?php //phpcs:ignore
declare(strict_types=1);

namespace Automattic\WordpressMcp\Core;

use Automattic\WordpressMcp\Tools\McpCustomPostTypesTools;
use Automattic\WordpressMcp\Tools\McpPostsTools;
use Automattic\WordpressMcp\Resources\McpGeneralSiteInfo;
use Automattic\WordpressMcp\Tools\McpSiteInfo;
use Automattic\WordpressMcp\Tools\McpUsersTools;
use Automattic\WordpressMcp\Tools\McpWooOrders;
use Automattic\WordpressMcp\Tools\McpWooProducts;
use Automattic\WordpressMcp\Tools\McpPagesTools;
use Automattic\WordpressMcp\Tools\McpSettingsTools;
use Automattic\WordpressMcp\Tools\McpMediaTools;
use Automattic\WordpressMcp\Prompts\McpGetSiteInfo as McpGetSiteInfoPrompt;
use Automattic\WordpressMcp\Prompts\McpAnalyzeSales;
use Automattic\WordpressMcp\Resources\McpPluginInfoResource;
use Automattic\WordpressMcp\Resources\McpThemeInfoResource;
use Automattic\WordpressMcp\Resources\McpUserInfoResource;
use Automattic\WordpressMcp\Resources\McpSiteSettingsResource;
use InvalidArgumentException;

/**
 * WordPress MCP
 *
 * @package WpMcp
 */
class WpMcp {

	/**
	 * The tools.
	 *
	 * @var array
	 */
	private array $tools = array();

	/**
	 * The tool callbacks.
	 *
	 * @var array
	 */
	private array $tools_callbacks = array();

	/**
	 * The resources.
	 *
	 * @var array
	 */
	private array $resources = array();

	/**
	 * The resource callbacks.
	 *
	 * @var array
	 */
	private array $resource_callbacks = array();

	/**
	 * The prompts.
	 *
	 * @var array
	 */
	private array $prompts = array();

	/**
	 * The prompt message.
	 *
	 * @var array
	 */
	private array $prompts_messages = array();

	/**
	 * The namespace.
	 *
	 * @var string
	 */
	private string $namespace = 'wpmcp/v1';


	/**
	 * The instance.
	 *
	 * @var ?WpMcp
	 */
	private static ?WpMcp $instance = null;

	/**
	 * The initialized flag.
	 *
	 * @var bool
	 */
	private static bool $initialized = false;

	/**
	 * The MCP settings.
	 *
	 * @var array
	 */
	private array $mcp_settings = array();

	/**
	 * The has triggered init flag.
	 *
	 * @var bool
	 */
	private bool $has_triggered_init = false;

	/**
	 * Constructor.
	 */
	public function __construct() {

		// Only initialize if not already initialized.
		if ( ! self::$initialized ) {
			$this->mcp_settings = get_option( 'wordpress_mcp_settings', array() );

			// Only initialize components if MCP is enabled.
			if ( $this->is_mcp_enabled() ) {
				$this->init_default_resources();
				$this->init_default_tools();
				$this->init_default_prompts();
				$this->init_features_as_tools();
				// Register the MCP assets late in the rest_api_init hook (required for rest_alias tools).
				// This is to ensure that the rest_api_init hook is not called too early.
				// We use a priority of 20000 to ensure that the rest_api_init hook is called after the rest_api_init hook of the FeaturesAPI plugin.
				add_action( 'rest_api_init', array( $this, 'wordpress_mcp_init' ), 20000 );

				self::$initialized = true;
			}
		}
	}

	/**
	 * Initialize the plugin.
	 */
	public function wordpress_mcp_init(): void {
		// Only trigger the wordpress_mcp_init action if MCP is enabled and hasn't been triggered before.
		if ( $this->is_mcp_enabled() && ! $this->has_triggered_init ) {
			do_action( 'wordpress_mcp_init', $this );
			$this->has_triggered_init = true;
		}
	}

	/**
	 * Check if MCP is enabled in settings.
	 *
	 * @return bool Whether MCP is enabled.
	 */
	private function is_mcp_enabled(): bool {
		return isset( $this->mcp_settings['enabled'] ) && $this->mcp_settings['enabled'];
	}

	/**
	 * Initialize the default resources.
	 */
	private function init_default_resources(): void {
		new McpGeneralSiteInfo();
		new McpPluginInfoResource();
		new McpThemeInfoResource();
		new McpUserInfoResource();
		new McpSiteSettingsResource();
	}

	/**
	 * Initialize the default tools.
	 */
	private function init_default_tools(): void {
		new McpPostsTools();
		new McpSiteInfo();
		new McpUsersTools();
		new McpWooProducts();
		new McpWooOrders();
		new McpPagesTools();
		new McpSettingsTools();
		new McpMediaTools();
		new McpCustomPostTypesTools();
	}

	/**
	 * Initialize the default prompts.
	 */
	private function init_default_prompts(): void {
		new McpGetSiteInfoPrompt();
		new McpAnalyzeSales();
	}

	/**
	 * Initialize the features as tools.
	 */
	private function init_features_as_tools(): void {
		$features_enabled = isset( $this->mcp_settings['features_adapter_enabled'] ) && $this->mcp_settings['features_adapter_enabled'];

		if ( $features_enabled ) {
			new WpFeaturesAdapter();
		}
	}

	/**
	 * Get the instance.
	 *
	 * @return WpMcp
	 */
	public static function instance(): WpMcp {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Check if a tool type is enabled.
	 *
	 * @param string $type The tool type to check.
	 * @return bool Whether the tool type is enabled.
	 */
        private function is_tool_type_enabled( string $type ): bool {

		// Read operations are always allowed if MCP is enabled.
		if ( 'read' === $type ) {
			return true;
		}

		// Check specific tool type settings.
		$type_settings_map = array(
			'create' => 'enable_create_tools',
			'update' => 'enable_update_tools',
			'delete' => 'enable_delete_tools',
		);

		// Check if the type exists in our mapping and is enabled.
		if ( isset( $type_settings_map[ $type ] ) ) {
			return isset( $this->mcp_settings[ $type_settings_map[ $type ] ] ) && $this->mcp_settings[ $type_settings_map[ $type ] ];
		}

                return false;
        }

        /**
         * Check if a specific tool is enabled via settings.
         *
         * @param string $name The tool name.
         * @return bool Whether the tool is enabled.
         */
        public function is_tool_enabled( string $name ): bool {
                if ( ! isset( $this->mcp_settings['enabled_tools'] ) || ! is_array( $this->mcp_settings['enabled_tools'] ) ) {
                        return true;
                }

                if ( empty( $this->mcp_settings['enabled_tools'] ) ) {
                        return false;
                }

                return in_array( $name, $this->mcp_settings['enabled_tools'], true );
        }

        /**
         * Get only tools enabled via settings.
         *
         * @return array
         */
        public function get_enabled_tools(): array {
                if ( ! isset( $this->mcp_settings['enabled_tools'] ) || ! is_array( $this->mcp_settings['enabled_tools'] ) ) {
                        return $this->tools;
                }

                if ( empty( $this->mcp_settings['enabled_tools'] ) ) {
                        return array();
                }

                return array_values( array_filter( $this->tools, function ( $tool ) {
                        return in_array( $tool['name'], $this->mcp_settings['enabled_tools'], true );
                } ) );
        }

	/**
	 * Register a tool.
	 *
	 * @param array $args The arguments.
	 * @throws InvalidArgumentException If the tool name is not unique or if the tool type is disabled.
	 */
	public function register_tool( array $args ): void {
		// Check if the tool type is enabled.
		if ( ! $this->is_tool_type_enabled( $args['type'] ) ) {
			return; // Skip registration if tool type is disabled.
		}

		// The name should be unique.
		if ( in_array( $args['name'], array_column( $this->tools, 'name' ), true ) ) {
			$this->tools_callbacks[ $args['name'] ] = array();

			// Search the tools array for the tool with the same name.
			foreach ( $this->tools as $tool ) {
				if ( $tool['name'] === $args['name'] ) {
					unset( $this->tools[ $tool['name'] ] );
					break;
				}
			}
		}

		$this->tools_callbacks[ $args['name'] ] = array(
			'callback'            => $args['callback'],
			'permission_callback' => $args['permission_callback'],
			'rest_alias'          => $args['rest_alias'] ?? null,
		);

		unset( $args['callback'] );
		unset( $args['permission_callback'] );
		unset( $args['rest_alias'] );
		$this->tools[] = $args;
	}

	/**
	 * Register a resource.
	 *
	 * @param array $args The arguments.
	 * @throws InvalidArgumentException If the resource name or URI is not unique.
	 */
	public function register_resource( array $args ): void {
		// the name and uri should be unique.
		if ( in_array( $args['name'], array_column( $this->resources, 'name' ), true ) || in_array( $args['uri'], array_column( $this->resources, 'uri' ), true ) ) {
			$this->resources[ $args['uri'] ] = array();
		}
		$this->resources[ $args['uri'] ] = $args;
	}

	/**
	 * Register a resource callback.
	 *
	 * @param string   $uri The uri.
	 * @param callable $callback The callback.
	 */
	public function register_resource_callback( string $uri, callable $callback ): void {
		$this->resource_callbacks[ $uri ] = $callback;
	}

	/**
	 * Register a prompt.
	 *
	 * @param array $prompt    The prompt instance.
	 * @param array $messages  The messages for the prompt.
	 * @throws InvalidArgumentException If the prompt name is not unique.
	 */
	public function register_prompt( array $prompt, array $messages ): void {
		$name = $prompt['name'];

		// Check if the prompt name is unique.
		if ( isset( $this->prompts[ $name ] ) ) {
			$this->prompts[ $name ]          = array();
			$this->prompts_messages[ $name ] = array();
		}

		$this->prompts[ $name ]          = $prompt;
		$this->prompts_messages[ $name ] = $messages;
	}

	/**
	 * Get the tools.
	 *
	 * @return array
	 */
	public function get_tools(): array {
		return $this->tools;
	}

	/**
	 * Get the tool callbacks.
	 *
	 * @return array
	 */
	public function get_tools_callbacks(): array {
		return $this->tools_callbacks;
	}

	/**
	 * Get the resources.
	 *
	 * @return array
	 */
	public function get_resources(): array {
		return $this->resources;
	}

	/**
	 * Get the resource callbacks.
	 *
	 * @return array
	 */
	public function get_resource_callbacks(): array {
		return $this->resource_callbacks;
	}

	/**
	 * Get the prompts.
	 *
	 * @return array
	 */
	public function get_prompts(): array {
		return $this->prompts;
	}

	/**
	 * Get a prompt by name.
	 *
	 * @param string $name The prompt name.
	 * @return array|null
	 */
	public function get_prompt_by_name( string $name ): ?array {
		return $this->prompts[ $name ] ?? null;
	}

	/**
	 * Get the prompt messages.
	 *
	 * @param string $name The prompt name.
	 * @return array|null
	 */
	public function get_prompt_messages( string $name ): ?array {
		return $this->prompts_messages[ $name ] ?? null;
	}

	/**
	 * Get the namespace.
	 *
	 * @return string
	 */
	public function get_namespace(): string {
		return $this->namespace;
	}

	/**
	 * Get a tool by name.
	 *
	 * @param string $name The tool name.
	 * @return array|null
	 */
	public function get_tool_by_name( string $name ): ?array {
		foreach ( $this->tools as $tool ) {
			if ( $tool['name'] === $name ) {
				return $tool;
			}
		}
		return null;
	}

	/**
	 * Get the MCP settings.
	 *
	 * @return array
	 */
	public function get_mcp_settings(): array {
		return $this->mcp_settings;
	}
}
