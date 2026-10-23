<?php
/**
 * Activity logs admin page.
 *
 * @package ChecklistDek
 *
 * @var array  $settings Plugin settings.
 * @var string $page     Page identifier.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap checklistdek-admin" data-checklistdek-page="activity-logs">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<p class="checklistdek-muted checklistdek-activity-logs-intro">
		<?php esc_html_e( 'Checklist runs, site changes, and client activity—all in one log. Filter by site, status, or date.', 'checklistdek' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . CHECKLISTDEK_Admin::PAGE_SLUG . '-sites' ) ); ?>"><?php esc_html_e( 'Back to Sites', 'checklistdek' ); ?></a>
	</p>

	<div class="checklistdek-card">
		<div class="checklistdek-audit-filters">
			<label class="checklistdek-audit-filter-field">
				<span class="checklistdek-audit-filter-label"><?php esc_html_e( 'Site', 'checklistdek' ); ?></span>
				<select id="checklistdek-activity-logs-site" class="checklistdek-select">
					<option value=""><?php esc_html_e( 'All sites', 'checklistdek' ); ?></option>
				</select>
			</label>
			<label class="checklistdek-audit-filter-field">
				<span class="checklistdek-audit-filter-label"><?php esc_html_e( 'Status', 'checklistdek' ); ?></span>
				<select id="checklistdek-activity-logs-status" class="checklistdek-select">
					<option value=""><?php esc_html_e( 'All statuses', 'checklistdek' ); ?></option>
					<option value="success"><?php esc_html_e( 'Success', 'checklistdek' ); ?></option>
					<option value="failed"><?php esc_html_e( 'Failed', 'checklistdek' ); ?></option>
					<option value="warning"><?php esc_html_e( 'Drift / Warning', 'checklistdek' ); ?></option>
				</select>
			</label>
			<label class="checklistdek-audit-filter-field checklistdek-filter-date">
				<span class="checklistdek-audit-filter-label"><?php esc_html_e( 'From', 'checklistdek' ); ?></span>
				<input type="date" id="checklistdek-activity-logs-date-from" />
			</label>
			<label class="checklistdek-audit-filter-field checklistdek-filter-date">
				<span class="checklistdek-audit-filter-label"><?php esc_html_e( 'To', 'checklistdek' ); ?></span>
				<input type="date" id="checklistdek-activity-logs-date-to" />
			</label>
			<label class="checklistdek-audit-filter-field checklistdek-audit-filter-search">
				<span class="checklistdek-audit-filter-label"><?php esc_html_e( 'Search', 'checklistdek' ); ?></span>
				<input type="search" id="checklistdek-activity-logs-search" class="regular-text" placeholder="<?php esc_attr_e( 'Checklist, site, or note…', 'checklistdek' ); ?>" />
			</label>
			<div class="checklistdek-audit-filter-actions">
				<button type="button" class="button button-primary" id="checklistdek-activity-logs-filter"><?php esc_html_e( 'Apply Filters', 'checklistdek' ); ?></button>
			</div>
		</div>
		<div class="checklistdek-table-scroll">
		<table class="wp-list-table widefat fixed striped checklistdek-audit-table" id="checklistdek-activity-logs-table">
			<thead>
				<tr>
					<th scope="col" class="checklistdek-audit-col-time"><?php esc_html_e( 'When', 'checklistdek' ); ?></th>
					<th scope="col" class="checklistdek-audit-col-user"><?php esc_html_e( 'User', 'checklistdek' ); ?></th>
					<th scope="col" class="checklistdek-audit-col-site"><?php esc_html_e( 'Site', 'checklistdek' ); ?></th>
					<th scope="col"><?php esc_html_e( 'What happened', 'checklistdek' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr><td colspan="4" class="checklistdek-muted"><?php esc_html_e( 'Loading activity…', 'checklistdek' ); ?></td></tr>
			</tbody>
		</table>
		</div>
		<div class="checklistdek-activity-logs-load-more-wrap" id="checklistdek-activity-logs-load-more-wrap" hidden></div>
	</div>
</div>
