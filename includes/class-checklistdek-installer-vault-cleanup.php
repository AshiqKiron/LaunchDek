<?php
/**
 * Pro-only checklist is_vault schema sync and legacy column cleanup.
 *
 * Omitted from the WordPress.org Community zip — hubs without Agency Vault do not
 * reference is_vault; an unused column left after Pro→Community is harmless.
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether Private Agency Vault is active on this Pro hub (premium module + license).
 *
 * @return bool
 */
function checklistdek_premium_agency_vault_is_active() {
	if ( ! checklistdek_is_pro_distribution() ) {
		return false;
	}

	if ( ! is_readable( CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-agency-vault.php' ) ) {
		return false;
	}

	if ( class_exists( 'CHECKLISTDEK_Licensing', false ) ) {
		return CHECKLISTDEK_Licensing::can_use_agency_vault();
	}

	return true;
}

/**
 * Add is_vault when Private Agency Vault is enabled; drop it when vault is unavailable.
 *
 * @return void
 */
function checklistdek_sync_checklist_vault_schema_for_build() {
	if ( checklistdek_premium_agency_vault_is_active() ) {
		if ( ! class_exists( 'CHECKLISTDEK_Agency_Vault', false ) ) {
			$file = CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-agency-vault.php';
			if ( ! is_readable( $file ) ) {
				return;
			}
			require_once $file;
		}

		CHECKLISTDEK_Agency_Vault::migrate_schema();
		return;
	}

	checklistdek_drop_legacy_checklist_is_vault_column_if_present();
}

/**
 * One-time removal of legacy is_vault for older Pro hubs when vault is no longer available.
 *
 * @return void
 */
function checklistdek_drop_legacy_checklist_is_vault_column_if_present() {
	global $wpdb;

	$table = CHECKLISTDEK_Checklist_Repository::table();

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$column = $wpdb->get_results(
		$wpdb->prepare(
			'SHOW COLUMNS FROM %i LIKE %s',
			$table,
			'is_vault'
		)
	);

	if ( empty( $column ) ) {
		return;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$index = $wpdb->get_results(
		$wpdb->prepare(
			'SHOW INDEX FROM %i WHERE Key_name = %s',
			$table,
			'is_vault'
		)
	);

	if ( ! empty( $index ) ) {
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->prepare(
				'ALTER TABLE %i DROP INDEX `is_vault`', // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
				$table
			)
		);
	}

	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->prepare(
			'ALTER TABLE %i DROP COLUMN `is_vault`', // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
			$table
		)
	);
}
