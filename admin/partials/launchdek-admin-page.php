<?php
/**
 * Dashboard admin page.
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
<div class="wrap launchdek-admin" data-launchdek-page="dashboard">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="launchdek-stats-grid" id="launchdek-stats">
		<div class="launchdek-stat-card"><span class="launchdek-stat-value" data-stat="sites_connected">—</span><span class="launchdek-stat-label"><?php esc_html_e( 'Total Sites Connected', LAUNCHDEK_TEXT_DOMAIN ); ?></span></div>
		<div class="launchdek-stat-card"><span class="launchdek-stat-value" data-stat="active_checklists">—</span><span class="launchdek-stat-label"><?php esc_html_e( 'Active Checklists', LAUNCHDEK_TEXT_DOMAIN ); ?></span></div>
		<div class="launchdek-stat-card"><span class="launchdek-stat-value" data-stat="completion_rate">—</span><span class="launchdek-stat-label"><?php esc_html_e( 'Checklist Completion Rate', LAUNCHDEK_TEXT_DOMAIN ); ?></span></div>
	</div>

	<div class="launchdek-card">
		<h2><?php esc_html_e( 'API Connection Status Ticker', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
		<div id="launchdek-ticker" class="launchdek-ticker launchdek-ticker-inline" role="status" aria-live="polite">
			<p class="launchdek-muted"><?php esc_html_e( 'Loading connection status…', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
		</div>
	</div>

	<div class="launchdek-card">
		<h2><?php esc_html_e( 'Live Log Feed', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
		<div id="launchdek-log-feed" class="launchdek-log-feed" role="log" aria-live="polite">
			<p class="launchdek-muted"><?php esc_html_e( 'Loading activity…', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
		</div>
	</div>

	<div class="launchdek-quick-launch launchdek-card">
		<h2><?php esc_html_e( 'Quick Launch Bar', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
		<div class="launchdek-inline-form">
			<select id="launchdek-quick-site" class="launchdek-select" aria-label="<?php esc_attr_e( 'Select site', LAUNCHDEK_TEXT_DOMAIN ); ?>"><option value=""><?php esc_html_e( 'Select site…', LAUNCHDEK_TEXT_DOMAIN ); ?></option></select>
			<select id="launchdek-quick-checklist" class="launchdek-select" aria-label="<?php esc_attr_e( 'Select checklist', LAUNCHDEK_TEXT_DOMAIN ); ?>"><option value=""><?php esc_html_e( 'Select checklist…', LAUNCHDEK_TEXT_DOMAIN ); ?></option></select>
			<button type="button" class="button button-primary" id="launchdek-quick-launch"><?php esc_html_e( 'Run Checklist', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
		</div>
		<div id="launchdek-quick-result" class="launchdek-notice-area"></div>
	</div>

	<div id="launchdek-onboarding-modal" class="launchdek-modal" hidden role="dialog" aria-modal="true" aria-labelledby="launchdek-onboarding-title">
		<div class="launchdek-modal-backdrop"></div>
		<div class="launchdek-modal-content launchdek-card launchdek-onboarding-modal">
			<div class="launchdek-onboarding-header">
				<h2 id="launchdek-onboarding-title"><?php esc_html_e( 'LaunchDek Onboarding', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
				<button type="button" class="launchdek-onboarding-close button-link" aria-label="<?php esc_attr_e( 'Close onboarding', LAUNCHDEK_TEXT_DOMAIN ); ?>">&times;</button>
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
				<div class="launchdek-onboarding-progress-track">
					<div id="launchdek-onboarding-progress-fill" class="launchdek-onboarding-progress-fill"></div>
				</div>
			</div>

			<div id="launchdek-onboarding-step-1">
				<p class="launchdek-onboarding-intro"><?php esc_html_e( 'Turn your messy Notion SOPs or Google Docs into an active workflow.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>

				<div class="launchdek-onboarding-columns">
					<div class="launchdek-onboarding-pane">
						<label for="launchdek-onboarding-paste" class="launchdek-onboarding-pane-label"><?php esc_html_e( 'Paste your checklist text here:', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
						<textarea id="launchdek-onboarding-paste" class="launchdek-onboarding-textarea" rows="10" placeholder="<?php echo esc_attr( "Client onboarding\nInstall and configure Wordfence\nSet up SMTP and send a test email" ); ?>"></textarea>
					</div>
					<div class="launchdek-onboarding-pane">
						<p class="launchdek-onboarding-pane-label"><?php esc_html_e( 'Live Interactive Preview:', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
						<div id="launchdek-onboarding-preview" class="launchdek-onboarding-preview" aria-live="polite">
							<p class="launchdek-muted"><?php esc_html_e( 'Start typing to see your checklist preview.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
						</div>
					</div>
				</div>

				<div class="launchdek-onboarding-optional">
					<label for="launchdek-onboarding-site"><?php esc_html_e( 'Test workflow immediately on:', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
					<select id="launchdek-onboarding-site" class="launchdek-select" aria-label="<?php esc_attr_e( 'Select site for optional test run', LAUNCHDEK_TEXT_DOMAIN ); ?>">
						<option value=""><?php esc_html_e( 'Select site…', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
					</select>
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
</div>
