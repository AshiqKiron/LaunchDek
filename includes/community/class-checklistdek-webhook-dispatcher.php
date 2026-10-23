<?php
/**
 * Community build stub — notifications are not bundled in the free distribution.
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * No-op webhook dispatcher for Community builds.
 */
class CHECKLISTDEK_Webhook_Dispatcher {

	/**
	 * @param string $event Event name.
	 * @param array  $data  Event payload.
	 * @return void
	 */
	public static function dispatch( $event, $data = array() ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
	}
}
