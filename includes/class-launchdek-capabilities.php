<?php
/**
 * Custom capabilities and role guardrails.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Capability definitions and checks.
 */
class LAUNCHDEK_Capabilities {

	const VIEW_DASHBOARD    = 'launchdek_view_dashboard';
	const MANAGE_SITES      = 'launchdek_manage_sites';
	const EDIT_CHECKLISTS    = 'launchdek_edit_checklists';
	const EXECUTE_CHECKLISTS = 'launchdek_execute_checklists';
	const VIEW_AUDIT        = 'launchdek_view_audit';
	const MANAGE_SETTINGS   = 'launchdek_manage_settings';

	/**
	 * All LaunchDek capabilities.
	 *
	 * @return array
	 */
	public static function get_all() {
		return array(
			self::VIEW_DASHBOARD,
			self::MANAGE_SITES,
			self::EDIT_CHECKLISTS,
			self::EXECUTE_CHECKLISTS,
			self::VIEW_AUDIT,
			self::MANAGE_SETTINGS,
		);
	}

	/**
	 * Add capabilities to administrator on activation.
	 *
	 * @return void
	 */
	public static function register() {
		self::migrate_workflow_caps();

		$role = get_role( 'administrator' );

		if ( ! $role ) {
			return;
		}

		foreach ( self::get_all() as $cap ) {
			$role->add_cap( $cap );
		}

		$legacy_caps = array(
			'launchdek_edit_workflows',
			'launchdek_execute_workflows',
		);

		foreach ( $legacy_caps as $legacy_cap ) {
			$role->remove_cap( $legacy_cap );
		}
	}

	/**
	 * Migrate stored role permissions and administrator caps from workflow naming.
	 *
	 * @return void
	 */
	public static function migrate_workflow_caps() {
		$map = array(
			'launchdek_edit_workflows'    => self::EDIT_CHECKLISTS,
			'launchdek_execute_workflows' => self::EXECUTE_CHECKLISTS,
		);

		$settings = LAUNCHDEK_Settings::get();
		$changed  = false;

		if ( ! empty( $settings['role_permissions'] ) && is_array( $settings['role_permissions'] ) ) {
			foreach ( $map as $old_cap => $new_cap ) {
				if ( isset( $settings['role_permissions'][ $old_cap ] ) && ! isset( $settings['role_permissions'][ $new_cap ] ) ) {
					$settings['role_permissions'][ $new_cap ] = $settings['role_permissions'][ $old_cap ];
					$changed                                  = true;
				}
				if ( isset( $settings['role_permissions'][ $old_cap ] ) ) {
					unset( $settings['role_permissions'][ $old_cap ] );
					$changed = true;
				}
			}

			if ( $changed ) {
				update_option( LAUNCHDEK_Settings::OPTION_NAME, $settings, false );
			}
		}

		$role = get_role( 'administrator' );
		if ( ! $role ) {
			return;
		}

		foreach ( $map as $old_cap => $new_cap ) {
			if ( $role->has_cap( $old_cap ) && ! $role->has_cap( $new_cap ) ) {
				$role->add_cap( $new_cap );
			}
			$role->remove_cap( $old_cap );
		}
	}

	/**
	 * Remove capabilities on uninstall.
	 *
	 * @return void
	 */
	public static function unregister() {
		$roles = wp_roles();

		if ( ! $roles ) {
			return;
		}

		$legacy_caps = array(
			'launchdek_edit_workflows',
			'launchdek_execute_workflows',
		);

		foreach ( array_keys( $roles->roles ) as $role_name ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( self::get_all() as $cap ) {
				$role->remove_cap( $cap );
			}

			foreach ( $legacy_caps as $legacy_cap ) {
				$role->remove_cap( $legacy_cap );
			}
		}
	}

	/**
	 * Check if current user has a LaunchDek capability, respecting settings overrides.
	 *
	 * @param string $cap Capability slug.
	 * @return bool
	 */
	public static function current_user_can( $cap ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$settings = LAUNCHDEK_Settings::get();
		$roles    = isset( $settings['role_permissions'] ) && is_array( $settings['role_permissions'] )
			? $settings['role_permissions']
			: array();

		if ( ! empty( $roles[ $cap ] ) && is_array( $roles[ $cap ] ) ) {
			$user = wp_get_current_user();

			foreach ( $roles[ $cap ] as $role_slug ) {
				if ( in_array( $role_slug, (array) $user->roles, true ) ) {
					return true;
				}
			}

			return false;
		}

		return current_user_can( $cap );
	}

	/**
	 * Minimum capability required for admin menu access.
	 *
	 * @return string
	 */
	public static function admin_menu_capability() {
		return self::VIEW_DASHBOARD;
	}

	/**
	 * Agency role archetypes for settings guardrails UI.
	 *
	 * @return array
	 */
	public static function get_agency_role_presets() {
		return array(
			'admin'     => array(
				'label'       => __( 'Admin', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Full platform access — manage sites, checklists, settings, and audit logs.', LAUNCHDEK_TEXT_DOMAIN ),
				'caps'        => array(
					self::VIEW_DASHBOARD,
					self::MANAGE_SITES,
					self::EDIT_CHECKLISTS,
					self::EXECUTE_CHECKLISTS,
					self::VIEW_AUDIT,
					self::MANAGE_SETTINGS,
				),
			),
			'developer' => array(
				'label'       => __( 'Developer', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Operational access — manage sites, build checklists, and execute runs.', LAUNCHDEK_TEXT_DOMAIN ),
				'caps'        => array(
					self::VIEW_DASHBOARD,
					self::MANAGE_SITES,
					self::EDIT_CHECKLISTS,
					self::EXECUTE_CHECKLISTS,
				),
			),
			'auditor'   => array(
				'label'       => __( 'Auditor', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Read-only oversight — view dashboard activity and immutable audit logs.', LAUNCHDEK_TEXT_DOMAIN ),
				'caps'        => array(
					self::VIEW_DASHBOARD,
					self::VIEW_AUDIT,
				),
			),
		);
	}

	/**
	 * Human-readable labels for LaunchDek capabilities.
	 *
	 * @return array
	 */
	public static function get_capability_labels() {
		return array(
			self::VIEW_DASHBOARD    => __( 'View Dashboard', LAUNCHDEK_TEXT_DOMAIN ),
			self::MANAGE_SITES      => __( 'Manage Sites', LAUNCHDEK_TEXT_DOMAIN ),
			self::EDIT_CHECKLISTS    => __( 'Edit Checklists', LAUNCHDEK_TEXT_DOMAIN ),
			self::EXECUTE_CHECKLISTS => __( 'Execute Checklists', LAUNCHDEK_TEXT_DOMAIN ),
			self::VIEW_AUDIT        => __( 'View Audit Log', LAUNCHDEK_TEXT_DOMAIN ),
			self::MANAGE_SETTINGS   => __( 'Manage Settings', LAUNCHDEK_TEXT_DOMAIN ),
		);
	}
}
