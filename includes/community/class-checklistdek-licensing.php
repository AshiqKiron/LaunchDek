<?php
/**
 * Full / Pro distribution licensing helpers (panel plan scope, template filters).
 *
 * Not loaded or shipped in the Community zip — use checklistdek_includes_pro_package() there.
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Distribution helpers for templates, panel layout, and optional Pro PHP modules.
 */
class CHECKLISTDEK_Licensing {

	const OPTION_NAME = 'checklistdek_billing';

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
		return checklistdek_includes_pro_package();
	}

	/**
	 * @return bool
	 */
	public static function can_use_auto_capture() {
		return checklistdek_includes_pro_package();
	}

	/**
	 * @return bool
	 */
	public static function can_use_notifications() {
		return checklistdek_includes_pro_package();
	}

	/**
	 * @return bool
	 */
	public static function can_use_scheduled_drift() {
		return checklistdek_includes_pro_package();
	}

	/**
	 * Panel layout choices for Settings UI (full / Pro hub only — not used on Community builds).
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function get_panel_layouts_for_plan() {
		return CHECKLISTDEK_Settings::get_client_panel_layouts();
	}

	/**
	 * Sanitize a panel layout slug for the current plan (full / Pro hub only).
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

		$default = sanitize_key( (string) ( CHECKLISTDEK_Settings::get_defaults()['client_panel_layout'] ?? 'live_topbar' ) );

		return isset( $allowed[ $default ] ) ? $default : 'live_topbar';
	}

	/**
	 * Layout pushed to client snapshots (full / Pro hub only).
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

		return array_values( $templates );
	}

	/**
	 * Template category metadata for admin UI (categories present in the shipped catalog).
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
	 * Schedule or clear automated drift verification based on build edition.
	 *
	 * @return void
	 */
	public static function sync_drift_cron() {
		checklistdek_sync_drift_cron();
	}
}
