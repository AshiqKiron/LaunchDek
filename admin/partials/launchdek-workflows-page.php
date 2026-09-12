<?php
/**
 * Workflows admin page.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap launchdek-admin" data-launchdek-page="workflows">
	<h1><?php echo esc_html( get_admin_page_title() ); ?>
		<button type="button" class="page-title-action" id="launchdek-new-workflow"><?php esc_html_e( 'New Workflow', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
	</h1>

	<div class="launchdek-grid-2 launchdek-builder-layout">
		<div class="launchdek-card launchdek-workflow-list-card">
			<h2><?php esc_html_e( 'Workflows', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<ul id="launchdek-workflow-list" class="launchdek-list"></ul>
		</div>

		<div class="launchdek-card" id="launchdek-workflow-editor" hidden>
			<h2><?php esc_html_e( 'Workflow Builder', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<div class="launchdek-wf-meta">
				<p><input type="text" id="launchdek-wf-title" class="large-text" placeholder="<?php esc_attr_e( 'Workflow title', LAUNCHDEK_TEXT_DOMAIN ); ?>" /></p>
				<p><textarea id="launchdek-wf-description" class="large-text" rows="2" placeholder="<?php esc_attr_e( 'Description', LAUNCHDEK_TEXT_DOMAIN ); ?>"></textarea></p>
			</div>

			<div class="launchdek-builder-workspace">
				<div class="launchdek-canvas-card">
					<div class="launchdek-canvas-header">
						<h3><?php esc_html_e( 'Workflow Canvas', LAUNCHDEK_TEXT_DOMAIN ); ?></h3>
						<button type="button" class="button" id="launchdek-add-step"><?php esc_html_e( 'Add Step', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
					</div>
					<div id="launchdek-workflow-canvas" class="launchdek-workflow-canvas" aria-label="<?php esc_attr_e( 'Drag-and-drop workflow builder canvas', LAUNCHDEK_TEXT_DOMAIN ); ?>">
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
				<button type="button" class="button button-primary" id="launchdek-save-workflow"><?php esc_html_e( 'Save Workflow', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				<button type="button" class="button" id="launchdek-export-workflow"><?php esc_html_e( 'Export JSON', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				<button type="button" class="button" id="launchdek-delete-workflow"><?php esc_html_e( 'Delete', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
			</p>
		</div>
	</div>

	<div class="launchdek-card launchdek-import-export">
		<h2><?php esc_html_e( 'Import / Export Center', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
		<div class="launchdek-inline-form">
			<label for="launchdek-import-file" class="screen-reader-text"><?php esc_html_e( 'Import workflow JSON file', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
			<input type="file" id="launchdek-import-file" accept=".json" />
			<label for="launchdek-import-url" class="screen-reader-text"><?php esc_html_e( 'Import workflow from URL', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
			<input type="url" id="launchdek-import-url" class="regular-text" placeholder="<?php esc_attr_e( 'Or paste JSON URL…', LAUNCHDEK_TEXT_DOMAIN ); ?>" />
			<button type="button" class="button" id="launchdek-import-workflow"><?php esc_html_e( 'Import', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
		</div>
	</div>
</div>
