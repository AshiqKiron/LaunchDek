<?php
/**
 * Centralized plugin settings — defaults, retrieval, sanitization.
 *
 * Purpose: Single source of truth for the launchdek_settings option.
 *          Uses wp_options (no custom tables).
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

	/**
	 * Settings option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'launchdek_settings';

	/**
	 * Installed version option name.
	 *
	 * @var string
	 */
	const VERSION_OPTION = 'launchdek_version';

	/**
	 * Settings API group identifier.
	 *
	 * @var string
	 */
	const SETTINGS_GROUP = 'launchdek_settings_group';

	/**
	 * Required capability for settings access.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		$defaults = array(
			'enabled' => true,
		);

		/**
		 * Filter default plugin settings.
		 *
		 * @param array $defaults Default settings.
		 */
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

		if ( array_key_exists( 'enabled', $input ) ) {
			$output['enabled'] = (bool) $input['enabled'];
		}

		/**
		 * Filter sanitized plugin settings.
		 *
		 * @param array $output   Sanitized settings.
		 * @param array $input    Raw input.
		 * @param array $defaults Default settings.
		 */
		return apply_filters( 'launchdek_settings_sanitize', $output, $input, $defaults );
	}
}
