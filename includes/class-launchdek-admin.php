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
			self::PAGE_SLUG              => array( __( 'Dashboard', LAUNCHDEK_TEXT_DOMAIN ), 'render_admin_page' ),
			self::PAGE_SLUG . '-sites'   => array( __( 'Sites', LAUNCHDEK_TEXT_DOMAIN ), 'render_sites_page' ),
			self::PAGE_SLUG . '-checklists' => array( __( 'Checklists', LAUNCHDEK_TEXT_DOMAIN ), 'render_checklists_page' ),
			self::PAGE_SLUG . '-automation' => array( __( 'Automation & Audit', LAUNCHDEK_TEXT_DOMAIN ), 'render_automation_page' ),
			self::PAGE_SLUG . '-templates' => array( __( 'Templates', LAUNCHDEK_TEXT_DOMAIN ), 'render_templates_page' ),
			self::PAGE_SLUG . '-integrations' => array( __( 'Integrations', LAUNCHDEK_TEXT_DOMAIN ), 'render_integrations_page' ),
			self::PAGE_SLUG . '-settings' => array( __( 'Settings', LAUNCHDEK_TEXT_DOMAIN ), 'render_settings_page' ),
		);

		foreach ( $pages as $slug => $page ) {
			add_submenu_page(
				self::PAGE_SLUG,
				$page[0],
				$page[0],
				$cap,
				$slug,
				array( $this, $page[1] )
			);
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

		wp_localize_script(
			'launchdek-admin',
			'launchdekAdmin',
			array(
				'restUrl'   => esc_url_raw( rest_url( LAUNCHDEK_REST_NAMESPACE ) ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'adminUrl'  => admin_url( 'admin.php' ),
				'pageSlug'  => self::PAGE_SLUG,
				'roles'     => $wp_roles,
				'onboarding' => array(
					'show'           => empty( LAUNCHDEK_Settings::get()['onboarding_dismissed'] ),
					'templatesUrl'   => admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-templates' ),
					'checklistsUrl'  => admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-checklists' ),
					'automationUrl'  => admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-automation' ),
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
					'cloneToChecklist' => __( 'Clone to Checklist', LAUNCHDEK_TEXT_DOMAIN ),
					'templateCloned'  => __( 'Template cloned. Edit it under Checklists.', LAUNCHDEK_TEXT_DOMAIN ),
					'checklistTitleRequired' => __( 'Checklist title is required.', LAUNCHDEK_TEXT_DOMAIN ),
					'noCustomChecklists' => __( 'No custom checklists yet. Click New Checklist to create one.', LAUNCHDEK_TEXT_DOMAIN ),
					'noCustomTemplates' => __( 'No custom checklists saved yet. Create one under Checklists.', LAUNCHDEK_TEXT_DOMAIN ),
					'editChecklist' => __( 'Edit Checklist', LAUNCHDEK_TEXT_DOMAIN ),
					'customChecklistBadge' => __( 'Custom', LAUNCHDEK_TEXT_DOMAIN ),
					'checklistCloned' => __( 'Checklist cloned. Edit it under Checklists.', LAUNCHDEK_TEXT_DOMAIN ),
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
					'stepsCount'      => __( '%d steps', LAUNCHDEK_TEXT_DOMAIN ),
					'viewSteps'       => __( 'View steps', LAUNCHDEK_TEXT_DOMAIN ),
					'hideSteps'       => __( 'Hide steps', LAUNCHDEK_TEXT_DOMAIN ),
					'checklistSteps'  => __( 'Checklist steps', LAUNCHDEK_TEXT_DOMAIN ),
					'stepsPreviewHint' => __( 'Hover or click View steps to preview the checklist.', LAUNCHDEK_TEXT_DOMAIN ),
					'connected'       => __( 'Connected', LAUNCHDEK_TEXT_DOMAIN ),
					'notDetected'     => __( 'Not Detected', LAUNCHDEK_TEXT_DOMAIN ),
					'setup'           => __( 'Setup', LAUNCHDEK_TEXT_DOMAIN ),
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
				),
			)
		);
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
		$this->render_page( 'launchdek-admin-page.php', array( 'page' => 'dashboard' ) );
	}

	public function render_sites_page() {
		$this->render_page( 'launchdek-sites-page.php', array( 'page' => 'sites' ) );
	}

	public function render_checklists_page() {
		$this->render_page( 'launchdek-checklists-page.php', array( 'page' => 'checklists' ) );
	}

	public function render_automation_page() {
		$this->render_page( 'launchdek-automation-page.php', array( 'page' => 'automation' ) );
	}

	public function render_templates_page() {
		$this->render_page( 'launchdek-templates-page.php', array( 'page' => 'templates' ) );
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
	}


	protected function is_plugin_screen( $hook ) {
		return false !== strpos( $hook, self::PAGE_SLUG );
	}
}
