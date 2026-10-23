<?php
/**
 * Client admin sticky checklist panel (mu-plugin).
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI for the client checklist panel.
 */
class CHECKLISTDEK_Client_Panel {

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
		$run = CHECKLISTDEK_Client_Run_Store::get();

		$layout = CHECKLISTDEK_Client_Run_Store::get_panel_layout( $run );
		$bar_layouts = array( 'live_topbar', 'admin_menu', 'fullscreen', 'toast', 'floating_pill', 'bottom_dock' );

		if ( ! $run || ! CHECKLISTDEK_Client_Run_Store::user_can_view_panel( $run ) || ! in_array( $layout, $bar_layouts, true ) ) {
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
			__( 'Checklist %1$d/%2$d', 'checklistdek' ),
			$done,
			$total
		);

		$wp_admin_bar->add_node(
			array(
				'id'    => 'checklistdek-client-checklist',
				'title' => esc_html( $title ),
				'href'  => '#checklistdek-client-panel-root',
				'meta'  => array(
					'class' => 'checklistdek-client-admin-bar-checklist',
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
		$run = CHECKLISTDEK_Client_Run_Store::get();

		if ( ! $run || ! CHECKLISTDEK_Client_Run_Store::user_can_view_panel( $run ) ) {
			return $classes;
		}

		$layout = CHECKLISTDEK_Client_Run_Store::get_panel_layout( $run );

		return trim( $classes . ' checklistdek-client-has-panel checklistdek-client-layout-' . sanitize_html_class( $layout ) );
	}

	/**
	 * Register the inline metabox layout on post editor screens.
	 *
	 * @return void
	 */
	public static function register_metabox() {
		$run = CHECKLISTDEK_Client_Run_Store::get();

		if ( ! $run || ! CHECKLISTDEK_Client_Run_Store::user_can_view_panel( $run ) || 'inline_metabox' !== CHECKLISTDEK_Client_Run_Store::get_panel_layout( $run ) ) {
			return;
		}

		$screens = array( 'post', 'page' );

		foreach ( $screens as $screen ) {
			add_meta_box(
				'checklistdek-client-checklist',
				CHECKLISTDEK_Client_Run_Store::get_panel_title( $run ),
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
		echo wp_kses_post( '<div id="checklistdek-client-metabox-root" class="checklistdek-client-metabox-root" aria-live="polite"></div>' );
	}

	/**
	 * Enqueue panel assets when an active run exists.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {
		unset( $hook );

		$run = CHECKLISTDEK_Client_Run_Store::get();

		if ( ! $run || ! CHECKLISTDEK_Client_Run_Store::user_can_view_panel( $run ) ) {
			return;
		}

		$css_path = CHECKLISTDEK_CLIENT_PANEL_DIR . 'css/checklistdek-client-admin.css';
		$js_path  = CHECKLISTDEK_CLIENT_PANEL_DIR . 'js/checklistdek-client-admin.js';

		wp_enqueue_style(
			'checklistdek-client-admin',
			CHECKLISTDEK_CLIENT_PANEL_URL . 'css/checklistdek-client-admin.css',
			array(),
			file_exists( $css_path ) ? (string) filemtime( $css_path ) : CHECKLISTDEK_CLIENT_PANEL_VERSION
		);

		wp_enqueue_media();

		wp_enqueue_script(
			'checklistdek-client-admin',
			CHECKLISTDEK_CLIENT_PANEL_URL . 'js/checklistdek-client-admin.js',
			array( 'jquery', 'heartbeat', 'media-editor', 'media-views' ),
			file_exists( $js_path ) ? (string) filemtime( $js_path ) : CHECKLISTDEK_CLIENT_PANEL_VERSION,
			true
		);

		wp_localize_script(
			'checklistdek-client-admin',
			'checklistdekClient',
			array(
				'restUrl'     => esc_url_raw( rest_url( CHECKLISTDEK_CLIENT_REST_NAMESPACE ) ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'panelLayout' => CHECKLISTDEK_Client_Run_Store::get_panel_layout( $run ),
				'run'         => CHECKLISTDEK_Client_Run_Store::format_for_api( $run ),
				'currentUser' => array(
					'name'  => sanitize_text_field( wp_get_current_user()->display_name ),
					'email' => sanitize_email( wp_get_current_user()->user_email ),
				),
				'strings' => array(
					'brandName'   => __( 'ChecklistDek', 'checklistdek' ),
					'panelTitle'  => CHECKLISTDEK_Client_Run_Store::get_panel_title( $run ),
					'collapse'    => __( 'Collapse', 'checklistdek' ),
					'expand'      => __( 'Expand', 'checklistdek' ),
					'showSteps'   => __( 'Show steps', 'checklistdek' ),
					'hideSteps'   => __( 'Hide steps', 'checklistdek' ),
					'currentStep' => __( 'Current step', 'checklistdek' ),
					'openChecklist' => __( 'Open checklist', 'checklistdek' ),
					'markComplete' => __( 'Mark complete', 'checklistdek' ),
					'undoComplete' => __( 'Mark not complete', 'checklistdek' ),
					/* translators: 1: user display name, 2: completion date/time. */
					'completedBy' => __( 'Completed by %1$s on %2$s', 'checklistdek' ),
					'unknownUser' => __( 'Unknown user', 'checklistdek' ),
					'completed'   => __( 'Completed', 'checklistdek' ),
					'waiting'     => __( 'Waiting on agency', 'checklistdek' ),
					'openStep'    => __( 'Open step', 'checklistdek' ),
					'goToSettings' => __( 'Go to settings', 'checklistdek' ),
					'toggleStep'  => __( 'Toggle step details', 'checklistdek' ),
					'error'       => __( 'Could not update this step.', 'checklistdek' ),
					'runComplete' => __( 'Checklist complete!', 'checklistdek' ),
					'startedLabel' => __( 'Started:', 'checklistdek' ),
					'completedLabel' => __( 'Completed:', 'checklistdek' ),
					'dismiss'     => __( 'Dismiss', 'checklistdek' ),
					'dismissError' => __( 'Could not dismiss this checklist.', 'checklistdek' ),
					'progress'    => __( 'Progress', 'checklistdek' ),
					/* translators: 1: current step number, 2: total step count. */
					'stepOf'      => __( 'Step %1$s of %2$s', 'checklistdek' ),
					/* translators: 1: completed step count, 2: total step count. */
					'checklistProgress' => __( 'Checklist %1$d/%2$d', 'checklistdek' ),
					'showAll'     => __( 'Show all steps', 'checklistdek' ),
					'showFocused' => __( 'Focus current step', 'checklistdek' ),
					'addNote'     => __( 'Add note', 'checklistdek' ),
					'addNotes'    => __( 'Add notes', 'checklistdek' ),
					'notePlaceholder' => __( 'Add a note about this step…', 'checklistdek' ),
					'noteRequired' => __( 'Type a note before saving.', 'checklistdek' ),
					/* translators: 1: note author, 2: note timestamp. */
					'noteMeta' => __( '%1$s · %2$s', 'checklistdek' ),
					'attachScreenshot' => __( 'Attach screenshot', 'checklistdek' ),
					'submitNote'  => __( 'Save note', 'checklistdek' ),
					'noteSaved'   => __( 'Note saved.', 'checklistdek' ),
					'noteSavedLocal' => __( 'Note saved on this site. Hub sync will retry on the next checklist update.', 'checklistdek' ),
					'notesHeading' => __( 'Notes', 'checklistdek' ),
					/* translators: 1: notes section label, 2: note count. */
					'notesCount' => __( '%1$s (%2$s)', 'checklistdek' ),
					'removeAttachment' => __( 'Remove screenshot', 'checklistdek' ),
					'mediaError'  => __( 'Could not open media library.', 'checklistdek' ),
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
		$run = CHECKLISTDEK_Client_Run_Store::get();

		if ( ! $run || ! CHECKLISTDEK_Client_Run_Store::user_can_view_panel( $run ) ) {
			return;
		}

		echo wp_kses_post( '<div id="checklistdek-client-panel-root" class="checklistdek-client-panel-root" aria-live="polite"></div>' );
	}
}
