<?php
/**
 * ManageWP integration adapter.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ManageWP integration.
 */
class LAUNCHDEK_Integration_ManageWP implements LAUNCHDEK_Integration_Interface {

	public function get_slug() {
		return 'managewp';
	}

	public function get_name() {
		return __( 'ManageWP Connector', LAUNCHDEK_TEXT_DOMAIN );
	}

	public function is_available() {
		return defined( 'MMB_WORKER_VERSION' ) || file_exists( WP_PLUGIN_DIR . '/worker/init.php' );
	}

	public function push_agent( $site_ids = array() ) {
		if ( ! $this->is_available() ) {
			return array( 'error' => __( 'ManageWP Worker is not detected on this site.', LAUNCHDEK_TEXT_DOMAIN ) );
		}

		$results = array(
			'message' => __( 'ManageWP Worker detected. Use ManageWP dashboard to install LaunchDek on connected sites.', LAUNCHDEK_TEXT_DOMAIN ),
		);

		do_action( 'launchdek_managewp_push_agent', $site_ids, $results );

		LAUNCHDEK_Audit_Log::log( 'integration_push', array( 'integration' => 'managewp', 'results' => $results ) );

		return $results;
	}

	public function get_status() {
		return array(
			'description' => __( 'Allows the ManageWP master site to push LaunchDek to child sites.', LAUNCHDEK_TEXT_DOMAIN ),
			'docs_url'    => 'https://managewp.com/guide/',
		);
	}
}
