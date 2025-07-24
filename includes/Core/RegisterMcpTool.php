<?php //phpcs:ignore
declare(strict_types=1);

namespace Automattic\WordpressMcp\Core;

use InvalidArgumentException;

/**
 * Register an MCP tool.
 */
class RegisterMcpTool {

	/**
	 * The arguments.
	 *
	 * @var array
	 */
	private array $args;

	/**
	 * Constructor.
	 *
	 * @param array $args The arguments to register the MCP tool.
	 * @throws InvalidArgumentException|\RuntimeException When the arguments are invalid or tool is registered outside of wordpress_mcp_init action.
	 */
	public function __construct( array $args ) {
		if ( ! doing_action( 'wordpress_mcp_init' ) ) {
			throw new \RuntimeException( 'RegisterMcpTool can only be used within the wordpress_mcp_init action.' );
		}

		$this->args = $args;

		// Backward compatibility for permissions_callback.
		if ( isset( $this->args['permissions_callback'] ) ) {
			$this->args['permission_callback'] = $this->args['permissions_callback'];
			unset( $this->args['permissions_callback'] );
		}
		$this->validate_arguments();
		$this->register_tool();
	}

	/**
	 * Register the tool.
	 *
	 * @return void
	 */
	private function register_tool(): void {
		if ( ! empty( $this->args['rest_alias'] ) ) {
			$this->get_args_from_rest_api();
		} else {
			WPMCP()->register_tool( $this->args );
		}
	}

	/**
	 * Get the arguments from the rest api.
	 *
	 * @return void
	 * @throws InvalidArgumentException When the REST API route or method is invalid.
	 */
	private function get_args_from_rest_api(): void {
		$method = $this->args['rest_alias']['method'];
		$route  = $this->args['rest_alias']['route'];

		// get a list of all registered rest routes.
		$routes     = rest_get_server()->get_routes();
		$rest_route = $routes[ $route ] ?? null;
		if ( ! $rest_route ) {
			McpErrorHandler::log_error( 'The route does not exist: ' . $route . ' ' . $method . ' Skipping registration.' );
			// Skip registration if the route doesn't exist.
			return;
		}

		$rest_api = null;

		// the subarray should contain the method.
		foreach ( $rest_route as $endpoint ) {
			if ( isset( $endpoint['methods'][ $method ] ) && true === $endpoint['methods'][ $method ] ) {
				$rest_api = $endpoint;
				break;
			}
		}
		if ( ! $rest_api ) {
			McpErrorHandler::log_error( 'The method does not exist: ' . $method . ' in ' . $route . ' Skipping registration.' );
			return;
		}

		// Convert REST API args to MCP input schema.
		$input_schema = array(
			'type'       => 'object',
			'properties' => array(),
			'required'   => array(),
		);

		foreach ( $rest_api['args'] as $arg_name => $arg_schema ) {
			if ( ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', $arg_name ) ) {
				// log the invalid parameter name.
				McpErrorHandler::log_error( 'Invalid parameter name: ' . $arg_name . ' in ' . $route . ' ' . $method . '. The parameter was skipped.' );
				continue; // Skip invalid parameter names.
			}

			$type = $arg_schema['type'];
			if ( is_array( $type ) ) {
				$type = reset( $type );
			}
			$input_schema['properties'][ $arg_name ] = array(
				'type'        => $type,
				'description' => $arg_schema['description'],
			);

			// Handle array items if present.
			if ( isset( $arg_schema['items'] ) ) {
				$input_schema['properties'][ $arg_name ]['items'] = $arg_schema['items'];
			}

			// Handle enums if present and remove duplicates.
			if ( isset( $arg_schema['enum'] ) ) {
				$input_schema['properties'][ $arg_name ]['enum'] = array_values( array_unique( $arg_schema['enum'], SORT_REGULAR ) );
			}

			// Handle default values if present.
			if ( isset( $arg_schema['default'] ) && ! empty( $arg_schema['default'] ) ) {
				$input_schema['properties'][ $arg_name ]['default'] = $arg_schema['default'];
			}

			// Handle format if present.
			if ( isset( $arg_schema['format'] ) ) {
				$input_schema['properties'][ $arg_name ]['format'] = $arg_schema['format'];
			}

			// Handle minimum/maximum if present.
			if ( isset( $arg_schema['minimum'] ) ) {
				$input_schema['properties'][ $arg_name ]['minimum'] = $arg_schema['minimum'];
			}
			if ( isset( $arg_schema['maximum'] ) ) {
				$input_schema['properties'][ $arg_name ]['maximum'] = $arg_schema['maximum'];
			}

			// If the parameter has no default value and is not explicitly optional, mark it as required.
			if ( isset( $arg_schema['required'] ) && true === $arg_schema['required'] ) {
				$input_schema['required'][] = $arg_name;
			}
		}

		// Convert required array to object.
		if ( empty( $input_schema['properties'] ) ) {
			unset( $input_schema['properties'] );
		}
		if ( empty( $input_schema['required'] ) ) {
			unset( $input_schema['required'] );
		}

		// Apply modifications if provided in rest_alias['modifications'] .
		if ( isset( $this->args['rest_alias']['inputSchemaReplacements'] ) ) {
			$modifications = $this->args['rest_alias']['inputSchemaReplacements'];
			$input_schema  = $this->apply_modifications( $input_schema, $modifications );

			// Ensure required field is always an array if it exists.
			if ( isset( $input_schema['required'] ) && ! is_array( $input_schema['required'] ) ) {
				// Convert to array if it's not already.
				if ( is_object( $input_schema['required'] ) ) {
					$input_schema['required'] = array_values( (array) $input_schema['required'] );
				} else {
					$input_schema['required'] = array();
				}
			}
		}

		// Update the args with the converted schema.
		$this->args['inputSchema']         = $input_schema;
		$this->args['callback']            = $rest_api['callback'];
		$this->args['permission_callback'] = $rest_api['permission_callback'];

		// Register the tool with the converted schema.
		WPMCP()->register_tool( $this->args );
	}

	/**
	 * Validate the arguments.
	 *
	 * @return void
	 * @throws InvalidArgumentException When the arguments are invalid.
	 */
	private function validate_arguments(): void {
		// name is required.
		if ( ! isset( $this->args['name'] ) ) {
			throw new InvalidArgumentException( 'The name is required.' );
		}

		// validate the name: must be a string and between 1 and 64 characters.
		if ( ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', $this->args['name'] ) ) {
			throw new InvalidArgumentException( 'The name must be a string between 1 and 64 characters.' );
		}

		// description is required.
		if ( ! isset( $this->args['description'] ) ) {
			throw new InvalidArgumentException( 'The description is required.' );
		}

		// functionality_type is required.
		if ( ! isset( $this->args['type'] ) ) {
			throw new InvalidArgumentException( 'The functionality type is required.' );
		}

		// validate functionality type: must be one of 'create', 'read', 'update', 'delete', 'action'.
		$valid_types = array( 'create', 'read', 'update', 'delete', 'action' );
		if ( ! in_array( $this->args['type'], $valid_types, true ) ) {
			throw new InvalidArgumentException( 'The functionality type must be one of: ' . esc_html( implode( ', ', $valid_types ) ) );
		}

		// if rest_alias is provided, the rest of the arguments are not required.
		if ( isset( $this->args['rest_alias'] ) ) {
			$this->validate_rest_alias();
			return;
		}

		// callback is required.
		if ( ! isset( $this->args['callback'] ) ) {
			throw new InvalidArgumentException( 'The callback is required.' );
		}

		// callback must be callable.
		if ( ! is_callable( $this->args['callback'] ) ) {
			throw new InvalidArgumentException( 'The callback must be a callable.' );
		}

		// permission_callback must be callable.
		if ( empty( $this->args['permission_callback'] ) ) {
			throw new InvalidArgumentException( 'The permission callback is required.' );
		}

		// permission_callback must be callable.
		if ( ! is_callable( $this->args['permission_callback'] ) ) {
			throw new InvalidArgumentException( 'The permission callback must be a callable.' );
		}

		// validate the input schema.
		$this->validate_input_schema();
	}

	/**
	 * Validate the rest api alias.
	 *
	 * @return void
	 * @throws InvalidArgumentException When the rest api alias is invalid.
	 */
	private function validate_rest_alias(): void {
		// route is required.
		if ( ! isset( $this->args['rest_alias']['route'] ) ) {
			throw new InvalidArgumentException( 'The route is required.' );
		}

		// method is required.
		if ( ! isset( $this->args['rest_alias']['method'] ) ) {
			throw new InvalidArgumentException( 'The method is required.' );
		}

		// validate the method: must be one of the following: GET, POST, PUT, PATCH, DELETE.
		if ( ! in_array( $this->args['rest_alias']['method'], array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
			throw new InvalidArgumentException( 'The method must be one of the following: GET, POST, PUT, PATCH, DELETE.' );
		}
	}

	/**
	 * Validate the input schema.
	 *
	 * @return void
	 * @throws InvalidArgumentException When the input schema is invalid.
	 */
	private function validate_input_schema(): void {
		// Check if the input schema is provided.
		if ( empty( $this->args['inputSchema'] ) ) {
			throw new InvalidArgumentException( 'The input schema is required.' );
		}

		// Validate that the input schema is a valid JSON Schema object.
		if ( ! isset( $this->args['inputSchema']['type'] ) || 'object' !== $this->args['inputSchema']['type'] ) {
			throw new InvalidArgumentException( esc_html__( 'The input schema must be an object type.', 'wordpress-mcp' ) );
		}

		// Validate properties field if present (it's optional according to MCP spec).
		if ( isset( $this->args['inputSchema']['properties'] ) ) {
			if ( ! is_array( $this->args['inputSchema']['properties'] ) && ! is_object( $this->args['inputSchema']['properties'] ) ) {
				throw new InvalidArgumentException( esc_html__( 'The input schema properties field must be an array or an object.', 'wordpress-mcp' ) );
			}

			// Validate each property has a type.
			foreach ( $this->args['inputSchema']['properties'] as $property_name => $property ) {
				// Validate property name format matches MCP requirements.
				if ( ! preg_match( '/^[a-zA-Z0-9_-]{1,64}$/', $property_name ) ) {
					// translators: %s: Property name.
					throw new InvalidArgumentException( sprintf( esc_html__( "Property name '%s' must match pattern '^[a-zA-Z0-9_-]{1,64}$'.", 'wordpress-mcp' ), esc_html( $property_name ) ) );
				}

				if ( ! isset( $property['type'] ) ) {
					// translators: %s: Property name.
					throw new InvalidArgumentException( sprintf( esc_html__( "Property '%s' must have a type field.", 'wordpress-mcp' ), esc_html( $property_name ) ) );
				}

				// Validate property type is a valid JSON Schema type.
				$valid_types = array( 'string', 'number', 'integer', 'boolean', 'array', 'object', 'null' );
				if ( ! in_array( $property['type'], $valid_types, true ) ) {
					// translators: 1: Property name, 2: Property type.
					throw new InvalidArgumentException( sprintf( esc_html__( "Property '%1\$s' has invalid type '%2\$s'.", 'wordpress-mcp' ), esc_html( $property_name ), esc_html( $property['type'] ) ) );
				}

				// If the type is array, validate that items field exists.
				if ( 'array' === $property['type'] && ! isset( $property['items'] ) ) {
					// translators: %s: Property name.
					throw new InvalidArgumentException( sprintf( esc_html__( "Array property '%s' must have an items field.", 'wordpress-mcp' ), esc_html( $property_name ) ) );
				}
			}
		}

		// Validate the required field if present (it's optional according to MCP spec).
		if ( isset( $this->args['inputSchema']['required'] ) ) {
			// Ensure required field is an array.
			if ( ! is_array( $this->args['inputSchema']['required'] ) ) {
				throw new InvalidArgumentException( esc_html__( 'The required field must be an array.', 'wordpress-mcp' ) );
			}

			// Validate required field values are non-empty strings.
			foreach ( $this->args['inputSchema']['required'] as $required_property ) {
				if ( ! is_string( $required_property ) || empty( $required_property ) ) {
					throw new InvalidArgumentException( esc_html__( 'Required field names must be non-empty strings.', 'wordpress-mcp' ) );
				}
			}

			// Check all required properties exist in properties (only if properties field exists).
			if ( isset( $this->args['inputSchema']['properties'] ) ) {
				foreach ( $this->args['inputSchema']['required'] as $required_property ) {
					if ( ! isset( $this->args['inputSchema']['properties'][ $required_property ] ) ) {
						// translators: %s: Required property.
						throw new InvalidArgumentException( sprintf( esc_html__( "Required property '%s' does not exist in properties.", 'wordpress-mcp' ), esc_html( $required_property ) ) );
					}
				}
			} else {
				// If properties doesn't exist but required does, that's an error.
				throw new InvalidArgumentException( esc_html__( 'Cannot have required fields without a properties definition.', 'wordpress-mcp' ) );
			}
		}
	}

	/**
	 * Recursively remove all null values from an array.
	 *
	 * @param array $data The array to clean.
	 * @return array The cleaned array.
	 */
	private function remove_null_recursive( array $data ): array {
		foreach ( $data as $key => &$value ) {
			if ( is_array( $value ) ) {
				$value = $this->remove_null_recursive( $value );
			} elseif ( is_null( $value ) ) {
				unset( $data[ $key ] );
			}
		}
		unset( $value ); // break reference.
		return $data;
	}

	/**
	 * Apply modifications to the input schema.
	 *
	 * @param array $input_schema The input schema.
	 * @param array $modifications The modifications to apply.
	 * @return array The modified input schema.
	 */
	private function apply_modifications( array $input_schema, array $modifications ): array {

		$result = array_replace_recursive( $input_schema, $modifications );

		// Ensure required field is always an array if it exists.
		if ( isset( $result['required'] ) && ! is_array( $result['required'] ) ) {
			$result['required'] = array();
		}

		return $this->remove_null_recursive( $result );
	}
}
