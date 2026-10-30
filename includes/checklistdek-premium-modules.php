<?php
/**
 * Pro-only module loader (omitted from WordPress.org Community zip).
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-installer-vault-cleanup.php';
require_once CHECKLISTDEK_PLUGIN_DIR . 'includes/class-checklistdek-agency-vault.php';

CHECKLISTDEK_Agency_Vault::register();

/**
 * Extra checklist table fragments for dbDelta on Pro hubs (Private Agency Vault).
 *
 * @return array{column: string, index: string}
 */
function checklistdek_premium_checklists_table_create_fragments() {
	return array(
		'column' => "\n\t\t\tis_vault tinyint(1) NOT NULL DEFAULT 0,",
		'index'  => "\n\t\t\tKEY is_vault (is_vault),",
	);
}

/**
 * Run Pro-only database migrations during hub installer upgrades.
 *
 * @return void
 */
function checklistdek_premium_installer_migrations() {
	checklistdek_sync_checklist_vault_schema_for_build();
}
