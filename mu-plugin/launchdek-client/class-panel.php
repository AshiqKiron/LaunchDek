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
	 * Register panel hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_bar_menu', array( __CLASS__, 'register_admin_bar' ), 100 );
		add_filter( 'admin_body_class', array( __CLASS__, 'admin_body_class' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_metabox' ) );
	}

	/**
	 * Add a compact checklist progress item to the admin bar (live top bar layout).
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance.
	 * @return void
	 */
	public static function register_admin_bar( $wp_admin_bar ) {
		$run = LAUNCHDEK_Client_Run_Store::get();

		$layout = LAUNCHDEK_Client_Run_Store::get_panel_layout( $run );
		$bar_layouts = array( 'live_topbar', 'admin_menu', 'fullscreen', 'toast', 'floating_pill', 'bottom_dock' );

		if ( ! $run || ! is_user_logged_in() || ! in_array( $layout, $bar_layouts, true ) ) {
			return;
		}

		$steps = is_array( $run['steps'] ?? null ) ? $run['steps'] : array();
		$total = count( $steps );
		$done  = 0;

		foreach ( $steps as $step ) {
			if ( is_array( $step ) && 'completed' === ( $step['status'] ?? '' ) ) {
				++$done;
			}
		}

		$title = sprintf(
			/* translators: 1: completed steps, 2: total steps */
			__( 'Checklist %1$d/%2$d', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
			$done,
			$total
		);

		$wp_admin_bar->add_node(
			array(
				'id'    => 'launchdek-client-checklist',
				'title' => esc_html( $title ),
				'href'  => '#launchdek-client-panel-root',
				'meta'  => array(
					'class' => 'launchdek-client-admin-bar-checklist',
					'title' => sanitize_text_field( $run['checklist_title'] ?? '' ),
				),
			)
		);
	}

	/**
	 * Add layout-specific admin body classes.
	 *
	 * @param string $classes Existing classes.
	 * @return string
	 */
	public static function admin_body_class( $classes ) {
		$run = LAUNCHDEK_Client_Run_Store::get();

		if ( ! $run || ! is_user_logged_in() ) {
			return $classes;
		}

		$layout = LAUNCHDEK_Client_Run_Store::get_panel_layout( $run );

		return trim( $classes . ' launchdek-client-has-panel launchdek-client-layout-' . sanitize_html_class( $layout ) );
	}

	/**
	 * Register the inline metabox layout on post editor screens.
	 *
	 * @return void
	 */
	public static function register_metabox() {
		$run = LAUNCHDEK_Client_Run_Store::get();

		if ( ! $run || ! is_user_logged_in() || 'inline_metabox' !== LAUNCHDEK_Client_Run_Store::get_panel_layout( $run ) ) {
			return;
		}

		$screens = array( 'post', 'page' );

		foreach ( $screens as $screen ) {
			add_meta_box(
				'launchdek-client-checklist',
				LAUNCHDEK_Client_Run_Store::get_panel_title( $run ),
				array( __CLASS__, 'render_metabox' ),
				$screen,
				'side',
				'high'
			);
		}
	}

	/**
	 * Render the inline metabox mount point.
	 *
	 * @return void
	 */
	public static function render_metabox() {
		echo '<div id="launchdek-client-metabox-root" class="launchdek-client-metabox-root" aria-live="polite"></div>';
	}

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
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : LAUNCHDEK_CLIENT_PANEL_VERSION
		);

		wp_enqueue_media();

		wp_enqueue_script(
			'launchdek-client-admin',
			LAUNCHDEK_CLIENT_PANEL_URL . 'js/launchdek-client-admin.js',
			array( 'jquery', 'media-editor', 'media-views' ),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : LAUNCHDEK_CLIENT_PANEL_VERSION,
			true
		);

		wp_localize_script(
			'launchdek-client-admin',
			'launchdekClient',
			array(
				'restUrl'     => esc_url_raw( rest_url( LAUNCHDEK_CLIENT_REST_NAMESPACE ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'panelLayout' => LAUNCHDEK_Client_Run_Store::get_panel_layout( $run ),
				'run'         => LAUNCHDEK_Client_Run_Store::format_for_api( $run ),
				'currentUser' => array(
					'name'  => sanitize_text_field( wp_get_current_user()->display_name ),
					'email' => sanitize_email( wp_get_current_user()->user_email ),
				),
				'strings' => array(
					'panelTitle'  => LAUNCHDEK_Client_Run_Store::get_panel_title( $run ),
					'collapse'    => __( 'Collapse', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'expand'      => __( 'Expand', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'showSteps'   => __( 'Show steps', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'hideSteps'   => __( 'Hide steps', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'currentStep' => __( 'Current step', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'openChecklist' => __( 'Open checklist', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'markComplete' => __( 'Mark complete', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'undoComplete' => __( 'Mark not complete', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'completedBy' => __( 'Completed by %1$s on %2$s', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'unknownUser' => __( 'Unknown user', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'completed'   => __( 'Completed', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'waiting'     => __( 'Waiting on agency', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'openStep'    => __( 'Open step', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'goToSettings' => __( 'Go to settings', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'toggleStep'  => __( 'Toggle step details', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'error'       => __( 'Could not update this step.', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'runComplete' => __( 'Checklist complete!', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'startedLabel' => __( 'Started:', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'completedLabel' => __( 'Completed:', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'dismiss'     => __( 'Dismiss', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'dismissError' => __( 'Could not dismiss this checklist.', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'progress'    => __( 'Progress', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'stepOf'      => __( 'Step %1$s of %2$s', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'checklistProgress' => __( 'Checklist %1$d/%2$d', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'showAll'     => __( 'Show all steps', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'showFocused' => __( 'Focus current step', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'addNote'     => __( 'Add note', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'addNotes'    => __( 'Add notes', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'notePlaceholder' => __( 'Add a note about this step…', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'noteRequired' => __( 'Type a note before saving.', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'noteMeta' => __( '%1$s · %2$s', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'attachScreenshot' => __( 'Attach screenshot', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'submitNote'  => __( 'Save note', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'noteSaved'   => __( 'Note saved.', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'noteSavedLocal' => __( 'Note saved on this site. Hub sync will retry on the next checklist update.', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'notesHeading' => __( 'Notes', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'notesCount' => __( '%1$s (%2$s)', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'removeAttachment' => __( 'Remove screenshot', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					'mediaError'  => __( 'Could not open media library.', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
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
