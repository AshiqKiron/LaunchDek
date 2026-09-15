<?php
/**
 * Onboarding modal markup (shared across admin screens).
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="launchdek-onboarding-modal" class="launchdek-modal" hidden role="dialog" aria-modal="true" aria-labelledby="launchdek-onboarding-title">
	<div class="launchdek-modal-backdrop"></div>
	<div class="launchdek-modal-content launchdek-card launchdek-onboarding-modal">
		<div class="launchdek-onboarding-header">
			<h2 id="launchdek-onboarding-title" class="launchdek-onboarding-title-line">
				<span class="launchdek-onboarding-title-main"><?php esc_html_e( 'LaunchDek Onboarding', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
				<span class="launchdek-onboarding-title-sep" aria-hidden="true"> — </span>
				<span class="launchdek-onboarding-title-tagline"><?php esc_html_e( 'Turn your messy Notion SOPs or Google Docs into an active Checklist.', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
			</h2>
			<button type="button" class="launchdek-onboarding-close" aria-label="<?php esc_attr_e( 'Close onboarding', LAUNCHDEK_TEXT_DOMAIN ); ?>">&times;</button>
		</div>

		<div
			id="launchdek-onboarding-progress"
			class="launchdek-onboarding-progress"
			role="progressbar"
			aria-valuemin="1"
			aria-valuemax="2"
			aria-valuenow="1"
			aria-label="<?php esc_attr_e( 'Onboarding progress', LAUNCHDEK_TEXT_DOMAIN ); ?>"
		>
			<div class="launchdek-onboarding-progress-segments">
				<div class="launchdek-onboarding-progress-segment is-active" data-step="1">
					<div class="launchdek-onboarding-progress-segment-bar" aria-hidden="true"></div>
					<div class="launchdek-onboarding-progress-segment-text">
						<span class="launchdek-onboarding-progress-segment-label"><?php esc_html_e( 'Step 1', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
						<span class="launchdek-onboarding-progress-segment-hint"><?php esc_html_e( 'Import your checklist from SOPs or docs', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
					</div>
				</div>
				<div class="launchdek-onboarding-progress-segment" data-step="2">
					<div class="launchdek-onboarding-progress-segment-bar" aria-hidden="true"></div>
					<div class="launchdek-onboarding-progress-segment-text">
						<span class="launchdek-onboarding-progress-segment-label"><?php esc_html_e( 'Step 2', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
						<span class="launchdek-onboarding-progress-segment-hint"><?php esc_html_e( 'Connect a client site with App Passwords', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
					</div>
				</div>
			</div>
		</div>

		<div id="launchdek-onboarding-step-1">
			<div class="launchdek-onboarding-columns">
				<label for="launchdek-onboarding-paste" class="launchdek-onboarding-pane-label"><?php esc_html_e( 'Paste your checklist text here:', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
				<span class="launchdek-onboarding-pane-label"><?php esc_html_e( 'Preview', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
				<textarea id="launchdek-onboarding-paste" class="launchdek-onboarding-textarea" rows="12" placeholder="<?php echo esc_attr( __( "Change site permalink structure\nBack up the site before making changes\nUpdate Settings → Permalinks to Post name\nTest key pages and fix 404 redirects", LAUNCHDEK_TEXT_DOMAIN ) ); ?>"></textarea>
				<div id="launchdek-onboarding-preview" class="launchdek-onboarding-preview" aria-live="polite">
					<p class="launchdek-muted"><?php esc_html_e( 'Start typing to see your checklist preview.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
				</div>
			</div>

			<div id="launchdek-onboarding-step1-notice" class="launchdek-notice-area"></div>

			<div class="launchdek-onboarding-footer">
				<button type="button" class="button button-link" id="launchdek-onboarding-use-template"><?php esc_html_e( 'Start from a template instead', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				<div class="launchdek-onboarding-footer-actions">
					<button type="button" class="button" id="launchdek-onboarding-skip"><?php esc_html_e( 'Skip', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
					<button type="button" class="button button-primary" id="launchdek-onboarding-next"><?php esc_html_e( 'Next: Connect', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				</div>
			</div>
		</div>

		<div id="launchdek-onboarding-step-2" hidden>
			<p class="launchdek-onboarding-intro"><?php esc_html_e( 'Connect your first remote client site using WordPress App Passwords.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>

			<div id="launchdek-onboarding-existing-sites" class="launchdek-onboarding-optional" hidden>
				<label for="launchdek-onboarding-site"><?php esc_html_e( 'Test workflow immediately on:', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
				<select id="launchdek-onboarding-site" class="launchdek-select" aria-label="<?php esc_attr_e( 'Select site for optional test run', LAUNCHDEK_TEXT_DOMAIN ); ?>">
					<option value=""><?php esc_html_e( 'Select site…', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
				</select>
			</div>

			<div id="launchdek-onboarding-connect-panel" class="launchdek-onboarding-connect-panel">
				<div class="launchdek-onboarding-field-row">
					<label for="launchdek-onboarding-site-url"><?php esc_html_e( 'Remote Site URL:', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
					<input type="url" id="launchdek-onboarding-site-url" class="regular-text" placeholder="https://client-site.com" required />
				</div>
				<div class="launchdek-onboarding-field-row">
					<label for="launchdek-onboarding-site-username"><?php esc_html_e( 'Admin Username:', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
					<input type="text" id="launchdek-onboarding-site-username" class="regular-text" placeholder="agency_admin" required />
				</div>
				<div class="launchdek-onboarding-field-row">
					<label for="launchdek-onboarding-site-password"><?php esc_html_e( 'Application Password:', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
					<input type="password" id="launchdek-onboarding-site-password" class="regular-text" autocomplete="new-password" placeholder="xxxx xxxx xxxx xxxx xxxx xxxx" required />
				</div>
				<div class="launchdek-onboarding-test-row">
					<button type="button" class="button" id="launchdek-onboarding-test"><?php esc_html_e( 'Test Connection', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
					<span id="launchdek-onboarding-test-status" class="launchdek-onboarding-test-status launchdek-muted" role="status" aria-live="polite"></span>
				</div>
			</div>

			<p id="launchdek-onboarding-existing-site" class="launchdek-onboarding-existing-site" hidden></p>

			<p class="launchdek-onboarding-outro"><?php esc_html_e( 'Your imported checklist workflow will be ready to push instantly.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>

			<div id="launchdek-onboarding-step2-notice" class="launchdek-notice-area"></div>

			<div class="launchdek-onboarding-footer">
				<button type="button" class="button" id="launchdek-onboarding-back"><?php esc_html_e( 'Back', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				<div class="launchdek-onboarding-footer-actions">
					<button type="button" class="button" id="launchdek-onboarding-skip-step2"><?php esc_html_e( 'Skip', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
					<button type="button" class="button button-primary" id="launchdek-onboarding-launch"><?php esc_html_e( 'Finish & Launch', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				</div>
			</div>
		</div>
	</div>
</div>
