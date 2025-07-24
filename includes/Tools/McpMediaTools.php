<?php //phpcs:ignore
declare( strict_types=1 );

namespace Automattic\WordpressMcp\Tools;

use Automattic\WordpressMcp\Core\RegisterMcpTool;

/**
 * Class for managing MCP Media Tools functionality.
 */
class McpMediaTools {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wordpress_mcp_init', array( $this, 'register_tools' ) );
	}

	/**
	 * Register the tools.
	 */
	public function register_tools(): void {
		new RegisterMcpTool(
			array(
				'name'        => 'wp_list_media',
				'description' => 'List WordPress media items with pagination and filtering',
				'type'        => 'read',
				'rest_alias'  => array(
					'route'  => '/wp/v2/media',
					'method' => 'GET',
				),
				'annotations' => array(
					'title'         => 'List Media',
					'readOnlyHint'  => true,
					'openWorldHint' => false,
				),
			)
		);

		new RegisterMcpTool(
			array(
				'name'        => 'wp_get_media',
				'description' => 'Get a WordPress media item details by ID',
				'type'        => 'read',
				'rest_alias'  => array(
					'route'                   => '/wp/v2/media/(?P<id>[\d]+)',
					'method'                  => 'GET',
					'inputSchemaReplacements' => array(
						'required' => array(
							'id',
							'context',
						),
					),
				),
				'annotations' => array(
					'title'         => 'Get Media',
					'readOnlyHint'  => true,
					'openWorldHint' => false,
				),
			)
		);

		new RegisterMcpTool(
			array(
				'name'                => 'wp_get_media_file',
				'description'         => 'Get the actual file content (blob) of a WordPress media item',
				'type'                => 'read',
				'callback'            => array( $this, 'wp_get_media_file' ),
				'permission_callback' => array( $this, 'get_media_file_permission_callback' ),
				'inputSchema'         => array(
					'type'       => 'object',
					'properties' => array(
						'id'   => array(
							'type'        => 'integer',
							'description' => 'The ID of the media item',
						),
						'size' => array(
							'type'        => 'string',
							'description' => 'Optional. The size of the image to retrieve (thumbnail, medium, large, full). Defaults to full/original size.',
						),
					),
					'required'   => array(
						'id',
					),
				),
				'annotations'         => array(
					'title'         => 'Get Media File',
					'readOnlyHint'  => true,
					'openWorldHint' => false,
				),
			)
		);

		new RegisterMcpTool(
			array(
				'name'        => 'wp_upload_media',
				'description' => 'Upload a new media file to WordPress',
				'type'        => 'create',
				'rest_alias'  => array(
					'route'                   => '/wp/v2/media',
					'method'                  => 'POST',
					'inputSchemaReplacements' => array(
						'properties' => array(
							'file'        => array(
								'type'        => 'string',
								'description' => 'The file to upload (base64 encoded)',
							),
							'title'       => array(
								'type'        => 'string',
								'description' => 'The title of the media item',
							),
							'caption'     => array(
								'type'        => 'string',
								'description' => 'The caption of the media item',
							),
							'description' => array(
								'type'        => 'string',
								'description' => 'The description of the media item',
							),
							'alt_text'    => array(
								'type'        => 'string',
								'description' => 'The alt text for the media item',
							),
						),
						'required'   => array(
							'file',
						),
					),
					'preCallback'             => array( $this, 'wp_upload_media_pre_callback' ),
				),
				'annotations' => array(
					'title'           => 'Upload Media',
					'readOnlyHint'    => false,
					'destructiveHint' => false,
					'idempotentHint'  => false,
					'openWorldHint'   => false,
				),
			)
		);

		new RegisterMcpTool(
			array(
				'name'        => 'wp_update_media',
				'description' => 'Update a WordPress media item',
				'type'        => 'update',
				'rest_alias'  => array(
					'route'                   => '/wp/v2/media/(?P<id>[\d]+)',
					'method'                  => 'POST',
					'inputSchemaReplacements' => array(
						'properties' => array(
							'title'       => array(
								'type'        => 'string',
								'description' => 'The title of the media item',
							),
							'caption'     => array(
								'type'        => 'string',
								'description' => 'The caption of the media item',
							),
							'description' => array(
								'type'        => 'string',
								'description' => 'The description of the media item',
							),
							'alt_text'    => array(
								'type'        => 'string',
								'description' => 'The alt text for the media item',
							),
						),
					),
				),
				'annotations' => array(
					'title'           => 'Update Media',
					'readOnlyHint'    => false,
					'destructiveHint' => false,
					'idempotentHint'  => true,
					'openWorldHint'   => false,
				),
			)
		);

		new RegisterMcpTool(
			array(
				'name'        => 'wp_delete_media',
				'description' => 'Delete a WordPress media item permanently (requires force=true)',
				'type'        => 'delete',
				'rest_alias'  => array(
					'route'  => '/wp/v2/media/(?P<id>[\d]+)',
					'method' => 'DELETE',
				),
				'annotations' => array(
					'title'           => 'Delete Media',
					'readOnlyHint'    => false,
					'destructiveHint' => true,
					'idempotentHint'  => true,
					'openWorldHint'   => false,
				),
			)
		);

		new RegisterMcpTool(
			array(
				'name'        => 'wp_search_media',
				'description' => 'Search WordPress media items by title, caption, or description',
				'type'        => 'read',
				'rest_alias'  => array(
					'route'                   => '/wp/v2/media',
					'method'                  => 'GET',
					'inputSchemaReplacements' => array(
						'properties' => array(
							'search'     => array(
								'type'        => 'string',
								'description' => 'Search term to look for in media titles, captions, and descriptions',
							),
							'media_type' => array(
								'type'        => 'string',
								'description' => 'Filter by media type (image, video, audio, application)',
							),
							'mime_type'  => array(
								'type'        => 'string',
								'description' => 'Filter by MIME type (e.g., image/jpeg, video/mp4)',
							),
							'author'     => array(
								'type'        => 'integer',
								'description' => 'Filter by author ID',
							),
							'parent'     => array(
								'type'        => 'integer',
								'description' => 'Filter by parent post ID',
							),
						),
					),
				),
				'annotations' => array(
					'title'         => 'Search Media',
					'readOnlyHint'  => true,
					'openWorldHint' => false,
				),
			)
		);
	}

	/**
	 * Get the actual file content (blob) of a WordPress media item.
	 *
	 * @param array $params REST request parameters.
	 * @return array
	 */
	public function wp_get_media_file( $params ): array {
		$id     = isset( $params['id'] ) ? intval( $params['id'] ) : 0;
		$size   = isset( $params['size'] ) ? sanitize_text_field( $params['size'] ) : 'full';
		$format = isset( $params['format'] ) ? sanitize_text_field( $params['format'] ) : 'base64';

		if ( ! $id ) {
			return array(
				'error' => array(
					'code'    => -32000,
					'message' => 'Invalid media ID',
				),
			);
		}

		$file_path = get_attached_file( $id );
		if ( ! file_exists( $file_path ) ) {
			return array(
				'error' => array(
					'code'    => -32000,
					'message' => 'File not found',
				),
			);
		}

		if ( 'full' !== $size && 'original' !== $size ) {
			$meta = wp_get_attachment_metadata( $id );
			if ( isset( $meta['sizes'][ $size ]['file'] ) ) {
				$base_dir  = pathinfo( $file_path, PATHINFO_DIRNAME );
				$file_path = $base_dir . '/' . $meta['sizes'][ $size ]['file'];
			}
		}

		if ( ! file_exists( $file_path ) ) {
			return array(
				'error' => array(
					'code'    => -32000,
					'message' => 'Requested size not found',
				),
			);
		}

		$mime_type = get_post_mime_type( $id );

		$file_data = file_get_contents( $file_path ); // phpcs:ignore
		return array(
			'results'  => $file_data,
			'type'     => 'image',
			'mimeType' => $mime_type,
		);
	}

	/**
	 * Permissions callback for the wp_get_media_file tool.
	 *
	 * @return bool
	 */
	public function get_media_file_permission_callback(): bool {
		// todo: check if the user has permission to view the media file.
		return true;
	}

	/**
	 * Pre-callback for the wp_upload_media tool.
	 *
	 * @param array $args The arguments passed to the tool.
	 * @return array The processed parameters.
	 * @throws \Exception If there's an error processing the file.
	 */
	public function wp_upload_media_pre_callback( $args ): array {
		$params = array(
			'args' => $args,
		);
		if ( ! isset( $params['args']['file'] ) ) {
			return $params;
		}

		try {
			// Get the base64 data.
			$base64_data = $params['args']['file'];

			// Remove data URI prefix if present.
			if ( strpos( $base64_data, 'data:' ) === 0 ) {
				$base64_data = preg_replace( '/^data:.*?;base64,/', '', $base64_data );
			}

			// Decode the base64 data.
			$file_data = base64_decode( $base64_data, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( false === $file_data ) {
				throw new \Exception( 'Invalid base64 data.' );
			}

			// Determine the file type from the base64 data.
			$finfo     = finfo_open( FILEINFO_MIME_TYPE );
			$mime_type = finfo_buffer( $finfo, $file_data );
			finfo_close( $finfo );

			if ( empty( $mime_type ) ) {
				throw new \Exception( 'Could not determine file type.' );
			}

			// Generate a filename based on the title or use a default.
			$filename  = isset( $params['args']['title'] ) ? sanitize_file_name( $params['args']['title'] ) : 'upload';
			$filename .= '.' . $this->get_extension_from_mime_type( $mime_type );

			// Set up the headers for the REST API.
			$params['headers'] = array(
				'content_type'        => array( $mime_type ),
				'content_disposition' => array( 'attachment; filename="' . $filename . '"' ),
			);

			// Set the raw file data.
			$params['body'] = $file_data;

			// Remove the original file parameter to avoid confusion.
			unset( $params['args']['file'] );

			return $params;
		} catch ( \Exception $e ) {
			throw $e;
		}
	}

	/**
	 * Get file extension from MIME type.
	 *
	 * @param string $mime_type The MIME type.
	 * @return string The file extension.
	 * @throws \Exception If the MIME type is not supported.
	 */
	private function get_extension_from_mime_type( string $mime_type ): string {
		$mime_map = array(
			'image/jpeg'                    => 'jpg',
			'image/png'                     => 'png',
			'image/gif'                     => 'gif',
			'image/webp'                    => 'webp',
			'image/svg+xml'                 => 'svg',
			'image/svg'                     => 'svg',
			'application/pdf'               => 'pdf',
			'application/msword'            => 'doc',
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
			'application/vnd.ms-excel'      => 'xls',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
			'application/vnd.ms-powerpoint' => 'ppt',
			'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
			'text/plain'                    => 'txt',
			'text/csv'                      => 'csv',
			'text/html'                     => 'html',
			'text/xml'                      => 'xml',
			'application/json'              => 'json',
			'audio/mpeg'                    => 'mp3',
			'audio/wav'                     => 'wav',
			'audio/ogg'                     => 'ogg',
			'video/mp4'                     => 'mp4',
			'video/webm'                    => 'webm',
			'video/ogg'                     => 'ogv',
			'video/quicktime'               => 'mov',
			'video/x-msvideo'               => 'avi',
			'video/x-ms-wmv'                => 'wmv',
		);

		if ( ! isset( $mime_map[ $mime_type ] ) ) {
			throw new \Exception( 'File type not supported.' );
		}

		return $mime_map[ $mime_type ];
	}
}
