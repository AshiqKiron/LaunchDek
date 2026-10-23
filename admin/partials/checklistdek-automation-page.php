<?php
/**
 * Batch Run admin page (checklist execution, batch queue, drift monitor).
 *
 * @package ChecklistDek
 *
 * @var array  $settings Plugin settings.
 * @var string $page     Page identifier.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Admin view partial; variables are template-scoped.

$activity_logs_url = admin_url( 'admin.php?page=' . CHECKLISTDEK_Admin::PAGE_SLUG . '-activity-logs' );
?>
<div class="wrap checklistdek-admin" data-checklistdek-page="automation">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<nav class="checklistdek-automation-nav nav-tab-wrapper" role="tablist" aria-label="<?php esc_attr_e( 'Batch run sections', 'checklistdek' ); ?>">
		<button type="button" class="nav-tab nav-tab-active" id="checklistdek-tab-run" data-checklistdek-automation-tab="run" role="tab" aria-selected="true" aria-controls="checklistdek-panel-run"><?php esc_html_e( 'Run Checklist', 'checklistdek' ); ?></button>
		<button type="button" class="nav-tab" id="checklistdek-tab-batch" data-checklistdek-automation-tab="batch" role="tab" aria-selected="false" aria-controls="checklistdek-panel-batch"><?php esc_html_e( 'Batch Queue', 'checklistdek' ); ?></button>
		<button type="button" class="nav-tab" id="checklistdek-tab-drift" data-checklistdek-automation-tab="drift" role="tab" aria-selected="false" aria-controls="checklistdek-panel-drift"><?php esc_html_e( 'Drift Monitor', 'checklistdek' ); ?></button>
	</nav>

	<div id="checklistdek-panel-run" class="checklistdek-tab-panel" data-checklistdek-automation-panel="run" role="tabpanel" aria-labelledby="checklistdek-tab-run">
		<div class="checklistdek-card">
			<p class="checklistdek-muted"><?php esc_html_e( 'Choose a checklist and target site, then execute steps one at a time or run all automatic API steps.', 'checklistdek' ); ?></p>

			<div class="checklistdek-automation-wizard">
				<nav
					class="checklistdek-automation-wizard-nav"
					aria-label="<?php esc_attr_e( 'Run checklist steps', 'checklistdek' ); ?>"
				>
					<div
						id="checklistdek-automation-progress"
						class="checklistdek-automation-progress"
						role="progressbar"
						aria-valuemin="1"
						aria-valuemax="3"
						aria-valuenow="1"
						aria-label="<?php esc_attr_e( 'Run checklist progress', 'checklistdek' ); ?>"
					>
						<div class="checklistdek-automation-progress-segments">
							<div class="checklistdek-automation-progress-segment is-active" data-step="1">
								<div class="checklistdek-automation-progress-segment-bar" aria-hidden="true"></div>
								<div class="checklistdek-automation-progress-segment-text">
									<span class="checklistdek-automation-progress-segment-label"><?php esc_html_e( 'Step 1', 'checklistdek' ); ?></span>
									<span class="checklistdek-automation-progress-segment-hint"><?php esc_html_e( 'Choose checklist', 'checklistdek' ); ?></span>
								</div>
							</div>
							<div class="checklistdek-automation-progress-segment" data-step="2">
								<div class="checklistdek-automation-progress-segment-bar" aria-hidden="true"></div>
								<div class="checklistdek-automation-progress-segment-text">
									<span class="checklistdek-automation-progress-segment-label"><?php esc_html_e( 'Step 2', 'checklistdek' ); ?></span>
									<span class="checklistdek-automation-progress-segment-hint"><?php esc_html_e( 'Choose site', 'checklistdek' ); ?></span>
								</div>
							</div>
							<div class="checklistdek-automation-progress-segment" data-step="3">
								<div class="checklistdek-automation-progress-segment-bar" aria-hidden="true"></div>
								<div class="checklistdek-automation-progress-segment-text">
									<span class="checklistdek-automation-progress-segment-label"><?php esc_html_e( 'Step 3', 'checklistdek' ); ?></span>
									<span class="checklistdek-automation-progress-segment-hint"><?php esc_html_e( 'Execute steps', 'checklistdek' ); ?></span>
								</div>
							</div>
						</div>
					</div>
				</nav>

				<div class="checklistdek-automation-wizard-body">
			<div id="checklistdek-automation-step-1" class="checklistdek-automation-step-panel">
				<label for="checklistdek-run-checklist" class="checklistdek-automation-field-label"><?php esc_html_e( 'Checklist', 'checklistdek' ); ?></label>
				<select id="checklistdek-run-checklist" class="checklistdek-select" aria-label="<?php esc_attr_e( 'Checklist', 'checklistdek' ); ?>">
					<option value=""><?php esc_html_e( 'Select checklist…', 'checklistdek' ); ?></option>
				</select>
				<div class="checklistdek-automation-step-footer">
					<div class="checklistdek-automation-step-footer-actions">
						<button type="button" class="button button-primary" id="checklistdek-automation-step1-next" disabled><?php esc_html_e( 'Next', 'checklistdek' ); ?></button>
					</div>
				</div>
			</div>

			<div id="checklistdek-automation-step-2" class="checklistdek-automation-step-panel" hidden>
				<div class="checklistdek-target-sites">
					<span class="checklistdek-multicheck-label" id="checklistdek-run-site-label"><?php esc_html_e( 'Target site', 'checklistdek' ); ?></span>
					<div id="checklistdek-run-site-picker" class="checklistdek-multicheck-dropdown checklistdek-target-sites-picker" aria-labelledby="checklistdek-run-site-label"></div>
				</div>
				<div class="checklistdek-automation-step-footer">
					<div class="checklistdek-automation-step-footer-actions">
						<button type="button" class="button" id="checklistdek-automation-step2-back"><?php esc_html_e( 'Back', 'checklistdek' ); ?></button>
						<button type="button" class="button button-primary" id="checklistdek-start-run" disabled><?php esc_html_e( 'Start Run', 'checklistdek' ); ?></button>
					</div>
				</div>
			</div>

			<div id="checklistdek-automation-step-3" class="checklistdek-automation-step-panel" hidden>
				<div class="checklistdek-automation-run-hero">
					<div class="checklistdek-automation-run-hero-header">
						<button type="button" class="button button-link" id="checklistdek-automation-back-to-setup"><?php esc_html_e( 'Back to setup', 'checklistdek' ); ?></button>
					</div>
					<div id="checklistdek-run-info" class="checklistdek-muted"><?php esc_html_e( 'Complete steps 1–2 to start a run, or open an existing run from Sites.', 'checklistdek' ); ?></div>
					<div id="checklistdek-run-progress-wrap"></div>
					<ul id="checklistdek-run-steps" class="checklistdek-run-steps" aria-label="<?php esc_attr_e( 'Live step-by-step runner', 'checklistdek' ); ?>"></ul>
					<div class="checklistdek-run-controls checklistdek-inline-form">
						<button type="button" class="button" id="checklistdek-run-auto"><?php esc_html_e( 'Run Auto Steps', 'checklistdek' ); ?></button>
						<button type="button" class="button" id="checklistdek-run-next"><?php esc_html_e( 'Execute Next Step', 'checklistdek' ); ?></button>
						<button type="button" class="button" id="checklistdek-run-push-client" hidden><?php esc_html_e( 'Refresh Client Panel', 'checklistdek' ); ?></button>
					</div>
					<div id="checklistdek-run-notice" class="checklistdek-notice-area" aria-live="polite"></div>
				</div>
			</div>
				</div>
			</div>
		</div>

		<div class="checklistdek-card checklistdek-automation-run-activity">
			<div class="checklistdek-card-heading-row">
				<h2><?php esc_html_e( 'Recent Activity', 'checklistdek' ); ?></h2>
				<a href="<?php echo esc_url( $activity_logs_url ); ?>" class="button button-link" id="checklistdek-run-activity-view-all"><?php esc_html_e( 'View all activity logs', 'checklistdek' ); ?></a>
			</div>
			<p class="checklistdek-muted checklistdek-automation-run-activity-intro" id="checklistdek-run-activity-intro">
				<?php esc_html_e( 'Start a run to see site activity here, or open the full activity log.', 'checklistdek' ); ?>
			</p>
			<div id="checklistdek-run-activity-feed" class="checklistdek-log-feed" hidden aria-live="polite"></div>
		</div>
	</div>

	<div id="checklistdek-panel-batch" class="checklistdek-tab-panel" data-checklistdek-automation-panel="batch" role="tabpanel" aria-labelledby="checklistdek-tab-batch" hidden>
		<div class="checklistdek-card">
			<h2><?php esc_html_e( 'Batch Queue', 'checklistdek' ); ?></h2>
			<p class="checklistdek-muted"><?php esc_html_e( 'Use Batch Queue when running the same checklist on multiple sites at once.', 'checklistdek' ); ?></p>

			<div class="checklistdek-target-selector">
				<div class="checklistdek-target-form">
					<div class="checklistdek-target-form-controls checklistdek-inline-form">
						<select id="checklistdek-batch-checklist" class="checklistdek-select" aria-label="<?php esc_attr_e( 'Checklist', 'checklistdek' ); ?>">
							<option value=""><?php esc_html_e( 'Select checklist…', 'checklistdek' ); ?></option>
						</select>
						<div class="checklistdek-target-actions">
							<button type="button" class="button" id="checklistdek-queue-add"><?php esc_html_e( 'Add to Batch Queue', 'checklistdek' ); ?></button>
							<button type="button" class="button button-primary" id="checklistdek-queue-process"><?php esc_html_e( 'Process Queue', 'checklistdek' ); ?></button>
							<button type="button" class="button" id="checklistdek-queue-clear"><?php esc_html_e( 'Clear Queue', 'checklistdek' ); ?></button>
						</div>
					</div>
					<div class="checklistdek-target-sites">
						<span class="checklistdek-multicheck-label" id="checklistdek-batch-site-label"><?php esc_html_e( 'Target sites', 'checklistdek' ); ?></span>
						<div id="checklistdek-batch-site-picker" class="checklistdek-multicheck-dropdown checklistdek-target-sites-picker" aria-labelledby="checklistdek-batch-site-label"></div>
					</div>
				</div>

				<div class="checklistdek-batch-queue">
					<div class="checklistdek-table-scroll">
					<table class="wp-list-table widefat fixed striped" id="checklistdek-batch-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Site', 'checklistdek' ); ?></th>
								<th><?php esc_html_e( 'Checklist', 'checklistdek' ); ?></th>
								<th><?php esc_html_e( 'Status', 'checklistdek' ); ?></th>
								<th><?php esc_html_e( 'Run', 'checklistdek' ); ?></th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							<tr class="checklistdek-batch-empty">
								<td colspan="5" class="checklistdek-muted"><?php esc_html_e( 'No queued runs. Select sites and add to the batch queue.', 'checklistdek' ); ?></td>
							</tr>
						</tbody>
					</table>
					</div>
				</div>
				<div id="checklistdek-batch-notice" class="checklistdek-notice-area" aria-live="polite"></div>
			</div>
		</div>
	</div>

	<div id="checklistdek-panel-drift" class="checklistdek-tab-panel" data-checklistdek-automation-panel="drift" role="tabpanel" aria-labelledby="checklistdek-tab-drift" hidden>
		<div class="checklistdek-card">
			<h2><?php esc_html_e( 'Catch unexpected site changes', 'checklistdek' ); ?></h2>
			<p><?php esc_html_e( 'ChecklistDek watches your connected sites for changes to permalinks, search engine visibility, and environment labels (production, staging, and so on). The first check saves a baseline; later scans alert you when something drifts.', 'checklistdek' ); ?></p>
			<div id="checklistdek-drift-status" class="checklistdek-drift-status checklistdek-muted" role="status" aria-live="polite">
				<?php esc_html_e( 'Loading drift monitor status…', 'checklistdek' ); ?>
			</div>
			<div class="checklistdek-inline-form">
				<select id="checklistdek-drift-site" class="checklistdek-select">
					<option value=""><?php esc_html_e( 'All sites', 'checklistdek' ); ?></option>
				</select>
				<button type="button" class="button button-primary" id="checklistdek-verify-drift"><?php esc_html_e( 'Run Verification Now', 'checklistdek' ); ?></button>
			</div>
			<div id="checklistdek-drift-results" class="checklistdek-drift-results"></div>
		</div>
	</div>
</div>
