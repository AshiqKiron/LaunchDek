<?php
/**
 * Checklists admin page (builder + templates).
 *
 * @package ChecklistDek
 *
 * @var array  $settings Plugin settings.
 * @var string $page     Page identifier.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$checklistdek_show_auto_capture = checklistdek_includes_pro_package() && CHECKLISTDEK_Licensing::can_use_auto_capture();
$checklistdek_show_vault        = checklistdek_includes_pro_package() && CHECKLISTDEK_Licensing::can_use_agency_vault();
?>
<div class="wrap checklistdek-admin" data-checklistdek-page="checklists">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<nav class="checklistdek-checklist-nav nav-tab-wrapper" role="tablist" aria-label="<?php esc_attr_e( 'Checklist sections', 'checklistdek' ); ?>">
		<button type="button" class="nav-tab nav-tab-active" id="checklistdek-tab-templates" data-checklistdek-tab="templates" role="tab" aria-selected="true" aria-controls="checklistdek-panel-templates"><?php esc_html_e( 'Templates', 'checklistdek' ); ?></button>
		<button type="button" class="nav-tab" id="checklistdek-tab-my-checklists" data-checklistdek-tab="my-checklists" role="tab" aria-selected="false" aria-controls="checklistdek-panel-my-checklists"><?php esc_html_e( 'My Checklists', 'checklistdek' ); ?></button>
		<?php if ( $checklistdek_show_auto_capture ) : ?>
		<button type="button" class="nav-tab" id="checklistdek-tab-auto-capture" data-checklistdek-tab="auto-capture" role="tab" aria-selected="false" aria-controls="checklistdek-panel-auto-capture"><?php esc_html_e( 'Auto-Capture', 'checklistdek' ); ?></button>
		<?php endif; ?>
	</nav>

	<div id="checklistdek-panel-templates" class="checklistdek-tab-panel" data-checklistdek-tab-panel="templates" role="tabpanel" aria-labelledby="checklistdek-tab-templates">
		<div class="checklistdek-card">
			<h2><?php esc_html_e( 'Built-In Standard Stacks', 'checklistdek' ); ?></h2>
			<div class="checklistdek-builtin-templates-toolbar">
				<label class="checklistdek-builtin-template-search-field" for="checklistdek-builtin-template-search">
					<span class="checklistdek-builtin-template-search-label"><?php esc_html_e( 'Search', 'checklistdek' ); ?></span>
					<input
						type="search"
						id="checklistdek-builtin-template-search"
						class="regular-text"
						placeholder="<?php esc_attr_e( 'Search templates by title, description, or steps…', 'checklistdek' ); ?>"
						autocomplete="off"
					/>
				</label>
				<p id="checklistdek-builtin-template-search-hint" class="checklistdek-muted checklistdek-builtin-template-search-hint" hidden aria-live="polite"></p>
			</div>
			<div id="checklistdek-category-tabs" class="checklistdek-category-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Template categories', 'checklistdek' ); ?>"></div>
			<div id="checklistdek-builtin-templates" class="checklistdek-template-grid"></div>
			<div id="checklistdek-builtin-notice" class="checklistdek-notice-area"></div>
		</div>

		<?php if ( $checklistdek_show_vault ) : ?>
		<div class="checklistdek-card">
			<?php
			$checklistdek_vault_help = __(
				'Save a checklist from My Checklists as a reusable template for your team. Vault items stay on this hub only—not in ChecklistDek\'s built-in library. Click Use Checklist to start from a copy.',
				'checklistdek'
			);
			?>
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
		<?php endif; ?>
	</div>

	<div id="checklistdek-panel-my-checklists" class="checklistdek-tab-panel" data-checklistdek-tab-panel="my-checklists" role="tabpanel" aria-labelledby="checklistdek-tab-my-checklists" hidden>
		<p class="checklistdek-checklist-picker">
			<label for="checklistdek-checklist-select"><?php esc_html_e( 'Checklist', 'checklistdek' ); ?></label>
			<select id="checklistdek-checklist-select" class="checklistdek-select">
				<option value=""><?php esc_html_e( 'Select checklist…', 'checklistdek' ); ?></option>
			</select>
		</p>
		<div id="checklistdek-custom-notice" class="checklistdek-notice-area"></div>

		<div class="checklistdek-card" id="checklistdek-checklist-editor" hidden>
			<h2><?php esc_html_e( 'Checklist Builder', 'checklistdek' ); ?></h2>
			<?php
			$checklistdek_checklist_title_help = __(
				'The checklist name shown in the hub, run history, and client panel when this checklist is pushed to a site.',
				'checklistdek'
			);
			$checklistdek_checklist_description_help = __(
				'Optional short summary for your team. Appears in checklist pickers and helps identify this checklist when vaulting or launching runs.',
				'checklistdek'
			);
			?>
			<div class="checklistdek-cl-meta">
				<p>
					<label for="checklistdek-cl-title">
						<span class="checklistdek-field-label-row">
							<button type="button" class="checklistdek-field-info checklistdek-has-tooltip" data-tooltip="<?php echo esc_attr( $checklistdek_checklist_title_help ); ?>" aria-label="<?php echo esc_attr( $checklistdek_checklist_title_help ); ?>">
								<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
							</button>
							<span><?php esc_html_e( 'Checklist title', 'checklistdek' ); ?></span>
						</span>
					</label>
					<input type="text" id="checklistdek-cl-title" class="large-text" required />
				</p>
				<p>
					<label for="checklistdek-cl-description">
						<span class="checklistdek-field-label-row">
							<button type="button" class="checklistdek-field-info checklistdek-has-tooltip" data-tooltip="<?php echo esc_attr( $checklistdek_checklist_description_help ); ?>" aria-label="<?php echo esc_attr( $checklistdek_checklist_description_help ); ?>">
								<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
							</button>
							<span><?php esc_html_e( 'Description', 'checklistdek' ); ?></span>
						</span>
					</label>
					<textarea id="checklistdek-cl-description" class="large-text" rows="2"></textarea>
				</p>
			</div>

			<div class="checklistdek-builder-workspace">
				<div class="checklistdek-canvas-card">
					<div class="checklistdek-canvas-header">
						<h3><?php esc_html_e( 'Checklist Canvas', 'checklistdek' ); ?></h3>
						<button type="button" class="button" id="checklistdek-add-step"><?php esc_html_e( 'Add Step', 'checklistdek' ); ?></button>
					</div>
					<div id="checklistdek-checklist-canvas" class="checklistdek-checklist-canvas" aria-label="<?php esc_attr_e( 'Drag-and-drop checklist builder canvas', 'checklistdek' ); ?>">
						<div id="checklistdek-canvas-steps" class="checklistdek-canvas-steps"></div>
					</div>
				</div>

				<aside class="checklistdek-step-config-sidebar" aria-label="<?php esc_attr_e( 'Step configuration panel', 'checklistdek' ); ?>">
					<h3><?php esc_html_e( 'Step Configuration', 'checklistdek' ); ?></h3>
					<p class="checklistdek-checklist-panel-title-field">
						<label for="checklistdek-cl-panel-title"><?php esc_html_e( 'Client panel heading', 'checklistdek' ); ?></label>
						<input
							type="text"
							id="checklistdek-cl-panel-title"
							class="regular-text"
							maxlength="80"
							value="<?php echo esc_attr( CHECKLISTDEK_Settings::get_client_panel_title() ); ?>"
							placeholder="<?php echo esc_attr( CHECKLISTDEK_Settings::get_default_client_panel_title() ); ?>"
						/>
						<span class="checklistdek-muted checklistdek-checklist-panel-title-help"><?php esc_html_e( 'Heading shown on the client checklist panel. Syncs on the next checklist push or client panel refresh.', 'checklistdek' ); ?></span>
					</p>
					<div id="checklistdek-step-config" class="checklistdek-step-config">
						<p class="checklistdek-muted"><?php esc_html_e( 'Select a step on the canvas to configure.', 'checklistdek' ); ?></p>
					</div>
				</aside>

				<aside class="checklistdek-checklist-preview-sidebar" aria-label="<?php esc_attr_e( 'Checklist preview panel', 'checklistdek' ); ?>">
					<h3><?php esc_html_e( 'Preview', 'checklistdek' ); ?></h3>
					<div id="checklistdek-checklist-preview" class="checklistdek-checklist-preview" aria-live="polite">
						<p class="checklistdek-muted"><?php esc_html_e( 'Add a title and steps to preview the client panel.', 'checklistdek' ); ?></p>
					</div>
				</aside>
			</div>

			<p class="checklistdek-modal-actions">
				<button type="button" class="button button-primary" id="checklistdek-save-checklist"><?php esc_html_e( 'Save Checklist', 'checklistdek' ); ?></button>
				<button type="button" class="button" id="checklistdek-canvas-start-blank"><?php esc_html_e( 'Start Blank', 'checklistdek' ); ?></button>
				<button type="button" class="button" id="checklistdek-export-checklist"><?php esc_html_e( 'Export JSON', 'checklistdek' ); ?></button>
				<button type="button" class="button" id="checklistdek-delete-checklist"><?php esc_html_e( 'Delete', 'checklistdek' ); ?></button>
			</p>
		</div>

		<div class="checklistdek-card checklistdek-import-export">
			<h2><?php esc_html_e( 'Import / Export Center', 'checklistdek' ); ?></h2>
			<div class="checklistdek-inline-form">
				<label for="checklistdek-import-file" class="screen-reader-text"><?php esc_html_e( 'Import checklist JSON file', 'checklistdek' ); ?></label>
				<input type="file" id="checklistdek-import-file" accept=".json" />
				<label for="checklistdek-import-url" class="screen-reader-text"><?php esc_html_e( 'Import checklist from URL', 'checklistdek' ); ?></label>
				<input type="url" id="checklistdek-import-url" class="regular-text" placeholder="<?php esc_attr_e( 'Or paste JSON URL…', 'checklistdek' ); ?>" />
				<button type="button" class="button" id="checklistdek-import-checklist"><?php esc_html_e( 'Import', 'checklistdek' ); ?></button>
			</div>
		</div>
	</div>

	<?php if ( $checklistdek_show_auto_capture ) : ?>
	<div id="checklistdek-panel-auto-capture" class="checklistdek-tab-panel" data-checklistdek-tab-panel="auto-capture" role="tabpanel" aria-labelledby="checklistdek-tab-auto-capture" hidden>
		<div class="checklistdek-card checklistdek-auto-capture-panel">
			<h2><?php esc_html_e( 'Auto-Capture Checklist Steps', 'checklistdek' ); ?></h2>
			<p class="checklistdek-muted"><?php esc_html_e( 'Record configuration changes on a client site and turn them into reusable checklist steps.', 'checklistdek' ); ?></p>
			<div id="checklistdek-auto-capture-notice" class="checklistdek-notice-area"></div>
			<p>
				<label for="checklistdek-auto-capture-site"><?php esc_html_e( 'Client site', 'checklistdek' ); ?></label>
				<select id="checklistdek-auto-capture-site" class="checklistdek-select">
					<option value=""><?php esc_html_e( 'Select site…', 'checklistdek' ); ?></option>
				</select>
			</p>
			<p id="checklistdek-auto-capture-status" class="checklistdek-auto-capture-status checklistdek-muted" aria-live="polite"></p>
			<ol id="checklistdek-auto-capture-steps" class="checklistdek-auto-capture-steps" hidden></ol>
			<div class="checklistdek-modal-actions checklistdek-auto-capture-actions">
				<button type="button" class="button" id="checklistdek-auto-capture-start"><?php esc_html_e( 'Start Recording', 'checklistdek' ); ?></button>
				<button type="button" class="button" id="checklistdek-auto-capture-stop" hidden><?php esc_html_e( 'Stop Recording', 'checklistdek' ); ?></button>
				<button type="button" class="button button-primary" id="checklistdek-auto-capture-import" hidden><?php esc_html_e( 'Import Captured Steps', 'checklistdek' ); ?></button>
				<button type="button" class="button" id="checklistdek-auto-capture-clear" hidden><?php esc_html_e( 'Clear', 'checklistdek' ); ?></button>
			</div>
		</div>
	</div>
	<?php endif; ?>

	<?php require CHECKLISTDEK_PLUGIN_DIR . 'admin/partials/checklistdek-template-picker-modal.php'; ?>
</div>
