<?php
/**
 * Checklists admin page (builder + templates).
 *
 * @package LaunchDek
 *
 * @var array  $settings Plugin settings.
 * @var string $page     Page identifier.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap launchdek-admin" data-launchdek-page="checklists">
	<h1><?php echo esc_html( get_admin_page_title() ); ?>
		<button type="button" class="page-title-action" id="launchdek-show-builder"><?php esc_html_e( 'My Checklists', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
		<button type="button" class="page-title-action" id="launchdek-show-templates" hidden><?php esc_html_e( 'Templates', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
		<button type="button" class="page-title-action" id="launchdek-new-checklist" hidden><?php esc_html_e( 'New Checklist', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
		<button type="button" class="page-title-action" id="launchdek-auto-capture" hidden><?php esc_html_e( 'Auto-Capture', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
	</h1>

	<div id="launchdek-panel-templates" class="launchdek-tab-panel" data-launchdek-tab-panel="templates" role="tabpanel">
		<div class="launchdek-card">
			<h2><?php esc_html_e( 'Built-In Standard Stacks', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<div id="launchdek-category-tabs" class="launchdek-category-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Template categories', LAUNCHDEK_TEXT_DOMAIN ); ?>"></div>
			<div id="launchdek-builtin-templates" class="launchdek-template-grid"></div>
			<div id="launchdek-builtin-notice" class="launchdek-notice-area"></div>
		</div>

		<div class="launchdek-card">
			<h2><?php esc_html_e( 'Your Custom Checklists', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<p class="launchdek-muted"><?php esc_html_e( 'Checklists you create and save here appear below for reuse.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
			<div id="launchdek-custom-checklists" class="launchdek-template-grid"></div>
			<div id="launchdek-custom-notice" class="launchdek-notice-area"></div>
		</div>

		<div class="launchdek-card">
			<h2><?php esc_html_e( 'Private Agency Vault', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<p><?php esc_html_e( 'Secure local repository for proprietary agency checklists.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
			<div class="launchdek-inline-form">
				<label class="screen-reader-text" for="launchdek-vault-checklist"><?php esc_html_e( 'Select checklist to vault', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
				<select id="launchdek-vault-checklist" class="launchdek-select">
					<option value=""><?php esc_html_e( 'Select checklist to vault…', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
				</select>
				<button type="button" class="button button-primary" id="launchdek-save-vault"><?php esc_html_e( 'Save to Vault', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
			</div>
			<div id="launchdek-vault-notice" class="launchdek-notice-area"></div>
			<div id="launchdek-vault-list" class="launchdek-template-grid"></div>
		</div>
	</div>

	<div id="launchdek-panel-builder" class="launchdek-tab-panel" data-launchdek-tab-panel="builder" role="tabpanel" hidden>
		<div id="launchdek-checklist-notice" class="launchdek-notice-area" aria-live="polite"></div>

		<p class="launchdek-checklist-picker">
			<label for="launchdek-checklist-select"><?php esc_html_e( 'Checklist', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
			<select id="launchdek-checklist-select" class="launchdek-select">
				<option value=""><?php esc_html_e( 'Select checklist…', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
			</select>
		</p>

		<div class="launchdek-card" id="launchdek-checklist-editor" hidden>
			<h2><?php esc_html_e( 'Checklist Builder', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<div class="launchdek-cl-meta">
				<p>
					<label for="launchdek-cl-title"><?php esc_html_e( 'Checklist title', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
					<input type="text" id="launchdek-cl-title" class="large-text" required />
				</p>
				<p>
					<label for="launchdek-cl-description"><?php esc_html_e( 'Description', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
					<textarea id="launchdek-cl-description" class="large-text" rows="2"></textarea>
				</p>
			</div>

			<div class="launchdek-builder-workspace">
				<div class="launchdek-canvas-card">
					<div class="launchdek-canvas-header">
						<h3><?php esc_html_e( 'Checklist Canvas', LAUNCHDEK_TEXT_DOMAIN ); ?></h3>
						<button type="button" class="button" id="launchdek-add-step"><?php esc_html_e( 'Add Step', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
					</div>
					<div id="launchdek-checklist-canvas" class="launchdek-checklist-canvas" aria-label="<?php esc_attr_e( 'Drag-and-drop checklist builder canvas', LAUNCHDEK_TEXT_DOMAIN ); ?>">
						<div id="launchdek-canvas-steps" class="launchdek-canvas-steps"></div>
					</div>
				</div>

				<aside class="launchdek-step-config-sidebar" aria-label="<?php esc_attr_e( 'Step configuration panel', LAUNCHDEK_TEXT_DOMAIN ); ?>">
					<h3><?php esc_html_e( 'Step Configuration', LAUNCHDEK_TEXT_DOMAIN ); ?></h3>
					<div id="launchdek-step-config" class="launchdek-step-config">
						<p class="launchdek-muted"><?php esc_html_e( 'Select a step on the canvas to configure.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
					</div>
				</aside>
			</div>

			<p class="launchdek-modal-actions">
				<button type="button" class="button button-primary" id="launchdek-save-checklist"><?php esc_html_e( 'Save Checklist', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				<button type="button" class="button" id="launchdek-export-checklist"><?php esc_html_e( 'Export JSON', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				<button type="button" class="button" id="launchdek-delete-checklist"><?php esc_html_e( 'Delete', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
			</p>
		</div>

		<div class="launchdek-card launchdek-import-export">
			<h2><?php esc_html_e( 'Import / Export Center', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<div class="launchdek-inline-form">
				<label for="launchdek-import-file" class="screen-reader-text"><?php esc_html_e( 'Import checklist JSON file', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
				<input type="file" id="launchdek-import-file" accept=".json" />
				<label for="launchdek-import-url" class="screen-reader-text"><?php esc_html_e( 'Import checklist from URL', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
				<input type="url" id="launchdek-import-url" class="regular-text" placeholder="<?php esc_attr_e( 'Or paste JSON URL…', LAUNCHDEK_TEXT_DOMAIN ); ?>" />
				<button type="button" class="button" id="launchdek-import-checklist"><?php esc_html_e( 'Import', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
			</div>
		</div>
	</div>

	<?php require LAUNCHDEK_PLUGIN_DIR . 'admin/partials/launchdek-template-picker-modal.php'; ?>

	<div id="launchdek-auto-capture-modal" class="launchdek-modal launchdek-auto-capture-modal" hidden>
		<div class="launchdek-modal-backdrop" data-launchdek-close-capture></div>
		<div class="launchdek-modal-content launchdek-auto-capture-content" role="dialog" aria-modal="true" aria-labelledby="launchdek-auto-capture-title">
			<div class="launchdek-auto-capture-header">
				<h2 id="launchdek-auto-capture-title"><?php esc_html_e( 'Auto-Capture Checklist Steps', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
				<button type="button" class="launchdek-auto-capture-close" data-launchdek-close-capture aria-label="<?php esc_attr_e( 'Close', LAUNCHDEK_TEXT_DOMAIN ); ?>">&times;</button>
			</div>
			<p class="launchdek-muted"><?php esc_html_e( 'Record configuration changes on a client site and turn them into reusable checklist steps.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
			<div id="launchdek-auto-capture-notice" class="launchdek-notice-area"></div>
			<p>
				<label for="launchdek-auto-capture-site"><?php esc_html_e( 'Client site', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
				<select id="launchdek-auto-capture-site" class="launchdek-select">
					<option value=""><?php esc_html_e( 'Select site…', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
				</select>
			</p>
			<p id="launchdek-auto-capture-status" class="launchdek-auto-capture-status launchdek-muted" aria-live="polite"></p>
			<ol id="launchdek-auto-capture-steps" class="launchdek-auto-capture-steps" hidden></ol>
			<div class="launchdek-modal-actions launchdek-auto-capture-actions">
				<button type="button" class="button" id="launchdek-auto-capture-start"><?php esc_html_e( 'Start Recording', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				<button type="button" class="button" id="launchdek-auto-capture-stop" hidden><?php esc_html_e( 'Stop Recording', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				<button type="button" class="button button-primary" id="launchdek-auto-capture-import" hidden><?php esc_html_e( 'Import Captured Steps', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				<button type="button" class="button" id="launchdek-auto-capture-clear" hidden><?php esc_html_e( 'Clear', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				<button type="button" class="button" data-launchdek-close-capture><?php esc_html_e( 'Close', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
			</div>
		</div>
	</div>
</div>
