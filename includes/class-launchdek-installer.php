<?php
/**
 * Database schema installer and upgrader.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles custom table creation via dbDelta.
 */
class LAUNCHDEK_Installer {

	/**
	 * Current database schema version.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Option key storing installed DB version.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'launchdek_db_version';

	/**
	 * Run install or upgrade if needed.
	 *
	 * @return void
	 */
	public static function maybe_install() {
		$installed = get_option( self::DB_VERSION_OPTION, '' );

		if ( version_compare( (string) $installed, self::DB_VERSION, '>=' ) ) {
			return;
		}

		self::install();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Create or update all plugin tables.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$sites           = $wpdb->prefix . 'launchdek_sites';
		$tags            = $wpdb->prefix . 'launchdek_site_tags';
		$workflows       = $wpdb->prefix . 'launchdek_workflows';
		$runs            = $wpdb->prefix . 'launchdek_runs';
		$run_steps       = $wpdb->prefix . 'launchdek_run_steps';
		$audit           = $wpdb->prefix . 'launchdek_audit_log';
		$connections     = $wpdb->prefix . 'launchdek_connection_events';

		$sql = "CREATE TABLE {$sites} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL DEFAULT '',
			url varchar(512) NOT NULL DEFAULT '',
			admin_username varchar(191) NOT NULL DEFAULT '',
			app_password_enc text NOT NULL,
			wp_version varchar(32) NOT NULL DEFAULT '',
			php_version varchar(32) NOT NULL DEFAULT '',
			health_status varchar(32) NOT NULL DEFAULT 'unknown',
			last_ping_at datetime DEFAULT NULL,
			last_error text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY health_status (health_status),
			KEY url (url(191))
		) {$charset_collate};

		CREATE TABLE {$tags} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			site_id bigint(20) unsigned NOT NULL,
			tag varchar(100) NOT NULL DEFAULT '',
			group_type varchar(50) NOT NULL DEFAULT 'general',
			PRIMARY KEY  (id),
			KEY site_id (site_id),
			KEY group_type (group_type),
			KEY tag (tag)
		) {$charset_collate};

		CREATE TABLE {$workflows} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL DEFAULT '',
			description text,
			steps_json longtext NOT NULL,
			is_template tinyint(1) NOT NULL DEFAULT 0,
			is_vault tinyint(1) NOT NULL DEFAULT 0,
			template_slug varchar(100) NOT NULL DEFAULT '',
			version varchar(20) NOT NULL DEFAULT '1.0.0',
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY is_template (is_template),
			KEY is_vault (is_vault),
			KEY template_slug (template_slug)
		) {$charset_collate};

		CREATE TABLE {$runs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			workflow_id bigint(20) unsigned NOT NULL,
			site_id bigint(20) unsigned NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'pending',
			started_by bigint(20) unsigned NOT NULL DEFAULT 0,
			started_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			completed_at datetime DEFAULT NULL,
			notes text,
			PRIMARY KEY  (id),
			KEY workflow_id (workflow_id),
			KEY site_id (site_id),
			KEY status (status),
			KEY started_at (started_at)
		) {$charset_collate};

		CREATE TABLE {$run_steps} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			run_id bigint(20) unsigned NOT NULL,
			step_index int(11) NOT NULL DEFAULT 0,
			step_id varchar(64) NOT NULL DEFAULT '',
			title varchar(255) NOT NULL DEFAULT '',
			step_type varchar(32) NOT NULL DEFAULT 'manual',
			status varchar(32) NOT NULL DEFAULT 'pending',
			response_json longtext,
			error_message text,
			manual_checked tinyint(1) NOT NULL DEFAULT 0,
			started_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY run_id (run_id),
			KEY status (status)
		) {$charset_collate};

		CREATE TABLE {$audit} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			site_id bigint(20) unsigned NOT NULL DEFAULT 0,
			run_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(100) NOT NULL DEFAULT '',
			details_json longtext,
			payload_hash varchar(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY site_id (site_id),
			KEY run_id (run_id),
			KEY action (action),
			KEY created_at (created_at)
		) {$charset_collate};

		CREATE TABLE {$connections} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			site_id bigint(20) unsigned NOT NULL DEFAULT 0,
			site_url varchar(512) NOT NULL DEFAULT '',
			status varchar(32) NOT NULL DEFAULT 'unknown',
			message text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY site_id (site_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * Drop all plugin tables on uninstall.
	 *
	 * @return void
	 */
	public static function uninstall() {
		global $wpdb;

		$tables = array(
			'launchdek_sites',
			'launchdek_site_tags',
			'launchdek_workflows',
			'launchdek_runs',
			'launchdek_run_steps',
			'launchdek_audit_log',
			'launchdek_connection_events',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		delete_option( self::DB_VERSION_OPTION );
	}
}
