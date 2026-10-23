<?php
/**
 * Onboarding modal markup (shared across admin screens).
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="checklistdek-onboarding-modal" class="checklistdek-modal" hidden role="dialog" aria-modal="true" aria-labelledby="checklistdek-onboarding-title">
	<div class="checklistdek-modal-backdrop"></div>
	<div class="checklistdek-modal-content checklistdek-card checklistdek-onboarding-modal">
		<div class="checklistdek-onboarding-header">
			<h2 id="checklistdek-onboarding-title" class="checklistdek-onboarding-title-line">
				<span class="checklistdek-onboarding-title-main"><?php esc_html_e( 'ChecklistDek Onboarding', 'checklistdek' ); ?></span>
				<span class="checklistdek-onboarding-title-sep" aria-hidden="true"> — </span>
				<span class="checklistdek-onboarding-title-tagline"><?php esc_html_e( 'Turn your messy Notion SOPs or Google Docs into an active Checklist.', 'checklistdek' ); ?></span>
			</h2>
			<button type="button" class="checklistdek-onboarding-close" aria-label="<?php esc_attr_e( 'Close onboarding', 'checklistdek' ); ?>">&times;</button>
		</div>

		<div
			id="checklistdek-onboarding-progress"
			class="checklistdek-onboarding-progress"
			role="progressbar"
			aria-valuemin="1"
			aria-valuemax="2"
			aria-valuenow="1"
			aria-label="<?php esc_attr_e( 'Onboarding progress', 'checklistdek' ); ?>"
		>
			<div class="checklistdek-onboarding-progress-segments">
				<div class="checklistdek-onboarding-progress-segment is-active" data-step="1">
					<div class="checklistdek-onboarding-progress-segment-bar" aria-hidden="true"></div>
					<div class="checklistdek-onboarding-progress-segment-text">
						<span class="checklistdek-onboarding-progress-segment-label"><?php esc_html_e( 'Step 1', 'checklistdek' ); ?></span>
						<span class="checklistdek-onboarding-progress-segment-hint"><?php esc_html_e( 'Import your checklist from SOPs or docs', 'checklistdek' ); ?></span>
					</div>
				</div>
				<div class="checklistdek-onboarding-progress-segment" data-step="2">
					<div class="checklistdek-onboarding-progress-segment-bar" aria-hidden="true"></div>
					<div class="checklistdek-onboarding-progress-segment-text">
						<span class="checklistdek-onboarding-progress-segment-label"><?php esc_html_e( 'Step 2', 'checklistdek' ); ?></span>
						<span class="checklistdek-onboarding-progress-segment-hint"><?php esc_html_e( 'Connect a client site with App Passwords', 'checklistdek' ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<div id="checklistdek-onboarding-step-1">
			<div class="checklistdek-onboarding-columns">
				<label for="checklistdek-onboarding-paste" class="checklistdek-onboarding-pane-label"><?php esc_html_e( 'Paste your checklist text here:', 'checklistdek' ); ?></label>
				<span class="checklistdek-onboarding-pane-label"><?php esc_html_e( 'Preview', 'checklistdek' ); ?></span>
				<textarea id="checklistdek-onboarding-paste" class="checklistdek-onboarding-textarea" rows="12" placeholder="<?php echo esc_attr__( "Change site permalink structure\nBack up the site before making changes\nUpdate Settings → Permalinks to Post name\nTest key pages and fix 404 redirects", 'checklistdek' ); ?>"></textarea>
				<div id="checklistdek-onboarding-preview" class="checklistdek-onboarding-preview" aria-live="polite">
					<p class="checklistdek-muted"><?php esc_html_e( 'Start typing to see your checklist preview.', 'checklistdek' ); ?></p>
				</div>
			</div>

			<div id="checklistdek-onboarding-step1-notice" class="checklistdek-notice-area"></div>

			<div class="checklistdek-onboarding-footer">
				<button type="button" class="button button-link" id="checklistdek-onboarding-use-template"><?php esc_html_e( 'Start from a template instead', 'checklistdek' ); ?></button>
				<div class="checklistdek-onboarding-footer-actions">
					<button type="button" class="button" id="checklistdek-onboarding-skip"><?php esc_html_e( 'Skip', 'checklistdek' ); ?></button>
					<button type="button" class="button button-primary" id="checklistdek-onboarding-next"><?php esc_html_e( 'Next: Connect', 'checklistdek' ); ?></button>
				</div>
			</div>
		</div>

		<div id="checklistdek-onboarding-step-2" hidden>
			<p class="checklistdek-onboarding-intro"><?php esc_html_e( 'Connect your first remote client site using WordPress App Passwords.', 'checklistdek' ); ?></p>

			<div id="checklistdek-onboarding-existing-sites" class="checklistdek-onboarding-optional" hidden>
				<label for="checklistdek-onboarding-site"><?php esc_html_e( 'Test workflow immediately on:', 'checklistdek' ); ?></label>
				<select id="checklistdek-onboarding-site" class="checklistdek-select" aria-label="<?php esc_attr_e( 'Select site for optional test run', 'checklistdek' ); ?>">
					<option value=""><?php esc_html_e( 'Select site…', 'checklistdek' ); ?></option>
				</select>
			</div>

			<div id="checklistdek-onboarding-connect-panel" class="checklistdek-onboarding-connect-panel">
				<div class="checklistdek-onboarding-field-row">
					<label for="checklistdek-onboarding-site-url"><?php esc_html_e( 'Remote Site URL:', 'checklistdek' ); ?></label>
					<input type="url" id="checklistdek-onboarding-site-url" class="regular-text" placeholder="https://client-site.com" required />
				</div>
				<div class="checklistdek-onboarding-field-row">
					<label for="checklistdek-onboarding-site-username"><?php esc_html_e( 'Admin Username:', 'checklistdek' ); ?></label>
					<input type="text" id="checklistdek-onboarding-site-username" class="regular-text" placeholder="agency_admin" required />
				</div>
				<div class="checklistdek-onboarding-field-row">
					<label for="checklistdek-onboarding-site-password"><?php esc_html_e( 'Application Password:', 'checklistdek' ); ?></label>
					<input type="password" id="checklistdek-onboarding-site-password" class="regular-text" autocomplete="new-password" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" required />
				</div>
				<div class="checklistdek-onboarding-test-row">
					<button type="button" class="button" id="checklistdek-onboarding-test"><?php esc_html_e( 'Test Connection', 'checklistdek' ); ?></button>
					<span id="checklistdek-onboarding-test-status" class="checklistdek-onboarding-test-status checklistdek-muted" role="status" aria-live="polite"></span>
				</div>
			</div>

			<?php
			$checklistdek_panel_setup_id_prefix = 'onboarding';
			$checklistdek_panel_setup_hidden      = true;
			require CHECKLISTDEK_PLUGIN_DIR . 'admin/partials/checklistdek-panel-setup-block.php';
			?>

			<p id="checklistdek-onboarding-existing-site" class="checklistdek-onboarding-existing-site" hidden></p>

			<p class="checklistdek-onboarding-outro"><?php esc_html_e( 'Your imported checklist workflow will be ready to push instantly.', 'checklistdek' ); ?></p>

			<div id="checklistdek-onboarding-step2-notice" class="checklistdek-notice-area"></div>

			<div class="checklistdek-onboarding-footer">
				<button type="button" class="button" id="checklistdek-onboarding-back"><?php esc_html_e( 'Back', 'checklistdek' ); ?></button>
				<div class="checklistdek-onboarding-footer-actions">
					<button type="button" class="button" id="checklistdek-onboarding-skip-step2"><?php esc_html_e( 'Skip', 'checklistdek' ); ?></button>
					<button type="button" class="button button-primary" id="checklistdek-onboarding-launch"><?php esc_html_e( 'Finish & Launch', 'checklistdek' ); ?></button>
				</div>
			</div>
		</div>
	</div>
</div>
