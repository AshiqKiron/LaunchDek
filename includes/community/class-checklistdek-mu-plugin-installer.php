<?php
/**
 * Community build — manual client panel setup only (no remote or local file writes).
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects the client panel on remote sites; does not write mu-plugin files.
 */
class CHECKLISTDEK_Mu_Plugin_Installer {

	const BUNDLE_DIR = 'mu-plugin';

	const INFO_ROUTE = '/checklistdek/v1/client/info';

	/**
	 * Verify the client panel is present on a remote site (no auto-install).
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

		if ( self::panel_available( $client ) ) {
			CHECKLISTDEK_Site_Repository::update(
				$site_id,
				array(
					'client_agent' => 1,
				)
			);

			return array(
				'success' => true,
				'message' => __( 'Client checklist panel is installed and ready.', 'checklistdek' ),
				'method'  => 'existing',
			);
		}

		CHECKLISTDEK_Site_Repository::update(
			$site_id,
			array(
				'client_agent' => 0,
			)
		);

		return new WP_Error(
			'checklistdek_mu_bootstrap_required',
			__( 'Client panel bootstrap required. Download checklistdek-client.php from onboarding or ChecklistDek → Sites, upload the full client panel to wp-content/mu-plugins/ on the client site, then click Retry panel install to verify.', 'checklistdek' ),
			array( 'status' => 404 )
		);
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
	 * Read bundled mu-plugin files from the hub plugin directory (bootstrap download / docs).
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
