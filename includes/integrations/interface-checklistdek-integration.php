<?php
/**
 * Integration adapter interface.
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integration contract.
 */
interface CHECKLISTDEK_Integration_Interface {

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
	 * Whether this connector can import site inventory into ChecklistDek.
	 *
	 * @return bool
	 */
	public function supports_site_sync();

	/**
	 * Fetch child sites from the connected platform.
	 *
	 * @return array|WP_Error Array of platform rows keyed by connector field names.
	 */
	public function fetch_platform_sites();

	/**
	 * Push ChecklistDek agent to child sites.
	 *
	 * @param array $site_ids Target ChecklistDek site IDs.
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
