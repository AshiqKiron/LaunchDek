<?php
/**
 * REST API controller for LaunchDek.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers launchdek/v1 routes.
 */
class LAUNCHDEK_REST_API {

	const NAMESPACE = 'launchdek/v1';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register all REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		// Dashboard.
		register_rest_route( self::NAMESPACE, '/dashboard/stats', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_dashboard_stats' ),
			'permission_callback' => array( __CLASS__, 'can_view_dashboard' ),
		) );

		register_rest_route( self::NAMESPACE, '/connections/ticker', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_connection_ticker' ),
			'permission_callback' => array( __CLASS__, 'can_view_dashboard' ),
		) );

		register_rest_route( self::NAMESPACE, '/logs/feed', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_log_feed' ),
			'permission_callback' => array( __CLASS__, 'can_view_dashboard' ),
		) );

		register_rest_route( self::NAMESPACE, '/logs/(?P<id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_log_entry' ),
			'permission_callback' => array( __CLASS__, 'can_view_dashboard' ),
		) );

		register_rest_route( self::NAMESPACE, '/logs/run/(?P<run_id>\d+)/steps', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_log_run_steps' ),
			'permission_callback' => array( __CLASS__, 'can_view_dashboard' ),
		) );

		register_rest_route( self::NAMESPACE, '/onboarding/dismiss', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'dismiss_onboarding' ),
			'permission_callback' => array( __CLASS__, 'can_view_dashboard' ),
		) );

		register_rest_route( self::NAMESPACE, '/onboarding/reset', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'reset_onboarding' ),
			'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
		) );

		register_rest_route( self::NAMESPACE, '/settings/client-panel-title', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'update_client_panel_title' ),
			'permission_callback' => array( __CLASS__, 'can_edit_checklists' ),
		) );

		// Sites.
		register_rest_route( self::NAMESPACE, '/sites', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_sites' ),
				'permission_callback' => array( __CLASS__, 'can_manage_sites' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_site' ),
				'permission_callback' => array( __CLASS__, 'can_manage_sites' ),
			),
		) );

		register_rest_route( self::NAMESPACE, '/sites/(?P<id>\d+)', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_site' ),
				'permission_callback' => array( __CLASS__, 'can_manage_sites' ),
			),
			array(
				'methods'             => 'PUT,PATCH',
				'callback'            => array( __CLASS__, 'update_site' ),
				'permission_callback' => array( __CLASS__, 'can_manage_sites' ),
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'delete_site' ),
				'permission_callback' => array( __CLASS__, 'can_manage_sites' ),
			),
		) );

		register_rest_route( self::NAMESPACE, '/sites/(?P<id>\d+)/test', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'test_site_connection' ),
			'permission_callback' => array( __CLASS__, 'can_manage_sites' ),
		) );

		register_rest_route( self::NAMESPACE, '/sites/test', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'test_raw_connection' ),
			'permission_callback' => array( __CLASS__, 'can_manage_sites' ),
		) );

		register_rest_route( self::NAMESPACE, '/sites/tags', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_all_tags' ),
			'permission_callback' => array( __CLASS__, 'can_manage_sites' ),
		) );

		register_rest_route( self::NAMESPACE, '/sites/groups', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_site_groups' ),
				'permission_callback' => array( __CLASS__, 'can_manage_sites' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_site_group' ),
				'permission_callback' => array( __CLASS__, 'can_manage_sites' ),
			),
		) );

		register_rest_route( self::NAMESPACE, '/sites/(?P<id>\d+)/panel/install', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'install_site_panel' ),
			'permission_callback' => array( __CLASS__, 'can_manage_sites' ),
		) );

		register_rest_route( self::NAMESPACE, '/panel/bootstrap', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_panel_bootstrap' ),
			'permission_callback' => array( __CLASS__, 'can_manage_sites' ),
		) );

		register_rest_route( self::NAMESPACE, '/sites/(?P<id>\d+)/runs', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_site_runs' ),
			'permission_callback' => array( __CLASS__, 'can_manage_sites' ),
		) );

		register_rest_route( self::NAMESPACE, '/sites/(?P<id>\d+)/capture', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_site_capture' ),
				'permission_callback' => array( __CLASS__, 'can_edit_checklists' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'update_site_capture' ),
				'permission_callback' => array( __CLASS__, 'can_edit_checklists' ),
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'clear_site_capture' ),
				'permission_callback' => array( __CLASS__, 'can_edit_checklists' ),
			),
		) );

		// Checklists.
		register_rest_route( self::NAMESPACE, '/checklists', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_checklists' ),
				'permission_callback' => array( __CLASS__, 'can_edit_checklists' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_checklist' ),
				'permission_callback' => array( __CLASS__, 'can_edit_checklists' ),
			),
		) );

		register_rest_route( self::NAMESPACE, '/checklists/(?P<id>\d+)', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_checklist' ),
				'permission_callback' => array( __CLASS__, 'can_edit_checklists' ),
			),
			array(
				'methods'             => 'PUT,PATCH',
				'callback'            => array( __CLASS__, 'update_checklist' ),
				'permission_callback' => array( __CLASS__, 'can_edit_checklists' ),
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'delete_checklist' ),
				'permission_callback' => array( __CLASS__, 'can_edit_checklists' ),
			),
		) );

		register_rest_route( self::NAMESPACE, '/checklists/import', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'import_checklist' ),
			'permission_callback' => array( __CLASS__, 'can_edit_checklists' ),
		) );

		register_rest_route( self::NAMESPACE, '/checklists/(?P<id>\d+)/export', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'export_checklist' ),
			'permission_callback' => array( __CLASS__, 'can_edit_checklists' ),
		) );

		register_rest_route( self::NAMESPACE, '/checklists/infer-deep-link', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'infer_deep_link' ),
			'permission_callback' => array( __CLASS__, 'can_edit_checklists' ),
		) );

		register_rest_route( self::NAMESPACE, '/checklists/(?P<id>\d+)/validate-step', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'validate_api_step' ),
			'permission_callback' => array( __CLASS__, 'can_edit_checklists' ),
		) );

		// Runs / Automation.
		register_rest_route( self::NAMESPACE, '/runs/batch', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'start_batch_runs' ),
			'permission_callback' => array( __CLASS__, 'can_execute' ),
		) );

		register_rest_route( self::NAMESPACE, '/runs', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_runs' ),
				'permission_callback' => array( __CLASS__, 'can_execute' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'start_run' ),
				'permission_callback' => array( __CLASS__, 'can_execute' ),
			),
		) );

		register_rest_route( self::NAMESPACE, '/runs/(?P<id>\d+)', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_run' ),
				'permission_callback' => array( __CLASS__, 'can_execute' ),
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( __CLASS__, 'delete_run' ),
				'permission_callback' => array( __CLASS__, 'can_manage_sites' ),
			),
		) );

		register_rest_route( self::NAMESPACE, '/runs/(?P<id>\d+)/archive', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'archive_run' ),
			'permission_callback' => array( __CLASS__, 'can_manage_sites' ),
		) );

		register_rest_route( self::NAMESPACE, '/runs/(?P<id>\d+)/next', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'execute_next_step' ),
			'permission_callback' => array( __CLASS__, 'can_execute' ),
		) );

		register_rest_route( self::NAMESPACE, '/runs/(?P<id>\d+)/auto', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'execute_auto_steps' ),
			'permission_callback' => array( __CLASS__, 'can_execute' ),
		) );

		register_rest_route( self::NAMESPACE, '/runs/(?P<id>\d+)/steps/(?P<step>\d+)/complete', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'complete_manual_step' ),
			'permission_callback' => array( __CLASS__, 'can_execute' ),
		) );

		register_rest_route( self::NAMESPACE, '/runs/(?P<id>\d+)/steps/(?P<step>\d+)/uncomplete', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'uncomplete_manual_step' ),
			'permission_callback' => array( __CLASS__, 'can_execute' ),
		) );

		register_rest_route( self::NAMESPACE, '/client-runs/(?P<id>\d+)/steps/(?P<step>\d+)/complete', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'client_complete_step' ),
			'permission_callback' => array( __CLASS__, 'can_client_token_access_run' ),
		) );

		register_rest_route( self::NAMESPACE, '/client-runs/(?P<id>\d+)/steps/(?P<step>\d+)/uncomplete', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'client_uncomplete_step' ),
			'permission_callback' => array( __CLASS__, 'can_client_token_access_run' ),
		) );

		register_rest_route( self::NAMESPACE, '/client-runs/(?P<id>\d+)/steps/(?P<step>\d+)/notes', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'client_add_step_note' ),
			'permission_callback' => array( __CLASS__, 'can_client_token_access_run' ),
		) );

		register_rest_route( self::NAMESPACE, '/runs/(?P<id>\d+)/push-client', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'push_run_to_client' ),
			'permission_callback' => array( __CLASS__, 'can_execute' ),
		) );

		// Audit.
		register_rest_route( self::NAMESPACE, '/audit/meta', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_audit_meta' ),
			'permission_callback' => array( __CLASS__, 'can_view_audit' ),
		) );

		register_rest_route( self::NAMESPACE, '/audit', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_audit_log' ),
			'permission_callback' => array( __CLASS__, 'can_view_audit' ),
		) );

		// Drift.
		register_rest_route( self::NAMESPACE, '/drift/status', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_drift_status' ),
			'permission_callback' => array( __CLASS__, 'can_execute' ),
		) );

		register_rest_route( self::NAMESPACE, '/drift/verify', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'verify_drift' ),
			'permission_callback' => array( __CLASS__, 'can_execute' ),
		) );

		register_rest_route( self::NAMESPACE, '/drift/verify/(?P<id>\d+)', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'verify_drift_site' ),
			'permission_callback' => array( __CLASS__, 'can_execute' ),
		) );

		// Templates.
		register_rest_route( self::NAMESPACE, '/templates', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_templates' ),
			'permission_callback' => array( __CLASS__, 'can_edit_checklists' ),
		) );

		register_rest_route( self::NAMESPACE, '/templates/vault', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_vault' ),
				'permission_callback' => array( __CLASS__, 'can_edit_checklists' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'save_to_vault' ),
				'permission_callback' => array( __CLASS__, 'can_edit_checklists' ),
			),
		) );

		register_rest_route( self::NAMESPACE, '/templates/(?P<slug>[a-z0-9\-_]+)/clone', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'clone_template' ),
			'permission_callback' => array( __CLASS__, 'can_edit_checklists' ),
		) );

		// Integrations.
		register_rest_route( self::NAMESPACE, '/integrations', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_integrations' ),
			'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
		) );

		register_rest_route( self::NAMESPACE, '/integrations/(?P<slug>[a-z0-9\-_]+)/sync', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'sync_integration' ),
			'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
		) );

		register_rest_route( self::NAMESPACE, '/integrations/(?P<slug>[a-z0-9\-_]+)/preview', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'preview_integration_sync' ),
				'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'preview_integration_sync' ),
				'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
			),
		) );

		register_rest_route( self::NAMESPACE, '/integrations/(?P<slug>[a-z0-9\-_]+)/push', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'push_integration' ),
			'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
		) );

		register_rest_route( self::NAMESPACE, '/integrations/telemetry-rules', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'get_telemetry_rules' ),
				'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
			),
			array(
				'methods'             => 'PUT',
				'callback'            => array( __CLASS__, 'update_telemetry_rules' ),
				'permission_callback' => array( __CLASS__, 'can_manage_settings' ),
			),
		) );
	}

	// Permission callbacks.
	public static function can_view_dashboard() {
		return LAUNCHDEK_Capabilities::current_user_can( LAUNCHDEK_Capabilities::VIEW_DASHBOARD );
	}

	public static function can_manage_sites() {
		return LAUNCHDEK_Capabilities::current_user_can( LAUNCHDEK_Capabilities::MANAGE_SITES );
	}

	public static function can_edit_checklists() {
		return LAUNCHDEK_Capabilities::current_user_can( LAUNCHDEK_Capabilities::EDIT_CHECKLISTS );
	}

	public static function can_execute() {
		return LAUNCHDEK_Capabilities::current_user_can( LAUNCHDEK_Capabilities::EXECUTE_CHECKLISTS );
	}

	public static function can_view_audit() {
		return LAUNCHDEK_Capabilities::current_user_can( LAUNCHDEK_Capabilities::VIEW_AUDIT );
	}

	public static function can_manage_settings() {
		return LAUNCHDEK_Capabilities::current_user_can( LAUNCHDEK_Capabilities::MANAGE_SETTINGS );
	}

	/**
	 * Validate run token sent by the optional client agent.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public static function can_client_token_access_run( $request ) {
		$run_id = absint( $request['id'] );
		$token  = (string) $request->get_header( 'X-LaunchDek-Run-Token' );

		if ( '' === $token ) {
			$token = sanitize_text_field( (string) $request->get_param( 'client_token' ) );
		}

		if ( '' === $token ) {
			return false;
		}

		$stored = LAUNCHDEK_Run_Repository::ensure_client_token( $run_id );

		if ( '' === $stored ) {
			return false;
		}

		return hash_equals( $stored, $token );
	}

	// Dashboard handlers.
	public static function get_dashboard_stats() {
		return rest_ensure_response( LAUNCHDEK_Dashboard_Cache::get_stats() );
	}

	public static function get_connection_ticker() {
		return rest_ensure_response( LAUNCHDEK_Dashboard_Cache::get_connection_counts() );
	}

	public static function get_log_feed( $request ) {
		$detailed = filter_var( $request->get_param( 'detailed' ), FILTER_VALIDATE_BOOLEAN );

		if ( $detailed ) {
			$include_details = filter_var( $request->get_param( 'include_details' ), FILTER_VALIDATE_BOOLEAN );
			if ( null === $request->get_param( 'include_details' ) ) {
				$include_details = false;
			}

			return rest_ensure_response(
				LAUNCHDEK_Audit_Log::query(
					LAUNCHDEK_Audit_Log::list_query_args_from_input(
						array(
							'user_id'         => $request->get_param( 'user_id' ) ?: 0,
							'site_id'         => $request->get_param( 'site_id' ) ?: 0,
							'action'          => $request->get_param( 'action' ) ?: '',
							'search'          => $request->get_param( 'search' ) ?: '',
							'status'          => $request->get_param( 'status' ) ?: '',
							'date_from'       => $request->get_param( 'date_from' ) ?: '',
							'date_to'         => $request->get_param( 'date_to' ) ?: '',
							'limit'           => $request->get_param( 'limit' ) ?: LAUNCHDEK_Audit_Log::LIST_DEFAULT_LIMIT,
							'offset'          => $request->get_param( 'offset' ) ?: 0,
							'include_details' => $include_details,
						)
					)
				)
			);
		}

		$limit = min( LAUNCHDEK_Dashboard_Cache::FEED_LIMIT, max( 1, absint( $request->get_param( 'limit' ) ?: LAUNCHDEK_Dashboard_Cache::FEED_LIMIT ) ) );
		return rest_ensure_response( LAUNCHDEK_Dashboard_Cache::get_feed( $limit ) );
	}

	/**
	 * Fetch a single audit log entry (includes raw details).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_log_entry( $request ) {
		$id = absint( $request['id'] );

		$details_only = filter_var( $request->get_param( 'details_only' ), FILTER_VALIDATE_BOOLEAN );
		if ( null === $request->get_param( 'details_only' ) ) {
			$details_only = false;
		}

		if ( $details_only ) {
			$details = LAUNCHDEK_Audit_Log::get_decoded_details( $id );
			if ( null === $details ) {
				return new WP_Error( 'not_found', __( 'Activity log entry not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
			}

			return rest_ensure_response(
				array(
					'id'          => $id,
					'details'     => $details,
					'has_details' => ! empty( $details ),
				)
			);
		}

		$entry = LAUNCHDEK_Audit_Log::find( $id, true );
		if ( ! $entry ) {
			return new WP_Error( 'not_found', __( 'Activity log entry not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $entry );
	}

	/**
	 * Completed step timeline for a checklist run (Activity Logs UI).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_log_run_steps( $request ) {
		$run_id = absint( $request['run_id'] );

		return rest_ensure_response(
			array(
				'run_id' => $run_id,
				'steps'  => LAUNCHDEK_Audit_Log::get_run_steps_timeline( $run_id ),
			)
		);
	}

	/**
	 * Persist onboarding dismissal.
	 *
	 * @return WP_REST_Response
	 */
	public static function dismiss_onboarding() {
		$settings                         = LAUNCHDEK_Settings::get();
		$settings['onboarding_dismissed'] = true;
		update_option( LAUNCHDEK_Settings::OPTION_NAME, $settings );

		return rest_ensure_response( array( 'dismissed' => true ) );
	}

	/**
	 * Clear onboarding dismissal so the wizard can be shown again.
	 *
	 * @return WP_REST_Response
	 */
	public static function reset_onboarding() {
		$settings                         = LAUNCHDEK_Settings::get();
		$settings['onboarding_dismissed'] = false;
		update_option( LAUNCHDEK_Settings::OPTION_NAME, $settings );

		return rest_ensure_response( array( 'dismissed' => false ) );
	}

	/**
	 * Update the client checklist panel heading from the Checklists builder.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response
	 */
	public static function update_client_panel_title( $request ) {
		$data  = $request->get_json_params();
		$title = isset( $data['title'] ) ? $data['title'] : '';

		return rest_ensure_response(
			array(
				'title' => LAUNCHDEK_Settings::save_client_panel_title( $title ),
			)
		);
	}

	// Sites handlers.
	public static function get_sites( $request ) {
		$args = array(
			'tag'        => $request->get_param( 'tag' ) ?: '',
			'group_type' => $request->get_param( 'group_type' ) ?: '',
			'health'     => $request->get_param( 'health' ) ?: '',
		);

		return rest_ensure_response( LAUNCHDEK_Dashboard_Cache::get_sites_list( $args ) );
	}

	public static function get_site( $request ) {
		$site = LAUNCHDEK_Site_Repository::find( absint( $request['id'] ) );
		if ( ! $site ) {
			return new WP_Error( 'not_found', __( 'Site not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $site );
	}

	/**
	 * List checklist runs for a registered site.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_site_runs( $request ) {
		$site_id = absint( $request['id'] );
		$site    = LAUNCHDEK_Site_Repository::find( $site_id );

		if ( ! $site ) {
			return new WP_Error( 'not_found', __( 'Site not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}

		$limit  = absint( $request->get_param( 'limit' ) );
		$offset = absint( $request->get_param( 'offset' ) );

		if ( $limit < 1 ) {
			$limit = 25;
		}

		return rest_ensure_response(
			LAUNCHDEK_Run_Repository::list_for_site_history(
				$site_id,
				array(
					'status' => sanitize_key( $request->get_param( 'status' ) ?: '' ),
					'limit'  => min( 200, $limit ),
					'offset' => $offset,
				)
			)
		);
	}

	public static function create_site( $request ) {
		$data = $request->get_json_params();
		if ( empty( $data['url'] ) || empty( $data['admin_username'] ) || empty( $data['app_password'] ) ) {
			return new WP_Error( 'missing_fields', __( 'URL, username, and app password are required.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 400 ) );
		}
		$id = LAUNCHDEK_Site_Repository::create( $data );
		if ( ! $id ) {
			return new WP_Error( 'create_failed', __( 'Failed to create site.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 500 ) );
		}

		$site  = LAUNCHDEK_Site_Repository::find( $id );
		$panel = LAUNCHDEK_Mu_Plugin_Installer::ensure_installed( $id );
		$site['client_panel'] = is_wp_error( $panel )
			? array(
				'success' => false,
				'message' => $panel->get_error_message(),
			)
			: $panel;

		return rest_ensure_response( $site );
	}

	public static function update_site( $request ) {
		$id = absint( $request['id'] );
		if ( ! LAUNCHDEK_Site_Repository::find( $id ) ) {
			return new WP_Error( 'not_found', __( 'Site not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}
		$data = $request->get_json_params();
		LAUNCHDEK_Site_Repository::update( $id, $data );

		$site = LAUNCHDEK_Site_Repository::find( $id );

		if ( ! empty( $data['app_password'] ) || ! empty( $data['url'] ) || ! empty( $data['admin_username'] ) ) {
			$panel = LAUNCHDEK_Mu_Plugin_Installer::ensure_installed( $id );
			$site['client_panel'] = is_wp_error( $panel )
				? array(
					'success' => false,
					'message' => $panel->get_error_message(),
				)
				: $panel;
		}

		return rest_ensure_response( $site );
	}

	public static function delete_site( $request ) {
		$id = absint( $request['id'] );

		if ( ! LAUNCHDEK_Site_Repository::find( $id ) ) {
			return new WP_Error( 'not_found', __( 'Site not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}

		if ( ! LAUNCHDEK_Site_Repository::delete( $id ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete site.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public static function test_site_connection( $request ) {
		return rest_ensure_response( LAUNCHDEK_Connection_Tester::test_site( absint( $request['id'] ) ) );
	}

	/**
	 * Retry client panel install for a stored site.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function install_site_panel( $request ) {
		$id = absint( $request['id'] );

		if ( ! LAUNCHDEK_Site_Repository::find( $id ) ) {
			return new WP_Error( 'not_found', __( 'Site not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}

		$panel = LAUNCHDEK_Mu_Plugin_Installer::ensure_installed( $id );

		if ( is_wp_error( $panel ) ) {
			return rest_ensure_response(
				array(
					'success' => false,
					'message' => $panel->get_error_message(),
				)
			);
		}

		return rest_ensure_response( $panel );
	}

	/**
	 * Get remote auto-capture status for a site.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_site_capture( $request ) {
		$id = absint( $request['id'] );

		if ( ! LAUNCHDEK_Site_Repository::find( $id ) ) {
			return new WP_Error( 'not_found', __( 'Site not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}

		$result = LAUNCHDEK_Auto_Capture::get_status( $id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Start or stop remote auto-capture for a site.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function update_site_capture( $request ) {
		$id   = absint( $request['id'] );
		$data = $request->get_json_params();
		$action = is_array( $data ) ? sanitize_key( $data['action'] ?? '' ) : '';

		if ( ! LAUNCHDEK_Site_Repository::find( $id ) ) {
			return new WP_Error( 'not_found', __( 'Site not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}

		if ( 'start' === $action ) {
			$result = LAUNCHDEK_Auto_Capture::start( $id );
		} elseif ( 'stop' === $action ) {
			$result = LAUNCHDEK_Auto_Capture::stop( $id );
		} else {
			return new WP_Error(
				'launchdek_capture_action',
				__( 'Invalid capture action.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => 400 )
			);
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Clear captured steps on a remote site.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function clear_site_capture( $request ) {
		$id = absint( $request['id'] );

		if ( ! LAUNCHDEK_Site_Repository::find( $id ) ) {
			return new WP_Error( 'not_found', __( 'Site not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}

		$result = LAUNCHDEK_Auto_Capture::clear( $id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Return the one-time client panel bootstrap file bundled with LaunchDek.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_panel_bootstrap() {
		$path = LAUNCHDEK_PLUGIN_DIR . 'mu-plugin/launchdek-client.php';

		if ( ! is_readable( $path ) ) {
			return new WP_Error(
				'launchdek_bootstrap_missing',
				__( 'Client panel bootstrap file is missing from this LaunchDek install.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => 500 )
			);
		}

		return rest_ensure_response(
			array(
				'filename'    => 'launchdek-client.php',
				'target_path' => 'wp-content/mu-plugins/launchdek-client.php',
				'contents'    => file_get_contents( $path ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			)
		);
	}

	public static function test_raw_connection( $request ) {
		$data = $request->get_json_params();
		return rest_ensure_response( LAUNCHDEK_Connection_Tester::test_raw(
			$data['url'] ?? '',
			$data['admin_username'] ?? '',
			$data['app_password'] ?? ''
		) );
	}

	public static function get_all_tags() {
		return rest_ensure_response( LAUNCHDEK_Site_Repository::get_all_tags() );
	}

	/**
	 * List site tag groups for filters and the site editor.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_site_groups() {
		return rest_ensure_response( LAUNCHDEK_Settings::get_site_group_catalog() );
	}

	/**
	 * Create a custom site tag group.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_site_group( $request ) {
		$label  = $request->get_param( 'label' );
		$result = LAUNCHDEK_Settings::add_custom_site_group( is_string( $label ) ? $label : '' );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'group'  => $result,
				'groups' => LAUNCHDEK_Settings::get_site_group_catalog(),
			)
		);
	}

	// Checklist handlers.
	public static function get_checklists( $request ) {
		$args = array( 'is_template' => null );
		if ( null !== $request->get_param( 'is_template' ) ) {
			$args['is_template'] = rest_sanitize_boolean( $request->get_param( 'is_template' ) );
		}
		if ( null !== $request->get_param( 'is_vault' ) ) {
			$args['is_vault'] = rest_sanitize_boolean( $request->get_param( 'is_vault' ) );
		}
		if ( rest_sanitize_boolean( $request->get_param( 'summary' ) ) ) {
			return rest_ensure_response( LAUNCHDEK_Checklist_Repository::summary_list( $args ) );
		}
		return rest_ensure_response( LAUNCHDEK_Checklist_Repository::all( $args ) );
	}

	public static function get_checklist( $request ) {
		$wf = LAUNCHDEK_Checklist_Repository::find( absint( $request['id'] ) );
		if ( ! $wf ) {
			return new WP_Error( 'not_found', __( 'Checklist not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $wf );
	}

	public static function create_checklist( $request ) {
		$data = self::parse_checklist_body( $request );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$valid = self::validate_custom_checklist_payload( $data );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$data['is_template'] = false;
		$data['is_vault']    = false;

		$id = LAUNCHDEK_Checklist_Repository::create( $data );
		if ( ! $id ) {
			return new WP_Error( 'create_failed', __( 'Failed to create checklist.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( LAUNCHDEK_Checklist_Repository::find( $id ) );
	}

	public static function update_checklist( $request ) {
		$id       = absint( $request['id'] );
		$existing = LAUNCHDEK_Checklist_Repository::find( $id );

		if ( ! $existing ) {
			return new WP_Error( 'not_found', __( 'Checklist not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}

		if ( ! empty( $existing['is_template'] ) && ! empty( $existing['template_slug'] ) ) {
			return new WP_Error(
				'read_only_template',
				__( 'Built-in templates cannot be edited. Clone one to create a custom checklist.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => 403 )
			);
		}

		$data = self::parse_checklist_body( $request );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$valid = self::validate_custom_checklist_payload( $data );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		unset( $data['is_template'], $data['is_vault'] );

		if ( ! LAUNCHDEK_Checklist_Repository::update( $id, $data ) ) {
			return new WP_Error( 'update_failed', __( 'Failed to update checklist.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( LAUNCHDEK_Checklist_Repository::find( $id ) );
	}

	/**
	 * Parse and validate checklist JSON request body.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return array|WP_Error
	 */
	protected static function parse_checklist_body( $request ) {
		$data = $request->get_json_params();

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'invalid_body',
				__( 'Invalid checklist data.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => 400 )
			);
		}

		return $data;
	}

	/**
	 * Validate payload for custom checklist create/update.
	 *
	 * @param array $data Checklist payload.
	 * @return true|WP_Error
	 */
	protected static function validate_custom_checklist_payload( $data ) {
		$title = trim( (string) ( $data['title'] ?? '' ) );

		if ( '' === $title ) {
			return new WP_Error(
				'missing_title',
				__( 'Checklist title is required.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	public static function delete_checklist( $request ) {
		if ( ! LAUNCHDEK_Checklist_Repository::delete( absint( $request['id'] ) ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete checklist.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public static function import_checklist( $request ) {
		$data = $request->get_json_params();

		if ( ! empty( $data['url'] ) ) {
			$response = wp_remote_get( esc_url_raw( $data['url'] ), array( 'timeout' => 15 ) );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_json', __( 'Invalid checklist JSON.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 400 ) );
		}

		$id = LAUNCHDEK_Checklist_Repository::import( $data );
		if ( ! $id ) {
			return new WP_Error( 'import_failed', __( 'Import failed.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( LAUNCHDEK_Checklist_Repository::find( $id ) );
	}

	public static function export_checklist( $request ) {
		$export = LAUNCHDEK_Checklist_Repository::export( absint( $request['id'] ) );
		if ( ! $export ) {
			return new WP_Error( 'not_found', __( 'Checklist not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $export );
	}

	public static function infer_deep_link( $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$path = LAUNCHDEK_Admin_Deep_Links::resolve_path(
			array(
				'deep_link'    => '',
				'type'         => sanitize_key( $data['type'] ?? 'manual' ),
				'title'        => sanitize_text_field( $data['title'] ?? '' ),
				'instructions' => sanitize_textarea_field( $data['instructions'] ?? '' ),
				'api'          => is_array( $data['api'] ?? null ) ? $data['api'] : array(),
			)
		);

		return rest_ensure_response( array( 'path' => $path ) );
	}

	public static function validate_api_step( $request ) {
		$data = $request->get_json_params();
		return rest_ensure_response( LAUNCHDEK_Payload_Mapper::validate( $data ) );
	}

	// Run handlers.
	public static function start_batch_runs( $request ) {
		$data       = $request->get_json_params();
		$checklist_id = absint( $data['checklist_id'] ?? 0 );
		$site_ids   = array_map( 'absint', (array) ( $data['site_ids'] ?? array() ) );
		$site_ids   = array_values( array_filter( $site_ids ) );

		if ( ! $checklist_id || empty( $site_ids ) ) {
			return new WP_Error(
				'missing_params',
				__( 'Checklist ID and at least one site ID are required.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response( LAUNCHDEK_Checklist_Runner::start_batch( $checklist_id, $site_ids ) );
	}

	public static function get_runs( $request ) {
		return rest_ensure_response( LAUNCHDEK_Run_Repository::all( array(
			'status'  => sanitize_key( $request->get_param( 'status' ) ?: '' ),
			'site_id' => absint( $request->get_param( 'site_id' ) ?: 0 ),
		) ) );
	}

	public static function get_run( $request ) {
		$run = LAUNCHDEK_Run_Repository::find( absint( $request['id'] ) );
		if ( ! $run ) {
			return new WP_Error( 'not_found', __( 'Run not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $run );
	}

	/**
	 * Archive a checklist run (hide from default site history).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function archive_run( $request ) {
		$id  = absint( $request['id'] );
		$run = LAUNCHDEK_Run_Repository::find( $id );

		if ( ! $run ) {
			return new WP_Error( 'not_found', __( 'Run not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}

		if ( ! LAUNCHDEK_Run_Repository::archive( $id ) ) {
			return new WP_Error( 'archive_failed', __( 'Failed to archive run.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'archived' => true, 'id' => $id ) );
	}

	/**
	 * Permanently delete a checklist run.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function delete_run( $request ) {
		$id  = absint( $request['id'] );
		$run = LAUNCHDEK_Run_Repository::find( $id );

		if ( ! $run ) {
			return new WP_Error( 'not_found', __( 'Run not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}

		if ( ! LAUNCHDEK_Run_Repository::delete( $id ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete run.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'deleted' => true, 'id' => $id ) );
	}

	public static function start_run( $request ) {
		$data   = $request->get_json_params();
		$result = LAUNCHDEK_Checklist_Runner::start(
			absint( $data['checklist_id'] ?? 0 ),
			absint( $data['site_id'] ?? 0 )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$push_to_client = ! array_key_exists( 'push_to_client', $data ) || rest_sanitize_boolean( $data['push_to_client'] );

		if ( $push_to_client && ! empty( $result['run_id'] ) ) {
			$client_push = LAUNCHDEK_Client_Push::push_run( (int) $result['run_id'] );

			if ( is_wp_error( $client_push ) ) {
				$result['client_push'] = array(
					'success' => false,
					'message' => $client_push->get_error_message(),
				);
			} else {
				$result['client_push'] = array(
					'success' => true,
					'message' => $client_push['message'],
				);
				$result['run']         = $client_push['run'];
			}
		}

		return rest_ensure_response( $result );
	}

	public static function execute_next_step( $request ) {
		$run_id = absint( $request['id'] );
		$result = LAUNCHDEK_Checklist_Runner::execute_next( $run_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		LAUNCHDEK_Client_Push::sync_run( $run_id );
		return rest_ensure_response( $result );
	}

	public static function execute_auto_steps( $request ) {
		$run_id = absint( $request['id'] );
		$result = LAUNCHDEK_Checklist_Runner::execute_all_auto( $run_id );
		LAUNCHDEK_Client_Push::sync_run( $run_id );
		return rest_ensure_response( $result );
	}

	public static function complete_manual_step( $request ) {
		$run_id = absint( $request['id'] );
		$ok     = LAUNCHDEK_Step_Executor::mark_manual_complete( $run_id, absint( $request['step'] ) );
		LAUNCHDEK_Checklist_Runner::check_completion( $run_id );
		LAUNCHDEK_Client_Push::sync_run( $run_id );

		return rest_ensure_response(
			array(
				'success' => $ok,
				'run'     => LAUNCHDEK_Run_Repository::find( $run_id ),
			)
		);
	}

	/**
	 * Revert a manually completed step on the hub.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public static function uncomplete_manual_step( $request ) {
		$run_id = absint( $request['id'] );
		$ok     = LAUNCHDEK_Step_Executor::mark_manual_incomplete( $run_id, absint( $request['step'] ) );
		LAUNCHDEK_Checklist_Runner::check_completion( $run_id );
		LAUNCHDEK_Client_Push::sync_run( $run_id );

		return rest_ensure_response(
			array(
				'success' => $ok,
				'run'     => LAUNCHDEK_Run_Repository::find( $run_id ),
			)
		);
	}

	/**
	 * Complete a manual step from the optional client agent.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function client_complete_step( $request ) {
		$data = $request->get_json_params();
		$meta = is_array( $data ) ? $data : array();

		$result = LAUNCHDEK_Client_Push::complete_client_step(
			absint( $request['id'] ),
			absint( $request['step'] ),
			$meta,
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Revert a completed manual step from the optional client agent.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function client_uncomplete_step( $request ) {
		$data = $request->get_json_params();
		$meta = is_array( $data ) ? $data : array();

		$result = LAUNCHDEK_Client_Push::uncomplete_client_step(
			absint( $request['id'] ),
			absint( $request['step'] ),
			$meta,
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Add a step note from the optional client panel.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function client_add_step_note( $request ) {
		$data = $request->get_json_params();
		$meta = is_array( $data ) ? $data : array();

		$result = LAUNCHDEK_Client_Push::add_client_step_note(
			absint( $request['id'] ),
			absint( $request['step'] ),
			$meta,
			true
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Push or refresh a run on the client admin panel.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function push_run_to_client( $request ) {
		$result = LAUNCHDEK_Client_Push::push_run( absint( $request['id'] ), false, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	public static function get_audit_meta() {
		return rest_ensure_response( LAUNCHDEK_Audit_Log::get_filter_meta() );
	}

	// Audit handlers.
	public static function get_audit_log( $request ) {
		return rest_ensure_response( LAUNCHDEK_Audit_Log::query( array(
			'user_id'   => absint( $request->get_param( 'user_id' ) ?: 0 ),
			'site_id'   => absint( $request->get_param( 'site_id' ) ?: 0 ),
			'action'    => sanitize_key( $request->get_param( 'action' ) ?: '' ),
			'search'    => sanitize_text_field( $request->get_param( 'search' ) ?: '' ),
			'status'    => sanitize_key( $request->get_param( 'status' ) ?: '' ),
			'date_from' => sanitize_text_field( $request->get_param( 'date_from' ) ?: '' ),
			'date_to'   => sanitize_text_field( $request->get_param( 'date_to' ) ?: '' ),
			'limit'     => absint( $request->get_param( 'limit' ) ?: 50 ),
			'offset'    => absint( $request->get_param( 'offset' ) ?: 0 ),
		) ) );
	}

	// Drift handlers.
	public static function get_drift_status() {
		return rest_ensure_response( LAUNCHDEK_Drift_Verifier::get_status_summary() );
	}

	public static function verify_drift() {
		return rest_ensure_response( LAUNCHDEK_Drift_Verifier::verify_all() );
	}

	public static function verify_drift_site( $request ) {
		return rest_ensure_response( LAUNCHDEK_Drift_Verifier::verify_site( absint( $request['id'] ) ) );
	}

	// Template handlers.
	public static function get_templates() {
		return rest_ensure_response( array(
			'builtin'    => LAUNCHDEK_Templates::get_catalog(),
			'categories' => LAUNCHDEK_Templates::get_categories(),
		) );
	}

	public static function get_vault() {
		return rest_ensure_response(
			LAUNCHDEK_Checklist_Repository::summary_list(
				array(
					'is_vault' => true,
					'limit'    => 100,
				)
			)
		);
	}

	public static function save_to_vault( $request ) {
		$data = $request->get_json_params();
		$id   = absint( $data['checklist_id'] ?? 0 );
		if ( ! $id ) {
			return new WP_Error( 'missing_id', __( 'Checklist ID required.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 400 ) );
		}
		LAUNCHDEK_Templates::save_to_vault( $id );
		return rest_ensure_response( LAUNCHDEK_Checklist_Repository::find( $id ) );
	}

	public static function clone_template( $request ) {
		$slug     = sanitize_key( $request['slug'] );
		$template = LAUNCHDEK_Templates::get_builtin_by_slug( $slug );

		if ( ! $template ) {
			return new WP_Error( 'not_found', __( 'Template not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}

		$id = LAUNCHDEK_Templates::clone_template( $template );
		if ( ! $id ) {
			return new WP_Error( 'clone_failed', __( 'Could not clone template.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 500 ) );
		}

		return rest_ensure_response( LAUNCHDEK_Checklist_Repository::find( $id ) );
	}

	// Integration handlers.
	public static function get_integrations() {
		return rest_ensure_response( LAUNCHDEK_Integrations::get_statuses() );
	}

	public static function sync_integration( $request ) {
		$slug = sanitize_key( $request['slug'] );
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = array();
		}

		$result = ! empty( $data['dry_run'] )
			? LAUNCHDEK_Integration_Sync::preview( $slug )
			: LAUNCHDEK_Integration_Sync::sync( $slug );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	public static function preview_integration_sync( $request ) {
		$slug = sanitize_key( $request['slug'] );
		$result = LAUNCHDEK_Integration_Sync::preview( $slug );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	public static function push_integration( $request ) {
		$integration = LAUNCHDEK_Integrations::get( sanitize_key( $request['slug'] ) );
		if ( ! $integration ) {
			return new WP_Error( 'not_found', __( 'Integration not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		return rest_ensure_response( $integration->push_agent( $data['site_ids'] ?? array() ) );
	}

	public static function get_telemetry_rules() {
		return rest_ensure_response(
			array(
				'rules'  => LAUNCHDEK_Integrations::get_telemetry_rules(),
				'fields' => LAUNCHDEK_Integrations::get_launchdek_fields(),
			)
		);
	}

	public static function update_telemetry_rules( $request ) {
		$data  = $request->get_json_params();
		$rules = isset( $data['rules'] ) && is_array( $data['rules'] ) ? $data['rules'] : array();

		return rest_ensure_response(
			array(
				'rules'  => LAUNCHDEK_Integrations::save_telemetry_rules( $rules ),
				'fields' => LAUNCHDEK_Integrations::get_launchdek_fields(),
			)
		);
	}
}
