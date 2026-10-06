<?php
/**
 * Build edition helpers (full plugin vs Community / WordPress.org zip).
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'LAUNCHDEK_BUILD' ) ) {
	define( 'LAUNCHDEK_BUILD', 'full' );
}

/**
 * Whether this install is the Community (free) distribution build.
 *
 * @return bool
 */
function launchdek_is_community_build() {
	return 'community' === LAUNCHDEK_BUILD;
}

/**
 * Whether Pro-only PHP modules are bundled and may be loaded.
 *
 * @return bool
 */
function launchdek_includes_pro_package() {
	return ! launchdek_is_community_build();
}

/**
 * Absolute path to the webhook dispatcher class file for this build.
 *
 * @return string
 */
function launchdek_get_webhook_dispatcher_file() {
	if ( launchdek_includes_pro_package() ) {
		return LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-webhook-dispatcher.php';
	}

	return LAUNCHDEK_PLUGIN_DIR . 'includes/community/class-launchdek-webhook-dispatcher.php';
}

/**
 * Absolute path to the drift cron class file for this build.
 *
 * @return string
 */
function launchdek_get_drift_cron_file() {
	if ( launchdek_includes_pro_package() ) {
		return LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-drift-cron.php';
	}

	return LAUNCHDEK_PLUGIN_DIR . 'includes/community/class-launchdek-drift-cron.php';
}

/**
 * Load Pro-only class files when present in this build.
 *
 * @return void
 */
function launchdek_require_pro_modules() {
	if ( ! launchdek_includes_pro_package() ) {
		return;
	}

	require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-auto-capture.php';
	require_once LAUNCHDEK_PLUGIN_DIR . 'includes/class-launchdek-email-notifier.php';
}
