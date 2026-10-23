<?php
/**
 * Dashboard admin page.
 *
 * @package ChecklistDek
 *
 * @var array  $settings                    Plugin settings.
 * @var string $page                        Page identifier.
 * @var array  $checklistdek_dashboard_stats   Cached dashboard stat cards.
 * @var array  $checklistdek_connection_counts Cached connection health summary.
 * @var array  $checklistdek_log_feed          Cached dashboard live log feed entries.
 * @var array  $checklistdek_quick_launch_picker Cached quick-launch site and checklist picker options.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$checklistdek_dashboard_stats     = isset( $checklistdek_dashboard_stats ) && is_array( $checklistdek_dashboard_stats ) ? $checklistdek_dashboard_stats : CHECKLISTDEK_Dashboard_Cache::get_stats();
$checklistdek_connection_counts   = isset( $checklistdek_connection_counts ) && is_array( $checklistdek_connection_counts ) ? $checklistdek_connection_counts : CHECKLISTDEK_Dashboard_Cache::get_connection_counts();
$checklistdek_log_feed            = isset( $checklistdek_log_feed ) && is_array( $checklistdek_log_feed ) ? $checklistdek_log_feed : CHECKLISTDEK_Dashboard_Cache::get_feed();
$checklistdek_quick_launch_picker = isset( $checklistdek_quick_launch_picker ) && is_array( $checklistdek_quick_launch_picker ) ? $checklistdek_quick_launch_picker : CHECKLISTDEK_Dashboard_Cache::get_quick_launch_picker();
$checklistdek_quick_launch_sites  = isset( $checklistdek_quick_launch_picker['sites'] ) && is_array( $checklistdek_quick_launch_picker['sites'] ) ? $checklistdek_quick_launch_picker['sites'] : array();
$checklistdek_quick_launch_lists  = isset( $checklistdek_quick_launch_picker['checklists'] ) && is_array( $checklistdek_quick_launch_picker['checklists'] ) ? $checklistdek_quick_launch_picker['checklists'] : array();
?>
<div class="wrap checklistdek-admin" data-checklistdek-page="dashboard">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="checklistdek-stats-grid" id="checklistdek-stats">
		<div class="checklistdek-stat-card"><span class="checklistdek-stat-value" data-stat="sites_connected"><?php echo esc_html( number_format_i18n( (int) ( $checklistdek_dashboard_stats['sites_connected'] ?? 0 ) ) ); ?></span><span class="checklistdek-stat-label"><?php esc_html_e( 'Total Sites Connected', 'checklistdek' ); ?></span></div>
		<div class="checklistdek-stat-card"><span class="checklistdek-stat-value" data-stat="active_checklists"><?php echo esc_html( number_format_i18n( (int) ( $checklistdek_dashboard_stats['active_checklists'] ?? 0 ) ) ); ?></span><span class="checklistdek-stat-label"><?php esc_html_e( 'Active Checklists', 'checklistdek' ); ?></span></div>
		<div class="checklistdek-stat-card"><span class="checklistdek-stat-value" data-stat="completion_rate"><?php echo esc_html( (string) ( $checklistdek_dashboard_stats['completion_rate'] ?? 0 ) . '%' ); ?></span><span class="checklistdek-stat-label"><?php esc_html_e( 'Checklist Completion Rate', 'checklistdek' ); ?></span></div>
	</div>

	<div class="checklistdek-card checklistdek-connection-panel">
		<div class="checklistdek-connection-panel-header">
			<h2><?php esc_html_e( 'API Connection Status', 'checklistdek' ); ?></h2>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . CHECKLISTDEK_Admin::PAGE_SLUG . '-sites' ) ); ?>" class="button"><?php esc_html_e( 'Manage Sites', 'checklistdek' ); ?></a>
		</div>
		<div id="checklistdek-connection-summary" class="checklistdek-stats-grid checklistdek-connection-stats" role="group" aria-label="<?php esc_attr_e( 'Connection status summary', 'checklistdek' ); ?>" data-checklistdek-preloaded="1">
			<div class="checklistdek-stat-card checklistdek-connection-stat-card is-total">
				<span class="checklistdek-stat-value"><?php echo esc_html( number_format_i18n( (int) ( $checklistdek_connection_counts['total'] ?? 0 ) ) ); ?></span>
				<span class="checklistdek-stat-label"><?php esc_html_e( 'All Sites', 'checklistdek' ); ?></span>
			</div>
			<div class="checklistdek-stat-card checklistdek-connection-stat-card is-healthy">
				<span class="checklistdek-stat-value"><?php echo esc_html( number_format_i18n( (int) ( $checklistdek_connection_counts['healthy'] ?? 0 ) ) ); ?></span>
				<span class="checklistdek-stat-label"><?php esc_html_e( 'Healthy', 'checklistdek' ); ?></span>
			</div>
			<div class="checklistdek-stat-card checklistdek-connection-stat-card is-unhealthy">
				<span class="checklistdek-stat-value"><?php echo esc_html( number_format_i18n( (int) ( $checklistdek_connection_counts['unhealthy'] ?? 0 ) ) ); ?></span>
				<span class="checklistdek-stat-label"><?php esc_html_e( 'Issues', 'checklistdek' ); ?></span>
			</div>
		</div>
	</div>

	<div class="checklistdek-card">
		<h2><?php esc_html_e( 'Live Log Feed', 'checklistdek' ); ?></h2>
		<p class="checklistdek-muted checklistdek-log-feed-meta">
			<?php
			$checklistdek_activity_logs_link = sprintf(
				'<a href="%1$s">%2$s</a>',
				esc_url( admin_url( 'admin.php?page=' . CHECKLISTDEK_Admin::PAGE_SLUG . '-activity-logs' ) ),
				esc_html__( 'View all activity logs', 'checklistdek' )
			);
			echo wp_kses_post(
				sprintf(
					/* translators: %s: link to activity logs page */
					__( 'Showing the latest 15 entries. %s', 'checklistdek' ),
					$checklistdek_activity_logs_link
				)
			);
			?>
		</p>
		<div id="checklistdek-log-feed" class="checklistdek-log-feed" role="log" aria-live="polite" data-checklistdek-preloaded="1">
			<?php if ( empty( $checklistdek_log_feed ) ) : ?>
				<p class="checklistdek-muted"><?php esc_html_e( 'No activity yet.', 'checklistdek' ); ?></p>
			<?php else : ?>
				<?php foreach ( $checklistdek_log_feed as $checklistdek_log ) : ?>
					<?php
					$checklistdek_item_class = 'checklistdek-log-item';
					if ( in_array( $checklistdek_log['action'] ?? '', array( 'client_step_completed', 'client_step_note_added' ), true ) ) {
						$checklistdek_item_class .= ' is-client-activity';
					}
					?>
					<div class="<?php echo esc_attr( $checklistdek_item_class ); ?>">
						<time>[<?php echo esc_html( $checklistdek_log['created_at'] ?? '' ); ?>]</time>
						<?php
						$checklistdek_show_site_label = ! empty( $checklistdek_log['show_site_label'] )
							|| ( ( ! empty( $checklistdek_log['site_id'] ) || ! empty( $checklistdek_log['run_id'] ) ) && ! empty( $checklistdek_log['site_name'] ) );
						?>
						<?php if ( $checklistdek_show_site_label ) : ?>
							<span class="checklistdek-log-site" title="<?php echo esc_attr( $checklistdek_log['site_name'] ); ?>"><?php echo esc_html( $checklistdek_log['site_name'] ); ?></span>
						<?php endif; ?>
						<span class="checklistdek-log-message"><?php echo esc_html( $checklistdek_log['message'] ?? $checklistdek_log['action'] ?? '' ); ?></span>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	</div>

	<div class="checklistdek-quick-launch checklistdek-card">
		<h2><?php esc_html_e( 'Quick Launch Bar', 'checklistdek' ); ?></h2>
		<div class="checklistdek-inline-form">
			<select id="checklistdek-quick-site" class="checklistdek-select" aria-label="<?php esc_attr_e( 'Select site', 'checklistdek' ); ?>" data-checklistdek-preloaded="1">
				<option value=""><?php esc_html_e( 'Select site…', 'checklistdek' ); ?></option>
				<?php foreach ( $checklistdek_quick_launch_sites as $checklistdek_site ) : ?>
					<option value="<?php echo esc_attr( (string) ( $checklistdek_site['id'] ?? '' ) ); ?>"><?php echo esc_html( $checklistdek_site['name'] ?? '' ); ?></option>
				<?php endforeach; ?>
			</select>
			<select id="checklistdek-quick-checklist" class="checklistdek-select" aria-label="<?php esc_attr_e( 'Select checklist', 'checklistdek' ); ?>" data-checklistdek-preloaded="1">
				<option value=""><?php esc_html_e( 'Select checklist…', 'checklistdek' ); ?></option>
				<?php foreach ( $checklistdek_quick_launch_lists as $checklistdek_checklist ) : ?>
					<option value="<?php echo esc_attr( (string) ( $checklistdek_checklist['id'] ?? '' ) ); ?>"><?php echo esc_html( $checklistdek_checklist['title'] ?? '' ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="button button-primary" id="checklistdek-quick-launch"><?php esc_html_e( 'Run Checklist', 'checklistdek' ); ?></button>
		</div>
		<div id="checklistdek-quick-result" class="checklistdek-notice-area"></div>
	</div>

	<?php require CHECKLISTDEK_PLUGIN_DIR . 'admin/partials/checklistdek-onboarding-modal.php'; ?>
	<?php require CHECKLISTDEK_PLUGIN_DIR . 'admin/partials/checklistdek-template-picker-modal.php'; ?>
</div>
