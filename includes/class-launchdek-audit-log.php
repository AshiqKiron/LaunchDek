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

		if ( false === $result ) {
			return false;
		}

		LAUNCHDEK_Dashboard_Cache::invalidate_feed();

		return (int) $wpdb->insert_id;
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
				$run    = $entry['run_id'] ? LAUNCHDEK_Run_Repository::find( (int) $entry['run_id'] ) : null;

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
	 * Resolve a site display name from site or run context.
	 *
	 * @param int   $site_id Site ID.
	 * @param int   $run_id  Run ID.
	 * @param array $details Audit details fallback.
	 * @return string
	 */
	private static function resolve_site_name( $site_id, $run_id = 0, $details = array() ) {
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
			'site_name'       => self::resolve_site_name( $site_id, (int) $row['run_id'], $details ),
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
