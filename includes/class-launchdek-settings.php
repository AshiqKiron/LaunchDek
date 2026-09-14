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
			'exclude_options'              => self::get_default_exclude_options(),
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

		if ( array_key_exists( 'exclude_options', $input ) ) {
			$output['exclude_options'] = self::sanitize_exclude_options( $input['exclude_options'] );
		}

		return apply_filters( 'launchdek_settings_sanitize', $output, $input, $defaults );
	}

	/**
	 * Default sensitive WordPress options to block from remote API steps.
	 *
	 * @return array
	 */
	public static function get_default_exclude_options() {
		return array(
			'siteurl',
			'home',
			'admin_email',
		);
	}

	/**
	 * Sanitize exclude option keys from settings input.
	 *
	 * @param mixed $input Raw textarea or array input.
	 * @return array
	 */
	public static function sanitize_exclude_options( $input ) {
		$lines = array();

		if ( is_array( $input ) ) {
			$lines = $input;
		} else {
			$lines = preg_split( '/[\r\n,]+/', (string) $input );
		}

		$clean = array();
		foreach ( $lines as $line ) {
			$key = self::sanitize_option_key( $line );
			if ( '' !== $key ) {
				$clean[] = $key;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Normalize a WordPress option or REST settings field key.
	 *
	 * @param string $key Raw key.
	 * @return string
	 */
	public static function sanitize_option_key( $key ) {
		$key = strtolower( trim( sanitize_text_field( (string) $key ) ) );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}

	/**
	 * Configured exclude option keys.
	 *
	 * @return array
	 */
	public static function get_exclude_options() {
		$settings = self::get();
		$options  = $settings['exclude_options'] ?? self::get_default_exclude_options();

		if ( ! is_array( $options ) ) {
			$options = self::sanitize_exclude_options( $options );
		}

		return self::sanitize_exclude_options( $options );
	}

	/**
	 * REST settings field aliases for common WordPress options.
	 *
	 * @return array
	 */
	public static function get_option_aliases() {
		return array(
			'siteurl'         => array( 'url', 'siteurl' ),
			'home'            => array( 'home' ),
			'admin_email'     => array( 'email', 'admin_email' ),
			'blogname'        => array( 'title', 'blogname' ),
			'blogdescription' => array( 'description', 'blogdescription' ),
			'wplang'          => array( 'language', 'wplang' ),
		);
	}

	/**
	 * Build a lookup table of excluded option keys including aliases.
	 *
	 * @return array
	 */
	public static function get_exclude_options_lookup() {
		$lookup    = array();
		$aliases   = self::get_option_aliases();
		$configured = self::get_exclude_options();

		foreach ( $configured as $key ) {
			$lookup[ $key ] = true;

			if ( isset( $aliases[ $key ] ) ) {
				foreach ( $aliases[ $key ] as $alias ) {
					$lookup[ self::sanitize_option_key( $alias ) ] = true;
				}
			}

			foreach ( $aliases as $canonical => $members ) {
				$canonical_key = self::sanitize_option_key( $canonical );
				$member_keys   = array_map( array( __CLASS__, 'sanitize_option_key' ), $members );

				if ( $key === $canonical_key || in_array( $key, $member_keys, true ) ) {
					$lookup[ $canonical_key ] = true;
					foreach ( $member_keys as $member_key ) {
						$lookup[ $member_key ] = true;
					}
				}
			}
		}

		return $lookup;
	}

	/**
	 * Whether a settings field key is excluded.
	 *
	 * @param string $key Field or option key.
	 * @return bool
	 */
	public static function is_excluded_option( $key ) {
		$key    = self::sanitize_option_key( $key );
		$lookup = self::get_exclude_options_lookup();

		return '' !== $key && ! empty( $lookup[ $key ] );
	}

	/**
	 * Whether a REST route targets WordPress settings.
	 *
	 * @param string $route REST route.
	 * @return bool
	 */
	public static function is_settings_route( $route ) {
		$route = '/' . ltrim( (string) $route, '/' );

		if ( 0 === strpos( $route, '/wp-json' ) ) {
			$route = substr( $route, strlen( '/wp-json' ) );
			$route = '/' . ltrim( $route, '/' );
		}

		return '/wp/v2/settings' === $route;
	}

	/**
	 * Remove excluded keys from a settings API payload.
	 *
	 * @param array $payload Settings payload.
	 * @return array {
	 *     @type array $payload  Filtered payload.
	 *     @type array $stripped Excluded keys removed from the payload.
	 * }
	 */
	public static function filter_settings_payload( array $payload ) {
		$filtered = array();
		$stripped = array();

		foreach ( $payload as $key => $value ) {
			if ( self::is_excluded_option( (string) $key ) ) {
				$stripped[] = (string) $key;
				continue;
			}

			$filtered[ $key ] = $value;
		}

		return array(
			'payload'  => $filtered,
			'stripped' => array_values( array_unique( $stripped ) ),
		);
	}

	/**
	 * Filter excluded settings keys from a checklist API step.
	 *
	 * @param array $step Step definition.
	 * @return array
	 */
	public static function filter_api_step( array $step ) {
		if ( 'api' !== ( $step['type'] ?? '' ) || empty( $step['api'] ) || ! is_array( $step['api'] ) ) {
			return $step;
		}

		$api     = $step['api'];
		$route   = $api['route'] ?? '';
		$method  = strtoupper( $api['method'] ?? 'GET' );
		$payload = $api['payload'] ?? array();

		if ( ! is_array( $payload ) || ! in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) || ! self::is_settings_route( $route ) ) {
			return $step;
		}

		$result = self::filter_settings_payload( $payload );
		if ( empty( $result['payload'] ) && ! empty( $result['stripped'] ) ) {
			return array();
		}

		$step['api']['payload'] = $result['payload'];
		return $step;
	}

	/**
	 * Filter captured or imported checklist steps against excluded options.
	 *
	 * @param array $steps Checklist steps.
	 * @return array
	 */
	public static function filter_checklist_steps( array $steps ) {
		$filtered = array();

		foreach ( $steps as $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}

			$step = self::filter_api_step( $step );
			if ( ! empty( $step ) ) {
				$filtered[] = $step;
			}
		}

		return $filtered;
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
