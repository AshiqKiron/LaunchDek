<?php
/**
 * Map connector telemetry fields to ChecklistDek site fields.
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Telemetry field mapper.
 */
class CHECKLISTDEK_Telemetry_Mapper {

	/**
	 * Map a platform row to ChecklistDek site fields using enabled rules.
	 *
	 * @param string $integration_slug Integration slug.
	 * @param array  $platform_row     Raw platform fields.
	 * @return array
	 */
	public static function map( $integration_slug, $platform_row ) {
		$mapped = array();

		foreach ( CHECKLISTDEK_Integrations::get_telemetry_rules() as $rule ) {
			if ( empty( $rule['enabled'] ) || ( $rule['integration'] ?? '' ) !== $integration_slug ) {
				continue;
			}

			$platform_field  = (string) ( $rule['platform_field'] ?? '' );
			$checklistdek_field = (string) ( $rule['checklistdek_field'] ?? '' );

			if ( '' === $platform_field || '' === $checklistdek_field ) {
				continue;
			}

			if ( ! array_key_exists( $platform_field, $platform_row ) ) {
				continue;
			}

			$value = $platform_row[ $platform_field ];
			if ( is_scalar( $value ) || null === $value ) {
				$mapped[ $checklistdek_field ] = sanitize_text_field( (string) $value );
			}
		}

		if ( ! empty( $mapped['url'] ) ) {
			$mapped['url'] = CHECKLISTDEK_Site_Repository::normalize_url( $mapped['url'] );
		}

		return $mapped;
	}
}
