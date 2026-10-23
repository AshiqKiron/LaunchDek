<?php
/**
 * Client panel REST API (mu-plugin).
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers checklistdek/v1/client/* routes on the client site.
 */
class CHECKLISTDEK_Client_REST_API {

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			CHECKLISTDEK_CLIENT_REST_NAMESPACE,
			'/client/info',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_info' ),
				'permission_callback' => array( __CLASS__, 'can_hub_manage' ),
			)
		);

		$install_helper = CHECKLISTDEK_CLIENT_PANEL_DIR . 'class-rest-api-install.php';
		if ( is_readable( $install_helper ) ) {
			require_once $install_helper;
			CHECKLISTDEK_Client_REST_API_Install::register_route( CHECKLISTDEK_CLIENT_REST_NAMESPACE );
		}

		register_rest_route(
			CHECKLISTDEK_CLIENT_REST_NAMESPACE,
			'/client/run',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'get_run' ),
					'permission_callback' => array( __CLASS__, 'can_view_panel' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'save_run' ),
					'permission_callback' => array( __CLASS__, 'can_hub_manage' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( __CLASS__, 'clear_run' ),
					'permission_callback' => array( __CLASS__, 'can_hub_manage' ),
				),
			)
		);

		register_rest_route(
			CHECKLISTDEK_CLIENT_REST_NAMESPACE,
			'/client/run/dismiss',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'dismiss_run' ),
				'permission_callback' => array( __CLASS__, 'can_view_panel' ),
			)
		);

		register_rest_route(
			CHECKLISTDEK_CLIENT_REST_NAMESPACE,
			'/client/run/steps/(?P<step>\d+)/complete',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'complete_step' ),
				'permission_callback' => array( __CLASS__, 'can_view_panel' ),
			)
		);

		register_rest_route(
			CHECKLISTDEK_CLIENT_REST_NAMESPACE,
			'/client/run/steps/(?P<step>\d+)/uncomplete',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'uncomplete_step' ),
				'permission_callback' => array( __CLASS__, 'can_view_panel' ),
			)
		);

		register_rest_route(
			CHECKLISTDEK_CLIENT_REST_NAMESPACE,
			'/client/run/steps/(?P<step>\d+)/notes',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'add_step_note' ),
				'permission_callback' => array( __CLASS__, 'can_view_panel' ),
			)
		);

		if ( class_exists( 'CHECKLISTDEK_Client_Auto_Capture' ) ) {
			register_rest_route(
				CHECKLISTDEK_CLIENT_REST_NAMESPACE,
				'/client/capture',
				array(
					array(
						'methods'             => 'GET',
						'callback'            => array( __CLASS__, 'get_capture' ),
						'permission_callback' => array( __CLASS__, 'can_hub_manage' ),
					),
					array(
						'methods'             => 'POST',
						'callback'            => array( __CLASS__, 'update_capture' ),
						'permission_callback' => array( __CLASS__, 'can_hub_manage' ),
					),
					array(
						'methods'             => 'DELETE',
						'callback'            => array( __CLASS__, 'clear_capture' ),
						'permission_callback' => array( __CLASS__, 'can_hub_manage' ),
					),
				)
			);
		}
	}

	/**
	 * Panel metadata for hub detection (authenticated hub users with manage_options).
	 *
	 * @return WP_REST_Response
	 */
	public static function get_info() {
		global $wp_version;

		return rest_ensure_response(
			array(
				'panel'       => true,
				'mode'        => 'mu-plugin',
				'version'     => CHECKLISTDEK_CLIENT_PANEL_VERSION,
				'wp_version'  => $wp_version,
				'php_version' => PHP_VERSION,
			)
		);
	}

	/**
	 * Get the active run for the admin panel.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_run() {
		return rest_ensure_response(
			array(
				'run' => CHECKLISTDEK_Client_Run_Store::format_for_api( CHECKLISTDEK_Client_Run_Store::get() ),
			)
		);
	}

	/**
	 * Receive a run snapshot pushed from the hub.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function save_run( $request ) {
		$data     = $request->get_json_params();
		$snapshot = is_array( $data ) ? $data : array();
		$run      = CHECKLISTDEK_Client_Run_Store::save( $snapshot );

		return rest_ensure_response(
			array(
				'success' => true,
				'run'     => $run,
			)
		);
	}

	/**
	 * Clear the active run.
	 *
	 * @return WP_REST_Response
	 */
	public static function clear_run() {
		CHECKLISTDEK_Client_Run_Store::clear();

		return rest_ensure_response(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * Dismiss a completed checklist from the client admin panel.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function dismiss_run() {
		$run = CHECKLISTDEK_Client_Run_Store::get();

		if ( ! $run ) {
			return new WP_Error(
				'checklistdek_client_no_run',
				__( 'No active checklist is assigned to this site.', 'checklistdek' ),
				array( 'status' => 404 )
			);
		}

		if ( 'completed' !== ( $run['run_status'] ?? '' ) && ! CHECKLISTDEK_Client_Run_Store::all_steps_completed( $run ) ) {
			return new WP_Error(
				'checklistdek_client_not_completed',
				__( 'Only completed checklists can be dismissed.', 'checklistdek' ),
				array( 'status' => 409 )
			);
		}

		CHECKLISTDEK_Client_Run_Store::clear();

		return rest_ensure_response(
			array(
				'success' => true,
			)
		);
	}

	/**
	 * Complete a manual step from the client admin panel.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function complete_step( $request ) {
		$run        = CHECKLISTDEK_Client_Run_Store::get();
		$step_index = absint( $request['step'] );

		if ( ! $run ) {
			return new WP_Error(
				'checklistdek_client_no_run',
				__( 'No active checklist is assigned to this site.', 'checklistdek' ),
				array( 'status' => 404 )
			);
		}

		$step = CHECKLISTDEK_Client_Run_Store::get_step( $step_index );

		if ( ! $step ) {
			return new WP_Error(
				'checklistdek_client_step_not_found',
				__( 'Checklist step not found.', 'checklistdek' ),
				array( 'status' => 404 )
			);
		}

		if ( ! CHECKLISTDEK_Client_Run_Store::user_can_complete_step( $step ) ) {
			return new WP_Error(
				'checklistdek_client_forbidden',
				__( 'You do not have permission to complete this step.', 'checklistdek' ),
				array( 'status' => 403 )
			);
		}

		if ( 'manual' !== ( $step['type'] ?? 'manual' ) ) {
			return new WP_Error(
				'checklistdek_client_not_manual',
				__( 'Only manual steps can be completed here.', 'checklistdek' ),
				array( 'status' => 400 )
			);
		}

		if ( ! in_array( $step['status'] ?? '', array( 'pending', 'awaiting_manual', 'running' ), true ) ) {
			return new WP_Error(
				'checklistdek_client_not_ready',
				__( 'This step cannot be marked complete.', 'checklistdek' ),
				array( 'status' => 409 )
			);
		}

		$user   = wp_get_current_user();
		$result = CHECKLISTDEK_Client_Hub_Client::complete_step( $run, $step_index, $user );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$saved_run = null;

		if ( ! empty( $result['snapshot'] ) && is_array( $result['snapshot'] ) ) {
			$saved_run = CHECKLISTDEK_Client_Run_Store::save( $result['snapshot'] );
		} elseif ( ! empty( $result['run'] ) && is_array( $result['run'] ) ) {
			$run_status = sanitize_key( $result['run']['status'] ?? '' );

			if ( in_array( $run_status, array( 'failed', 'cancelled' ), true ) ) {
				CHECKLISTDEK_Client_Run_Store::clear();
			} else {
				$saved_run = CHECKLISTDEK_Client_Run_Store::patch_from_hub_run( $result['run'] );
			}
		} else {
			$saved_run = CHECKLISTDEK_Client_Run_Store::get();
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'run'     => CHECKLISTDEK_Client_Run_Store::format_for_api( $saved_run ),
			)
		);
	}

	/**
	 * Revert a completed manual step from the client admin panel.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function uncomplete_step( $request ) {
		$run        = CHECKLISTDEK_Client_Run_Store::get();
		$step_index = absint( $request['step'] );

		if ( ! $run ) {
			return new WP_Error(
				'checklistdek_client_no_run',
				__( 'No active checklist is assigned to this site.', 'checklistdek' ),
				array( 'status' => 404 )
			);
		}

		$step = CHECKLISTDEK_Client_Run_Store::get_step( $step_index );

		if ( ! $step ) {
			return new WP_Error(
				'checklistdek_client_step_not_found',
				__( 'Checklist step not found.', 'checklistdek' ),
				array( 'status' => 404 )
			);
		}

		if ( ! CHECKLISTDEK_Client_Run_Store::user_can_complete_step( $step ) ) {
			return new WP_Error(
				'checklistdek_client_forbidden',
				__( 'You do not have permission to update this step.', 'checklistdek' ),
				array( 'status' => 403 )
			);
		}

		if ( 'manual' !== ( $step['type'] ?? 'manual' ) ) {
			return new WP_Error(
				'checklistdek_client_not_manual',
				__( 'Only manual steps can be updated here.', 'checklistdek' ),
				array( 'status' => 400 )
			);
		}

		if ( 'completed' !== ( $step['status'] ?? '' ) ) {
			return new WP_Error(
				'checklistdek_client_not_completed',
				__( 'This step is not marked complete.', 'checklistdek' ),
				array( 'status' => 409 )
			);
		}

		$user   = wp_get_current_user();
		$result = CHECKLISTDEK_Client_Hub_Client::uncomplete_step( $run, $step_index, $user );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$saved_run = null;

		if ( ! empty( $result['snapshot'] ) && is_array( $result['snapshot'] ) ) {
			$saved_run = CHECKLISTDEK_Client_Run_Store::save( $result['snapshot'] );
		} elseif ( ! empty( $result['run'] ) && is_array( $result['run'] ) ) {
			$saved_run = CHECKLISTDEK_Client_Run_Store::patch_from_hub_run( $result['run'] );
		} else {
			$saved_run = CHECKLISTDEK_Client_Run_Store::get();
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'run'     => CHECKLISTDEK_Client_Run_Store::format_for_api( $saved_run ),
			)
		);
	}

	/**
	 * Add a note (and optional screenshot) to a checklist step.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function add_step_note( $request ) {
		$run        = CHECKLISTDEK_Client_Run_Store::get();
		$step_index = absint( $request['step'] );

		if ( ! $run ) {
			return new WP_Error(
				'checklistdek_client_no_run',
				__( 'No active checklist is assigned to this site.', 'checklistdek' ),
				array( 'status' => 404 )
			);
		}

		$step = CHECKLISTDEK_Client_Run_Store::get_step( $step_index );

		if ( ! $step ) {
			return new WP_Error(
				'checklistdek_client_step_not_found',
				__( 'Checklist step not found.', 'checklistdek' ),
				array( 'status' => 404 )
			);
		}

		if ( ! CHECKLISTDEK_Client_Run_Store::user_can_complete_step( $step ) ) {
			return new WP_Error(
				'checklistdek_client_forbidden',
				__( 'You do not have permission to add notes to this step.', 'checklistdek' ),
				array( 'status' => 403 )
			);
		}

		if ( ! in_array( $step['status'] ?? '', array( 'pending', 'awaiting_manual', 'running' ), true ) ) {
			return new WP_Error(
				'checklistdek_client_not_ready',
				__( 'Notes can only be added to steps that are not yet complete.', 'checklistdek' ),
				array( 'status' => 409 )
			);
		}

		$data          = $request->get_json_params();
		$payload       = is_array( $data ) ? $data : array();
		$text          = trim( sanitize_textarea_field( $payload['text'] ?? '' ) );
		$attachment_id = absint( $payload['attachment_id'] ?? 0 );

		if ( '' === $text ) {
			return new WP_Error(
				'checklistdek_client_note_empty',
				__( 'Type a note before saving.', 'checklistdek' ),
				array( 'status' => 400 )
			);
		}

		if ( $attachment_id > 0 ) {
			$attachment = get_post( $attachment_id );
			if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
				return new WP_Error(
					'checklistdek_client_invalid_attachment',
					__( 'The screenshot attachment is not valid.', 'checklistdek' ),
					array( 'status' => 400 )
				);
			}
			$payload['attachment_url'] = wp_get_attachment_url( $attachment_id );
		}

		$user       = wp_get_current_user();
		$created_at = sanitize_text_field( $payload['created_at'] ?? '' );
		if ( '' === $created_at ) {
			$created_at = current_time( 'mysql', true );
		}

		$payload['text']       = $text;
		$payload['created_at'] = $created_at;

		$note = array(
			'user'           => sanitize_text_field( $user->display_name ),
			'text'           => $text,
			'attachment_id'  => $attachment_id,
			'attachment_url' => ! empty( $payload['attachment_url'] ) ? esc_url_raw( $payload['attachment_url'] ) : '',
			'created_at'     => $created_at,
		);

		$local_run = CHECKLISTDEK_Client_Run_Store::add_step_note( $step_index, $note );

		if ( ! $local_run ) {
			return new WP_Error(
				'checklistdek_client_note_save_failed',
				__( 'Failed to save note locally.', 'checklistdek' ),
				array( 'status' => 500 )
			);
		}

		$result = CHECKLISTDEK_Client_Hub_Client::add_step_note( $run, $step_index, $user, $payload );

		if ( ! is_wp_error( $result ) ) {
			if ( ! empty( $result['snapshot'] ) && is_array( $result['snapshot'] ) ) {
				CHECKLISTDEK_Client_Run_Store::save( $result['snapshot'] );
			} elseif ( ! empty( $result['run'] ) && is_array( $result['run'] ) ) {
				CHECKLISTDEK_Client_Run_Store::patch_from_hub_run( $result['run'] );
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'run'     => CHECKLISTDEK_Client_Run_Store::format_for_api( CHECKLISTDEK_Client_Run_Store::get() ),
				'hub_synced' => ! is_wp_error( $result ),
			)
		);
	}

	/**
	 * Get auto-capture status from the client site.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_capture() {
		return rest_ensure_response( CHECKLISTDEK_Client_Auto_Capture::get_status() );
	}

	/**
	 * Start or stop auto-capture on the client site.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function update_capture( $request ) {
		$data   = $request->get_json_params();
		$action = is_array( $data ) ? sanitize_key( $data['action'] ?? '' ) : '';

		if ( 'start' === $action ) {
			return rest_ensure_response( CHECKLISTDEK_Client_Auto_Capture::start() );
		}

		if ( 'stop' === $action ) {
			return rest_ensure_response( CHECKLISTDEK_Client_Auto_Capture::stop() );
		}

		return new WP_Error(
			'checklistdek_client_capture_action',
			__( 'Invalid capture action.', 'checklistdek' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Clear captured steps on the client site.
	 *
	 * @return WP_REST_Response
	 */
	public static function clear_capture() {
		return rest_ensure_response( CHECKLISTDEK_Client_Auto_Capture::clear() );
	}

	/**
	 * Hub management permission — Application Password admin user.
	 *
	 * @return bool
	 */
	public static function can_hub_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Client panel permission — read capability plus step role alignment (see Run_Store::user_can_view_panel).
	 *
	 * @return bool
	 */
	public static function can_view_panel() {
		return CHECKLISTDEK_Client_Run_Store::user_can_view_panel();
	}
}
