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

		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Query audit entries with filters.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public static function query( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'user_id'   => 0,
			'site_id'   => 0,
			'run_id'    => 0,
			'action'    => '',
			'search'    => '',
			'status'    => '',
			'date_from' => '',
			'date_to'   => '',
			'limit'     => 50,
			'offset'    => 0,
			'order'     => 'DESC',
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = $wpdb->prefix . 'launchdek_audit_log';
		$where = array( '1=1' );
		$vals  = array();

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

		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
		$limit   = max( 1, min( 200, absint( $args['limit'] ) ) );
		$offset  = max( 0, absint( $args['offset'] ) );
		$where_sql = implode( ' AND ', $where );

		$sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at {$order} LIMIT %d OFFSET %d";
		$vals[] = $limit;
		$vals[] = $offset;

		if ( ! empty( $vals ) ) {
			$sql = $wpdb->prepare( $sql, $vals ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $rows ) ? array_map( array( __CLASS__, 'format_row' ), $rows ) : array();
	}

	/**
	 * Get recent entries for dashboard feed.
	 *
	 * @param int $limit Number of entries.
	 * @return array
	 */
	public static function get_feed( $limit = 20 ) {
		$entries = self::query( array( 'limit' => $limit ) );

		return array_map(
			function ( $entry ) {
				$entry['message'] = self::format_feed_message( $entry );
				return $entry;
			},
			$entries
		);
	}

	/**
	 * Build a human-readable message for the dashboard log feed.
	 *
	 * @param array $entry Formatted audit entry.
	 * @return string
	 */
	public static function format_feed_message( $entry ) {
		$details = is_array( $entry['details'] ) ? $entry['details'] : array();
		$site    = self::resolve_site_name( (int) $entry['site_id'], $entry['run_id'] );

		switch ( $entry['action'] ) {
			case 'run_started':
				$workflow = $details['workflow'] ?? __( 'Workflow', LAUNCHDEK_TEXT_DOMAIN );

				return sprintf(
					/* translators: 1: workflow title, 2: site name */
					__( "Checklist '%1\$s' started on %2\$s", LAUNCHDEK_TEXT_DOMAIN ),
					$workflow,
					$site
				);

			case 'run_status_changed':
				$status = $details['status'] ?? '';
				$run    = $entry['run_id'] ? LAUNCHDEK_Run_Repository::find( (int) $entry['run_id'] ) : null;

				if ( $run && 'completed' === $status ) {
					return sprintf(
						/* translators: 1: workflow title, 2: site name */
						__( "Checklist '%1\$s' completed on %2\$s", LAUNCHDEK_TEXT_DOMAIN ),
						$run['workflow_title'] ?: __( 'Workflow', LAUNCHDEK_TEXT_DOMAIN ),
						$run['site_name'] ?: $site
					);
				}

				if ( $run && 'failed' === $status ) {
					return sprintf(
						/* translators: 1: workflow title, 2: site name */
						__( "Checklist '%1\$s' failed on %2\$s", LAUNCHDEK_TEXT_DOMAIN ),
						$run['workflow_title'] ?: __( 'Workflow', LAUNCHDEK_TEXT_DOMAIN ),
						$run['site_name'] ?: $site
					);
				}

				return sprintf(
					/* translators: 1: status, 2: site name */
					__( 'Run status changed to %1$s on %2$s', LAUNCHDEK_TEXT_DOMAIN ),
					$status,
					$site
				);

			case 'site_created':
				return sprintf(
					/* translators: %s: site name */
					__( 'Site %s registered', LAUNCHDEK_TEXT_DOMAIN ),
					$site
				);

			case 'site_updated':
				return sprintf(
					/* translators: %s: site name */
					__( 'Site %s updated', LAUNCHDEK_TEXT_DOMAIN ),
					$site
				);

			case 'site_deleted':
				return __( 'Site removed', LAUNCHDEK_TEXT_DOMAIN );

			case 'workflow_created':
				return sprintf(
					/* translators: %s: workflow title */
					__( "Workflow '%s' created", LAUNCHDEK_TEXT_DOMAIN ),
					$details['title'] ?? __( 'Workflow', LAUNCHDEK_TEXT_DOMAIN )
				);

			case 'connection_test':
				return sprintf(
					/* translators: 1: site name, 2: result message */
					__( 'Connection test on %1$s: %2$s', LAUNCHDEK_TEXT_DOMAIN ),
					$site,
					$details['message'] ?? __( 'Completed', LAUNCHDEK_TEXT_DOMAIN )
				);

			default:
				return str_replace( '_', ' ', $entry['action'] );
		}
	}

	/**
	 * Resolve a site display name from site or run context.
	 *
	 * @param int $site_id Site ID.
	 * @param int $run_id  Run ID.
	 * @return string
	 */
	private static function resolve_site_name( $site_id, $run_id = 0 ) {
		if ( $site_id ) {
			$site = LAUNCHDEK_Site_Repository::find( $site_id );
			if ( $site ) {
				return $site['name'] ?: $site['url'];
			}
		}

		if ( $run_id ) {
			$run = LAUNCHDEK_Run_Repository::find( (int) $run_id );
			if ( $run && ! empty( $run['site_name'] ) ) {
				return $run['site_name'];
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
				return "(action IN ('run_started', 'manual_step_completed', 'site_created', 'site_updated', 'workflow_created', 'workflow_updated') OR (action = 'run_status_changed' AND details_json LIKE '%\"status\":\"completed\"%') OR (action = 'connection_test' AND details_json LIKE '%\"success\":true%') OR (action = 'drift_verified' AND details_json LIKE '%\"count\":0%'))";

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

			default:
				$encoded = wp_json_encode( $details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
				return is_string( $encoded ) ? $encoded : '';
		}
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
			'site_created'           => __( 'Site registered', LAUNCHDEK_TEXT_DOMAIN ),
			'site_updated'           => __( 'Site updated', LAUNCHDEK_TEXT_DOMAIN ),
			'site_deleted'           => __( 'Site removed', LAUNCHDEK_TEXT_DOMAIN ),
			'workflow_created'       => __( 'Workflow created', LAUNCHDEK_TEXT_DOMAIN ),
			'workflow_updated'       => __( 'Workflow updated', LAUNCHDEK_TEXT_DOMAIN ),
			'workflow_deleted'       => __( 'Workflow deleted', LAUNCHDEK_TEXT_DOMAIN ),
			'connection_test'        => __( 'Connection test', LAUNCHDEK_TEXT_DOMAIN ),
			'drift_verified'         => __( 'Drift verification', LAUNCHDEK_TEXT_DOMAIN ),
			'integration_push'       => __( 'Integration push', LAUNCHDEK_TEXT_DOMAIN ),
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
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function format_row( $row ) {
		$user     = get_userdata( (int) $row['user_id'] );
		$details  = json_decode( (string) $row['details_json'], true );
		$details  = is_array( $details ) ? $details : array();
		$site_id  = (int) $row['site_id'];

		return array(
			'id'              => (int) $row['id'],
			'user_id'         => (int) $row['user_id'],
			'user_name'       => $user ? $user->display_name : __( 'System', LAUNCHDEK_TEXT_DOMAIN ),
			'site_id'         => $site_id,
			'site_name'       => self::resolve_site_name( $site_id, (int) $row['run_id'] ),
			'run_id'          => (int) $row['run_id'],
			'action'          => $row['action'],
			'action_label'    => self::format_action_label( $row['action'] ),
			'details'         => $details,
			'details_summary' => self::format_details_summary( $row['action'], $details ),
			'outcome_class'   => self::resolve_outcome_class( $row['action'], $details ),
			'payload_hash'    => $row['payload_hash'],
			'created_at'      => $row['created_at'],
		);
	}
}
