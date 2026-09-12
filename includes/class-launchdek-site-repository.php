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
	public static function find( $id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', absint( $id ) ),
			ARRAY_A
		);

		return $row ? self::format( $row ) : null;
	}

	/**
	 * List sites with optional tag filter.
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
			'limit'      => 100,
			'offset'     => 0,
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = self::table();
		$tags  = self::tags_table();
		$sql   = "SELECT s.* FROM {$table} s";
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

		if ( $where ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}

		$sql .= ' ORDER BY s.name ASC LIMIT %d OFFSET %d';
		$vals[] = max( 1, absint( $args['limit'] ) );
		$vals[] = max( 0, absint( $args['offset'] ) );

		$prepared = $wpdb->prepare( $sql, $vals ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows     = $wpdb->get_results( $prepared, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $rows ) ? array_map( array( __CLASS__, 'format' ), $rows ) : array();
	}

	/**
	 * Count sites.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Create a site.
	 *
	 * @param array $data Site data.
	 * @return int|false
	 */
	public static function create( $data ) {
		global $wpdb;

		$url = esc_url_raw( untrailingslashit( $data['url'] ?? '' ) );

		$result = $wpdb->insert(
			self::table(),
			array(
				'name'             => sanitize_text_field( $data['name'] ?? $url ),
				'url'              => $url,
				'admin_username'   => sanitize_user( $data['admin_username'] ?? '' ),
				'app_password_enc' => LAUNCHDEK_Credential_Vault::encrypt( $data['app_password'] ?? '' ),
				'health_status'    => 'unknown',
				'created_at'       => current_time( 'mysql', true ),
				'updated_at'       => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		$id = (int) $wpdb->insert_id;

		if ( ! empty( $data['tags'] ) && is_array( $data['tags'] ) ) {
			self::set_tags( $id, $data['tags'] );
		}

		LAUNCHDEK_Audit_Log::log( 'site_created', array( 'site_id' => $id, 'url' => $url ), $id );

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
			$fields['url'] = esc_url_raw( untrailingslashit( $data['url'] ) );
			$format[]      = '%s';
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

		$result = $wpdb->update( self::table(), $fields, array( 'id' => absint( $id ) ), $format, array( '%d' ) );

		if ( isset( $data['tags'] ) && is_array( $data['tags'] ) ) {
			self::set_tags( $id, $data['tags'] );
		}

		if ( false !== $result ) {
			LAUNCHDEK_Audit_Log::log( 'site_updated', array( 'site_id' => $id ), $id );
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

		$wpdb->delete( self::tags_table(), array( 'site_id' => absint( $id ) ), array( '%d' ) );
		$result = $wpdb->delete( self::table(), array( 'id' => absint( $id ) ), array( '%d' ) );

		if ( $result ) {
			LAUNCHDEK_Audit_Log::log( 'site_deleted', array( 'site_id' => $id ), $id );
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
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT tag, group_type FROM ' . self::tags_table() . ' WHERE site_id = %d ORDER BY group_type, tag',
				absint( $site_id )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
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
	}

	/**
	 * Get all unique tags.
	 *
	 * @return array
	 */
	public static function get_all_tags() {
		global $wpdb;

		$rows = $wpdb->get_results(
			'SELECT DISTINCT tag, group_type FROM ' . self::tags_table() . ' ORDER BY group_type, tag', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get decrypted credentials for API use.
	 *
	 * @param int $id Site ID.
	 * @return array|null
	 */
	public static function get_credentials( $id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT url, admin_username, app_password_enc FROM ' . self::table() . ' WHERE id = %d', absint( $id ) ),
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
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function format( $row ) {
		return array(
			'id'            => (int) $row['id'],
			'name'          => $row['name'],
			'url'           => $row['url'],
			'admin_username' => $row['admin_username'],
			'wp_version'    => $row['wp_version'],
			'php_version'   => $row['php_version'],
			'health_status' => $row['health_status'],
			'last_ping_at'  => $row['last_ping_at'],
			'last_error'    => $row['last_error'],
			'tags'          => self::get_tags( (int) $row['id'] ),
			'created_at'    => $row['created_at'],
			'updated_at'    => $row['updated_at'],
		);
	}
}
