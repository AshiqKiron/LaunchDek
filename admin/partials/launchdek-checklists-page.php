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
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<nav class="launchdek-checklist-nav nav-tab-wrapper" role="tablist" aria-label="<?php esc_attr_e( 'Checklist sections', LAUNCHDEK_TEXT_DOMAIN ); ?>">
		<button type="button" class="nav-tab nav-tab-active" id="launchdek-tab-templates" data-launchdek-tab="templates" role="tab" aria-selected="true" aria-controls="launchdek-panel-templates"><?php esc_html_e( 'Templates', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
		<button type="button" class="nav-tab" id="launchdek-tab-my-checklists" data-launchdek-tab="my-checklists" role="tab" aria-selected="false" aria-controls="launchdek-panel-my-checklists"><?php esc_html_e( 'My Checklists', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
		<button type="button" class="nav-tab" id="launchdek-tab-auto-capture" data-launchdek-tab="auto-capture" role="tab" aria-selected="false" aria-controls="launchdek-panel-auto-capture"><?php esc_html_e( 'Auto-Capture', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
	</nav>

	<div id="launchdek-panel-templates" class="launchdek-tab-panel" data-launchdek-tab-panel="templates" role="tabpanel" aria-labelledby="launchdek-tab-templates">
		<div class="launchdek-card">
			<h2><?php esc_html_e( 'Built-In Standard Stacks', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<div class="launchdek-builtin-templates-toolbar">
				<label class="launchdek-builtin-template-search-field" for="launchdek-builtin-template-search">
					<span class="launchdek-builtin-template-search-label"><?php esc_html_e( 'Search', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
					<input
						type="search"
						id="launchdek-builtin-template-search"
						class="regular-text"
						placeholder="<?php esc_attr_e( 'Search templates by title, description, or steps…', LAUNCHDEK_TEXT_DOMAIN ); ?>"
						autocomplete="off"
					/>
				</label>
				<p id="launchdek-builtin-template-search-hint" class="launchdek-muted launchdek-builtin-template-search-hint" hidden aria-live="polite"></p>
			</div>
			<div id="launchdek-category-tabs" class="launchdek-category-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Template categories', LAUNCHDEK_TEXT_DOMAIN ); ?>"></div>
			<div id="launchdek-builtin-templates" class="launchdek-template-grid"></div>
			<div id="launchdek-builtin-notice" class="launchdek-notice-area"></div>
		</div>

		<div class="launchdek-card">
			<?php
			$vault_help = __(
				'Save a checklist from My Checklists as a reusable template for your team. Vault items stay on this hub only—not in LaunchDek\'s built-in library. Click Use Checklist to start from a copy.',
				LAUNCHDEK_TEXT_DOMAIN
			);
			?>
			<div class="launchdek-card-heading-row">
				<h2><?php esc_html_e( 'Private Agency Vault', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
				<button type="button" class="launchdek-field-info launchdek-has-tooltip" data-tooltip="<?php echo esc_attr( $vault_help ); ?>" aria-label="<?php echo esc_attr( $vault_help ); ?>">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				</button>
			</div>
			<p class="launchdek-muted"><?php esc_html_e( 'Save your best custom checklists here and reuse them across client sites.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
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

	<div id="launchdek-panel-my-checklists" class="launchdek-tab-panel" data-launchdek-tab-panel="my-checklists" role="tabpanel" aria-labelledby="launchdek-tab-my-checklists" hidden>
		<p class="launchdek-checklist-picker">
			<label for="launchdek-checklist-select"><?php esc_html_e( 'Checklist', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
			<select id="launchdek-checklist-select" class="launchdek-select">
				<option value=""><?php esc_html_e( 'Select checklist…', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
			</select>
		</p>
		<div id="launchdek-custom-notice" class="launchdek-notice-area"></div>

		<div class="launchdek-card" id="launchdek-checklist-editor" hidden>
			<h2><?php esc_html_e( 'Checklist Builder', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<?php
			$checklist_title_help = __(
				'The checklist name shown in the hub, run history, and client panel when this checklist is pushed to a site.',
				LAUNCHDEK_TEXT_DOMAIN
			);
			$checklist_description_help = __(
				'Optional short summary for your team. Appears in checklist pickers and helps identify this checklist when vaulting or launching runs.',
				LAUNCHDEK_TEXT_DOMAIN
			);
			?>
			<div class="launchdek-cl-meta">
				<p>
					<label for="launchdek-cl-title">
						<span class="launchdek-field-label-row">
							<button type="button" class="launchdek-field-info launchdek-has-tooltip" data-tooltip="<?php echo esc_attr( $checklist_title_help ); ?>" aria-label="<?php echo esc_attr( $checklist_title_help ); ?>">
								<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
							</button>
							<span><?php esc_html_e( 'Checklist title', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
						</span>
					</label>
					<input type="text" id="launchdek-cl-title" class="large-text" required />
				</p>
				<p>
					<label for="launchdek-cl-description">
						<span class="launchdek-field-label-row">
							<button type="button" class="launchdek-field-info launchdek-has-tooltip" data-tooltip="<?php echo esc_attr( $checklist_description_help ); ?>" aria-label="<?php echo esc_attr( $checklist_description_help ); ?>">
								<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
							</button>
							<span><?php esc_html_e( 'Description', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
						</span>
					</label>
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
					<p class="launchdek-checklist-panel-title-field">
						<label for="launchdek-cl-panel-title"><?php esc_html_e( 'Client panel heading', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
						<input
							type="text"
							id="launchdek-cl-panel-title"
							class="regular-text"
							maxlength="80"
							value="<?php echo esc_attr( LAUNCHDEK_Settings::get_client_panel_title() ); ?>"
							placeholder="<?php echo esc_attr( LAUNCHDEK_Settings::get_default_client_panel_title() ); ?>"
						/>
						<span class="launchdek-muted launchdek-checklist-panel-title-help"><?php esc_html_e( 'Heading shown on the client checklist panel. Syncs on the next checklist push or client panel refresh.', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
					</p>
					<div id="launchdek-step-config" class="launchdek-step-config">
						<p class="launchdek-muted"><?php esc_html_e( 'Select a step on the canvas to configure.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
					</div>
				</aside>

				<aside class="launchdek-checklist-preview-sidebar" aria-label="<?php esc_attr_e( 'Checklist preview panel', LAUNCHDEK_TEXT_DOMAIN ); ?>">
					<h3><?php esc_html_e( 'Preview', LAUNCHDEK_TEXT_DOMAIN ); ?></h3>
					<div id="launchdek-checklist-preview" class="launchdek-checklist-preview" aria-live="polite">
						<p class="launchdek-muted"><?php esc_html_e( 'Add a title and steps to preview the client panel.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
					</div>
				</aside>
			</div>

			<p class="launchdek-modal-actions">
				<button type="button" class="button button-primary" id="launchdek-save-checklist"><?php esc_html_e( 'Save Checklist', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				<button type="button" class="button" id="launchdek-canvas-start-blank"><?php esc_html_e( 'Start Blank', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
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

	<div id="launchdek-panel-auto-capture" class="launchdek-tab-panel" data-launchdek-tab-panel="auto-capture" role="tabpanel" aria-labelledby="launchdek-tab-auto-capture" hidden>
		<div class="launchdek-card launchdek-auto-capture-panel">
			<h2><?php esc_html_e( 'Auto-Capture Checklist Steps', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
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
			</div>
		</div>
	</div>

	<?php require LAUNCHDEK_PLUGIN_DIR . 'admin/partials/launchdek-template-picker-modal.php'; ?>
</div>
