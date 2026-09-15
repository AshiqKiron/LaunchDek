<?php
/**
 * Billing admin page.
 *
 * @package LaunchDek
 *
 * @var array  $settings        Plugin settings.
 * @var string $page            Page identifier.
 * @var array  $dashboard_stats Cached dashboard stat cards.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dashboard_stats = isset( $dashboard_stats ) && is_array( $dashboard_stats ) ? $dashboard_stats : LAUNCHDEK_Dashboard_Cache::get_stats();
$sites_connected = (int) ( $dashboard_stats['sites_connected'] ?? 0 );
$active_checklists = (int) ( $dashboard_stats['active_checklists'] ?? 0 );
?>
<div class="wrap launchdek-admin" data-launchdek-page="billing">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="launchdek-grid-2 launchdek-billing-layout">
		<div class="launchdek-card launchdek-billing-plan-card">
			<h2><?php esc_html_e( 'Current Plan', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<p class="launchdek-billing-plan-name"><?php esc_html_e( 'Self-Hosted', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
			<p class="launchdek-billing-plan-badge"><?php esc_html_e( 'Community Edition', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
			<p class="launchdek-muted">
				<?php esc_html_e( 'LaunchDek runs on your WordPress hub with no recurring subscription. Connect client sites, run checklists, and manage your agency workflow from this install.', LAUNCHDEK_TEXT_DOMAIN ); ?>
			</p>
		</div>

		<div class="launchdek-card launchdek-billing-usage-card">
			<h2><?php esc_html_e( 'Hub Usage', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<div class="launchdek-stats-grid launchdek-billing-usage-grid">
				<div class="launchdek-stat-card">
					<span class="launchdek-stat-value"><?php echo esc_html( number_format_i18n( $sites_connected ) ); ?></span>
					<span class="launchdek-stat-label"><?php esc_html_e( 'Sites Connected', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
				</div>
				<div class="launchdek-stat-card">
					<span class="launchdek-stat-value"><?php echo esc_html( number_format_i18n( $active_checklists ) ); ?></span>
					<span class="launchdek-stat-label"><?php esc_html_e( 'Active Checklists', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
				</div>
			</div>
			<p class="launchdek-muted">
				<?php esc_html_e( 'Usage reflects your current hub. There are no per-site billing limits on the self-hosted edition.', LAUNCHDEK_TEXT_DOMAIN ); ?>
			</p>
		</div>
	</div>

	<div class="launchdek-card launchdek-billing-account-card">
		<h2><?php esc_html_e( 'Invoices & Payment', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
		<p class="launchdek-muted">
			<?php esc_html_e( 'No payment method is required for this self-hosted install. When managed billing becomes available, subscription details and invoices will appear here.', LAUNCHDEK_TEXT_DOMAIN ); ?>
		</p>
		<p class="launchdek-billing-actions">
			<a class="button" href="<?php echo esc_url( LAUNCHDEK_PLUGIN_DOCS_URL ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'View Documentation', LAUNCHDEK_TEXT_DOMAIN ); ?>
			</a>
		</p>
	</div>
</div>
