<?php
/**
 * WPvibe integration adapter.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPvibe integration.
 */
class LAUNCHDEK_Integration_WPvibe implements LAUNCHDEK_Integration_Interface {

	public function get_slug() {
		return 'wpvibe';
	}

	public function get_name() {
		return __( 'WPvibe Connector', LAUNCHDEK_TEXT_DOMAIN );
	}

	public function is_available() {
		if ( defined( 'WPVIBE_VERSION' ) ) {
			return true;
		}

		return class_exists( 'WPVibe\Core', false );
	}

	public function supports_site_sync() {
		return false;
	}

	public function fetch_platform_sites() {
		return new WP_Error(
			'launchdek_integration_sync_unsupported',
			__( 'WPvibe site sync is not available yet.', LAUNCHDEK_TEXT_DOMAIN ),
			array( 'status' => 400 )
		);
	}

	public function push_agent( $site_ids = array() ) {
		$results = array();

		if ( $this->is_available() ) {
			$results['message'] = __( 'WPvibe detected. Use WPvibe dashboard to distribute LaunchDek to managed sites.', LAUNCHDEK_TEXT_DOMAIN );
		} else {
			$results['message'] = __( 'Install WPvibe to enable centralized LaunchDek deployment.', LAUNCHDEK_TEXT_DOMAIN );
		}

		do_action( 'launchdek_wpvibe_push_agent', $site_ids, $results );

		LAUNCHDEK_Audit_Log::log( 'integration_push', array( 'integration' => 'wpvibe', 'results' => $results ) );

		return $results;
	}

	public function get_status() {
		return array(
			'description' => __( 'Allows WPvibe to push LaunchDek to child sites.', LAUNCHDEK_TEXT_DOMAIN ),
			'docs_url'    => 'https://wpvibe.com/',
		);
	}
}
