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
		return defined( 'MAINWP_PLUGIN_URL' ) || class_exists( 'MainWP\Dashboard\MainWP' );
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
			$websites = \MainWP\Dashboard\MainWP_DB::instance()->get_websites_for_current_user();

			if ( is_array( $websites ) ) {
				$rows = array();

				foreach ( $websites as $website ) {
					$row = $this->normalize_website_row( $website );

					if ( $row ) {
						$rows[] = $row;
					}
				}

				return $rows;
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
		$sync_supported = $this->supports_site_sync();
		$synced_count   = $sync_supported ? count( LAUNCHDEK_Site_Repository::list_by_integration( 'mainwp' ) ) : 0;

		return array(
			'description'    => __( 'Import child sites from MainWP and deploy the LaunchDek client checklist panel through the MainWP connection.', LAUNCHDEK_TEXT_DOMAIN ),
			'docs_url'       => 'https://mainwp.com/kb/',
			'supports_sync'  => $sync_supported,
			'synced_sites'   => $synced_count,
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
	 * Normalize a MainWP website object into telemetry field keys.
	 *
	 * @param object $website MainWP website object.
	 * @return array|null
	 */
	protected function normalize_website_row( $website ) {
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

		$from_sql = "FROM {$wp_table} wp";
		if ( $sync_exists ) {
			$from_sql .= " INNER JOIN {$sync_table} wp_sync ON wp.id = wp_sync.wpid";
		}

		$sql = "SELECT wp.id, wp.url, wp.name{$version_select} {$from_sql} WHERE (wp.sync_errors IS NULL OR wp.sync_errors = '') ORDER BY wp.name ASC";

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql, ARRAY_A );

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
		global $wpdb;

		$wp_table = $wpdb->prefix . 'mainwp_wp';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wp_table ) ) === $wp_table;
	}
}
