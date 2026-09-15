<?php
/**
 * Batch Run admin page (checklist execution, batch queue, drift monitor).
 *
 * @package LaunchDek
 *
 * @var array  $settings Plugin settings.
 * @var string $page     Page identifier.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$activity_logs_url = admin_url( 'admin.php?page=' . LAUNCHDEK_Admin::PAGE_SLUG . '-activity-logs' );
?>
<div class="wrap launchdek-admin" data-launchdek-page="automation">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<nav class="launchdek-automation-nav nav-tab-wrapper" role="tablist" aria-label="<?php esc_attr_e( 'Batch run sections', LAUNCHDEK_TEXT_DOMAIN ); ?>">
		<button type="button" class="nav-tab nav-tab-active" id="launchdek-tab-run" data-launchdek-automation-tab="run" role="tab" aria-selected="true" aria-controls="launchdek-panel-run"><?php esc_html_e( 'Run Checklist', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
		<button type="button" class="nav-tab" id="launchdek-tab-batch" data-launchdek-automation-tab="batch" role="tab" aria-selected="false" aria-controls="launchdek-panel-batch"><?php esc_html_e( 'Batch Queue', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
		<button type="button" class="nav-tab" id="launchdek-tab-drift" data-launchdek-automation-tab="drift" role="tab" aria-selected="false" aria-controls="launchdek-panel-drift"><?php esc_html_e( 'Drift Monitor', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
	</nav>

	<div id="launchdek-panel-run" class="launchdek-tab-panel" data-launchdek-automation-panel="run" role="tabpanel" aria-labelledby="launchdek-tab-run">
		<div class="launchdek-card">
			<p class="launchdek-muted"><?php esc_html_e( 'Choose a checklist and target site, then execute steps one at a time or run all automatic API steps.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>

			<div class="launchdek-automation-wizard">
				<nav
					class="launchdek-automation-wizard-nav"
					aria-label="<?php esc_attr_e( 'Run checklist steps', LAUNCHDEK_TEXT_DOMAIN ); ?>"
				>
					<div
						id="launchdek-automation-progress"
						class="launchdek-automation-progress"
						role="progressbar"
						aria-valuemin="1"
						aria-valuemax="3"
						aria-valuenow="1"
						aria-label="<?php esc_attr_e( 'Run checklist progress', LAUNCHDEK_TEXT_DOMAIN ); ?>"
					>
						<div class="launchdek-automation-progress-segments">
							<div class="launchdek-automation-progress-segment is-active" data-step="1">
								<div class="launchdek-automation-progress-segment-bar" aria-hidden="true"></div>
								<div class="launchdek-automation-progress-segment-text">
									<span class="launchdek-automation-progress-segment-label"><?php esc_html_e( 'Step 1', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
									<span class="launchdek-automation-progress-segment-hint"><?php esc_html_e( 'Choose checklist', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
								</div>
							</div>
							<div class="launchdek-automation-progress-segment" data-step="2">
								<div class="launchdek-automation-progress-segment-bar" aria-hidden="true"></div>
								<div class="launchdek-automation-progress-segment-text">
									<span class="launchdek-automation-progress-segment-label"><?php esc_html_e( 'Step 2', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
									<span class="launchdek-automation-progress-segment-hint"><?php esc_html_e( 'Choose site', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
								</div>
							</div>
							<div class="launchdek-automation-progress-segment" data-step="3">
								<div class="launchdek-automation-progress-segment-bar" aria-hidden="true"></div>
								<div class="launchdek-automation-progress-segment-text">
									<span class="launchdek-automation-progress-segment-label"><?php esc_html_e( 'Step 3', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
									<span class="launchdek-automation-progress-segment-hint"><?php esc_html_e( 'Execute steps', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
								</div>
							</div>
						</div>
					</div>
				</nav>

				<div class="launchdek-automation-wizard-body">
			<div id="launchdek-automation-step-1" class="launchdek-automation-step-panel">
				<label for="launchdek-run-checklist" class="launchdek-automation-field-label"><?php esc_html_e( 'Checklist', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
				<select id="launchdek-run-checklist" class="launchdek-select" aria-label="<?php esc_attr_e( 'Checklist', LAUNCHDEK_TEXT_DOMAIN ); ?>">
					<option value=""><?php esc_html_e( 'Select checklist…', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
				</select>
				<div class="launchdek-automation-step-footer">
					<div class="launchdek-automation-step-footer-actions">
						<button type="button" class="button button-primary" id="launchdek-automation-step1-next" disabled><?php esc_html_e( 'Next', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
					</div>
				</div>
			</div>

			<div id="launchdek-automation-step-2" class="launchdek-automation-step-panel" hidden>
				<div class="launchdek-target-sites">
					<span class="launchdek-multicheck-label" id="launchdek-run-site-label"><?php esc_html_e( 'Target site', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
					<div id="launchdek-run-site-picker" class="launchdek-multicheck-dropdown launchdek-target-sites-picker" aria-labelledby="launchdek-run-site-label"></div>
				</div>
				<div class="launchdek-automation-step-footer">
					<div class="launchdek-automation-step-footer-actions">
						<button type="button" class="button" id="launchdek-automation-step2-back"><?php esc_html_e( 'Back', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
						<button type="button" class="button button-primary" id="launchdek-start-run" disabled><?php esc_html_e( 'Start Run', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
					</div>
				</div>
			</div>

			<div id="launchdek-automation-step-3" class="launchdek-automation-step-panel" hidden>
				<div class="launchdek-automation-run-hero">
					<div class="launchdek-automation-run-hero-header">
						<button type="button" class="button button-link" id="launchdek-automation-back-to-setup"><?php esc_html_e( 'Back to setup', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
					</div>
					<div id="launchdek-run-info" class="launchdek-muted"><?php esc_html_e( 'Complete steps 1–2 to start a run, or open an existing run from Sites.', LAUNCHDEK_TEXT_DOMAIN ); ?></div>
					<div id="launchdek-run-progress-wrap"></div>
					<ul id="launchdek-run-steps" class="launchdek-run-steps" aria-label="<?php esc_attr_e( 'Live step-by-step runner', LAUNCHDEK_TEXT_DOMAIN ); ?>"></ul>
					<div class="launchdek-run-controls launchdek-inline-form">
						<button type="button" class="button" id="launchdek-run-auto"><?php esc_html_e( 'Run Auto Steps', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
						<button type="button" class="button" id="launchdek-run-next"><?php esc_html_e( 'Execute Next Step', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
						<button type="button" class="button" id="launchdek-run-push-client" hidden><?php esc_html_e( 'Refresh Client Panel', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
					</div>
					<div id="launchdek-run-notice" class="launchdek-notice-area" aria-live="polite"></div>
				</div>
			</div>
				</div>
			</div>
		</div>

		<div class="launchdek-card launchdek-automation-run-activity">
			<div class="launchdek-card-heading-row">
				<h2><?php esc_html_e( 'Recent Activity', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
				<a href="<?php echo esc_url( $activity_logs_url ); ?>" class="button button-link" id="launchdek-run-activity-view-all"><?php esc_html_e( 'View all activity logs', LAUNCHDEK_TEXT_DOMAIN ); ?></a>
			</div>
			<p class="launchdek-muted launchdek-automation-run-activity-intro" id="launchdek-run-activity-intro">
				<?php esc_html_e( 'Start a run to see site activity here, or open the full activity log.', LAUNCHDEK_TEXT_DOMAIN ); ?>
			</p>
			<div id="launchdek-run-activity-feed" class="launchdek-log-feed" hidden aria-live="polite"></div>
		</div>
	</div>

	<div id="launchdek-panel-batch" class="launchdek-tab-panel" data-launchdek-automation-panel="batch" role="tabpanel" aria-labelledby="launchdek-tab-batch" hidden>
		<div class="launchdek-card">
			<h2><?php esc_html_e( 'Batch Queue', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<p class="launchdek-muted"><?php esc_html_e( 'Use Batch Queue when running the same checklist on multiple sites at once.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>

			<div class="launchdek-target-selector">
				<div class="launchdek-target-form">
					<div class="launchdek-target-form-controls launchdek-inline-form">
						<select id="launchdek-batch-checklist" class="launchdek-select" aria-label="<?php esc_attr_e( 'Checklist', LAUNCHDEK_TEXT_DOMAIN ); ?>">
							<option value=""><?php esc_html_e( 'Select checklist…', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
						</select>
						<div class="launchdek-target-actions">
							<button type="button" class="button" id="launchdek-queue-add"><?php esc_html_e( 'Add to Batch Queue', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
							<button type="button" class="button button-primary" id="launchdek-queue-process"><?php esc_html_e( 'Process Queue', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
							<button type="button" class="button" id="launchdek-queue-clear"><?php esc_html_e( 'Clear Queue', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
						</div>
					</div>
					<div class="launchdek-target-sites">
						<span class="launchdek-multicheck-label" id="launchdek-batch-site-label"><?php esc_html_e( 'Target sites', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
						<div id="launchdek-batch-site-picker" class="launchdek-multicheck-dropdown launchdek-target-sites-picker" aria-labelledby="launchdek-batch-site-label"></div>
					</div>
				</div>

				<div class="launchdek-batch-queue">
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
				<div id="launchdek-batch-notice" class="launchdek-notice-area" aria-live="polite"></div>
			</div>
		</div>
	</div>

	<div id="launchdek-panel-drift" class="launchdek-tab-panel" data-launchdek-automation-panel="drift" role="tabpanel" aria-labelledby="launchdek-tab-drift" hidden>
		<div class="launchdek-card">
			<h2><?php esc_html_e( 'Catch unexpected site changes', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<p><?php esc_html_e( 'LaunchDek watches your connected sites for changes to permalinks, search engine visibility, and environment labels (production, staging, and so on). The first check saves a baseline; later scans alert you when something drifts.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
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
</div>
