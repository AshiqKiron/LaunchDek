<?php
/**
 * Checklist run persistence layer.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Run repository.
 */
class LAUNCHDEK_Run_Repository {

	/**
	 * Runs table.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'launchdek_runs';
	}

	/**
	 * Run steps table.
	 *
	 * @return string
	 */
	public static function steps_table() {
		global $wpdb;
		return $wpdb->prefix . 'launchdek_run_steps';
	}

	/**
	 * Find run by ID with steps.
	 *
	 * @param int $id Run ID.
	 * @return array|null
	 */
	public static function find( $id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', absint( $id ) ),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$formatted         = self::format( $row );
		$formatted['steps'] = self::get_steps( $id );

		return $formatted;
	}

	/**
	 * List runs.
	 *
	 * @param array $args Filters.
	 * @return array
	 */
	public static function all( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'           => '',
			'site_id'          => 0,
			'limit'            => 50,
			'include_archived' => false,
		);

		$args  = wp_parse_args( $args, $defaults );
		$where = array( '1=1' );
		$vals  = array();

		if ( $args['status'] ) {
			$where[] = 'status = %s';
			$vals[]  = sanitize_key( $args['status'] );
		}

		if ( $args['site_id'] ) {
			$where[] = 'site_id = %d';
			$vals[]  = absint( $args['site_id'] );
		}

		if ( empty( $args['include_archived'] ) ) {
			$where[] = 'is_archived = 0';
		}

		$sql = 'SELECT * FROM ' . self::table() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY started_at DESC LIMIT %d';
		$vals[] = max( 1, absint( $args['limit'] ) );

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $vals ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return array();
		}

		$formatted   = array_map( array( __CLASS__, 'format' ), $rows );
		$progress_map = self::get_step_progress_map( wp_list_pluck( $formatted, 'id' ) );

		foreach ( $formatted as &$run ) {
			$run = array_merge( $run, $progress_map[ $run['id'] ] ?? self::empty_step_progress() );
		}
		unset( $run );

		return $formatted;
	}

	/**
	 * List checklist runs for a site history panel (lean query, no N+1 lookups).
	 *
	 * @param int   $site_id Site ID.
	 * @param array $args    Optional status, limit, and offset filters.
	 * @return array{runs:array,has_more:bool,offset:int,limit:int}
	 */
	public static function list_for_site_history( $site_id, $args = array() ) {
		global $wpdb;

		$site_id = absint( $site_id );
		$empty   = array(
			'runs'     => array(),
			'has_more' => false,
			'offset'   => 0,
			'limit'    => 25,
		);

		if ( ! $site_id ) {
			return $empty;
		}

		$defaults = array(
			'status' => '',
			'limit'  => 25,
			'offset' => 0,
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = array( 'r.site_id = %d', 'r.is_archived = 0' );
		$vals   = array( $site_id );
		$offset = max( 0, absint( $args['offset'] ) );
		$limit  = min( 200, max( 1, absint( $args['limit'] ) ) );
		$runs   = self::table();
		$checks = LAUNCHDEK_Checklist_Repository::table();
		$users  = $wpdb->users;

		if ( $args['status'] ) {
			$where[] = 'r.status = %s';
			$vals[]  = sanitize_key( $args['status'] );
		}

		$sql = 'SELECT r.id, r.checklist_id, r.site_id, r.status, r.started_at, r.completed_at,
				c.title AS checklist_title, c.is_template AS checklist_is_template, c.template_slug AS checklist_template_slug,
				u.display_name AS started_by_name
			FROM ' . $runs . ' r
			LEFT JOIN ' . $checks . ' c ON c.id = r.checklist_id
			LEFT JOIN ' . $users . ' u ON u.ID = r.started_by
			WHERE ' . implode( ' AND ', $where ) . '
			ORDER BY r.started_at DESC
			LIMIT %d OFFSET %d';

		$vals[] = $limit + 1;
		$vals[] = $offset;

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $vals ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return array_merge(
				$empty,
				array(
					'offset' => $offset,
					'limit'  => $limit,
				)
			);
		}

		$has_more = count( $rows ) > $limit;
		if ( $has_more ) {
			$rows = array_slice( $rows, 0, $limit );
		}

		$run_ids      = array_map( 'intval', wp_list_pluck( $rows, 'id' ) );
		$progress_map = self::get_step_progress_map( $run_ids );
		$formatted    = array();

		foreach ( $rows as $row ) {
			$run_id       = (int) $row['id'];
			$status       = (string) ( $row['status'] ?? '' );
			$completed_at = $row['completed_at'];

			if ( empty( $completed_at ) && in_array( $status, array( 'completed', 'failed', 'cancelled' ), true ) ) {
				$progress = $progress_map[ $run_id ] ?? self::empty_step_progress();
				if ( ! empty( $progress['last_step_completed_at'] ) ) {
					$completed_at = $progress['last_step_completed_at'];
				}
			}

			$formatted[] = array_merge(
				array(
					'id'                      => $run_id,
					'checklist_id'            => (int) $row['checklist_id'],
					'checklist_title'         => (string) ( $row['checklist_title'] ?? '' ),
					'checklist_is_template'   => ! empty( $row['checklist_is_template'] ),
					'checklist_template_slug' => (string) ( $row['checklist_template_slug'] ?? '' ),
					'site_id'                 => (int) $row['site_id'],
					'status'                  => $status,
					'started_by_name'         => (string) ( $row['started_by_name'] ?? '' ),
					'started_at'              => $row['started_at'],
					'completed_at'            => $completed_at,
				),
				$progress_map[ $run_id ] ?? self::empty_step_progress()
			);
		}

		return array(
			'runs'     => $formatted,
			'has_more' => $has_more,
			'offset'   => $offset,
			'limit'    => $limit,
		);
	}

	/**
	 * Default step progress shape for runs with no step rows.
	 *
	 * @return array{steps_total:int,steps_completed:int,steps_failed:int,progress_percent:float,last_step_completed_at:string|null}
	 */
	public static function empty_step_progress() {
		return array(
			'steps_total'            => 0,
			'steps_completed'        => 0,
			'steps_failed'           => 0,
			'progress_percent'       => 0.0,
			'last_step_completed_at' => null,
		);
	}

	/**
	 * Batch-fetch step progress for multiple runs.
	 *
	 * @param int[] $run_ids Run IDs.
	 * @return array<int, array{steps_total:int,steps_completed:int,steps_failed:int,progress_percent:float,last_step_completed_at:string|null}>
	 */
	public static function get_step_progress_map( $run_ids ) {
		$run_ids = array_values( array_filter( array_map( 'absint', (array) $run_ids ) ) );

		if ( empty( $run_ids ) ) {
			return array();
		}

		global $wpdb;

		$placeholders = implode( ', ', array_fill( 0, count( $run_ids ), '%d' ) );
		$sql          = 'SELECT run_id,
				COUNT(*) AS steps_total,
				SUM( CASE WHEN status = %s THEN 1 ELSE 0 END ) AS steps_completed,
				SUM( CASE WHEN status = %s THEN 1 ELSE 0 END ) AS steps_failed,
				MAX( completed_at ) AS last_step_completed_at
			FROM ' . self::steps_table() . '
			WHERE run_id IN (' . $placeholders . ')
			GROUP BY run_id';

		$params = array_merge(
			array( 'completed', 'failed' ),
			$run_ids
		);

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$map = array();

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$run_id    = (int) $row['run_id'];
				$total     = (int) $row['steps_total'];
				$completed = (int) $row['steps_completed'];

				$last_completed = ! empty( $row['last_step_completed_at'] ) ? $row['last_step_completed_at'] : null;

				$map[ $run_id ] = array(
					'steps_total'            => $total,
					'steps_completed'        => $completed,
					'steps_failed'           => (int) $row['steps_failed'],
					'progress_percent'       => $total > 0 ? round( ( $completed / $total ) * 100, 1 ) : 0.0,
					'last_step_completed_at' => $last_completed,
				);
			}
		}

		return $map;
	}

	/**
	 * Count runs by status.
	 *
	 * @param string $status Status filter.
	 * @return int
	 */
	public static function count_by_status( $status = '' ) {
		global $wpdb;

		if ( $status ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE status = %s AND is_archived = 0', sanitize_key( $status ) )
			);
		}

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE is_archived = 0' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Archive a run (hide from default checklist history).
	 *
	 * @param int $id Run ID.
	 * @return bool
	 */
	public static function archive( $id ) {
		global $wpdb;

		$result = $wpdb->update(
			self::table(),
			array( 'is_archived' => 1 ),
			array( 'id' => absint( $id ) ),
			array( '%d' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			LAUNCHDEK_Audit_Log::log( 'run_archived', array( 'run_id' => absint( $id ) ) );
			LAUNCHDEK_Dashboard_Cache::invalidate_stats();
		}

		return false !== $result;
	}

	/**
	 * Permanently delete a run and its step records.
	 *
	 * @param int $id Run ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$id = absint( $id );

		$wpdb->delete( self::steps_table(), array( 'run_id' => $id ), array( '%d' ) );
		$result = $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );

		if ( $result ) {
			LAUNCHDEK_Audit_Log::log( 'run_deleted', array( 'run_id' => $id ) );
			LAUNCHDEK_Dashboard_Cache::invalidate_stats();
		}

		return (bool) $result;
	}

	/**
	 * Permanently delete all runs (and step rows) for a site.
	 *
	 * @param int $site_id Site ID.
	 * @return void
	 */
	public static function delete_for_site( $site_id ) {
		global $wpdb;

		$site_id = absint( $site_id );
		if ( ! $site_id ) {
			return;
		}

		$run_ids = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT id FROM ' . self::table() . ' WHERE site_id = %d',
				$site_id
			)
		);

		if ( ! empty( $run_ids ) ) {
			$run_ids      = array_map( 'absint', $run_ids );
			$placeholders = implode( ', ', array_fill( 0, count( $run_ids ), '%d' ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$wpdb->query(
				$wpdb->prepare(
					'DELETE FROM ' . self::steps_table() . ' WHERE run_id IN (' . $placeholders . ')',
					$run_ids
				)
			);
		}

		$wpdb->delete( self::table(), array( 'site_id' => $site_id ), array( '%d' ) );
		LAUNCHDEK_Dashboard_Cache::invalidate_stats();
	}

	/**
	 * Calculate completion rate percentage.
	 *
	 * @return float
	 */
	public static function completion_rate() {
		$total = self::count_by_status();

		if ( 0 === $total ) {
			return 0.0;
		}

		$completed = self::count_by_status( 'completed' );

		return round( ( $completed / $total ) * 100, 1 );
	}

	/**
	 * Create a new run with step records.
	 *
	 * @param int $checklist_id Checklist ID.
	 * @param int $site_id     Site ID.
	 * @return int|false
	 */
	public static function create( $checklist_id, $site_id ) {
		global $wpdb;

		$checklist = LAUNCHDEK_Checklist_Repository::find( $checklist_id );

		if ( ! $checklist ) {
			return false;
		}

		$result = $wpdb->insert(
			self::table(),
			array(
				'checklist_id' => absint( $checklist_id ),
				'site_id'     => absint( $site_id ),
				'status'      => 'running',
				'started_by'  => get_current_user_id(),
				'started_at'  => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		$run_id = (int) $wpdb->insert_id;
		$token  = wp_generate_password( 48, false, false );

		$wpdb->update(
			self::table(),
			array( 'client_run_token' => $token ),
			array( 'id' => $run_id ),
			array( '%s' ),
			array( '%d' )
		);

		foreach ( $checklist['steps'] as $index => $step ) {
			$wpdb->insert(
				self::steps_table(),
				array(
					'run_id'     => $run_id,
					'step_index' => $index,
					'step_id'    => sanitize_key( $step['id'] ?? 'step_' . ( $index + 1 ) ),
					'title'      => sanitize_text_field( $step['title'] ?? '' ),
					'step_type'  => sanitize_key( $step['type'] ?? 'manual' ),
					'status'     => 'pending',
				),
				array( '%d', '%d', '%s', '%s', '%s', '%s' )
			);
		}

		LAUNCHDEK_Audit_Log::log(
			'run_started',
			array(
				'checklist_id' => $checklist_id,
				'checklist'    => $checklist['title'],
			),
			$site_id,
			$run_id
		);

		LAUNCHDEK_Webhook_Dispatcher::dispatch(
			'run_started',
			array(
				'run_id'    => $run_id,
				'checklist' => $checklist['title'],
				'site_id'   => $site_id,
			)
		);
		LAUNCHDEK_Dashboard_Cache::invalidate_stats();

		return $run_id;
	}

	/**
	 * Update run status.
	 *
	 * @param int    $id     Run ID.
	 * @param string $status Status.
	 * @return bool
	 */
	public static function update_status( $id, $status ) {
		global $wpdb;

		$fields = array( 'status' => sanitize_key( $status ) );

		if ( in_array( $status, array( 'completed', 'failed', 'cancelled' ), true ) ) {
			$fields['completed_at'] = current_time( 'mysql', true );
		}

		$result = $wpdb->update( self::table(), $fields, array( 'id' => absint( $id ) ), null, array( '%d' ) );

		if ( false !== $result ) {
			LAUNCHDEK_Audit_Log::log( 'run_status_changed', array( 'status' => $status ), 0, $id );
			LAUNCHDEK_Webhook_Dispatcher::dispatch( 'run_' . $status, array( 'run_id' => $id, 'status' => $status ) );
			LAUNCHDEK_Dashboard_Cache::invalidate_stats();
		}

		return false !== $result;
	}

	/**
	 * Reopen a finished run so steps can be updated again.
	 *
	 * @param int $id Run ID.
	 * @return bool
	 */
	public static function reopen( $id ) {
		global $wpdb;

		$result = $wpdb->update(
			self::table(),
			array(
				'status'       => 'running',
				'completed_at' => null,
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			LAUNCHDEK_Audit_Log::log( 'run_status_changed', array( 'status' => 'running' ), 0, $id );
			LAUNCHDEK_Dashboard_Cache::invalidate_stats();
		}

		return false !== $result;
	}

	/**
	 * Get the client callback token for a run.
	 *
	 * @param int $run_id Run ID.
	 * @return string
	 */
	public static function get_client_token( $run_id ) {
		global $wpdb;

		$token = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT client_run_token FROM ' . self::table() . ' WHERE id = %d',
				absint( $run_id )
			)
		);

		return is_string( $token ) ? $token : '';
	}

	/**
	 * Return an existing client callback token or create one for legacy runs.
	 *
	 * @param int $run_id Run ID.
	 * @return string
	 */
	public static function ensure_client_token( $run_id ) {
		$token = self::get_client_token( $run_id );

		if ( '' !== $token ) {
			return $token;
		}

		global $wpdb;

		$token = wp_generate_password( 48, false, false );

		$wpdb->update(
			self::table(),
			array( 'client_run_token' => $token ),
			array( 'id' => absint( $run_id ) ),
			array( '%s' ),
			array( '%d' )
		);

		return $token;
	}

	/**
	 * Get steps for a run.
	 *
	 * @param int $run_id Run ID.
	 * @return array
	 */
	public static function get_steps( $run_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::steps_table() . ' WHERE run_id = %d ORDER BY step_index ASC',
				absint( $run_id )
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			array( __CLASS__, 'format_step_row' ),
			$rows
		);
	}

	/**
	 * Update a run step.
	 *
	 * @param int   $run_id     Run ID.
	 * @param int   $step_index Step index.
	 * @param array $data       Update data.
	 * @return bool
	 */
	public static function update_step( $run_id, $step_index, $data ) {
		global $wpdb;

		$fields = array();
		$format = array();

		foreach ( array( 'status', 'error_message' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$fields[ $key ] = 'status' === $key ? sanitize_key( $data[ $key ] ) : sanitize_textarea_field( $data[ $key ] );
				$format[]       = '%s';
			}
		}

		if ( isset( $data['response'] ) ) {
			$fields['response_json'] = wp_json_encode( $data['response'] );
			$format[]                = '%s';
		}

		if ( isset( $data['notes'] ) && is_array( $data['notes'] ) ) {
			$fields['notes_json'] = wp_json_encode( $data['notes'] );
			$format[]             = '%s';
		}

		if ( isset( $data['manual_checked'] ) ) {
			$fields['manual_checked'] = $data['manual_checked'] ? 1 : 0;
			$format[]                 = '%d';
		}

		if ( isset( $data['started_at'] ) ) {
			$fields['started_at'] = $data['started_at'];
			$format[]             = '%s';
		}

		if ( isset( $data['completed_at'] ) ) {
			$fields['completed_at'] = $data['completed_at'];
			$format[]               = '%s';
		}

		if ( empty( $fields ) ) {
			return false;
		}

		return false !== $wpdb->update(
			self::steps_table(),
			$fields,
			array(
				'run_id'     => absint( $run_id ),
				'step_index' => absint( $step_index ),
			),
			$format,
			array( '%d', '%d' )
		);
	}

	/**
	 * Append a note to a run step.
	 *
	 * @param int   $run_id     Run ID.
	 * @param int   $step_index Step index.
	 * @param array $note       Note payload (user, text, attachment_id, attachment_url).
	 * @return array|false Updated notes array or false on failure.
	 */
	public static function add_step_note( $run_id, $step_index, $note ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT notes_json FROM ' . self::steps_table() . ' WHERE run_id = %d AND step_index = %d',
				absint( $run_id ),
				absint( $step_index )
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return false;
		}

		$note = self::sanitize_step_note( $note );

		if ( null === $note ) {
			return false;
		}

		$notes = self::filter_step_notes( json_decode( (string) $row['notes_json'], true ) );

		foreach ( $notes as $existing ) {
			if ( self::step_notes_match( $existing, $note ) ) {
				return $notes;
			}
		}

		$notes[] = $note;

		$updated = self::update_step(
			$run_id,
			$step_index,
			array(
				'notes' => $notes,
			)
		);

		return $updated ? $notes : false;
	}

	/**
	 * Sanitize a single step note entry.
	 *
	 * @param array $note Raw note.
	 * @return array|null Sanitized note or null when invalid.
	 */
	public static function sanitize_step_note( $note ) {
		if ( ! is_array( $note ) ) {
			return null;
		}

		$text = trim( sanitize_textarea_field( $note['text'] ?? '' ) );

		if ( '' === $text ) {
			return null;
		}

		$sanitized = array(
			'user'       => sanitize_text_field( $note['user'] ?? '' ),
			'text'       => $text,
			'created_at' => sanitize_text_field( $note['created_at'] ?? current_time( 'mysql', true ) ),
		);

		$attachment_id = absint( $note['attachment_id'] ?? 0 );
		if ( $attachment_id > 0 ) {
			$sanitized['attachment_id'] = $attachment_id;
		}

		if ( ! empty( $note['attachment_url'] ) ) {
			$sanitized['attachment_url'] = esc_url_raw( $note['attachment_url'] );
		}

		return $sanitized;
	}

	/**
	 * Keep only user-authored notes with non-empty text.
	 *
	 * @param mixed $notes Raw notes array.
	 * @return array
	 */
	public static function filter_step_notes( $notes ) {
		if ( ! is_array( $notes ) ) {
			return array();
		}

		$filtered = array();

		foreach ( $notes as $note ) {
			$sanitized = self::sanitize_step_note( $note );
			if ( null === $sanitized ) {
				continue;
			}

			foreach ( $filtered as $existing ) {
				if ( self::step_notes_match( $existing, $sanitized ) ) {
					continue 2;
				}
			}

			$filtered[] = $sanitized;
		}

		return $filtered;
	}

	/**
	 * Compare two notes by author/content rather than sync timestamps.
	 *
	 * @param array $left  First note.
	 * @param array $right Second note.
	 * @return bool
	 */
	public static function step_notes_match( $left, $right ) {
		if ( ! is_array( $left ) || ! is_array( $right ) ) {
			return false;
		}

		return md5(
			strtolower( trim( (string) ( $left['user'] ?? '' ) ) ) . '|' .
			trim( (string) ( $left['text'] ?? '' ) ) . '|' .
			(string) absint( $left['attachment_id'] ?? 0 )
		) === md5(
			strtolower( trim( (string) ( $right['user'] ?? '' ) ) ) . '|' .
			trim( (string) ( $right['text'] ?? '' ) ) . '|' .
			(string) absint( $right['attachment_id'] ?? 0 )
		);
	}

	/**
	 * Format a run step database row.
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function format_step_row( $row ) {
		$response = json_decode( (string) $row['response_json'], true );
		$notes    = json_decode( (string) ( $row['notes_json'] ?? '' ), true );

		return array(
			'id'             => (int) $row['id'],
			'step_index'     => (int) $row['step_index'],
			'step_id'        => $row['step_id'],
			'title'          => $row['title'],
			'step_type'      => $row['step_type'],
			'status'         => $row['status'],
			'response'       => is_array( $response ) ? $response : null,
			'notes'          => self::filter_step_notes( $notes ),
			'error_message'  => $row['error_message'],
			'manual_checked' => (bool) $row['manual_checked'],
			'started_at'     => $row['started_at'],
			'completed_at'   => $row['completed_at'],
		);
	}

	/**
	 * Format run row.
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function format( $row ) {
		$checklist = LAUNCHDEK_Checklist_Repository::find( (int) $row['checklist_id'] );
		$site      = LAUNCHDEK_Site_Repository::find( (int) $row['site_id'] );
		$user      = get_userdata( (int) $row['started_by'] );

		return array(
			'id'                      => (int) $row['id'],
			'checklist_id'            => (int) $row['checklist_id'],
			'checklist_title'         => $checklist ? $checklist['title'] : '',
			'checklist_is_template'   => $checklist ? ! empty( $checklist['is_template'] ) : false,
			'checklist_template_slug' => $checklist ? (string) ( $checklist['template_slug'] ?? '' ) : '',
			'site_id'                 => (int) $row['site_id'],
			'site_name'               => $site ? $site['name'] : '',
			'site_url'                => $site ? $site['url'] : '',
			'status'                  => $row['status'],
			'started_by'              => (int) $row['started_by'],
			'started_by_name'         => $user ? $user->display_name : '',
			'started_at'              => $row['started_at'],
			'completed_at'            => $row['completed_at'],
			'notes'                   => $row['notes'],
			'is_archived'             => ! empty( $row['is_archived'] ),
		);
	}
}
