<?php
/**
 * Admin area — menus, settings pages, asset enqueuing.
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin handler.
 */
class CHECKLISTDEK_Admin {

	const PAGE_SLUG = CHECKLISTDEK_PLUGIN_SLUG;

	/** @var string Option flag set on activation for one-time onboarding redirect (user ID or 1). */
	const ACTIVATION_REDIRECT_OPTION = 'checklistdek_activation_redirect';

	/**
	 * Register the admin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		$cap = CHECKLISTDEK_Capabilities::admin_menu_capability();

		add_menu_page(
			__( 'ChecklistDek', 'checklistdek' ),
			__( 'ChecklistDek', 'checklistdek' ),
			$cap,
			self::PAGE_SLUG,
			array( $this, 'render_admin_page' ),
			'dashicons-networking',
			73
		);

		$pages = array(
			self::PAGE_SLUG              => array( __( 'ChecklistDek Overview', 'checklistdek' ), 'render_admin_page', __( 'Dashboard', 'checklistdek' ) ),
			self::PAGE_SLUG . '-sites'   => array( __( 'Sites', 'checklistdek' ), 'render_sites_page' ),
			self::PAGE_SLUG . '-checklists' => array( __( 'Checklists', 'checklistdek' ), 'render_checklists_page' ),
			self::PAGE_SLUG . '-automation' => array( __( 'Batch Run', 'checklistdek' ), 'render_automation_page' ),
			self::PAGE_SLUG . '-integrations' => array( __( 'Integrations', 'checklistdek' ), 'render_integrations_page' ),
			self::PAGE_SLUG . '-settings' => array( __( 'Settings', 'checklistdek' ), 'render_settings_page' ),
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
			__( 'Activity Logs', 'checklistdek' ),
			__( 'Activity Logs', 'checklistdek' ),
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
			CHECKLISTDEK_Settings::SETTINGS_GROUP,
			CHECKLISTDEK_Settings::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'CHECKLISTDEK_Settings', 'sanitize' ),
				'default'           => CHECKLISTDEK_Settings::get_defaults(),
			)
		);
	}

	/**
	 * Redirect legacy Templates submenu URL to Checklists → Templates tab.
	 *
	 * @return void
	 */
	public function maybe_redirect_legacy_templates_page() {
		if ( ! is_admin() ) {
			return;
		}

		$page = self::get_admin_query_page();
		if ( '' === $page ) {
			return;
		}

		if ( self::PAGE_SLUG . '-templates' === $page ) {
			wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-checklists' ) );
			exit;
		}

		if ( self::PAGE_SLUG . '-sites' === $page && self::has_admin_query_flag( 'activity_logs' ) ) {
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
		if ( wp_doing_ajax() || is_network_admin() || self::has_admin_query_flag( 'activate-multi' ) ) {
			return;
		}

		$redirect_user = $this->get_pending_activation_redirect_user();
		if ( ! $redirect_user ) {
			return;
		}

		if ( ! CHECKLISTDEK_Capabilities::current_user_can( CHECKLISTDEK_Capabilities::VIEW_DASHBOARD ) ) {
			return;
		}

		if ( $redirect_user > 1 && get_current_user_id() !== $redirect_user ) {
			return;
		}

		$this->clear_activation_redirect_flag();

		if ( self::PAGE_SLUG === self::get_admin_query_page() ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&onboarding=1' ) );
		exit;
	}

	/**
	 * Pending activation redirect target (option, or legacy transient from pre-option installs).
	 *
	 * @return int User ID to match (1 = any user with dashboard access).
	 */
	private function get_pending_activation_redirect_user() {
		$flag = get_option( self::ACTIVATION_REDIRECT_OPTION, false );
		if ( false !== $flag && '' !== $flag ) {
			$user_id = absint( $flag );
			return $user_id > 0 ? $user_id : 0;
		}

		$legacy = get_transient( self::ACTIVATION_REDIRECT_OPTION );
		if ( false === $legacy || '' === $legacy ) {
			if ( false !== $legacy ) {
				delete_transient( self::ACTIVATION_REDIRECT_OPTION );
			}
			return 0;
		}

		$user_id = absint( $legacy );
		if ( $user_id > 0 ) {
			update_option( self::ACTIVATION_REDIRECT_OPTION, $user_id, false );
			delete_transient( self::ACTIVATION_REDIRECT_OPTION );
			return $user_id;
		}

		delete_transient( self::ACTIVATION_REDIRECT_OPTION );
		return 0;
	}

	/**
	 * Remove activation redirect flag from options and legacy transient storage.
	 *
	 * @return void
	 */
	private function clear_activation_redirect_flag() {
		delete_option( self::ACTIVATION_REDIRECT_OPTION );
		delete_transient( self::ACTIVATION_REDIRECT_OPTION );
	}

	/**
	 * Sanitized `page` query arg for wp-admin plugin screens (navigation only).
	 *
	 * @return string Empty when absent.
	 */
	private static function get_admin_query_page() {
		if ( ! isset( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation.
			return '';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation.
		return sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Whether a legacy/boolean wp-admin query flag is present.
	 *
	 * @param string $key Query arg key.
	 * @return bool
	 */
	private static function has_admin_query_flag( $key ) {
		$key = sanitize_key( $key );
		if ( '' === $key ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- Presence-only flag; key sanitized above.
		return isset( $_GET[ $key ] );
	}

	/**
	 * Positive integer wp-admin query arg (e.g. site_id filter).
	 *
	 * @param string $key Query arg key.
	 * @return int
	 */
	private static function get_admin_query_absint( $key ) {
		$key = sanitize_key( $key );
		if ( '' === $key || ! isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation.
			return 0;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation.
		return absint( wp_unslash( $_GET[ $key ] ) );
	}

	public function enqueue_styles( $hook ) {
		if ( ! $this->is_plugin_screen( $hook ) ) {
			return;
		}
		$path = CHECKLISTDEK_PLUGIN_DIR . 'admin/css/checklistdek-admin.css';
		wp_enqueue_style( 'wp-components' );
		wp_enqueue_style( 'checklistdek-admin', CHECKLISTDEK_PLUGIN_URL . 'admin/css/checklistdek-admin.css', array( 'wp-components' ), file_exists( $path ) ? (string) filemtime( $path ) : CHECKLISTDEK_VERSION );

		if ( self::PAGE_SLUG . '_page_' . self::PAGE_SLUG . '-checklists' === $hook ) {
			$client_css_path = CHECKLISTDEK_PLUGIN_DIR . 'mu-plugin/checklistdek-client/css/checklistdek-client-admin.css';
			wp_enqueue_style(
				'checklistdek-client-admin-preview',
				CHECKLISTDEK_PLUGIN_URL . 'mu-plugin/checklistdek-client/css/checklistdek-client-admin.css',
				array( 'checklistdek-admin' ),
				file_exists( $client_css_path ) ? (string) filemtime( $client_css_path ) : CHECKLISTDEK_VERSION
			);
		}
	}

	public function enqueue_scripts( $hook ) {
		if ( ! $this->is_plugin_screen( $hook ) ) {
			return;
		}
		$path = CHECKLISTDEK_PLUGIN_DIR . 'admin/js/checklistdek-admin.js';
		wp_enqueue_script( 'wp-components' );
		wp_enqueue_script(
			'checklistdek-admin',
			CHECKLISTDEK_PLUGIN_URL . 'admin/js/checklistdek-admin.js',
			array( 'wp-components', 'heartbeat' ),
			file_exists( $path ) ? (string) filemtime( $path ) : CHECKLISTDEK_VERSION,
			true
		);
		$wp_roles = array();
		if ( function_exists( 'wp_roles' ) && wp_roles() ) {
			foreach ( wp_roles()->get_names() as $role_slug => $role_name ) {
				$wp_roles[ $role_slug ] = translate_user_role( $role_name );
			}
		}

		$localize = array(
				'restUrl'   => esc_url_raw( rest_url( CHECKLISTDEK_REST_NAMESPACE ) ),
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'adminUrl'  => admin_url( 'admin.php' ),
				'pageSlug'  => self::PAGE_SLUG,
				'roles'     => $wp_roles,
				'onboarding' => array(
					'show'           => empty( CHECKLISTDEK_Settings::get()['onboarding_dismissed'] ),
					'templatesUrl'   => admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-checklists' ),
					'checklistsUrl'  => admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-checklists&tab=my-checklists' ),
					'automationUrl'  => admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-automation' ),
					'activityLogsUrl' => admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-activity-logs' ),
				),
				'strings'   => array(
					'confirmDelete'  => __( 'Are you sure you want to delete this?', 'checklistdek' ),
					'confirmDeleteSite' => __( 'Delete this site permanently? All checklist runs for this site will also be removed.', 'checklistdek' ),
					'siteDeleted'       => __( 'Site deleted.', 'checklistdek' ),
					'saved'          => __( 'Saved successfully.', 'checklistdek' ),
					'error'          => __( 'Something went wrong.', 'checklistdek' ),
					'sessionExpired' => __( 'Your session has expired. Please reload the page and try again.', 'checklistdek' ),
					'loading'        => __( 'Loading…', 'checklistdek' ),
					'noSites'        => __( 'No sites registered yet.', 'checklistdek' ),
					'noSitesFiltered' => __( 'No sites match the current filters. Try clearing tag or group filters.', 'checklistdek' ),
					'restUnavailable' => __( 'REST API is unavailable. On the hub site, go to Settings → Permalinks, choose Post name, and save.', 'checklistdek' ),
					'healthOk'       => __( 'OK', 'checklistdek' ),
					'healthFail'     => __( 'Fail', 'checklistdek' ),
					'healthUnknown'  => __( 'Unknown', 'checklistdek' ),
					'connectionOk'   => __( 'Connection OK', 'checklistdek' ),
					'connectionFail' => __( 'Connection failed', 'checklistdek' ),
					'connectionDisconnected' => __( 'Connection not working', 'checklistdek' ),
					'connectionChecking' => __( 'Checking connection…', 'checklistdek' ),
					'connectionAllStatuses' => __( 'All Sites', 'checklistdek' ),
					'connectionSummaryHealthy' => __( 'Healthy', 'checklistdek' ),
					'connectionSummaryUnhealthy' => __( 'Issues', 'checklistdek' ),
					'connectionSummaryUnknown' => __( 'Unknown', 'checklistdek' ),
					'editSite'       => __( 'Edit', 'checklistdek' ),
					'testSite'       => __( 'Test', 'checklistdek' ),
					'siteActivityLog' => __( 'Activity Log', 'checklistdek' ),
					'siteActionsMenu' => __( 'More site actions', 'checklistdek' ),
					'siteMoreInfo'           => __( 'More info', 'checklistdek' ),
					'siteInfoDetails'        => __( 'Site details', 'checklistdek' ),
					'siteInfoEdit'           => __( 'Edit site', 'checklistdek' ),
					'siteInfoName'           => __( 'Site name', 'checklistdek' ),
					'siteInfoUrl'            => __( 'URL', 'checklistdek' ),
					'siteInfoId'             => __( 'Site ID', 'checklistdek' ),
					'siteInfoCreated'        => __( 'Created', 'checklistdek' ),
					'siteInfoUser'           => __( 'Username', 'checklistdek' ),
					'siteInfoEnvironmentLabel' => __( 'Environment', 'checklistdek' ),
					/* translators: 1: WordPress version, 2: PHP version. */
					'siteInfoEnvironment'    => __( 'WP %1$s · PHP %2$s', 'checklistdek' ),
					'siteInfoConnection'     => __( 'Connection', 'checklistdek' ),
					'siteInfoLastError'      => __( 'Last error', 'checklistdek' ),
					'siteInfoClientPanel'    => __( 'Client panel', 'checklistdek' ),
					'siteInfoAppPassword'    => __( 'App password', 'checklistdek' ),
					'siteInfoIntegration'    => __( 'Integration', 'checklistdek' ),
					'siteInfoTags'           => __( 'Tags', 'checklistdek' ),
					'siteInfoUpdated'        => __( 'Updated', 'checklistdek' ),
					'siteInfoYes'            => __( 'Yes', 'checklistdek' ),
					'siteInfoNo'             => __( 'No', 'checklistdek' ),
					'siteInfoConfigured'     => __( 'Configured', 'checklistdek' ),
					'siteInfoMissing'        => __( 'Missing', 'checklistdek' ),
					'siteGroupAdd'           => __( 'Add group…', 'checklistdek' ),
					'siteGroupAddLabel'      => __( 'New group name', 'checklistdek' ),
					'siteGroupAddSave'       => __( 'Add group', 'checklistdek' ),
					'siteGroupAddCancel'     => __( 'Back', 'checklistdek' ),
					'siteGroupNameRequired'  => __( 'Enter a group name.', 'checklistdek' ),
					'siteGroupAdded'         => __( 'Group added.', 'checklistdek' ),
					'siteAddToGroup'         => __( 'Add to group', 'checklistdek' ),
					'siteAddToGroupTagRequired' => __( 'Enter a tag name.', 'checklistdek' ),
					'pushChecklist'  => __( 'Push Checklist', 'checklistdek' ),
					'pushChecklistSelect' => __( 'Select checklist…', 'checklistdek' ),
					'pushChecklistNeed' => __( 'Select a checklist to push.', 'checklistdek' ),
					'pushChecklistStarted' => __( 'Checklist run started.', 'checklistdek' ),
					'pushChecklistBlocked' => __( 'Fix the connection before pushing a checklist.', 'checklistdek' ),
					'siteChecklistHistory' => __( 'Checklist history', 'checklistdek' ),
					'siteChecklistHistoryShow' => __( 'Show checklist history', 'checklistdek' ),
					'siteChecklistHistoryHide' => __( 'Hide checklist history', 'checklistdek' ),
					'siteNoChecklistRuns' => __( 'No checklist runs recorded for this site yet.', 'checklistdek' ),
					'siteRunsLoadMore'   => __( 'Load more', 'checklistdek' ),
					'siteRunsLoadingMore' => __( 'Loading more…', 'checklistdek' ),
					'runChecklist'       => __( 'Checklist', 'checklistdek' ),
					'runSteps'           => __( 'Steps', 'checklistdek' ),
					/* translators: 1: completed step count, 2: total step count. */
					'runStepsProgress'   => __( '%1$d of %2$d steps completed', 'checklistdek' ),
					'runStatus'          => __( 'Status', 'checklistdek' ),
					'runStatusCompleted' => __( 'Completed', 'checklistdek' ),
					'runStatusRunning'   => __( 'Running', 'checklistdek' ),
					'runStatusFailed'    => __( 'Failed', 'checklistdek' ),
					'runStatusCancelled' => __( 'Cancelled', 'checklistdek' ),
					'runStartedAt'       => __( 'Started', 'checklistdek' ),
					'runCompletedAt'     => __( 'Completed', 'checklistdek' ),
					'runStartedBy'       => __( 'By', 'checklistdek' ),
					'viewRun'            => __( 'View run', 'checklistdek' ),
					'runActionsMenu'     => __( 'More run actions', 'checklistdek' ),
					'duplicate'          => __( 'Duplicate', 'checklistdek' ),
					'export'             => __( 'Export', 'checklistdek' ),
					'archive'            => __( 'Archive', 'checklistdek' ),
					'delete'             => __( 'Delete', 'checklistdek' ),
					'confirmArchiveRun'  => __( 'Archive this run? It will be hidden from checklist history.', 'checklistdek' ),
					'confirmDeleteRun'   => __( 'Delete this checklist run permanently?', 'checklistdek' ),
					'confirmActionTitle' => __( 'Confirm action', 'checklistdek' ),
					'confirm'            => __( 'Confirm', 'checklistdek' ),
					'deletePermanently'  => __( 'Delete permanently', 'checklistdek' ),
					'runArchived'        => __( 'Run archived.', 'checklistdek' ),
					'runDeleted'         => __( 'Run deleted.', 'checklistdek' ),
					'templateEditBlocked' => __( 'Built-in templates cannot be edited. Duplicate instead.', 'checklistdek' ),
					'openRunner'     => __( 'Open runner →', 'checklistdek' ),
					'clientPanelBadge' => __( 'Client panel', 'checklistdek' ),
					'clientPushOk'   => __( 'Checklist pushed to client admin panel.', 'checklistdek' ),
					'clientPushSkipped' => __( 'Run started on the hub. Complete the one-time client panel setup on Sites to show the checklist on the client site.', 'checklistdek' ),
					'clientPushFailed' => __( 'Run started, but the client panel could not be updated.', 'checklistdek' ),
					'panelSetupTitle' => __( 'Client checklist panel setup', 'checklistdek' ),
					'panelSetupNeeded' => __( 'Client panel is not installed yet. Download the bootstrap file below, upload it to wp-content/mu-plugins/ on the client site, then retry panel install.', 'checklistdek' ),
					'panelSetupReady' => __( 'Client panel is installed and ready.', 'checklistdek' ),
					'panelInstallOk' => __( 'Client panel installed successfully.', 'checklistdek' ),
					'panelInstallFailed' => __( 'Client panel install failed.', 'checklistdek' ),
					'panelRetryNeedsSave' => __( 'Save this site first, then retry panel install.', 'checklistdek' ),
					'panelBootstrapDownloaded' => __( 'Bootstrap file downloaded. Upload it to wp-content/mu-plugins/ on the client site, then click Retry panel install.', 'checklistdek' ),
					'useChecklist'    => __( 'Use Checklist', 'checklistdek' ),
					'checklistCreated' => __( 'Checklist created.', 'checklistdek' ),
					'checklistTitleRequired' => __( 'Checklist title is required.', 'checklistdek' ),
					'noCustomChecklists' => __( 'No custom checklists yet. Use a template or Start Blank in My Checklists.', 'checklistdek' ),
					'noCustomTemplates' => __( 'No custom checklists saved yet. Create one under Checklists.', 'checklistdek' ),
					'editChecklist' => __( 'Edit Checklist', 'checklistdek' ),
					'customChecklistBadge' => __( 'Custom', 'checklistdek' ),
					'checklistDuplicated' => __( 'Checklist duplicated.', 'checklistdek' ),
					'confirmDeleteChecklist' => __( 'Are you sure you want to delete this checklist?', 'checklistdek' ),
					'savedToVault'    => __( 'Saved to vault.', 'checklistdek' ),
					'noVaultTemplates' => __( 'No vault templates yet.', 'checklistdek' ),
					'noCategoryTemplates' => __( 'No templates in this category yet.', 'checklistdek' ),
					'noSearchTemplates'   => __( 'No templates match your search.', 'checklistdek' ),
					/* translators: %d: number of matching templates. */
					'builtinTemplateSearchHint' => __( 'Showing %d results across all categories.', 'checklistdek' ),
					'chooseTemplate'  => __( 'Choose a Template', 'checklistdek' ),
					'startBlankInstead' => __( 'Start blank instead', 'checklistdek' ),
					'importTemplate'  => __( 'Import Template', 'checklistdek' ),
					'cancel'          => __( 'Cancel', 'checklistdek' ),
					'allCategories'   => __( 'All', 'checklistdek' ),
					'templateImported' => __( 'Template imported successfully.', 'checklistdek' ),
					'selectTemplateFirst' => __( 'Select a template to import.', 'checklistdek' ),
					'templateStepsHeading' => __( 'Steps preview', 'checklistdek' ),
					'templateStepsEmpty' => __( 'Select a template to preview its steps.', 'checklistdek' ),
					'templateStepsNone' => __( 'This template has no steps yet.', 'checklistdek' ),
					/* translators: %d: number of steps. */
					'stepCount'       => __( '%d step', 'checklistdek' ),
					/* translators: %d: number of steps. */
					'stepsCount'      => __( '%d steps', 'checklistdek' ),
					'viewSteps'       => __( 'View steps', 'checklistdek' ),
					'hideSteps'       => __( 'Hide steps', 'checklistdek' ),
					'checklistSteps'  => __( 'Checklist steps', 'checklistdek' ),
					'stepSettings'    => __( 'Step settings', 'checklistdek' ),
					'roleTargetMapping' => __( 'Role Target Mapping', 'checklistdek' ),
					'allRoles'        => __( 'All roles', 'checklistdek' ),
					/* translators: %d: number of selected roles. */
					'rolesSelected'   => __( '%d roles selected', 'checklistdek' ),
					'selectTargetSites' => __( 'Select sites…', 'checklistdek' ),
					/* translators: %d: number of selected sites. */
					'targetSitesSelected' => __( '%d sites selected', 'checklistdek' ),
					'deepLinkAuto'    => __( 'Auto-detected from step content.', 'checklistdek' ),
					'deepLinkHelp'    => __( 'Optional wp-admin path (e.g. options-permalink.php). ChecklistDek auto-fills this from the step title, instructions, or API route when possible.', 'checklistdek' ),
					'stepTypeHelp'    => __( 'Choose how this step completes during a run. Manual steps wait for a person; API steps run automatically on the remote site.', 'checklistdek' ),
					'stepTypeManualHelp' => __( 'Manual steps pause the run until someone marks them complete in the hub run tracker or the client checklist panel. Use for tasks that need human verification.', 'checklistdek' ),
					'stepTypeApiHelp' => __( 'API steps run automatically when the checklist reaches this step. ChecklistDek sends an authenticated REST request to the remote WordPress site using its saved Application Password—no client panel action required.', 'checklistdek' ),
					'apiMapperHelp'   => __( 'Configure the REST call ChecklistDek makes on the client site. Use Validate API Step to dry-run checks before saving. Fields listed under Settings → Exclude Options are stripped from /wp/v2/settings payloads.', 'checklistdek' ),
					'apiMethodHelp'   => __( 'HTTP verb for the request. GET reads data without changes. POST, PUT, PATCH, and DELETE send the JSON payload to create, update, or remove resources.', 'checklistdek' ),
					'apiRouteHelp'    => __( 'WordPress REST API path on the remote site. Must start with / (for example, /wp/v2/settings for site options).', 'checklistdek' ),
					'apiPayloadHelp'  => __( 'Request body for mutating methods. For /wp/v2/settings, use WordPress setting field names (for example, {"title":"My Site"}). Use {} for GET requests.', 'checklistdek' ),
					'roleTargetMappingHelp' => __( 'Limit which WordPress roles can complete this step on the client panel. Leave empty to allow all logged-in users.', 'checklistdek' ),
					'showNoteField'   => __( 'Show note field on client panel', 'checklistdek' ),
					'showNoteFieldHelp' => __( 'When enabled, clients can add text notes as evidence when completing this manual step.', 'checklistdek' ),
					'showScreenshotField' => __( 'Allow screenshot attachment on client panel', 'checklistdek' ),
					'showScreenshotFieldHelp' => __( 'When enabled, clients can attach a screenshot from the media library when saving a step note.', 'checklistdek' ),
					'connected'       => __( 'Connected', 'checklistdek' ),
					'notDetected'     => __( 'Not Detected', 'checklistdek' ),
					'setup'           => __( 'Setup', 'checklistdek' ),
					'integrationSyncSites' => __( 'Sync Sites', 'checklistdek' ),
					'integrationPreviewSync' => __( 'Preview Sync', 'checklistdek' ),
					'integrationPushPanel' => __( 'Push Client Panel', 'checklistdek' ),
					/* translators: 1: sites created, 2: sites updated, 3: sites skipped, 4: sites needing credentials. */
					'integrationSyncSummary' => __( 'Sync complete: %1$s created, %2$s updated, %3$s skipped. %4$s need credentials on the Sites page.', 'checklistdek' ),
					/* translators: 1: sites that would be created, 2: updated, 3: skipped, 4: total platform sites. */
					'integrationPreviewSummary' => __( 'Preview: %1$s would be created, %2$s updated, %3$s skipped out of %4$s platform sites.', 'checklistdek' ),
					/* translators: 1: successful pushes, 2: failed pushes, 3: skipped sites. */
					'integrationPushSummary' => __( 'Client panel push finished: %1$s succeeded, %2$s failed, %3$s skipped.', 'checklistdek' ),
					/* translators: %s: number of synced sites. */
					'integrationSyncedSites' => __( '%s synced sites', 'checklistdek' ),
					'integrationSyncUnsupported' => __( 'Site sync is not available for this connector yet.', 'checklistdek' ),
					'integrationSyncEmptyHint' => __( 'No sites were available to sync. See the hint below, then try Preview Sync again.', 'checklistdek' ),
					'integrationApiTokenConfigured' => __( 'API token saved.', 'checklistdek' ),
					'integrationApiTokenRemoved' => __( 'API token removed.', 'checklistdek' ),
					'integrationApiTokenRequired' => __( 'Save your API token before syncing sites.', 'checklistdek' ),
					'integrationApiTokenPlaceholder' => __( 'Paste a new token to replace the saved token', 'checklistdek' ),
					'onboardingTitle' => __( 'ChecklistDek Onboarding', 'checklistdek' ),
					'onboardingProgressLabel' => __( 'Onboarding progress', 'checklistdek' ),
					'onboardingStep1'         => __( 'Step 1', 'checklistdek' ),
					'onboardingStep1Hint'     => __( 'Import your checklist from SOPs or docs', 'checklistdek' ),
					'onboardingStep2'         => __( 'Step 2', 'checklistdek' ),
					'onboardingStep2Hint'     => __( 'Connect a client site with App Passwords', 'checklistdek' ),
					'onboardingIntro' => __( 'Turn your messy Notion SOPs or Google Docs into an active Checklist.', 'checklistdek' ),
					'onboardingPasteLabel' => __( 'Paste your checklist text here:', 'checklistdek' ),
					'onboardingPreviewLabel' => __( 'Preview', 'checklistdek' ),
					'onboardingTestSite' => __( 'Test workflow immediately on:', 'checklistdek' ),
					'onboardingSelectSite' => __( 'Select site…', 'checklistdek' ),
					'onboardingUseTemplate' => __( 'Start from a template instead', 'checklistdek' ),
					'onboardingSkip' => __( 'Skip', 'checklistdek' ),
					'onboardingNextConnect' => __( 'Next: Connect', 'checklistdek' ),
					'onboardingConnectIntro' => __( 'Connect your first remote client site using WordPress App Passwords.', 'checklistdek' ),
					'onboardingBack' => __( 'Back', 'checklistdek' ),
					'onboardingLaunch' => __( 'Finish & Launch', 'checklistdek' ),
					'onboardingOutro' => __( 'Your imported checklist workflow will be ready to push instantly.', 'checklistdek' ),
					'onboardingTestConnection' => __( 'Test Connection', 'checklistdek' ),
					'onboardingStatusConnected' => __( 'Status: Connected & Verified (OK)', 'checklistdek' ),
					'onboardingStatusFailed' => __( 'Status: Connection failed', 'checklistdek' ),
					'onboardingStatusPending' => __( 'Status: Not tested yet', 'checklistdek' ),
					'onboardingStatusTesting' => __( 'Status: Testing…', 'checklistdek' ),
					'onboardingPreviewEmpty' => __( 'Start typing to see your checklist preview.', 'checklistdek' ),
					'onboardingNeedSteps' => __( 'Add at least one checklist step before continuing.', 'checklistdek' ),
					'onboardingNeedConnection' => __( 'Test the connection successfully before launching.', 'checklistdek' ),
					'onboardingExistingSiteReady' => __( 'Using selected site:', 'checklistdek' ),
					'captureSelectSite'   => __( 'Select site…', 'checklistdek' ),
					'captureNeedPanel'    => __( 'Auto-capture requires the client checklist panel on at least one site.', 'checklistdek' ),
					'captureIdle'         => __( 'Choose a client site and start recording to capture configuration changes.', 'checklistdek' ),
					'captureRecording'    => __( 'Recording — configure the client site in wp-admin. Changes are captured automatically.', 'checklistdek' ),
					'captureReady'        => __( 'Recording stopped. Review captured steps below.', 'checklistdek' ),
					'captureImportOne'    => __( 'Import 1 Captured Step', 'checklistdek' ),
					/* translators: %d: number of captured steps. */
					'captureImportMany'   => __( 'Import %d Captured Steps', 'checklistdek' ),
					'captureImported'     => __( 'Captured steps imported into the checklist builder.', 'checklistdek' ),
					'captureDefaultTitle' => __( 'Captured Checklist', 'checklistdek' ),
					'automationNeedSiteChecklist' => __( 'Select at least one site and a checklist.', 'checklistdek' ),
					'automationNeedOneSite'       => __( 'Select exactly one site to start a run.', 'checklistdek' ),
					'automationBatchQueueEmpty'   => __( 'No queued items to process.', 'checklistdek' ),
					'automationStartRunFirst'     => __( 'Start a run first.', 'checklistdek' ),
					'automationBatchProcessed'    => __( 'Batch queue processed.', 'checklistdek' ),
					/* translators: %d: checklist run ID. */
					'automationRunStarted'        => __( 'Run #%d started.', 'checklistdek' ),
					'automationExecuteEmpty'      => __( 'Complete steps 1–2 to start a run, or open an existing run from Sites.', 'checklistdek' ),
					/* translators: %s: site name. */
					'automationRunActivityIntro'  => __( 'Recent activity for %s.', 'checklistdek' ),
					'automationRunActivityIdle'   => __( 'Start a run to see site activity here, or open the full activity log.', 'checklistdek' ),
					'auditViewDetails'            => __( 'View details', 'checklistdek' ),
					'auditViewRawData'            => __( 'View raw data', 'checklistdek' ),
					'auditViewRunSteps'           => __( 'View completed steps', 'checklistdek' ),
					'auditRunStepsEmpty'          => __( 'No steps completed yet for this run.', 'checklistdek' ),
					/* translators: %s: user display name. */
					'auditRunStepsBy'             => __( 'by %s', 'checklistdek' ),
					/* translators: %d: step number. */
					'auditRunStepNumber'          => __( 'Step %d', 'checklistdek' ),
					/* translators: %d: step number. */
					'auditRunStepFallback'        => __( 'Step %d', 'checklistdek' ),
					'auditNoDetails'              => __( 'No additional details.', 'checklistdek' ),
					'auditEmpty'                  => __( 'No activity matches these filters.', 'checklistdek' ),
					'activityLogsLoadMore'        => __( 'Load more activity', 'checklistdek' ),
					'activityLogsLoadingMore'     => __( 'Loading more activity…', 'checklistdek' ),
					'automationTabRun'            => __( 'Run Checklist', 'checklistdek' ),
					'automationTabBatch'          => __( 'Batch Queue', 'checklistdek' ),
					'automationTabDrift'          => __( 'Drift Monitor', 'checklistdek' ),
					'automationStep1Label'        => __( 'Step 1', 'checklistdek' ),
					'automationStep1Hint'         => __( 'Choose checklist', 'checklistdek' ),
					'automationStep2Label'        => __( 'Step 2', 'checklistdek' ),
					'automationStep2Hint'         => __( 'Choose site', 'checklistdek' ),
					'automationStep3Label'        => __( 'Step 3', 'checklistdek' ),
					'automationStep3Hint'         => __( 'Execute steps', 'checklistdek' ),
					'automationNext'              => __( 'Next', 'checklistdek' ),
					'automationBack'              => __( 'Back', 'checklistdek' ),
					'automationStartRun'          => __( 'Start Run', 'checklistdek' ),
					'automationBackToSetup'       => __( 'Back to setup', 'checklistdek' ),
				),
		);

		if ( ! CHECKLISTDEK_Licensing::can_use_auto_capture() ) {
			foreach ( array(
				'captureSelectSite',
				'captureNeedPanel',
				'captureIdle',
				'captureRecording',
				'captureReady',
				'captureImportOne',
				'captureImportMany',
				'captureImported',
				'captureDefaultTitle',
			) as $capture_string_key ) {
				unset( $localize['strings'][ $capture_string_key ] );
			}
		}

		if ( ! CHECKLISTDEK_Licensing::can_use_agency_vault() ) {
			unset( $localize['strings']['savedToVault'], $localize['strings']['noVaultTemplates'] );
		}

		if ( 'toplevel_page_' . self::PAGE_SLUG === $hook ) {
			$localize['dashboard'] = array(
				'stats'       => CHECKLISTDEK_Dashboard_Cache::get_stats(),
				'connections' => CHECKLISTDEK_Dashboard_Cache::get_connection_counts(),
				'feed'        => CHECKLISTDEK_Dashboard_Cache::get_feed(),
				'quickLaunch' => CHECKLISTDEK_Dashboard_Cache::get_quick_launch_picker(),
			);
		}

		if ( self::PAGE_SLUG . '_page_' . self::PAGE_SLUG . '-sites' === $hook ) {
			$localize['sites'] = array(
				'list'    => CHECKLISTDEK_Dashboard_Cache::get_sites_list(),
				'groups'  => CHECKLISTDEK_Settings::get_site_group_catalog(),
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
				'builtin'    => CHECKLISTDEK_Templates::get_catalog(),
				'categories' => CHECKLISTDEK_Licensing::get_template_categories_for_plan(),
			);
		}

		if ( self::PAGE_SLUG . '_page_' . self::PAGE_SLUG . '-activity-logs' === $hook ) {
			$initial_site_id = self::get_admin_query_absint( 'site_id' );
			$localize['activityLogs'] = array(
				'preloaded'          => true,
				'sites'              => CHECKLISTDEK_Site_Repository::picker_list(),
				'initialSiteId'      => $initial_site_id,
				'initialFeedPreload' => true,
				'initialFeed'        => CHECKLISTDEK_Audit_Log::query(
					CHECKLISTDEK_Audit_Log::list_query_args_from_input(
						array(
							'site_id'         => $initial_site_id,
							'limit'           => CHECKLISTDEK_Audit_Log::LIST_DEFAULT_LIMIT,
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
				CHECKLISTDEK_Integrations::get_page_bootstrap()
			);
		}

		if ( self::PAGE_SLUG . '_page_' . self::PAGE_SLUG . '-checklists' === $hook ) {
			$localize['customChecklists'] = array(
				'preloaded' => true,
				'items'     => CHECKLISTDEK_Checklist_Repository::summary_list(
					array(
						'is_template' => 0,
					)
				),
			);
			$localize['clientPreview'] = array(
				'panelTitle'        => CHECKLISTDEK_Settings::get_client_panel_title(),
				'defaultPanelTitle' => CHECKLISTDEK_Settings::get_default_client_panel_title(),
				'brandName'         => __( 'ChecklistDek', 'checklistdek' ),
				'strings'    => array(
					'collapse'         => __( 'Collapse', 'checklistdek' ),
					/* translators: 1: current step number, 2: total step count. */
					'stepOf'           => __( 'Step %1$s of %2$s', 'checklistdek' ),
					'markComplete'     => __( 'Mark complete', 'checklistdek' ),
					'goToSettings'     => __( 'Go to settings', 'checklistdek' ),
					'toggleStep'       => __( 'Toggle step details', 'checklistdek' ),
					'waiting'          => __( 'Waiting on agency', 'checklistdek' ),
					'addNotes'         => __( 'Add notes', 'checklistdek' ),
					'attachScreenshot' => __( 'Attach screenshot', 'checklistdek' ),
					'notePlaceholder'  => __( 'Add a note about this step…', 'checklistdek' ),
					'previewEmpty'     => __( 'Add a title and steps to preview the client panel.', 'checklistdek' ),
				),
			);
		}

		wp_localize_script( 'checklistdek-admin', 'checklistdekAdmin', $localize );
	}

	public function add_settings_link( $links ) {
		array_unshift(
			$links,
			wp_kses(
				sprintf(
					'<a href="%1$s">%2$s</a>',
					esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-settings' ) ),
					esc_html__( 'Settings', 'checklistdek' )
				),
				array(
					'a' => array(
						'href' => true,
					),
				)
			)
		);
		return $links;
	}

	public function add_plugin_row_meta( $links, $file ) {
		if ( CHECKLISTDEK_PLUGIN_BASENAME !== $file ) {
			return $links;
		}
		$links[] = wp_kses(
			sprintf(
				'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( CHECKLISTDEK_PLUGIN_DOCS_URL ),
				esc_html__( 'Docs', 'checklistdek' )
			),
			array(
				'a' => array(
					'href'   => true,
					'target' => true,
					'rel'    => true,
				),
			)
		);
		return $links;
	}

	public function render_admin_page() {
		$this->render_page(
			'checklistdek-admin-page.php',
			array(
				'page'                        => 'dashboard',
				'checklistdek_dashboard_stats'     => CHECKLISTDEK_Dashboard_Cache::get_stats(),
				'checklistdek_connection_counts'   => CHECKLISTDEK_Dashboard_Cache::get_connection_counts(),
				'checklistdek_log_feed'            => CHECKLISTDEK_Dashboard_Cache::get_feed(),
				'checklistdek_quick_launch_picker' => CHECKLISTDEK_Dashboard_Cache::get_quick_launch_picker(),
			)
		);
	}

	public function render_sites_page() {
		$this->render_page(
			'checklistdek-sites-page.php',
			array(
				'page'       => 'sites',
				'sites_list' => CHECKLISTDEK_Dashboard_Cache::get_sites_list(),
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

		$title = __( 'Activity Logs', 'checklistdek' );
	}

	public function render_activity_logs_page() {
		$this->render_page( 'checklistdek-activity-logs-page.php', array( 'page' => 'activity-logs' ) );
	}

	public function render_checklists_page() {
		$this->render_page( 'checklistdek-checklists-page.php', array( 'page' => 'checklists' ) );
	}

	public function render_automation_page() {
		$this->render_page( 'checklistdek-automation-page.php', array( 'page' => 'automation' ) );
	}

	public function render_integrations_page() {
		if ( ! CHECKLISTDEK_Capabilities::current_user_can( CHECKLISTDEK_Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'checklistdek' ) );
		}
		$this->render_page(
			'checklistdek-integrations-page.php',
			array(
				'page'                   => 'integrations',
				'integrations_bootstrap' => CHECKLISTDEK_Integrations::get_page_bootstrap(),
			)
		);
	}

	public function render_settings_page() {
		if ( ! CHECKLISTDEK_Capabilities::current_user_can( CHECKLISTDEK_Capabilities::MANAGE_SETTINGS ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'checklistdek' ) );
		}
		$settings = CHECKLISTDEK_Settings::get();
		require CHECKLISTDEK_PLUGIN_DIR . 'admin/partials/checklistdek-settings-page.php';
	}

	protected function render_page( $partial, $vars = array() ) {
		if ( ! CHECKLISTDEK_Capabilities::current_user_can( CHECKLISTDEK_Capabilities::VIEW_DASHBOARD ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'checklistdek' ) );
		}
		$settings = CHECKLISTDEK_Settings::get();
		extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		require CHECKLISTDEK_PLUGIN_DIR . 'admin/partials/' . $partial;
	}


	protected function is_plugin_screen( $hook ) {
		return false !== strpos( $hook, self::PAGE_SLUG );
	}
}
