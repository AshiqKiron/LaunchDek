<?php
/**
 * Core plugin orchestrator — wires all hooks in one place.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
class LAUNCHDEK_Plugin {

	/** @var LAUNCHDEK_Admin */
	protected $admin;

	public function __construct() {
		$this->admin = new LAUNCHDEK_Admin();
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
		add_filter( 'plugin_action_links_' . LAUNCHDEK_PLUGIN_BASENAME, array( $this->admin, 'add_settings_link' ) );
		add_filter( 'plugin_row_meta', array( $this->admin, 'add_plugin_row_meta' ), 10, 2 );

		LAUNCHDEK_REST_API::register();
		LAUNCHDEK_Admin_Ajax::register();
		LAUNCHDEK_Drift_Cron::register();
	}

	/**
	 * Late init — textdomain, DB migrations, and template seeding.
	 *
	 * @return void
	 */
	public function init() {
		load_plugin_textdomain(
			LAUNCHDEK_TEXT_DOMAIN,
			false,
			dirname( LAUNCHDEK_PLUGIN_BASENAME ) . '/languages'
		);

		LAUNCHDEK_Installer::maybe_install();
		LAUNCHDEK_Capabilities::register();
		LAUNCHDEK_Templates::seed_builtin();
		LAUNCHDEK_Templates::sync_builtin();
	}
}
