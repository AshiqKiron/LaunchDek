<?php
/**
 * Lightweight admin-ajax handlers for memory-constrained installs.
 *
 * Integrations actions use admin-ajax instead of REST so sites with many
 * plugins are not forced to bootstrap the full REST route registry on each sync.
 *
 * All handlers register on Community and Pro builds (see CHECKLISTDEK_Plugin::run()).
 * Do not gate registration behind checklistdek_includes_pro_package().
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin AJAX handlers.
 */
class CHECKLISTDEK_Admin_Ajax {

	/**
	 * Register admin-ajax actions (Community and Pro — same action list).
	 *
	 * @return void
	 */
	public static function register() {
		$actions = array(
			'checklistdek_get_integrations',
			'checklistdek_integration_sync',
			'checklistdek_integration_push',
			'checklistdek_save_wp_umbrella_token',
			'checklistdek_get_telemetry_rules',
			'checklistdek_save_telemetry_rules',
			'checklistdek_get_site_runs',
			'checklistdek_get_activity_logs',
		);

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( __CLASS__, str_replace( 'checklistdek_', 'handle_', $action ) ) );
		}
	}

	/**
	 * Read the wp_rest nonce from admin-ajax POST or the REST X-WP-Nonce header.
	 *
	 * @return string
	 */
	protected static function get_request_nonce() {
		if ( isset( $_POST['nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Validated via wp_verify_nonce in verify_ajax_nonce().
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Validated via wp_verify_nonce in verify_ajax_nonce().
			$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ) );
			if ( '' !== $nonce ) {
				return $nonce;
			}
		}

		if ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized on the next line.
			$header_nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via sanitize_text_field.
			if ( '' !== $header_nonce ) {
				return $header_nonce;
			}
		}

		if ( isset( $_REQUEST['nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Validated via wp_verify_nonce in verify_ajax_nonce().
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Validated via wp_verify_nonce in verify_ajax_nonce().
			return sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) );
		}

		return '';
	}

	/**
	 * Verify the admin-ajax REST nonce (returns JSON error instead of dying with -1).
	 *
	 * @return void
	 */
	protected static function verify_ajax_nonce() {
		if ( ! wp_verify_nonce( self::get_request_nonce(), 'wp_rest' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Your session has expired. Please reload the page and try again.', 'checklistdek' ),
				),
				403
			);
		}
	}

	/**
	 * Verify nonce and settings capability.
	 *
	 * @return void
	 */
	protected static function verify_request() {
		self::verify_ajax_nonce();

		if ( ! CHECKLISTDEK_Capabilities::current_user_can( CHECKLISTDEK_Capabilities::MANAGE_SETTINGS ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to manage integrations.', 'checklistdek' ),
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
		self::verify_ajax_nonce();

		if ( ! CHECKLISTDEK_Capabilities::current_user_can( CHECKLISTDEK_Capabilities::MANAGE_SITES ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to manage sites.', 'checklistdek' ),
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
		self::verify_ajax_nonce();

		if ( ! CHECKLISTDEK_Capabilities::current_user_can( CHECKLISTDEK_Capabilities::VIEW_DASHBOARD ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to view activity logs.', 'checklistdek' ),
				),
				403
			);
		}
	}

	/**
	 * Read a POST field after verify_*() has validated nonce and capability.
	 *
	 * @param string $key     POST key.
	 * @param mixed  $default Default when missing.
	 * @return mixed
	 */
	protected static function post_input( $key, $default = '' ) {
		if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in verify_*() before handlers read POST.
			return $default;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Raw POST; callers sanitize. Nonce verified in verify_*().
		return wp_unslash( $_POST[ $key ] );
	}

	/**
	 * Read a boolean POST flag after verify_*() has validated nonce and capability.
	 *
	 * @param string $key POST key.
	 * @return bool
	 */
	protected static function post_bool( $key ) {
		$value = self::post_input( $key, null );

		if ( null === $value || '' === $value ) {
			return false;
		}

		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Return connector statuses.
	 *
	 * @return void
	 */
	public static function handle_get_integrations() {
		self::verify_request();
		wp_send_json_success( CHECKLISTDEK_Integrations::get_statuses() );
	}

	/**
	 * Sync or preview-sync an integration inventory.
	 *
	 * @return void
	 */
	public static function handle_integration_sync() {
		self::verify_request();

		$slug = sanitize_key( (string) self::post_input( 'slug', '' ) );
		if ( '' === $slug ) {
			wp_send_json_error(
				array(
					'message' => __( 'Integration not found.', 'checklistdek' ),
				),
				400
			);
		}

		$dry_run = self::post_bool( 'dry_run' );
		$result  = $dry_run
			? CHECKLISTDEK_Integration_Sync::preview( $slug )
			: CHECKLISTDEK_Integration_Sync::sync( $slug );

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

		$slug        = sanitize_key( (string) self::post_input( 'slug', '' ) );
		$integration = CHECKLISTDEK_Integrations::get( $slug );

		if ( ! $integration ) {
			wp_send_json_error(
				array(
					'message' => __( 'Integration not found.', 'checklistdek' ),
				),
				404
			);
		}

		$site_ids = array();
		$site_ids_raw = self::post_input( 'site_ids', '' );
		if ( '' !== $site_ids_raw ) {
			$decoded = json_decode( (string) $site_ids_raw, true );
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

		$token_raw = self::post_input( 'token', null );
		$token     = null !== $token_raw ? CHECKLISTDEK_Settings::normalize_wp_umbrella_api_token( (string) $token_raw ) : null;
		$clear     = self::post_bool( 'clear' );
		$payload = '';

		if ( $clear ) {
			$payload = '';
		} elseif ( null !== $token ) {
			$payload = $token;
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'API token is required.', 'checklistdek' ),
				),
				400
			);
		}

		if ( '' !== $payload ) {
			$integration = CHECKLISTDEK_Integrations::get( 'wp-umbrella' );
			if ( $integration instanceof CHECKLISTDEK_Integration_WP_Umbrella ) {
				$verified = $integration->verify_api_token( $payload );
				if ( is_wp_error( $verified ) ) {
					wp_send_json_error(
						array(
							'message' => $verified->get_error_message(),
						),
						400
					);
				}
			}
		}

		$configured = CHECKLISTDEK_Settings::save_wp_umbrella_api_token( $payload );

		wp_send_json_success(
			array(
				'api_token_configured' => $configured,
				'message'              => $configured
					? __( 'WP Umbrella API token saved.', 'checklistdek' )
					: __( 'WP Umbrella API token removed.', 'checklistdek' ),
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
				'rules'  => CHECKLISTDEK_Integrations::get_telemetry_rules(),
				'fields' => CHECKLISTDEK_Integrations::get_checklistdek_fields(),
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
		$rules_raw = self::post_input( 'rules', '' );
		if ( '' !== $rules_raw ) {
			$decoded = json_decode( (string) $rules_raw, true );
			if ( is_array( $decoded ) ) {
				$rules = $decoded;
			}
		}

		wp_send_json_success(
			array(
				'rules'  => CHECKLISTDEK_Integrations::save_telemetry_rules( $rules ),
				'fields' => CHECKLISTDEK_Integrations::get_checklistdek_fields(),
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
			CHECKLISTDEK_Audit_Log::query(
				CHECKLISTDEK_Audit_Log::list_query_args_from_input(
					array(
						'site_id'         => self::post_input( 'site_id', 0 ),
						'status'          => self::post_input( 'status', '' ),
						'search'          => self::post_input( 'search', '' ),
						'date_from'       => self::post_input( 'date_from', '' ),
						'date_to'         => self::post_input( 'date_to', '' ),
						'limit'           => self::post_input( 'limit', CHECKLISTDEK_Audit_Log::LIST_DEFAULT_LIMIT ),
						'offset'          => self::post_input( 'offset', 0 ),
						'include_details' => self::post_input( 'include_details', '0' ),
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

		$site_id = absint( self::post_input( 'site_id', 0 ) );
		$site    = CHECKLISTDEK_Site_Repository::find( $site_id );

		if ( ! $site ) {
			wp_send_json_error(
				array(
					'message' => __( 'Site not found.', 'checklistdek' ),
				),
				404
			);
		}

		$limit  = absint( self::post_input( 'limit', 25 ) );
		$offset = absint( self::post_input( 'offset', 0 ) );

		if ( $limit < 1 ) {
			$limit = 25;
		}

		wp_send_json_success(
			CHECKLISTDEK_Run_Repository::list_for_site_history(
				$site_id,
				array(
					'status' => sanitize_key( (string) self::post_input( 'status', '' ) ),
					'limit'  => min( 200, $limit ),
					'offset' => $offset,
				)
			)
		);
	}
}
