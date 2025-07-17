<?php //phpcs:ignore
declare(strict_types=1);

namespace Automattic\WordpressMcp\Tools;

use Automattic\WordpressMcp\Core\RegisterMcpTool;

/**
 * Class McpPluginUpdateTools
 *
 * Tool for updating WordPress plugins.
 *
 * @package Automattic\WordpressMcp\Tools
 */
class McpPluginUpdateTools {

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct() {
        add_action( 'wordpress_mcp_init', array( $this, 'register_tool' ) );
    }

    /**
     * Register the tool.
     *
     * @return void
     */
    public function register_tool(): void {
        new RegisterMcpTool(
            array(
                'name'                => 'update_plugins',
                'description'         => 'Updates one or more WordPress plugins.',
                'inputSchema'         => array(
                    'type'       => 'object',
                    'properties' => array(
                        'plugin_slugs' => array(
                            'type'        => 'array',
                            'description' => 'An array of plugin slugs to update.',
                            'items'       => array(
                                'type' => 'string',
                            ),
                            'minItems'    => 1,
                        ),
                    ),
                    'required'   => array( 'plugin_slugs' ),
                ),
                'type'                => 'action',
                'callback'            => array( $this, 'update_plugins' ),
                'permission_callback' => array( $this, 'update_plugins_permission_callback' ),
            )
        );
    }

    /**
     * Permission callback for the update_plugins tool.
     *
     * @return bool True if the user has permission, false otherwise.
     */
    public function update_plugins_permission_callback(): bool {
        return current_user_can( 'update_plugins' );
    }

    /**
     * Update one or more plugins.
     *
     * @param array $args The arguments containing plugin_slugs.
     *
     * @return array The result of the update operation for each plugin.
     */
    public function update_plugins( array $args ): array {
        // Start output buffering to prevent any HTML output
        ob_start();
        
        try {
            $results = array();

            if ( ! isset( $args['plugin_slugs'] ) || empty( $args['plugin_slugs'] ) ) {
                ob_end_clean();
                return array(
                    'status'  => 'failed',
                    'message' => 'No plugin slugs provided for update.',
                );
            }

            $plugin_slugs = (array) $args['plugin_slugs'];

            // Ensure necessary WordPress files are loaded.
            if ( ! function_exists( 'get_plugins' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            
            // Load required files for plugin updates
            if ( ! class_exists( 'WP_Upgrader' ) ) {
                require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            }
            if ( ! function_exists( 'plugins_api' ) ) {
                require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            }
            if ( ! function_exists( 'request_filesystem_credentials' ) ) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }
            if ( ! function_exists( 'wp_tempnam' ) ) {
                require_once ABSPATH . 'wp-admin/includes/misc.php';
            }
            if ( ! class_exists( 'WP_Upgrader_Skin' ) ) {
                require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';
            }
            
            if ( ! class_exists( 'Plugin_Upgrader' ) ) {
                ob_end_clean();
                return array(
                    'status'  => 'failed',
                    'message' => 'Plugin_Upgrader class not available.',
                );
            }

        $all_plugins = get_plugins();

        foreach ( $plugin_slugs as $slug ) {
            $plugin_path = '';
            
            // Try multiple ways to find the plugin
            foreach ( $all_plugins as $path => $data ) {
                // Check if path starts with slug
                if ( strpos( $path, $slug . '/' ) === 0 ) {
                    $plugin_path = $path;
                    break;
                }
                // Check text domain
                if ( isset( $data['TextDomain'] ) && $data['TextDomain'] === $slug ) {
                    $plugin_path = $path;
                    break;
                }
                // Check plugin name (case insensitive)
                if ( isset( $data['Name'] ) && stripos( $data['Name'], str_replace( '-', ' ', $slug ) ) !== false ) {
                    $plugin_path = $path;
                    break;
                }
                // Check for common plugin name variations
                if ( $slug === 'wp-mail-smtp' && strpos( $path, 'wp-mail-smtp' ) !== false ) {
                    $plugin_path = $path;
                    break;
                }
            }

            if ( empty( $plugin_path ) ) {
                $results[] = array(
                    'plugin_slug' => $slug,
                    'status'      => 'not_found',
                    'message'     => 'Plugin not found: ' . $slug . '. Available plugins: ' . implode( ', ', array_keys( $all_plugins ) ),
                );
                continue;
            }

            // Force refresh update transients to get latest data
            wp_update_plugins();
            
            // Check for updates before attempting to update.
            $update_plugins = get_site_transient( 'update_plugins' );
            $update_available = false;
            $update_info = null;
            if ( $update_plugins && isset( $update_plugins->response[ $plugin_path ] ) ) {
                $update_available = true;
                $update_info = $update_plugins->response[ $plugin_path ];
            }

            if ( ! $update_available ) {
                $results[] = array(
                    'plugin_slug' => $slug,
                    'status'      => 'no_update_available',
                    'message'     => 'No update available for plugin: ' . $slug,
                );
                continue;
            }

            // Get current version before update
            $current_version = $all_plugins[ $plugin_path ]['Version'] ?? 'unknown';
            $new_version = $update_info->new_version ?? 'unknown';
            
            // Check if plugin is currently active
            $was_active = is_plugin_active( $plugin_path );

            // Create a silent upgrader skin to prevent UI function calls
            $skin = new class() extends \WP_Upgrader_Skin {
                public function header() {}
                public function footer() {}
                public function error( $error ) {}
                public function feedback( $feedback, ...$args ) {}
                public function before() {}
                public function after() {}
                public function set_result( $result ) {}
                public function request_filesystem_credentials( $error = false, $context = '', $allow_relaxed_file_ownership = false ) {
                    return true;
                }
            };
            
            // Attempt to update the plugin using Plugin_Upgrader.
            $upgrader = new \Plugin_Upgrader( $skin );
            $update_result = $upgrader->upgrade( $plugin_path );

            if ( is_wp_error( $update_result ) ) {
                $results[] = array(
                    'plugin_slug' => $slug,
                    'status'      => 'failed',
                    'message'     => $update_result->get_error_message(),
                );
            } elseif ( $update_result === false ) {
                $results[] = array(
                    'plugin_slug' => $slug,
                    'status'      => 'failed',
                    'message'     => 'Plugin update failed: ' . $slug,
                );
            } else {
                // Reactivate plugin if it was active before update
                if ( $was_active ) {
                    activate_plugin( $plugin_path );
                }
                
                $results[] = array(
                    'plugin_slug' => $slug,
                    'status'      => 'success',
                    'message'     => 'Plugin updated successfully: ' . $slug . ' (from v' . $current_version . ' to v' . $new_version . ')',
                    'old_version' => $current_version,
                    'new_version' => $new_version,
                    'reactivated' => $was_active,
                );
            }
        }

            ob_end_clean();
            return $results;
        } catch ( \Exception $e ) {
            ob_end_clean();
            return array(
                'status'  => 'failed',
                'message' => 'Plugin update failed with error: ' . $e->getMessage(),
            );
        } catch ( \Error $e ) {
            ob_end_clean();
            return array(
                'status'  => 'failed',
                'message' => 'Plugin update failed with fatal error: ' . $e->getMessage(),
            );
        }
    }
}