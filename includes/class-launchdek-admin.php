<?php
/**
 * Admin area — menus, settings pages, asset enqueuing.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin handler.
 */
class LAUNCHDEK_Admin {

	const PAGE_SLUG = LAUNCHDEK_PLUGIN_SLUG;

	/**
	 * Register the admin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		$cap = LAUNCHDEK_Capabilities::admin_menu_capability();

		add_menu_page(
			__( 'LaunchDek', LAUNCHDEK_TEXT_DOMAIN ),
			__( 'LaunchDek', LAUNCHDEK_TEXT_DOMAIN ),
			$cap,
			self::PAGE_SLUG,
			array( $this, 'render_admin_page' ),
			'dashicons-networking',
			73
		);

		$pages = array(
			self::PAGE_SLUG              => array( __( 'LaunchDek Overview', LAUNCHDEK_TEXT_DOMAIN ), 'render_admin_page', __( 'Dashboard', LAUNCHDEK_TEXT_DOMAIN ) ),
			self::PAGE_SLUG . '-sites'   => array( __( 'Sites', LAUNCHDEK_TEXT_DOMAIN ), 'render_sites_page' ),
			self::PAGE_SLUG . '-checklists' => array( __( 'Checklists', LAUNCHDEK_TEXT_DOMAIN ), 'render_checklists_page' ),
			self::PAGE_SLUG . '-automation' => array( __( 'Batch Run', LAUNCHDEK_TEXT_DOMAIN ), 'render_automation_page' ),
			self::PAGE_SLUG . '-integrations' => array( __( 'Integrations', LAUNCHDEK_TEXT_DOMAIN ), 'render_integrations_page' ),
			self::PAGE_SLUG . '-settings' => array( __( 'Settings', LAUNCHDEK_TEXT_DOMAIN ), 'render_settings_page' ),
		);

		foreach ( $pages as $slug => $page ) {
			add_submenu_page(
				self::PAGE_SLUG,
				$page[0],
				isset( $page[2] ) ? $page[2] : $page[0],
				$cap,
				$slug,
				array( $this, $page[1] )
			);
		}

		// Hidden page — null parent keeps it out of the menu while allowing direct URL access.
		$activity_logs_hook = add_submenu_page(
			null,
			__( 'Activity Logs', LAUNCHDEK_TEXT_DOMAIN ),
			__( 'Activity Logs', LAUNCHDEK_TEXT_DOMAIN ),
			$cap,
			self::PAGE_SLUG . '-activity-logs',
			array( $this, 'render_activity_logs_page' )
		);
		if ( $activity_logs_hook ) {
			add_action( 'load-' . $activity_logs_hook, array( $this, 'prepare_activity_logs_page' ) );
		}
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			LAUNCHDEK_Settings::SETTINGS_GROUP,
			LAUNCHDEK_Settings::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'LAUNCHDEK_Settings', 'sanitize' ),
				'default'           => LAUNCHDEK_Settings::get_defaults(),
			)
		);
	}

	/**
	 * Redirect legacy Templates submenu URL to Checklists → Templates tab.
	 *
	 * @return void
	 */
	public function maybe_redirect_legacy_templates_page() {
		if ( ! is_admin() || ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( self::PAGE_SLUG . '-templates' === $page ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-checklists' ) );
			exit;
		}

		if ( self::PAGE_SLUG . '-sites' === $page && ! empty( $_GET['activity_logs'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-activity-logs' ) );
			exit;
		}
	}

	public function maybe_activation_redirect() {
		if ( ! get_transient( 'launchdek_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'launchdek_activation_redirect' );
		if ( wp_doing_ajax() || is_network_admin() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&onboarding=1' ) );
		exit;
	}

	public function enqueue_styles( $hook ) {
		if ( ! $this->is_plugin_screen( $hook ) ) {
			return;
		}
		$path = LAUNCHDEK_PLUGIN_DIR . 'admin/css/launchdek-admin.css';
		wp_enqueue_style( 'launchdek-admin', LAUNCHDEK_PLUGIN_URL . 'admin/css/launchdek-admin.css', array(), file_exists( $path ) ? (string) filemtime( $path ) : LAUNCHDEK_VERSION );

		if ( self::PAGE_SLUG . '_page_' . self::PAGE_SLUG . '-checklists' === $hook ) {
			$client_css_path = LAUNCHDEK_PLUGIN_DIR . 'mu-plugin/launchdek-client/css/launchdek-client-admin.css';
			wp_enqueue_style(
				'launchdek-client-admin-preview',
				LAUNCHDEK_PLUGIN_URL . 'mu-plugin/launchdek-client/css/launchdek-client-admin.css',
				array( 'launchdek-admin' ),
				file_exists( $client_css_path ) ? (string) filemtime( $client_css_path ) : LAUNCHDEK_VERSION
			);
		}
	}

	public function enqueue_scripts( $hook ) {
		if ( ! $this->is_plugin_screen( $hook ) ) {
			return;
		}
		$path = LAUNCHDEK_PLUGIN_DIR . 'admin/js/launchdek-admin.js';
		wp_enqueue_script( 'launchdek-admin', LAUNCHDEK_PLUGIN_URL . 'admin/js/launchdek-admin.js', array(), file_exists( $path ) ? (string) filemtime( $path ) : LAUNCHDEK_VERSION, true );
		$wp_roles = array();
		if ( function_exists( 'wp_roles' ) && wp_roles() ) {
			foreach ( wp_roles()->get_names() as $role_slug => $role_name ) {
				$wp_roles[ $role_slug ] = translate_user_role( $role_name );
			}
		}

		$localize = array(
				'restUrl'   => esc_url_raw( rest_url( LAUNCHDEK_REST_NAMESPACE ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'adminUrl'  => admin_url( 'admin.php' ),
				'pageSlug'  => self::PAGE_SLUG,
				'roles'     => $wp_roles,
				'onboarding' => array(
					'show'           => empty( LAUNCHDEK_Settings::get()['onboarding_dismissed'] ),
					'templatesUrl'   => admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-checklists' ),
					'checklistsUrl'  => admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-checklists&tab=my-checklists' ),
					'automationUrl'  => admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-automation' ),
					'activityLogsUrl' => admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-activity-logs' ),
				),
				'strings'   => array(
					'confirmDelete'  => __( 'Are you sure you want to delete this?', LAUNCHDEK_TEXT_DOMAIN ),
					'confirmDeleteSite' => __( 'Are you sure you want to delete this site?', LAUNCHDEK_TEXT_DOMAIN ),
					'saved'          => __( 'Saved successfully.', LAUNCHDEK_TEXT_DOMAIN ),
					'error'          => __( 'Something went wrong.', LAUNCHDEK_TEXT_DOMAIN ),
					'loading'        => __( 'Loading…', LAUNCHDEK_TEXT_DOMAIN ),
					'noSites'        => __( 'No sites registered yet.', LAUNCHDEK_TEXT_DOMAIN ),
					'noSitesFiltered' => __( 'No sites match the current filters. Try clearing tag or group filters.', LAUNCHDEK_TEXT_DOMAIN ),
					'restUnavailable' => __( 'REST API is unavailable. On the hub site, go to Settings → Permalinks, choose Post name, and save.', LAUNCHDEK_TEXT_DOMAIN ),
					'healthOk'       => __( 'OK', LAUNCHDEK_TEXT_DOMAIN ),
					'healthFail'     => __( 'Fail', LAUNCHDEK_TEXT_DOMAIN ),
					'healthUnknown'  => __( 'Unknown', LAUNCHDEK_TEXT_DOMAIN ),
					'connectionOk'   => __( 'Connection OK', LAUNCHDEK_TEXT_DOMAIN ),
					'connectionFail' => __( 'Connection failed', LAUNCHDEK_TEXT_DOMAIN ),
					'connectionDisconnected' => __( 'Connection not working', LAUNCHDEK_TEXT_DOMAIN ),
					'connectionChecking' => __( 'Checking connection…', LAUNCHDEK_TEXT_DOMAIN ),
					'connectionAllStatuses' => __( 'All Sites', LAUNCHDEK_TEXT_DOMAIN ),
					'connectionSummaryHealthy' => __( 'Healthy', LAUNCHDEK_TEXT_DOMAIN ),
					'connectionSummaryUnhealthy' => __( 'Issues', LAUNCHDEK_TEXT_DOMAIN ),
					'connectionSummaryUnknown' => __( 'Unknown', LAUNCHDEK_TEXT_DOMAIN ),
					'editSite'       => __( 'Edit', LAUNCHDEK_TEXT_DOMAIN ),
					'testSite'       => __( 'Test', LAUNCHDEK_TEXT_DOMAIN ),
					'siteActionsMenu' => __( 'More site actions', LAUNCHDEK_TEXT_DOMAIN ),
					'pushChecklist'  => __( 'Push Checklist', LAUNCHDEK_TEXT_DOMAIN ),
					'pushChecklistSelect' => __( 'Select checklist…', LAUNCHDEK_TEXT_DOMAIN ),
					'pushChecklistNeed' => __( 'Select a checklist to push.', LAUNCHDEK_TEXT_DOMAIN ),
					'pushChecklistStarted' => __( 'Checklist run started.', LAUNCHDEK_TEXT_DOMAIN ),
					'pushChecklistBlocked' => __( 'Fix the connection before pushing a checklist.', LAUNCHDEK_TEXT_DOMAIN ),
					'siteChecklistHistory' => __( 'Checklist history', LAUNCHDEK_TEXT_DOMAIN ),
					'siteChecklistHistoryShow' => __( 'Show checklist history', LAUNCHDEK_TEXT_DOMAIN ),
					'siteChecklistHistoryHide' => __( 'Hide checklist history', LAUNCHDEK_TEXT_DOMAIN ),
					'siteNoChecklistRuns' => __( 'No checklist runs recorded for this site yet.', LAUNCHDEK_TEXT_DOMAIN ),
					'siteRunsLoadMore'   => __( 'Load more', LAUNCHDEK_TEXT_DOMAIN ),
					'siteRunsLoadingMore' => __( 'Loading more…', LAUNCHDEK_TEXT_DOMAIN ),
					'runChecklist'       => __( 'Checklist', LAUNCHDEK_TEXT_DOMAIN ),
					'runSteps'           => __( 'Steps', LAUNCHDEK_TEXT_DOMAIN ),
					'runStepsProgress'   => __( '%1$d of %2$d steps completed', LAUNCHDEK_TEXT_DOMAIN ),
					'runStatus'          => __( 'Status', LAUNCHDEK_TEXT_DOMAIN ),
					'runStatusCompleted' => __( 'Completed', LAUNCHDEK_TEXT_DOMAIN ),
					'runStatusRunning'   => __( 'Running', LAUNCHDEK_TEXT_DOMAIN ),
					'runStatusFailed'    => __( 'Failed', LAUNCHDEK_TEXT_DOMAIN ),
					'runStatusCancelled' => __( 'Cancelled', LAUNCHDEK_TEXT_DOMAIN ),
					'runStartedAt'       => __( 'Started', LAUNCHDEK_TEXT_DOMAIN ),
					'runCompletedAt'     => __( 'Completed', LAUNCHDEK_TEXT_DOMAIN ),
					'runStartedBy'       => __( 'By', LAUNCHDEK_TEXT_DOMAIN ),
					'viewRun'            => __( 'View run', LAUNCHDEK_TEXT_DOMAIN ),
					'runActionsMenu'     => __( 'More run actions', LAUNCHDEK_TEXT_DOMAIN ),
					'duplicate'          => __( 'Duplicate', LAUNCHDEK_TEXT_DOMAIN ),
					'export'             => __( 'Export', LAUNCHDEK_TEXT_DOMAIN ),
					'archive'            => __( 'Archive', LAUNCHDEK_TEXT_DOMAIN ),
					'delete'             => __( 'Delete', LAUNCHDEK_TEXT_DOMAIN ),
					'confirmArchiveRun'  => __( 'Archive this run? It will be hidden from checklist history.', LAUNCHDEK_TEXT_DOMAIN ),
					'confirmDeleteRun'   => __( 'Delete this checklist run permanently?', LAUNCHDEK_TEXT_DOMAIN ),
					'confirmActionTitle' => __( 'Confirm action', LAUNCHDEK_TEXT_DOMAIN ),
					'confirm'            => __( 'Confirm', LAUNCHDEK_TEXT_DOMAIN ),
					'deletePermanently'  => __( 'Delete permanently', LAUNCHDEK_TEXT_DOMAIN ),
					'runArchived'        => __( 'Run archived.', LAUNCHDEK_TEXT_DOMAIN ),
					'runDeleted'         => __( 'Run deleted.', LAUNCHDEK_TEXT_DOMAIN ),
					'templateEditBlocked' => __( 'Built-in templates cannot be edited. Duplicate instead.', LAUNCHDEK_TEXT_DOMAIN ),
					'openRunner'     => __( 'Open runner →', LAUNCHDEK_TEXT_DOMAIN ),
					'clientPanelBadge' => __( 'Client panel', LAUNCHDEK_TEXT_DOMAIN ),
					'clientPushOk'   => __( 'Checklist pushed to client admin panel.', LAUNCHDEK_TEXT_DOMAIN ),
					'clientPushSkipped' => __( 'Run started on the hub. Complete the one-time client panel setup on Sites to show the checklist on the client site.', LAUNCHDEK_TEXT_DOMAIN ),
					'clientPushFailed' => __( 'Run started, but the client panel could not be updated.', LAUNCHDEK_TEXT_DOMAIN ),
					'panelSetupTitle' => __( 'Client checklist panel setup', LAUNCHDEK_TEXT_DOMAIN ),
					'panelSetupNeeded' => __( 'Client panel is not installed yet. Download the bootstrap file, upload it to the client site, then retry panel install.', LAUNCHDEK_TEXT_DOMAIN ),
					'panelSetupReady' => __( 'Client panel is installed and ready.', LAUNCHDEK_TEXT_DOMAIN ),
					'panelInstallOk' => __( 'Client panel installed successfully.', LAUNCHDEK_TEXT_DOMAIN ),
					'panelInstallFailed' => __( 'Client panel install failed.', LAUNCHDEK_TEXT_DOMAIN ),
					'panelRetryNeedsSave' => __( 'Save this site first, then retry panel install.', LAUNCHDEK_TEXT_DOMAIN ),
					'panelBootstrapDownloaded' => __( 'Bootstrap file downloaded. Upload it to wp-content/mu-plugins/ on the client site, then click Retry panel install.', LAUNCHDEK_TEXT_DOMAIN ),
					'useChecklist'    => __( 'Use Checklist', LAUNCHDEK_TEXT_DOMAIN ),
					'checklistCreated' => __( 'Checklist created.', LAUNCHDEK_TEXT_DOMAIN ),
					'checklistTitleRequired' => __( 'Checklist title is required.', LAUNCHDEK_TEXT_DOMAIN ),
					'noCustomChecklists' => __( 'No custom checklists yet. Use a template or Start Blank in My Checklists.', LAUNCHDEK_TEXT_DOMAIN ),
					'noCustomTemplates' => __( 'No custom checklists saved yet. Create one under Checklists.', LAUNCHDEK_TEXT_DOMAIN ),
					'editChecklist' => __( 'Edit Checklist', LAUNCHDEK_TEXT_DOMAIN ),
					'customChecklistBadge' => __( 'Custom', LAUNCHDEK_TEXT_DOMAIN ),
					'checklistDuplicated' => __( 'Checklist duplicated.', LAUNCHDEK_TEXT_DOMAIN ),
					'confirmDeleteChecklist' => __( 'Are you sure you want to delete this checklist?', LAUNCHDEK_TEXT_DOMAIN ),
					'savedToVault'    => __( 'Saved to vault.', LAUNCHDEK_TEXT_DOMAIN ),
					'noVaultTemplates' => __( 'No vault templates yet.', LAUNCHDEK_TEXT_DOMAIN ),
					'noCategoryTemplates' => __( 'No templates in this category yet.', LAUNCHDEK_TEXT_DOMAIN ),
					'chooseTemplate'  => __( 'Choose a Template', LAUNCHDEK_TEXT_DOMAIN ),
					'startBlankInstead' => __( 'Start blank instead', LAUNCHDEK_TEXT_DOMAIN ),
					'importTemplate'  => __( 'Import Template', LAUNCHDEK_TEXT_DOMAIN ),
					'cancel'          => __( 'Cancel', LAUNCHDEK_TEXT_DOMAIN ),
					'allCategories'   => __( 'All', LAUNCHDEK_TEXT_DOMAIN ),
					'templateImported' => __( 'Template imported successfully.', LAUNCHDEK_TEXT_DOMAIN ),
					'selectTemplateFirst' => __( 'Select a template to import.', LAUNCHDEK_TEXT_DOMAIN ),
					'templateStepsHeading' => __( 'Steps preview', LAUNCHDEK_TEXT_DOMAIN ),
					'templateStepsEmpty' => __( 'Select a template to preview its steps.', LAUNCHDEK_TEXT_DOMAIN ),
					'templateStepsNone' => __( 'This template has no steps yet.', LAUNCHDEK_TEXT_DOMAIN ),
					'stepCount'       => __( '%d step', LAUNCHDEK_TEXT_DOMAIN ),
					'stepsCount'      => __( '%d steps', LAUNCHDEK_TEXT_DOMAIN ),
					'viewSteps'       => __( 'View steps', LAUNCHDEK_TEXT_DOMAIN ),
					'hideSteps'       => __( 'Hide steps', LAUNCHDEK_TEXT_DOMAIN ),
					'checklistSteps'  => __( 'Checklist steps', LAUNCHDEK_TEXT_DOMAIN ),
					'stepSettings'    => __( 'Step settings', LAUNCHDEK_TEXT_DOMAIN ),
					'roleTargetMapping' => __( 'Role Target Mapping', LAUNCHDEK_TEXT_DOMAIN ),
					'allRoles'        => __( 'All roles', LAUNCHDEK_TEXT_DOMAIN ),
					'rolesSelected'   => __( '%d roles selected', LAUNCHDEK_TEXT_DOMAIN ),
					'selectTargetSites' => __( 'Select sites…', LAUNCHDEK_TEXT_DOMAIN ),
					'targetSitesSelected' => __( '%d sites selected', LAUNCHDEK_TEXT_DOMAIN ),
					'deepLinkAuto'    => __( 'Auto-detected from step content.', LAUNCHDEK_TEXT_DOMAIN ),
					'deepLinkHelp'    => __( 'Optional wp-admin path (e.g. options-permalink.php). LaunchDek auto-fills this from the step title, instructions, or API route when possible.', LAUNCHDEK_TEXT_DOMAIN ),
					'stepTypeHelp'    => __( 'Choose how this step completes during a run. Manual steps wait for a person; API steps run automatically on the remote site.', LAUNCHDEK_TEXT_DOMAIN ),
					'stepTypeManualHelp' => __( 'Manual steps pause the run until someone marks them complete in the hub run tracker or the client checklist panel. Use for tasks that need human verification.', LAUNCHDEK_TEXT_DOMAIN ),
					'stepTypeApiHelp' => __( 'API steps run automatically when the checklist reaches this step. LaunchDek sends an authenticated REST request to the remote WordPress site using its saved Application Password—no client panel action required.', LAUNCHDEK_TEXT_DOMAIN ),
					'apiMapperHelp'   => __( 'Configure the REST call LaunchDek makes on the client site. Use Validate API Step to dry-run checks before saving. Fields listed under Settings → Exclude Options are stripped from /wp/v2/settings payloads.', LAUNCHDEK_TEXT_DOMAIN ),
					'apiMethodHelp'   => __( 'HTTP verb for the request. GET reads data without changes. POST, PUT, PATCH, and DELETE send the JSON payload to create, update, or remove resources.', LAUNCHDEK_TEXT_DOMAIN ),
					'apiRouteHelp'    => __( 'WordPress REST API path on the remote site. Must start with / (for example, /wp/v2/settings for site options).', LAUNCHDEK_TEXT_DOMAIN ),
					'apiPayloadHelp'  => __( 'Request body for mutating methods. For /wp/v2/settings, use WordPress setting field names (for example, {"title":"My Site"}). Use {} for GET requests.', LAUNCHDEK_TEXT_DOMAIN ),
					'roleTargetMappingHelp' => __( 'Limit which WordPress roles can complete this step on the client panel. Leave empty to allow all logged-in users.', LAUNCHDEK_TEXT_DOMAIN ),
					'showNoteField'   => __( 'Show note field on client panel', LAUNCHDEK_TEXT_DOMAIN ),
					'showNoteFieldHelp' => __( 'When enabled, clients can add text notes as evidence when completing this manual step.', LAUNCHDEK_TEXT_DOMAIN ),
					'showScreenshotField' => __( 'Allow screenshot attachment on client panel', LAUNCHDEK_TEXT_DOMAIN ),
					'showScreenshotFieldHelp' => __( 'When enabled, clients can attach a screenshot from the media library when saving a step note.', LAUNCHDEK_TEXT_DOMAIN ),
					'connected'       => __( 'Connected', LAUNCHDEK_TEXT_DOMAIN ),
					'notDetected'     => __( 'Not Detected', LAUNCHDEK_TEXT_DOMAIN ),
					'setup'           => __( 'Setup', LAUNCHDEK_TEXT_DOMAIN ),
					'integrationSyncSites' => __( 'Sync Sites', LAUNCHDEK_TEXT_DOMAIN ),
					'integrationPreviewSync' => __( 'Preview Sync', LAUNCHDEK_TEXT_DOMAIN ),
					'integrationPushPanel' => __( 'Push Client Panel', LAUNCHDEK_TEXT_DOMAIN ),
					'integrationSyncSummary' => __( 'Sync complete: %1$s created, %2$s updated, %3$s skipped. %4$s need credentials on the Sites page.', LAUNCHDEK_TEXT_DOMAIN ),
					'integrationPreviewSummary' => __( 'Preview: %1$s would be created, %2$s updated, %3$s skipped out of %4$s MainWP sites.', LAUNCHDEK_TEXT_DOMAIN ),
					'integrationPushSummary' => __( 'Client panel push finished: %1$s succeeded, %2$s failed, %3$s skipped.', LAUNCHDEK_TEXT_DOMAIN ),
					'integrationSyncedSites' => __( '%s synced sites', LAUNCHDEK_TEXT_DOMAIN ),
					'integrationSyncUnsupported' => __( 'Site sync is not available for this connector yet.', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingTitle' => __( 'LaunchDek Onboarding', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingProgressLabel' => __( 'Onboarding progress', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingStep1'         => __( 'Step 1', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingStep1Hint'     => __( 'Import your checklist from SOPs or docs', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingStep2'         => __( 'Step 2', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingStep2Hint'     => __( 'Connect a client site with App Passwords', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingIntro' => __( 'Turn your messy Notion SOPs or Google Docs into an active Checklist.', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingPasteLabel' => __( 'Paste your checklist text here:', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingPreviewLabel' => __( 'Preview', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingTestSite' => __( 'Test workflow immediately on:', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingSelectSite' => __( 'Select site…', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingUseTemplate' => __( 'Start from a template instead', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingSkip' => __( 'Skip', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingNextConnect' => __( 'Next: Connect', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingConnectIntro' => __( 'Connect your first remote client site using WordPress App Passwords.', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingBack' => __( 'Back', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingLaunch' => __( 'Finish & Launch', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingOutro' => __( 'Your imported checklist workflow will be ready to push instantly.', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingTestConnection' => __( 'Test Connection', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingStatusConnected' => __( 'Status: Connected & Verified (OK)', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingStatusFailed' => __( 'Status: Connection failed', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingStatusPending' => __( 'Status: Not tested yet', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingStatusTesting' => __( 'Status: Testing…', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingPreviewEmpty' => __( 'Start typing to see your checklist preview.', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingNeedSteps' => __( 'Add at least one checklist step before continuing.', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingNeedConnection' => __( 'Test the connection successfully before launching.', LAUNCHDEK_TEXT_DOMAIN ),
					'onboardingExistingSiteReady' => __( 'Using selected site:', LAUNCHDEK_TEXT_DOMAIN ),
					'captureSelectSite'   => __( 'Select site…', LAUNCHDEK_TEXT_DOMAIN ),
					'captureNeedPanel'    => __( 'Auto-capture requires the client checklist panel on at least one site.', LAUNCHDEK_TEXT_DOMAIN ),
					'captureIdle'         => __( 'Choose a client site and start recording to capture configuration changes.', LAUNCHDEK_TEXT_DOMAIN ),
					'captureRecording'    => __( 'Recording — configure the client site in wp-admin. Changes are captured automatically.', LAUNCHDEK_TEXT_DOMAIN ),
					'captureReady'        => __( 'Recording stopped. Review captured steps below.', LAUNCHDEK_TEXT_DOMAIN ),
					'captureImportOne'    => __( 'Import 1 Captured Step', LAUNCHDEK_TEXT_DOMAIN ),
					'captureImportMany'   => __( 'Import %d Captured Steps', LAUNCHDEK_TEXT_DOMAIN ),
					'captureImported'     => __( 'Captured steps imported into the checklist builder.', LAUNCHDEK_TEXT_DOMAIN ),
					'captureDefaultTitle' => __( 'Captured Checklist', LAUNCHDEK_TEXT_DOMAIN ),
					'automationNeedSiteChecklist' => __( 'Select at least one site and a checklist.', LAUNCHDEK_TEXT_DOMAIN ),
					'automationNeedOneSite'       => __( 'Select exactly one site to start a run.', LAUNCHDEK_TEXT_DOMAIN ),
					'automationBatchQueueEmpty'   => __( 'No queued items to process.', LAUNCHDEK_TEXT_DOMAIN ),
					'automationStartRunFirst'     => __( 'Start a run first.', LAUNCHDEK_TEXT_DOMAIN ),
					'automationBatchProcessed'    => __( 'Batch queue processed.', LAUNCHDEK_TEXT_DOMAIN ),
					'automationRunStarted'        => __( 'Run #%d started.', LAUNCHDEK_TEXT_DOMAIN ),
					'automationExecuteEmpty'      => __( 'Complete steps 1–2 to start a run, or open an existing run from Sites.', LAUNCHDEK_TEXT_DOMAIN ),
					'automationRunActivityIntro'  => __( 'Recent activity for %s.', LAUNCHDEK_TEXT_DOMAIN ),
					'automationRunActivityIdle'   => __( 'Start a run to see site activity here, or open the full activity log.', LAUNCHDEK_TEXT_DOMAIN ),
					'auditViewRawData'            => __( 'View raw data', LAUNCHDEK_TEXT_DOMAIN ),
					'auditNoDetails'              => __( 'No additional details.', LAUNCHDEK_TEXT_DOMAIN ),
					'auditEmpty'                  => __( 'No activity matches these filters.', LAUNCHDEK_TEXT_DOMAIN ),
					'activityLogsLoadMore'        => __( 'Load more activity', LAUNCHDEK_TEXT_DOMAIN ),
					'activityLogsLoadingMore'     => __( 'Loading more activity…', LAUNCHDEK_TEXT_DOMAIN ),
					'automationTabRun'            => __( 'Run Checklist', LAUNCHDEK_TEXT_DOMAIN ),
					'automationTabBatch'          => __( 'Batch Queue', LAUNCHDEK_TEXT_DOMAIN ),
					'automationTabDrift'          => __( 'Drift Monitor', LAUNCHDEK_TEXT_DOMAIN ),
					'automationStep1Label'        => __( 'Step 1', LAUNCHDEK_TEXT_DOMAIN ),
					'automationStep1Hint'         => __( 'Choose checklist', LAUNCHDEK_TEXT_DOMAIN ),
					'automationStep2Label'        => __( 'Step 2', LAUNCHDEK_TEXT_DOMAIN ),
					'automationStep2Hint'         => __( 'Choose site', LAUNCHDEK_TEXT_DOMAIN ),
					'automationStep3Label'        => __( 'Step 3', LAUNCHDEK_TEXT_DOMAIN ),
					'automationStep3Hint'         => __( 'Execute steps', LAUNCHDEK_TEXT_DOMAIN ),
					'automationNext'              => __( 'Next', LAUNCHDEK_TEXT_DOMAIN ),
					'automationBack'              => __( 'Back', LAUNCHDEK_TEXT_DOMAIN ),
					'automationStartRun'          => __( 'Start Run', LAUNCHDEK_TEXT_DOMAIN ),
					'automationBackToSetup'       => __( 'Back to setup', LAUNCHDEK_TEXT_DOMAIN ),
				),
		);

		if ( 'toplevel_page_' . self::PAGE_SLUG === $hook ) {
			$localize['dashboard'] = array(
				'stats'       => LAUNCHDEK_Dashboard_Cache::get_stats(),
				'connections' => LAUNCHDEK_Dashboard_Cache::get_connection_counts(),
				'feed'        => LAUNCHDEK_Dashboard_Cache::get_feed(),
				'quickLaunch' => LAUNCHDEK_Dashboard_Cache::get_quick_launch_picker(),
			);
		}

		if ( self::PAGE_SLUG . '_page_' . self::PAGE_SLUG . '-sites' === $hook ) {
			$localize['sites'] = array(
				'list' => LAUNCHDEK_Dashboard_Cache::get_sites_list(),
			);
		}

		$template_screens = array(
			'toplevel_page_' . self::PAGE_SLUG,
			self::PAGE_SLUG . '_page_' . self::PAGE_SLUG . '-checklists',
			self::PAGE_SLUG . '_page_' . self::PAGE_SLUG . '-settings',
		);
		if ( in_array( $hook, $template_screens, true ) ) {
			$localize['templates'] = array(
				'preloaded'  => true,
				'builtin'    => LAUNCHDEK_Templates::get_catalog(),
				'categories' => LAUNCHDEK_Templates::get_categories(),
			);
		}

		if ( self::PAGE_SLUG . '_page_' . self::PAGE_SLUG . '-activity-logs' === $hook ) {
			$localize['activityLogs'] = array(
				'preloaded' => true,
				'sites'     => LAUNCHDEK_Site_Repository::picker_list(),
			);
		}

		if ( self::PAGE_SLUG . '_page_' . self::PAGE_SLUG . '-checklists' === $hook ) {
			$localize['customChecklists'] = array(
				'preloaded' => true,
				'items'     => LAUNCHDEK_Checklist_Repository::summary_list(
					array(
						'is_template' => 0,
					)
				),
			);
			$localize['clientPreview'] = array(
				'panelTitle'        => LAUNCHDEK_Settings::get_client_panel_title(),
				'defaultPanelTitle' => LAUNCHDEK_Settings::get_default_client_panel_title(),
				'strings'    => array(
					'collapse'         => __( 'Collapse', LAUNCHDEK_TEXT_DOMAIN ),
					'stepOf'           => __( 'Step %1$s of %2$s', LAUNCHDEK_TEXT_DOMAIN ),
					'markComplete'     => __( 'Mark complete', LAUNCHDEK_TEXT_DOMAIN ),
					'goToSettings'     => __( 'Go to settings', LAUNCHDEK_TEXT_DOMAIN ),
					'toggleStep'       => __( 'Toggle step details', LAUNCHDEK_TEXT_DOMAIN ),
					'waiting'          => __( 'Waiting on agency', LAUNCHDEK_TEXT_DOMAIN ),
					'addNotes'         => __( 'Add notes', LAUNCHDEK_TEXT_DOMAIN ),
					'attachScreenshot' => __( 'Attach screenshot', LAUNCHDEK_TEXT_DOMAIN ),
					'notePlaceholder'  => __( 'Add a note about this step…', LAUNCHDEK_TEXT_DOMAIN ),
					'previewEmpty'     => __( 'Add a title and steps to preview the client panel.', LAUNCHDEK_TEXT_DOMAIN ),
				),
			);
		}

		wp_localize_script( 'launchdek-admin', 'launchdekAdmin', $localize );
	}

	public function add_settings_link( $links ) {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-settings' ) ), esc_html__( 'Settings', LAUNCHDEK_TEXT_DOMAIN ) ) );
		return $links;
	}

	public function add_plugin_row_meta( $links, $file ) {
		if ( LAUNCHDEK_PLUGIN_BASENAME !== $file ) {
			return $links;
		}
		$links[] = sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url( LAUNCHDEK_PLUGIN_DOCS_URL ), esc_html__( 'Docs', LAUNCHDEK_TEXT_DOMAIN ) );
		return $links;
	}

	public function render_admin_page() {
		$this->render_page(
			'launchdek-admin-page.php',
			array(
				'page'              => 'dashboard',
				'dashboard_stats'     => LAUNCHDEK_Dashboard_Cache::get_stats(),
				'connection_counts'   => LAUNCHDEK_Dashboard_Cache::get_connection_counts(),
				'log_feed'            => LAUNCHDEK_Dashboard_Cache::get_feed(),
				'quick_launch_picker' => LAUNCHDEK_Dashboard_Cache::get_quick_launch_picker(),
			)
		);
	}

	public function render_sites_page() {
		$this->render_page(
			'launchdek-sites-page.php',
			array(
				'page'       => 'sites',
				'sites_list' => LAUNCHDEK_Dashboard_Cache::get_sites_list(),
			)
		);
	}

	/**
	 * Set admin screen title before admin-header.php runs (hidden pages have no parent menu).
	 *
	 * @return void
	 */
	public function prepare_activity_logs_page() {
		global $title;

		$title = __( 'Activity Logs', LAUNCHDEK_TEXT_DOMAIN );
	}

	public function render_activity_logs_page() {
		$this->render_page( 'launchdek-activity-logs-page.php', array( 'page' => 'activity-logs' ) );
	}

	public function render_checklists_page() {
		$this->render_page( 'launchdek-checklists-page.php', array( 'page' => 'checklists' ) );
	}

	public function render_automation_page() {
		$this->render_page( 'launchdek-automation-page.php', array( 'page' => 'automation' ) );
	}

	public function render_integrations_page() {
		$this->render_page( 'launchdek-integrations-page.php', array( 'page' => 'integrations' ) );
	}

	public function render_settings_page() {
		if ( ! LAUNCHDEK_Capabilities::current_user_can( LAUNCHDEK_Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', LAUNCHDEK_TEXT_DOMAIN ) );
		}
		$settings = LAUNCHDEK_Settings::get();
		require LAUNCHDEK_PLUGIN_DIR . 'admin/partials/launchdek-settings-page.php';
	}

	protected function render_page( $partial, $vars = array() ) {
		if ( ! LAUNCHDEK_Capabilities::current_user_can( LAUNCHDEK_Capabilities::VIEW_DASHBOARD ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', LAUNCHDEK_TEXT_DOMAIN ) );
		}
		$settings = LAUNCHDEK_Settings::get();
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		require LAUNCHDEK_PLUGIN_DIR . 'admin/partials/' . $partial;
		require LAUNCHDEK_PLUGIN_DIR . 'admin/partials/launchdek-confirm-modal.php';
	}


	protected function is_plugin_screen( $hook ) {
		return false !== strpos( $hook, self::PAGE_SLUG );
	}
}
