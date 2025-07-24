<?php //phpcs:ignore
declare(strict_types=1);

namespace Automattic\WordpressMcp\Utils;

/**
 * Utility class for validating MCP tools.
 */
class ValidateTool {

	/**
	 * Validation levels.
	 */
	public const LEVEL_STRICT     = 'strict';
	public const LEVEL_EXTENDED   = 'extended';
	public const LEVEL_PERMISSIVE = 'permissive';

	/**
	 * Validate a single tool with default (extended) level.
	 *
	 * @param array $tool The tool data.
	 * @return array Validation result.
	 */
	public static function validate_tool( array $tool ): array {
		return self::validate_with_level( $tool, self::LEVEL_EXTENDED );
	}

	/**
	 * Validate tool with strict MCP compliance only.
	 *
	 * @param array $tool The tool data.
	 * @return array Validation result.
	 */
	public static function validate_mcp_strict( array $tool ): array {
		return self::validate_with_level( $tool, self::LEVEL_STRICT );
	}

	/**
	 * Validate tool with WordPress extensions (default).
	 *
	 * @param array $tool The tool data.
	 * @return array Validation result.
	 */
	public static function validate_wordpress_extended( array $tool ): array {
		return self::validate_with_level( $tool, self::LEVEL_EXTENDED );
	}

	/**
	 * Validate tool with permissive mode (warnings instead of errors).
	 *
	 * @param array $tool The tool data.
	 * @return array Validation result.
	 */
	public static function validate_permissive( array $tool ): array {
		return self::validate_with_level( $tool, self::LEVEL_PERMISSIVE );
	}

	/**
	 * Validate tool with specific level.
	 *
	 * @param array  $tool The tool data.
	 * @param string $level Validation level.
	 * @return array Validation result.
	 */
	public static function validate_with_level( array $tool, string $level ): array {
		$result = array(
			'name'                  => $tool['name'] ?? '',
			'type'                  => $tool['type'] ?? 'unknown',
			'enabled'               => $tool['enabled'] ?? false,
			'tool_enabled'          => $tool['tool_enabled'] ?? false,
			'tool_type_enabled'     => $tool['tool_type_enabled'] ?? false,
			'disabled_by_rest_crud' => $tool['disabled_by_rest_crud'] ?? false,
			'has_rest_alias'        => ! empty( $tool['rest_alias'] ),
			'valid'                 => true,
			'errors'                => array(),
			'warnings'              => array(),
			'validation_level'      => $level,
		);

		try {
			// Always validate core MCP fields.
			self::validate_mcp_fields( $tool, $result, $level );

			// Validate WordPress-specific fields if not strict mode.
			if ( self::LEVEL_STRICT !== $level ) {
				self::validate_wordpress_fields( $tool, $result, $level );
			}

			// Optional: Validate against JSON schemas.
			if ( self::should_use_schema_validation() ) {
				self::validate_with_schemas( $tool, $result, $level );
			}
		} catch ( \Exception $e ) {
			$result['valid']    = false;
			$result['errors'][] = $e->getMessage();
		}

		return $result;
	}

	/**
	 * Validate multiple tools.
	 *
	 * @param array  $tools Array of tools to validate.
	 * @param string $level Validation level.
	 * @return array Array of validation results.
	 */
	public static function validate_tools( array $tools, string $level = self::LEVEL_EXTENDED ): array {
		$results = array();

		foreach ( $tools as $tool ) {
			$results[] = self::validate_with_level( $tool, $level );
		}

		return $results;
	}

	/**
	 * Get validation summary for an array of validation results.
	 *
	 * @param array $validation_results Array of validation results.
	 * @return array Summary statistics.
	 */
	public static function get_validation_summary( array $validation_results ): array {
		$total    = count( $validation_results );
		$valid    = 0;
		$invalid  = 0;
		$warnings = 0;

		foreach ( $validation_results as $result ) {
			if ( $result['valid'] ) {
				++$valid;
				if ( ! empty( $result['warnings'] ) ) {
					++$warnings;
				}
			} else {
				++$invalid;
			}
		}

		return array(
			'total'               => $total,
			'valid'               => $valid,
			'invalid'             => $invalid,
			'valid_with_warnings' => $warnings,
			'success_rate'        => $total > 0 ? ( $valid / $total ) * 100 : 0,
		);
	}

	/**
	 * Validate core MCP fields.
	 *
	 * @param array  $tool The tool data.
	 * @param array  &$result The validation result (passed by reference).
	 * @param string $level Validation level.
	 * @return void
	 */
	private static function validate_mcp_fields( array $tool, array &$result, string $level ): void {
		// Name validation (required).
		if ( empty( $tool['name'] ) ) {
			self::add_issue( $result, 'Tool name is required.', $level );
		} elseif ( ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', $tool['name'] ) ) {
			self::add_issue( $result, 'Tool name must be 1-64 characters and contain only letters, numbers, underscores, and hyphens.', $level );
		}

		// Description validation (required).
		if ( empty( $tool['description'] ) ) {
			self::add_issue( $result, 'Tool description is required.', $level );
		}

		// Title validation (optional).
		if ( isset( $tool['title'] ) ) {
			if ( ! is_string( $tool['title'] ) ) {
				self::add_issue( $result, 'Tool title must be a string.', $level );
			} elseif ( strlen( $tool['title'] ) > 200 ) {
				self::add_issue( $result, 'Tool title must be under 200 characters.', $level );
			}
		}

		// Input schema validation (optional but comprehensive).
		if ( isset( $tool['inputSchema'] ) ) {
			self::validate_json_schema_comprehensive( $tool['inputSchema'], 'inputSchema', $result, $level );
		}

		// Output schema validation (optional).
		if ( isset( $tool['outputSchema'] ) ) {
			self::validate_json_schema_comprehensive( $tool['outputSchema'], 'outputSchema', $result, $level );
		}

		// Annotations validation (optional).
		if ( isset( $tool['annotations'] ) ) {
			self::validate_annotations( $tool['annotations'], $result, $level );
		}
	}

	/**
	 * Validate WordPress-specific fields.
	 *
	 * @param array  $tool The tool data.
	 * @param array  &$result The validation result (passed by reference).
	 * @param string $level Validation level.
	 * @return void
	 */
	private static function validate_wordpress_fields( array $tool, array &$result, string $level ): void {
		// Type validation (WordPress-specific).
		$valid_types = array( 'create', 'read', 'update', 'delete', 'action' );
		if ( empty( $tool['type'] ) ) {
			self::add_issue( $result, 'Tool type is required.', $level );
		} elseif ( ! in_array( $tool['type'], $valid_types, true ) ) {
			self::add_issue( $result, sprintf( 'Tool type must be one of: %s', implode( ', ', $valid_types ) ), $level );
		}

		// Callback validation.
		if ( isset( $tool['callback'] ) ) {
			self::validate_php_callable( $tool['callback'], 'callback', $result, $level );
		}

		// Permission callback validation.
		if ( isset( $tool['permission_callback'] ) ) {
			self::validate_php_callable( $tool['permission_callback'], 'permission_callback', $result, $level );
		}

		// Legacy permissions callback validation.
		if ( isset( $tool['permissions_callback'] ) ) {
			self::validate_php_callable( $tool['permissions_callback'], 'permissions_callback', $result, $level );
		}

		// REST alias validation.
		if ( ! empty( $tool['rest_alias'] ) ) {
			self::validate_rest_alias( $tool['rest_alias'], $result, $level );
		}

		// Check tool enablement status.
		self::check_tool_status( $tool, $result );
	}

	/**
	 * Validate comprehensive JSON Schema.
	 *
	 * @param array  $schema The schema to validate.
	 * @param string $context Context for error messages (inputSchema/outputSchema).
	 * @param array  &$result The validation result (passed by reference).
	 * @param string $level Validation level.
	 * @return void
	 */
	private static function validate_json_schema_comprehensive( array $schema, string $context, array &$result, string $level ): void {
		// Must be an object type.
		if ( ! isset( $schema['type'] ) || 'object' !== $schema['type'] ) {
			self::add_issue( $result, "{$context} must be an object type.", $level );
			return;
		}

		// Validate properties if present.
		if ( isset( $schema['properties'] ) ) {
			if ( ! is_array( $schema['properties'] ) ) {
				self::add_issue( $result, "{$context} properties must be an object.", $level );
				return;
			}

			// Validate each property.
			foreach ( $schema['properties'] as $property_name => $property ) {
				if ( ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', $property_name ) ) {
					self::add_issue( $result, "Property name '{$property_name}' in {$context} must match pattern '^[a-zA-Z0-9_-]{1,64}$'.", $level );
				}

				if ( ! isset( $property['type'] ) ) {
					self::add_issue( $result, "Property '{$property_name}' in {$context} must have a type field.", $level );
				} else {
					$valid_types = array( 'string', 'number', 'integer', 'boolean', 'array', 'object', 'null' );
					if ( ! in_array( $property['type'], $valid_types, true ) ) {
						self::add_issue( $result, "Property '{$property_name}' in {$context} has invalid type '{$property['type']}'.", $level );
					}

					// Array type must have items.
					if ( 'array' === $property['type'] && ! isset( $property['items'] ) ) {
						self::add_issue( $result, "Array property '{$property_name}' in {$context} must have an items field.", $level );
					}
				}

				// Validate additional JSON Schema keywords.
				self::validate_json_schema_keywords( $property, $property_name, $context, $result, $level );
			}
		}

		// Validate required field if present.
		if ( isset( $schema['required'] ) ) {
			if ( ! is_array( $schema['required'] ) ) {
				self::add_issue( $result, "Required field in {$context} must be an array.", $level );
			} else {
				foreach ( $schema['required'] as $required_property ) {
					if ( ! is_string( $required_property ) || empty( $required_property ) ) {
						self::add_issue( $result, "Required field names in {$context} must be non-empty strings.", $level );
					}
				}

				// Check all required properties exist in properties.
				if ( isset( $schema['properties'] ) ) {
					foreach ( $schema['required'] as $required_property ) {
						if ( ! isset( $schema['properties'][ $required_property ] ) ) {
							self::add_issue( $result, "Required property '{$required_property}' in {$context} does not exist in properties.", $level );
						}
					}
				} elseif ( ! empty( $schema['required'] ) ) {
					self::add_issue( $result, "Cannot have required fields without a properties definition in {$context}.", $level );
				}
			}
		}
	}

	/**
	 * Validate additional JSON Schema keywords.
	 *
	 * @param array  $property The property schema.
	 * @param string $property_name The property name.
	 * @param string $context The context (inputSchema/outputSchema).
	 * @param array  &$result The validation result (passed by reference).
	 * @param string $level Validation level.
	 * @return void
	 */
	private static function validate_json_schema_keywords( array $property, string $property_name, string $context, array &$result, string $level ): void {
		$type = $property['type'] ?? '';

		// String constraints.
		if ( 'string' === $type ) {
			if ( isset( $property['minLength'] ) && ( ! is_int( $property['minLength'] ) || $property['minLength'] < 0 ) ) {
				self::add_issue( $result, "Property '{$property_name}' in {$context} has invalid minLength.", $level );
			}
			if ( isset( $property['maxLength'] ) && ( ! is_int( $property['maxLength'] ) || $property['maxLength'] < 0 ) ) {
				self::add_issue( $result, "Property '{$property_name}' in {$context} has invalid maxLength.", $level );
			}
			if ( isset( $property['pattern'] ) && ! is_string( $property['pattern'] ) ) {
				self::add_issue( $result, "Property '{$property_name}' in {$context} pattern must be a string.", $level );
			}
		}

		// Numeric constraints.
		if ( in_array( $type, array( 'number', 'integer' ), true ) ) {
			if ( isset( $property['minimum'] ) && ! is_numeric( $property['minimum'] ) ) {
				self::add_issue( $result, "Property '{$property_name}' in {$context} has invalid minimum.", $level );
			}
			if ( isset( $property['maximum'] ) && ! is_numeric( $property['maximum'] ) ) {
				self::add_issue( $result, "Property '{$property_name}' in {$context} has invalid maximum.", $level );
			}
		}

		// Array constraints.
		if ( 'array' === $type ) {
			if ( isset( $property['minItems'] ) && ( ! is_int( $property['minItems'] ) || $property['minItems'] < 0 ) ) {
				self::add_issue( $result, "Property '{$property_name}' in {$context} has invalid minItems.", $level );
			}
			if ( isset( $property['maxItems'] ) && ( ! is_int( $property['maxItems'] ) || $property['maxItems'] < 0 ) ) {
				self::add_issue( $result, "Property '{$property_name}' in {$context} has invalid maxItems.", $level );
			}
		}
	}

	/**
	 * Validate annotations according to MCP specification.
	 *
	 * @param array  $annotations The annotations to validate.
	 * @param array  &$result The validation result (passed by reference).
	 * @param string $level Validation level.
	 * @return void
	 */
	private static function validate_annotations( array $annotations, array &$result, string $level ): void {
		// Audience validation.
		if ( isset( $annotations['audience'] ) ) {
			if ( ! is_array( $annotations['audience'] ) ) {
				self::add_issue( $result, 'Annotations audience must be an array.', $level );
			} else {
				$valid_audiences = array( 'user', 'assistant' );
				foreach ( $annotations['audience'] as $audience ) {
					if ( ! in_array( $audience, $valid_audiences, true ) ) {
						self::add_issue( $result, "Invalid audience '{$audience}'. Must be 'user' or 'assistant'.", $level );
					}
				}
			}
		}

		// Priority validation.
		if ( isset( $annotations['priority'] ) ) {
			if ( ! is_numeric( $annotations['priority'] ) ) {
				self::add_issue( $result, 'Annotations priority must be a number.', $level );
			} elseif ( $annotations['priority'] < 0 || $annotations['priority'] > 1 ) {
				self::add_issue( $result, 'Annotations priority must be between 0 and 1.', $level );
			}
		}

		// LastModified validation.
		if ( isset( $annotations['lastModified'] ) ) {
			if ( ! is_string( $annotations['lastModified'] ) ) {
				self::add_issue( $result, 'Annotations lastModified must be a string.', $level );
			} elseif ( ! self::is_valid_iso8601( $annotations['lastModified'] ) ) {
				self::add_issue( $result, 'Annotations lastModified must be a valid ISO 8601 timestamp.', $level );
			}
		}
	}

	/**
	 * Validate REST alias configuration.
	 *
	 * @param array  $rest_alias The REST alias configuration.
	 * @param array  &$result The validation result (passed by reference).
	 * @param string $level Validation level.
	 * @return void
	 */
	private static function validate_rest_alias( array $rest_alias, array &$result, string $level ): void {
		if ( empty( $rest_alias['route'] ) ) {
			self::add_issue( $result, 'REST alias route is required.', $level );
		}

		if ( empty( $rest_alias['method'] ) ) {
			self::add_issue( $result, 'REST alias method is required.', $level );
		} elseif ( ! in_array( $rest_alias['method'], array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
			self::add_issue( $result, 'REST alias method must be one of: GET, POST, PUT, PATCH, DELETE.', $level );
		}

		// Validate preCallback if present in REST alias.
		if ( isset( $rest_alias['preCallback'] ) ) {
			self::validate_php_callable( $rest_alias['preCallback'], 'preCallback', $result, $level );
		}

		// Check if the REST route actually exists.
		if ( ! empty( $rest_alias['route'] ) && function_exists( 'rest_get_server' ) ) {
			$routes = rest_get_server()->get_routes();
			if ( ! isset( $routes[ $rest_alias['route'] ] ) ) {
				$result['warnings'][] = sprintf( "REST route '%s' does not exist.", $rest_alias['route'] );
			}
		}
	}

	/**
	 * Validate a PHP callable (string, array, or closure).
	 *
	 * @param mixed  $callback The callable to validate.
	 * @param string $field_name The field name for error messages.
	 * @param array  &$result The validation result (passed by reference).
	 * @param string $level Validation level.
	 * @return void
	 */
	private static function validate_php_callable( $callback, string $field_name, array &$result, string $level ): void {
		// Check if it's a valid callable.
		if ( ! is_callable( $callback ) ) {
			// If not callable, check if it's in a valid format.
			if ( is_string( $callback ) ) {
				// String function name - valid format even if function doesn't exist.
				return;
			}

			if ( is_array( $callback ) && 2 === count( $callback ) ) {
				// Array format [class/object, method] - valid format.
				$class_or_object = $callback[0];
				$method          = $callback[1];

				if ( ! is_string( $method ) ) {
					self::add_issue( $result, "Field '{$field_name}' array format requires method name as string.", $level );
					return;
				}

				if ( is_string( $class_or_object ) ) {
					// Static method reference [ClassName, method].
					return;
				}

				if ( is_object( $class_or_object ) ) {
					// Instance method reference [object, method].
					return;
				}

				self::add_issue( $result, "Field '{$field_name}' array format requires class name or object as first element.", $level );
				return;
			}

			if ( is_object( $callback ) && ( $callback instanceof \Closure ) ) {
				// Closure/anonymous function - valid.
				return;
			}

			self::add_issue( $result, "Field '{$field_name}' must be a valid PHP callable (string, array, or closure).", $level );
		}
	}

	/**
	 * Check tool enablement status and add warnings.
	 *
	 * @param array $tool The tool data.
	 * @param array &$result The validation result (passed by reference).
	 * @return void
	 */
	private static function check_tool_status( array $tool, array &$result ): void {
		if ( isset( $tool['disabled_by_rest_crud'] ) && $tool['disabled_by_rest_crud'] ) {
			$result['warnings'][] = 'Tool is disabled because REST API CRUD tools are enabled.';
		}

		if ( isset( $tool['tool_enabled'] ) && ! $tool['tool_enabled'] ) {
			$result['warnings'][] = 'Tool is individually disabled in settings.';
		}

		if ( isset( $tool['tool_type_enabled'] ) && ! $tool['tool_type_enabled'] ) {
			$result['warnings'][] = sprintf( "Tool type '%s' is disabled in settings.", $tool['type'] ?? 'unknown' );
		}
	}

	/**
	 * Validate with JSON schemas if available.
	 *
	 * @param array  $tool The tool data.
	 * @param array  &$result The validation result (passed by reference).
	 * @param string $level Validation level.
	 * @return void
	 */
	private static function validate_with_schemas( array $tool, array &$result, string $level ): void {
		try {
			// Validate against MCP schema if in strict mode or extended mode.
			if ( in_array( $level, array( self::LEVEL_STRICT, self::LEVEL_EXTENDED ), true ) ) {
				$mcp_validation = SchemaValidator::validate_mcp_tool( $tool );
				if ( ! $mcp_validation['valid'] ) {
					foreach ( $mcp_validation['errors'] as $error ) {
						self::add_issue( $result, "MCP Schema: {$error}", $level );
					}
				}
			}

			// Validate against WordPress schema if in extended mode.
			if ( self::LEVEL_EXTENDED === $level ) {
				// Filter out callback fields since we validate them manually.
				$filtered_tool = $tool;
				unset( $filtered_tool['callback'] );
				unset( $filtered_tool['permission_callback'] );
				unset( $filtered_tool['permissions_callback'] );

				$wp_validation = SchemaValidator::validate_wordpress_tool( $filtered_tool );
				if ( ! $wp_validation['valid'] ) {
					foreach ( $wp_validation['errors'] as $error ) {
						// WordPress schema errors are less critical, so add as warnings in permissive mode.
						if ( self::LEVEL_PERMISSIVE === $level ) {
							$result['warnings'][] = "WordPress Schema: {$error}";
						} else {
							self::add_issue( $result, "WordPress Schema: {$error}", $level );
						}
					}
				}
			}
		} catch ( \Exception $e ) {
			// Schema validation failure is not critical, just log it.
			$result['warnings'][] = 'Schema validation unavailable: ' . $e->getMessage();
		}
	}

	/**
	 * Add an issue (error or warning) based on validation level.
	 *
	 * @param array  &$result The validation result (passed by reference).
	 * @param string $message The issue message.
	 * @param string $level Validation level.
	 * @return void
	 */
	private static function add_issue( array &$result, string $message, string $level ): void {
		if ( self::LEVEL_PERMISSIVE === $level ) {
			$result['warnings'][] = $message;
		} else {
			$result['valid']    = false;
			$result['errors'][] = $message;
		}
	}

	/**
	 * Check if schema validation should be used.
	 *
	 * @return bool True if schema validation is enabled.
	 */
	private static function should_use_schema_validation(): bool {
		// Check WordPress settings or default to true.
		$settings = get_option( 'wordpress_mcp_settings', array() );
		return $settings['schema_validation_enabled'] ?? true;
	}

	/**
	 * Validate ISO 8601 timestamp format.
	 *
	 * @param string $timestamp The timestamp to validate.
	 * @return bool True if valid ISO 8601 format.
	 */
	private static function is_valid_iso8601( string $timestamp ): bool {
		try {
			$date = \DateTime::createFromFormat(\DateTime::ATOM, $timestamp);
			return $date !== false && !\DateTime::getLastErrors()['warning_count'] && !\DateTime::getLastErrors()['error_count'];
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Check if a tool has validation errors.
	 *
	 * @param array $validation_result The validation result.
	 * @return bool True if the tool has errors.
	 */
	public static function has_errors( array $validation_result ): bool {
		return ! $validation_result['valid'] || ! empty( $validation_result['errors'] );
	}

	/**
	 * Check if a tool has validation warnings.
	 *
	 * @param array $validation_result The validation result.
	 * @return bool True if the tool has warnings.
	 */
	public static function has_warnings( array $validation_result ): bool {
		return ! empty( $validation_result['warnings'] );
	}

	/**
	 * Get all validation errors from a validation result.
	 *
	 * @param array $validation_result The validation result.
	 * @return array Array of error messages.
	 */
	public static function get_errors( array $validation_result ): array {
		return $validation_result['errors'] ?? array();
	}

	/**
	 * Get all validation warnings from a validation result.
	 *
	 * @param array $validation_result The validation result.
	 * @return array Array of warning messages.
	 */
	public static function get_warnings( array $validation_result ): array {
		return $validation_result['warnings'] ?? array();
	}

	/**
	 * Filter validation results to only include tools with errors.
	 *
	 * @param array $validation_results Array of validation results.
	 * @return array Filtered array containing only tools with errors.
	 */
	public static function filter_errors_only( array $validation_results ): array {
		return array_filter( $validation_results, array( self::class, 'has_errors' ) );
	}

	/**
	 * Filter validation results to only include tools with warnings.
	 *
	 * @param array $validation_results Array of validation results.
	 * @return array Filtered array containing only tools with warnings.
	 */
	public static function filter_warnings_only( array $validation_results ): array {
		return array_filter( $validation_results, array( self::class, 'has_warnings' ) );
	}
}
