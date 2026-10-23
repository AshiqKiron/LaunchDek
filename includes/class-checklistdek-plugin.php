<?php
/**
 * Core plugin orchestrator — wires all hooks in one place.
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
class CHECKLISTDEK_Plugin {

	/** @var CHECKLISTDEK_Admin */
	protected $admin;

	public function __construct() {
		$this->admin = new CHECKLISTDEK_Admin();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'init', array( $this, 'init' ), 0 );
		add_action( 'admin_menu', array( $this->admin, 'register_menu' ) );
		add_action( 'admin_init', array( $this->admin, 'register_settings' ) );
		add_action( 'admin_init', array( $this->admin, 'maybe_activation_redirect' ) );
		add_action( 'admin_init', array( $this->admin, 'maybe_redirect_legacy_templates_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_scripts' ) );
		add_filter( 'plugin_action_links_' . CHECKLISTDEK_PLUGIN_BASENAME, array( $this->admin, 'add_settings_link' ) );
		add_filter( 'plugin_row_meta', array( $this->admin, 'add_plugin_row_meta' ), 10, 2 );

		CHECKLISTDEK_REST_API::register();
		// Community zip: same admin-ajax handlers as Pro (integrations, telemetry, site runs, activity logs).
		CHECKLISTDEK_Admin_Ajax::register();
		CHECKLISTDEK_Drift_Cron::register();
	}

	/**
	 * Late init — DB migrations and template seeding.
	 *
	 * @return void
	 */
	public function init() {
		CHECKLISTDEK_Installer::maybe_install();
		CHECKLISTDEK_Capabilities::register();
		CHECKLISTDEK_Templates::bootstrap_builtin_templates();
		checklistdek_sync_drift_cron();
	}

	/**
	 * Whether built-in template sync should run on this request (skip public front-end views).
	 *
	 * @return bool
	 */
	public static function should_sync_builtin_templates() {
		if ( is_admin() ) {
			return true;
		}

		if ( wp_doing_cron() || wp_doing_ajax() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		return false;
	}
}
