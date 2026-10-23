<?php
/**
 * ManageWP integration adapter.
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ManageWP integration.
 */
class CHECKLISTDEK_Integration_ManageWP implements CHECKLISTDEK_Integration_Interface {

	public function get_slug() {
		return 'managewp';
	}

	public function get_name() {
		return __( 'ManageWP Connector', 'checklistdek' );
	}

	public function is_available() {
		return defined( 'MMB_WORKER_VERSION' ) || file_exists( WP_PLUGIN_DIR . '/worker/init.php' );
	}

	public function supports_site_sync() {
		return false;
	}

	public function fetch_platform_sites() {
		return new WP_Error(
			'checklistdek_integration_sync_unsupported',
			__( 'ManageWP site sync is not available yet.', 'checklistdek' ),
			array( 'status' => 400 )
		);
	}

	public function push_agent( $site_ids = array() ) {
		if ( ! $this->is_available() ) {
			return array( 'error' => __( 'ManageWP Worker is not detected on this site.', 'checklistdek' ) );
		}

		$results = array(
			'message' => __( 'ManageWP Worker detected. Use ManageWP dashboard to install ChecklistDek on connected sites.', 'checklistdek' ),
		);

		do_action( 'checklistdek_managewp_push_agent', $site_ids, $results );

		CHECKLISTDEK_Audit_Log::log( 'integration_push', array( 'integration' => 'managewp', 'results' => $results ) );

		return $results;
	}

	public function get_status() {
		return array(
			'description' => __( 'Allows the ManageWP master site to push ChecklistDek to child sites.', 'checklistdek' ),
			'docs_url'    => 'https://managewp.com/guide/',
		);
	}
}
