<?php
/**
 * Pro / premium distribution licensing helpers (Freemius plan gates).
 *
 * Not shipped in the WordPress.org Community zip.
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
	 * Whether the active Freemius license may use premium code paths.
	 *
	 * @return bool
	 */
	public static function can_use_premium_code() {
		if ( ! function_exists( 'cld_fs' ) ) {
			return false;
		}

		return (bool) cld_fs()->can_use_premium_code();
	}

	/**
	 * Whether the admin sidebar should include the external Upgrade item.
	 *
	 * @return bool
	 */
	public static function should_show_upgrade_submenu() {
		return ! self::can_use_premium_code();
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
	public static function can_auto_deploy_client_panel() {
		if ( ! self::can_use_premium_code() ) {
			return false;
		}

		return is_readable( CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-mu-plugin-installer.php' );
	}

	/**
	 * @return bool
	 */
	public static function can_use_agency_vault() {
		return self::can_use_premium_code();
	}

	/**
	 * @return bool
	 */
	public static function can_use_auto_capture() {
		if ( ! self::can_use_premium_code() ) {
			return false;
		}

		return is_readable( CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-auto-capture.php' );
	}

	/**
	 * @return bool
	 */
	public static function can_use_notifications() {
		if ( ! self::can_use_premium_code() ) {
			return false;
		}

		return is_readable( CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-webhook-dispatcher.php' );
	}

	/**
	 * @return bool
	 */
	public static function can_use_scheduled_drift() {
		if ( ! self::can_use_premium_code() ) {
			return false;
		}

		return is_readable( CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-drift-cron.php' );
	}

	/**
	 * Panel layout choices for Settings UI (plan-scoped when all layouts are bundled).
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function get_panel_layouts_for_plan() {
		$layouts = CHECKLISTDEK_Settings::get_client_panel_layouts();
		if ( self::can_use_premium_code() ) {
			return $layouts;
		}

		$free_file = CHECKLISTDEK_PLUGIN_DIR . 'includes/community/client-panel-layouts.php';
		if ( ! is_readable( $free_file ) ) {
			return $layouts;
		}

		$free = include $free_file;
		if ( ! is_array( $free ) ) {
			return $layouts;
		}

		return array_intersect_key( $layouts, $free );
	}

	/**
	 * Sanitize a panel layout slug for the current plan.
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
	 * Layout pushed to client snapshots.
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
	 * Schedule or clear automated drift verification based on plan and bundled cron class.
	 *
	 * @return void
	 */
	public static function sync_drift_cron() {
		checklistdek_sync_drift_cron();
	}
}
