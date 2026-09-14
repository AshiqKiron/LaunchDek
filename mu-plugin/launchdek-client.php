<?php
/**
 * LaunchDek client checklist panel (must-use loader).
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LAUNCHDEK_CLIENT_PANEL_VERSION', '1.0.0' );
define( 'LAUNCHDEK_CLIENT_PANEL_DIR', __DIR__ . '/launchdek-client/' );
define( 'LAUNCHDEK_CLIENT_PANEL_URL', content_url( 'mu-plugins/launchdek-client/' ) );
define( 'LAUNCHDEK_CLIENT_TEXT_DOMAIN', 'launchdek' );
define( 'LAUNCHDEK_CLIENT_REST_NAMESPACE', 'launchdek/v1' );

require_once LAUNCHDEK_CLIENT_PANEL_DIR . 'class-run-store.php';
require_once LAUNCHDEK_CLIENT_PANEL_DIR . 'class-hub-client.php';
require_once LAUNCHDEK_CLIENT_PANEL_DIR . 'class-auto-capture.php';
require_once LAUNCHDEK_CLIENT_PANEL_DIR . 'class-rest-api.php';
require_once LAUNCHDEK_CLIENT_PANEL_DIR . 'class-panel.php';

add_action(
	'init',
	static function () {
		load_plugin_textdomain(
			LAUNCHDEK_CLIENT_TEXT_DOMAIN,
			false,
			'mu-plugins/launchdek-client/languages'
		);
	}
);

LAUNCHDEK_Client_Auto_Capture::register();
LAUNCHDEK_Client_REST_API::register();
add_action( 'admin_enqueue_scripts', array( 'LAUNCHDEK_Client_Panel', 'enqueue_assets' ) );
add_action( 'admin_footer', array( 'LAUNCHDEK_Client_Panel', 'render_panel' ) );
