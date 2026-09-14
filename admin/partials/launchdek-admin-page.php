<?php
/**
 * Dashboard admin page.
 *
 * @package LaunchDek
 *
 * @var array  $settings          Plugin settings.
 * @var string $page              Page identifier.
 * @var array  $dashboard_stats   Cached dashboard stat cards.
 * @var array  $connection_counts Cached connection health summary.
 * @var array  $log_feed          Cached dashboard live log feed entries.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dashboard_stats   = isset( $dashboard_stats ) && is_array( $dashboard_stats ) ? $dashboard_stats : LAUNCHDEK_Dashboard_Cache::get_stats();
$connection_counts = isset( $connection_counts ) && is_array( $connection_counts ) ? $connection_counts : LAUNCHDEK_Dashboard_Cache::get_connection_counts();
$log_feed          = isset( $log_feed ) && is_array( $log_feed ) ? $log_feed : LAUNCHDEK_Dashboard_Cache::get_feed();
?>
<div class="wrap launchdek-admin" data-launchdek-page="dashboard">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="launchdek-stats-grid" id="launchdek-stats">
		<div class="launchdek-stat-card"><span class="launchdek-stat-value" data-stat="sites_connected"><?php echo esc_html( number_format_i18n( (int) ( $dashboard_stats['sites_connected'] ?? 0 ) ) ); ?></span><span class="launchdek-stat-label"><?php esc_html_e( 'Total Sites Connected', LAUNCHDEK_TEXT_DOMAIN ); ?></span></div>
		<div class="launchdek-stat-card"><span class="launchdek-stat-value" data-stat="active_checklists"><?php echo esc_html( number_format_i18n( (int) ( $dashboard_stats['active_checklists'] ?? 0 ) ) ); ?></span><span class="launchdek-stat-label"><?php esc_html_e( 'Active Checklists', LAUNCHDEK_TEXT_DOMAIN ); ?></span></div>
		<div class="launchdek-stat-card"><span class="launchdek-stat-value" data-stat="completion_rate"><?php echo esc_html( (string) ( $dashboard_stats['completion_rate'] ?? 0 ) . '%' ); ?></span><span class="launchdek-stat-label"><?php esc_html_e( 'Checklist Completion Rate', LAUNCHDEK_TEXT_DOMAIN ); ?></span></div>
	</div>

	<div class="launchdek-card launchdek-connection-panel">
		<div class="launchdek-connection-panel-header">
			<h2><?php esc_html_e( 'API Connection Status', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . LAUNCHDEK_Admin::PAGE_SLUG . '-sites' ) ); ?>" class="button"><?php esc_html_e( 'Manage Sites', LAUNCHDEK_TEXT_DOMAIN ); ?></a>
		</div>
		<div id="launchdek-connection-summary" class="launchdek-stats-grid launchdek-connection-stats" role="group" aria-label="<?php esc_attr_e( 'Connection status summary', LAUNCHDEK_TEXT_DOMAIN ); ?>" data-launchdek-preloaded="1">
			<div class="launchdek-stat-card launchdek-connection-stat-card is-total">
				<span class="launchdek-stat-value"><?php echo esc_html( number_format_i18n( (int) ( $connection_counts['total'] ?? 0 ) ) ); ?></span>
				<span class="launchdek-stat-label"><?php esc_html_e( 'All Sites', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
			</div>
			<div class="launchdek-stat-card launchdek-connection-stat-card is-healthy">
				<span class="launchdek-stat-value"><?php echo esc_html( number_format_i18n( (int) ( $connection_counts['healthy'] ?? 0 ) ) ); ?></span>
				<span class="launchdek-stat-label"><?php esc_html_e( 'Healthy', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
			</div>
			<div class="launchdek-stat-card launchdek-connection-stat-card is-unhealthy">
				<span class="launchdek-stat-value"><?php echo esc_html( number_format_i18n( (int) ( $connection_counts['unhealthy'] ?? 0 ) ) ); ?></span>
				<span class="launchdek-stat-label"><?php esc_html_e( 'Issues', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
			</div>
			<div class="launchdek-stat-card launchdek-connection-stat-card is-unknown">
				<span class="launchdek-stat-value"><?php echo esc_html( number_format_i18n( (int) ( $connection_counts['unknown'] ?? 0 ) ) ); ?></span>
				<span class="launchdek-stat-label"><?php esc_html_e( 'Unknown', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
			</div>
		</div>
	</div>

	<div class="launchdek-card">
		<h2><?php esc_html_e( 'Live Log Feed', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
		<p class="launchdek-muted launchdek-log-feed-meta">
			<?php
			printf(
				/* translators: %s: link to activity logs page */
				esc_html__( 'Showing the latest 15 entries. %s', LAUNCHDEK_TEXT_DOMAIN ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=' . LAUNCHDEK_Admin::PAGE_SLUG . '-activity-logs' ) ) . '">' . esc_html__( 'View all activity logs', LAUNCHDEK_TEXT_DOMAIN ) . '</a>'
			);
			?>
		</p>
		<div id="launchdek-log-feed" class="launchdek-log-feed" role="log" aria-live="polite" data-launchdek-preloaded="1">
			<?php if ( empty( $log_feed ) ) : ?>
				<p class="launchdek-muted"><?php esc_html_e( 'No activity yet.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
			<?php else : ?>
				<?php foreach ( $log_feed as $log ) : ?>
					<?php
					$item_class = 'launchdek-log-item';
					if ( in_array( $log['action'] ?? '', array( 'client_step_completed', 'client_step_note_added' ), true ) ) {
						$item_class .= ' is-client-activity';
					}
					?>
					<div class="<?php echo esc_attr( $item_class ); ?>">
						<time>[<?php echo esc_html( $log['created_at'] ?? '' ); ?>]</time>
						<?php if ( ( ! empty( $log['site_id'] ) || ! empty( $log['run_id'] ) ) && ! empty( $log['site_name'] ) ) : ?>
							<span class="launchdek-log-site" title="<?php echo esc_attr( $log['site_name'] ); ?>"><?php echo esc_html( $log['site_name'] ); ?></span>
						<?php endif; ?>
						<span class="launchdek-log-message"><?php echo esc_html( $log['message'] ?? $log['action'] ?? '' ); ?></span>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
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

	<?php require LAUNCHDEK_PLUGIN_DIR . 'admin/partials/launchdek-onboarding-modal.php'; ?>
	<?php require LAUNCHDEK_PLUGIN_DIR . 'admin/partials/launchdek-template-picker-modal.php'; ?>
</div>
