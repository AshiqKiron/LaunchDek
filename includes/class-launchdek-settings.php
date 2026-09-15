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
			'email_notification_events'    => array( 'run_completed' ),
			'email_notification_address'   => '',
			'role_permissions'             => array(),
			'telemetry_sync_rules'         => array(),
			'exclude_options'              => self::get_default_exclude_options(),
			'client_panel_layout'          => 'sidebar',
			'client_panel_title'           => self::get_default_client_panel_title(),
			'wp_umbrella_api_token_enc'    => '',
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

		// onboarding_dismissed is REST-only (POST /onboarding/dismiss|reset) — not a Settings form field.
		$checkboxes = array( 'enabled', 'encrypt_credentials', 'drift_verification_enabled' );
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

		if ( isset( $_POST['option_page'] ) && self::SETTINGS_GROUP === $_POST['option_page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( isset( $input['email_notification_events'] ) && is_array( $input['email_notification_events'] ) ) {
				$output['email_notification_events'] = array_map( 'sanitize_key', $input['email_notification_events'] );
			} else {
				$output['email_notification_events'] = array();
			}
		}

		if ( isset( $input['email_notification_address'] ) ) {
			$output['email_notification_address'] = self::sanitize_email_notification_addresses( $input['email_notification_address'] );
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

		if ( isset( $input['exclude_options'] ) ) {
			$output['exclude_options'] = self::sanitize_exclude_options( $input['exclude_options'] );
		}

		if ( isset( $input['client_panel_layout'] ) ) {
			$output['client_panel_layout'] = self::sanitize_client_panel_layout( $input['client_panel_layout'] );
		}

		if ( isset( $input['client_panel_title'] ) ) {
			$output['client_panel_title'] = self::sanitize_client_panel_title( $input['client_panel_title'] );
		}

		return apply_filters( 'launchdek_settings_sanitize', $output, $input, $defaults );
	}

	/**
	 * Default sensitive settings excluded from remote API steps.
	 *
	 * Uses WordPress REST settings field names (e.g. url, email). Legacy option
	 * names (siteurl, admin_email) are normalized on save.
	 *
	 * @return array
	 */
	public static function get_default_exclude_options() {
		return array(
			'url',
			'email',
			'wp_environment_type',
		);
	}

	/**
	 * Map legacy WordPress option names to REST settings field names.
	 *
	 * @return array
	 */
	public static function get_exclude_option_aliases() {
		return array(
			'siteurl'         => 'url',
			'home'            => 'url',
			'admin_email'     => 'email',
			'blogname'        => 'title',
			'blogdescription' => 'description',
			'blog_public'     => 'blog_public',
		);
	}

	/**
	 * Normalize a single exclude option key.
	 *
	 * @param string $key Raw option or REST field name.
	 * @return string
	 */
	public static function normalize_exclude_option( $key ) {
		$key = sanitize_key( (string) $key );

		if ( '' === $key ) {
			return '';
		}

		$aliases = self::get_exclude_option_aliases();

		return $aliases[ $key ] ?? $key;
	}

	/**
	 * Get the configured exclude-options list (normalized, unique).
	 *
	 * @return array
	 */
	public static function get_exclude_options() {
		$settings = self::get();
		$options  = $settings['exclude_options'] ?? self::get_default_exclude_options();

		if ( ! is_array( $options ) ) {
			$options = self::sanitize_exclude_options( $options );
		}

		return $options;
	}

	/**
	 * Sanitize exclude-options input (array or newline/comma-separated string).
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize_exclude_options( $input ) {
		if ( is_array( $input ) ) {
			$lines = $input;
		} else {
			$lines = preg_split( '/[\r\n,]+/', (string) $input );
		}

		$clean = array();

		foreach ( $lines as $line ) {
			$key = self::normalize_exclude_option( $line );
			if ( '' !== $key ) {
				$clean[] = $key;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Human-readable labels for common excluded settings fields (display order).
	 *
	 * @return array<string, string>
	 */
	public static function get_exclude_option_labels() {
		return array(
			'url'                 => __( 'Site URL', LAUNCHDEK_TEXT_DOMAIN ),
			'email'               => __( 'Admin Email', LAUNCHDEK_TEXT_DOMAIN ),
			'title'               => __( 'Site Title', LAUNCHDEK_TEXT_DOMAIN ),
			'description'         => __( 'Tagline', LAUNCHDEK_TEXT_DOMAIN ),
			'blog_public'         => __( 'Search Engine Visibility', LAUNCHDEK_TEXT_DOMAIN ),
			'wp_environment_type' => __( 'Environment Type', LAUNCHDEK_TEXT_DOMAIN ),
			'site_logo'           => __( 'Site Logo', LAUNCHDEK_TEXT_DOMAIN ),
			'site_icon'           => __( 'Site Icon', LAUNCHDEK_TEXT_DOMAIN ),
		);
	}

	/**
	 * Format exclude-options textarea value (one REST field name per line).
	 *
	 * @param array $options Normalized exclude option keys.
	 * @return string
	 */
	public static function format_exclude_options_textarea( $options ) {
		if ( ! is_array( $options ) ) {
			$options = self::sanitize_exclude_options( $options );
		}

		return implode( "\n", $options );
	}

	/**
	 * Available client checklist panel display layouts.
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function get_client_panel_layouts() {
		return array(
			'sidebar'        => array(
				'label'       => __( 'Right sidebar floater', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Sticky panel on the right edge with a vertical expand tab when collapsed.', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'live_topbar'    => array(
				'label'       => __( 'Live top bar', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Compact progress strip below the admin bar with a slide-down step drawer.', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'bottom_dock'    => array(
				'label'       => __( 'Bottom dock', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Slim progress bar docked above the footer with a slide-up step drawer.', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'floating_pill'  => array(
				'label'       => __( 'Floating pill', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Small progress chip in the bottom-right corner that expands into a compact panel.', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'toast'          => array(
				'label'       => __( 'Notification toast', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Dismissible toast with current-step summary; expands into the full checklist on click.', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'admin_menu'     => array(
				'label'       => __( 'Admin bar flyout', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Hidden until opened from the WordPress admin bar checklist shortcut.', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'fullscreen'     => array(
				'label'       => __( 'Fullscreen overlay', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Modal takeover centered on screen for focused checklist completion.', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'focus_mode'     => array(
				'label'       => __( 'Focus mode', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Single current-step card centered on screen with minimal distractions.', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'inline_metabox' => array(
				'label'       => __( 'Inline metabox', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Embeds the checklist in the post/page editor sidebar metabox area.', LAUNCHDEK_TEXT_DOMAIN ),
			),
		);
	}

	/**
	 * Sanitize a client panel layout slug.
	 *
	 * @param mixed $layout Raw layout input.
	 * @return string
	 */
	public static function sanitize_client_panel_layout( $layout ) {
		$layout  = sanitize_key( (string) $layout );
		$layouts = self::get_client_panel_layouts();

		return isset( $layouts[ $layout ] ) ? $layout : 'sidebar';
	}

	/**
	 * Get the configured client panel layout slug.
	 *
	 * @return string
	 */
	public static function get_client_panel_layout() {
		$settings = self::get();

		return self::sanitize_client_panel_layout( $settings['client_panel_layout'] ?? 'sidebar' );
	}

	/**
	 * Default client checklist panel heading.
	 *
	 * @return string
	 */
	public static function get_default_client_panel_title() {
		return 'Agency Checklist';
	}

	/**
	 * Sanitize the client checklist panel heading.
	 *
	 * @param mixed $title Raw title input.
	 * @return string
	 */
	public static function sanitize_client_panel_title( $title ) {
		$title = sanitize_text_field( (string) $title );

		if ( '' === $title ) {
			return self::get_default_client_panel_title();
		}

		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $title, 0, 80 );
		}

		return substr( $title, 0, 80 );
	}

	/**
	 * Get the configured client checklist panel heading.
	 *
	 * @return string
	 */
	public static function get_client_panel_title() {
		$settings = self::get();

		return self::sanitize_client_panel_title( $settings['client_panel_title'] ?? self::get_default_client_panel_title() );
	}

	/**
	 * Persist the client checklist panel heading.
	 *
	 * @param mixed $title Raw title input.
	 * @return string Sanitized title stored in settings.
	 */
	public static function save_client_panel_title( $title ) {
		$settings                       = self::get();
		$settings['client_panel_title'] = self::sanitize_client_panel_title( $title );
		update_option( self::OPTION_NAME, $settings );

		return $settings['client_panel_title'];
	}

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

	/**
	 * Email notification events with labels and descriptions for the Settings UI.
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function get_email_notification_events() {
		return array(
			'run_completed'         => array(
				'label'       => __( 'Notify when checklist completed', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Email admin when a user finishes all steps of a checklist.', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'run_started'           => array(
				'label'       => __( 'Notify when checklist run starts', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Email when a new checklist run begins on a connected site.', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'run_failed'            => array(
				'label'       => __( 'Notify when checklist run fails', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Email when a run stops with a failed status.', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'step_failed'           => array(
				'label'       => __( 'Notify when a step fails', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Email when an automated API step fails during a run.', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'drift_detected'        => array(
				'label'       => __( 'Notify when drift is detected', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Email when scheduled drift verification finds configuration changes.', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'client_step_completed' => array(
				'label'       => __( 'Notify when client completes a step', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Email when someone marks a manual step complete on the client panel.', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'client_note_added'     => array(
				'label'       => __( 'Notify when client adds a note', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Email when a note is saved on a client checklist step.', LAUNCHDEK_TEXT_DOMAIN ),
			),
		);
	}

	/**
	 * Sanitize comma-separated email notification addresses.
	 *
	 * @param mixed $input Raw address input.
	 * @return string Comma-separated valid email addresses.
	 */
	public static function sanitize_email_notification_addresses( $input ) {
		$parts = preg_split( '/\s*,\s*/', (string) $input );
		$clean = array();

		foreach ( (array) $parts as $part ) {
			$email = sanitize_email( trim( (string) $part ) );
			if ( '' !== $email && is_email( $email ) ) {
				$clean[] = $email;
			}
		}

		return implode( ', ', array_values( array_unique( $clean ) ) );
	}

	/**
	 * Get configured email notification recipients.
	 *
	 * Falls back to the WordPress admin email when unset.
	 *
	 * @return string[] Valid email addresses.
	 */
	/**
	 * Whether a WP Umbrella Public API token is stored.
	 *
	 * @return bool
	 */
	public static function has_wp_umbrella_api_token() {
		$settings = self::get();

		return '' !== trim( (string) ( $settings['wp_umbrella_api_token_enc'] ?? '' ) );
	}

	/**
	 * Get the decrypted WP Umbrella Public API token.
	 *
	 * @return string
	 */
	public static function get_wp_umbrella_api_token() {
		$settings = self::get();

		return LAUNCHDEK_Credential_Vault::decrypt( $settings['wp_umbrella_api_token_enc'] ?? '' );
	}

	/**
	 * Save or clear the WP Umbrella Public API token.
	 *
	 * @param string $token Plaintext token. Empty string clears the stored token.
	 * @return bool Whether a token remains configured after save.
	 */
	public static function save_wp_umbrella_api_token( $token ) {
		$settings = self::get();
		$token    = trim( (string) $token );

		if ( '' === $token ) {
			$settings['wp_umbrella_api_token_enc'] = '';
		} else {
			$settings['wp_umbrella_api_token_enc'] = LAUNCHDEK_Credential_Vault::encrypt( $token );
		}

		update_option( self::OPTION_NAME, $settings );

		return self::has_wp_umbrella_api_token();
	}

	public static function get_email_notification_addresses() {
		$settings = self::get();
		$stored   = self::sanitize_email_notification_addresses( $settings['email_notification_address'] ?? '' );

		if ( '' !== $stored ) {
			return array_values(
				array_filter(
					array_map( 'trim', explode( ',', $stored ) ),
					'is_email'
				)
			);
		}

		$admin_email = sanitize_email( get_option( 'admin_email' ) );

		return ( '' !== $admin_email && is_email( $admin_email ) ) ? array( $admin_email ) : array();
	}
}
