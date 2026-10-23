<?php
/**
 * Community build stub — no MainWP ZIP staging or remote panel file writes.
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MainWP panel deployment is not available in the Community distribution.
 */
class CHECKLISTDEK_MainWP_Agent {

	/**
	 * @param int $mainwp_site_id MainWP child site ID.
	 * @return WP_Error
	 */
	public static function install_panel( $mainwp_site_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		return new WP_Error(
			'checklistdek_mainwp_manual_panel',
			__( 'Install the client checklist panel manually on the child site (download the bootstrap from Sites), or add Application Password credentials after the panel is installed.', 'checklistdek' )
		);
	}
}
