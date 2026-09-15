<?php
/**
 * WP Engine integration adapter.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Engine integration.
 */
class LAUNCHDEK_Integration_WP_Engine implements LAUNCHDEK_Integration_Interface {

	public function get_slug() {
		return 'wp-engine';
	}

	public function get_name() {
		return __( 'WP Engine Connector', LAUNCHDEK_TEXT_DOMAIN );
	}

	public function is_available() {
		return defined( 'WPE_PLUGIN_BASE' ) || class_exists( 'WpeCommon' ) || getenv( 'IS_WPE' );
	}

	public function supports_site_sync() {
		return false;
	}

	public function fetch_platform_sites() {
		return new WP_Error(
			'launchdek_integration_sync_unsupported',
			__( 'WP Engine site sync is not available yet.', LAUNCHDEK_TEXT_DOMAIN ),
			array( 'status' => 400 )
		);
	}

	public function push_agent( $site_ids = array() ) {
		$results = array();

		if ( $this->is_available() ) {
			$results['message'] = __( 'WP Engine environment detected. Use WP Engine portal or SSH gateway to deploy LaunchDek to staging/production sites.', LAUNCHDEK_TEXT_DOMAIN );
		} else {
			$results['message'] = __( 'Configure WP Engine API credentials in Settings to enable remote deployment.', LAUNCHDEK_TEXT_DOMAIN );
		}

		do_action( 'launchdek_wp_engine_push_agent', $site_ids, $results );

		LAUNCHDEK_Audit_Log::log( 'integration_push', array( 'integration' => 'wp-engine', 'results' => $results ) );

		return $results;
	}

	public function get_status() {
		return array(
			'description' => __( 'Allows WP Engine to push LaunchDek to connected sites.', LAUNCHDEK_TEXT_DOMAIN ),
			'docs_url'    => 'https://wpengine.com/support/',
		);
	}
}
