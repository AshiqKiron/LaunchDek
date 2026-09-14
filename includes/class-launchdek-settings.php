<?php
/**
 * Centralized plugin settings — defaults, retrieval, sanitization.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings helper.
 */
class LAUNCHDEK_Settings {

	const OPTION_NAME    = 'launchdek_settings';
	const VERSION_OPTION = 'launchdek_version';
	const SETTINGS_GROUP = 'launchdek_settings_group';
	const CAPABILITY     = 'manage_options';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		$defaults = array(
			'enabled'                      => true,
			'encrypt_credentials'          => true,
			'drift_verification_enabled'   => true,
			'onboarding_dismissed'         => false,
			'slack_webhook'                => '',
			'discord_webhook'              => '',
			'teams_webhook'                => '',
			'notification_events'          => array( 'run_started', 'run_completed', 'run_failed', 'step_failed' ),
			'role_permissions'             => array(),
			'telemetry_sync_rules'         => array(),
		);

		return apply_filters( 'launchdek_settings_defaults', $defaults );
	}

	/**
	 * Get merged plugin settings.
	 *
	 * @return array
	 */
	public static function get() {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, self::get_defaults() );
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param mixed $input Raw settings input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::get_defaults();
		$output   = self::get();

		if ( ! is_array( $input ) ) {
			return $output;
		}

		$checkboxes = array( 'enabled', 'encrypt_credentials', 'drift_verification_enabled', 'onboarding_dismissed' );
		foreach ( $checkboxes as $key ) {
			if ( array_key_exists( $key, $input ) ) {
				$output[ $key ] = (bool) $input[ $key ];
			} elseif ( isset( $_POST['option_page'] ) && self::SETTINGS_GROUP === $_POST['option_page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				$output[ $key ] = false;
			}
		}

		$urls = array( 'slack_webhook', 'discord_webhook', 'teams_webhook' );
		foreach ( $urls as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$output[ $key ] = esc_url_raw( $input[ $key ] );
			}
		}

		if ( isset( $input['notification_events'] ) && is_array( $input['notification_events'] ) ) {
			$output['notification_events'] = array_map( 'sanitize_key', $input['notification_events'] );
		}

		if ( isset( $input['role_permissions'] ) && is_array( $input['role_permissions'] ) ) {
			$clean = array();
			foreach ( $input['role_permissions'] as $cap => $roles ) {
				if ( is_array( $roles ) ) {
					$clean[ sanitize_key( $cap ) ] = array_map( 'sanitize_key', $roles );
				}
			}
			$output['role_permissions'] = $clean;
		}

		if ( isset( $input['telemetry_sync_rules'] ) && is_array( $input['telemetry_sync_rules'] ) ) {
			$clean = array();
			foreach ( $input['telemetry_sync_rules'] as $rule ) {
				if ( ! is_array( $rule ) || empty( $rule['id'] ) ) {
					continue;
				}
				$clean[] = array(
					'id'      => sanitize_key( $rule['id'] ),
					'enabled' => ! empty( $rule['enabled'] ),
				);
			}
			$output['telemetry_sync_rules'] = $clean;
		}

		return apply_filters( 'launchdek_settings_sanitize', $output, $input, $defaults );
	}

	/**
	 * Available notification events.
	 *
	 * @return array
	 */
	public static function get_notification_events() {
		return array(
			'run_started'           => __( 'Checklist run started', LAUNCHDEK_TEXT_DOMAIN ),
			'run_completed'         => __( 'Checklist run completed', LAUNCHDEK_TEXT_DOMAIN ),
			'run_failed'            => __( 'Checklist run failed', LAUNCHDEK_TEXT_DOMAIN ),
			'step_failed'           => __( 'Step failed', LAUNCHDEK_TEXT_DOMAIN ),
			'drift_detected'        => __( 'Configuration drift detected', LAUNCHDEK_TEXT_DOMAIN ),
			'client_step_completed' => __( 'Client completed a checklist step', LAUNCHDEK_TEXT_DOMAIN ),
			'client_note_added'     => __( 'Client added a step note', LAUNCHDEK_TEXT_DOMAIN ),
		);
	}
}
