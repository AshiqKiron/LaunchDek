<?php
/**
 * Template picker modal (shared across admin screens).
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="checklistdek-template-picker-modal" class="checklistdek-modal checklistdek-template-picker-modal" hidden role="dialog" aria-modal="true" aria-labelledby="checklistdek-template-picker-title">
	<div class="checklistdek-modal-backdrop"></div>
	<div class="checklistdek-modal-content checklistdek-card checklistdek-template-picker-content">
		<div class="checklistdek-template-picker-header">
			<h2 id="checklistdek-template-picker-title"><?php esc_html_e( 'Choose a Template', 'checklistdek' ); ?></h2>
			<button type="button" class="checklistdek-template-picker-close" aria-label="<?php esc_attr_e( 'Close template picker', 'checklistdek' ); ?>">&times;</button>
		</div>

		<div id="checklistdek-template-picker-filters" class="checklistdek-template-picker-filters" role="tablist" aria-label="<?php esc_attr_e( 'Filter templates by category', 'checklistdek' ); ?>"></div>

		<div id="checklistdek-template-picker-notice" class="checklistdek-notice-area" aria-live="polite"></div>

		<div class="checklistdek-template-picker-body">
			<div id="checklistdek-template-picker-list" class="checklistdek-template-picker-list" role="listbox" aria-label="<?php esc_attr_e( 'Available templates', 'checklistdek' ); ?>">
				<p class="checklistdek-muted checklistdek-template-picker-loading"><?php esc_html_e( 'Loading templates…', 'checklistdek' ); ?></p>
			</div>
			<aside id="checklistdek-template-picker-steps" class="checklistdek-template-picker-steps" aria-live="polite">
				<p class="checklistdek-template-picker-steps-heading"><?php esc_html_e( 'Steps preview', 'checklistdek' ); ?></p>
				<p class="checklistdek-muted checklistdek-template-picker-steps-empty"><?php esc_html_e( 'Select a template to preview its steps.', 'checklistdek' ); ?></p>
				<ol id="checklistdek-template-picker-steps-list" class="checklistdek-template-picker-steps-list" hidden></ol>
			</aside>
		</div>

		<div class="checklistdek-template-picker-footer">
			<button type="button" class="button button-link" id="checklistdek-template-picker-blank"><?php esc_html_e( 'Start blank instead', 'checklistdek' ); ?></button>
			<div class="checklistdek-template-picker-footer-actions">
				<button type="button" class="button" id="checklistdek-template-picker-cancel"><?php esc_html_e( 'Cancel', 'checklistdek' ); ?></button>
				<button type="button" class="button button-primary" id="checklistdek-template-picker-import" disabled><?php esc_html_e( 'Import Template', 'checklistdek' ); ?></button>
			</div>
		</div>
	</div>
</div>
