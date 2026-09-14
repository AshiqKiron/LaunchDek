<?php
/**
 * Cached dashboard stats and connection summaries.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores dashboard aggregates and refreshes only when underlying data changes.
 */
class LAUNCHDEK_Dashboard_Cache {

	const OPTION_NAME = 'launchdek_dashboard_cache';

	const FEED_LIMIT = 15;

	/**
	 * Cached dashboard stat cards.
	 *
	 * @return array{sites_connected:int,active_checklists:int,runs_in_progress:int,completion_rate:float}
	 */
	public static function get_stats() {
		$cache = self::get_cache();

		if ( isset( $cache['stats'] ) && is_array( $cache['stats'] ) ) {
			return $cache['stats'];
		}

		return self::refresh_stats();
	}

	/**
	 * Cached connection health summary counts.
	 *
	 * @return array{healthy:int,unhealthy:int,unknown:int,total:int}
	 */
	public static function get_connection_counts() {
		$cache = self::get_cache();

		if ( isset( $cache['connections'] ) && is_array( $cache['connections'] ) ) {
			return $cache['connections'];
		}

		return self::refresh_connection_counts();
	}

	/**
	 * Recompute and store dashboard stat cards.
	 *
	 * @return array{sites_connected:int,active_checklists:int,runs_in_progress:int,completion_rate:float}
	 */
	public static function refresh_stats() {
		$stats = array(
			'sites_connected'   => LAUNCHDEK_Site_Repository::count(),
			'active_checklists' => LAUNCHDEK_Checklist_Repository::count_active(),
			'runs_in_progress'  => LAUNCHDEK_Run_Repository::count_by_status( 'running' ),
			'completion_rate'   => LAUNCHDEK_Run_Repository::completion_rate(),
		);

		$cache           = self::get_cache();
		$cache['stats']  = $stats;
		self::save_cache( $cache );

		return $stats;
	}

	/**
	 * Recompute and store connection health summary counts.
	 *
	 * @return array{healthy:int,unhealthy:int,unknown:int,total:int}
	 */
	public static function refresh_connection_counts() {
		$counts = LAUNCHDEK_Site_Repository::health_counts();

		$cache                  = self::get_cache();
		$cache['connections']   = $counts;
		self::save_cache( $cache );

		return $counts;
	}

	/**
	 * Cached dashboard live log feed entries.
	 *
	 * @param int $limit Max entries (capped at FEED_LIMIT).
	 * @return array
	 */
	public static function get_feed( $limit = self::FEED_LIMIT ) {
		$limit = min( self::FEED_LIMIT, max( 1, absint( $limit ) ) );
		$cache = self::get_cache();

		if (
			isset( $cache['feed']['entries'] )
			&& is_array( $cache['feed']['entries'] )
			&& (int) ( $cache['feed']['limit'] ?? 0 ) === $limit
		) {
			return self::normalize_feed_entries( $cache['feed']['entries'] );
		}

		return self::refresh_feed( $limit );
	}

	/**
	 * Recompute and store the dashboard live log feed.
	 *
	 * @param int $limit Max entries (capped at FEED_LIMIT).
	 * @return array
	 */
	public static function refresh_feed( $limit = self::FEED_LIMIT ) {
		$limit   = min( self::FEED_LIMIT, max( 1, absint( $limit ) ) );
		$entries = LAUNCHDEK_Audit_Log::get_feed( $limit );

		$cache                 = self::get_cache();
		$cache['feed']         = array(
			'limit'   => $limit,
			'entries' => $entries,
		);
		self::save_cache( $cache );

		return $entries;
	}

	/**
	 * Ensure cached feed entries include site labels and compact messages.
	 *
	 * @param array $entries Cached feed entries.
	 * @return array
	 */
	private static function normalize_feed_entries( $entries ) {
		return array_map(
			static function ( $entry ) {
				if ( ! is_array( $entry ) ) {
					return $entry;
				}

				if ( array_key_exists( 'show_site_label', $entry ) ) {
					return $entry;
				}

				$entry['show_site_label'] = LAUNCHDEK_Audit_Log::feed_entry_show_site_label( $entry );
				$entry['message']         = LAUNCHDEK_Audit_Log::format_feed_message( $entry, ! $entry['show_site_label'] );

				return $entry;
			},
			$entries
		);
	}

	/**
	 * Drop cached dashboard live log feed entries.
	 *
	 * @return void
	 */
	public static function invalidate_feed() {
		$cache = self::get_cache();

		if ( ! isset( $cache['feed'] ) ) {
			return;
		}

		unset( $cache['feed'] );
		self::save_cache( $cache );
	}

	/**
	 * Drop cached dashboard stat cards.
	 *
	 * @return void
	 */
	public static function invalidate_stats() {
		$cache = self::get_cache();

		if ( ! isset( $cache['stats'] ) ) {
			return;
		}

		unset( $cache['stats'] );
		self::save_cache( $cache );
	}

	/**
	 * Cached sites list for the Sites admin table.
	 *
	 * @param array $args Optional filters (tag, group_type, health).
	 * @return array
	 */
	public static function get_sites_list( $args = array() ) {
		$args = self::normalize_sites_list_args( $args );
		$key  = self::sites_list_cache_key( $args );
		$cache = self::get_cache();

		if (
			isset( $cache['sites_lists'][ $key ] )
			&& is_array( $cache['sites_lists'][ $key ] )
		) {
			return $cache['sites_lists'][ $key ];
		}

		return self::refresh_sites_list( $args );
	}

	/**
	 * Recompute and store a sites list payload.
	 *
	 * @param array $args Optional filters (tag, group_type, health).
	 * @return array
	 */
	public static function refresh_sites_list( $args = array() ) {
		$args    = self::normalize_sites_list_args( $args );
		$key     = self::sites_list_cache_key( $args );
		$entries = LAUNCHDEK_Site_Repository::all( $args );

		$cache = self::get_cache();
		if ( ! isset( $cache['sites_lists'] ) || ! is_array( $cache['sites_lists'] ) ) {
			$cache['sites_lists'] = array();
		}
		$cache['sites_lists'][ $key ] = $entries;
		self::save_cache( $cache );

		return $entries;
	}

	/**
	 * Drop cached sites list payloads.
	 *
	 * @return void
	 */
	public static function invalidate_sites_list() {
		$cache = self::get_cache();

		if ( ! isset( $cache['sites_lists'] ) ) {
			return;
		}

		unset( $cache['sites_lists'] );
		self::save_cache( $cache );
	}

	/**
	 * Drop cached connection health summary counts.
	 *
	 * @return void
	 */
	public static function invalidate_connections() {
		$cache = self::get_cache();

		if ( ! isset( $cache['connections'] ) ) {
			return;
		}

		unset( $cache['connections'] );
		self::save_cache( $cache );
	}

	/**
	 * Drop all cached dashboard aggregates.
	 *
	 * @return void
	 */
	public static function clear() {
		delete_option( self::OPTION_NAME );
	}

	/**
	 * Normalize sites list filter args for cache keys and queries.
	 *
	 * @param array $args Raw filter args.
	 * @return array{tag:string,group_type:string,health:string}
	 */
	private static function normalize_sites_list_args( $args ) {
		return array(
			'tag'        => sanitize_text_field( $args['tag'] ?? '' ),
			'group_type' => sanitize_key( $args['group_type'] ?? '' ),
			'health'     => sanitize_key( $args['health'] ?? '' ),
		);
	}

	/**
	 * Build a stable cache key for a sites list query.
	 *
	 * @param array $args Normalized filter args.
	 * @return string
	 */
	private static function sites_list_cache_key( $args ) {
		$args = self::normalize_sites_list_args( $args );

		return md5( wp_json_encode( $args ) );
	}

	/**
	 * @return array
	 */
	private static function get_cache() {
		$cache = get_option( self::OPTION_NAME, array() );

		return is_array( $cache ) ? $cache : array();
	}

	/**
	 * @param array $cache Cache payload.
	 * @return void
	 */
	private static function save_cache( $cache ) {
		if ( empty( $cache ) ) {
			self::clear();
			return;
		}

		update_option( self::OPTION_NAME, $cache, false );
	}
}
