<?php //phpcs:ignore
declare(strict_types=1);


namespace Automattic\WordpressMcp\Core;

use InvalidArgumentException;

/**
 * Register MCP Resource
 *
 * @package WpMcp
 */
class RegisterMcpResource {

	/**
	 * The arguments.
	 *
	 * @var array
	 */
	private array $args;

	/**
	 * The resource content callback.
	 *
	 * @var callable
	 */
	private $resource_content_callback;

	/**
	 * The accepted mime types.
	 *
	 * @var array
	 */
	private array $accepted_mime_types = array( 'application/json', 'text/plain' );

	/**
	 * Constructor.
	 *
	 * @param array    $args The arguments.
	 * @param callable $resource_content_callback The resource content callback.
	 * @throws InvalidArgumentException|\RuntimeException When validation fails or resource is registered outside of wordpress_mcp_init action.
	 */
	public function __construct( array $args, callable $resource_content_callback ) {
		if ( ! doing_action( 'wordpress_mcp_init' ) ) {
			throw new \RuntimeException( 'RegisterMcpResource can only be used within the wordpress_mcp_init action.' );
		}

		$this->args                      = $args;
		$this->resource_content_callback = $resource_content_callback;
		$this->validate_arguments();
		$this->validate_resource_content_callback();
		$this->register_resource();
	}

	/**
	 * Register the resource.
	 *
	 * @return void
	 */
	private function register_resource(): void {
		WPMCP()->register_resource( $this->args );
		WPMCP()->register_resource_callback( $this->args['uri'], $this->resource_content_callback );
	}

	/**
	 * Validate the arguments.
	 *
	 * @throws InvalidArgumentException When validation fails.
	 * @return void
	 */
	private function validate_arguments(): void {
		if ( ! isset( $this->args['uri'] ) || empty( $this->args['uri'] ) ) {
			throw new InvalidArgumentException( 'Resource URI is required' );
		}

		if ( ! isset( $this->args['name'] ) || empty( $this->args['name'] ) ) {
			throw new InvalidArgumentException( 'Resource name is required' );
		}

		// Validate URI format.
		if ( ! filter_var( $this->args['uri'], FILTER_VALIDATE_URL ) && ! preg_match( '/^WordPress:\/\//', $this->args['uri'] ) ) {
			throw new InvalidArgumentException( 'Invalid resource URI format. Must follow WordPress://[host]/[path] format' );
		}

		// Validate the MIME type if provided.
		if ( isset( $this->args['mimeType'] ) && ! empty( $this->args['mimeType'] ) ) {
			if ( ! in_array( $this->args['mimeType'], $this->accepted_mime_types, true ) ) {
				throw new InvalidArgumentException( 'Invalid MIME type format. Accepted mime types are: ' . esc_html( implode( ', ', $this->accepted_mime_types ) ) );
			}
		}

		// Ensure no trailing whitespace in strings.
		foreach ( array( 'uri', 'name', 'description', 'mimeType' ) as $field ) {
			if ( isset( $this->args[ $field ] ) ) {
				$this->args[ $field ] = trim( $this->args[ $field ] );
			}
		}
	}

	/**
	 * Validate the resource content callback.
	 *
	 * @throws InvalidArgumentException When validation fails.
	 * @return void
	 */
	private function validate_resource_content_callback(): void {
		if ( ! is_callable( $this->resource_content_callback ) ) {
			throw new InvalidArgumentException( 'Resource content callback must be a callable' );
		}
	}
}
