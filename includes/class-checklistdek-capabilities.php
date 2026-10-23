<?php
/**
 * Custom capabilities and role guardrails.
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Capability definitions and checks.
 */
class CHECKLISTDEK_Capabilities {

	const VIEW_DASHBOARD    = 'checklistdek_view_dashboard';
	const MANAGE_SITES      = 'checklistdek_manage_sites';
	const EDIT_CHECKLISTS    = 'checklistdek_edit_checklists';
	const EXECUTE_CHECKLISTS = 'checklistdek_execute_checklists';
	const VIEW_AUDIT        = 'checklistdek_view_audit';
	const MANAGE_SETTINGS   = 'checklistdek_manage_settings';

	/**
	 * All ChecklistDek capabilities.
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
			'checklistdek_edit_workflows',
			'checklistdek_execute_workflows',
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
			'checklistdek_edit_workflows'    => self::EDIT_CHECKLISTS,
			'checklistdek_execute_workflows' => self::EXECUTE_CHECKLISTS,
		);

		$settings = CHECKLISTDEK_Settings::get();
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
				update_option( CHECKLISTDEK_Settings::OPTION_NAME, $settings, false );
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
	 * Migrate LaunchDek capability keys to ChecklistDek (roles + stored role_permissions).
	 *
	 * @return void
	 */
	public static function migrate_launchdek_caps() {
		$prefix_map = array();
		foreach ( self::get_all() as $cap ) {
			$legacy = str_replace( 'checklistdek_', 'launchdek_', $cap );
			if ( $legacy !== $cap ) {
				$prefix_map[ $legacy ] = $cap;
			}
		}
		$prefix_map['launchdek_edit_workflows']    = self::EDIT_CHECKLISTS;
		$prefix_map['launchdek_execute_workflows'] = self::EXECUTE_CHECKLISTS;

		$settings = CHECKLISTDEK_Settings::get();
		$changed  = false;
		if ( ! empty( $settings['role_permissions'] ) && is_array( $settings['role_permissions'] ) ) {
			foreach ( $prefix_map as $old_cap => $new_cap ) {
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
				update_option( CHECKLISTDEK_Settings::OPTION_NAME, $settings, false );
			}
		}

		$roles = wp_roles();
		if ( ! $roles ) {
			return;
		}

		foreach ( array_keys( $roles->roles ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( $prefix_map as $old_cap => $new_cap ) {
				if ( $role->has_cap( $old_cap ) && ! $role->has_cap( $new_cap ) ) {
					$role->add_cap( $new_cap );
				}
				$role->remove_cap( $old_cap );
			}
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
			'checklistdek_edit_workflows',
			'checklistdek_execute_workflows',
			'launchdek_view_dashboard',
			'launchdek_manage_sites',
			'launchdek_edit_checklists',
			'launchdek_execute_checklists',
			'launchdek_view_audit',
			'launchdek_manage_settings',
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
	 * Check if current user has a ChecklistDek capability, respecting settings overrides.
	 *
	 * @param string $cap Capability slug.
	 * @return bool
	 */
	public static function current_user_can( $cap ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$settings = CHECKLISTDEK_Settings::get();
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
				'label'       => __( 'Admin', 'checklistdek' ),
				'description' => __( 'Full platform access — manage sites, checklists, settings, and audit logs.', 'checklistdek' ),
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
				'label'       => __( 'Developer', 'checklistdek' ),
				'description' => __( 'Operational access — manage sites, build checklists, and execute runs.', 'checklistdek' ),
				'caps'        => array(
					self::VIEW_DASHBOARD,
					self::MANAGE_SITES,
					self::EDIT_CHECKLISTS,
					self::EXECUTE_CHECKLISTS,
				),
			),
			'auditor'   => array(
				'label'       => __( 'Auditor', 'checklistdek' ),
				'description' => __( 'Read-only oversight — view dashboard activity and immutable audit logs.', 'checklistdek' ),
				'caps'        => array(
					self::VIEW_DASHBOARD,
					self::VIEW_AUDIT,
				),
			),
		);
	}

	/**
	 * Human-readable labels for ChecklistDek capabilities.
	 *
	 * @return array
	 */
	public static function get_capability_labels() {
		return array(
			self::VIEW_DASHBOARD    => __( 'View Dashboard', 'checklistdek' ),
			self::MANAGE_SITES      => __( 'Manage Sites', 'checklistdek' ),
			self::EDIT_CHECKLISTS    => __( 'Edit Checklists', 'checklistdek' ),
			self::EXECUTE_CHECKLISTS => __( 'Execute Checklists', 'checklistdek' ),
			self::VIEW_AUDIT        => __( 'View Audit Log', 'checklistdek' ),
			self::MANAGE_SETTINGS   => __( 'Manage Settings', 'checklistdek' ),
		);
	}
}
