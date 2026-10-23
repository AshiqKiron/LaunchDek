<?php
/**
 * Hub-initiated client panel file install (Pro hub builds only — omitted from Community zip).
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST route and file writer for remote panel bundle deployment.
 */
class CHECKLISTDEK_Client_REST_API_Install {

	/**
	 * Register the install route.
	 *
	 * @param string $namespace REST namespace.
	 * @return void
	 */
	public static function register_route( $namespace ) {
		register_rest_route(
			$namespace,
			'/client/install',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'install_files' ),
				'permission_callback' => array( 'CHECKLISTDEK_Client_REST_API', 'can_hub_manage' ),
			)
		);
	}

	/**
	 * Install or update mu-plugin files pushed from the hub.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function install_files( $request ) {
		$data  = $request->get_json_params();
		$files = is_array( $data ) && isset( $data['files'] ) && is_array( $data['files'] ) ? $data['files'] : array();

		if ( empty( $files ) ) {
			return new WP_Error(
				'checklistdek_client_no_files',
				__( 'No panel files were provided.', 'checklistdek' ),
				array( 'status' => 400 )
			);
		}

		$written = self::write_panel_files( $files );

		if ( is_wp_error( $written ) ) {
			return $written;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'written' => $written,
			)
		);
	}

	/**
	 * Write validated panel files into mu-plugins.
	 *
	 * @param array $files Relative path => file contents.
	 * @return array|WP_Error
	 */
	public static function write_panel_files( $files ) {
		if ( ! defined( 'WPMU_PLUGIN_DIR' ) ) {
			return new WP_Error(
				'checklistdek_client_no_mu_dir',
				__( 'Must-use plugins directory is not available.', 'checklistdek' ),
				array( 'status' => 500 )
			);
		}

		$allowed = array(
			'checklistdek-client.php',
			'checklistdek-client/index.php',
			'checklistdek-client/class-run-store.php',
			'checklistdek-client/class-hub-client.php',
			'checklistdek-client/class-auto-capture.php',
			'checklistdek-client/class-rest-api.php',
			'checklistdek-client/class-rest-api-install.php',
			'checklistdek-client/class-panel.php',
			'checklistdek-client/css/checklistdek-client-admin.css',
			'checklistdek-client/js/checklistdek-client-admin.js',
			'checklistdek-client/js/index.php',
			'checklistdek-client/css/index.php',
		);

		$written = array();

		foreach ( $files as $relative => $content ) {
			$relative = ltrim( str_replace( '\\', '/', (string) $relative ), '/' );

			if ( '' === $relative || false !== strpos( $relative, '..' ) ) {
				continue;
			}

			if ( ! in_array( $relative, $allowed, true ) ) {
				continue;
			}

			$target = trailingslashit( WPMU_PLUGIN_DIR ) . $relative;
			$dir    = dirname( $target );

			if ( ! wp_mkdir_p( $dir ) ) {
				return new WP_Error(
					'checklistdek_client_write_failed',
					__( 'Could not create the client panel directory.', 'checklistdek' ),
					array( 'status' => 500 )
				);
			}

			if ( false === file_put_contents( $target, (string) $content ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				return new WP_Error(
					'checklistdek_client_write_failed',
					__( 'Could not write the client panel files.', 'checklistdek' ),
					array( 'status' => 500 )
				);
			}

			$written[] = $relative;
		}

		if ( empty( $written ) ) {
			return new WP_Error(
				'checklistdek_client_no_valid_files',
				__( 'No valid panel files were written.', 'checklistdek' ),
				array( 'status' => 400 )
			);
		}

		return $written;
	}
}
