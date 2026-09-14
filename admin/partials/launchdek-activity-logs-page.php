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
		<?php esc_html_e( 'Full immutable audit trail with action details. Filter by site, status, or date range.', LAUNCHDEK_TEXT_DOMAIN ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . LAUNCHDEK_Admin::PAGE_SLUG . '-sites' ) ); ?>"><?php esc_html_e( 'Back to Sites', LAUNCHDEK_TEXT_DOMAIN ); ?></a>
	</p>

	<div class="launchdek-card">
		<div class="launchdek-audit-filters launchdek-inline-form">
			<select id="launchdek-activity-logs-site" class="launchdek-select" aria-label="<?php esc_attr_e( 'Filter by site', LAUNCHDEK_TEXT_DOMAIN ); ?>">
				<option value=""><?php esc_html_e( 'All sites', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
			</select>
			<select id="launchdek-activity-logs-status" class="launchdek-select" aria-label="<?php esc_attr_e( 'Status', LAUNCHDEK_TEXT_DOMAIN ); ?>">
				<option value=""><?php esc_html_e( 'All statuses', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
				<option value="success"><?php esc_html_e( 'Success', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
				<option value="failed"><?php esc_html_e( 'Failed', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
				<option value="warning"><?php esc_html_e( 'Drift / Warning', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
			</select>
			<label class="launchdek-filter-date">
				<span class="screen-reader-text"><?php esc_html_e( 'From date', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
				<input type="date" id="launchdek-activity-logs-date-from" aria-label="<?php esc_attr_e( 'From date', LAUNCHDEK_TEXT_DOMAIN ); ?>" />
			</label>
			<label class="launchdek-filter-date">
				<span class="screen-reader-text"><?php esc_html_e( 'To date', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
				<input type="date" id="launchdek-activity-logs-date-to" aria-label="<?php esc_attr_e( 'To date', LAUNCHDEK_TEXT_DOMAIN ); ?>" />
			</label>
			<input type="search" id="launchdek-activity-logs-search" class="regular-text" placeholder="<?php esc_attr_e( 'Search details…', LAUNCHDEK_TEXT_DOMAIN ); ?>" />
			<button type="button" class="button" id="launchdek-activity-logs-filter"><?php esc_html_e( 'Apply Filters', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
		</div>
		<table class="wp-list-table widefat fixed striped launchdek-audit-table" id="launchdek-activity-logs-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Timestamp', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
					<th scope="col"><?php esc_html_e( 'User', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
					<th scope="col"><?php esc_html_e( 'Target Site', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
					<th scope="col"><?php esc_html_e( 'Action & Data Diffs', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr><td colspan="4" class="launchdek-muted"><?php esc_html_e( 'Loading activity…', LAUNCHDEK_TEXT_DOMAIN ); ?></td></tr>
			</tbody>
		</table>
	</div>
</div>
