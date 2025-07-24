<?php //phpcs:ignore
declare(strict_types=1);

namespace Automattic\WordpressMcp\Utils;

/**
 * Utility class for JSON Schema validation of MCP tools.
 */
class SchemaValidator {

	/**
	 * Cache for loaded schemas.
	 *
	 * @var array
	 */
	private static array $schema_cache = array();

	/**
	 * Cache TTL in seconds (1 hour).
	 *
	 * @var int
	 */
	private static int $cache_ttl = 3600;

	/**
	 * Load a schema by name.
	 *
	 * @param string $schema_name The schema name (without .json extension).
	 * @return array The loaded schema.
	 * @throws \InvalidArgumentException If schema file doesn't exist or is invalid.
	 */
	public static function load_schema( string $schema_name ): array {
		$cache_key = $schema_name;

		// Check cache first.
		if ( isset( self::$schema_cache[ $cache_key ] ) ) {
			$cached_data = self::$schema_cache[ $cache_key ];

			// Check if cache is still valid.
			if ( time() - $cached_data['timestamp'] < self::$cache_ttl ) {
				return $cached_data['schema'];
			}

			// Cache expired, remove it.
			unset( self::$schema_cache[ $cache_key ] );
		}

		// Build schema file path.
		$schema_file = WORDPRESS_MCP_PATH . "includes/schemas/{$schema_name}.json";

		if ( ! file_exists( $schema_file ) ) {
			throw new \InvalidArgumentException( esc_html( "Schema file not found: {$schema_name}.json" ) );
		}

		// Load and decode schema.
		$schema_content = file_get_contents( $schema_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $schema_content ) {
			throw new \InvalidArgumentException( esc_html( "Failed to read schema file: {$schema_name}.json" ) );
		}

		$schema = json_decode( $schema_content, true );
		if ( null === $schema ) {
			throw new \InvalidArgumentException( esc_html( "Invalid JSON in schema file: {$schema_name}.json - " . json_last_error_msg() ) );
		}

		// Resolve any schema references.
		$schema = self::resolve_schema_refs( $schema );

		// Cache the schema.
		self::$schema_cache[ $cache_key ] = array(
			'schema'    => $schema,
			'timestamp' => time(),
		);

		return $schema;
	}

	/**
	 * Validate data against a schema.
	 *
	 * @param array $data The data to validate.
	 * @param array $schema The schema to validate against.
	 * @return array Validation result with 'valid' boolean and 'errors' array.
	 */
	public static function validate_against_schema( array $data, array $schema ): array {
		$result = array(
			'valid'  => true,
			'errors' => array(),
		);

		try {
			self::validate_schema_recursive( $data, $schema, '', $result );
		} catch ( \Exception $e ) {
			$result['valid']    = false;
			$result['errors'][] = $e->getMessage();
		}

		return $result;
	}

	/**
	 * Get validation errors for data against schema.
	 *
	 * @param array $data The data to validate.
	 * @param array $schema The schema to validate against.
	 * @return array Array of error messages.
	 */
	public static function get_schema_errors( array $data, array $schema ): array {
		$result = self::validate_against_schema( $data, $schema );
		return $result['errors'];
	}

	/**
	 * Validate MCP tool against core MCP schema.
	 *
	 * @param array $tool The tool to validate.
	 * @return array Validation result.
	 */
	public static function validate_mcp_tool( array $tool ): array {
		try {
			$mcp_schema = self::load_schema( 'mcp-2025-06-18' );

			// Extract tool definition from the larger schema.
			$tool_schema = $mcp_schema['definitions']['Tool'] ?? $mcp_schema;

			return self::validate_against_schema( $tool, $tool_schema );
		} catch ( \Exception $e ) {
			return array(
				'valid'  => false,
				'errors' => array( 'Schema validation failed: ' . $e->getMessage() ),
			);
		}
	}

	/**
	 * Validate tool against WordPress extensions schema.
	 *
	 * @param array $tool The tool to validate.
	 * @return array Validation result.
	 */
	public static function validate_wordpress_tool( array $tool ): array {
		try {
			$wp_schema = self::load_schema( 'wordpress-mcp-extensions' );
			return self::validate_against_schema( $tool, $wp_schema );
		} catch ( \Exception $e ) {
			return array(
				'valid'  => false,
				'errors' => array( 'WordPress schema validation failed: ' . $e->getMessage() ),
			);
		}
	}

	/**
	 * Clear the schema cache.
	 *
	 * @return void
	 */
	public static function clear_cache(): void {
		self::$schema_cache = array();
	}

	/**
	 * Set cache TTL.
	 *
	 * @param int $ttl Time to live in seconds.
	 * @return void
	 */
	public static function set_cache_ttl( int $ttl ): void {
		self::$cache_ttl = $ttl;
	}

	/**
	 * Resolve schema references ($ref).
	 *
	 * @param array $schema The schema to resolve references in.
	 * @return array The schema with resolved references.
	 */
	private static function resolve_schema_refs( array $schema ): array {
		// Simple implementation - just return as-is for now.
		// TODO: Implement proper $ref resolution if needed.
		return $schema;
	}

	/**
	 * Recursively validate data against schema.
	 *
	 * @param mixed  $data The data to validate.
	 * @param array  $schema The schema to validate against.
	 * @param string $path Current path for error reporting.
	 * @param array  &$result Validation result (passed by reference).
	 * @return void
	 */
	private static function validate_schema_recursive( $data, array $schema, string $path, array &$result ): void {
		// Handle oneOf validation first (mutually exclusive options).
		if ( isset( $schema['oneOf'] ) ) {
			self::validate_one_of( $data, $schema['oneOf'], $path, $result );
			return;
		}

		// Handle anyOf validation (one or more must match).
		if ( isset( $schema['anyOf'] ) ) {
			self::validate_any_of( $data, $schema['anyOf'], $path, $result );
			return;
		}

		// Handle type validation.
		if ( isset( $schema['type'] ) ) {
			if ( ! self::validate_type( $data, $schema['type'], $path, $result ) ) {
				return; // Early return if type validation fails.
			}
		}

		// Handle object properties.
		if ( 'object' === ( $schema['type'] ?? '' ) && is_array( $data ) ) {
			self::validate_object_properties( $data, $schema, $path, $result );
		}

		// Handle array items.
		if ( 'array' === ( $schema['type'] ?? '' ) && is_array( $data ) ) {
			self::validate_array_items( $data, $schema, $path, $result );
		}

		// Handle string constraints.
		if ( 'string' === ( $schema['type'] ?? '' ) && is_string( $data ) ) {
			self::validate_string_constraints( $data, $schema, $path, $result );
		}

		// Handle numeric constraints.
		if ( in_array( $schema['type'] ?? '', array( 'number', 'integer' ), true ) && is_numeric( $data ) ) {
			self::validate_numeric_constraints( $data, $schema, $path, $result );
		}

		// Handle enum validation.
		if ( isset( $schema['enum'] ) ) {
			self::validate_enum( $data, $schema['enum'], $path, $result );
		}
	}

	/**
	 * Validate data type.
	 *
	 * @param mixed  $data The data to validate.
	 * @param string $expected_type The expected type.
	 * @param string $path Current path for error reporting.
	 * @param array  &$result Validation result (passed by reference).
	 * @return bool True if type is valid.
	 */
	private static function validate_type( $data, string $expected_type, string $path, array &$result ): bool {
		$actual_type = self::get_json_type( $data );

		if ( $actual_type !== $expected_type ) {
			$result['valid']    = false;
			$result['errors'][] = "Type mismatch at '{$path}': expected {$expected_type}, got {$actual_type}";
			return false;
		}

		return true;
	}

	/**
	 * Validate object properties.
	 *
	 * @param array  $data The object data.
	 * @param array  $schema The schema.
	 * @param string $path Current path.
	 * @param array  &$result Validation result (passed by reference).
	 * @return void
	 */
	private static function validate_object_properties( array $data, array $schema, string $path, array &$result ): void {
		// Validate required properties.
		if ( isset( $schema['required'] ) && is_array( $schema['required'] ) ) {
			foreach ( $schema['required'] as $required_prop ) {
				if ( ! isset( $data[ $required_prop ] ) ) {
					$result['valid']    = false;
					$result['errors'][] = "Missing required property '{$required_prop}' at '{$path}'";
				}
			}
		}

		// Validate properties.
		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			foreach ( $data as $prop_name => $prop_value ) {
				if ( isset( $schema['properties'][ $prop_name ] ) ) {
					$prop_path = $path ? "{$path}.{$prop_name}" : $prop_name;
					self::validate_schema_recursive( $prop_value, $schema['properties'][ $prop_name ], $prop_path, $result );
				} elseif ( isset( $schema['additionalProperties'] ) && false === $schema['additionalProperties'] ) {
					$result['valid']    = false;
					$result['errors'][] = "Additional property '{$prop_name}' not allowed at '{$path}'";
				}
			}
		}
	}

	/**
	 * Validate array items.
	 *
	 * @param array  $data The array data.
	 * @param array  $schema The schema.
	 * @param string $path Current path.
	 * @param array  &$result Validation result (passed by reference).
	 * @return void
	 */
	private static function validate_array_items( array $data, array $schema, string $path, array &$result ): void {
		if ( ! isset( $schema['items'] ) ) {
			return;
		}

		foreach ( $data as $index => $item ) {
			$item_path = "{$path}[{$index}]";
			self::validate_schema_recursive( $item, $schema['items'], $item_path, $result );
		}

		// Validate array constraints.
		if ( isset( $schema['minItems'] ) && count( $data ) < $schema['minItems'] ) {
			$result['valid']    = false;
			$result['errors'][] = "Array at '{$path}' has too few items (minimum: {$schema['minItems']})";
		}

		if ( isset( $schema['maxItems'] ) && count( $data ) > $schema['maxItems'] ) {
			$result['valid']    = false;
			$result['errors'][] = "Array at '{$path}' has too many items (maximum: {$schema['maxItems']})";
		}
	}

	/**
	 * Validate string constraints.
	 *
	 * @param string $data The string data.
	 * @param array  $schema The schema.
	 * @param string $path Current path.
	 * @param array  &$result Validation result (passed by reference).
	 * @return void
	 */
	private static function validate_string_constraints( string $data, array $schema, string $path, array &$result ): void {
		// Length constraints.
		if ( isset( $schema['minLength'] ) && strlen( $data ) < $schema['minLength'] ) {
			$result['valid']    = false;
			$result['errors'][] = "String at '{$path}' is too short (minimum length: {$schema['minLength']})";
		}

		if ( isset( $schema['maxLength'] ) && strlen( $data ) > $schema['maxLength'] ) {
			$result['valid']    = false;
			$result['errors'][] = "String at '{$path}' is too long (maximum length: {$schema['maxLength']})";
		}

		// Pattern validation.
		if ( isset( $schema['pattern'] ) && ! preg_match( '/' . preg_quote( $schema['pattern'], '/' ) . '/', $data ) ) {
			$result['valid']    = false;
			$result['errors'][] = "String at '{$path}' does not match pattern '{$schema['pattern']}'";
		}
	}

	/**
	 * Validate numeric constraints.
	 *
	 * @param float  $data The numeric data.
	 * @param array  $schema The schema.
	 * @param string $path Current path.
	 * @param array  &$result Validation result (passed by reference).
	 * @return void
	 */
	private static function validate_numeric_constraints( float $data, array $schema, string $path, array &$result ): void {
		if ( isset( $schema['minimum'] ) && $data < $schema['minimum'] ) {
			$result['valid']    = false;
			$result['errors'][] = "Number at '{$path}' is below minimum ({$schema['minimum']})";
		}

		if ( isset( $schema['maximum'] ) && $data > $schema['maximum'] ) {
			$result['valid']    = false;
			$result['errors'][] = "Number at '{$path}' exceeds maximum ({$schema['maximum']})";
		}

		if ( isset( $schema['multipleOf'] ) && fmod( $data, $schema['multipleOf'] ) !== 0.0 ) {
			$result['valid']    = false;
			$result['errors'][] = "Number at '{$path}' is not a multiple of {$schema['multipleOf']}";
		}
	}

	/**
	 * Validate enum values.
	 *
	 * @param mixed  $data The data to validate.
	 * @param array  $enum_values Allowed enum values.
	 * @param string $path Current path.
	 * @param array  &$result Validation result (passed by reference).
	 * @return void
	 */
	private static function validate_enum( $data, array $enum_values, string $path, array &$result ): void {
		if ( ! in_array( $data, $enum_values, true ) ) {
			$allowed            = implode( ', ', array_map( 'json_encode', $enum_values ) );
			$result['valid']    = false;
			$result['errors'][] = "Value at '{$path}' must be one of: {$allowed}";
		}
	}

	/**
	 * Validate oneOf constraint (exactly one schema must match).
	 *
	 * @param mixed  $data The data to validate.
	 * @param array  $schemas Array of schemas to validate against.
	 * @param string $path Current path.
	 * @param array  &$result Validation result (passed by reference).
	 * @return void
	 */
	private static function validate_one_of( $data, array $schemas, string $path, array &$result ): void {
		$valid_schemas = 0;
		$errors        = array();

		foreach ( $schemas as $index => $schema ) {
			$sub_result = array(
				'valid'  => true,
				'errors' => array(),
			);

			self::validate_schema_recursive( $data, $schema, $path, $sub_result );

			if ( $sub_result['valid'] ) {
				++$valid_schemas;
			} else {
				$errors[] = "Schema {$index}: " . implode( ', ', $sub_result['errors'] );
			}
		}

		if ( 1 !== $valid_schemas ) {
			$result['valid'] = false;
			if ( 0 === $valid_schemas ) {
				$result['errors'][] = "Value at '{$path}' does not match any of the expected schemas";
			} else {
				$result['errors'][] = "Value at '{$path}' matches multiple schemas in oneOf (should match exactly one)";
			}
		}
	}

	/**
	 * Validate anyOf constraint (one or more schemas must match).
	 *
	 * @param mixed  $data The data to validate.
	 * @param array  $schemas Array of schemas to validate against.
	 * @param string $path Current path.
	 * @param array  &$result Validation result (passed by reference).
	 * @return void
	 */
	private static function validate_any_of( $data, array $schemas, string $path, array &$result ): void {
		$valid_schemas = 0;
		$errors        = array();

		foreach ( $schemas as $index => $schema ) {
			$sub_result = array(
				'valid'  => true,
				'errors' => array(),
			);

			self::validate_schema_recursive( $data, $schema, $path, $sub_result );

			if ( $sub_result['valid'] ) {
				++$valid_schemas;
			} else {
				$errors[] = "Schema {$index}: " . implode( ', ', $sub_result['errors'] );
			}
		}

		if ( 0 === $valid_schemas ) {
			$result['valid']    = false;
			$result['errors'][] = "Value at '{$path}' does not match any of the expected schemas: " . implode( '; ', $errors );
		}
	}

	/**
	 * Get JSON type of a value.
	 *
	 * @param mixed $value The value to get type for.
	 * @return string The JSON type.
	 */
	private static function get_json_type( $value ): string {
		if ( is_null( $value ) ) {
			return 'null';
		}
		if ( is_bool( $value ) ) {
			return 'boolean';
		}
		if ( is_int( $value ) ) {
			return 'integer';
		}
		if ( is_float( $value ) ) {
			return 'number';
		}
		if ( is_string( $value ) ) {
			return 'string';
		}
		if ( is_array( $value ) ) {
			return self::is_list( $value ) ? 'array' : 'object';
		}

		return 'unknown';
	}

	/**
	 * Polyfill for array_is_list() for PHP 8.0 compatibility.
	 *
	 * @param array $array The array to check.
	 * @return bool True if the array is a list, false otherwise.
	 */
	private static function is_list( array $array ): bool {
		if ( function_exists( 'array_is_list' ) ) {
			return array_is_list( $array );
		}

		$index = 0;
		foreach ( $array as $key => $value ) {
			if ( $key !== $index++ ) {
				return false;
			}
		}

		return true;
	}
}
