<?php
/**
 * Lightweight admin-ajax handlers for memory-constrained installs.
 *
 * Integrations actions use admin-ajax instead of REST so sites with many
 * plugins are not forced to bootstrap the full REST route registry on each sync.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin AJAX handlers.
 */
class LAUNCHDEK_Admin_Ajax {

	/**
	 * Register admin-ajax actions.
	 *
	 * @return void
	 */
	public static function register() {
		$actions = array(
			'launchdek_get_integrations',
			'launchdek_integration_sync',
			'launchdek_integration_push',
			'launchdek_save_wp_umbrella_token',
			'launchdek_get_telemetry_rules',
			'launchdek_save_telemetry_rules',
			'launchdek_get_site_runs',
			'launchdek_get_activity_logs',
		);

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, str_replace( 'launchdek_', 'handle_', $action ) ) );
		}
	}

	/**
	 * Verify nonce and settings capability.
	 *
	 * @return void
	 */
	protected static function verify_request() {
		check_ajax_referer( 'wp_rest', 'nonce' );

		if ( ! LAUNCHDEK_Capabilities::current_user_can( LAUNCHDEK_Capabilities::MANAGE_SETTINGS ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to manage integrations.', LAUNCHDEK_TEXT_DOMAIN ),
				),
				403
			);
		}
	}

	/**
	 * Verify nonce and site-management capability.
	 *
	 * @return void
	 */
	protected static function verify_sites_request() {
		check_ajax_referer( 'wp_rest', 'nonce' );

		if ( ! LAUNCHDEK_Capabilities::current_user_can( LAUNCHDEK_Capabilities::MANAGE_SITES ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to manage sites.', LAUNCHDEK_TEXT_DOMAIN ),
				),
				403
			);
		}
	}

	/**
	 * Verify nonce and dashboard view capability (Activity Logs).
	 *
	 * @return void
	 */
	protected static function verify_dashboard_request() {
		check_ajax_referer( 'wp_rest', 'nonce' );

		if ( ! LAUNCHDEK_Capabilities::current_user_can( LAUNCHDEK_Capabilities::VIEW_DASHBOARD ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to view activity logs.', LAUNCHDEK_TEXT_DOMAIN ),
				),
				403
			);
		}
	}

	/**
	 * Return connector statuses.
	 *
	 * @return void
	 */
	public static function handle_get_integrations() {
		self::verify_request();
		wp_send_json_success( LAUNCHDEK_Integrations::get_statuses() );
	}

	/**
	 * Sync or preview-sync an integration inventory.
	 *
	 * @return void
	 */
	public static function handle_integration_sync() {
		self::verify_request();

		$slug = sanitize_key( wp_unslash( $_POST['slug'] ?? '' ) );
		if ( '' === $slug ) {
			wp_send_json_error(
				array(
					'message' => __( 'Integration not found.', LAUNCHDEK_TEXT_DOMAIN ),
				),
				400
			);
		}

		$dry_run = ! empty( $_POST['dry_run'] );
		$result  = $dry_run
			? LAUNCHDEK_Integration_Sync::preview( $slug )
			: LAUNCHDEK_Integration_Sync::sync( $slug );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				),
				(int) ( $result->get_error_data()['status'] ?? 400 )
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Push the client panel through an integration connector.
	 *
	 * @return void
	 */
	public static function handle_integration_push() {
		self::verify_request();

		$slug = sanitize_key( wp_unslash( $_POST['slug'] ?? '' ) );
		$integration = LAUNCHDEK_Integrations::get( $slug );

		if ( ! $integration ) {
			wp_send_json_error(
				array(
					'message' => __( 'Integration not found.', LAUNCHDEK_TEXT_DOMAIN ),
				),
				404
			);
		}

		$site_ids = array();
		if ( ! empty( $_POST['site_ids'] ) ) {
			$decoded = json_decode( wp_unslash( $_POST['site_ids'] ), true );
			if ( is_array( $decoded ) ) {
				$site_ids = array_map( 'absint', $decoded );
			}
		}

		wp_send_json_success( $integration->push_agent( $site_ids ) );
	}

	/**
	 * Save or clear the WP Umbrella Public API token.
	 *
	 * @return void
	 */
	public static function handle_save_wp_umbrella_token() {
		self::verify_request();

		$token   = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : null;
		$clear   = ! empty( $_POST['clear'] );
		$payload = '';

		if ( $clear ) {
			$payload = '';
		} elseif ( null !== $token ) {
			$payload = $token;
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'API token is required.', LAUNCHDEK_TEXT_DOMAIN ),
				),
				400
			);
		}

		$configured = LAUNCHDEK_Settings::save_wp_umbrella_api_token( $payload );

		wp_send_json_success(
			array(
				'api_token_configured' => $configured,
				'message'              => $configured
					? __( 'WP Umbrella API token saved.', LAUNCHDEK_TEXT_DOMAIN )
					: __( 'WP Umbrella API token removed.', LAUNCHDEK_TEXT_DOMAIN ),
			)
		);
	}

	/**
	 * Return telemetry mapping rules.
	 *
	 * @return void
	 */
	public static function handle_get_telemetry_rules() {
		self::verify_request();

		wp_send_json_success(
			array(
				'rules'  => LAUNCHDEK_Integrations::get_telemetry_rules(),
				'fields' => LAUNCHDEK_Integrations::get_launchdek_fields(),
			)
		);
	}

	/**
	 * Save telemetry mapping rule enabled states.
	 *
	 * @return void
	 */
	public static function handle_save_telemetry_rules() {
		self::verify_request();

		$rules = array();
		if ( ! empty( $_POST['rules'] ) ) {
			$decoded = json_decode( wp_unslash( $_POST['rules'] ), true );
			if ( is_array( $decoded ) ) {
				$rules = $decoded;
			}
		}

		wp_send_json_success(
			array(
				'rules'  => LAUNCHDEK_Integrations::save_telemetry_rules( $rules ),
				'fields' => LAUNCHDEK_Integrations::get_launchdek_fields(),
			)
		);
	}

	/**
	 * Paginated activity log feed for the Activity Logs admin page.
	 *
	 * @return void
	 */
	public static function handle_get_activity_logs() {
		self::verify_dashboard_request();

		wp_send_json_success(
			LAUNCHDEK_Audit_Log::query(
				LAUNCHDEK_Audit_Log::list_query_args_from_input(
					array(
						'site_id'         => wp_unslash( $_POST['site_id'] ?? 0 ),
						'status'          => wp_unslash( $_POST['status'] ?? '' ),
						'search'          => wp_unslash( $_POST['search'] ?? '' ),
						'date_from'       => wp_unslash( $_POST['date_from'] ?? '' ),
						'date_to'         => wp_unslash( $_POST['date_to'] ?? '' ),
						'limit'           => wp_unslash( $_POST['limit'] ?? LAUNCHDEK_Audit_Log::LIST_DEFAULT_LIMIT ),
						'offset'          => wp_unslash( $_POST['offset'] ?? 0 ),
						'include_details' => wp_unslash( $_POST['include_details'] ?? '0' ),
					)
				)
			)
		);
	}

	/**
	 * Return checklist run history for a site row expand panel.
	 *
	 * @return void
	 */
	public static function handle_get_site_runs() {
		self::verify_sites_request();

		$site_id = absint( wp_unslash( $_POST['site_id'] ?? 0 ) );
		$site    = LAUNCHDEK_Site_Repository::find( $site_id );

		if ( ! $site ) {
			wp_send_json_error(
				array(
					'message' => __( 'Site not found.', LAUNCHDEK_TEXT_DOMAIN ),
				),
				404
			);
		}

		$limit  = absint( wp_unslash( $_POST['limit'] ?? 25 ) );
		$offset = absint( wp_unslash( $_POST['offset'] ?? 0 ) );

		if ( $limit < 1 ) {
			$limit = 25;
		}

		wp_send_json_success(
			LAUNCHDEK_Run_Repository::list_for_site_history(
				$site_id,
				array(
					'status' => sanitize_key( wp_unslash( $_POST['status'] ?? '' ) ),
					'limit'  => min( 200, $limit ),
					'offset' => $offset,
				)
			)
		);
	}
}
