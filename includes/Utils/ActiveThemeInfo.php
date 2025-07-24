<?php //phpcs:ignore
declare(strict_types=1);

namespace Automattic\WordpressMcp\Utils;

/**
 * Class ActiveThemeInfo
 *
 * Utility class for retrieving information about WordPress themes.
 *
 * @package Automattic\WordpressMcp\Utils
 */
class ActiveThemeInfo {

	/**
	 * Get information about the active theme.
	 *
	 * @return array
	 */
	public static function get_theme_info(): array {
		$active_theme = wp_get_theme();
		$parent_theme = $active_theme->parent() ? wp_get_theme( $active_theme->get_template() ) : null;

		// Get theme update information.
		$theme_update_info = self::get_theme_update_info( $active_theme->get_stylesheet() );

		$theme_info = array(
			'active_theme' => array(
				'name'             => $active_theme->get( 'Name' ),
				'theme_uri'        => $active_theme->get( 'ThemeURI' ),
				'description'      => $active_theme->get( 'Description' ),
				'author'           => $active_theme->get( 'Author' ),
				'author_uri'       => $active_theme->get( 'AuthorURI' ),
				'version'          => $active_theme->get( 'Version' ),
				'license'          => $active_theme->get( 'License' ),
				'license_uri'      => $active_theme->get( 'LicenseURI' ),
				'text_domain'      => $active_theme->get( 'TextDomain' ),
				'domain_path'      => $active_theme->get( 'DomainPath' ),
				'requires_php'     => $active_theme->get( 'RequiresPHP' ),
				'requires_wp'      => $active_theme->get( 'RequiresWP' ),
				'status'           => $active_theme->get( 'Status' ),
				'tags'             => $active_theme->get( 'Tags' ),
				'template'         => $active_theme->get_template(),
				'stylesheet'       => $active_theme->get_stylesheet(),
				'screenshot'       => $active_theme->get_screenshot( 'relative' ),
				'update_available' => isset( $theme_update_info['update_available'] ) ? $theme_update_info['update_available'] : false,
				'latest_version'   => isset( $theme_update_info['latest_version'] ) ? $theme_update_info['latest_version'] : '',
				'last_updated'     => isset( $theme_update_info['last_updated'] ) ? $theme_update_info['last_updated'] : '',
			),
		);

		// Add parent theme information if it exists.
		if ( $parent_theme ) {
			$theme_info['parent_theme'] = array(
				'name'         => $parent_theme->get( 'Name' ),
				'theme_uri'    => $parent_theme->get( 'ThemeURI' ),
				'description'  => $parent_theme->get( 'Description' ),
				'author'       => $parent_theme->get( 'Author' ),
				'author_uri'   => $parent_theme->get( 'AuthorURI' ),
				'version'      => $parent_theme->get( 'Version' ),
				'license'      => $parent_theme->get( 'License' ),
				'license_uri'  => $parent_theme->get( 'LicenseURI' ),
				'text_domain'  => $parent_theme->get( 'TextDomain' ),
				'domain_path'  => $parent_theme->get( 'DomainPath' ),
				'requires_php' => $parent_theme->get( 'RequiresPHP' ),
				'requires_wp'  => $parent_theme->get( 'RequiresWP' ),
				'status'       => $parent_theme->get( 'Status' ),
				'tags'         => $parent_theme->get( 'Tags' ),
				'template'     => $parent_theme->get_template(),
				'stylesheet'   => $parent_theme->get_stylesheet(),
				'screenshot'   => $parent_theme->get_screenshot( 'relative' ),
			);
		}

		// Add theme support information.
		$theme_info['theme_supports'] = array(
			'post_thumbnails'      => current_theme_supports( 'post-thumbnails' ),
			'post_formats'         => current_theme_supports( 'post-formats' ),
			'custom_background'    => current_theme_supports( 'custom-background' ),
			'custom_header'        => current_theme_supports( 'custom-header' ),
			'custom_logo'          => current_theme_supports( 'custom-logo' ),
			'automatic_feed_links' => current_theme_supports( 'automatic-feed-links' ),
			'html5'                => array(
				'search-form',
				'comment-form',
				'comment-list',
				'gallery',
				'caption',
				'style',
				'script',
			),
		);

		// Add theme mods information.
		$theme_info['theme_mods'] = get_theme_mods();

		return $theme_info;
	}

	/**
	 * Get update information for a theme.
	 *
	 * @param string $theme_slug The theme slug.
	 *
	 * @return array Update information for the theme.
	 */
	private static function get_theme_update_info( string $theme_slug ): array {
		$update_info = array(
			'update_available' => false,
			'latest_version'   => '',
			'last_updated'     => '',
		);

		// Check if there are updates available.
		$update_themes = get_site_transient( 'update_themes' );

		if ( $update_themes && isset( $update_themes->response ) ) {
			foreach ( $update_themes->response as $theme_name => $theme_data ) {
				if ( $theme_name === $theme_slug ) {
					$update_info['update_available'] = true;
					$update_info['latest_version']   = $theme_data->new_version;
					$update_info['last_updated']     = isset( $theme_data->{'last-updated'} ) ? $theme_data->{'last-updated'} : '';
					break;
				}
			}
		}

		return $update_info;
	}
}
