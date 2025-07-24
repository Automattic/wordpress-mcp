<?php //phpcs:ignore
declare(strict_types=1);

namespace Automattic\WordpressMcp\Resources;

use Automattic\WordpressMcp\Core\RegisterMcpResource;

/**
 * Class SiteSettingsResource
 *
 * Resource for retrieving information about WordPress site settings.
 * Provides detailed information about general settings, reading settings, discussion settings, etc.
 *
 * @package Automattic\WordpressMcp\Resources
 */
class McpSiteSettingsResource {

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'wordpress_mcp_init', array( $this, 'register_resource' ) );
	}

	/**
	 * Register the resource.
	 *
	 * @return void
	 */
	public function register_resource(): void {
		new RegisterMcpResource(
			array(
				'uri'         => 'WordPress://site-settings',
				'name'        => 'site-settings',
				'description' => 'Provides detailed information about WordPress site settings',
				'mimeType'    => 'application/json',
			),
			array( $this, 'get_site_settings' )
		);
	}

	/**
	 * Get information about WordPress site settings.
	 *
	 * @return array
	 */
	public function get_site_settings(): array {
		// Get general settings.
		$general_settings = $this->get_general_settings();

		// Get reading settings.
		$reading_settings = $this->get_reading_settings();

		// Get discussion settings.
		$discussion_settings = $this->get_discussion_settings();

		// Get media settings.
		$media_settings = $this->get_media_settings();

		// Get permalink settings.
		$permalink_settings = $this->get_permalink_settings();

		// Get privacy settings.
		$privacy_settings = $this->get_privacy_settings();

		// Get writing settings.
		$writing_settings = $this->get_writing_settings();

		// Get misc settings.
		$misc_settings = $this->get_misc_settings();

		return array(
			'general'    => $general_settings,
			'reading'    => $reading_settings,
			'discussion' => $discussion_settings,
			'media'      => $media_settings,
			'permalink'  => $permalink_settings,
			'privacy'    => $privacy_settings,
			'writing'    => $writing_settings,
			'misc'       => $misc_settings,
		);
	}

	/**
	 * Get general settings.
	 *
	 * @return array General settings.
	 */
	private function get_general_settings(): array {
		return array(
			'site_title'    => get_bloginfo( 'name' ),
			'site_tagline'  => get_bloginfo( 'description' ),
			'site_url'      => get_bloginfo( 'url' ),
			'admin_email'   => get_bloginfo( 'admin_email' ),
			'membership'    => get_option( 'users_can_register' ) ? 'Anyone can register' : 'Only administrators can add new users',
			'default_role'  => get_option( 'default_role' ),
			'site_language' => get_bloginfo( 'language' ),
			'timezone'      => wp_timezone_string(),
			'date_format'   => get_option( 'date_format' ),
			'time_format'   => get_option( 'time_format' ),
			'start_of_week' => get_option( 'start_of_week' ),
		);
	}

	/**
	 * Get reading settings.
	 *
	 * @return array Reading settings.
	 */
	private function get_reading_settings(): array {
		$show_on_front  = get_option( 'show_on_front' );
		$page_on_front  = get_option( 'page_on_front' );
		$page_for_posts = get_option( 'page_for_posts' );

		$front_page    = 'posts';
		$front_page_id = 0;
		$posts_page_id = 0;

		if ( 'page' === $show_on_front ) {
			$front_page    = 'page';
			$front_page_id = $page_on_front;
		}

		if ( $page_for_posts ) {
			$posts_page_id = $page_for_posts;
		}

		return array(
			'front_page_displays' => $front_page,
			'front_page_id'       => $front_page_id,
			'posts_page_id'       => $posts_page_id,
			'posts_per_page'      => get_option( 'posts_per_page' ),
			'posts_per_rss'       => get_option( 'posts_per_rss' ),
			'rss_use_excerpt'     => get_option( 'rss_use_excerpt' ),
		);
	}

	/**
	 * Get discussion settings.
	 *
	 * @return array Discussion settings.
	 */
	private function get_discussion_settings(): array {
		return array(
			'default_comment_status'       => get_option( 'default_comment_status' ),
			'default_ping_status'          => get_option( 'default_ping_status' ),
			'comment_moderation'           => get_option( 'comment_moderation' ),
			'require_name_email'           => get_option( 'require_name_email' ),
			'comment_registration'         => get_option( 'comment_registration' ),
			'close_comments_for_old_posts' => get_option( 'close_comments_for_old_posts' ),
			'close_comments_days_old'      => get_option( 'close_comments_days_old' ),
			'thread_comments'              => get_option( 'thread_comments' ),
			'thread_comments_depth'        => get_option( 'thread_comments_depth' ),
			'page_comments'                => get_option( 'page_comments' ),
			'comments_per_page'            => get_option( 'comments_per_page' ),
			'default_comments_page'        => get_option( 'default_comments_page' ),
			'comment_order'                => get_option( 'comment_order' ),
			'comments_notify'              => get_option( 'comments_notify' ),
			'moderation_notify'            => get_option( 'moderation_notify' ),
			'comment_previously_approved'  => get_option( 'comment_previously_approved' ),
			'comment_max_links'            => get_option( 'comment_max_links' ),
			'moderation_keys'              => get_option( 'moderation_keys' ),
			'disallowed_keys'              => get_option( 'disallowed_keys' ),
		);
	}

	/**
	 * Get media settings.
	 *
	 * @return array Media settings.
	 */
	private function get_media_settings(): array {
		return array(
			'thumbnail_size_w'              => get_option( 'thumbnail_size_w' ),
			'thumbnail_size_h'              => get_option( 'thumbnail_size_h' ),
			'thumbnail_crop'                => get_option( 'thumbnail_crop' ),
			'medium_size_w'                 => get_option( 'medium_size_w' ),
			'medium_size_h'                 => get_option( 'medium_size_h' ),
			'large_size_w'                  => get_option( 'large_size_w' ),
			'large_size_h'                  => get_option( 'large_size_h' ),
			'image_default_size'            => get_option( 'image_default_size' ),
			'image_default_align'           => get_option( 'image_default_align' ),
			'image_default_link_type'       => get_option( 'image_default_link_type' ),
			'uploads_use_yearmonth_folders' => get_option( 'uploads_use_yearmonth_folders' ),
		);
	}

	/**
	 * Get permalink settings.
	 *
	 * @return array Permalink settings.
	 */
	private function get_permalink_settings(): array {
		$permalink_structure = get_option( 'permalink_structure' );
		$category_base       = get_option( 'category_base' );
		$tag_base            = get_option( 'tag_base' );

		return array(
			'permalink_structure'      => $permalink_structure,
			'category_base'            => $category_base,
			'tag_base'                 => $tag_base,
			'permalink_structure_name' => $this->get_permalink_structure_name( $permalink_structure ),
		);
	}

	/**
	 * Get privacy settings.
	 *
	 * @return array Privacy settings.
	 */
	private function get_privacy_settings(): array {
		$privacy_policy_page_id = get_option( 'wp_page_for_privacy_policy' );

		return array(
			'privacy_policy_page_id'    => $privacy_policy_page_id,
			'privacy_policy_page_title' => $privacy_policy_page_id ? get_the_title( $privacy_policy_page_id ) : '',
			'blog_public'               => get_option( 'blog_public' ),
		);
	}

	/**
	 * Get writing settings.
	 *
	 * @return array Writing settings.
	 */
	private function get_writing_settings(): array {
		return array(
			'default_category'       => get_option( 'default_category' ),
			'default_email_category' => get_option( 'default_email_category' ),
			'default_link_category'  => get_option( 'default_link_category' ),
			'default_post_format'    => get_option( 'default_post_format' ),
			'post_format'            => get_option( 'post_format' ),
		);
	}

	/**
	 * Get miscellaneous settings.
	 *
	 * @return array Miscellaneous settings.
	 */
	private function get_misc_settings(): array {
		return array(
			'use_smilies'                               => get_option( 'use_smilies' ),
			'use_balanceTags'                           => get_option( 'use_balanceTags' ),
			'embed_autourls'                            => get_option( 'embed_autourls' ),
			'embed_media'                               => get_option( 'embed_media' ),
			'embed_shortcodes'                          => get_option( 'embed_shortcodes' ),
			'embed_embeds'                              => get_option( 'embed_embeds' ),
			'embed_cache'                               => get_option( 'embed_cache' ),
			'embed_cache_time'                          => get_option( 'embed_cache_time' ),
			'embed_autodiscovery'                       => get_option( 'embed_autodiscovery' ),
			'embed_autodiscovery_cache'                 => get_option( 'embed_autodiscovery_cache' ),
			'embed_autodiscovery_cache_time'            => get_option( 'embed_autodiscovery_cache_time' ),
			'embed_autodiscovery_cache_size'            => get_option( 'embed_autodiscovery_cache_size' ),
			'embed_autodiscovery_cache_expires'         => get_option( 'embed_autodiscovery_cache_expires' ),
			'embed_autodiscovery_cache_cleanup'         => get_option( 'embed_autodiscovery_cache_cleanup' ),
			'embed_autodiscovery_cache_cleanup_time'    => get_option( 'embed_autodiscovery_cache_cleanup_time' ),
			'embed_autodiscovery_cache_cleanup_size'    => get_option( 'embed_autodiscovery_cache_cleanup_size' ),
			'embed_autodiscovery_cache_cleanup_expires' => get_option( 'embed_autodiscovery_cache_cleanup_expires' ),
		);
	}

	/**
	 * Get the name of the permalink structure.
	 *
	 * @param string $permalink_structure The permalink structure.
	 *
	 * @return string The name of the permalink structure.
	 */
	private function get_permalink_structure_name( string $permalink_structure ): string {
		if ( empty( $permalink_structure ) ) {
			return 'Plain';
		}

		if ( '%postname%' === $permalink_structure ) {
			return 'Post name';
		}

		if ( '%post_id%' === $permalink_structure ) {
			return 'Numeric';
		}

		if ( '%category%' === $permalink_structure ) {
			return 'Category name';
		}

		if ( '%author%' === $permalink_structure ) {
			return 'Author name';
		}

		if ( strpos( $permalink_structure, '%postname%' ) !== false ) {
			return 'Custom Structure';
		}

		return 'Custom';
	}
}
