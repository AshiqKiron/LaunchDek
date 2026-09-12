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
		<div class="launchdek-stat-card"><span class="launchdek-stat-value" data-stat="active_workflows">—</span><span class="launchdek-stat-label"><?php esc_html_e( 'Active Workflows', LAUNCHDEK_TEXT_DOMAIN ); ?></span></div>
		<div class="launchdek-stat-card"><span class="launchdek-stat-value" data-stat="completion_rate">—</span><span class="launchdek-stat-label"><?php esc_html_e( 'Workflow Completion Rate', LAUNCHDEK_TEXT_DOMAIN ); ?></span></div>
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
			<select id="launchdek-quick-workflow" class="launchdek-select" aria-label="<?php esc_attr_e( 'Select workflow', LAUNCHDEK_TEXT_DOMAIN ); ?>"><option value=""><?php esc_html_e( 'Select workflow…', LAUNCHDEK_TEXT_DOMAIN ); ?></option></select>
			<button type="button" class="button button-primary" id="launchdek-quick-launch"><?php esc_html_e( 'Run Workflow', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
		</div>
		<div id="launchdek-quick-result" class="launchdek-notice-area"></div>
	</div>
</div>
