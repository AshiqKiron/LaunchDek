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
			'status'  => '',
			'site_id' => 0,
			'limit'   => 50,
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

		$sql = 'SELECT * FROM ' . self::table() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY started_at DESC LIMIT %d';
		$vals[] = max( 1, absint( $args['limit'] ) );

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $vals ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $rows ) ? array_map( array( __CLASS__, 'format' ), $rows ) : array();
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
				$wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE status = %s', sanitize_key( $status ) )
			);
		}

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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

		$notes   = json_decode( (string) $row['notes_json'], true );
		$notes   = is_array( $notes ) ? $notes : array();
		$notes[] = self::sanitize_step_note( $note );

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
	 * @return array
	 */
	public static function sanitize_step_note( $note ) {
		$sanitized = array(
			'user'       => sanitize_text_field( $note['user'] ?? '' ),
			'text'       => sanitize_textarea_field( $note['text'] ?? '' ),
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
			'notes'          => is_array( $notes ) ? $notes : array(),
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
			'id'              => (int) $row['id'],
			'checklist_id'    => (int) $row['checklist_id'],
			'checklist_title' => $checklist ? $checklist['title'] : '',
			'site_id'       => (int) $row['site_id'],
			'site_name'     => $site ? $site['name'] : '',
			'site_url'      => $site ? $site['url'] : '',
			'status'        => $row['status'],
			'started_by'    => (int) $row['started_by'],
			'started_by_name' => $user ? $user->display_name : '',
			'started_at'    => $row['started_at'],
			'completed_at'  => $row['completed_at'],
			'notes'         => $row['notes'],
		);
	}
}
