<?php //phpcs:ignore
declare(strict_types=1);

namespace Automattic\WordpressMcp\Utils;

/**
 * Class PluginsInfo
 *
 * Utility class for retrieving and managing WordPress plugin information.
 * Provides detailed information about all plugins including active and inactive ones.
 *
 * @package Automattic\WordpressMcp\Utils
 */
class PluginsInfo {

	/**
	 * Get information about all plugins.
	 *
	 * @return array Array containing active and inactive plugins with their details.
	 */
	public function get_plugins_info(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins_data     = array();
		$all_plugins      = get_plugins();
		$active_plugins   = get_option( 'active_plugins' );
		$inactive_plugins = array_diff( array_keys( $all_plugins ), $active_plugins );

		foreach ( $all_plugins as $plugin_path => $plugin_data ) {
			$plugin_slug = explode( '/', (string) $plugin_path )[0];
			$plugin_info = $this->get_plugin_info( (string) $plugin_path );
			$update_info = $this->get_plugin_update_info( $plugin_slug );

			$plugins_data[] = array(
				'name'             => $plugin_info['Name'],
				'version'          => $plugin_info['Version'],
				'description'      => $plugin_info['Description'],
				'author'           => $plugin_info['Author'],
				'author_uri'       => $plugin_info['AuthorURI'],
				'plugin_uri'       => $plugin_info['PluginURI'],
				'text_domain'      => $plugin_info['TextDomain'],
				'domain_path'      => $plugin_info['DomainPath'],
				'network'          => $plugin_info['Network'],
				'requires_php'     => isset( $plugin_info['RequiresPHP'] ) ? $plugin_info['RequiresPHP'] : '',
				'requires_wp'      => isset( $plugin_info['RequiresWP'] ) ? $plugin_info['RequiresWP'] : '',
				'update_available' => $update_info['update_available'],
				'latest_version'   => $update_info['latest_version'],
				'last_updated'     => $update_info['last_updated'],
				'plugin_path'      => $plugin_path,
				'plugin_slug'      => $plugin_slug,
				'status'           => in_array( $plugin_path, $active_plugins, true ) ? 'active' : 'inactive',
			);
		}

		return array(
			'plugins'     => $plugins_data,
			'active'      => $active_plugins,
			'inactive'    => $inactive_plugins,
			'total_count' => count( $plugins_data ),
		);
	}

	/**
	 * Get the plugin info.
	 *
	 * @param string $plugin_path The plugin path.
	 *
	 * @return array The plugin info.
	 */
	public function get_plugin_info( string $plugin_path ): array {
		return get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_path );
	}

	/**
	 * Get update information for a plugin.
	 *
	 * @param string $plugin_slug The plugin slug.
	 *
	 * @return array Update information for the plugin.
	 */
	private function get_plugin_update_info( string $plugin_slug ): array {
		$update_info = array(
			'update_available' => false,
			'latest_version'   => '',
			'last_updated'     => '',
		);

		// Check if there are updates available.
		$update_plugins = get_site_transient( 'update_plugins' );

		if ( $update_plugins && isset( $update_plugins->response ) ) {
			foreach ( $update_plugins->response as $plugin_file => $plugin_data ) {
				if ( strpos( $plugin_file, $plugin_slug ) === 0 ) {
					$update_info['update_available'] = true;
					$update_info['latest_version']   = $plugin_data->new_version;
					$update_info['last_updated']     = isset( $plugin_data->last_updated ) ? $plugin_data->last_updated : '';
					break;
				}
			}
		}

		return $update_info;
	}
}
