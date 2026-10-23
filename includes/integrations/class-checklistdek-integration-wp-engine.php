<?php
/**
 * WP Engine integration adapter.
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Engine integration.
 */
class CHECKLISTDEK_Integration_WP_Engine implements CHECKLISTDEK_Integration_Interface {

	public function get_slug() {
		return 'wp-engine';
	}

	public function get_name() {
		return __( 'WP Engine Connector', 'checklistdek' );
	}

	public function is_available() {
		if ( defined( 'WPE_PLUGIN_BASE' ) ) {
			return true;
		}

		if ( class_exists( 'WpeCommon', false ) ) {
			return true;
		}

		return (bool) getenv( 'IS_WPE' );
	}

	public function supports_site_sync() {
		return false;
	}

	public function fetch_platform_sites() {
		return new WP_Error(
			'checklistdek_integration_sync_unsupported',
			__( 'WP Engine site sync is not available yet.', 'checklistdek' ),
			array( 'status' => 400 )
		);
	}

	public function push_agent( $site_ids = array() ) {
		$results = array();

		if ( $this->is_available() ) {
			$results['message'] = __( 'WP Engine environment detected. Use WP Engine portal or SSH gateway to deploy ChecklistDek to staging/production sites.', 'checklistdek' );
		} else {
			$results['message'] = __( 'Configure WP Engine API credentials in Settings to enable remote deployment.', 'checklistdek' );
		}

		do_action( 'checklistdek_wp_engine_push_agent', $site_ids, $results );

		CHECKLISTDEK_Audit_Log::log( 'integration_push', array( 'integration' => 'wp-engine', 'results' => $results ) );

		return $results;
	}

	public function get_status() {
		return array(
			'description' => __( 'Allows WP Engine to push ChecklistDek to connected sites.', 'checklistdek' ),
			'docs_url'    => 'https://wpengine.com/support/',
		);
	}
}
