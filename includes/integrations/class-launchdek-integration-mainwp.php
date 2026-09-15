<?php
/**
 * MainWP integration adapter.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MainWP integration.
 */
class LAUNCHDEK_Integration_MainWP implements LAUNCHDEK_Integration_Interface {

	public function get_slug() {
		return 'mainwp';
	}

	public function get_name() {
		return __( 'MainWP Connector', LAUNCHDEK_TEXT_DOMAIN );
	}

	public function is_available() {
		if ( defined( 'MAINWP_PLUGIN_URL' ) ) {
			return true;
		}

		return class_exists( 'MainWP\Dashboard\MainWP', false );
	}

	public function supports_site_sync() {
		return $this->is_available() && $this->mainwp_table_exists();
	}

	public function fetch_platform_sites() {
		if ( ! $this->supports_site_sync() ) {
			return new WP_Error(
				'launchdek_mainwp_unavailable',
				__( 'MainWP is not installed or its site table is unavailable.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => 400 )
			);
		}

		if ( class_exists( 'MainWP\Dashboard\MainWP_DB' ) ) {
			$websites = \MainWP\Dashboard\MainWP_DB::instance()->get_websites_for_current_user(
				array(
					'format'      => 'array',
					'select_data' => array( 'id', 'url', 'name', 'sync_errors', 'phpversion', 'wpversion' ),
				)
			);

			if ( is_array( $websites ) && ! empty( $websites ) ) {
				$rows = $this->normalize_website_rows( $websites );

				if ( ! empty( $rows ) ) {
					return $rows;
				}
			}
		}

		return $this->fetch_platform_sites_from_table();
	}

	public function push_agent( $site_ids = array() ) {
		if ( ! $this->is_available() ) {
			return array(
				'error' => __( 'MainWP is not installed.', LAUNCHDEK_TEXT_DOMAIN ),
			);
		}

		$targets = $this->resolve_push_targets( $site_ids );
		$results = array(
			'sites'   => array(),
			'summary' => array(
				'success' => 0,
				'failed'  => 0,
				'skipped' => 0,
			),
		);

		foreach ( $targets as $site ) {
			$site_id = (int) ( $site['id'] ?? 0 );
			$result  = $this->push_agent_to_site( $site );

			$results['sites'][ $site_id ] = $result;

			if ( ! empty( $result['success'] ) ) {
				++$results['summary']['success'];
			} elseif ( ! empty( $result['skipped'] ) ) {
				++$results['summary']['skipped'];
			} else {
				++$results['summary']['failed'];
			}
		}

		if ( empty( $targets ) ) {
			$results['message'] = __( 'No MainWP-linked LaunchDek sites were found to push. Sync sites from MainWP first.', LAUNCHDEK_TEXT_DOMAIN );
		}

		/**
		 * Fires when LaunchDek pushes agent via MainWP.
		 *
		 * @param array $site_ids LaunchDek site IDs.
		 * @param array $results  Results array passed by reference.
		 */
		do_action( 'launchdek_mainwp_push_agent', $site_ids, $results );

		LAUNCHDEK_Audit_Log::log(
			'integration_push',
			array(
				'integration' => 'mainwp',
				'summary'       => $results['summary'],
			)
		);

		return $results;
	}

	public function get_status() {
		return array(
			'description'   => __( 'Import child sites from MainWP and deploy the LaunchDek client checklist panel through the MainWP connection.', LAUNCHDEK_TEXT_DOMAIN ),
			'docs_url'      => 'https://mainwp.com/kb/',
			'supports_sync' => $this->supports_site_sync(),
		);
	}

	/**
	 * Push the client panel to a single LaunchDek site.
	 *
	 * @param array $site LaunchDek site row.
	 * @return array
	 */
	protected function push_agent_to_site( $site ) {
		$site_id = (int) ( $site['id'] ?? 0 );

		if ( $site_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid site.', LAUNCHDEK_TEXT_DOMAIN ),
			);
		}

		if ( LAUNCHDEK_Site_Repository::has_credentials( $site_id ) ) {
			$panel = LAUNCHDEK_Mu_Plugin_Installer::ensure_installed( $site_id );

			if ( is_wp_error( $panel ) ) {
				return array(
					'success' => false,
					'message' => $panel->get_error_message(),
				);
			}

			return array_merge(
				array( 'success' => true ),
				is_array( $panel ) ? $panel : array()
			);
		}

		$external_id = (string) ( $site['external_id'] ?? '' );

		if ( '' === $external_id ) {
			return array(
				'skipped' => true,
				'message' => __( 'Add Application Password credentials on Sites or sync this site from MainWP before pushing the client panel.', LAUNCHDEK_TEXT_DOMAIN ),
			);
		}

		$panel = LAUNCHDEK_MainWP_Agent::install_panel( absint( $external_id ) );

		if ( is_wp_error( $panel ) ) {
			return array(
				'success' => false,
				'message' => $panel->get_error_message(),
			);
		}

		LAUNCHDEK_Site_Repository::update(
			$site_id,
			array(
				'client_agent' => 1,
			)
		);

		return array_merge(
			array( 'success' => true ),
			is_array( $panel ) ? $panel : array()
		);
	}

	/**
	 * Resolve LaunchDek sites to push.
	 *
	 * @param array $site_ids Optional LaunchDek site IDs.
	 * @return array
	 */
	protected function resolve_push_targets( $site_ids ) {
		$site_ids = array_filter( array_map( 'absint', (array) $site_ids ) );

		if ( ! empty( $site_ids ) ) {
			$targets = array();

			foreach ( $site_ids as $site_id ) {
				$site = LAUNCHDEK_Site_Repository::find( $site_id );

				if ( $site && 'mainwp' === ( $site['integration_source'] ?? '' ) ) {
					$targets[] = $site;
				}
			}

			return $targets;
		}

		return LAUNCHDEK_Site_Repository::list_by_integration( 'mainwp' );
	}

	/**
	 * Build sync diagnostics for empty MainWP inventories.
	 *
	 * @return array
	 */
	public function get_sync_diagnostics() {
		global $wpdb;

		$diagnostics = array(
			'mainwp_detected'     => $this->is_available(),
			'mainwp_table_exists' => $this->mainwp_table_exists(),
			'supports_sync'       => $this->supports_site_sync(),
		);

		if ( ! $diagnostics['mainwp_table_exists'] ) {
			$diagnostics['hint'] = __( 'MainWP must be installed and activated on this same WordPress site as LaunchDek.', LAUNCHDEK_TEXT_DOMAIN );
			return $diagnostics;
		}

		$wp_table = $wpdb->prefix . 'mainwp_wp';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$diagnostics['mainwp_total_sites'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wp_table}" );

		$sync_table = $wpdb->prefix . 'mainwp_wp_sync';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sync_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sync_table ) ) === $sync_table;

		if ( $sync_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$diagnostics['mainwp_connected_sites'] = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wp_table} wp LEFT JOIN {$sync_table} wp_sync ON wp.id = wp_sync.wpid WHERE (wp_sync.sync_errors IS NULL OR wp_sync.sync_errors = '')"
			);

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			$diagnostics['mainwp_disconnected_sites'] = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wp_table} wp INNER JOIN {$sync_table} wp_sync ON wp.id = wp_sync.wpid WHERE wp_sync.sync_errors IS NOT NULL AND wp_sync.sync_errors <> ''"
			);
		} else {
			$diagnostics['mainwp_connected_sites']    = $diagnostics['mainwp_total_sites'];
			$diagnostics['mainwp_disconnected_sites'] = 0;
		}

		if ( 0 === $diagnostics['mainwp_total_sites'] ) {
			$diagnostics['hint'] = __( 'Add your child site in MainWP first (MainWP → Sites → Add New Site), then return here and click Sync Sites.', LAUNCHDEK_TEXT_DOMAIN );
		} elseif ( 0 === $diagnostics['mainwp_connected_sites'] ) {
			$diagnostics['hint'] = __( 'MainWP child sites exist but all are disconnected. Reconnect them in MainWP, then sync again.', LAUNCHDEK_TEXT_DOMAIN );
		} else {
			$diagnostics['hint'] = __( 'MainWP has connected child sites. Open Setup and click Sync Sites to import them into LaunchDek → Sites.', LAUNCHDEK_TEXT_DOMAIN );
		}

		return $diagnostics;
	}

	/**
	 * Normalize multiple MainWP website rows.
	 *
	 * @param array $websites MainWP website objects.
	 * @return array
	 */
	protected function normalize_website_rows( $websites ) {
		$rows = array();

		foreach ( $websites as $website ) {
			$row = $this->normalize_website_row( $website );

			if ( $row ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}

	/**
	 * Normalize a MainWP website object into telemetry field keys.
	 *
	 * @param object $website MainWP website object.
	 * @return array|null
	 */
	protected function normalize_website_row( $website ) {
		if ( is_array( $website ) ) {
			$website = (object) $website;
		}

		if ( ! is_object( $website ) ) {
			return null;
		}

		$url = LAUNCHDEK_Site_Repository::normalize_url( $website->url ?? '' );

		if ( '' === $url ) {
			return null;
		}

		if ( ! empty( $website->sync_errors ) ) {
			return null;
		}

		return array(
			'external_id' => (string) ( $website->id ?? '' ),
			'site_url'    => $url,
			'site_name'   => sanitize_text_field( (string) ( $website->name ?? $url ) ),
			'wp_version'  => sanitize_text_field( (string) ( $website->wpversion ?? ( $website->wp_version ?? '' ) ) ),
			'php_version' => sanitize_text_field( (string) ( $website->phpversion ?? ( $website->php_version ?? '' ) ) ),
		);
	}

	/**
	 * Fallback query against the MainWP sites table.
	 *
	 * @return array|WP_Error
	 */
	protected function fetch_platform_sites_from_table() {
		global $wpdb;

		$wp_table    = $wpdb->prefix . 'mainwp_wp';
		$opts_table  = $wpdb->prefix . 'mainwp_wp_options';
		$sync_table  = $wpdb->prefix . 'mainwp_wp_sync';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wp_table ) ) !== $wp_table ) {
			return new WP_Error(
				'launchdek_mainwp_table_missing',
				__( 'MainWP site table was not found on this install.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => 500 )
			);
		}

		$opts_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $opts_table ) ) === $opts_table; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sync_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sync_table ) ) === $sync_table; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$version_select = '';
		if ( $opts_exists ) {
			$version_select = ",
			(SELECT phpversion.value FROM {$opts_table} phpversion WHERE phpversion.wpid = wp.id AND phpversion.name = 'phpversion' LIMIT 1) AS phpversion,
			(SELECT wpversion.value FROM {$opts_table} wpversion WHERE wpversion.wpid = wp.id AND wpversion.name = 'wpversion' LIMIT 1) AS wpversion";
		}

		$from_sql  = "FROM {$wp_table} wp";
		$where_sql = '1=1';

		if ( $sync_exists ) {
			$from_sql .= " LEFT JOIN {$sync_table} wp_sync ON wp.id = wp_sync.wpid";
			$where_sql = '(wp_sync.sync_errors IS NULL OR wp_sync.sync_errors = \'\')';
		}

		$sql = "SELECT wp.id, wp.url, wp.name{$version_select} {$from_sql} WHERE {$where_sql} ORDER BY wp.name ASC";

		$suppress_errors = $wpdb->suppress_errors( true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		$wpdb->suppress_errors( $suppress_errors );

		if ( $wpdb->last_error ) {
			return new WP_Error(
				'launchdek_mainwp_query_failed',
				__( 'Could not read MainWP child sites. Verify MainWP is installed and its site tables are intact.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => 500 )
			);
		}

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$platform_rows = array();

		foreach ( $rows as $row ) {
			$url = LAUNCHDEK_Site_Repository::normalize_url( $row['url'] ?? '' );

			if ( '' === $url ) {
				continue;
			}

			$platform_rows[] = array(
				'external_id' => (string) ( $row['id'] ?? '' ),
				'site_url'    => $url,
				'site_name'   => sanitize_text_field( (string) ( $row['name'] ?? $url ) ),
				'wp_version'  => sanitize_text_field( (string) ( $row['wpversion'] ?? '' ) ),
				'php_version' => sanitize_text_field( (string) ( $row['phpversion'] ?? '' ) ),
			);
		}

		return $platform_rows;
	}

	/**
	 * Whether the MainWP sites table exists.
	 *
	 * @return bool
	 */
	protected function mainwp_table_exists() {
		static $exists = null;

		if ( null !== $exists ) {
			return $exists;
		}

		global $wpdb;

		$wp_table = $wpdb->prefix . 'mainwp_wp';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wp_table ) ) === $wp_table;

		return $exists;
	}
}
