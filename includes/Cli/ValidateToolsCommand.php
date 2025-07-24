<?php //phpcs:ignore
declare(strict_types=1);
// Ensure WP_CLI is available before executing WP-CLI-specific logic.

namespace Automattic\WordpressMcp\Cli;

use Automattic\WordpressMcp\Core\WpMcp;
use Automattic\WordpressMcp\Utils\ValidateTool;

/**
 * WP-CLI command for validating MCP tools.
 */
class ValidateToolsCommand {

	/**
	 * Validates all registered MCP tools or a specific tool by name.
	 *
	 * ## OPTIONS
	 *
	 * [<tool_name>]
	 * : The name of a specific tool to validate. If not provided, all tools will be validated.
	 *
	 * [--level=<level>]
	 * : Validation level to use. One of: strict, extended, permissive.
	 * ---
	 * default: extended
	 * options:
	 *   - strict
	 *   - extended
	 *   - permissive
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format. One of: table, json, yaml, csv.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 *   - csv
	 * ---
	 *
	 * [--errors-only]
	 * : Show only tools with validation errors.
	 *
	 * [--warnings-only]
	 * : Show only tools with validation warnings.
	 *
	 * [--detailed]
	 * : Show detailed validation messages for each tool.
	 *
	 * ## EXAMPLES
	 *
	 *     # Validate all tools with default (extended) level
	 *     wp mcp validate-tools
	 *
	 *     # Validate specific tool with strict MCP compliance
	 *     wp mcp validate-tools create_post --level=strict
	 *
	 *     # Show only tools with errors in JSON format
	 *     wp mcp validate-tools --errors-only --format=json
	 *
	 *     # Permissive validation (warnings instead of errors)
	 *     wp mcp validate-tools --level=permissive --detailed
	 *
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return void
	 * @throws \WP_CLI\ExitException
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		if ( ! $this->is_mcp_enabled() ) {
			\WP_CLI::error( 'WordPress MCP plugin is not active or properly configured.' );
		}

		$tool_name = $args[0] ?? null;
		$validation_level = $assoc_args['level'] ?? ValidateTool::LEVEL_EXTENDED;
		$format = $assoc_args['format'] ?? 'table';
		$errors_only = isset( $assoc_args['errors-only'] );
		$warnings_only = isset( $assoc_args['warnings-only'] );
		$detailed = isset( $assoc_args['detailed'] );

		// Validate level parameter.
		$valid_levels = array( ValidateTool::LEVEL_STRICT, ValidateTool::LEVEL_EXTENDED, ValidateTool::LEVEL_PERMISSIVE );
		if ( ! in_array( $validation_level, $valid_levels, true ) ) {
			\WP_CLI::error( sprintf( 'Invalid validation level "%s". Must be one of: %s', $validation_level, implode( ', ', $valid_levels ) ) );
		}

		if ( $tool_name ) {
			$this->validate_single_tool( $tool_name, $validation_level, $format, $detailed );
		} else {
			$this->validate_all_tools( $validation_level, $format, $errors_only, $warnings_only, $detailed );
		}
	}

	/**
	 * Validate a single tool by name.
	 *
	 * @param string $tool_name The tool name to validate.
	 * @param string $validation_level The validation level.
	 * @param string $format Output format.
	 * @param bool   $detailed Show detailed messages.
	 * @return void
	 * @throws \WP_CLI\ExitException
	 */
	private function validate_single_tool( string $tool_name, string $validation_level, string $format, bool $detailed ): void {
		$wp_mcp = WpMcp::instance();
		$all_tools = $wp_mcp->get_all_tools();

		$tool = null;
		foreach ( $all_tools as $registered_tool ) {
			if ( $registered_tool['name'] === $tool_name ) {
				$tool = $registered_tool;
				break;
			}
		}

		if ( ! $tool ) {
			\WP_CLI::error( sprintf( 'Tool "%s" not found. Use "wp mcp validate-tools" to see all available tools.', $tool_name ) );
		}

		$validation_result = ValidateTool::validate_with_level( $tool, $validation_level );

		if ( 'table' === $format ) {
			$this->display_single_tool_result( $validation_result, $detailed );
		} else {
			\WP_CLI::print_value( $validation_result, array( 'format' => $format ) );
		}

		if ( ValidateTool::has_errors( $validation_result ) ) {
			\WP_CLI::halt( 1 );
		}
	}

	/**
	 * Validate all registered tools.
	 *
	 * @param string $validation_level The validation level.
	 * @param string $format Output format.
	 * @param bool   $errors_only Show only tools with errors.
	 * @param bool   $warnings_only Show only tools with warnings.
	 * @param bool   $detailed Show detailed messages.
	 * @return void
	 * @throws \WP_CLI\ExitException
	 */
	private function validate_all_tools( string $validation_level, string $format, bool $errors_only, bool $warnings_only, bool $detailed ): void {
		$wp_mcp = WpMcp::instance();
		$all_tools = $wp_mcp->get_all_tools();

		if ( empty( $all_tools ) ) {
			\WP_CLI::warning( 'No MCP tools found.' );
			return;
		}

		\WP_CLI::log( sprintf( 'Validating %d tools with %s level...', count( $all_tools ), $validation_level ) );

		$results = ValidateTool::validate_tools( $all_tools, $validation_level );

		// Filter results if requested.
		if ( $errors_only ) {
			$results = ValidateTool::filter_errors_only( $results );
		} elseif ( $warnings_only ) {
			$results = ValidateTool::filter_warnings_only( $results );
		}

		if ( 'table' === $format ) {
			$this->display_summary_results( $results, $validation_level );
			if ( $detailed ) {
				$this->display_detailed_results( $results );
			}
		} else {
			\WP_CLI::print_value( $results, array( 'format' => $format ) );
		}

		$summary = ValidateTool::get_validation_summary( $results );
		if ( $summary['invalid'] > 0 ) {
			\WP_CLI::halt( 1 );
		}
	}

	/**
	 * Display results for a single tool.
	 *
	 * @param array $result The validation result.
	 * @param bool  $detailed Show detailed messages.
	 * @return void
	 */
	private function display_single_tool_result( array $result, bool $detailed ): void {
		$status = $result['valid'] ? 'Valid' : 'Invalid';
		$status_color = $result['valid'] ? '%G' : '%R';

		\WP_CLI::log( \WP_CLI::colorize( sprintf( '%sTool: %s%%n', $status_color, $result['name'] ) ) );
		\WP_CLI::log( \WP_CLI::colorize( sprintf( '%sStatus: %s%%n', $status_color, $status ) ) );
		\WP_CLI::log( sprintf( 'Type: %s', $result['type'] ) );
		\WP_CLI::log( sprintf( 'Validation Level: %s', $result['validation_level'] ?? 'unknown' ) );
		\WP_CLI::log( sprintf( 'Enabled: %s', $result['enabled'] ? 'Yes' : 'No' ) );

		if ( $result['has_rest_alias'] ) {
			\WP_CLI::log( 'Has REST Alias: Yes' );
		}

		if ( ! empty( $result['errors'] ) ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( \WP_CLI::colorize( '%RErrors:%n' ) );
			foreach ( $result['errors'] as $error ) {
				\WP_CLI::log( sprintf( '  - %s', $error ) );
			}
		}

		if ( ! empty( $result['warnings'] ) ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( \WP_CLI::colorize( '%YWarnings:%n' ) );
			foreach ( $result['warnings'] as $warning ) {
				\WP_CLI::log( sprintf( '  - %s', $warning ) );
			}
		}
	}

	/**
	 * Display summary results for all tools.
	 *
	 * @param array  $results The validation results.
	 * @param string $validation_level The validation level used.
	 * @return void
	 */
	private function display_summary_results( array $results, string $validation_level ): void {
		$summary = ValidateTool::get_validation_summary( $results );

		\WP_CLI::log( '' );
		\WP_CLI::log( \WP_CLI::colorize( sprintf( '%%BValidation Summary (Level: %s)%%n', $validation_level ) ) );
		\WP_CLI::log( sprintf( 'Total Tools: %d', $summary['total'] ) );
		\WP_CLI::log( \WP_CLI::colorize( sprintf( '%%GValid: %d%%n', $summary['valid'] ) ) );
		\WP_CLI::log( \WP_CLI::colorize( sprintf( '%%RInvalid: %d%%n', $summary['invalid'] ) ) );
		\WP_CLI::log( \WP_CLI::colorize( sprintf( '%%YValid with Warnings: %d%%n', $summary['valid_with_warnings'] ) ) );
		\WP_CLI::log( sprintf( 'Success Rate: %.1f%%', $summary['success_rate'] ) );

		if ( empty( $results ) ) {
			\WP_CLI::log( 'No tools match the specified filters.' );
			return;
		}

		$table_data = array();
		foreach ( $results as $result ) {
			$status_icon = $result['valid'] ? '✓' : '✗';
			$warnings_count = count( $result['warnings'] ?? array() );
			$errors_count = count( $result['errors'] ?? array() );

			$table_data[] = array(
				'Status' => $status_icon,
				'Name' => $result['name'],
				'Type' => $result['type'],
				'Enabled' => $result['enabled'] ? 'Yes' : 'No',
				'REST' => $result['has_rest_alias'] ? 'Yes' : 'No',
				'Errors' => $errors_count,
				'Warnings' => $warnings_count,
			);
		}

		\WP_CLI::log( '' );
		\WP_CLI\Utils\format_items( 'table', $table_data, array( 'Status', 'Name', 'Type', 'Enabled', 'REST', 'Errors', 'Warnings' ) );
	}

	/**
	 * Display detailed results with error and warning messages.
	 *
	 * @param array $results The validation results.
	 * @return void
	 */
	private function display_detailed_results( array $results ): void {
		\WP_CLI::log( '' );
		\WP_CLI::log( \WP_CLI::colorize( '%BDetailed Validation Results%n' ) );

		foreach ( $results as $result ) {
			if ( empty( $result['errors'] ) && empty( $result['warnings'] ) ) {
				continue;
			}

			\WP_CLI::log( '' );
			\WP_CLI::log( \WP_CLI::colorize( sprintf( '%s%s%%n', $result['valid'] ? '%G' : '%R', $result['name'] ) ) );

			if ( ! empty( $result['errors'] ) ) {
				\WP_CLI::log( \WP_CLI::colorize( '  %RErrors:%n' ) );
				foreach ( $result['errors'] as $error ) {
					\WP_CLI::log( sprintf( '    - %s', $error ) );
				}
			}

			if ( ! empty( $result['warnings'] ) ) {
				\WP_CLI::log( \WP_CLI::colorize( '  %YWarnings:%n' ) );
				foreach ( $result['warnings'] as $warning ) {
					\WP_CLI::log( sprintf( '    - %s', $warning ) );
				}
			}
		}
	}

	/**
	 * Check if MCP is enabled and properly configured.
	 *
	 * @return bool True if MCP is enabled.
	 */
	private function is_mcp_enabled(): bool {
		return class_exists( 'Automattic\WordpressMcp\Core\WpMcp' );
	}
} 