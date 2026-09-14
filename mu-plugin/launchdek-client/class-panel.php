<?php
/**
 * Client admin sticky checklist panel (mu-plugin).
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI for the client checklist panel.
 */
class LAUNCHDEK_Client_Panel {

	/**
	 * Enqueue panel assets when an active run exists.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		unset( $hook );

		$run = LAUNCHDEK_Client_Run_Store::get();

		if ( ! $run || ! is_user_logged_in() ) {
			return;
		}

		$css_path = LAUNCHDEK_CLIENT_PANEL_DIR . 'css/launchdek-client-admin.css';
		$js_path  = LAUNCHDEK_CLIENT_PANEL_DIR . 'js/launchdek-client-admin.js';

		wp_enqueue_style(
			'launchdek-client-admin',
			LAUNCHDEK_CLIENT_PANEL_URL . 'css/launchdek-client-admin.css',
			array( 'dashicons' ),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : LAUNCHDEK_CLIENT_PANEL_VERSION
		);

		wp_enqueue_media();

		wp_enqueue_script(
			'launchdek-client-admin',
			LAUNCHDEK_CLIENT_PANEL_URL . 'js/launchdek-client-admin.js',
			array( 'media-views' ),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : LAUNCHDEK_CLIENT_PANEL_VERSION,
			true
		);

		wp_localize_script(
			'launchdek-client-admin',
			'launchdekClient',
			array(
				'restUrl' => esc_url_raw( rest_url( LAUNCHDEK_CLIENT_REST_NAMESPACE ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'run'     => LAUNCHDEK_Client_Run_Store::format_for_api( $run ),
				'strings' => array(
					'panelTitle'  => __( 'Agency Checklist', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'collapse'    => __( 'Collapse', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'expand'      => __( 'Expand', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'complete'    => __( 'Mark complete', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'completed'   => __( 'Completed', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'waiting'     => __( 'Waiting on agency', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'pending'     => __( 'Pending', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'openStep'    => __( 'Open step', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'goToSettings' => __( 'Go to settings', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'toggleStep'  => __( 'Toggle step details', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'error'       => __( 'Could not update this step.', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'runComplete' => __( 'Checklist complete — great work!', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'progress'    => __( 'Progress', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'stepOf'      => __( 'Step %1$s of %2$s', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'showAll'     => __( 'Show all steps', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'showFocused' => __( 'Focus current step', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'addNote'     => __( 'Add note', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'notePlaceholder' => __( 'Add a note about this step…', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'attachScreenshot' => __( 'Attach screenshot', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'submitNote'  => __( 'Save note', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'noteSaved'   => __( 'Note saved.', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'notesHeading' => __( 'Notes', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
				),
			)
		);
	}

	/**
	 * Render panel mount point in admin footer.
	 *
	 * @return void
	 */
	public static function render_panel() {
		$run = LAUNCHDEK_Client_Run_Store::get();

		if ( ! $run || ! is_user_logged_in() ) {
			return;
		}

		echo '<div id="launchdek-client-panel-root" class="launchdek-client-panel-root" aria-live="polite"></div>';
	}
}
