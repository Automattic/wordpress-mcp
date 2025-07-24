<?php //phpcs:ignore
/**
 * System method handlers for MCP requests.
 *
 * @package WordPressMcp
 */

namespace Automattic\WordpressMcp\RequestMethodHandlers;

use Automattic\WordpressMcp\Core\McpErrorHandler;

/**
 * Handles system-related MCP methods.
 */
class SystemHandler {
	/**
	 * Handle the ping request.
	 *
	 * @return array
	 */
	public function ping(): array {
		// According to MCP specification, ping returns an empty result.
		return array();
	}

	/**
	 * Handle the logging/setLevel request.
	 *
	 * @param array $params Request parameters.
	 * @return array
	 */
	public function set_logging_level( array $params ): array {
		if ( ! isset( $params['params']['level'] ) && ! isset( $params['level'] ) ) {
			return array(
				'error' => McpErrorHandler::missing_parameter( 0, 'level' )['error'],
			);
		}

		// @todo: Implement logging level setting logic here.

		return array(
			'success' => true,
		);
	}

	/**
	 * Handle the completion/complete request.
	 *
	 * @return array
	 */
	public function complete(): array {
		// Implement completion logic here.

		return array(
			'success' => true,
		);
	}

	/**
	 * Handle the roots/list request.
	 *
	 * @return array
	 */
	public function list_roots(): array {
		// Implement roots listing logic here.
		$roots = array();

		return array(
			'roots' => $roots,
		);
	}

	/**
	 * Handle method not found errors.
	 *
	 * @param array $params Request parameters.
	 * @return array
	 */
	public function method_not_found( array $params ): array {
		$method = $params['method'] ?? 'unknown';
		return array(
			'error' => McpErrorHandler::method_not_found( 0, $method )['error'],
		);
	}
}
