<?php
/**
 * Remote site persistence layer.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site repository.
 */
class LAUNCHDEK_Site_Repository {

	/**
	 * Default sites list page size.
	 *
	 * @var int
	 */
	const LIST_DEFAULT_LIMIT = 500;

	/**
	 * Maximum sites list page size.
	 *
	 * @var int
	 */
	const LIST_MAX_LIMIT = 1000;

	/**
	 * Quick-launch picker cap.
	 *
	 * @var int
	 */
	const PICKER_LIMIT = 500;

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'launchdek_sites';
	}

	/**
	 * Get tags table name.
	 *
	 * @return string
	 */
	public static function tags_table() {
		global $wpdb;
		return $wpdb->prefix . 'launchdek_site_tags';
	}

	/**
	 * Find site by ID.
	 *
	 * @param int $id Site ID.
	 * @return array|null
	 */
	/**
	 * Normalize a site URL for storage and lookup.
	 *
	 * @param string $url Site URL.
	 * @return string
	 */
	public static function normalize_url( $url ) {
		return esc_url_raw( untrailingslashit( (string) $url ) );
	}

	/**
	 * Find site by normalized URL.
	 *
	 * @param string $url Site URL.
	 * @return array|null
	 */
	public static function find_by_url( $url ) {
		global $wpdb;

		$url = self::normalize_url( $url );
		if ( '' === $url ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table name; URL via prepare().
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE url = %s',
				self::table(),
				$url
			),
			ARRAY_A
		);

		return $row ? self::format( $row ) : null;
	}

	/**
	 * Find site by integration source and external ID.
	 *
	 * @param string $source     Integration slug.
	 * @param string $external_id Platform site ID.
	 * @return array|null
	 */
	public static function find_by_external( $source, $external_id ) {
		global $wpdb;

		$source      = sanitize_key( $source );
		$external_id = sanitize_text_field( (string) $external_id );

		if ( '' === $source || '' === $external_id ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table name; integration fields via prepare().
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE integration_source = %s AND external_id = %s',
				self::table(),
				$source,
				$external_id
			),
			ARRAY_A
		);

		return $row ? self::format( $row ) : null;
	}

	/**
	 * Whether a stored site has usable Application Password credentials.
	 *
	 * @param int $id Site ID.
	 * @return bool
	 */
	public static function has_credentials( $id ) {
		$credentials = self::get_credentials( $id );

		return is_array( $credentials ) && '' !== trim( (string) ( $credentials['app_password'] ?? '' ) );
	}

	/**
	 * Create or update a site imported from an integration connector.
	 *
	 * @param string $source      Integration slug.
	 * @param string $external_id Platform site ID.
	 * @param array  $platform_row Raw platform fields.
	 * @param bool   $dry_run     Preview only.
	 * @return array
	 */
	public static function upsert_from_integration( $source, $external_id, $platform_row, $dry_run = false ) {
		$mapped = LAUNCHDEK_Telemetry_Mapper::map( $source, $platform_row );
		$url    = self::normalize_url( $mapped['url'] ?? ( $platform_row['site_url'] ?? ( $platform_row['url'] ?? '' ) ) );

		if ( '' === $url ) {
			return array(
				'action'  => 'skipped',
				'reason'  => 'missing_url',
				'site_id' => 0,
			);
		}

		$existing = self::find_by_external( $source, $external_id );
		if ( ! $existing ) {
			$existing = self::find_by_url( $url );
		}

		if ( $dry_run ) {
			return array(
				'action'  => $existing ? 'updated' : 'created',
				'site_id' => $existing ? (int) $existing['id'] : 0,
				'url'     => $url,
			);
		}

		if ( $existing ) {
			$update = array(
				'integration_source' => sanitize_key( $source ),
				'external_id'        => sanitize_text_field( (string) $external_id ),
				'url'                => $url,
			);

			if ( ! empty( $mapped['name'] ) ) {
				$update['name'] = $mapped['name'];
			}
			if ( ! empty( $mapped['wp_version'] ) ) {
				$update['wp_version'] = $mapped['wp_version'];
			}
			if ( ! empty( $mapped['php_version'] ) ) {
				$update['php_version'] = $mapped['php_version'];
			}

			self::update( (int) $existing['id'], $update );
			self::ensure_integration_tag( (int) $existing['id'], $source );

			return array(
				'action'  => 'updated',
				'site_id' => (int) $existing['id'],
				'url'     => $url,
			);
		}

		if ( ! LAUNCHDEK_Licensing::can_add_site() ) {
			return array(
				'action'  => 'skipped',
				'reason'  => 'site_limit',
				'site_id' => 0,
				'url'     => $url,
			);
		}

		$create = array(
			'name'               => ! empty( $mapped['name'] ) ? $mapped['name'] : $url,
			'url'                => $url,
			'admin_username'     => '',
			'app_password'       => '',
			'wp_version'         => $mapped['wp_version'] ?? '',
			'php_version'        => $mapped['php_version'] ?? '',
			'integration_source' => sanitize_key( $source ),
			'external_id'        => sanitize_text_field( (string) $external_id ),
			'tags'               => array(
				array(
					'tag'        => $source,
					'group_type' => 'integration',
				),
			),
		);

		$id = self::create( $create );

		if ( ! $id ) {
			return array(
				'action'  => 'skipped',
				'reason'  => 'create_failed',
				'site_id' => 0,
				'url'     => $url,
			);
		}

		return array(
			'action'  => 'created',
			'site_id' => (int) $id,
			'url'     => $url,
		);
	}

	/**
	 * Ensure an integration tag exists for a site.
	 *
	 * @param int    $site_id Site ID.
	 * @param string $source  Integration slug.
	 * @return void
	 */
	public static function ensure_integration_tag( $site_id, $source ) {
		$source = sanitize_key( $source );
		$tags   = self::get_tags( $site_id );

		foreach ( $tags as $tag ) {
			if ( sanitize_key( $tag['tag'] ?? '' ) === $source && 'integration' === sanitize_key( $tag['group_type'] ?? '' ) ) {
				return;
			}
		}

		$tags[] = array(
			'tag'        => $source,
			'group_type' => 'integration',
		);

		self::set_tags( $site_id, $tags );
	}

	/**
	 * List sites imported from a specific integration.
	 *
	 * @param string $source Integration slug.
	 * @return array
	 */
	public static function list_by_integration( $source ) {
		global $wpdb;

		$source = sanitize_key( $source );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table name; source via prepare().
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE integration_source = %s ORDER BY name ASC',
				self::table(),
				$source
			),
			ARRAY_A
		);

		return is_array( $rows ) ? array_map( array( __CLASS__, 'format' ), $rows ) : array();
	}

	/**
	 * Count LaunchDek sites grouped by integration_source.
	 *
	 * @return array<string,int> Integration slug => site count.
	 */
	public static function count_by_integration_sources() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table name; static aggregate query (no user input).
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT integration_source, COUNT(*) AS site_count FROM %i WHERE integration_source IS NOT NULL AND integration_source <> '' GROUP BY integration_source",
				self::table()
			),
			ARRAY_A
		);

		$counts = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$source = sanitize_key( (string) ( $row['integration_source'] ?? '' ) );
				if ( '' !== $source ) {
					$counts[ $source ] = (int) ( $row['site_count'] ?? 0 );
				}
			}
		}

		return $counts;
	}

	public static function find( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table name; ID via prepare().
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				self::table(),
				absint( $id )
			),
			ARRAY_A
		);

		return $row ? self::format( $row ) : null;
	}

	/**
	 * List sites with optional filters.
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public static function all( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'tag'        => '',
			'group_type' => '',
			'health'     => '',
			'search'     => '',
			'limit'      => self::LIST_DEFAULT_LIMIT,
			'offset'     => 0,
		);

		$args  = wp_parse_args( $args, $defaults );
		$parts = self::build_list_query_parts( $args );
		$sql   = 'SELECT ' . self::list_select_columns() . ' ' . $parts['from_where'] . ' ORDER BY s.name ASC LIMIT %d OFFSET %d';
		$vals  = array_merge(
			$parts['vals'],
			array(
				min( self::LIST_MAX_LIMIT, max( 1, absint( $args['limit'] ) ) ),
				max( 0, absint( $args['offset'] ) ),
			)
		);

		$prepared = $wpdb->prepare( $sql, $vals ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- FROM/WHERE built in build_list_query_parts(); filter values in $vals.
		$rows     = $wpdb->get_results( $prepared, ARRAY_A );

		return is_array( $rows ) ? self::format_list( $rows ) : array();
	}

	/**
	 * Lightweight id/name pairs for dashboard quick-launch pickers.
	 *
	 * @return array<int, array{id:int,name:string}>
	 */
	public static function picker_list() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table name; static picker query.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, name FROM %i ORDER BY name ASC LIMIT %d',
				self::table(),
				self::PICKER_LIMIT
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static function ( $row ) {
				return array(
					'id'   => (int) $row['id'],
					'name' => (string) $row['name'],
				);
			},
			$rows
		);
	}

	/**
	 * Count sites matching list filters.
	 *
	 * @param array $args Query args (tag, group_type, health, search).
	 * @return int
	 */
	public static function count_filtered( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'tag'        => '',
			'group_type' => '',
			'health'     => '',
			'search'     => '',
		);

		$args  = wp_parse_args( $args, $defaults );
		$parts = self::build_list_query_parts( $args );
		$sql   = 'SELECT COUNT(DISTINCT s.id) ' . $parts['from_where'];

		if ( $parts['vals'] ) {
			$sql = $wpdb->prepare( $sql, $parts['vals'] ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- FROM/WHERE from build_list_query_parts(); filter values in $parts['vals'].
		return (int) $wpdb->get_var( $sql );
	}

	/**
	 * Count sites grouped by health status.
	 *
	 * @return array{healthy:int,unhealthy:int,unknown:int,total:int}
	 */
	public static function health_counts() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table name; static aggregate query.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT health_status, COUNT(*) AS count FROM %i GROUP BY health_status',
				self::table()
			),
			ARRAY_A
		);

		$counts = array(
			'healthy'   => 0,
			'unhealthy' => 0,
			'unknown'   => 0,
		);

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$status = sanitize_key( $row['health_status'] ?? '' );
				if ( isset( $counts[ $status ] ) ) {
					$counts[ $status ] = (int) $row['count'];
				}
			}
		}

		$counts['total'] = $counts['healthy'] + $counts['unhealthy'] + $counts['unknown'];

		return $counts;
	}

	/**
	 * Build shared FROM/WHERE clause for site list queries.
	 *
	 * @param array $args Query args.
	 * @return array{from_where:string,vals:array}
	 */
	private static function build_list_query_parts( $args ) {
		global $wpdb;

		$table = self::table();
		$tags  = self::tags_table();
		$sql   = "FROM {$table} s";
		$where = array();
		$vals  = array();

		if ( $args['tag'] || $args['group_type'] ) {
			$sql .= " INNER JOIN {$tags} t ON t.site_id = s.id";
			if ( $args['tag'] ) {
				$where[] = 't.tag = %s';
				$vals[]  = sanitize_text_field( $args['tag'] );
			}
			if ( $args['group_type'] ) {
				$where[] = 't.group_type = %s';
				$vals[]  = sanitize_key( $args['group_type'] );
			}
		}

		if ( $args['health'] ) {
			$where[] = 's.health_status = %s';
			$vals[]  = sanitize_key( $args['health'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$like    = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
			$where[] = '(s.name LIKE %s OR s.url LIKE %s)';
			$vals[]  = $like;
			$vals[]  = $like;
		}

		if ( $where ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}

		return array(
			'from_where' => $sql,
			'vals'       => $vals,
		);
	}

	/**
	 * Count sites.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table name; static count query.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i',
				self::table()
			)
		);
	}

	/**
	 * Create a site.
	 *
	 * @param array $data Site data.
	 * @return int|false
	 */
	public static function create( $data ) {
		global $wpdb;

		$url = self::normalize_url( $data['url'] ?? '' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->insert(
			self::table(),
			array(
				'name'               => sanitize_text_field( $data['name'] ?? $url ),
				'url'                => $url,
				'admin_username'     => sanitize_user( $data['admin_username'] ?? '' ),
				'app_password_enc'   => LAUNCHDEK_Credential_Vault::encrypt( $data['app_password'] ?? '' ),
				'wp_version'         => sanitize_text_field( $data['wp_version'] ?? '' ),
				'php_version'        => sanitize_text_field( $data['php_version'] ?? '' ),
				'health_status'      => 'unknown',
				'client_agent'       => 0,
				'integration_source' => sanitize_key( $data['integration_source'] ?? '' ),
				'external_id'        => sanitize_text_field( $data['external_id'] ?? '' ),
				'created_at'         => current_time( 'mysql', true ),
				'updated_at'         => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		$id = (int) $wpdb->insert_id;

		if ( ! empty( $data['tags'] ) && is_array( $data['tags'] ) ) {
			self::set_tags( $id, $data['tags'] );
		}

		LAUNCHDEK_Audit_Log::log( 'site_created', array( 'site_id' => $id, 'url' => $url ), $id );
		LAUNCHDEK_Dashboard_Cache::invalidate_stats();
		LAUNCHDEK_Dashboard_Cache::invalidate_connections();
		LAUNCHDEK_Dashboard_Cache::invalidate_sites_list();
		LAUNCHDEK_Dashboard_Cache::invalidate_quick_launch_picker();

		return $id;
	}

	/**
	 * Update a site.
	 *
	 * @param int   $id   Site ID.
	 * @param array $data Update data.
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		$fields = array(
			'updated_at' => current_time( 'mysql', true ),
		);
		$format = array( '%s' );

		if ( isset( $data['name'] ) ) {
			$fields['name'] = sanitize_text_field( $data['name'] );
			$format[]       = '%s';
		}

		if ( isset( $data['url'] ) ) {
			$fields['url'] = self::normalize_url( $data['url'] );
			$format[]      = '%s';
		}

		if ( isset( $data['integration_source'] ) ) {
			$fields['integration_source'] = sanitize_key( $data['integration_source'] );
			$format[]                     = '%s';
		}

		if ( isset( $data['external_id'] ) ) {
			$fields['external_id'] = sanitize_text_field( (string) $data['external_id'] );
			$format[]              = '%s';
		}

		if ( isset( $data['admin_username'] ) ) {
			$fields['admin_username'] = sanitize_user( $data['admin_username'] );
			$format[]                 = '%s';
		}

		if ( ! empty( $data['app_password'] ) ) {
			$fields['app_password_enc'] = LAUNCHDEK_Credential_Vault::encrypt( $data['app_password'] );
			$format[]                   = '%s';
		}

		if ( isset( $data['wp_version'] ) ) {
			$fields['wp_version'] = sanitize_text_field( $data['wp_version'] );
			$format[]             = '%s';
		}

		if ( isset( $data['php_version'] ) ) {
			$fields['php_version'] = sanitize_text_field( $data['php_version'] );
			$format[]              = '%s';
		}

		if ( isset( $data['health_status'] ) ) {
			$fields['health_status'] = sanitize_key( $data['health_status'] );
			$format[]                = '%s';
		}

		if ( isset( $data['last_ping_at'] ) ) {
			$fields['last_ping_at'] = $data['last_ping_at'];
			$format[]               = '%s';
		}

		if ( array_key_exists( 'last_error', $data ) ) {
			$fields['last_error'] = sanitize_textarea_field( (string) $data['last_error'] );
			$format[]             = '%s';
		}

		if ( isset( $data['client_agent'] ) ) {
			$fields['client_agent'] = ! empty( $data['client_agent'] ) ? 1 : 0;
			$format[]               = '%d';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update( self::table(), $fields, array( 'id' => absint( $id ) ), $format, array( '%d' ) );

		if ( isset( $data['tags'] ) && is_array( $data['tags'] ) ) {
			self::set_tags( $id, $data['tags'] );
		}

		if ( false !== $result ) {
			LAUNCHDEK_Audit_Log::log( 'site_updated', array( 'site_id' => $id ), $id );
			LAUNCHDEK_Dashboard_Cache::invalidate_sites_list();
			LAUNCHDEK_Dashboard_Cache::invalidate_quick_launch_picker();

			if ( isset( $data['health_status'] ) ) {
				LAUNCHDEK_Dashboard_Cache::invalidate_connections();
			}
		}

		return false !== $result;
	}

	/**
	 * Delete a site.
	 *
	 * @param int $id Site ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id   = absint( $id );
		$site = self::find( $id );

		if ( ! $site ) {
			return false;
		}

		LAUNCHDEK_Run_Repository::delete_for_site( $id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( self::tags_table(), array( 'site_id' => $id ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$wpdb->prefix . 'launchdek_connection_events',
			array( 'site_id' => $id ),
			array( '%d' )
		);
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );

		if ( $result ) {
			LAUNCHDEK_Audit_Log::log(
				'site_deleted',
				array(
					'site_id' => $id,
					'name'    => $site ? ( $site['name'] ?? '' ) : '',
					'url'     => $site ? ( $site['url'] ?? '' ) : '',
				),
				$id
			);
			LAUNCHDEK_Dashboard_Cache::invalidate_stats();
			LAUNCHDEK_Dashboard_Cache::invalidate_connections();
			LAUNCHDEK_Dashboard_Cache::invalidate_sites_list();
			LAUNCHDEK_Dashboard_Cache::invalidate_quick_launch_picker();
		}

		return (bool) $result;
	}

	/**
	 * Get tags for a site.
	 *
	 * @param int $site_id Site ID.
	 * @return array
	 */
	public static function get_tags( $site_id ) {
		$map = self::get_tags_map( array( absint( $site_id ) ) );

		return $map[ absint( $site_id ) ] ?? array();
	}

	/**
	 * Batch-load tags for many sites (avoids N+1 queries on list screens).
	 *
	 * @param int[] $site_ids Site IDs.
	 * @return array<int, array<int, array{tag:string, group_type:string}>>
	 */
	public static function get_tags_map( array $site_ids ) {
		global $wpdb;

		$site_ids = array_values( array_filter( array_map( 'absint', $site_ids ) ) );
		$map      = array();

		if ( empty( $site_ids ) ) {
			return $map;
		}

		foreach ( $site_ids as $site_id ) {
			$map[ $site_id ] = array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $site_ids ), '%d' ) );
		$prepared     = $wpdb->prepare(
			'SELECT site_id, tag, group_type FROM ' . self::tags_table() . ' WHERE site_id IN (' . $placeholders . ') ORDER BY site_id ASC, group_type ASC, tag ASC', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table from tags_table(); site_id IN list uses %d placeholders only.
			...$site_ids
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results( $prepared, ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return $map;
		}

		foreach ( $rows as $row ) {
			$site_id = (int) $row['site_id'];
			if ( ! isset( $map[ $site_id ] ) ) {
				$map[ $site_id ] = array();
			}
			$map[ $site_id ][] = array(
				'tag'        => (string) $row['tag'],
				'group_type' => (string) $row['group_type'],
			);
		}

		return $map;
	}

	/**
	 * Column list for site list queries (excludes encrypted credential blob).
	 *
	 * @return string
	 */
	private static function list_select_columns() {
		return "s.id, s.name, s.url, s.admin_username, s.wp_version, s.php_version, s.health_status, s.last_ping_at, s.last_error, s.client_agent, s.integration_source, s.external_id, s.created_at, s.updated_at, (CASE WHEN TRIM(s.app_password_enc) <> '' THEN 1 ELSE 0 END) AS has_credentials_flag";
	}

	/**
	 * Whether a raw row has stored credentials without decrypting the vault.
	 *
	 * @param array $row Raw DB row.
	 * @return bool
	 */
	public static function row_has_stored_credentials( array $row ) {
		if ( array_key_exists( 'has_credentials_flag', $row ) ) {
			return ! empty( $row['has_credentials_flag'] );
		}

		return '' !== trim( (string) ( $row['app_password_enc'] ?? '' ) );
	}

	/**
	 * Format many site rows with batched tag lookups.
	 *
	 * @param array $rows Raw rows.
	 * @return array
	 */
	public static function format_list( array $rows ) {
		if ( empty( $rows ) ) {
			return array();
		}

		$tags_map = self::get_tags_map( wp_list_pluck( $rows, 'id' ) );
		$out      = array();

		foreach ( $rows as $row ) {
			$site_id = (int) $row['id'];
			$out[]   = self::format(
				$row,
				array(
					'tags'            => $tags_map[ $site_id ] ?? array(),
					'list_credentials' => true,
				)
			);
		}

		return $out;
	}

	/**
	 * Replace all tags for a site.
	 *
	 * @param int   $site_id Site ID.
	 * @param array $tags    Tag definitions.
	 * @return void
	 */
	public static function set_tags( $site_id, $tags ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( self::tags_table(), array( 'site_id' => absint( $site_id ) ), array( '%d' ) );

		foreach ( $tags as $tag ) {
			if ( is_string( $tag ) ) {
				$tag = array(
					'tag'        => $tag,
					'group_type' => 'general',
				);
			}

			if ( empty( $tag['tag'] ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert(
				self::tags_table(),
				array(
					'site_id'    => absint( $site_id ),
					'tag'        => sanitize_text_field( $tag['tag'] ),
					'group_type' => sanitize_key( $tag['group_type'] ?? 'general' ),
				),
				array( '%d', '%s', '%s' )
			);
		}

		LAUNCHDEK_Dashboard_Cache::invalidate_sites_list();
		LAUNCHDEK_Dashboard_Cache::invalidate_quick_launch_picker();
	}

	/**
	 * Get all unique tags.
	 *
	 * @return array
	 */
	public static function get_all_tags() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table name; static distinct query.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT DISTINCT tag, group_type FROM %i ORDER BY group_type, tag',
				self::tags_table()
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Distinct tag group_type values in use across sites.
	 *
	 * @return string[]
	 */
	public static function get_distinct_group_types() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table name; static distinct query.
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT group_type FROM %i ORDER BY group_type',
				self::tags_table()
			)
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$slugs = array();
		foreach ( $rows as $slug ) {
			$slug = sanitize_key( (string) $slug );
			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}

		return $slugs;
	}

	/**
	 * Get decrypted credentials for API use.
	 *
	 * @param int $id Site ID.
	 * @return array|null
	 */
	public static function get_credentials( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Plugin-owned table name; ID via prepare().
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT url, admin_username, app_password_enc FROM %i WHERE id = %d',
				self::table(),
				absint( $id )
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		return array(
			'url'           => $row['url'],
			'admin_username' => $row['admin_username'],
			'app_password'  => LAUNCHDEK_Credential_Vault::decrypt( $row['app_password_enc'] ),
		);
	}

	/**
	 * Format site row for output (no password).
	 *
	 * @param array $row     Raw row.
	 * @param array $context Optional preloaded tags or list credential flag.
	 * @return array
	 */
	public static function format( $row, $context = array() ) {
		$site_id = (int) $row['id'];

		if ( isset( $context['tags'] ) && is_array( $context['tags'] ) ) {
			$tags = $context['tags'];
		} else {
			$tags = self::get_tags( $site_id );
		}

		if ( ! empty( $context['list_credentials'] ) ) {
			$has_credentials = self::row_has_stored_credentials( $row );
		} else {
			$has_credentials = '' !== trim( LAUNCHDEK_Credential_Vault::decrypt( $row['app_password_enc'] ?? '' ) );
		}

		return array(
			'id'            => $site_id,
			'name'          => $row['name'],
			'url'           => $row['url'],
			'admin_username' => $row['admin_username'],
			'wp_version'    => $row['wp_version'],
			'php_version'   => $row['php_version'],
			'health_status' => $row['health_status'],
			'last_ping_at'  => $row['last_ping_at'],
			'last_error'    => $row['last_error'],
			'client_agent'        => ! empty( $row['client_agent'] ),
			'integration_source'  => (string) ( $row['integration_source'] ?? '' ),
			'external_id'         => (string) ( $row['external_id'] ?? '' ),
			'has_credentials'     => $has_credentials,
			'tags'                => $tags,
			'created_at'    => $row['created_at'],
			'updated_at'    => $row['updated_at'],
		);
	}
}
