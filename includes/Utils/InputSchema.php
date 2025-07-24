<?php // phpcs:ignore

namespace Automattic\WordpressMcp\Utils;

/**
 * Clean the input schema.
 *
 * @param array $input_schema The input schema.
 * @return array The cleaned input schema.
 */
class InputSchema {

	/**
	 * Clean the input schema.
	 *
	 * @param array $input_schema The input schema.
	 * @return array The cleaned input schema.
	 */
	public static function clean( $input_schema ) {
		if ( ! is_array( $input_schema ) ) {
			return $input_schema;
		}

		// Handle properties if they exist.
		if ( isset( $input_schema['properties'] ) ) {
			foreach ( $input_schema['properties'] as $property_name => $property ) {
				if ( isset( $property['type'] ) ) {
					// If type is an array, take the first element.
					if ( is_array( $property['type'] ) ) {
						$input_schema['properties'][ $property_name ]['type'] = reset( $property['type'] );
					}
				}
			}
		}

		return $input_schema;
	}
}
