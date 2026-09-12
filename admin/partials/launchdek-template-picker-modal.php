<?php
/**
 * Template picker modal (shared across admin screens).
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="launchdek-template-picker-modal" class="launchdek-modal launchdek-template-picker-modal" hidden role="dialog" aria-modal="true" aria-labelledby="launchdek-template-picker-title">
	<div class="launchdek-modal-backdrop"></div>
	<div class="launchdek-modal-content launchdek-card launchdek-template-picker-content">
		<div class="launchdek-template-picker-header">
			<h2 id="launchdek-template-picker-title"><?php esc_html_e( 'Choose a Template', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<button type="button" class="launchdek-template-picker-close" aria-label="<?php esc_attr_e( 'Close template picker', LAUNCHDEK_TEXT_DOMAIN ); ?>">&times;</button>
		</div>

		<div id="launchdek-template-picker-filters" class="launchdek-template-picker-filters" role="tablist" aria-label="<?php esc_attr_e( 'Filter templates by category', LAUNCHDEK_TEXT_DOMAIN ); ?>"></div>

		<div id="launchdek-template-picker-notice" class="launchdek-notice-area" aria-live="polite"></div>

		<div class="launchdek-template-picker-body">
			<div id="launchdek-template-picker-list" class="launchdek-template-picker-list" role="listbox" aria-label="<?php esc_attr_e( 'Available templates', LAUNCHDEK_TEXT_DOMAIN ); ?>">
				<p class="launchdek-muted launchdek-template-picker-loading"><?php esc_html_e( 'Loading templates…', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
			</div>
			<aside id="launchdek-template-picker-steps" class="launchdek-template-picker-steps" aria-live="polite">
				<p class="launchdek-template-picker-steps-heading"><?php esc_html_e( 'Steps preview', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
				<p class="launchdek-muted launchdek-template-picker-steps-empty"><?php esc_html_e( 'Select a template to preview its steps.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
				<ol id="launchdek-template-picker-steps-list" class="launchdek-template-picker-steps-list" hidden></ol>
			</aside>
		</div>

		<div class="launchdek-template-picker-footer">
			<button type="button" class="button button-link" id="launchdek-template-picker-blank"><?php esc_html_e( 'Start blank instead', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
			<div class="launchdek-template-picker-footer-actions">
				<button type="button" class="button" id="launchdek-template-picker-cancel"><?php esc_html_e( 'Cancel', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				<button type="button" class="button button-primary" id="launchdek-template-picker-import" disabled><?php esc_html_e( 'Import Template', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
			</div>
		</div>
	</div>
</div>
