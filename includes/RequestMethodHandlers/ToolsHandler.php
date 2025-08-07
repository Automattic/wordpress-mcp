<?php //phpcs:ignore
/**
 * Tools method handlers for MCP requests.
 *
 * @package WordPressMcp
 */

namespace Automattic\WordpressMcp\RequestMethodHandlers;

use Automattic\WordpressMcp\Core\WpMcp;
use Automattic\WordpressMcp\Core\McpErrorHandler;
use Automattic\WordpressMcp\Utils\HandleToolsCall;

/**
 * Handles tools-related MCP methods.
 */
class ToolsHandler {
	/**
	 * The WordPress MCP instance.
	 *
	 * @var WpMcp
	 */
	private WpMcp $mcp;

	/**
	 * Constructor.
	 *
	 * @param WpMcp $mcp The WordPress MCP instance.
	 */
	public function __construct( WpMcp $mcp ) {
		$this->mcp = $mcp;
	}

	/**
	 * Handle the tools/list request.
	 *
	 * @return array
	 */
	public function list_tools(): array {
		return array(
			'tools' => array_values( $this->mcp->get_tools() ),
		);
	}

	/**
	 * Handle the tools/list/all request.
	 *
	 * @return array
	 */
	public function list_all_tools(): array {
		// Return all tools with additional details.
		$tools = $this->mcp->get_all_tools();

		// Add any additional metadata or details.
		foreach ( $tools as &$tool ) {
			$tool['available'] = true;
		}

		return array(
			'tools' => array_values( $tools ),
		);
	}

	/**
	 * Handle the tools/call request.
	 *
	 * @param array $message Request message.
	 * @return array
	 */
	public function call_tool( array $message ): array {
		// Handle both direct params and nested params structure.
		$request_params = $message['params'] ?? $message;

		if ( ! isset( $request_params['name'] ) ) {
			return array(
				'error' => McpErrorHandler::missing_parameter( 0, 'name' )['error'],
			);
		}

		// Clean parameters arguments.
		if ( ! empty( $request_params['arguments'] ) ) {
			foreach ( $request_params['arguments'] as $key => $value ) {
				if ( empty( $value ) || 'null' === $value ) {
					unset( $request_params['arguments'][ $key ] );
				}
			}
		}

		try {
			// Implement a tool calling logic here.
			$result = HandleToolsCall::run( $request_params );

			// Check if the result contains an error.
			if ( isset( $result['error'] ) ) {
				return $result; // Return error directly.
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

			return $response;
		} catch ( \Throwable $exception ) {
			McpErrorHandler::log_error(
				'Error calling tool',
				array(
					'tool'      => $request_params['name'],
					'exception' => $exception->getMessage(),
				)
			);
			return array(
				'error' => McpErrorHandler::internal_error( 0, 'Failed to execute tool' )['error'],
			);
		}
	}
}
