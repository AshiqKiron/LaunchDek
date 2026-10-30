<?php
/**
 * Private Agency Vault UI (Pro distribution only — omitted from WordPress.org zip).
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$checklistdek_vault_help = __(
	'Save a checklist from My Checklists as a reusable template for your team. Vault items stay on this hub only—not in ChecklistDek\'s built-in library. Click Use Checklist to start from a copy.',
	'checklistdek'
);
?>
<div class="checklistdek-card checklistdek-agency-vault-card">
	<div class="checklistdek-card-heading-row">
		<h2><?php esc_html_e( 'Private Agency Vault', 'checklistdek' ); ?></h2>
		<button type="button" class="checklistdek-field-info checklistdek-has-tooltip" data-tooltip="<?php echo esc_attr( $checklistdek_vault_help ); ?>" aria-label="<?php echo esc_attr( $checklistdek_vault_help ); ?>">
			<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
		</button>
	</div>
	<p class="checklistdek-muted"><?php esc_html_e( 'Save your best custom checklists here and reuse them across client sites.', 'checklistdek' ); ?></p>
	<div class="checklistdek-inline-form">
		<label class="screen-reader-text" for="checklistdek-vault-checklist"><?php esc_html_e( 'Select checklist to vault', 'checklistdek' ); ?></label>
		<select id="checklistdek-vault-checklist" class="checklistdek-select">
			<option value=""><?php esc_html_e( 'Select checklist to vault…', 'checklistdek' ); ?></option>
		</select>
		<button type="button" class="button button-primary" id="checklistdek-save-vault"><?php esc_html_e( 'Save to Vault', 'checklistdek' ); ?></button>
	</div>
	<div id="checklistdek-vault-notice" class="checklistdek-notice-area"></div>
	<div id="checklistdek-vault-list" class="checklistdek-template-grid"></div>
</div>
