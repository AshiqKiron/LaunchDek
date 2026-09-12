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

		register_rest_route( self::NAMESPACE, '/onboarding/dismiss', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'dismiss_onboarding' ),
			'permission_callback' => array( __CLASS__, 'can_view_dashboard' ),
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
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'get_run' ),
			'permission_callback' => array( __CLASS__, 'can_execute' ),
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

	// Dashboard handlers.
	public static function get_dashboard_stats() {
		return rest_ensure_response( array(
			'sites_connected'      => LAUNCHDEK_Site_Repository::count(),
			'active_checklists'     => LAUNCHDEK_Checklist_Repository::count_active(),
			'runs_in_progress'     => LAUNCHDEK_Run_Repository::count_by_status( 'running' ),
			'completion_rate'      => LAUNCHDEK_Run_Repository::completion_rate(),
			'healthy_sites'        => count( LAUNCHDEK_Site_Repository::all( array( 'health' => 'healthy' ) ) ),
			'unhealthy_sites'      => count( LAUNCHDEK_Site_Repository::all( array( 'health' => 'unhealthy' ) ) ),
		) );
	}

	public static function get_connection_ticker() {
		$sites = LAUNCHDEK_Site_Repository::all();
		$items = array();

		foreach ( $sites as $site ) {
			$items[] = array(
				'id'     => (int) $site['id'],
				'name'   => $site['name'] ?: $site['url'],
				'status' => $site['health_status'],
				'label'  => self::connection_ticker_label( $site ),
			);
		}

		return rest_ensure_response( $items );
	}

	/**
	 * Human-readable connection status for the dashboard ticker.
	 *
	 * @param array $site Site row.
	 * @return string
	 */
	private static function connection_ticker_label( $site ) {
		if ( 'healthy' === $site['health_status'] ) {
			return __( 'Good', LAUNCHDEK_TEXT_DOMAIN );
		}

		if ( 'unhealthy' === $site['health_status'] ) {
			$error = ! empty( $site['last_error'] ) ? $site['last_error'] : __( 'Connection Error', LAUNCHDEK_TEXT_DOMAIN );

			return sprintf(
				/* translators: %s: error message */
				__( 'Bad - %s', LAUNCHDEK_TEXT_DOMAIN ),
				$error
			);
		}

		return __( 'Unknown', LAUNCHDEK_TEXT_DOMAIN );
	}

	public static function get_log_feed( $request ) {
		$limit = absint( $request->get_param( 'limit' ) ?: 20 );
		return rest_ensure_response( LAUNCHDEK_Audit_Log::get_feed( $limit ) );
	}

	/**
	 * Persist onboarding dismissal.
	 *
	 * @return WP_REST_Response
	 */
	public static function dismiss_onboarding() {
		$settings                           = LAUNCHDEK_Settings::get();
		$settings['onboarding_dismissed']     = true;
		update_option( LAUNCHDEK_Settings::OPTION_NAME, $settings );

		return rest_ensure_response( array( 'dismissed' => true ) );
	}

	// Sites handlers.
	public static function get_sites( $request ) {
		$args = array(
			'tag'        => sanitize_text_field( $request->get_param( 'tag' ) ?: '' ),
			'group_type' => sanitize_key( $request->get_param( 'group_type' ) ?: '' ),
			'health'     => sanitize_key( $request->get_param( 'health' ) ?: '' ),
		);
		return rest_ensure_response( LAUNCHDEK_Site_Repository::all( $args ) );
	}

	public static function get_site( $request ) {
		$site = LAUNCHDEK_Site_Repository::find( absint( $request['id'] ) );
		if ( ! $site ) {
			return new WP_Error( 'not_found', __( 'Site not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $site );
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
		return rest_ensure_response( LAUNCHDEK_Site_Repository::find( $id ) );
	}

	public static function update_site( $request ) {
		$id = absint( $request['id'] );
		if ( ! LAUNCHDEK_Site_Repository::find( $id ) ) {
			return new WP_Error( 'not_found', __( 'Site not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}
		LAUNCHDEK_Site_Repository::update( $id, $request->get_json_params() );
		return rest_ensure_response( LAUNCHDEK_Site_Repository::find( $id ) );
	}

	public static function delete_site( $request ) {
		$id = absint( $request['id'] );
		if ( ! LAUNCHDEK_Site_Repository::delete( $id ) ) {
			return new WP_Error( 'delete_failed', __( 'Failed to delete site.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 500 ) );
		}
		return rest_ensure_response( array( 'deleted' => true ) );
	}

	public static function test_site_connection( $request ) {
		return rest_ensure_response( LAUNCHDEK_Connection_Tester::test_site( absint( $request['id'] ) ) );
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

	// Checklist handlers.
	public static function get_checklists( $request ) {
		$args = array( 'is_template' => null );
		if ( null !== $request->get_param( 'is_template' ) ) {
			$args['is_template'] = rest_sanitize_boolean( $request->get_param( 'is_template' ) );
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

	public static function start_run( $request ) {
		$data = $request->get_json_params();
		$result = LAUNCHDEK_Checklist_Runner::start(
			absint( $data['checklist_id'] ?? 0 ),
			absint( $data['site_id'] ?? 0 )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	public static function execute_next_step( $request ) {
		$result = LAUNCHDEK_Checklist_Runner::execute_next( absint( $request['id'] ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	public static function execute_auto_steps( $request ) {
		return rest_ensure_response( LAUNCHDEK_Checklist_Runner::execute_all_auto( absint( $request['id'] ) ) );
	}

	public static function complete_manual_step( $request ) {
		$ok = LAUNCHDEK_Step_Executor::mark_manual_complete( absint( $request['id'] ), absint( $request['step'] ) );
		LAUNCHDEK_Checklist_Runner::check_completion( absint( $request['id'] ) );
		return rest_ensure_response( array(
			'success' => $ok,
			'run'     => LAUNCHDEK_Run_Repository::find( absint( $request['id'] ) ),
		) );
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
		$builtin = LAUNCHDEK_Templates::get_builtin();
		$db      = LAUNCHDEK_Checklist_Repository::all( array( 'is_template' => true ) );
		return rest_ensure_response( array(
			'builtin'    => $builtin,
			'categories' => LAUNCHDEK_Templates::get_categories(),
			'stored'     => $db,
		) );
	}

	public static function get_vault() {
		return rest_ensure_response( LAUNCHDEK_Templates::get_vault() );
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
		$slug = sanitize_key( $request['slug'] );

		foreach ( LAUNCHDEK_Templates::get_builtin() as $template ) {
			if ( ( $template['template_slug'] ?? '' ) === $slug ) {
				$id = LAUNCHDEK_Templates::clone_template( $template );
				return rest_ensure_response( LAUNCHDEK_Checklist_Repository::find( $id ) );
			}
		}

		return new WP_Error( 'not_found', __( 'Template not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
	}

	// Integration handlers.
	public static function get_integrations() {
		return rest_ensure_response( LAUNCHDEK_Integrations::get_statuses() );
	}

	public static function push_integration( $request ) {
		$integration = LAUNCHDEK_Integrations::get( sanitize_key( $request['slug'] ) );
		if ( ! $integration ) {
			return new WP_Error( 'not_found', __( 'Integration not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}
		$data = $request->get_json_params();
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
