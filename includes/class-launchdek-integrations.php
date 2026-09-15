<?php
/**
 * Integration registry and manager.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once LAUNCHDEK_PLUGIN_DIR . 'includes/integrations/interface-launchdek-integration.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/integrations/class-launchdek-integration-mainwp.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/integrations/class-launchdek-integration-managewp.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/integrations/class-launchdek-integration-wp-umbrella.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/integrations/class-launchdek-integration-wp-engine.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/integrations/class-launchdek-integration-wpvibe.php';
require_once LAUNCHDEK_PLUGIN_DIR . 'includes/integrations/class-launchdek-mainwp-agent.php';

/**
 * Integrations manager.
 */
class LAUNCHDEK_Integrations {

	/**
	 * Registered integration instances.
	 *
	 * @return LAUNCHDEK_Integration_Interface[]
	 */
	public static function get_all() {
		static $integrations = null;

		if ( null === $integrations ) {
			$integrations = array(
				new LAUNCHDEK_Integration_MainWP(),
				new LAUNCHDEK_Integration_ManageWP(),
				new LAUNCHDEK_Integration_WP_Umbrella(),
				new LAUNCHDEK_Integration_WP_Engine(),
				new LAUNCHDEK_Integration_WPvibe(),
			);
		}

		return $integrations;
	}

	/**
	 * Get integration by slug.
	 *
	 * @param string $slug Integration slug.
	 * @return LAUNCHDEK_Integration_Interface|null
	 */
	public static function get( $slug ) {
		foreach ( self::get_all() as $integration ) {
			if ( $integration->get_slug() === $slug ) {
				return $integration;
			}
		}

		return null;
	}

	/**
	 * Get status summary for all integrations.
	 *
	 * @return array
	 */
	public static function get_statuses() {
		$synced_counts = LAUNCHDEK_Site_Repository::count_by_integration_sources();
		$statuses      = array();

		foreach ( self::get_all() as $integration ) {
			$slug   = $integration->get_slug();
			$status = array_merge(
				array(
					'slug'      => $slug,
					'name'      => $integration->get_name(),
					'available' => $integration->is_available(),
				),
				$integration->get_status()
			);

			if ( ! empty( $status['supports_sync'] ) ) {
				$status['synced_sites'] = $synced_counts[ $slug ] ?? 0;
			}

			$statuses[] = $status;
		}

		return $statuses;
	}

	/**
	 * Integrations page bootstrap payload (connectors + telemetry rules).
	 *
	 * @return array
	 */
	public static function get_page_bootstrap() {
		static $bootstrap = null;

		if ( null !== $bootstrap ) {
			return $bootstrap;
		}

		$bootstrap = array(
			'connectors' => self::get_statuses(),
			'telemetry'  => array(
				'rules'  => self::get_telemetry_rules(),
				'fields' => self::get_launchdek_fields(),
			),
		);

		return $bootstrap;
	}

	/**
	 * LaunchDek site fields available for telemetry mapping.
	 *
	 * @return array
	 */
	public static function get_launchdek_fields() {
		return array(
			'name'        => __( 'Site Name', LAUNCHDEK_TEXT_DOMAIN ),
			'url'         => __( 'Site URL', LAUNCHDEK_TEXT_DOMAIN ),
			'wp_version'  => __( 'WP Version', LAUNCHDEK_TEXT_DOMAIN ),
			'php_version' => __( 'PHP Version', LAUNCHDEK_TEXT_DOMAIN ),
		);
	}

	/**
	 * Default telemetry sync mapping rules per connector.
	 *
	 * @return array
	 */
	public static function get_default_telemetry_rules() {
		$platform_fields = array(
			'mainwp'      => array( 'site_url', 'site_name', 'wp_version', 'php_version' ),
			'managewp'    => array( 'site_url', 'site_name', 'wp_version', 'php_version' ),
			'wp-umbrella' => array( 'url', 'name', 'wp_version', 'php_version' ),
			'wp-engine'   => array( 'site_url', 'name', 'wp_version', 'php_version' ),
			'wpvibe'      => array( 'site_url', 'site_name', 'wp_version', 'php_version' ),
		);

		$launchdek_fields = array( 'url', 'name', 'wp_version', 'php_version' );
		$rules            = array();

		foreach ( self::get_all() as $integration ) {
			$slug   = $integration->get_slug();
			$fields = $platform_fields[ $slug ] ?? $launchdek_fields;

			foreach ( $fields as $index => $platform_field ) {
				$launchdek_field = $launchdek_fields[ $index ] ?? 'name';
				$rules[]         = array(
					'id'              => $slug . '-' . $platform_field,
					'integration'     => $slug,
					'integration_name' => $integration->get_name(),
					'platform_field'  => $platform_field,
					'launchdek_field' => $launchdek_field,
					'enabled'         => true,
				);
			}
		}

		return $rules;
	}

	/**
	 * Get merged telemetry sync rules (defaults + saved enabled state).
	 *
	 * @return array
	 */
	public static function get_telemetry_rules() {
		$defaults = self::get_default_telemetry_rules();
		$settings = LAUNCHDEK_Settings::get();
		$saved    = isset( $settings['telemetry_sync_rules'] ) && is_array( $settings['telemetry_sync_rules'] )
			? $settings['telemetry_sync_rules']
			: array();

		$enabled_map = array();
		foreach ( $saved as $rule ) {
			if ( ! empty( $rule['id'] ) ) {
				$enabled_map[ $rule['id'] ] = ! empty( $rule['enabled'] );
			}
		}

		foreach ( $defaults as $index => $rule ) {
			if ( isset( $enabled_map[ $rule['id'] ] ) ) {
				$defaults[ $index ]['enabled'] = $enabled_map[ $rule['id'] ];
			}
		}

		return $defaults;
	}

	/**
	 * Save telemetry sync rule enabled states.
	 *
	 * @param array $rules Rule payloads from REST.
	 * @return array
	 */
	public static function save_telemetry_rules( $rules ) {
		if ( ! is_array( $rules ) ) {
			return self::get_telemetry_rules();
		}

		$allowed_ids = wp_list_pluck( self::get_default_telemetry_rules(), 'id' );
		$clean       = array();

		foreach ( $rules as $rule ) {
			if ( empty( $rule['id'] ) || ! in_array( $rule['id'], $allowed_ids, true ) ) {
				continue;
			}

			$clean[] = array(
				'id'      => sanitize_key( $rule['id'] ),
				'enabled' => ! empty( $rule['enabled'] ),
			);
		}

		$settings = LAUNCHDEK_Settings::get();
		$settings['telemetry_sync_rules'] = $clean;
		update_option( LAUNCHDEK_Settings::OPTION_NAME, $settings );

		LAUNCHDEK_Audit_Log::log( 'telemetry_rules_updated', array( 'count' => count( $clean ) ) );

		return self::get_telemetry_rules();
	}
}
