<?php
/**
 * Checklists admin page.
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
		<button type="button" class="page-title-action" id="launchdek-new-checklist"><?php esc_html_e( 'New Checklist', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
	</h1>

	<div id="launchdek-checklist-notice" class="launchdek-notice-area" aria-live="polite"></div>

	<div class="launchdek-grid-2 launchdek-builder-layout">
		<div class="launchdek-card launchdek-checklist-list-card">
			<h2><?php esc_html_e( 'Checklists', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<ul id="launchdek-checklist-list" class="launchdek-list"></ul>
		</div>

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

	<?php require LAUNCHDEK_PLUGIN_DIR . 'admin/partials/launchdek-template-picker-modal.php'; ?>
</div>
