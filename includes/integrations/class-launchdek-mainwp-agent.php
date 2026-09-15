<?php
/**
 * MainWP child-site agent deployment helper.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deploy LaunchDek client panel to MainWP child sites.
 */
class LAUNCHDEK_MainWP_Agent {

	const INSTALLER_SLUG = 'launchdek-client-bootstrap';

	/**
	 * Install the client panel on a MainWP child site.
	 *
	 * @param int $mainwp_site_id MainWP child site ID.
	 * @return array|WP_Error
	 */
	public static function install_panel( $mainwp_site_id ) {
		$website = self::get_website( $mainwp_site_id );

		if ( is_wp_error( $website ) ) {
			return $website;
		}

		if ( ! empty( $website->sync_errors ) ) {
			return new WP_Error(
				'launchdek_mainwp_site_disconnected',
				__( 'This MainWP child site is disconnected or has sync errors.', LAUNCHDEK_TEXT_DOMAIN )
			);
		}

		if ( ! class_exists( 'MainWP\Dashboard\MainWP_Connect' ) ) {
			return new WP_Error(
				'launchdek_mainwp_connect_missing',
				__( 'MainWP Connect is unavailable. Update MainWP and try again.', LAUNCHDEK_TEXT_DOMAIN )
			);
		}

		$zip = self::build_installer_zip();

		if ( is_wp_error( $zip ) ) {
			return $zip;
		}

		$staged = self::stage_zip_for_mainwp( $zip['path'], $zip['filename'] );

		if ( is_wp_error( $staged ) ) {
			@unlink( $zip['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return $staged;
		}

		$post_data = array(
			'type'           => 'plugin',
			'url'            => wp_json_encode( array( $staged['url'] ) ),
			'activatePlugin' => 'yes',
			'overwrite'      => true,
		);

		$information = \MainWP\Dashboard\MainWP_Connect::fetch_url_authed(
			$website,
			'installplugintheme',
			$post_data,
			false,
			true
		);

		@unlink( $zip['path'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		if ( is_array( $information ) && isset( $information['installation'] ) && 'SUCCESS' === $information['installation'] ) {
			return array(
				'success' => true,
				'message' => __( 'Client checklist panel installed via MainWP.', LAUNCHDEK_TEXT_DOMAIN ),
				'method'  => 'mainwp',
			);
		}

		$error = __( 'MainWP could not install the client panel on this child site.', LAUNCHDEK_TEXT_DOMAIN );

		if ( is_array( $information ) && ! empty( $information['error'] ) ) {
			$error = sanitize_text_field( (string) $information['error'] );
		}

		return new WP_Error(
			'launchdek_mainwp_install_failed',
			$error,
			array(
				'status' => 500,
			)
		);
	}

	/**
	 * Fetch a MainWP website object.
	 *
	 * @param int $mainwp_site_id MainWP child site ID.
	 * @return object|WP_Error
	 */
	public static function get_website( $mainwp_site_id ) {
		$mainwp_site_id = absint( $mainwp_site_id );

		if ( $mainwp_site_id <= 0 ) {
			return new WP_Error(
				'launchdek_mainwp_invalid_site',
				__( 'Invalid MainWP site ID.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => 400 )
			);
		}

		if ( class_exists( 'MainWP\Dashboard\MainWP_DB' ) ) {
			$website = \MainWP\Dashboard\MainWP_DB::instance()->get_website_by_id( $mainwp_site_id );

			if ( $website ) {
				return $website;
			}
		}

		return new WP_Error(
			'launchdek_mainwp_site_not_found',
			__( 'MainWP child site not found.', LAUNCHDEK_TEXT_DOMAIN ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Build a bootstrap plugin ZIP that copies the mu-plugin bundle on activation.
	 *
	 * @return array|WP_Error
	 */
	protected static function build_installer_zip() {
		$files = LAUNCHDEK_Mu_Plugin_Installer::get_bundle_files();

		if ( empty( $files ) ) {
			return new WP_Error(
				'launchdek_mu_bundle_missing',
				__( 'Client panel bundle files are missing on the hub.', LAUNCHDEK_TEXT_DOMAIN )
			);
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error(
				'launchdek_zip_unavailable',
				__( 'ZipArchive is required to deploy the client panel via MainWP.', LAUNCHDEK_TEXT_DOMAIN )
			);
		}

		$filename = self::INSTALLER_SLUG . '-' . LAUNCHDEK_VERSION . '.zip';
		$tmp_path = wp_tempnam( $filename );

		if ( ! $tmp_path ) {
			return new WP_Error(
				'launchdek_zip_temp_failed',
				__( 'Could not create a temporary install package.', LAUNCHDEK_TEXT_DOMAIN )
			);
		}

		@unlink( $tmp_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		$zip = new ZipArchive();

		if ( true !== $zip->open( $tmp_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error(
				'launchdek_zip_open_failed',
				__( 'Could not create the MainWP install package.', LAUNCHDEK_TEXT_DOMAIN )
			);
		}

		$installer_php = self::get_installer_plugin_source();
		$root          = self::INSTALLER_SLUG . '/';
		$zip->addFromString( $root . self::INSTALLER_SLUG . '.php', $installer_php );

		foreach ( $files as $relative => $content ) {
			$zip->addFromString( $root . 'bundle/' . ltrim( $relative, '/' ), (string) $content );
		}

		$zip->close();

		return array(
			'path'     => $tmp_path,
			'filename' => $filename,
		);
	}

	/**
	 * Bootstrap plugin source copied into the install ZIP.
	 *
	 * @return string
	 */
	protected static function get_installer_plugin_source() {
		return <<<'PHP'
<?php
/**
 * Plugin Name: LaunchDek Client Bootstrap
 * Description: Installs the LaunchDek client checklist panel into mu-plugins.
 * Version: 1.0.0
 * Author: LaunchDek
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

register_activation_hook( __FILE__, 'launchdek_client_bootstrap_activate' );

/**
 * Copy bundled client panel files into mu-plugins.
 *
 * @return void
 */
function launchdek_client_bootstrap_activate() {
	$source = __DIR__ . '/bundle';
	$dest   = WP_CONTENT_DIR . '/mu-plugins';

	if ( ! is_dir( $dest ) ) {
		wp_mkdir_p( $dest );
	}

	launchdek_client_bootstrap_copy_tree( $source, $dest );
	deactivate_plugins( plugin_basename( __FILE__ ) );
}

/**
 * Recursively copy files from source to destination.
 *
 * @param string $source Source directory.
 * @param string $dest   Destination directory.
 * @return void
 */
function launchdek_client_bootstrap_copy_tree( $source, $dest ) {
	if ( ! is_dir( $source ) ) {
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $item ) {
		$target = $dest . '/' . $iterator->getSubPathname();

		if ( $item->isDir() ) {
			wp_mkdir_p( $target );
			continue;
		}

		$dir = dirname( $target );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		copy( $item->getPathname(), $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
	}
}
PHP;
	}

	/**
	 * Stage the ZIP where MainWP child sites can download it.
	 *
	 * @param string $zip_path  Local ZIP path.
	 * @param string $filename  Destination filename.
	 * @return array|WP_Error
	 */
	protected static function stage_zip_for_mainwp( $zip_path, $filename ) {
		$filename = sanitize_file_name( $filename );

		if ( class_exists( 'MainWP\Dashboard\MainWP_System_Utility' ) ) {
			$dir = \MainWP\Dashboard\MainWP_System_Utility::get_mainwp_specific_dir( 'bulk' );

			if ( ! wp_mkdir_p( $dir ) ) {
				return new WP_Error(
					'launchdek_mainwp_stage_failed',
					__( 'Could not prepare the MainWP upload directory.', LAUNCHDEK_TEXT_DOMAIN )
				);
			}

			$target = trailingslashit( $dir ) . $filename;

			if ( ! copy( $zip_path, $target ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
				return new WP_Error(
					'launchdek_mainwp_stage_failed',
					__( 'Could not stage the client panel install package for MainWP.', LAUNCHDEK_TEXT_DOMAIN )
				);
			}

			return array(
				'url' => \MainWP\Dashboard\MainWP_System_Utility::get_download_url( 'bulk', $filename ),
			);
		}

		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error(
				'launchdek_mainwp_stage_failed',
				$uploads['error']
			);
		}

		$dir = trailingslashit( $uploads['basedir'] ) . 'launchdek-mainwp';

		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error(
				'launchdek_mainwp_stage_failed',
				__( 'Could not prepare a temporary upload directory.', LAUNCHDEK_TEXT_DOMAIN )
			);
		}

		$target = trailingslashit( $dir ) . $filename;

		if ( ! copy( $zip_path, $target ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_copy
			return new WP_Error(
				'launchdek_mainwp_stage_failed',
				__( 'Could not stage the client panel install package.', LAUNCHDEK_TEXT_DOMAIN )
			);
		}

		return array(
			'url' => trailingslashit( $uploads['baseurl'] ) . 'launchdek-mainwp/' . rawurlencode( $filename ),
		);
	}
}
