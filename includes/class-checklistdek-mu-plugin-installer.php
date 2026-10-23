<?php
/**
 * Deploy the client checklist panel as a must-use plugin on remote sites.
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installs and updates the client panel mu-plugin via Application Passwords.
 */
class CHECKLISTDEK_Mu_Plugin_Installer {

	const BUNDLE_DIR = 'mu-plugin';

	const INSTALL_ROUTE = '/checklistdek/v1/client/install';

	const INFO_ROUTE = '/checklistdek/v1/client/info';

	/**
	 * Ensure the client panel mu-plugin is present on a remote site.
	 *
	 * @param int $site_id Site ID.
	 * @return array|WP_Error
	 */
	public static function ensure_installed( $site_id ) {
		$client = CHECKLISTDEK_Remote_Client::from_site( $site_id );

		if ( ! $client ) {
			return new WP_Error(
				'checklistdek_no_client',
				__( 'Unable to connect to remote site.', 'checklistdek' )
			);
		}

		$files = self::get_bundle_files();

		if ( self::panel_available( $client ) ) {
			$sync = self::sync_bundle( $client, $files );

			CHECKLISTDEK_Site_Repository::update(
				$site_id,
				array(
					'client_agent' => 1,
				)
			);

			if ( is_wp_error( $sync ) ) {
				return array(
					'success' => true,
					'message' => __( 'Client checklist panel is installed, but the latest panel files could not be synced.', 'checklistdek' ),
					'method'  => 'existing',
					'sync'    => $sync->get_error_message(),
				);
			}

			return array(
				'success' => true,
				'message' => __( 'Client checklist panel updated.', 'checklistdek' ),
				'method'  => 'sync',
				'written' => $sync['written'] ?? array(),
			);
		}

		if ( empty( $files ) ) {
			return new WP_Error(
				'checklistdek_mu_bundle_missing',
				__( 'Client panel bundle files are missing on the hub.', 'checklistdek' )
			);
		}

		$local = self::install_via_local_filesystem( $client, $files );

		if ( ! is_wp_error( $local ) && self::panel_available( $client ) ) {
			CHECKLISTDEK_Site_Repository::update(
				$site_id,
				array(
					'client_agent' => 1,
				)
			);

			return $local;
		}

		$remote = self::install_via_rest( $client, $files );

		if ( is_wp_error( $remote ) ) {
			CHECKLISTDEK_Site_Repository::update(
				$site_id,
				array(
					'client_agent' => 0,
				)
			);

			if ( is_wp_error( $local ) ) {
				return new WP_Error(
					'checklistdek_mu_bootstrap_required',
					__( 'Client panel bootstrap required. Download checklistdek-client.php from onboarding or ChecklistDek → Sites, upload it to wp-content/mu-plugins/ on the client site, then click Retry panel install.', 'checklistdek' ),
					array(
						'status' => 404,
						'remote' => $remote->get_error_message(),
					)
				);
			}

			return $local;
		}

		if ( ! self::panel_available( $client ) ) {
			return new WP_Error(
				'checklistdek_mu_install_verify_failed',
				__( 'Client panel files were written but the panel is still unavailable.', 'checklistdek' )
			);
		}

		CHECKLISTDEK_Site_Repository::update(
			$site_id,
			array(
				'client_agent' => 1,
			)
		);

		return $remote;
	}

	/**
	 * Whether the remote site exposes the client panel.
	 *
	 * @param CHECKLISTDEK_Remote_Client $client Remote client.
	 * @return bool
	 */
	public static function panel_available( $client ) {
		$result = $client->rest( 'GET', self::INFO_ROUTE );

		return ! is_wp_error( $result ) && ! empty( $result['body']['panel'] );
	}

	/**
	 * Sync the bundled panel files to a site that already has the panel installed.
	 *
	 * @param CHECKLISTDEK_Remote_Client $client Remote client.
	 * @param array                   $files  Relative path => contents.
	 * @return array|WP_Error
	 */
	protected static function sync_bundle( $client, $files ) {
		if ( empty( $files ) ) {
			return new WP_Error(
				'checklistdek_mu_bundle_missing',
				__( 'Client panel bundle files are missing on the hub.', 'checklistdek' )
			);
		}

		$local = self::install_via_local_filesystem( $client, $files );

		if ( ! is_wp_error( $local ) ) {
			return $local;
		}

		return self::install_via_rest( $client, $files );
	}

	/**
	 * Push bundle files through the client install REST route.
	 *
	 * @param CHECKLISTDEK_Remote_Client $client Remote client.
	 * @param array                   $files  Relative path => contents.
	 * @return array|WP_Error
	 */
	protected static function install_via_rest( $client, $files ) {
		$result = $client->rest(
			'POST',
			self::INSTALL_ROUTE,
			array(
				'files' => $files,
			)
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success' => true,
			'message' => __( 'Client checklist panel installed via REST.', 'checklistdek' ),
			'method'  => 'rest',
			'written' => $result['body']['written'] ?? array(),
		);
	}

	/**
	 * Write bundle files directly when the client site shares this server filesystem.
	 *
	 * @param CHECKLISTDEK_Remote_Client $client Remote client.
	 * @param array                   $files  Relative path => contents.
	 * @return array|WP_Error
	 */
	protected static function install_via_local_filesystem( $client, $files ) {
		$root = self::resolve_local_wp_root( $client );

		if ( ! $root ) {
			return new WP_Error(
				'checklistdek_mu_local_path_unresolved',
				__( 'Could not resolve a local path for the client site. Install the panel via REST on first connect.', 'checklistdek' ),
				array( 'status' => 404 )
			);
		}

		$mu_dir = trailingslashit( $root ) . 'wp-content/mu-plugins';

		if ( ! wp_mkdir_p( $mu_dir ) ) {
			return new WP_Error(
				'checklistdek_mu_local_mkdir_failed',
				__( 'Could not create the client mu-plugins directory.', 'checklistdek' )
			);
		}

		$written = array();

		foreach ( $files as $relative => $content ) {
			$relative = ltrim( str_replace( '\\', '/', (string) $relative ), '/' );
			$target   = trailingslashit( $mu_dir ) . $relative;
			$dir      = dirname( $target );

			if ( ! wp_mkdir_p( $dir ) ) {
				return new WP_Error(
					'checklistdek_mu_local_write_failed',
					__( 'Could not create a client panel directory.', 'checklistdek' )
				);
			}

			if ( false === file_put_contents( $target, (string) $content ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				return new WP_Error(
					'checklistdek_mu_local_write_failed',
					__( 'Could not write client panel files locally.', 'checklistdek' )
				);
			}

			$written[] = $relative;
		}

		return array(
			'success' => true,
			'message' => __( 'Client checklist panel installed locally on this server.', 'checklistdek' ),
			'method'  => 'local',
			'written' => $written,
		);
	}

	/**
	 * Resolve a local WordPress root path from a remote site URL.
	 *
	 * @param CHECKLISTDEK_Remote_Client $client Remote client.
	 * @return string|null
	 */
	protected static function resolve_local_wp_root( $client ) {
		$url = method_exists( $client, 'get_url' ) ? $client->get_url() : '';

		if ( ! $url ) {
			return null;
		}

		$parsed = wp_parse_url( untrailingslashit( $url ) );
		$path   = $parsed['path'] ?? '';
		$host   = strtolower( $parsed['host'] ?? '' );

		$candidates = array();

		if ( in_array( $host, array( 'localhost', '127.0.0.1' ), true ) ) {
			$candidates[] = '/Applications/MAMP/htdocs' . $path;
			$candidates[] = rtrim( ABSPATH, '/\\' ) . '/../' . basename( $path );
		}

		$hub_host = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( $hub_host && $host && strtolower( (string) $hub_host ) === $host ) {
			$doc_root = realpath( ABSPATH . '../..' );
			if ( $doc_root ) {
				$candidates[] = wp_normalize_path( $doc_root . $path );
			}
		}

		foreach ( $candidates as $candidate ) {
			$candidate = wp_normalize_path( $candidate );
			if ( file_exists( $candidate . '/wp-config.php' ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * Read bundled mu-plugin files from the hub plugin directory.
	 *
	 * @return array
	 */
	public static function get_bundle_files() {
		$base = trailingslashit( CHECKLISTDEK_PLUGIN_DIR . self::BUNDLE_DIR );
		$map  = array(
			'checklistdek-client.php',
			'checklistdek-client/index.php',
			'checklistdek-client/class-run-store.php',
			'checklistdek-client/class-hub-client.php',
			'checklistdek-client/class-auto-capture.php',
			'checklistdek-client/class-rest-api.php',
			'checklistdek-client/class-panel.php',
			'checklistdek-client/css/checklistdek-client-admin.css',
			'checklistdek-client/css/index.php',
			'checklistdek-client/js/checklistdek-client-admin.js',
			'checklistdek-client/js/index.php',
		);

		$files = array();

		foreach ( $map as $relative ) {
			$path = $base . $relative;
			if ( ! is_readable( $path ) ) {
				continue;
			}
			$files[ $relative ] = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}

		return $files;
	}
}
