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
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'wordpress_mcp_init' ), PHP_INT_MAX );

		// Only initialize components if MCP is enabled.
		if ( $this->is_mcp_enabled() ) {
			$this->init_default_resources();
			$this->init_default_tools();
			$this->init_default_prompts();
			$this->init_features_as_tools();
		}
	}

	/**
	 * Initialize the plugin.
	 */
	public function wordpress_mcp_init(): void {
		// Only trigger the init action if MCP is enabled.
		if ( $this->is_mcp_enabled() ) {
			do_action( 'wordpress_mcp_init', $this );
		}
	}

	/**
	 * Check if MCP is enabled in settings.
	 *
	 * @return bool Whether MCP is enabled.
	 */
	private function is_mcp_enabled(): bool {
		$options = get_option( 'wordpress_mcp_settings', array() );
		return isset( $options['enabled'] ) && $options['enabled'];
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
		$options          = get_option( 'wordpress_mcp_settings', array() );
		$features_enabled = isset( $options['features_adapter_enabled'] ) && $options['features_adapter_enabled'];

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
		$options = get_option( 'wordpress_mcp_settings', array() );

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
			return isset( $options[ $type_settings_map[ $type ] ] ) && $options[ $type_settings_map[ $type ] ];
		}

		return false;
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
			throw new InvalidArgumentException( 'The tool name must be unique. A tool with this name already exists: ' . esc_html( $args['name'] ) );
		}

		$this->tools_callbacks[ $args['name'] ] = array(
			'callback'             => $args['callback'] ?? null,
			'permissions_callback' => $args['permissions_callback'] ?? null,
			'rest_alias'           => $args['rest_alias'] ?? null,
		);

		unset( $args['callback'] );
		unset( $args['permissions_callback'] );
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
			throw new InvalidArgumentException( 'The resource name and uri must be unique. A resource with this name or uri already exists: ' . esc_html( $args['name'] ) . ' ' . esc_html( $args['uri'] ) );
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
			throw new InvalidArgumentException( 'The prompt name must be unique. A prompt with this name already exists: ' . esc_html( $name ) );
		}

		$this->prompts[ $name ] = $prompt;

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
}
