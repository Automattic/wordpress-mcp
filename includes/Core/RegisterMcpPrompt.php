<?php //phpcs:ignore
declare(strict_types=1);

namespace Automattic\WordpressMcp\Core;

use InvalidArgumentException;

/**
 * Register an MCP prompt.
 */
class RegisterMcpPrompt {

	/**
	 * The prompt instance.
	 *
	 * @var array
	 */
	private array $prompt;

	/**
	 * The messages instance.
	 *
	 * @var array
	 */
	private array $messages;

	/**
	 * Constructor.
	 *
	 * @param array $prompt The prompt instance.
	 * @param array $messages The messages instance.
	 * @throws InvalidArgumentException|\RuntimeException When the prompt is invalid or registered outside of wordpress_mcp_init action.
	 */
	public function __construct( array $prompt, array $messages ) {
		if ( ! doing_action( 'wordpress_mcp_init' ) ) {
			throw new \RuntimeException( 'RegisterMcpPrompt can only be used within the wordpress_mcp_init action.' );
		}

		$this->prompt   = $prompt;
		$this->messages = $messages;
		$this->validate_prompt();
		$this->register_prompt();
	}

	/**
	 * Register the prompt.
	 *
	 * @return void
	 */
	private function register_prompt(): void {
		WPMCP()->register_prompt( $this->prompt, $this->messages );
	}

	/**
	 * Validate the prompt.
	 *
	 * @return void
	 * @throws InvalidArgumentException When the prompt is invalid.
	 */
	private function validate_prompt(): void {
		// Validate the prompt name.
		if ( empty( $this->prompt['name'] ) || ! is_string( $this->prompt['name'] ) ) {
			throw new InvalidArgumentException( 'The prompt name must be a non-empty string.' );
		}

		// Validate the prompt description.
		if ( empty( $this->prompt['description'] ) || ! is_string( $this->prompt['description'] ) ) {
			throw new InvalidArgumentException( 'The prompt description must be a non-empty string.' );
		}

		if ( isset( $this->prompt['arguments'] ) ) {
			if ( ! is_array( $this->prompt['arguments'] ) ) {
				throw new InvalidArgumentException( 'The prompt arguments must be an array.' );
			}

			// Validate each argument.
			foreach ( $this->prompt['arguments'] as $argument ) {
				if ( empty( $argument['name'] ) || ! is_string( $argument['name'] ) ) {
					throw new InvalidArgumentException( 'Each argument must have a non-empty name.' );
				}

				if ( isset( $argument['description'] ) && ! is_string( $argument['description'] ) ) {
					throw new InvalidArgumentException( 'The argument description must be a string.' );
				}

				if ( isset( $argument['required'] ) && ! is_bool( $argument['required'] ) ) {
					throw new InvalidArgumentException( 'The argument required flag must be a boolean.' );
				}
			}
		}

		// Validate each message.
		foreach ( $this->messages as $message ) {
			if ( ! is_array( $message ) ) {
				throw new InvalidArgumentException( 'Each message must be an array.' );
			}
			// Validate the message structure.
			if ( ! isset( $message['role'] ) || ! isset( $message['content'] ) ) {
				throw new InvalidArgumentException( 'Each message must have a role and content.' );
			}
			// Validate the content.
			if ( ! isset( $message['content']['type'] ) || ! isset( $message['content']['text'] ) ) {
				throw new InvalidArgumentException( 'Each message must have a type and text.' );
			}
		}
	}
}
