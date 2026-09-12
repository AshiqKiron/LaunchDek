<?php
/**
 * WP Umbrella integration adapter.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Umbrella integration.
 */
class LAUNCHDEK_Integration_WP_Umbrella implements LAUNCHDEK_Integration_Interface {

	public function get_slug() {
		return 'wp-umbrella';
	}

	public function get_name() {
		return __( 'WP Umbrella Connector', LAUNCHDEK_TEXT_DOMAIN );
	}

	public function is_available() {
		return defined( 'WP_UMBRELLA_VERSION' ) || class_exists( 'WPUmbrella\Core' );
	}

	public function push_agent( $site_ids = array() ) {
		if ( ! $this->is_available() ) {
			return array( 'error' => __( 'WP Umbrella is not installed.', LAUNCHDEK_TEXT_DOMAIN ) );
		}

		$results = array(
			'message' => __( 'WP Umbrella detected. Deploy LaunchDek via Umbrella plugin management.', LAUNCHDEK_TEXT_DOMAIN ),
		);

		do_action( 'launchdek_wp_umbrella_push_agent', $site_ids, $results );

		LAUNCHDEK_Audit_Log::log( 'integration_push', array( 'integration' => 'wp-umbrella', 'results' => $results ) );

		return $results;
	}

	public function get_status() {
		return array(
			'description' => __( 'Allows the Umbrella master site to push LaunchDek to child sites.', LAUNCHDEK_TEXT_DOMAIN ),
			'docs_url'    => 'https://wp-umbrella.com/',
		);
	}
}
