<?php
/**
 * Integration adapter interface.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integration contract.
 */
interface LAUNCHDEK_Integration_Interface {

	/**
	 * Integration slug.
	 *
	 * @return string
	 */
	public function get_slug();

	/**
	 * Human-readable name.
	 *
	 * @return string
	 */
	public function get_name();

	/**
	 * Whether the master plugin/service is detected.
	 *
	 * @return bool
	 */
	public function is_available();

	/**
	 * Push LaunchDek agent to child sites.
	 *
	 * @param array $site_ids Target site IDs.
	 * @return array Results per site.
	 */
	public function push_agent( $site_ids = array() );

	/**
	 * Get integration status details.
	 *
	 * @return array
	 */
	public function get_status();
}
