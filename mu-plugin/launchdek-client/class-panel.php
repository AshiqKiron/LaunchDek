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

		if ( ! $run || ! LAUNCHDEK_Client_Run_Store::user_can_view_panel( $run ) || ! in_array( $layout, $bar_layouts, true ) ) {
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
			__( 'Checklist %1$d/%2$d', 'launchdek' ),
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

		if ( ! $run || ! LAUNCHDEK_Client_Run_Store::user_can_view_panel( $run ) ) {
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

		if ( ! $run || ! LAUNCHDEK_Client_Run_Store::user_can_view_panel( $run ) || 'inline_metabox' !== LAUNCHDEK_Client_Run_Store::get_panel_layout( $run ) ) {
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
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static mount point markup.
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

		if ( ! $run || ! LAUNCHDEK_Client_Run_Store::user_can_view_panel( $run ) ) {
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
					'brandName'   => __( 'LaunchDek', 'launchdek' ),
					'panelTitle'  => LAUNCHDEK_Client_Run_Store::get_panel_title( $run ),
					'collapse'    => __( 'Collapse', 'launchdek' ),
					'expand'      => __( 'Expand', 'launchdek' ),
					'showSteps'   => __( 'Show steps', 'launchdek' ),
					'hideSteps'   => __( 'Hide steps', 'launchdek' ),
					'currentStep' => __( 'Current step', 'launchdek' ),
					'openChecklist' => __( 'Open checklist', 'launchdek' ),
					'markComplete' => __( 'Mark complete', 'launchdek' ),
					'undoComplete' => __( 'Mark not complete', 'launchdek' ),
					/* translators: 1: user display name, 2: completion date/time. */
					'completedBy' => __( 'Completed by %1$s on %2$s', 'launchdek' ),
					'unknownUser' => __( 'Unknown user', 'launchdek' ),
					'completed'   => __( 'Completed', 'launchdek' ),
					'waiting'     => __( 'Waiting on agency', 'launchdek' ),
					'openStep'    => __( 'Open step', 'launchdek' ),
					'goToSettings' => __( 'Go to settings', 'launchdek' ),
					'toggleStep'  => __( 'Toggle step details', 'launchdek' ),
					'error'       => __( 'Could not update this step.', 'launchdek' ),
					'runComplete' => __( 'Checklist complete!', 'launchdek' ),
					'startedLabel' => __( 'Started:', 'launchdek' ),
					'completedLabel' => __( 'Completed:', 'launchdek' ),
					'dismiss'     => __( 'Dismiss', 'launchdek' ),
					'dismissError' => __( 'Could not dismiss this checklist.', 'launchdek' ),
					'progress'    => __( 'Progress', 'launchdek' ),
					/* translators: 1: current step number, 2: total step count. */
					'stepOf'      => __( 'Step %1$s of %2$s', 'launchdek' ),
					/* translators: 1: completed step count, 2: total step count. */
					'checklistProgress' => __( 'Checklist %1$d/%2$d', 'launchdek' ),
					'showAll'     => __( 'Show all steps', 'launchdek' ),
					'showFocused' => __( 'Focus current step', 'launchdek' ),
					'addNote'     => __( 'Add note', 'launchdek' ),
					'addNotes'    => __( 'Add notes', 'launchdek' ),
					'notePlaceholder' => __( 'Add a note about this step…', 'launchdek' ),
					'noteRequired' => __( 'Type a note before saving.', 'launchdek' ),
					/* translators: 1: note author, 2: note timestamp. */
					'noteMeta' => __( '%1$s · %2$s', 'launchdek' ),
					'attachScreenshot' => __( 'Attach screenshot', 'launchdek' ),
					'submitNote'  => __( 'Save note', 'launchdek' ),
					'noteSaved'   => __( 'Note saved.', 'launchdek' ),
					'noteSavedLocal' => __( 'Note saved on this site. Hub sync will retry on the next checklist update.', 'launchdek' ),
					'notesHeading' => __( 'Notes', 'launchdek' ),
					/* translators: 1: notes section label, 2: note count. */
					'notesCount' => __( '%1$s (%2$s)', 'launchdek' ),
					'removeAttachment' => __( 'Remove screenshot', 'launchdek' ),
					'mediaError'  => __( 'Could not open media library.', 'launchdek' ),
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

		if ( ! $run || ! LAUNCHDEK_Client_Run_Store::user_can_view_panel( $run ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static mount point markup.
		echo '<div id="launchdek-client-panel-root" class="launchdek-client-panel-root" aria-live="polite"></div>';
	}
}
