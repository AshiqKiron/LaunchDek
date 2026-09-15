<?php
/**
 * Immutable audit log writer and reader.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audit log service.
 */
class LAUNCHDEK_Audit_Log {

	/**
	 * Default page size for activity log queries.
	 *
	 * @var int
	 */
	const LIST_DEFAULT_LIMIT = 50;

	/**
	 * Maximum page size for activity log queries.
	 *
	 * @var int
	 */
	const LIST_MAX_LIMIT = 100;

	/**
	 * Append an audit entry (immutable — no update/delete methods).
	 *
	 * @param string $action       Action identifier.
	 * @param array  $details      Structured details.
	 * @param int    $site_id      Related site ID.
	 * @param int    $run_id       Related run ID.
	 * @return int|false Insert ID or false.
	 */
	public static function log( $action, $details = array(), $site_id = 0, $run_id = 0 ) {
		global $wpdb;

		$table   = $wpdb->prefix . 'launchdek_audit_log';
		$payload = wp_json_encode( $details );

		$result = $wpdb->insert(
			$table,
			array(
				'user_id'      => get_current_user_id(),
				'site_id'      => absint( $site_id ),
				'run_id'       => absint( $run_id ),
				'action'       => sanitize_key( $action ),
				'details_json' => $payload,
				'payload_hash' => hash( 'sha256', (string) $payload ),
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		LAUNCHDEK_Dashboard_Cache::invalidate_feed();

		return (int) $wpdb->insert_id;
	}

	/**
	 * Find a single audit entry by ID.
	 *
	 * @param int  $id              Entry ID.
	 * @param bool $include_details Include decoded details payload.
	 * @return array|null
	 */
	public static function find( $id, $include_details = true ) {
		$rows = self::query(
			array(
				'id'              => absint( $id ),
				'limit'           => 1,
				'include_details' => $include_details,
			)
		);

		return ! empty( $rows ) ? $rows[0] : null;
	}

	/**
	 * Query audit entries with filters.
	 *
	 * @param array $args Query arguments.
	 * @return array Flat list of entries, or paginated shape when paginate is true.
	 */
	public static function query( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'id'              => 0,
			'user_id'         => 0,
			'site_id'         => 0,
			'run_id'          => 0,
			'action'          => '',
			'search'          => '',
			'status'          => '',
			'date_from'       => '',
			'date_to'         => '',
			'limit'           => self::LIST_DEFAULT_LIMIT,
			'offset'          => 0,
			'order'           => 'DESC',
			'include_details' => true,
			'paginate'        => false,
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = $wpdb->prefix . 'launchdek_audit_log';
		$where = array( '1=1' );
		$vals  = array();

		if ( $args['id'] ) {
			$where[] = 'id = %d';
			$vals[]  = absint( $args['id'] );
		}

		if ( $args['user_id'] ) {
			$where[] = 'user_id = %d';
			$vals[]  = absint( $args['user_id'] );
		}

		if ( $args['site_id'] ) {
			$where[] = 'site_id = %d';
			$vals[]  = absint( $args['site_id'] );
		}

		if ( $args['run_id'] ) {
			$where[] = 'run_id = %d';
			$vals[]  = absint( $args['run_id'] );
		}

		if ( $args['action'] ) {
			$where[] = 'action = %s';
			$vals[]  = sanitize_key( $args['action'] );
		}

		if ( $args['search'] ) {
			$where[] = 'details_json LIKE %s';
			$vals[]  = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		if ( $args['date_from'] && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $args['date_from'] ) ) {
			$where[] = 'created_at >= %s';
			$vals[]  = $args['date_from'] . ' 00:00:00';
		}

		if ( $args['date_to'] && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $args['date_to'] ) ) {
			$where[] = 'created_at <= %s';
			$vals[]  = $args['date_to'] . ' 23:59:59';
		}

		if ( $args['status'] ) {
			$status_clause = self::status_where_clause( $args['status'] );
			if ( $status_clause ) {
				$where[] = $status_clause;
			}
		}

		$order        = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit        = max( 1, min( self::LIST_MAX_LIMIT, absint( $args['limit'] ) ) );
		$offset       = max( 0, absint( $args['offset'] ) );
		$paginate     = ! empty( $args['paginate'] );
		$fetch_limit  = $paginate ? $limit + 1 : $limit;
		$where_sql    = implode( ' AND ', $where );
		$include_details = ! empty( $args['include_details'] );

		$sql = "SELECT id, user_id, site_id, run_id, action, details_json, payload_hash, created_at FROM {$table} WHERE {$where_sql} ORDER BY created_at {$order} LIMIT %d OFFSET %d";
		$vals[] = $fetch_limit;
		$vals[] = $offset;

		if ( ! empty( $vals ) ) {
			$sql = $wpdb->prepare( $sql, $vals ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$has_more = false;
		if ( $paginate && count( $rows ) > $limit ) {
			$has_more = true;
			$rows     = array_slice( $rows, 0, $limit );
		}

		$context   = self::build_query_context( $rows );
		$formatted = array();
		foreach ( $rows as $row ) {
			$formatted[] = self::format_row( $row, $context, $include_details );
		}

		if ( $paginate ) {
			return array(
				'logs'     => $formatted,
				'has_more' => $has_more,
				'offset'   => $offset,
				'limit'    => $limit,
			);
		}

		return $formatted;
	}

	/**
	 * Build lookup maps for batch row formatting.
	 *
	 * @param array $rows Raw audit rows.
	 * @return array{users:array,sites:array,runs:array}
	 */
	private static function build_query_context( array $rows ) {
		$user_ids = array();
		$site_ids = array();
		$run_ids  = array();

		foreach ( $rows as $row ) {
			if ( ! empty( $row['user_id'] ) ) {
				$user_ids[] = (int) $row['user_id'];
			}
			if ( ! empty( $row['site_id'] ) ) {
				$site_ids[] = (int) $row['site_id'];
			}
			if ( ! empty( $row['run_id'] ) ) {
				$run_ids[] = (int) $row['run_id'];
			}
		}

		$unique_run_ids  = array_values( array_unique( $run_ids ) );
		$runs            = self::load_runs_map( $unique_run_ids );
		$linked_site_ids = array();

		foreach ( $runs as $run ) {
			if ( ! empty( $run['site_id'] ) ) {
				$linked_site_ids[] = (int) $run['site_id'];
			}
		}

		return array(
			'users' => self::load_users_map( array_values( array_unique( $user_ids ) ) ),
			'sites' => self::load_sites_map(
				array_values(
					array_unique(
						array_merge( $site_ids, $linked_site_ids )
					)
				)
			),
			'runs'  => $runs,
		);
	}

	/**
	 * Batch-load user display names.
	 *
	 * @param array $user_ids User IDs.
	 * @return array<int, string>
	 */
	private static function load_users_map( array $user_ids ) {
		$map = array();
		if ( empty( $user_ids ) ) {
			return $map;
		}

		$users = get_users(
			array(
				'include' => $user_ids,
				'fields'  => 'id=>name',
			)
		);

		if ( ! is_array( $users ) ) {
			return $map;
		}

		foreach ( $users as $user_id => $display_name ) {
			$map[ (int) $user_id ] = (string) $display_name;
		}

		return $map;
	}

	/**
	 * Batch-load site names.
	 *
	 * @param array $site_ids Site IDs.
	 * @return array<int, array{name:string,url:string}>
	 */
	private static function load_sites_map( array $site_ids ) {
		global $wpdb;

		$map = array();
		if ( empty( $site_ids ) ) {
			return $map;
		}

		$table        = $wpdb->prefix . 'launchdek_sites';
		$placeholders = implode( ',', array_fill( 0, count( $site_ids ), '%d' ) );
		$sql          = "SELECT id, name, url FROM {$table} WHERE id IN ({$placeholders})";
		$rows         = $wpdb->get_results( $wpdb->prepare( $sql, $site_ids ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $rows ) ) {
			return $map;
		}

		foreach ( $rows as $row ) {
			$map[ (int) $row['id'] ] = array(
				'name' => (string) $row['name'],
				'url'  => (string) $row['url'],
			);
		}

		return $map;
	}

	/**
	 * Batch-load lightweight run summaries for message formatting.
	 *
	 * @param array $run_ids Run IDs.
	 * @return array<int, array{status:string,checklist_title:string,site_name:string,site_id:int}>
	 */
	private static function load_runs_map( array $run_ids ) {
		global $wpdb;

		$map = array();
		if ( empty( $run_ids ) ) {
			return $map;
		}

		$runs_table       = $wpdb->prefix . 'launchdek_runs';
		$checklists_table = $wpdb->prefix . 'launchdek_checklists';
		$sites_table      = $wpdb->prefix . 'launchdek_sites';
		$placeholders     = implode( ',', array_fill( 0, count( $run_ids ), '%d' ) );
		$sql              = "SELECT r.id, r.status, r.site_id, c.title AS checklist_title, s.name AS site_name
			FROM {$runs_table} r
			LEFT JOIN {$checklists_table} c ON c.id = r.checklist_id
			LEFT JOIN {$sites_table} s ON s.id = r.site_id
			WHERE r.id IN ({$placeholders})";
		$rows             = $wpdb->get_results( $wpdb->prepare( $sql, $run_ids ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $rows ) ) {
			return $map;
		}

		foreach ( $rows as $row ) {
			$map[ (int) $row['id'] ] = array(
				'status'          => (string) $row['status'],
				'checklist_title' => (string) $row['checklist_title'],
				'site_name'       => (string) $row['site_name'],
				'site_id'         => (int) $row['site_id'],
			);
		}

		return $map;
	}

	/**
	 * Get recent entries for dashboard feed.
	 *
	 * @param int $limit Number of entries.
	 * @return array
	 */
	public static function get_feed( $limit = 15 ) {
		$entries = self::query( array( 'limit' => $limit ) );

		return array_map(
			function ( $entry ) {
				$entry['show_site_label'] = self::feed_entry_show_site_label( $entry );
				$entry['message']         = self::format_feed_message( $entry, ! $entry['show_site_label'] );
				return $entry;
			},
			$entries
		);
	}

	/**
	 * Whether a dashboard feed entry should render a separate site name label.
	 *
	 * @param array $entry Formatted audit entry.
	 * @return bool
	 */
	public static function feed_entry_show_site_label( $entry ) {
		if ( empty( $entry['site_name'] ) ) {
			return false;
		}

		$site_scoped_actions = array(
			'run_started',
			'run_status_changed',
			'site_created',
			'site_updated',
			'site_deleted',
			'connection_test',
			'client_step_completed',
			'client_step_note_added',
			'client_run_pushed',
			'drift_verified',
			'manual_step_completed',
			'api_step_executed',
		);

		if ( in_array( $entry['action'], $site_scoped_actions, true ) ) {
			return true;
		}

		return ! empty( $entry['site_id'] ) || ! empty( $entry['run_id'] );
	}

	/**
	 * Build a human-readable message for the dashboard log feed.
	 *
	 * @param array $entry     Formatted audit entry.
	 * @param bool  $embed_site Include the site name in the message text.
	 * @return string
	 */
	public static function format_feed_message( $entry, $embed_site = true ) {
		$details = is_array( $entry['details'] ) ? $entry['details'] : array();
		$site    = self::resolve_site_name( (int) $entry['site_id'], $entry['run_id'], $details );

		switch ( $entry['action'] ) {
			case 'run_started':
				$checklist_title = $details['checklist'] ?? $details['workflow'] ?? __( 'Checklist', LAUNCHDEK_TEXT_DOMAIN );

				if ( ! $embed_site ) {
					return sprintf(
						/* translators: %s: checklist title */
						__( "Checklist '%s' started", LAUNCHDEK_TEXT_DOMAIN ),
						$checklist_title
					);
				}

				return sprintf(
					/* translators: 1: checklist title, 2: site name */
					__( "Checklist '%1\$s' started on %2\$s", LAUNCHDEK_TEXT_DOMAIN ),
					$checklist_title,
					$site
				);

			case 'run_status_changed':
				$status = $details['status'] ?? '';
				$run    = self::resolve_run_summary( $entry );

				if ( $run && 'completed' === $status ) {
					$title = $run['checklist_title'] ?: __( 'Checklist', LAUNCHDEK_TEXT_DOMAIN );

					if ( ! $embed_site ) {
						return sprintf(
							/* translators: %s: checklist title */
							__( "Checklist '%s' completed", LAUNCHDEK_TEXT_DOMAIN ),
							$title
						);
					}

					return sprintf(
						/* translators: 1: workflow title, 2: site name */
						__( "Checklist '%1\$s' completed on %2\$s", LAUNCHDEK_TEXT_DOMAIN ),
						$title,
						$run['site_name'] ?: $site
					);
				}

				if ( $run && 'failed' === $status ) {
					$title = $run['checklist_title'] ?: __( 'Checklist', LAUNCHDEK_TEXT_DOMAIN );

					if ( ! $embed_site ) {
						return sprintf(
							/* translators: %s: checklist title */
							__( "Checklist '%s' failed", LAUNCHDEK_TEXT_DOMAIN ),
							$title
						);
					}

					return sprintf(
						/* translators: 1: workflow title, 2: site name */
						__( "Checklist '%1\$s' failed on %2\$s", LAUNCHDEK_TEXT_DOMAIN ),
						$title,
						$run['site_name'] ?: $site
					);
				}

				if ( ! $embed_site ) {
					return sprintf(
						/* translators: %s: status */
						__( 'Run status changed to %s', LAUNCHDEK_TEXT_DOMAIN ),
						$status
					);
				}

				return sprintf(
					/* translators: 1: status, 2: site name */
					__( 'Run status changed to %1$s on %2$s', LAUNCHDEK_TEXT_DOMAIN ),
					$status,
					$site
				);

			case 'site_created':
				if ( ! $embed_site ) {
					return __( 'Site registered', LAUNCHDEK_TEXT_DOMAIN );
				}

				return sprintf(
					/* translators: %s: site name */
					__( 'Site %s registered', LAUNCHDEK_TEXT_DOMAIN ),
					$site
				);

			case 'site_updated':
				if ( ! $embed_site ) {
					return __( 'Site updated', LAUNCHDEK_TEXT_DOMAIN );
				}

				return sprintf(
					/* translators: %s: site name */
					__( 'Site %s updated', LAUNCHDEK_TEXT_DOMAIN ),
					$site
				);

			case 'site_deleted':
				if ( ! $embed_site ) {
					return __( 'Site removed', LAUNCHDEK_TEXT_DOMAIN );
				}

				return sprintf(
					/* translators: %s: site name */
					__( 'Site %s removed', LAUNCHDEK_TEXT_DOMAIN ),
					$site
				);

			case 'checklist_created':
			case 'workflow_created':
				return sprintf(
					/* translators: %s: checklist title */
					__( "Checklist '%s' created", LAUNCHDEK_TEXT_DOMAIN ),
					$details['title'] ?? __( 'Checklist', LAUNCHDEK_TEXT_DOMAIN )
				);

			case 'integration_sync':
				$summary = is_array( $details['summary'] ?? null ) ? $details['summary'] : array();
				$integration = $details['integration'] ?? __( 'Connector', LAUNCHDEK_TEXT_DOMAIN );

				return sprintf(
					/* translators: 1: connector slug, 2: created count, 3: updated count */
					__( '%1$s sync: %2$s created, %3$s updated', LAUNCHDEK_TEXT_DOMAIN ),
					$integration,
					(int) ( $summary['created'] ?? 0 ),
					(int) ( $summary['updated'] ?? 0 )
				);

			case 'integration_push':
				$summary = is_array( $details['summary'] ?? null ) ? $details['summary'] : array();
				$integration = $details['integration'] ?? __( 'Connector', LAUNCHDEK_TEXT_DOMAIN );

				return sprintf(
					/* translators: 1: connector slug, 2: success count, 3: failed count */
					__( '%1$s client panel push: %2$s succeeded, %3$s failed', LAUNCHDEK_TEXT_DOMAIN ),
					$integration,
					(int) ( $summary['success'] ?? 0 ),
					(int) ( $summary['failed'] ?? 0 )
				);

			case 'connection_test':
				$result_message = $details['message'] ?? __( 'Completed', LAUNCHDEK_TEXT_DOMAIN );

				if ( ! $embed_site ) {
					return sprintf(
						/* translators: %s: result message */
						__( 'Connection test: %s', LAUNCHDEK_TEXT_DOMAIN ),
						$result_message
					);
				}

				return sprintf(
					/* translators: 1: site name, 2: result message */
					__( 'Connection test on %1$s: %2$s', LAUNCHDEK_TEXT_DOMAIN ),
					$site,
					$result_message
				);

			case 'client_step_completed':
				$client_user = $details['client_user'] ?? __( 'Client user', LAUNCHDEK_TEXT_DOMAIN );
				$step_title  = $details['step_title'] ?? __( 'step', LAUNCHDEK_TEXT_DOMAIN );

				if ( ! $embed_site ) {
					return sprintf(
						/* translators: 1: client user, 2: step title */
						__( '%1$s completed "%2$s"', LAUNCHDEK_TEXT_DOMAIN ),
						$client_user,
						$step_title
					);
				}

				return sprintf(
					/* translators: 1: client user, 2: step title, 3: site name */
					__( '%1$s completed "%2$s" on %3$s', LAUNCHDEK_TEXT_DOMAIN ),
					$client_user,
					$step_title,
					$site
				);

			case 'client_step_note_added':
				$preview     = $details['note_preview'] ?? '';
				$client_user = $details['client_user'] ?? __( 'Client user', LAUNCHDEK_TEXT_DOMAIN );
				$step_title  = $details['step_title'] ?? __( 'step', LAUNCHDEK_TEXT_DOMAIN );

				if ( $preview ) {
					if ( ! $embed_site ) {
						return sprintf(
							/* translators: 1: client user, 2: step title, 3: note preview */
							__( '%1$s added a note on "%2$s": %3$s', LAUNCHDEK_TEXT_DOMAIN ),
							$client_user,
							$step_title,
							$preview
						);
					}

					return sprintf(
						/* translators: 1: client user, 2: step title, 3: site name, 4: note preview */
						__( '%1$s added a note on "%2$s" (%3$s): %4$s', LAUNCHDEK_TEXT_DOMAIN ),
						$client_user,
						$step_title,
						$site,
						$preview
					);
				}

				if ( ! $embed_site ) {
					return sprintf(
						/* translators: 1: client user, 2: step title */
						__( '%1$s added a note on "%2$s"', LAUNCHDEK_TEXT_DOMAIN ),
						$client_user,
						$step_title
					);
				}

				return sprintf(
					/* translators: 1: client user, 2: step title, 3: site name */
					__( '%1$s added a note on "%2$s" (%3$s)', LAUNCHDEK_TEXT_DOMAIN ),
					$client_user,
					$step_title,
					$site
				);

			case 'client_run_pushed':
				$checklist_title = $details['checklist'] ?? __( 'Checklist', LAUNCHDEK_TEXT_DOMAIN );

				if ( ! $embed_site ) {
					return sprintf(
						/* translators: %s: checklist title */
						__( "Checklist '%s' pushed to client panel", LAUNCHDEK_TEXT_DOMAIN ),
						$checklist_title
					);
				}

				return sprintf(
					/* translators: 1: checklist title, 2: site name */
					__( "Checklist '%1\$s' pushed to %2\$s", LAUNCHDEK_TEXT_DOMAIN ),
					$checklist_title,
					$site
				);

			case 'drift_verified':
				$count = (int) ( $details['count'] ?? 0 );

				if ( $count > 0 ) {
					if ( ! $embed_site ) {
						return sprintf(
							/* translators: %d: drift count */
							__( '%d drift issue(s) detected', LAUNCHDEK_TEXT_DOMAIN ),
							$count
						);
					}

					return sprintf(
						/* translators: 1: drift count, 2: site name */
						__( '%1$d drift issue(s) detected on %2$s', LAUNCHDEK_TEXT_DOMAIN ),
						$count,
						$site
					);
				}

				if ( ! $embed_site ) {
					return __( 'Drift check passed', LAUNCHDEK_TEXT_DOMAIN );
				}

				return sprintf(
					/* translators: %s: site name */
					__( 'Drift check passed on %s', LAUNCHDEK_TEXT_DOMAIN ),
					$site
				);

			case 'manual_step_completed':
				if ( ! $embed_site ) {
					return __( 'Manual step completed', LAUNCHDEK_TEXT_DOMAIN );
				}

				return sprintf(
					/* translators: %s: site name */
					__( 'Manual step completed on %s', LAUNCHDEK_TEXT_DOMAIN ),
					$site
				);

			case 'api_step_executed':
				$method = $details['method'] ?? 'GET';
				$route  = $details['route'] ?? '';

				if ( ! $embed_site ) {
					return sprintf(
						/* translators: 1: HTTP method, 2: route */
						__( '%1$s %2$s executed', LAUNCHDEK_TEXT_DOMAIN ),
						$method,
						$route
					);
				}

				return sprintf(
					/* translators: 1: HTTP method, 2: route, 3: site name */
					__( '%1$s %2$s executed on %3$s', LAUNCHDEK_TEXT_DOMAIN ),
					$method,
					$route,
					$site
				);

			default:
				return str_replace( '_', ' ', $entry['action'] );
		}
	}

	/**
	 * Resolve lightweight run context for feed formatting.
	 *
	 * @param array $entry Formatted audit entry.
	 * @return array|null
	 */
	private static function resolve_run_summary( $entry ) {
		if ( ! empty( $entry['run_summary'] ) && is_array( $entry['run_summary'] ) ) {
			return $entry['run_summary'];
		}

		if ( empty( $entry['run_id'] ) ) {
			return null;
		}

		$run = LAUNCHDEK_Run_Repository::find( (int) $entry['run_id'] );
		if ( ! $run ) {
			return null;
		}

		return array(
			'status'          => (string) ( $run['status'] ?? '' ),
			'checklist_title' => (string) ( $run['checklist_title'] ?? '' ),
			'site_name'       => (string) ( $run['site_name'] ?? '' ),
			'site_id'         => (int) ( $run['site_id'] ?? 0 ),
		);
	}

	/**
	 * Resolve a site display name from site or run context.
	 *
	 * @param int   $site_id Site ID.
	 * @param int   $run_id  Run ID.
	 * @param array $details Audit details fallback.
	 * @param array $context Optional lookup maps.
	 * @return string
	 */
	private static function resolve_site_name( $site_id, $run_id = 0, $details = array(), $context = array() ) {
		if ( $site_id && ! empty( $context['sites'][ $site_id ] ) ) {
			$site = $context['sites'][ $site_id ];
			return $site['name'] ?: $site['url'];
		}

		if ( $site_id ) {
			$site = LAUNCHDEK_Site_Repository::find( $site_id );
			if ( $site ) {
				return $site['name'] ?: $site['url'];
			}
		}

		if ( $run_id && ! empty( $context['runs'][ $run_id ]['site_name'] ) ) {
			return (string) $context['runs'][ $run_id ]['site_name'];
		}

		if ( $run_id && ! empty( $context['runs'][ $run_id ]['site_id'] ) ) {
			$linked_site_id = (int) $context['runs'][ $run_id ]['site_id'];
			if ( ! empty( $context['sites'][ $linked_site_id ] ) ) {
				$site = $context['sites'][ $linked_site_id ];
				return $site['name'] ?: $site['url'];
			}
		}

		if ( $run_id ) {
			$run = LAUNCHDEK_Run_Repository::find( (int) $run_id );
			if ( $run && ! empty( $run['site_name'] ) ) {
				return $run['site_name'];
			}
			if ( $run && ! empty( $run['site_id'] ) ) {
				$site = LAUNCHDEK_Site_Repository::find( (int) $run['site_id'] );
				if ( $site ) {
					return $site['name'] ?: $site['url'];
				}
			}
		}

		if ( is_array( $details ) ) {
			if ( ! empty( $details['name'] ) ) {
				return sanitize_text_field( $details['name'] );
			}
			if ( ! empty( $details['site_name'] ) ) {
				return sanitize_text_field( $details['site_name'] );
			}
			if ( ! empty( $details['url'] ) ) {
				return esc_url_raw( $details['url'] );
			}
		}

		return __( 'Unknown site', LAUNCHDEK_TEXT_DOMAIN );
	}

	/**
	 * Get filter metadata for the audit log UI.
	 *
	 * @return array
	 */
	public static function get_filter_meta() {
		global $wpdb;

		$table = $wpdb->prefix . 'launchdek_audit_log';
		$rows  = $wpdb->get_results( "SELECT DISTINCT user_id FROM {$table} WHERE user_id > 0 ORDER BY user_id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$users = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$user = get_userdata( (int) $row['user_id'] );
				if ( $user ) {
					$users[] = array(
						'id'   => (int) $user->ID,
						'name' => $user->display_name,
					);
				}
			}
		}

		return array(
			'users' => $users,
		);
	}

	/**
	 * Build a SQL fragment for outcome-based status filtering.
	 *
	 * @param string $status Status slug.
	 * @return string
	 */
	private static function status_where_clause( $status ) {
		switch ( sanitize_key( $status ) ) {
			case 'success':
				return "(action IN ('run_started', 'manual_step_completed', 'client_step_completed', 'client_step_note_added', 'site_created', 'site_updated', 'checklist_created', 'checklist_updated', 'workflow_created', 'workflow_updated') OR (action = 'run_status_changed' AND details_json LIKE '%\"status\":\"completed\"%') OR (action = 'connection_test' AND details_json LIKE '%\"success\":true%') OR (action = 'drift_verified' AND details_json LIKE '%\"count\":0%'))";

			case 'failed':
				return "(action LIKE '%failed%' OR (action = 'run_status_changed' AND details_json LIKE '%\"status\":\"failed\"%') OR (action = 'connection_test' AND details_json LIKE '%\"success\":false%') OR (action = 'drift_verified' AND details_json LIKE '%\"status\":\"error\"%'))";

			case 'warning':
				return "(action = 'drift_verified' AND details_json NOT LIKE '%\"count\":0%' AND details_json LIKE '%\"drifts\":[%')";

			default:
				return '';
		}
	}

	/**
	 * Format audit details for display.
	 *
	 * @param string $action  Action slug.
	 * @param array  $details Structured details.
	 * @return string
	 */
	public static function format_details_summary( $action, $details ) {
		if ( ! is_array( $details ) ) {
			return '';
		}

		switch ( $action ) {
			case 'run_started':
				$title = $details['checklist'] ?? $details['workflow'] ?? '';
				$id    = isset( $details['checklist_id'] ) ? absint( $details['checklist_id'] ) : 0;
				if ( $title && $id ) {
					return sprintf(
						/* translators: 1: checklist title, 2: checklist ID */
						__( 'Checklist: %1$s (ID %2$d)', LAUNCHDEK_TEXT_DOMAIN ),
						$title,
						$id
					);
				}
				return $title ? sprintf(
					/* translators: %s: checklist title */
					__( 'Checklist: %s', LAUNCHDEK_TEXT_DOMAIN ),
					$title
				) : '';

			case 'client_run_pushed':
				return ! empty( $details['checklist'] ) ? sprintf(
					/* translators: %s: checklist title */
					__( 'Checklist: %s', LAUNCHDEK_TEXT_DOMAIN ),
					$details['checklist']
				) : '';

			case 'api_step_executed':
				return trim( sprintf(
					'%s %s',
					$details['method'] ?? 'GET',
					$details['route'] ?? ''
				) );

			case 'manual_step_completed':
			case 'manual_step_uncompleted':
			case 'client_step_uncompleted':
				return ! empty( $details['step_title'] ) ? sprintf(
					/* translators: %s: step title */
					__( 'Step: %s', LAUNCHDEK_TEXT_DOMAIN ),
					$details['step_title']
				) : '';

			case 'site_created':
			case 'site_updated':
				return self::format_details_kv(
					$details,
					array( 'success', 'message' )
				);

			case 'site_deleted':
				return ! empty( $details['name'] ) ? sprintf(
					/* translators: %s: site name */
					__( 'Site: %s', LAUNCHDEK_TEXT_DOMAIN ),
					$details['name']
				) : self::format_details_kv( $details );

			case 'checklist_created':
			case 'checklist_updated':
			case 'checklist_deleted':
			case 'workflow_created':
			case 'workflow_updated':
			case 'workflow_deleted':
				return ! empty( $details['title'] ) ? sprintf(
					/* translators: %s: checklist title */
					__( 'Checklist: %s', LAUNCHDEK_TEXT_DOMAIN ),
					$details['title']
				) : '';

			case 'integration_push':
			case 'integration_sync':
				return self::format_details_kv( $details );

			case 'telemetry_rules_updated':
				return ! empty( $details['count'] ) ? sprintf(
					/* translators: %d: number of rules */
					__( '%d mapping rules saved', LAUNCHDEK_TEXT_DOMAIN ),
					(int) $details['count']
				) : '';

			case 'run_status_changed':
				return sprintf(
					/* translators: %s: run status */
					__( 'Status changed to %s', LAUNCHDEK_TEXT_DOMAIN ),
					$details['status'] ?? ''
				);

			case 'drift_verified':
				$count = (int) ( $details['count'] ?? 0 );
				if ( $count > 0 && ! empty( $details['drifts'] ) ) {
					$lines = array();
					foreach ( (array) $details['drifts'] as $drift ) {
						if ( ! is_array( $drift ) ) {
							continue;
						}
						if ( 'drift' === ( $drift['status'] ?? '' ) ) {
							$lines[] = sprintf(
								'%1$s: expected %2$s, actual %3$s',
								$drift['label'] ?? ( $drift['check'] ?? '' ),
								wp_json_encode( $drift['expected'] ?? null ),
								wp_json_encode( $drift['actual'] ?? null )
							);
						} elseif ( 'error' === ( $drift['status'] ?? '' ) ) {
							$lines[] = ( $drift['label'] ?? '' ) . ': ' . ( $drift['message'] ?? '' );
						}
					}
					return implode( "\n", $lines );
				}
				return __( 'No drift detected.', LAUNCHDEK_TEXT_DOMAIN );

			case 'connection_test':
				return $details['message'] ?? '';

			case 'client_step_completed':
				return sprintf(
					'%s — %s',
					$details['step_title'] ?? '',
					$details['client_user'] ?? ''
				);

			case 'client_step_note_added':
				$summary = $details['note_preview'] ?? '';
				if ( ! empty( $details['has_attachment'] ) ) {
					$summary .= $summary ? "\n" : '';
					$summary .= __( 'Includes screenshot attachment.', LAUNCHDEK_TEXT_DOMAIN );
				}
				return $summary;

			default:
				return self::format_details_kv( $details );
		}
	}

	/**
	 * Format audit details as readable key-value pairs.
	 *
	 * @param array $details Detail payload.
	 * @param array $exclude Keys to omit.
	 * @return string
	 */
	private static function format_details_kv( $details, $exclude = array() ) {
		if ( ! is_array( $details ) || empty( $details ) ) {
			return '';
		}

		$labels = array(
			'checklist'     => __( 'Checklist', LAUNCHDEK_TEXT_DOMAIN ),
			'checklist_id'  => __( 'Checklist ID', LAUNCHDEK_TEXT_DOMAIN ),
			'workflow'      => __( 'Checklist', LAUNCHDEK_TEXT_DOMAIN ),
			'workflow_id'   => __( 'Checklist ID', LAUNCHDEK_TEXT_DOMAIN ),
			'run_id'        => __( 'Run ID', LAUNCHDEK_TEXT_DOMAIN ),
			'site_id'       => __( 'Site ID', LAUNCHDEK_TEXT_DOMAIN ),
			'site_name'     => __( 'Site', LAUNCHDEK_TEXT_DOMAIN ),
			'name'          => __( 'Name', LAUNCHDEK_TEXT_DOMAIN ),
			'url'           => __( 'URL', LAUNCHDEK_TEXT_DOMAIN ),
			'title'         => __( 'Title', LAUNCHDEK_TEXT_DOMAIN ),
			'status'        => __( 'Status', LAUNCHDEK_TEXT_DOMAIN ),
			'message'       => __( 'Message', LAUNCHDEK_TEXT_DOMAIN ),
			'method'        => __( 'Method', LAUNCHDEK_TEXT_DOMAIN ),
			'route'         => __( 'Route', LAUNCHDEK_TEXT_DOMAIN ),
			'step_title'    => __( 'Step', LAUNCHDEK_TEXT_DOMAIN ),
			'step_index'    => __( 'Step #', LAUNCHDEK_TEXT_DOMAIN ),
			'client_user'   => __( 'Client user', LAUNCHDEK_TEXT_DOMAIN ),
			'note_preview'  => __( 'Note', LAUNCHDEK_TEXT_DOMAIN ),
			'count'         => __( 'Count', LAUNCHDEK_TEXT_DOMAIN ),
			'success'       => __( 'Success', LAUNCHDEK_TEXT_DOMAIN ),
		);

		$parts = array();
		foreach ( $details as $key => $value ) {
			if ( in_array( $key, $exclude, true ) ) {
				continue;
			}
			if ( is_array( $value ) || is_object( $value ) ) {
				continue;
			}
			if ( '' === $value || null === $value ) {
				continue;
			}

			$label = $labels[ $key ] ?? ucwords( str_replace( '_', ' ', (string) $key ) );
			if ( is_bool( $value ) ) {
				$value = $value ? __( 'Yes', LAUNCHDEK_TEXT_DOMAIN ) : __( 'No', LAUNCHDEK_TEXT_DOMAIN );
			}

			$parts[] = $label . ': ' . $value;
		}

		return implode( ' · ', $parts );
	}

	/**
	 * Resolve a human-readable action label.
	 *
	 * @param string $action Action slug.
	 * @return string
	 */
	public static function format_action_label( $action ) {
		$labels = array(
			'run_started'            => __( 'Run started', LAUNCHDEK_TEXT_DOMAIN ),
			'run_status_changed'     => __( 'Run status changed', LAUNCHDEK_TEXT_DOMAIN ),
			'manual_step_completed'  => __( 'Manual step completed', LAUNCHDEK_TEXT_DOMAIN ),
			'manual_step_uncompleted' => __( 'Manual step uncompleted', LAUNCHDEK_TEXT_DOMAIN ),
			'site_created'           => __( 'Site registered', LAUNCHDEK_TEXT_DOMAIN ),
			'site_updated'           => __( 'Site updated', LAUNCHDEK_TEXT_DOMAIN ),
			'site_deleted'           => __( 'Site removed', LAUNCHDEK_TEXT_DOMAIN ),
			'checklist_created'       => __( 'Checklist created', LAUNCHDEK_TEXT_DOMAIN ),
			'checklist_updated'       => __( 'Checklist updated', LAUNCHDEK_TEXT_DOMAIN ),
			'checklist_deleted'       => __( 'Checklist deleted', LAUNCHDEK_TEXT_DOMAIN ),
			'workflow_created'        => __( 'Checklist created', LAUNCHDEK_TEXT_DOMAIN ),
			'workflow_updated'        => __( 'Checklist updated', LAUNCHDEK_TEXT_DOMAIN ),
			'workflow_deleted'        => __( 'Checklist deleted', LAUNCHDEK_TEXT_DOMAIN ),
			'connection_test'        => __( 'Connection test', LAUNCHDEK_TEXT_DOMAIN ),
			'drift_verified'         => __( 'Drift verification', LAUNCHDEK_TEXT_DOMAIN ),
			'integration_push'       => __( 'Integration push', LAUNCHDEK_TEXT_DOMAIN ),
			'integration_sync'       => __( 'Integration sync', LAUNCHDEK_TEXT_DOMAIN ),
			'telemetry_rules_updated' => __( 'Telemetry rules updated', LAUNCHDEK_TEXT_DOMAIN ),
			'client_run_pushed'      => __( 'Checklist pushed to client', LAUNCHDEK_TEXT_DOMAIN ),
			'client_step_completed'  => __( 'Client step completed', LAUNCHDEK_TEXT_DOMAIN ),
			'client_step_uncompleted' => __( 'Client step uncompleted', LAUNCHDEK_TEXT_DOMAIN ),
			'client_step_note_added' => __( 'Client step note added', LAUNCHDEK_TEXT_DOMAIN ),
		);

		if ( isset( $labels[ $action ] ) ) {
			return $labels[ $action ];
		}

		return ucwords( str_replace( '_', ' ', $action ) );
	}

	/**
	 * Resolve an outcome class for UI badges.
	 *
	 * @param string $action  Action slug.
	 * @param array  $details Structured details.
	 * @return string
	 */
	public static function resolve_outcome_class( $action, $details ) {
		if ( ! is_array( $details ) ) {
			$details = array();
		}

		if ( false !== strpos( $action, 'failed' ) ) {
			return 'failed';
		}

		if ( 'run_status_changed' === $action ) {
			$status = $details['status'] ?? '';
			if ( 'failed' === $status ) {
				return 'failed';
			}
			if ( 'completed' === $status ) {
				return 'success';
			}
		}

		if ( 'connection_test' === $action ) {
			return ! empty( $details['success'] ) ? 'success' : 'failed';
		}

		if ( 'drift_verified' === $action ) {
			$count = (int) ( $details['count'] ?? 0 );
			if ( $count > 0 ) {
				return 'warning';
			}
			foreach ( (array) ( $details['drifts'] ?? array() ) as $drift ) {
				if ( is_array( $drift ) && 'error' === ( $drift['status'] ?? '' ) ) {
					return 'failed';
				}
			}
			return 'success';
		}

		return 'success';
	}

	/**
	 * Format a database row for API output.
	 *
	 * @param array $row             Raw row.
	 * @param array $context         Lookup maps from build_query_context().
	 * @param bool  $include_details Include decoded details payload in the response.
	 * @return array
	 */
	public static function format_row( $row, $context = array(), $include_details = true ) {
		$user_id = (int) $row['user_id'];
		if ( $user_id && ! empty( $context['users'][ $user_id ] ) ) {
			$user_name = (string) $context['users'][ $user_id ];
		} else {
			$user      = get_userdata( $user_id );
			$user_name = $user ? $user->display_name : __( 'System', LAUNCHDEK_TEXT_DOMAIN );
		}

		$details = json_decode( (string) $row['details_json'], true );
		$details = is_array( $details ) ? $details : array();
		$site_id = (int) $row['site_id'];
		$run_id  = (int) $row['run_id'];

		$formatted = array(
			'id'              => (int) $row['id'],
			'user_id'         => $user_id,
			'user_name'       => $user_name,
			'site_id'         => $site_id,
			'site_name'       => self::resolve_site_name( $site_id, $run_id, $details, $context ),
			'run_id'          => $run_id,
			'action'          => $row['action'],
			'action_label'    => self::format_action_label( $row['action'] ),
			'details_summary' => self::format_details_summary( $row['action'], $details ),
			'outcome_class'   => self::resolve_outcome_class( $row['action'], $details ),
			'payload_hash'    => $row['payload_hash'],
			'created_at'      => $row['created_at'],
			'has_details'     => ! empty( $details ),
		);

		if ( $run_id && ! empty( $context['runs'][ $run_id ] ) ) {
			$formatted['run_summary'] = $context['runs'][ $run_id ];
		}

		if ( $include_details ) {
			$formatted['details'] = $details;
		}

		$formatted['message'] = self::format_feed_message( $formatted, false );

		unset( $formatted['run_summary'] );

		return $formatted;
	}
}
