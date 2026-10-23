<?php
/**
 * WPvibe integration adapter.
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPvibe integration.
 */
class CHECKLISTDEK_Integration_WPvibe implements CHECKLISTDEK_Integration_Interface {

	public function get_slug() {
		return 'wpvibe';
	}

	public function get_name() {
		return __( 'WPvibe Connector', 'checklistdek' );
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
			'checklistdek_integration_sync_unsupported',
			__( 'WPvibe site sync is not available yet.', 'checklistdek' ),
			array( 'status' => 400 )
		);
	}

	public function push_agent( $site_ids = array() ) {
		$results = array();

		if ( $this->is_available() ) {
			$results['message'] = __( 'WPvibe detected. Use WPvibe dashboard to distribute ChecklistDek to managed sites.', 'checklistdek' );
		} else {
			$results['message'] = __( 'Install WPvibe to enable centralized ChecklistDek deployment.', 'checklistdek' );
		}

		do_action( 'checklistdek_wpvibe_push_agent', $site_ids, $results );

		CHECKLISTDEK_Audit_Log::log( 'integration_push', array( 'integration' => 'wpvibe', 'results' => $results ) );

		return $results;
	}

	public function get_status() {
		return array(
			'description' => __( 'Allows WPvibe to push ChecklistDek to child sites.', 'checklistdek' ),
			'docs_url'    => 'https://wpvibe.com/',
		);
	}
}
