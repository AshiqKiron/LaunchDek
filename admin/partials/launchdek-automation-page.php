<?php
/**
 * Automation & Audit admin page.
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
<div class="wrap launchdek-admin" data-launchdek-page="automation">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="launchdek-card">
		<h2><?php esc_html_e( 'Execution & Run Control', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
		<p class="launchdek-muted"><?php esc_html_e( 'Select targets and queue checklist runs across one or many remote sites.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>

		<div class="launchdek-target-selector">
			<div class="launchdek-target-form">
				<div class="launchdek-target-form-controls launchdek-inline-form">
					<select id="launchdek-run-checklist" class="launchdek-select" aria-label="<?php esc_attr_e( 'Checklist', LAUNCHDEK_TEXT_DOMAIN ); ?>">
						<option value=""><?php esc_html_e( 'Select checklist…', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
					</select>
					<div class="launchdek-target-actions">
						<button type="button" class="button" id="launchdek-queue-add"><?php esc_html_e( 'Add to Batch Queue', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
						<button type="button" class="button button-primary" id="launchdek-start-run"><?php esc_html_e( 'Start Single Run', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
						<button type="button" class="button" id="launchdek-queue-process"><?php esc_html_e( 'Process Queue', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
						<button type="button" class="button" id="launchdek-queue-clear"><?php esc_html_e( 'Clear Queue', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
					</div>
				</div>
				<div class="launchdek-target-sites">
					<label for="launchdek-run-site"><?php esc_html_e( 'Target sites', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
					<select id="launchdek-run-site" class="launchdek-select launchdek-select-multi" multiple size="5" aria-label="<?php esc_attr_e( 'Target sites', LAUNCHDEK_TEXT_DOMAIN ); ?>"></select>
					<p class="launchdek-muted launchdek-field-hint"><?php esc_html_e( 'Hold Ctrl or Cmd to select multiple sites.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
				</div>
			</div>

			<div class="launchdek-batch-queue">
				<h3><?php esc_html_e( 'Batch Queue', LAUNCHDEK_TEXT_DOMAIN ); ?></h3>
				<table class="wp-list-table widefat fixed striped" id="launchdek-batch-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Site', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Checklist', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Status', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Run', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<tr class="launchdek-batch-empty">
							<td colspan="5" class="launchdek-muted"><?php esc_html_e( 'No queued runs. Select sites and add to the batch queue.', LAUNCHDEK_TEXT_DOMAIN ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="launchdek-run-controls launchdek-inline-form">
				<button type="button" class="button" id="launchdek-run-auto"><?php esc_html_e( 'Run Auto Steps', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				<button type="button" class="button" id="launchdek-run-next"><?php esc_html_e( 'Execute Next Step', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				<button type="button" class="button" id="launchdek-run-push-client" hidden><?php esc_html_e( 'Refresh Client Panel', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
			</div>
			<div id="launchdek-run-notice" class="launchdek-notice-area" aria-live="polite"></div>
		</div>
	</div>

	<div class="launchdek-automation-layout">
		<div class="launchdek-automation-main">
			<div class="launchdek-card">
				<h2><?php esc_html_e( 'Immutable Audit Logs', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
				<div class="launchdek-audit-filters launchdek-inline-form">
					<select id="launchdek-audit-status" class="launchdek-select" aria-label="<?php esc_attr_e( 'Status', LAUNCHDEK_TEXT_DOMAIN ); ?>">
						<option value=""><?php esc_html_e( 'All statuses', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
						<option value="success"><?php esc_html_e( 'Success', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
						<option value="failed"><?php esc_html_e( 'Failed', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
						<option value="warning"><?php esc_html_e( 'Drift / Warning', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
					</select>
					<label class="launchdek-filter-date">
						<span class="screen-reader-text"><?php esc_html_e( 'From date', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
						<input type="date" id="launchdek-audit-date-from" aria-label="<?php esc_attr_e( 'From date', LAUNCHDEK_TEXT_DOMAIN ); ?>" />
					</label>
					<label class="launchdek-filter-date">
						<span class="screen-reader-text"><?php esc_html_e( 'To date', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
						<input type="date" id="launchdek-audit-date-to" aria-label="<?php esc_attr_e( 'To date', LAUNCHDEK_TEXT_DOMAIN ); ?>" />
					</label>
					<select id="launchdek-audit-user" class="launchdek-select" aria-label="<?php esc_attr_e( 'User', LAUNCHDEK_TEXT_DOMAIN ); ?>">
						<option value=""><?php esc_html_e( 'All users', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
					</select>
					<input type="search" id="launchdek-audit-search" class="regular-text" placeholder="<?php esc_attr_e( 'Search details…', LAUNCHDEK_TEXT_DOMAIN ); ?>" />
					<button type="button" class="button" id="launchdek-audit-filter"><?php esc_html_e( 'Apply Filters', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				</div>
				<table class="wp-list-table widefat fixed striped launchdek-audit-table" id="launchdek-audit-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Timestamp', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'User', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Target Site', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
							<th><?php esc_html_e( 'Action & Data Diffs', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
						</tr>
					</thead>
					<tbody></tbody>
				</table>
			</div>
		</div>

		<aside class="launchdek-automation-sidebar launchdek-card" aria-label="<?php esc_attr_e( 'Live step-by-step runner', LAUNCHDEK_TEXT_DOMAIN ); ?>">
			<h2><?php esc_html_e( 'Live Step-by-Step Runner', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<div id="launchdek-run-info" class="launchdek-muted"><?php esc_html_e( 'Start a run to track progress in real time.', LAUNCHDEK_TEXT_DOMAIN ); ?></div>
			<ul id="launchdek-run-steps" class="launchdek-run-steps"></ul>
		</aside>
	</div>

	<div class="launchdek-card">
		<h2><?php esc_html_e( 'Automated Drift & State Verifier', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
		<p><?php esc_html_e( 'Background REST checks monitor permalink structure, search visibility, and environment type on registered sites.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
		<div id="launchdek-drift-status" class="launchdek-drift-status launchdek-muted" role="status" aria-live="polite">
			<?php esc_html_e( 'Loading drift monitor status…', LAUNCHDEK_TEXT_DOMAIN ); ?>
		</div>
		<div class="launchdek-inline-form">
			<select id="launchdek-drift-site" class="launchdek-select">
				<option value=""><?php esc_html_e( 'All sites', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
			</select>
			<button type="button" class="button button-primary" id="launchdek-verify-drift"><?php esc_html_e( 'Run Verification Now', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
		</div>
		<div id="launchdek-drift-results" class="launchdek-drift-results"></div>
	</div>
</div>
