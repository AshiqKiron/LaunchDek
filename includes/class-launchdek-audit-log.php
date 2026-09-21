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
	 * Decode stored details JSON for a single audit entry (lean lookup).
	 *
	 * @param int $id Entry ID.
	 * @return array|null Decoded details array, or null when the row does not exist.
	 */
	public static function get_decoded_details( $id ) {
		global $wpdb;

		$id = absint( $id );
		if ( ! $id ) {
			return null;
		}

		$table = $wpdb->prefix . 'launchdek_audit_log';
		$json  = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT details_json FROM {$table} WHERE id = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			)
		);

		if ( null === $json ) {
			return null;
		}

		$details = json_decode( (string) $json, true );

		return is_array( $details ) ? $details : array();
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
			$where[] = "{$table}.id = %d";
			$vals[]  = absint( $args['id'] );
		}

		if ( $args['user_id'] ) {
			$where[] = "{$table}.user_id = %d";
			$vals[]  = absint( $args['user_id'] );
		}

		$site_scope_join = '';
		if ( $args['site_id'] ) {
			$filtered_site_id = absint( $args['site_id'] );
			$runs_table       = $wpdb->prefix . 'launchdek_runs';
			$site_scope_join  = " LEFT JOIN {$runs_table} AS launchdek_audit_runs ON launchdek_audit_runs.id = {$table}.run_id ";
			$where[]          = "({$table}.site_id = %d OR launchdek_audit_runs.site_id = %d)";
			$vals[]           = $filtered_site_id;
			$vals[]           = $filtered_site_id;
		}

		if ( $args['run_id'] ) {
			$where[] = "{$table}.run_id = %d";
			$vals[]  = absint( $args['run_id'] );
		}

		if ( $args['action'] ) {
			$where[] = "{$table}.action = %s";
			$vals[]  = sanitize_key( $args['action'] );
		}

		if ( $args['search'] ) {
			$where[] = "{$table}.details_json LIKE %s";
			$vals[]  = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		if ( $args['date_from'] && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $args['date_from'] ) ) {
			$where[] = "{$table}.created_at >= %s";
			$vals[]  = $args['date_from'] . ' 00:00:00';
		}

		if ( $args['date_to'] && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $args['date_to'] ) ) {
			$where[] = "{$table}.created_at <= %s";
			$vals[]  = $args['date_to'] . ' 23:59:59';
		}

		if ( $args['status'] ) {
			$status_clause = self::status_where_clause( $args['status'], $table );
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
		$lean_list       = ! $include_details;

		$sql = "SELECT {$table}.id, {$table}.user_id, {$table}.site_id, {$table}.run_id, {$table}.action, {$table}.details_json, {$table}.payload_hash, {$table}.created_at FROM {$table}{$site_scope_join} WHERE {$where_sql} ORDER BY {$table}.created_at {$order} LIMIT %d OFFSET %d";
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

		$context   = self::build_query_context( $rows, $lean_list );
		$formatted = array();
		foreach ( $rows as $row ) {
			$formatted[] = self::format_row( $row, $context, $include_details, $lean_list );
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
	private static function build_query_context( array $rows, $lean = false ) {
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

		$step_context = $lean
			? self::load_run_steps_map_lean( $rows )
			: self::load_run_steps_map( $unique_run_ids );

		return array(
			'users'           => self::load_users_map( array_values( array_unique( $user_ids ) ) ),
			'sites'           => self::load_sites_map(
				array_values(
					array_unique(
						array_merge( $site_ids, $linked_site_ids )
					)
				)
			),
			'runs'            => $runs,
			'steps'           => $step_context['steps'],
			'run_step_counts' => $step_context['counts'],
		);
	}

	/**
	 * Batch-load run step metadata for audit detail formatting.
	 *
	 * @param array $run_ids Run IDs.
	 * @return array{steps:array<string,array>,counts:array<int,int>}
	 */
	private static function load_run_steps_map( array $run_ids ) {
		global $wpdb;

		$steps = array();
		$counts = array();
		if ( empty( $run_ids ) ) {
			return array(
				'steps'  => $steps,
				'counts' => $counts,
			);
		}

		$steps_table  = $wpdb->prefix . 'launchdek_run_steps';
		$placeholders = implode( ',', array_fill( 0, count( $run_ids ), '%d' ) );
		$sql          = "SELECT run_id, step_index, title, step_type, status, completed_at FROM {$steps_table} WHERE run_id IN ({$placeholders}) ORDER BY run_id ASC, step_index ASC";
		$rows         = $wpdb->get_results( $wpdb->prepare( $sql, $run_ids ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $rows ) ) {
			return array(
				'steps'  => $steps,
				'counts' => $counts,
			);
		}

		foreach ( $rows as $row ) {
			$run_id = (int) $row['run_id'];
			$index  = (int) $row['step_index'];
			$key    = $run_id . ':' . $index;

			$steps[ $key ] = array(
				'title'        => (string) $row['title'],
				'step_index'   => $index,
				'step_type'    => (string) $row['step_type'],
				'status'       => (string) $row['status'],
				'completed_at' => (string) $row['completed_at'],
			);

			$counts[ $run_id ] = isset( $counts[ $run_id ] ) ? $counts[ $run_id ] + 1 : 1;
		}

		return array(
			'steps'  => $steps,
			'counts' => $counts,
		);
	}

	/**
	 * Load only step titles referenced on the current audit page (lean list queries).
	 *
	 * @param array $rows Raw audit rows.
	 * @return array{steps:array<string,array>,counts:array<int,int>}
	 */
	private static function load_run_steps_map_lean( array $rows ) {
		global $wpdb;

		$steps  = array();
		$counts = array();
		$pairs  = array();

		foreach ( $rows as $row ) {
			$run_id = (int) ( $row['run_id'] ?? 0 );
			if ( ! $run_id ) {
				continue;
			}

			$details = json_decode( (string) ( $row['details_json'] ?? '' ), true );
			if ( ! is_array( $details ) || ! isset( $details['step_index'] ) ) {
				continue;
			}

			$index = (int) $details['step_index'];
			$key   = $run_id . ':' . $index;
			if ( isset( $pairs[ $key ] ) ) {
				continue;
			}

			$pairs[ $key ] = array(
				'run_id'     => $run_id,
				'step_index' => $index,
			);
		}

		if ( empty( $pairs ) ) {
			return array(
				'steps'  => $steps,
				'counts' => $counts,
			);
		}

		$steps_table = $wpdb->prefix . 'launchdek_run_steps';
		$clauses     = array();
		$vals        = array();

		foreach ( $pairs as $pair ) {
			$clauses[] = '(run_id = %d AND step_index = %d)';
			$vals[]    = $pair['run_id'];
			$vals[]    = $pair['step_index'];
		}

		$sql  = 'SELECT run_id, step_index, title FROM ' . $steps_table . ' WHERE ' . implode( ' OR ', $clauses );
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $vals ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $rows ) ) {
			return array(
				'steps'  => $steps,
				'counts' => $counts,
			);
		}

		foreach ( $rows as $row ) {
			$run_id = (int) $row['run_id'];
			$index  = (int) $row['step_index'];
			$key    = $run_id . ':' . $index;

			$steps[ $key ] = array(
				'title'      => (string) $row['title'],
				'step_index' => $index,
			);
		}

		return array(
			'steps'  => $steps,
			'counts' => $counts,
		);
	}

	/**
	 * Build paginated activity-log query args from request-style input.
	 *
	 * @param array $input Raw query/body parameters.
	 * @return array
	 */
	public static function list_query_args_from_input( array $input ) {
		$include_details = filter_var( $input['include_details'] ?? false, FILTER_VALIDATE_BOOLEAN );

		return array(
			'user_id'         => absint( $input['user_id'] ?? 0 ),
			'site_id'         => absint( $input['site_id'] ?? 0 ),
			'action'          => sanitize_key( $input['action'] ?? '' ),
			'search'          => sanitize_text_field( $input['search'] ?? '' ),
			'status'          => sanitize_key( $input['status'] ?? '' ),
			'date_from'       => sanitize_text_field( $input['date_from'] ?? '' ),
			'date_to'         => sanitize_text_field( $input['date_to'] ?? '' ),
			'limit'           => absint( $input['limit'] ?? self::LIST_DEFAULT_LIMIT ),
			'offset'          => absint( $input['offset'] ?? 0 ),
			'include_details' => $include_details,
			'paginate'        => true,
		);
	}

	/**
	 * Resolve a step title from audit details and batch context.
	 *
	 * @param int   $run_id  Run ID.
	 * @param array $details Audit details.
	 * @param array $context Query context maps.
	 * @return string
	 */
	private static function resolve_step_title_from_context( $run_id, $details, $context ) {
		if ( ! empty( $details['step_title'] ) ) {
			return sanitize_text_field( $details['step_title'] );
		}

		$entry = self::get_step_context_entry( $context, $run_id, $details['step_index'] ?? null );
		if ( $entry && ! empty( $entry['title'] ) ) {
			return (string) $entry['title'];
		}

		return '';
	}

	/**
	 * Resolve a run step record from batch query context.
	 *
	 * @param array    $context    Query context maps.
	 * @param int      $run_id     Run ID.
	 * @param int|null $step_index Step index.
	 * @return array|null
	 */
	private static function get_step_context_entry( $context, $run_id, $step_index ) {
		$run_id = absint( $run_id );
		if ( ! $run_id || null === $step_index || ! is_array( $context ) ) {
			return null;
		}

		$key = $run_id . ':' . (int) $step_index;
		if ( empty( $context['steps'][ $key ] ) ) {
			return null;
		}

		$entry = $context['steps'][ $key ];
		if ( is_string( $entry ) ) {
			return array(
				'title'      => $entry,
				'step_index' => (int) $step_index,
			);
		}

		return is_array( $entry ) ? $entry : null;
	}

	/**
	 * Format a UTC MySQL datetime for Activity Logs detail copy.
	 *
	 * @param string $mysql_gmt Datetime string (GMT).
	 * @return string
	 */
	private static function format_activity_datetime( $mysql_gmt ) {
		if ( empty( $mysql_gmt ) ) {
			return '';
		}

		$timestamp = strtotime( (string) $mysql_gmt . ' +0000' );
		if ( ! $timestamp ) {
			return sanitize_text_field( (string) $mysql_gmt );
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/**
	 * Human-readable checklist step type label.
	 *
	 * @param string $step_type Step type slug.
	 * @return string
	 */
	private static function format_step_type_label( $step_type ) {
		switch ( sanitize_key( $step_type ) ) {
			case 'api':
				return __( 'API step', LAUNCHDEK_TEXT_DOMAIN );
			case 'manual':
			default:
				return __( 'Manual step', LAUNCHDEK_TEXT_DOMAIN );
		}
	}

	/**
	 * Append a labeled field for Activity Logs detail panels.
	 *
	 * @param array  $fields Field list (by reference).
	 * @param string $label  Field label.
	 * @param mixed  $value  Field value.
	 * @return void
	 */
	private static function append_detail_field( array &$fields, $label, $value ) {
		$formatted = self::format_detail_field_value( $value );
		if ( '' === $formatted ) {
			return;
		}

		$fields[] = array(
			'label' => (string) $label,
			'value' => $formatted,
		);
	}

	/**
	 * Format a detail field value for display.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function format_detail_field_value( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? __( 'Yes', LAUNCHDEK_TEXT_DOMAIN ) : __( 'No', LAUNCHDEK_TEXT_DOMAIN );
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}

		if ( is_string( $value ) ) {
			return trim( $value );
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			$encoded = wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
			return is_string( $encoded ) ? $encoded : '';
		}

		return '';
	}

	/**
	 * Known audit detail keys mapped to translatable labels.
	 *
	 * @return array<string, string>
	 */
	private static function get_detail_field_labels() {
		return array(
			'checklist'           => __( 'Checklist', LAUNCHDEK_TEXT_DOMAIN ),
			'checklist_id'        => __( 'Checklist ID', LAUNCHDEK_TEXT_DOMAIN ),
			'workflow'            => __( 'Checklist', LAUNCHDEK_TEXT_DOMAIN ),
			'workflow_id'         => __( 'Checklist ID', LAUNCHDEK_TEXT_DOMAIN ),
			'run_id'              => __( 'Run ID', LAUNCHDEK_TEXT_DOMAIN ),
			'site_id'             => __( 'Site ID', LAUNCHDEK_TEXT_DOMAIN ),
			'site_name'           => __( 'Site', LAUNCHDEK_TEXT_DOMAIN ),
			'name'                => __( 'Name', LAUNCHDEK_TEXT_DOMAIN ),
			'url'                 => __( 'URL', LAUNCHDEK_TEXT_DOMAIN ),
			'title'               => __( 'Title', LAUNCHDEK_TEXT_DOMAIN ),
			'status'              => __( 'Status', LAUNCHDEK_TEXT_DOMAIN ),
			'message'             => __( 'Message', LAUNCHDEK_TEXT_DOMAIN ),
			'method'              => __( 'HTTP method', LAUNCHDEK_TEXT_DOMAIN ),
			'route'               => __( 'API route', LAUNCHDEK_TEXT_DOMAIN ),
			'code'                => __( 'HTTP status', LAUNCHDEK_TEXT_DOMAIN ),
			'step_title'          => __( 'Step', LAUNCHDEK_TEXT_DOMAIN ),
			'step_index'          => __( 'Step number', LAUNCHDEK_TEXT_DOMAIN ),
			'client_user'         => __( 'Client user', LAUNCHDEK_TEXT_DOMAIN ),
			'client_user_email'   => __( 'Client email', LAUNCHDEK_TEXT_DOMAIN ),
			'client_id'           => __( 'Client user ID', LAUNCHDEK_TEXT_DOMAIN ),
			'note_preview'        => __( 'Note', LAUNCHDEK_TEXT_DOMAIN ),
			'has_attachment'      => __( 'Screenshot attached', LAUNCHDEK_TEXT_DOMAIN ),
			'count'               => __( 'Count', LAUNCHDEK_TEXT_DOMAIN ),
			'success'             => __( 'Success', LAUNCHDEK_TEXT_DOMAIN ),
			'wp_version'          => __( 'WordPress version', LAUNCHDEK_TEXT_DOMAIN ),
			'php_version'         => __( 'PHP version', LAUNCHDEK_TEXT_DOMAIN ),
			'client_agent'        => __( 'Client panel installed', LAUNCHDEK_TEXT_DOMAIN ),
			'integration'         => __( 'Integration', LAUNCHDEK_TEXT_DOMAIN ),
			'excluded_fields'     => __( 'Excluded fields', LAUNCHDEK_TEXT_DOMAIN ),
			'payload'             => __( 'Request body', LAUNCHDEK_TEXT_DOMAIN ),
			'results'             => __( 'Results', LAUNCHDEK_TEXT_DOMAIN ),
			'drifts'              => __( 'Drift findings', LAUNCHDEK_TEXT_DOMAIN ),
		);
	}

	/**
	 * Add step-related detail fields for run-scoped audit actions.
	 *
	 * @param array  $fields    Field list (by reference).
	 * @param int    $run_id    Run ID.
	 * @param array  $details   Audit details.
	 * @param array  $context   Query context.
	 * @param string $user_name Hub actor display name.
	 * @param string $action    Audit action slug.
	 * @return void
	 */
	private static function append_step_detail_fields( array &$fields, $run_id, $details, $context, $user_name, $action ) {
		$step_index = isset( $details['step_index'] ) ? (int) $details['step_index'] : null;
		$entry      = self::get_step_context_entry( $context, $run_id, $step_index );
		$step_title = self::resolve_step_title_from_context( $run_id, $details, $context );
		$total      = ! empty( $context['run_step_counts'][ $run_id ] ) ? (int) $context['run_step_counts'][ $run_id ] : 0;

		if ( $step_title ) {
			self::append_detail_field( $fields, __( 'Step', LAUNCHDEK_TEXT_DOMAIN ), $step_title );
		}

		if ( null !== $step_index ) {
			if ( $total > 0 ) {
				self::append_detail_field(
					$fields,
					__( 'Progress', LAUNCHDEK_TEXT_DOMAIN ),
					sprintf(
						/* translators: 1: step number, 2: total steps */
						__( 'Step %1$d of %2$d', LAUNCHDEK_TEXT_DOMAIN ),
						$step_index + 1,
						$total
					)
				);
			} else {
				self::append_detail_field( $fields, __( 'Step number', LAUNCHDEK_TEXT_DOMAIN ), (string) ( $step_index + 1 ) );
			}
		}

		if ( $entry && ! empty( $entry['step_type'] ) ) {
			self::append_detail_field( $fields, __( 'Step type', LAUNCHDEK_TEXT_DOMAIN ), self::format_step_type_label( $entry['step_type'] ) );
		}

		if ( $entry && ! empty( $entry['completed_at'] ) ) {
			self::append_detail_field( $fields, __( 'Step completed', LAUNCHDEK_TEXT_DOMAIN ), self::format_activity_datetime( $entry['completed_at'] ) );
		}

		if ( in_array( $action, array( 'client_step_completed', 'client_step_uncompleted', 'client_step_note_added' ), true ) ) {
			$client = sanitize_text_field( $details['client_user'] ?? '' );
			$email  = sanitize_email( $details['client_user_email'] ?? '' );
			if ( $client ) {
				self::append_detail_field( $fields, __( 'Client user', LAUNCHDEK_TEXT_DOMAIN ), $client );
			}
			if ( $email ) {
				self::append_detail_field( $fields, __( 'Client email', LAUNCHDEK_TEXT_DOMAIN ), $email );
			}
		} elseif ( in_array( $action, array( 'manual_step_completed', 'manual_step_uncompleted' ), true ) && $user_name ) {
			$label = 'manual_step_uncompleted' === $action
				? __( 'Reverted by', LAUNCHDEK_TEXT_DOMAIN )
				: __( 'Completed by', LAUNCHDEK_TEXT_DOMAIN );
			self::append_detail_field( $fields, $label, $user_name );
		}
	}

	/**
	 * Build labeled detail fields for the Activity Logs expandable panel.
	 *
	 * @param array  $row       Raw audit row.
	 * @param array  $details   Decoded details.
	 * @param array  $context   Lookup maps.
	 * @param string $user_name Resolved actor display name.
	 * @return array<int, array{label:string,value:string}>
	 */
	public static function format_activity_detail_fields( $row, $details, $context, $user_name ) {
		if ( ! is_array( $details ) ) {
			$details = array();
		}

		if ( ! is_array( $context ) ) {
			$context = array();
		}

		$fields = array();
		$action = $row['action'] ?? '';
		$run_id = (int) ( $row['run_id'] ?? 0 );
		$run    = $run_id && ! empty( $context['runs'][ $run_id ] ) ? $context['runs'][ $run_id ] : null;
		$used   = array();

		self::append_detail_field( $fields, __( 'Logged at', LAUNCHDEK_TEXT_DOMAIN ), self::format_activity_datetime( $row['created_at'] ?? '' ) );

		if ( $run_id ) {
			self::append_detail_field( $fields, __( 'Run', LAUNCHDEK_TEXT_DOMAIN ), '#' . $run_id );
			$used['run_id'] = true;
		}

		if ( is_array( $run ) ) {
			if ( ! empty( $run['checklist_title'] ) ) {
				self::append_detail_field( $fields, __( 'Checklist', LAUNCHDEK_TEXT_DOMAIN ), $run['checklist_title'] );
				$used['checklist'] = true;
				$used['workflow']  = true;
			}
			if ( ! empty( $run['status'] ) ) {
				self::append_detail_field( $fields, __( 'Run status', LAUNCHDEK_TEXT_DOMAIN ), $run['status'] );
			}
			if ( ! empty( $run['site_name'] ) ) {
				self::append_detail_field( $fields, __( 'Site', LAUNCHDEK_TEXT_DOMAIN ), $run['site_name'] );
			}
		}

		switch ( $action ) {
			case 'run_status_changed':
				if ( ! empty( $details['status'] ) ) {
					self::append_detail_field( $fields, __( 'New status', LAUNCHDEK_TEXT_DOMAIN ), $details['status'] );
					$used['status'] = true;
				}
				if ( $user_name ) {
					self::append_detail_field( $fields, __( 'Recorded by', LAUNCHDEK_TEXT_DOMAIN ), $user_name );
				}
				break;

			case 'manual_step_completed':
			case 'manual_step_uncompleted':
			case 'client_step_completed':
			case 'client_step_uncompleted':
			case 'client_step_note_added':
				self::append_step_detail_fields( $fields, $run_id, $details, $context, $user_name, $action );
				$used['step_index'] = true;
				$used['step_title'] = true;
				$used['client_user'] = true;
				$used['client_user_email'] = true;
				$used['client_id'] = true;
				if ( 'client_step_note_added' === $action ) {
					if ( ! empty( $details['note_preview'] ) ) {
						self::append_detail_field( $fields, __( 'Note', LAUNCHDEK_TEXT_DOMAIN ), $details['note_preview'] );
						$used['note_preview'] = true;
					}
					if ( ! empty( $details['has_attachment'] ) ) {
						self::append_detail_field( $fields, __( 'Screenshot attached', LAUNCHDEK_TEXT_DOMAIN ), true );
						$used['has_attachment'] = true;
					}
				}
				break;

			case 'api_step_executed':
				if ( ! empty( $details['method'] ) ) {
					self::append_detail_field( $fields, __( 'HTTP method', LAUNCHDEK_TEXT_DOMAIN ), $details['method'] );
					$used['method'] = true;
				}
				if ( ! empty( $details['route'] ) ) {
					self::append_detail_field( $fields, __( 'API route', LAUNCHDEK_TEXT_DOMAIN ), $details['route'] );
					$used['route'] = true;
				}
				if ( isset( $details['code'] ) ) {
					self::append_detail_field( $fields, __( 'HTTP status', LAUNCHDEK_TEXT_DOMAIN ), (int) $details['code'] );
					$used['code'] = true;
				}
				if ( ! empty( $details['excluded_fields'] ) && is_array( $details['excluded_fields'] ) ) {
					self::append_detail_field(
						$fields,
						__( 'Excluded fields', LAUNCHDEK_TEXT_DOMAIN ),
						implode( ', ', array_map( 'sanitize_text_field', $details['excluded_fields'] ) )
					);
					$used['excluded_fields'] = true;
				}
				if ( ! empty( $details['payload'] ) && is_array( $details['payload'] ) ) {
					self::append_detail_field( $fields, __( 'Request body', LAUNCHDEK_TEXT_DOMAIN ), $details['payload'] );
					$used['payload'] = true;
				}
				if ( isset( $details['step_index'] ) ) {
					self::append_step_detail_fields( $fields, $run_id, $details, $context, $user_name, $action );
					$used['step_index'] = true;
					$used['step_title'] = true;
				}
				break;

			case 'connection_test':
				if ( isset( $details['success'] ) ) {
					self::append_detail_field( $fields, __( 'Connection', LAUNCHDEK_TEXT_DOMAIN ), ! empty( $details['success'] ) ? __( 'Successful', LAUNCHDEK_TEXT_DOMAIN ) : __( 'Failed', LAUNCHDEK_TEXT_DOMAIN ) );
					$used['success'] = true;
				}
				break;

			case 'drift_verified':
				$count = (int) ( $details['count'] ?? 0 );
				self::append_detail_field( $fields, __( 'Drift count', LAUNCHDEK_TEXT_DOMAIN ), $count );
				$used['count'] = true;
				if ( ! empty( $details['drifts'] ) && is_array( $details['drifts'] ) ) {
					foreach ( $details['drifts'] as $index => $drift ) {
						if ( ! is_array( $drift ) ) {
							continue;
						}
						$label = ! empty( $drift['label'] ) ? (string) $drift['label'] : sprintf(
							/* translators: %d: drift item number */
							__( 'Check %d', LAUNCHDEK_TEXT_DOMAIN ),
							$index + 1
						);
						if ( 'drift' === ( $drift['status'] ?? '' ) ) {
							self::append_detail_field(
								$fields,
								$label,
								sprintf(
									'expected %1$s · actual %2$s',
									wp_json_encode( $drift['expected'] ?? null ),
									wp_json_encode( $drift['actual'] ?? null )
								)
							);
						} else {
							self::append_detail_field( $fields, $label, $drift['message'] ?? '' );
						}
					}
					$used['drifts'] = true;
				}
				break;

			case 'integration_sync':
			case 'integration_push':
				if ( ! empty( $details['results'] ) ) {
					self::append_detail_field( $fields, __( 'Results', LAUNCHDEK_TEXT_DOMAIN ), $details['results'] );
					$used['results'] = true;
				}
				break;
		}

		$labels = self::get_detail_field_labels();
		foreach ( $details as $key => $value ) {
			if ( isset( $used[ $key ] ) ) {
				continue;
			}
			if ( is_array( $value ) || is_object( $value ) ) {
				if ( in_array( $key, array( 'payload', 'results', 'drifts', 'client_panel' ), true ) ) {
					continue;
				}
				continue;
			}
			if ( '' === $value || null === $value ) {
				continue;
			}

			$label = $labels[ $key ] ?? ucwords( str_replace( '_', ' ', (string) $key ) );
			self::append_detail_field( $fields, $label, $value );
		}

		return $fields;
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
	private static function status_where_clause( $status, $table = '' ) {
		$prefix = $table ? $table . '.' : '';

		switch ( sanitize_key( $status ) ) {
			case 'success':
				return "({$prefix}action IN ('run_started', 'manual_step_completed', 'client_step_completed', 'client_step_note_added', 'site_created', 'site_updated', 'checklist_created', 'checklist_updated', 'workflow_created', 'workflow_updated') OR ({$prefix}action = 'run_status_changed' AND {$prefix}details_json LIKE '%\"status\":\"completed\"%') OR ({$prefix}action = 'connection_test' AND {$prefix}details_json LIKE '%\"success\":true%') OR ({$prefix}action = 'drift_verified' AND {$prefix}details_json LIKE '%\"count\":0%'))";

			case 'failed':
				return "({$prefix}action LIKE '%failed%' OR ({$prefix}action = 'run_status_changed' AND {$prefix}details_json LIKE '%\"status\":\"failed\"%') OR ({$prefix}action = 'connection_test' AND {$prefix}details_json LIKE '%\"success\":false%') OR ({$prefix}action = 'drift_verified' AND {$prefix}details_json LIKE '%\"status\":\"error\"%'))";

			case 'warning':
				return "({$prefix}action = 'drift_verified' AND {$prefix}details_json NOT LIKE '%\"count\":0%' AND {$prefix}details_json LIKE '%\"drifts\":[%')";

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
	public static function format_details_summary( $action, $details, $context = array(), $row = array() ) {
		if ( ! is_array( $details ) ) {
			return '';
		}

		$run_id = isset( $row['run_id'] ) ? (int) $row['run_id'] : 0;

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
				$step_title = self::resolve_step_title_from_context( $run_id, $details, $context );
				return $step_title ? sprintf(
					/* translators: %s: step title */
					__( 'Step: %s', LAUNCHDEK_TEXT_DOMAIN ),
					$step_title
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
				$status = $details['status'] ?? '';
				if ( $run_id && ! empty( $context['runs'][ $run_id ]['checklist_title'] ) ) {
					if ( 'completed' === $status ) {
						return sprintf(
							/* translators: %s: checklist title */
							__( 'Checklist completed: %s', LAUNCHDEK_TEXT_DOMAIN ),
							$context['runs'][ $run_id ]['checklist_title']
						);
					}
					if ( 'failed' === $status ) {
						return sprintf(
							/* translators: %s: checklist title */
							__( 'Checklist failed: %s', LAUNCHDEK_TEXT_DOMAIN ),
							$context['runs'][ $run_id ]['checklist_title']
						);
					}
					return sprintf(
						/* translators: 1: checklist title, 2: run status */
						__( 'Checklist %1$s — status %2$s', LAUNCHDEK_TEXT_DOMAIN ),
						$context['runs'][ $run_id ]['checklist_title'],
						$status
					);
				}
				return sprintf(
					/* translators: %s: run status */
					__( 'Status changed to %s', LAUNCHDEK_TEXT_DOMAIN ),
					$status
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
				$step_title = self::resolve_step_title_from_context( $run_id, $details, $context );
				$client     = $details['client_user'] ?? '';
				if ( $step_title && $client ) {
					return sprintf(
						/* translators: 1: step title, 2: client user name */
						__( 'Step %1$s completed by %2$s', LAUNCHDEK_TEXT_DOMAIN ),
						$step_title,
						$client
					);
				}
				return trim( $step_title . ( $client ? ' — ' . $client : '' ) );

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
	 * @param bool  $lean_list       Skip heavy detail field formatting (lazy-loaded in UI).
	 * @return array
	 */
	public static function format_row( $row, $context = array(), $include_details = true, $lean_list = false ) {
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

		$action_label = self::format_action_label( $row['action'] );
		if ( 'run_status_changed' === $row['action'] ) {
			$run_status = $details['status'] ?? '';
			if ( 'completed' === $run_status ) {
				$action_label = __( 'Checklist completed', LAUNCHDEK_TEXT_DOMAIN );
			} elseif ( 'failed' === $run_status ) {
				$action_label = __( 'Checklist failed', LAUNCHDEK_TEXT_DOMAIN );
			}
		}

		$formatted = array(
			'id'              => (int) $row['id'],
			'user_id'         => $user_id,
			'user_name'       => $user_name,
			'site_id'         => $site_id,
			'site_name'       => self::resolve_site_name( $site_id, $run_id, $details, $context ),
			'run_id'          => $run_id,
			'action'          => $row['action'],
			'action_label'    => $action_label,
			'details_summary' => self::format_details_summary( $row['action'], $details, $context, $row ),
			'detail_fields'   => $lean_list ? array() : self::format_activity_detail_fields( $row, $details, $context, $user_name ),
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

		$message_entry            = $formatted;
		$message_entry['details'] = $details;
		$formatted['message']     = self::format_feed_message( $message_entry, false );

		unset( $formatted['run_summary'] );

		$formatted['show_run_timeline'] = $run_id > 0 && self::log_supports_run_timeline( $row['action'] );

		return $formatted;
	}

	/**
	 * Whether an audit action can show a run step completion timeline.
	 *
	 * @param string $action Action slug.
	 * @return bool
	 */
	public static function log_supports_run_timeline( $action ) {
		$actions = array(
			'run_started',
			'run_status_changed',
			'manual_step_completed',
			'manual_step_uncompleted',
			'client_step_completed',
			'client_step_uncompleted',
			'client_step_note_added',
			'api_step_executed',
			'client_run_pushed',
		);

		return in_array( $action, $actions, true );
	}

	/**
	 * Completed steps for a checklist run (Activity Logs expandable timeline).
	 *
	 * @param int $run_id Run ID.
	 * @return array<int, array{step_index:int,step_number:int,title:string,step_type:string,step_type_label:string,http_code:int,completed_at:string,completed_at_formatted:string,completed_by:string}>
	 */
	public static function get_run_steps_timeline( $run_id ) {
		$run_id = absint( $run_id );
		if ( ! $run_id ) {
			return array();
		}

		$run = LAUNCHDEK_Run_Repository::find( $run_id );
		if ( ! $run || empty( $run['steps'] ) || ! is_array( $run['steps'] ) ) {
			return array();
		}

		$actors = self::load_run_step_completion_actors( $run_id );
		$out    = array();

		foreach ( $run['steps'] as $step ) {
			if ( ! is_array( $step ) || 'completed' !== ( $step['status'] ?? '' ) ) {
				continue;
			}

			$index = (int) ( $step['step_index'] ?? 0 );
			$by    = '';

			if ( ! empty( $step['response']['completed_by'] ) && is_array( $step['response']['completed_by'] ) ) {
				$by = sanitize_text_field( $step['response']['completed_by']['name'] ?? '' );
				if ( '' === $by && ! empty( $step['response']['completed_by']['email'] ) ) {
					$by = sanitize_email( $step['response']['completed_by']['email'] );
				}
			}

			if ( '' === $by && ! empty( $actors[ $index ] ) ) {
				$by = $actors[ $index ];
			}

			if ( '' === $by && 'api' === ( $step['step_type'] ?? '' ) ) {
				$by = __( 'Automated', LAUNCHDEK_TEXT_DOMAIN );
			}

			$completed_at = ! empty( $step['completed_at'] ) ? sanitize_text_field( $step['completed_at'] ) : '';
			$step_type    = sanitize_key( $step['step_type'] ?? 'manual' );
			$http_code    = 0;

			if ( 'api' === $step_type && ! empty( $step['response'] ) && is_array( $step['response'] ) && isset( $step['response']['code'] ) ) {
				$http_code = (int) $step['response']['code'];
			}

			$out[] = array(
				'step_index'             => $index,
				'step_number'            => $index + 1,
				'title'                  => sanitize_text_field( $step['title'] ?? '' ),
				'step_type'              => $step_type,
				'step_type_label'        => self::format_step_type_label( $step_type ),
				'http_code'              => $http_code,
				'completed_at'           => $completed_at,
				'completed_at_formatted' => self::format_activity_datetime( $completed_at ),
				'completed_by'           => $by,
			);
		}

		return $out;
	}

	/**
	 * Map run step indexes to the user who completed them (from audit entries).
	 *
	 * @param int $run_id Run ID.
	 * @return array<int, string>
	 */
	private static function load_run_step_completion_actors( $run_id ) {
		global $wpdb;

		$run_id = absint( $run_id );
		if ( ! $run_id ) {
			return array();
		}

		$table   = $wpdb->prefix . 'launchdek_audit_log';
		$actions = array( 'manual_step_completed', 'client_step_completed', 'api_step_executed' );
		$holders = implode( ',', array_fill( 0, count( $actions ), '%s' ) );
		$sql     = "SELECT user_id, action, details_json, created_at FROM {$table} WHERE run_id = %d AND action IN ({$holders}) ORDER BY created_at ASC";
		$vals    = array_merge( array( $run_id ), $actions );
		$rows    = $wpdb->get_results( $wpdb->prepare( $sql, $vals ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return array();
		}

		$user_ids = array();
		foreach ( $rows as $row ) {
			if ( ! empty( $row['user_id'] ) ) {
				$user_ids[] = (int) $row['user_id'];
			}
		}

		$user_map = self::load_users_map( array_values( array_unique( $user_ids ) ) );
		$map      = array();

		foreach ( $rows as $row ) {
			$details = json_decode( (string) ( $row['details_json'] ?? '' ), true );
			if ( ! is_array( $details ) || ! isset( $details['step_index'] ) ) {
				continue;
			}

			$index = (int) $details['step_index'];
			$actor = '';

			if ( 'client_step_completed' === ( $row['action'] ?? '' ) && ! empty( $details['client_user'] ) ) {
				$actor = sanitize_text_field( $details['client_user'] );
			} elseif ( ! empty( $row['user_id'] ) && ! empty( $user_map[ (int) $row['user_id'] ] ) ) {
				$actor = $user_map[ (int) $row['user_id'] ];
			}

			if ( $actor ) {
				$map[ $index ] = $actor;
			}
		}

		return $map;
	}
}
