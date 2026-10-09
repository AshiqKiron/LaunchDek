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

	/** @var string Option flag set on activation for one-time onboarding redirect (user ID or 1). */
	const ACTIVATION_REDIRECT_OPTION = 'launchdek_activation_redirect';

	/**
	 * Register the admin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		$cap = LAUNCHDEK_Capabilities::admin_menu_capability();

		add_menu_page(
			__( 'LaunchDek', 'launchdek' ),
			__( 'LaunchDek', 'launchdek' ),
			$cap,
			self::PAGE_SLUG,
			array( $this, 'render_admin_page' ),
			'dashicons-networking',
			73
		);

		$pages = array(
			self::PAGE_SLUG              => array( __( 'LaunchDek Overview', 'launchdek' ), 'render_admin_page', __( 'Dashboard', 'launchdek' ) ),
			self::PAGE_SLUG . '-sites'   => array( __( 'Sites', 'launchdek' ), 'render_sites_page' ),
			self::PAGE_SLUG . '-checklists' => array( __( 'Checklists', 'launchdek' ), 'render_checklists_page' ),
			self::PAGE_SLUG . '-automation' => array( __( 'Batch Run', 'launchdek' ), 'render_automation_page' ),
			self::PAGE_SLUG . '-integrations' => array( __( 'Integrations', 'launchdek' ), 'render_integrations_page' ),
			self::PAGE_SLUG . '-settings' => array( __( 'Settings', 'launchdek' ), 'render_settings_page' ),
		);

		if ( launchdek_includes_pro_package() ) {
			$pages[ self::PAGE_SLUG . '-billing' ] = array( __( 'Billing', 'launchdek' ), 'render_billing_page' );
		}

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
			__( 'Activity Logs', 'launchdek' ),
			__( 'Activity Logs', 'launchdek' ),
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

	/**
	 * Redirect to the dashboard onboarding wizard once after plugin activation.
	 *
	 * @return void
	 */
	public function maybe_activation_redirect() {
		$redirect_flag = get_option( self::ACTIVATION_REDIRECT_OPTION, false );
		if ( ! $redirect_flag ) {
			delete_transient( self::ACTIVATION_REDIRECT_OPTION );
			return;
		}

		delete_option( self::ACTIVATION_REDIRECT_OPTION );
		delete_transient( self::ACTIVATION_REDIRECT_OPTION );

		if ( wp_doing_ajax() || is_network_admin() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		if ( ! LAUNCHDEK_Capabilities::current_user_can( LAUNCHDEK_Capabilities::VIEW_DASHBOARD ) ) {
			return;
		}

		$redirect_user = absint( $redirect_flag );
		if ( $redirect_user > 1 && get_current_user_id() !== $redirect_user ) {
			return;
		}

		if ( isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( self::PAGE_SLUG === $page ) {
				return;
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&onboarding=1' ) );
		exit;
	}

	public function enqueue_styles( $hook ) {
		if ( ! $this->is_plugin_screen( $hook ) ) {
			return;
		}
		$path = LAUNCHDEK_PLUGIN_DIR . 'admin/css/launchdek-admin.css';
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'launchdek-admin', LAUNCHDEK_PLUGIN_URL . 'admin/css/launchdek-admin.css', array( 'wp-components' ), file_exists( $path ) ? (string) filemtime( $path ) : LAUNCHDEK_VERSION );

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
		wp_enqueue_script( 'wp-components' );
		wp_enqueue_script(
			'launchdek-admin',
			LAUNCHDEK_PLUGIN_URL . 'admin/js/launchdek-admin.js',
			array( 'wp-components' ),
			file_exists( $path ) ? (string) filemtime( $path ) : LAUNCHDEK_VERSION,
			true
		);
		$wp_roles = array();
		if ( function_exists( 'wp_roles' ) && wp_roles() ) {
			foreach ( wp_roles()->get_names() as $role_slug => $role_name ) {
				$wp_roles[ $role_slug ] = translate_user_role( $role_name );
			}
		}

		$localize = array(
				'restUrl'   => esc_url_raw( rest_url( LAUNCHDEK_REST_NAMESPACE ) ),
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
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
					'confirmDelete'  => __( 'Are you sure you want to delete this?', 'launchdek' ),
					'confirmDeleteSite' => __( 'Delete this site permanently? All checklist runs for this site will also be removed.', 'launchdek' ),
					'siteDeleted'       => __( 'Site deleted.', 'launchdek' ),
					'saved'          => __( 'Saved successfully.', 'launchdek' ),
					'error'          => __( 'Something went wrong.', 'launchdek' ),
					'sessionExpired' => __( 'Your session has expired. Please reload the page and try again.', 'launchdek' ),
					'loading'        => __( 'Loading…', 'launchdek' ),
					'noSites'        => __( 'No sites registered yet.', 'launchdek' ),
					'noSitesFiltered' => __( 'No sites match the current filters. Try clearing tag or group filters.', 'launchdek' ),
					'restUnavailable' => __( 'REST API is unavailable. On the hub site, go to Settings → Permalinks, choose Post name, and save.', 'launchdek' ),
					'healthOk'       => __( 'OK', 'launchdek' ),
					'healthFail'     => __( 'Fail', 'launchdek' ),
					'healthUnknown'  => __( 'Unknown', 'launchdek' ),
					'connectionOk'   => __( 'Connection OK', 'launchdek' ),
					'connectionFail' => __( 'Connection failed', 'launchdek' ),
					'connectionDisconnected' => __( 'Connection not working', 'launchdek' ),
					'connectionChecking' => __( 'Checking connection…', 'launchdek' ),
					'connectionAllStatuses' => __( 'All Sites', 'launchdek' ),
					'connectionSummaryHealthy' => __( 'Healthy', 'launchdek' ),
					'connectionSummaryUnhealthy' => __( 'Issues', 'launchdek' ),
					'connectionSummaryUnknown' => __( 'Unknown', 'launchdek' ),
					'editSite'       => __( 'Edit', 'launchdek' ),
					'testSite'       => __( 'Test', 'launchdek' ),
					'siteActivityLog' => __( 'Activity Log', 'launchdek' ),
					'siteActionsMenu' => __( 'More site actions', 'launchdek' ),
					'siteMoreInfo'           => __( 'More info', 'launchdek' ),
					'siteInfoDetails'        => __( 'Site details', 'launchdek' ),
					'siteInfoEdit'           => __( 'Edit site', 'launchdek' ),
					'siteInfoName'           => __( 'Site name', 'launchdek' ),
					'siteInfoUrl'            => __( 'URL', 'launchdek' ),
					'siteInfoId'             => __( 'Site ID', 'launchdek' ),
					'siteInfoCreated'        => __( 'Created', 'launchdek' ),
					'siteInfoUser'           => __( 'Username', 'launchdek' ),
					'siteInfoEnvironmentLabel' => __( 'Environment', 'launchdek' ),
					/* translators: 1: WordPress version, 2: PHP version. */
					'siteInfoEnvironment'    => __( 'WP %1$s · PHP %2$s', 'launchdek' ),
					'siteInfoConnection'     => __( 'Connection', 'launchdek' ),
					'siteInfoLastError'      => __( 'Last error', 'launchdek' ),
					'siteInfoClientPanel'    => __( 'Client panel', 'launchdek' ),
					'siteInfoAppPassword'    => __( 'App password', 'launchdek' ),
					'siteInfoIntegration'    => __( 'Integration', 'launchdek' ),
					'siteInfoTags'           => __( 'Tags', 'launchdek' ),
					'siteInfoUpdated'        => __( 'Updated', 'launchdek' ),
					'siteInfoYes'            => __( 'Yes', 'launchdek' ),
					'siteInfoNo'             => __( 'No', 'launchdek' ),
					'siteInfoConfigured'     => __( 'Configured', 'launchdek' ),
					'siteInfoMissing'        => __( 'Missing', 'launchdek' ),
					'siteGroupAdd'           => __( 'Add group…', 'launchdek' ),
					'siteGroupAddLabel'      => __( 'New group name', 'launchdek' ),
					'siteGroupAddSave'       => __( 'Add group', 'launchdek' ),
					'siteGroupAddCancel'     => __( 'Back', 'launchdek' ),
					'siteGroupNameRequired'  => __( 'Enter a group name.', 'launchdek' ),
					'siteGroupAdded'         => __( 'Group added.', 'launchdek' ),
					'siteAddToGroup'         => __( 'Add to group', 'launchdek' ),
					'siteAddToGroupTagRequired' => __( 'Enter a tag name.', 'launchdek' ),
					'pushChecklist'  => __( 'Push Checklist', 'launchdek' ),
					'pushChecklistSelect' => __( 'Select checklist…', 'launchdek' ),
					'pushChecklistNeed' => __( 'Select a checklist to push.', 'launchdek' ),
					'pushChecklistStarted' => __( 'Checklist run started.', 'launchdek' ),
					'pushChecklistBlocked' => __( 'Fix the connection before pushing a checklist.', 'launchdek' ),
					'siteChecklistHistory' => __( 'Checklist history', 'launchdek' ),
					'siteChecklistHistoryShow' => __( 'Show checklist history', 'launchdek' ),
					'siteChecklistHistoryHide' => __( 'Hide checklist history', 'launchdek' ),
					'siteNoChecklistRuns' => __( 'No checklist runs recorded for this site yet.', 'launchdek' ),
					'siteRunsLoadMore'   => __( 'Load more', 'launchdek' ),
					'siteRunsLoadingMore' => __( 'Loading more…', 'launchdek' ),
					'runChecklist'       => __( 'Checklist', 'launchdek' ),
					'runSteps'           => __( 'Steps', 'launchdek' ),
					/* translators: 1: completed step count, 2: total step count. */
					'runStepsProgress'   => __( '%1$d of %2$d steps completed', 'launchdek' ),
					'runStatus'          => __( 'Status', 'launchdek' ),
					'runStatusCompleted' => __( 'Completed', 'launchdek' ),
					'runStatusRunning'   => __( 'Running', 'launchdek' ),
					'runStatusFailed'    => __( 'Failed', 'launchdek' ),
					'runStatusCancelled' => __( 'Cancelled', 'launchdek' ),
					'runStartedAt'       => __( 'Started', 'launchdek' ),
					'runCompletedAt'     => __( 'Completed', 'launchdek' ),
					'runStartedBy'       => __( 'By', 'launchdek' ),
					'viewRun'            => __( 'View run', 'launchdek' ),
					'runActionsMenu'     => __( 'More run actions', 'launchdek' ),
					'duplicate'          => __( 'Duplicate', 'launchdek' ),
					'export'             => __( 'Export', 'launchdek' ),
					'archive'            => __( 'Archive', 'launchdek' ),
					'delete'             => __( 'Delete', 'launchdek' ),
					'confirmArchiveRun'  => __( 'Archive this run? It will be hidden from checklist history.', 'launchdek' ),
					'confirmDeleteRun'   => __( 'Delete this checklist run permanently?', 'launchdek' ),
					'confirmActionTitle' => __( 'Confirm action', 'launchdek' ),
					'confirm'            => __( 'Confirm', 'launchdek' ),
					'deletePermanently'  => __( 'Delete permanently', 'launchdek' ),
					'runArchived'        => __( 'Run archived.', 'launchdek' ),
					'runDeleted'         => __( 'Run deleted.', 'launchdek' ),
					'templateEditBlocked' => __( 'Built-in templates cannot be edited. Duplicate instead.', 'launchdek' ),
					'openRunner'     => __( 'Open runner →', 'launchdek' ),
					'clientPanelBadge' => __( 'Client panel', 'launchdek' ),
					'clientPushOk'   => __( 'Checklist pushed to client admin panel.', 'launchdek' ),
					'clientPushSkipped' => __( 'Run started on the hub. Complete the one-time client panel setup on Sites to show the checklist on the client site.', 'launchdek' ),
					'clientPushFailed' => __( 'Run started, but the client panel could not be updated.', 'launchdek' ),
					'panelSetupTitle' => __( 'Client checklist panel setup', 'launchdek' ),
					'panelSetupNeeded' => __( 'Client panel is not installed yet. Download the bootstrap file below, upload it to wp-content/mu-plugins/ on the client site, then retry panel install.', 'launchdek' ),
					'panelSetupReady' => __( 'Client panel is installed and ready.', 'launchdek' ),
					'panelInstallOk' => __( 'Client panel installed successfully.', 'launchdek' ),
					'panelInstallFailed' => __( 'Client panel install failed.', 'launchdek' ),
					'panelRetryNeedsSave' => __( 'Save this site first, then retry panel install.', 'launchdek' ),
					'panelBootstrapDownloaded' => __( 'Bootstrap file downloaded. Upload it to wp-content/mu-plugins/ on the client site, then click Retry panel install.', 'launchdek' ),
					'useChecklist'    => __( 'Use Checklist', 'launchdek' ),
					'checklistCreated' => __( 'Checklist created.', 'launchdek' ),
					'checklistTitleRequired' => __( 'Checklist title is required.', 'launchdek' ),
					'noCustomChecklists' => __( 'No custom checklists yet. Use a template or Start Blank in My Checklists.', 'launchdek' ),
					'noCustomTemplates' => __( 'No custom checklists saved yet. Create one under Checklists.', 'launchdek' ),
					'editChecklist' => __( 'Edit Checklist', 'launchdek' ),
					'customChecklistBadge' => __( 'Custom', 'launchdek' ),
					'checklistDuplicated' => __( 'Checklist duplicated.', 'launchdek' ),
					'confirmDeleteChecklist' => __( 'Are you sure you want to delete this checklist?', 'launchdek' ),
					'savedToVault'    => __( 'Saved to vault.', 'launchdek' ),
					'noVaultTemplates' => __( 'No vault templates yet.', 'launchdek' ),
					'noCategoryTemplates' => __( 'No templates in this category yet.', 'launchdek' ),
					'noSearchTemplates'   => __( 'No templates match your search.', 'launchdek' ),
					/* translators: %d: number of matching templates. */
					'builtinTemplateSearchHint' => __( 'Showing %d results across all categories.', 'launchdek' ),
					'chooseTemplate'  => __( 'Choose a Template', 'launchdek' ),
					'startBlankInstead' => __( 'Start blank instead', 'launchdek' ),
					'importTemplate'  => __( 'Import Template', 'launchdek' ),
					'cancel'          => __( 'Cancel', 'launchdek' ),
					'allCategories'   => __( 'All', 'launchdek' ),
					'templateImported' => __( 'Template imported successfully.', 'launchdek' ),
					'selectTemplateFirst' => __( 'Select a template to import.', 'launchdek' ),
					'templateStepsHeading' => __( 'Steps preview', 'launchdek' ),
					'templateStepsEmpty' => __( 'Select a template to preview its steps.', 'launchdek' ),
					'templateStepsNone' => __( 'This template has no steps yet.', 'launchdek' ),
					/* translators: %d: number of steps. */
					'stepCount'       => __( '%d step', 'launchdek' ),
					/* translators: %d: number of steps. */
					'stepsCount'      => __( '%d steps', 'launchdek' ),
					'viewSteps'       => __( 'View steps', 'launchdek' ),
					'hideSteps'       => __( 'Hide steps', 'launchdek' ),
					'checklistSteps'  => __( 'Checklist steps', 'launchdek' ),
					'stepSettings'    => __( 'Step settings', 'launchdek' ),
					'roleTargetMapping' => __( 'Role Target Mapping', 'launchdek' ),
					'allRoles'        => __( 'All roles', 'launchdek' ),
					/* translators: %d: number of selected roles. */
					'rolesSelected'   => __( '%d roles selected', 'launchdek' ),
					'selectTargetSites' => __( 'Select sites…', 'launchdek' ),
					/* translators: %d: number of selected sites. */
					'targetSitesSelected' => __( '%d sites selected', 'launchdek' ),
					'deepLinkAuto'    => __( 'Auto-detected from step content.', 'launchdek' ),
					'deepLinkHelp'    => __( 'Optional wp-admin path (e.g. options-permalink.php). LaunchDek auto-fills this from the step title, instructions, or API route when possible.', 'launchdek' ),
					'stepTypeHelp'    => __( 'Choose how this step completes during a run. Manual steps wait for a person; API steps run automatically on the remote site.', 'launchdek' ),
					'stepTypeManualHelp' => __( 'Manual steps pause the run until someone marks them complete in the hub run tracker or the client checklist panel. Use for tasks that need human verification.', 'launchdek' ),
					'stepTypeApiHelp' => __( 'API steps run automatically when the checklist reaches this step. LaunchDek sends an authenticated REST request to the remote WordPress site using its saved Application Password—no client panel action required.', 'launchdek' ),
					'apiMapperHelp'   => __( 'Configure the REST call LaunchDek makes on the client site. Use Validate API Step to dry-run checks before saving. Fields listed under Settings → Exclude Options are stripped from /wp/v2/settings payloads.', 'launchdek' ),
					'apiMethodHelp'   => __( 'HTTP verb for the request. GET reads data without changes. POST, PUT, PATCH, and DELETE send the JSON payload to create, update, or remove resources.', 'launchdek' ),
					'apiRouteHelp'    => __( 'WordPress REST API path on the remote site. Must start with / (for example, /wp/v2/settings for site options).', 'launchdek' ),
					'apiPayloadHelp'  => __( 'Request body for mutating methods. For /wp/v2/settings, use WordPress setting field names (for example, {"title":"My Site"}). Use {} for GET requests.', 'launchdek' ),
					'roleTargetMappingHelp' => __( 'Limit which WordPress roles can complete this step on the client panel. Leave empty to allow all logged-in users.', 'launchdek' ),
					'showNoteField'   => __( 'Show note field on client panel', 'launchdek' ),
					'showNoteFieldHelp' => __( 'When enabled, clients can add text notes as evidence when completing this manual step.', 'launchdek' ),
					'showScreenshotField' => __( 'Allow screenshot attachment on client panel', 'launchdek' ),
					'showScreenshotFieldHelp' => __( 'When enabled, clients can attach a screenshot from the media library when saving a step note.', 'launchdek' ),
					'connected'       => __( 'Connected', 'launchdek' ),
					'notDetected'     => __( 'Not Detected', 'launchdek' ),
					'setup'           => __( 'Setup', 'launchdek' ),
					'integrationSyncSites' => __( 'Sync Sites', 'launchdek' ),
					'integrationPreviewSync' => __( 'Preview Sync', 'launchdek' ),
					'integrationPushPanel' => __( 'Push Client Panel', 'launchdek' ),
					/* translators: 1: sites created, 2: sites updated, 3: sites skipped, 4: sites needing credentials. */
					'integrationSyncSummary' => __( 'Sync complete: %1$s created, %2$s updated, %3$s skipped. %4$s need credentials on the Sites page.', 'launchdek' ),
					/* translators: 1: sites that would be created, 2: updated, 3: skipped, 4: total platform sites. */
					'integrationPreviewSummary' => __( 'Preview: %1$s would be created, %2$s updated, %3$s skipped out of %4$s platform sites.', 'launchdek' ),
					/* translators: 1: successful pushes, 2: failed pushes, 3: skipped sites. */
					'integrationPushSummary' => __( 'Client panel push finished: %1$s succeeded, %2$s failed, %3$s skipped.', 'launchdek' ),
					/* translators: %s: number of synced sites. */
					'integrationSyncedSites' => __( '%s synced sites', 'launchdek' ),
					'integrationSyncUnsupported' => __( 'Site sync is not available for this connector yet.', 'launchdek' ),
					'integrationSyncEmptyHint' => __( 'No sites were available to sync. See the hint below, then try Preview Sync again.', 'launchdek' ),
					'integrationApiTokenConfigured' => __( 'API token saved.', 'launchdek' ),
					'integrationApiTokenRemoved' => __( 'API token removed.', 'launchdek' ),
					'integrationApiTokenRequired' => __( 'Save your API token before syncing sites.', 'launchdek' ),
					'integrationApiTokenPlaceholder' => __( 'Paste a new token to replace the saved token', 'launchdek' ),
					'onboardingTitle' => __( 'LaunchDek Onboarding', 'launchdek' ),
					'onboardingProgressLabel' => __( 'Onboarding progress', 'launchdek' ),
					'onboardingStep1'         => __( 'Step 1', 'launchdek' ),
					'onboardingStep1Hint'     => __( 'Import your checklist from SOPs or docs', 'launchdek' ),
					'onboardingStep2'         => __( 'Step 2', 'launchdek' ),
					'onboardingStep2Hint'     => __( 'Connect a client site with App Passwords', 'launchdek' ),
					'onboardingIntro' => __( 'Turn your messy Notion SOPs or Google Docs into an active Checklist.', 'launchdek' ),
					'onboardingPasteLabel' => __( 'Paste your checklist text here:', 'launchdek' ),
					'onboardingPreviewLabel' => __( 'Preview', 'launchdek' ),
					'onboardingTestSite' => __( 'Test workflow immediately on:', 'launchdek' ),
					'onboardingSelectSite' => __( 'Select site…', 'launchdek' ),
					'onboardingUseTemplate' => __( 'Start from a template instead', 'launchdek' ),
					'onboardingSkip' => __( 'Skip', 'launchdek' ),
					'onboardingNextConnect' => __( 'Next: Connect', 'launchdek' ),
					'onboardingConnectIntro' => __( 'Connect your first remote client site using WordPress App Passwords.', 'launchdek' ),
					'onboardingBack' => __( 'Back', 'launchdek' ),
					'onboardingLaunch' => __( 'Finish & Launch', 'launchdek' ),
					'onboardingOutro' => __( 'Your imported checklist workflow will be ready to push instantly.', 'launchdek' ),
					'onboardingTestConnection' => __( 'Test Connection', 'launchdek' ),
					'onboardingStatusConnected' => __( 'Status: Connected & Verified (OK)', 'launchdek' ),
					'onboardingStatusFailed' => __( 'Status: Connection failed', 'launchdek' ),
					'onboardingStatusPending' => __( 'Status: Not tested yet', 'launchdek' ),
					'onboardingStatusTesting' => __( 'Status: Testing…', 'launchdek' ),
					'onboardingPreviewEmpty' => __( 'Start typing to see your checklist preview.', 'launchdek' ),
					'onboardingNeedSteps' => __( 'Add at least one checklist step before continuing.', 'launchdek' ),
					'onboardingNeedConnection' => __( 'Test the connection successfully before launching.', 'launchdek' ),
					'onboardingExistingSiteReady' => __( 'Using selected site:', 'launchdek' ),
					'captureSelectSite'   => __( 'Select site…', 'launchdek' ),
					'captureNeedPanel'    => __( 'Auto-capture requires the client checklist panel on at least one site.', 'launchdek' ),
					'captureIdle'         => __( 'Choose a client site and start recording to capture configuration changes.', 'launchdek' ),
					'captureRecording'    => __( 'Recording — configure the client site in wp-admin. Changes are captured automatically.', 'launchdek' ),
					'captureReady'        => __( 'Recording stopped. Review captured steps below.', 'launchdek' ),
					'captureImportOne'    => __( 'Import 1 Captured Step', 'launchdek' ),
					/* translators: %d: number of captured steps. */
					'captureImportMany'   => __( 'Import %d Captured Steps', 'launchdek' ),
					'captureImported'     => __( 'Captured steps imported into the checklist builder.', 'launchdek' ),
					'captureDefaultTitle' => __( 'Captured Checklist', 'launchdek' ),
					'automationNeedSiteChecklist' => __( 'Select at least one site and a checklist.', 'launchdek' ),
					'automationNeedOneSite'       => __( 'Select exactly one site to start a run.', 'launchdek' ),
					'automationBatchQueueEmpty'   => __( 'No queued items to process.', 'launchdek' ),
					'automationStartRunFirst'     => __( 'Start a run first.', 'launchdek' ),
					'automationBatchProcessed'    => __( 'Batch queue processed.', 'launchdek' ),
					/* translators: %d: checklist run ID. */
					'automationRunStarted'        => __( 'Run #%d started.', 'launchdek' ),
					'automationExecuteEmpty'      => __( 'Complete steps 1–2 to start a run, or open an existing run from Sites.', 'launchdek' ),
					/* translators: %s: site name. */
					'automationRunActivityIntro'  => __( 'Recent activity for %s.', 'launchdek' ),
					'automationRunActivityIdle'   => __( 'Start a run to see site activity here, or open the full activity log.', 'launchdek' ),
					'auditViewDetails'            => __( 'View details', 'launchdek' ),
					'auditViewRawData'            => __( 'View raw data', 'launchdek' ),
					'auditViewRunSteps'           => __( 'View completed steps', 'launchdek' ),
					'auditRunStepsEmpty'          => __( 'No steps completed yet for this run.', 'launchdek' ),
					/* translators: %s: user display name. */
					'auditRunStepsBy'             => __( 'by %s', 'launchdek' ),
					/* translators: %d: step number. */
					'auditRunStepNumber'          => __( 'Step %d', 'launchdek' ),
					/* translators: %d: step number. */
					'auditRunStepFallback'        => __( 'Step %d', 'launchdek' ),
					'auditNoDetails'              => __( 'No additional details.', 'launchdek' ),
					'auditEmpty'                  => __( 'No activity matches these filters.', 'launchdek' ),
					'activityLogsLoadMore'        => __( 'Load more activity', 'launchdek' ),
					'activityLogsLoadingMore'     => __( 'Loading more activity…', 'launchdek' ),
					'automationTabRun'            => __( 'Run Checklist', 'launchdek' ),
					'automationTabBatch'          => __( 'Batch Queue', 'launchdek' ),
					'automationTabDrift'          => __( 'Drift Monitor', 'launchdek' ),
					'automationStep1Label'        => __( 'Step 1', 'launchdek' ),
					'automationStep1Hint'         => __( 'Choose checklist', 'launchdek' ),
					'automationStep2Label'        => __( 'Step 2', 'launchdek' ),
					'automationStep2Hint'         => __( 'Choose site', 'launchdek' ),
					'automationStep3Label'        => __( 'Step 3', 'launchdek' ),
					'automationStep3Hint'         => __( 'Execute steps', 'launchdek' ),
					'automationNext'              => __( 'Next', 'launchdek' ),
					'automationBack'              => __( 'Back', 'launchdek' ),
					'automationStartRun'          => __( 'Start Run', 'launchdek' ),
					'automationBackToSetup'       => __( 'Back to setup', 'launchdek' ),
					'billingProRequired'          => __( 'This feature is available on Pro and Agency plans.', 'launchdek' ),
					'billingViewPlans'            => __( 'View plans', 'launchdek' ),
					'billingSiteLimit'            => __( 'Your plan has reached its site limit. Upgrade on Billing to add more sites.', 'launchdek' ),
				),
			'billing' => LAUNCHDEK_Licensing::get_summary(),
		);

		if ( launchdek_includes_pro_package() ) {
			$localize['billingUrl'] = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-billing' );
		}

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
				'list'    => LAUNCHDEK_Dashboard_Cache::get_sites_list(),
				'groups'  => LAUNCHDEK_Settings::get_site_group_catalog(),
				'preloaded' => true,
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
				'categories' => LAUNCHDEK_Licensing::get_template_categories_for_plan(),
			);
		}

		if ( self::PAGE_SLUG . '_page_' . self::PAGE_SLUG . '-activity-logs' === $hook ) {
			$initial_site_id = isset( $_GET['site_id'] ) ? absint( wp_unslash( $_GET['site_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$localize['activityLogs'] = array(
				'preloaded'          => true,
				'sites'              => LAUNCHDEK_Site_Repository::picker_list(),
				'initialSiteId'      => $initial_site_id,
				'initialFeedPreload' => true,
				'initialFeed'        => LAUNCHDEK_Audit_Log::query(
					LAUNCHDEK_Audit_Log::list_query_args_from_input(
						array(
							'site_id'         => $initial_site_id,
							'limit'           => LAUNCHDEK_Audit_Log::LIST_DEFAULT_LIMIT,
							'offset'          => 0,
							'include_details' => false,
						)
					)
				),
			);
		}

		if ( self::PAGE_SLUG . '_page_' . self::PAGE_SLUG . '-integrations' === $hook ) {
			$localize['integrations'] = array_merge(
				array( 'preloaded' => true ),
				LAUNCHDEK_Integrations::get_page_bootstrap()
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
				'brandName'         => __( 'LaunchDek', 'launchdek' ),
				'strings'    => array(
					'collapse'         => __( 'Collapse', 'launchdek' ),
					/* translators: 1: current step number, 2: total step count. */
					'stepOf'           => __( 'Step %1$s of %2$s', 'launchdek' ),
					'markComplete'     => __( 'Mark complete', 'launchdek' ),
					'goToSettings'     => __( 'Go to settings', 'launchdek' ),
					'toggleStep'       => __( 'Toggle step details', 'launchdek' ),
					'waiting'          => __( 'Waiting on agency', 'launchdek' ),
					'addNotes'         => __( 'Add notes', 'launchdek' ),
					'attachScreenshot' => __( 'Attach screenshot', 'launchdek' ),
					'notePlaceholder'  => __( 'Add a note about this step…', 'launchdek' ),
					'previewEmpty'     => __( 'Add a title and steps to preview the client panel.', 'launchdek' ),
				),
			);
		}

		wp_localize_script( 'launchdek-admin', 'launchdekAdmin', $localize );
	}

	public function add_settings_link( $links ) {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-settings' ) ), esc_html__( 'Settings', 'launchdek' ) ) );
		return $links;
	}

	public function add_plugin_row_meta( $links, $file ) {
		if ( LAUNCHDEK_PLUGIN_BASENAME !== $file ) {
			return $links;
		}
		$links[] = sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url( LAUNCHDEK_PLUGIN_DOCS_URL ), esc_html__( 'Docs', 'launchdek' ) );
		return $links;
	}

	public function render_admin_page() {
		$this->render_page(
			'launchdek-admin-page.php',
			array(
				'page'                        => 'dashboard',
				'launchdek_dashboard_stats'     => LAUNCHDEK_Dashboard_Cache::get_stats(),
				'launchdek_connection_counts'   => LAUNCHDEK_Dashboard_Cache::get_connection_counts(),
				'launchdek_log_feed'            => LAUNCHDEK_Dashboard_Cache::get_feed(),
				'launchdek_quick_launch_picker' => LAUNCHDEK_Dashboard_Cache::get_quick_launch_picker(),
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

		$title = __( 'Activity Logs', 'launchdek' );
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
		if ( ! LAUNCHDEK_Capabilities::current_user_can( LAUNCHDEK_Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'launchdek' ) );
		}
		$this->render_page(
			'launchdek-integrations-page.php',
			array(
				'page'                   => 'integrations',
				'integrations_bootstrap' => LAUNCHDEK_Integrations::get_page_bootstrap(),
			)
		);
	}

	public function render_billing_page() {
		if ( ! LAUNCHDEK_Capabilities::current_user_can( LAUNCHDEK_Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'launchdek' ) );
		}
		$this->render_page(
			'launchdek-billing-page.php',
			array(
				'page' => 'billing',
			)
		);
	}

	public function render_settings_page() {
		if ( ! LAUNCHDEK_Capabilities::current_user_can( LAUNCHDEK_Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'launchdek' ) );
		}
		$settings = LAUNCHDEK_Settings::get();
		require LAUNCHDEK_PLUGIN_DIR . 'admin/partials/launchdek-settings-page.php';
	}

	protected function render_page( $partial, $vars = array() ) {
		if ( ! LAUNCHDEK_Capabilities::current_user_can( LAUNCHDEK_Capabilities::VIEW_DASHBOARD ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'launchdek' ) );
		}
		$settings = LAUNCHDEK_Settings::get();
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		require LAUNCHDEK_PLUGIN_DIR . 'admin/partials/' . $partial;
	}


	protected function is_plugin_screen( $hook ) {
		return false !== strpos( $hook, self::PAGE_SLUG );
	}
}
