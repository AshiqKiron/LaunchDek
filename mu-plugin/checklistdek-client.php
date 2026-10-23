<?php
/**
 * ChecklistDek client checklist panel (must-use loader).
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CHECKLISTDEK_CLIENT_PANEL_VERSION', '1.0.8' );
define( 'CHECKLISTDEK_CLIENT_PANEL_DIR', __DIR__ . '/checklistdek-client/' );
define( 'CHECKLISTDEK_CLIENT_PANEL_URL', content_url( 'mu-plugins/checklistdek-client/' ) );
define( 'CHECKLISTDEK_CLIENT_TEXT_DOMAIN', 'checklistdek' );
define( 'CHECKLISTDEK_CLIENT_REST_NAMESPACE', 'checklistdek/v1' );

require_once CHECKLISTDEK_CLIENT_PANEL_DIR . 'class-run-store.php';
require_once CHECKLISTDEK_CLIENT_PANEL_DIR . 'class-hub-client.php';
$checklistdek_client_auto_capture = CHECKLISTDEK_CLIENT_PANEL_DIR . 'class-auto-capture.php';
if ( is_readable( $checklistdek_client_auto_capture ) ) {
	require_once $checklistdek_client_auto_capture;
}
require_once CHECKLISTDEK_CLIENT_PANEL_DIR . 'class-rest-api.php';
require_once CHECKLISTDEK_CLIENT_PANEL_DIR . 'class-panel.php';

if ( class_exists( 'CHECKLISTDEK_Client_Auto_Capture' ) ) {
	CHECKLISTDEK_Client_Auto_Capture::register();
}
CHECKLISTDEK_Client_REST_API::register();
CHECKLISTDEK_Client_Panel::register();
add_action( 'admin_enqueue_scripts', array( 'CHECKLISTDEK_Client_Panel', 'enqueue_assets' ) );
add_action( 'admin_footer', array( 'CHECKLISTDEK_Client_Panel', 'render_panel' ) );
