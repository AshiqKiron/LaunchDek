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
	const DB_VERSION = '1.5.0';

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

		self::migrate_from_previous( (string) $installed );
		self::install();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Upgrade schema and settings from older DB versions.
	 *
	 * @param string $installed Previously installed DB version.
	 * @return void
	 */
	protected static function migrate_from_previous( $installed ) {
		if ( '' === $installed || version_compare( $installed, '1.1.0', '<' ) ) {
			self::migrate_workflows_to_checklists();
		}

		if ( '' === $installed || version_compare( $installed, '1.2.0', '<' ) ) {
			self::migrate_client_agent_columns();
		}

		if ( '' === $installed || version_compare( $installed, '1.3.0', '<' ) ) {
			self::migrate_step_notes_column();
		}

		if ( '' === $installed || version_compare( $installed, '1.4.0', '<' ) ) {
			self::migrate_run_archive_column();
		}

		if ( '' === $installed || version_compare( $installed, '1.5.0', '<' ) ) {
			self::migrate_site_integration_columns();
		}
	}

	/**
	 * Add integration source and external ID columns for connector sync.
	 *
	 * @return void
	 */
	protected static function migrate_site_integration_columns() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$source_col = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				LAUNCHDEK_Site_Repository::table(),
				'integration_source'
			)
		);
		if ( empty( $source_col ) ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->prepare(
					'ALTER TABLE %i ADD `integration_source` varchar(32) NOT NULL DEFAULT %s AFTER `client_agent`', // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
					LAUNCHDEK_Site_Repository::table(),
					''
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$external_col = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				LAUNCHDEK_Site_Repository::table(),
				'external_id'
			)
		);
		if ( empty( $external_col ) ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->prepare(
					'ALTER TABLE %i ADD `external_id` varchar(64) NOT NULL DEFAULT %s AFTER `integration_source`', // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
					LAUNCHDEK_Site_Repository::table(),
					''
				)
			);
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->prepare(
					'ALTER TABLE %i ADD KEY integration_source_external (integration_source, external_id)', // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
					LAUNCHDEK_Site_Repository::table()
				)
			);
		}
	}

	/**
	 * Add archived flag for checklist run history.
	 *
	 * @return void
	 */
	protected static function migrate_run_archive_column() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$archived_col = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				LAUNCHDEK_Run_Repository::table(),
				'is_archived'
			)
		);
		if ( empty( $archived_col ) ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->prepare(
					'ALTER TABLE %i ADD `is_archived` tinyint(1) NOT NULL DEFAULT 0 AFTER `client_run_token`', // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
					LAUNCHDEK_Run_Repository::table()
				)
			);
		}
	}

	/**
	 * Add per-step notes column for client evidence.
	 *
	 * @return void
	 */
	protected static function migrate_step_notes_column() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$notes_col = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				LAUNCHDEK_Run_Repository::steps_table(),
				'notes_json'
			)
		);
		if ( empty( $notes_col ) ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->prepare(
					'ALTER TABLE %i ADD `notes_json` longtext AFTER `response_json`', // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
					LAUNCHDEK_Run_Repository::steps_table()
				)
			);
		}
	}

	/**
	 * Add client agent and run token columns.
	 *
	 * @return void
	 */
	protected static function migrate_client_agent_columns() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$run_token = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				LAUNCHDEK_Run_Repository::table(),
				'client_run_token'
			)
		);
		if ( empty( $run_token ) ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->prepare(
					'ALTER TABLE %i ADD `client_run_token` varchar(64) NOT NULL DEFAULT %s AFTER `notes`', // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
					LAUNCHDEK_Run_Repository::table(),
					''
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$client_agent = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				LAUNCHDEK_Site_Repository::table(),
				'client_agent'
			)
		);
		if ( empty( $client_agent ) ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->prepare(
					'ALTER TABLE %i ADD `client_agent` tinyint(1) NOT NULL DEFAULT 0 AFTER `last_error`', // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
					LAUNCHDEK_Site_Repository::table()
				)
			);
		}
	}

	/**
	 * Rename workflow tables/columns and migrate capability keys.
	 *
	 * @return void
	 */
	protected static function migrate_workflows_to_checklists() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$old_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				self::legacy_workflows_table()
			)
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$new_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				LAUNCHDEK_Checklist_Repository::table()
			)
		);

		if ( self::legacy_workflows_table() === $old_exists && LAUNCHDEK_Checklist_Repository::table() !== $new_exists ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->prepare(
					'RENAME TABLE %i TO %i', // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
					self::legacy_workflows_table(),
					LAUNCHDEK_Checklist_Repository::table()
				)
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$column = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				LAUNCHDEK_Run_Repository::table(),
				'workflow_id'
			)
		);

		if ( ! empty( $column ) ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->prepare(
					'ALTER TABLE %i CHANGE `workflow_id` `checklist_id` bigint(20) unsigned NOT NULL', // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
					LAUNCHDEK_Run_Repository::table()
				)
			);
		}

		LAUNCHDEK_Capabilities::migrate_workflow_caps();
	}

	/**
	 * Legacy workflows table name (pre-1.1.0 migrations only).
	 *
	 * @return string
	 */
	private static function legacy_workflows_table() {
		global $wpdb;

		return $wpdb->prefix . 'launchdek_workflows';
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
		$checklists      = $wpdb->prefix . 'launchdek_checklists';
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
			client_agent tinyint(1) NOT NULL DEFAULT 0,
			integration_source varchar(32) NOT NULL DEFAULT '',
			external_id varchar(64) NOT NULL DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY health_status (health_status),
			KEY url (url(191)),
			KEY integration_source_external (integration_source, external_id)
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

		CREATE TABLE {$checklists} (
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
			checklist_id bigint(20) unsigned NOT NULL,
			site_id bigint(20) unsigned NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'pending',
			started_by bigint(20) unsigned NOT NULL DEFAULT 0,
			started_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			completed_at datetime DEFAULT NULL,
			notes text,
			client_run_token varchar(64) NOT NULL DEFAULT '',
			is_archived tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY checklist_id (checklist_id),
			KEY site_id (site_id),
			KEY status (status),
			KEY started_at (started_at),
			KEY is_archived (is_archived)
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
			notes_json longtext,
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
			'launchdek_checklists',
			'launchdek_runs',
			'launchdek_run_steps',
			'launchdek_audit_log',
			'launchdek_connection_events',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
				$wpdb->prepare(
					'DROP TABLE IF EXISTS %i', // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
					$wpdb->prefix . $table
				)
			);
		}

		delete_option( self::DB_VERSION_OPTION );
	}
}
