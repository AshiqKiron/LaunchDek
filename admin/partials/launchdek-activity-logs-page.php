<?php
/**
 * Activity logs admin page.
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
<div class="wrap launchdek-admin" data-launchdek-page="activity-logs">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<p class="launchdek-muted launchdek-activity-logs-intro">
		<?php esc_html_e( 'Checklist runs, site changes, and client activity—all in one log. Filter by site, status, or date.', LAUNCHDEK_TEXT_DOMAIN ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . LAUNCHDEK_Admin::PAGE_SLUG . '-sites' ) ); ?>"><?php esc_html_e( 'Back to Sites', LAUNCHDEK_TEXT_DOMAIN ); ?></a>
	</p>

	<div class="launchdek-card">
		<div class="launchdek-audit-filters">
			<label class="launchdek-audit-filter-field">
				<span class="launchdek-audit-filter-label"><?php esc_html_e( 'Site', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
				<select id="launchdek-activity-logs-site" class="launchdek-select">
					<option value=""><?php esc_html_e( 'All sites', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
				</select>
			</label>
			<label class="launchdek-audit-filter-field">
				<span class="launchdek-audit-filter-label"><?php esc_html_e( 'Status', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
				<select id="launchdek-activity-logs-status" class="launchdek-select">
					<option value=""><?php esc_html_e( 'All statuses', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
					<option value="success"><?php esc_html_e( 'Success', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
					<option value="failed"><?php esc_html_e( 'Failed', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
					<option value="warning"><?php esc_html_e( 'Drift / Warning', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
				</select>
			</label>
			<label class="launchdek-audit-filter-field launchdek-filter-date">
				<span class="launchdek-audit-filter-label"><?php esc_html_e( 'From', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
				<input type="date" id="launchdek-activity-logs-date-from" />
			</label>
			<label class="launchdek-audit-filter-field launchdek-filter-date">
				<span class="launchdek-audit-filter-label"><?php esc_html_e( 'To', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
				<input type="date" id="launchdek-activity-logs-date-to" />
			</label>
			<label class="launchdek-audit-filter-field launchdek-audit-filter-search">
				<span class="launchdek-audit-filter-label"><?php esc_html_e( 'Search', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
				<input type="search" id="launchdek-activity-logs-search" class="regular-text" placeholder="<?php esc_attr_e( 'Checklist, site, or note…', LAUNCHDEK_TEXT_DOMAIN ); ?>" />
			</label>
			<div class="launchdek-audit-filter-actions">
				<button type="button" class="button button-primary" id="launchdek-activity-logs-filter"><?php esc_html_e( 'Apply Filters', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
			</div>
		</div>
		<table class="wp-list-table widefat fixed striped launchdek-audit-table" id="launchdek-activity-logs-table">
			<thead>
				<tr>
					<th scope="col" class="launchdek-audit-col-time"><?php esc_html_e( 'When', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
					<th scope="col" class="launchdek-audit-col-user"><?php esc_html_e( 'User', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
					<th scope="col" class="launchdek-audit-col-site"><?php esc_html_e( 'Site', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
					<th scope="col"><?php esc_html_e( 'What happened', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr><td colspan="4" class="launchdek-muted"><?php esc_html_e( 'Loading activity…', LAUNCHDEK_TEXT_DOMAIN ); ?></td></tr>
			</tbody>
		</table>
		<div class="launchdek-activity-logs-load-more-wrap" id="launchdek-activity-logs-load-more-wrap" hidden></div>
	</div>
</div>
