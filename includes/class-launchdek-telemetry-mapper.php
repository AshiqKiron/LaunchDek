<?php
/**
 * Map connector telemetry fields to LaunchDek site fields.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Telemetry field mapper.
 */
class LAUNCHDEK_Telemetry_Mapper {

	/**
	 * Map a platform row to LaunchDek site fields using enabled rules.
	 *
	 * @param string $integration_slug Integration slug.
	 * @param array  $platform_row     Raw platform fields.
	 * @return array
	 */
	public static function map( $integration_slug, $platform_row ) {
		$mapped = array();

		foreach ( LAUNCHDEK_Integrations::get_telemetry_rules() as $rule ) {
			if ( empty( $rule['enabled'] ) || ( $rule['integration'] ?? '' ) !== $integration_slug ) {
				continue;
			}

			$platform_field  = (string) ( $rule['platform_field'] ?? '' );
			$launchdek_field = (string) ( $rule['launchdek_field'] ?? '' );

			if ( '' === $platform_field || '' === $launchdek_field ) {
				continue;
			}

			if ( ! array_key_exists( $platform_field, $platform_row ) ) {
				continue;
			}

			$value = $platform_row[ $platform_field ];
			if ( is_scalar( $value ) || null === $value ) {
				$mapped[ $launchdek_field ] = sanitize_text_field( (string) $value );
			}
		}

		if ( ! empty( $mapped['url'] ) ) {
			$mapped['url'] = LAUNCHDEK_Site_Repository::normalize_url( $mapped['url'] );
		}

		return $mapped;
	}
}
