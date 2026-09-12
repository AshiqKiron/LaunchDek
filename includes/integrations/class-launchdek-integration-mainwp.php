<?php
/**
 * MainWP integration adapter.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MainWP integration.
 */
class LAUNCHDEK_Integration_MainWP implements LAUNCHDEK_Integration_Interface {

	public function get_slug() {
		return 'mainwp';
	}

	public function get_name() {
		return __( 'MainWP Connector', LAUNCHDEK_TEXT_DOMAIN );
	}

	public function is_available() {
		return defined( 'MAINWP_PLUGIN_URL' ) || class_exists( 'MainWP\Dashboard\MainWP' );
	}

	public function push_agent( $site_ids = array() ) {
		if ( ! $this->is_available() ) {
			return array( 'error' => __( 'MainWP is not installed.', LAUNCHDEK_TEXT_DOMAIN ) );
		}

		$results = array();

		/**
		 * Fires when LaunchDek pushes agent via MainWP.
		 *
		 * @param array $site_ids Site IDs.
		 * @param array $results  Results array passed by reference.
		 */
		do_action( 'launchdek_mainwp_push_agent', $site_ids, $results );

		if ( empty( $results ) ) {
			$results['message'] = __( 'MainWP detected. Configure child site sync via MainWP dashboard to install LaunchDek agent.', LAUNCHDEK_TEXT_DOMAIN );
		}

		LAUNCHDEK_Audit_Log::log( 'integration_push', array( 'integration' => 'mainwp', 'results' => $results ) );

		return $results;
	}

	public function get_status() {
		return array(
			'description' => __( 'Allows the MainWP master site to push LaunchDek to child sites.', LAUNCHDEK_TEXT_DOMAIN ),
			'docs_url'    => 'https://mainwp.com/kb/',
		);
	}
}
