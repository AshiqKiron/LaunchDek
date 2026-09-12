<?php
/**
 * Core plugin orchestrator — wires all hooks in one place.
 *
 * Purpose: Instantiate dependencies and register WordPress actions/filters.
 *
 * Architecture map
 * -----------------
 * Admin pages:
 *   - launchdek (Dashboard)
 *
 * Settings (wp_options):
 *   - launchdek_settings  (array, Settings API group: launchdek_settings_group)
 *   - launchdek_version   (string, schema version tracker)
 *
 * Core hooks (this class):
 *   - admin_menu                  → register admin pages
 *   - admin_init                  → register settings
 *   - admin_enqueue_scripts       → enqueue CSS/JS on plugin screens
 *   - plugin_action_links_{basename} → Settings shortcut on Plugins screen
 *   - plugin_row_meta                  → Docs link on Plugins screen
 *
 * REST endpoints:  none (scaffold)
 * Cron events:     none (scaffold)
 * Blocks:          none (scaffold)
 * Custom tables:   none (options API only)
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

	/**
	 * Admin handler.
	 *
	 * @var LAUNCHDEK_Admin
	 */
	protected $admin;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->admin = new LAUNCHDEK_Admin();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function run() {
		add_action( 'admin_menu', array( $this->admin, 'register_menu' ) );
		add_action( 'admin_init', array( $this->admin, 'register_settings' ) );
		add_action( 'admin_init', array( $this->admin, 'maybe_activation_redirect' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_scripts' ) );
		add_filter( 'plugin_action_links_' . LAUNCHDEK_PLUGIN_BASENAME, array( $this->admin, 'add_settings_link' ) );
		add_filter( 'plugin_row_meta', array( $this->admin, 'add_plugin_row_meta' ), 10, 2 );
	}
}
