<?php
/**
 * Plugin deactivation handler.
 *
 * Purpose: Reserved for clearing scheduled hooks or transient cleanup on deactivate.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin deactivation.
 */
class LAUNCHDEK_Deactivator {

	/**
	 * Run deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate() {
		delete_transient( 'launchdek_activation_redirect' );
	}
}
