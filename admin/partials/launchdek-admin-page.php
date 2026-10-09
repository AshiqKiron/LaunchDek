<?php
/**
 * Dashboard admin page.
 *
 * @package LaunchDek
 *
 * @var array  $settings                    Plugin settings.
 * @var string $page                        Page identifier.
 * @var array  $launchdek_dashboard_stats   Cached dashboard stat cards.
 * @var array  $launchdek_connection_counts Cached connection health summary.
 * @var array  $launchdek_log_feed          Cached dashboard live log feed entries.
 * @var array  $launchdek_quick_launch_picker Cached quick-launch site and checklist picker options.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$launchdek_dashboard_stats     = isset( $launchdek_dashboard_stats ) && is_array( $launchdek_dashboard_stats ) ? $launchdek_dashboard_stats : LAUNCHDEK_Dashboard_Cache::get_stats();
$launchdek_connection_counts   = isset( $launchdek_connection_counts ) && is_array( $launchdek_connection_counts ) ? $launchdek_connection_counts : LAUNCHDEK_Dashboard_Cache::get_connection_counts();
$launchdek_log_feed            = isset( $launchdek_log_feed ) && is_array( $launchdek_log_feed ) ? $launchdek_log_feed : LAUNCHDEK_Dashboard_Cache::get_feed();
$launchdek_quick_launch_picker = isset( $launchdek_quick_launch_picker ) && is_array( $launchdek_quick_launch_picker ) ? $launchdek_quick_launch_picker : LAUNCHDEK_Dashboard_Cache::get_quick_launch_picker();
$launchdek_quick_launch_sites  = isset( $launchdek_quick_launch_picker['sites'] ) && is_array( $launchdek_quick_launch_picker['sites'] ) ? $launchdek_quick_launch_picker['sites'] : array();
$launchdek_quick_launch_lists  = isset( $launchdek_quick_launch_picker['checklists'] ) && is_array( $launchdek_quick_launch_picker['checklists'] ) ? $launchdek_quick_launch_picker['checklists'] : array();
?>
<div class="wrap launchdek-admin" data-launchdek-page="dashboard">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="launchdek-stats-grid" id="launchdek-stats">
		<div class="launchdek-stat-card"><span class="launchdek-stat-value" data-stat="sites_connected"><?php echo esc_html( number_format_i18n( (int) ( $launchdek_dashboard_stats['sites_connected'] ?? 0 ) ) ); ?></span><span class="launchdek-stat-label"><?php esc_html_e( 'Total Sites Connected', 'launchdek' ); ?></span></div>
		<div class="launchdek-stat-card"><span class="launchdek-stat-value" data-stat="active_checklists"><?php echo esc_html( number_format_i18n( (int) ( $launchdek_dashboard_stats['active_checklists'] ?? 0 ) ) ); ?></span><span class="launchdek-stat-label"><?php esc_html_e( 'Active Checklists', 'launchdek' ); ?></span></div>
		<div class="launchdek-stat-card"><span class="launchdek-stat-value" data-stat="completion_rate"><?php echo esc_html( (string) ( $launchdek_dashboard_stats['completion_rate'] ?? 0 ) . '%' ); ?></span><span class="launchdek-stat-label"><?php esc_html_e( 'Checklist Completion Rate', 'launchdek' ); ?></span></div>
	</div>

	<div class="launchdek-card launchdek-connection-panel">
		<div class="launchdek-connection-panel-header">
			<h2><?php esc_html_e( 'API Connection Status', 'launchdek' ); ?></h2>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . LAUNCHDEK_Admin::PAGE_SLUG . '-sites' ) ); ?>" class="button"><?php esc_html_e( 'Manage Sites', 'launchdek' ); ?></a>
		</div>
		<div id="launchdek-connection-summary" class="launchdek-stats-grid launchdek-connection-stats" role="group" aria-label="<?php esc_attr_e( 'Connection status summary', 'launchdek' ); ?>" data-launchdek-preloaded="1">
			<div class="launchdek-stat-card launchdek-connection-stat-card is-total">
				<span class="launchdek-stat-value"><?php echo esc_html( number_format_i18n( (int) ( $launchdek_connection_counts['total'] ?? 0 ) ) ); ?></span>
				<span class="launchdek-stat-label"><?php esc_html_e( 'All Sites', 'launchdek' ); ?></span>
			</div>
			<div class="launchdek-stat-card launchdek-connection-stat-card is-healthy">
				<span class="launchdek-stat-value"><?php echo esc_html( number_format_i18n( (int) ( $launchdek_connection_counts['healthy'] ?? 0 ) ) ); ?></span>
				<span class="launchdek-stat-label"><?php esc_html_e( 'Healthy', 'launchdek' ); ?></span>
			</div>
			<div class="launchdek-stat-card launchdek-connection-stat-card is-unhealthy">
				<span class="launchdek-stat-value"><?php echo esc_html( number_format_i18n( (int) ( $launchdek_connection_counts['unhealthy'] ?? 0 ) ) ); ?></span>
				<span class="launchdek-stat-label"><?php esc_html_e( 'Issues', 'launchdek' ); ?></span>
			</div>
		</div>
	</div>

	<div class="launchdek-card">
		<h2><?php esc_html_e( 'Live Log Feed', 'launchdek' ); ?></h2>
		<p class="launchdek-muted launchdek-log-feed-meta">
			<?php
			printf(
				/* translators: %s: link to activity logs page */
				esc_html__( 'Showing the latest 15 entries. %s', 'launchdek' ),
				'<a href="' . esc_url( admin_url( 'admin.php?page=' . LAUNCHDEK_Admin::PAGE_SLUG . '-activity-logs' ) ) . '">' . esc_html__( 'View all activity logs', 'launchdek' ) . '</a>'
			);
			?>
		</p>
		<div id="launchdek-log-feed" class="launchdek-log-feed" role="log" aria-live="polite" data-launchdek-preloaded="1">
			<?php if ( empty( $launchdek_log_feed ) ) : ?>
				<p class="launchdek-muted"><?php esc_html_e( 'No activity yet.', 'launchdek' ); ?></p>
			<?php else : ?>
				<?php foreach ( $launchdek_log_feed as $launchdek_log ) : ?>
					<?php
					$launchdek_item_class = 'launchdek-log-item';
					if ( in_array( $launchdek_log['action'] ?? '', array( 'client_step_completed', 'client_step_note_added' ), true ) ) {
						$launchdek_item_class .= ' is-client-activity';
					}
					?>
					<div class="<?php echo esc_attr( $launchdek_item_class ); ?>">
						<time>[<?php echo esc_html( $launchdek_log['created_at'] ?? '' ); ?>]</time>
						<?php
						$launchdek_show_site_label = ! empty( $launchdek_log['show_site_label'] )
							|| ( ( ! empty( $launchdek_log['site_id'] ) || ! empty( $launchdek_log['run_id'] ) ) && ! empty( $launchdek_log['site_name'] ) );
						?>
						<?php if ( $launchdek_show_site_label ) : ?>
							<span class="launchdek-log-site" title="<?php echo esc_attr( $launchdek_log['site_name'] ); ?>"><?php echo esc_html( $launchdek_log['site_name'] ); ?></span>
						<?php endif; ?>
						<span class="launchdek-log-message"><?php echo esc_html( $launchdek_log['message'] ?? $launchdek_log['action'] ?? '' ); ?></span>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>

	<div class="launchdek-quick-launch launchdek-card">
		<h2><?php esc_html_e( 'Quick Launch Bar', 'launchdek' ); ?></h2>
		<div class="launchdek-inline-form">
			<select id="launchdek-quick-site" class="launchdek-select" aria-label="<?php esc_attr_e( 'Select site', 'launchdek' ); ?>" data-launchdek-preloaded="1">
				<option value=""><?php esc_html_e( 'Select site…', 'launchdek' ); ?></option>
				<?php foreach ( $launchdek_quick_launch_sites as $launchdek_site ) : ?>
					<option value="<?php echo esc_attr( (string) ( $launchdek_site['id'] ?? '' ) ); ?>"><?php echo esc_html( $launchdek_site['name'] ?? '' ); ?></option>
				<?php endforeach; ?>
			</select>
			<select id="launchdek-quick-checklist" class="launchdek-select" aria-label="<?php esc_attr_e( 'Select checklist', 'launchdek' ); ?>" data-launchdek-preloaded="1">
				<option value=""><?php esc_html_e( 'Select checklist…', 'launchdek' ); ?></option>
				<?php foreach ( $launchdek_quick_launch_lists as $launchdek_checklist ) : ?>
					<option value="<?php echo esc_attr( (string) ( $launchdek_checklist['id'] ?? '' ) ); ?>"><?php echo esc_html( $launchdek_checklist['title'] ?? '' ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="button button-primary" id="launchdek-quick-launch"><?php esc_html_e( 'Run Checklist', 'launchdek' ); ?></button>
		</div>
		<div id="launchdek-quick-result" class="launchdek-notice-area"></div>
	</div>

	<?php require LAUNCHDEK_PLUGIN_DIR . 'admin/partials/launchdek-onboarding-modal.php'; ?>
	<?php require LAUNCHDEK_PLUGIN_DIR . 'admin/partials/launchdek-template-picker-modal.php'; ?>
</div>
