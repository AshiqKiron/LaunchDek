<?php
/**
 * Community build stub — no Pro billing, plan gates, or trialware in the free distribution.
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Minimal licensing helpers for the WordPress.org Community build.
 */
class CHECKLISTDEK_Licensing {

	const OPTION_NAME = 'checklistdek_billing';

	/**
	 * Built-in template categories included in the WordPress.org Community build.
	 *
	 * @return string[]
	 */
	public static function get_community_template_categories() {
		return array( 'security', 'maintenance', 'performance', 'ecommerce' );
	}

	/**
	 * REST error when a feature is not in the Community distribution.
	 *
	 * @param string $feature_name Short feature label for the message.
	 * @return WP_Error
	 */
	public static function pro_required_error( $feature_name ) {
		return new WP_Error(
			'checklistdek_pro_required',
			sprintf(
				/* translators: %s: feature name */
				__( '%s is not available in the Community build.', 'checklistdek' ),
				$feature_name
			),
			array(
				'status' => 403,
				'plan'   => 'community',
			)
		);
	}

	/**
	 * Whether a built-in template category is available in Community.
	 *
	 * @param string $category Category slug.
	 * @return bool
	 */
	public static function is_template_category_allowed( $category ) {
		return in_array( sanitize_key( $category ), self::get_community_template_categories(), true );
	}

	/**
	 * @return bool
	 */
	public static function can_add_site() {
		return true;
	}

	/**
	 * @return bool
	 */
	public static function can_use_agency_vault() {
		return false;
	}

	/**
	 * @return bool
	 */
	public static function can_use_auto_capture() {
		return false;
	}

	/**
	 * @return bool
	 */
	public static function can_use_notifications() {
		return false;
	}

	/**
	 * @return bool
	 */
	public static function can_use_scheduled_drift() {
		return false;
	}

	/**
	 * Client panel layouts included in the Community distribution.
	 *
	 * @return string[]
	 */
	public static function get_community_panel_layouts() {
		return array( 'live_topbar', 'bottom_dock', 'floating_pill' );
	}

	/**
	 * Panel layout choices for Settings UI (Community subset only).
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function get_panel_layouts_for_plan() {
		$all     = CHECKLISTDEK_Settings::get_client_panel_layouts();
		$allowed = self::get_community_panel_layouts();
		$out     = array();

		foreach ( $allowed as $slug ) {
			if ( isset( $all[ $slug ] ) ) {
				$out[ $slug ] = $all[ $slug ];
			}
		}

		return $out;
	}

	/**
	 * Sanitize a panel layout slug for Community.
	 *
	 * @param mixed $layout Raw layout.
	 * @return string
	 */
	public static function sanitize_panel_layout( $layout ) {
		$layout  = sanitize_key( (string) $layout );
		$allowed = self::get_panel_layouts_for_plan();

		if ( isset( $allowed[ $layout ] ) ) {
			return $layout;
		}

		$community = self::get_community_panel_layouts();

		return $community[0];
	}

	/**
	 * Layout pushed to client snapshots (Community plan layouts only).
	 *
	 * @return string
	 */
	public static function get_effective_panel_layout() {
		$settings = CHECKLISTDEK_Settings::get();
		$stored   = sanitize_key( (string) ( $settings['client_panel_layout'] ?? 'live_topbar' ) );

		return self::sanitize_panel_layout( $stored );
	}

	/**
	 * @param array $templates Template definitions.
	 * @return array
	 */
	public static function filter_builtin_templates( $templates ) {
		if ( ! is_array( $templates ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$templates,
				function ( $row ) {
					if ( ! is_array( $row ) ) {
						return false;
					}
					$category = CHECKLISTDEK_Templates::normalize_category( $row['category'] ?? '' );

					return self::is_template_category_allowed( $category );
				}
			)
		);
	}

	/**
	 * Template category metadata for admin UI (only categories present in the shipped catalog).
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function get_template_categories_for_plan() {
		$all     = CHECKLISTDEK_Templates::get_categories();
		$catalog = CHECKLISTDEK_Templates::get_catalog();
		$slugs   = array();

		foreach ( $catalog as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$slug           = CHECKLISTDEK_Templates::normalize_category( $row['category'] ?? '' );
			$slugs[ $slug ] = true;
		}

		$out = array();
		foreach ( array_keys( $slugs ) as $slug ) {
			if ( isset( $all[ $slug ] ) ) {
				$out[ $slug ] = $all[ $slug ];
			}
		}

		return $out;
	}

	/**
	 * Schedule or clear automated drift verification (Community: always clear).
	 *
	 * @return void
	 */
	public static function sync_drift_cron() {
		CHECKLISTDEK_Drift_Cron::deactivate();
	}
}
