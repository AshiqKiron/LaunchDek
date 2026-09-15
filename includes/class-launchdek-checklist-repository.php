<?php
/**
 * Checklist persistence layer.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checklist repository.
 */
class LAUNCHDEK_Checklist_Repository {

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'launchdek_checklists';
	}

	/**
	 * Find checklist by ID.
	 *
	 * @param int $id Checklist ID.
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
	 * List checklists.
	 *
	 * @param array $args Filters.
	 * @return array
	 */
	public static function all( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'is_template' => null,
			'is_vault'    => null,
			'limit'       => 100,
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = self::table();
		$where = array( '1=1' );
		$vals  = array();

		if ( null !== $args['is_template'] ) {
			$where[] = 'is_template = %d';
			$vals[]  = $args['is_template'] ? 1 : 0;
		}

		if ( null !== $args['is_vault'] ) {
			$where[] = 'is_vault = %d';
			$vals[]  = $args['is_vault'] ? 1 : 0;
		}

		$sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY title ASC LIMIT %d';
		$vals[] = max( 1, absint( $args['limit'] ) );

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $vals ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $rows ) ? array_map( array( __CLASS__, 'format' ), $rows ) : array();
	}

	/**
	 * Lightweight checklist rows for list/grid UIs (no full step payloads).
	 *
	 * @param array $args Filters.
	 * @return array
	 */
	public static function summary_list( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'is_template' => null,
			'is_vault'    => null,
			'limit'       => 100,
		);

		$args  = wp_parse_args( $args, $defaults );
		$table = self::table();
		$where = array( '1=1' );
		$vals  = array();

		if ( null !== $args['is_template'] ) {
			$where[] = 'is_template = %d';
			$vals[]  = $args['is_template'] ? 1 : 0;
		}

		if ( null !== $args['is_vault'] ) {
			$where[] = 'is_vault = %d';
			$vals[]  = $args['is_vault'] ? 1 : 0;
		}

		$sql    = 'SELECT id, title, description, steps_json FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY title ASC LIMIT %d';
		$vals[] = max( 1, absint( $args['limit'] ) );

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $vals ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $rows ) ? array_map( array( __CLASS__, 'format_summary' ), $rows ) : array();
	}

	/**
	 * Format a row for summary list output.
	 *
	 * @param array $row Raw row with steps_json.
	 * @return array
	 */
	public static function format_summary( $row ) {
		$steps   = json_decode( (string) ( $row['steps_json'] ?? '' ), true );
		$preview = array();

		if ( is_array( $steps ) ) {
			foreach ( $steps as $step ) {
				if ( ! is_array( $step ) ) {
					continue;
				}

				$preview[] = array(
					'id'    => ! empty( $step['id'] ) ? sanitize_key( $step['id'] ) : '',
					'title' => sanitize_text_field( $step['title'] ?? '' ),
				);
			}
		}

		return array(
			'id'          => (int) $row['id'],
			'title'       => (string) $row['title'],
			'description' => (string) $row['description'],
			'steps_count' => count( $preview ),
			'steps'       => $preview,
		);
	}

	/**
	 * Lightweight id/title pairs for dashboard quick-launch pickers.
	 *
	 * @return array<int, array{id:int,title:string}>
	 */
	public static function picker_list() {
		global $wpdb;

		$table = self::table();
		$rows  = $wpdb->get_results(
			'SELECT id, title FROM ' . $table . ' WHERE is_template = 0 ORDER BY title ASC LIMIT 100', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static function ( $row ) {
				return array(
					'id'    => (int) $row['id'],
					'title' => (string) $row['title'],
				);
			},
			$rows
		);
	}

	/**
	 * Count active (non-template) checklists.
	 *
	 * @return int
	 */
	public static function count_active() {
		global $wpdb;

		return (int) $wpdb->get_var(
			'SELECT COUNT(*) FROM ' . self::table() . ' WHERE is_template = 0' // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
	}

	/**
	 * Create checklist.
	 *
	 * @param array $data Checklist data.
	 * @return int|false
	 */
	public static function create( $data ) {
		global $wpdb;

		$steps = self::normalize_steps( $data['steps'] ?? array() );

		$result = $wpdb->insert(
			self::table(),
			array(
				'title'         => sanitize_text_field( $data['title'] ?? __( 'Untitled Checklist', LAUNCHDEK_TEXT_DOMAIN ) ),
				'description'   => sanitize_textarea_field( $data['description'] ?? '' ),
				'steps_json'    => wp_json_encode( $steps ),
				'is_template'   => ! empty( $data['is_template'] ) ? 1 : 0,
				'is_vault'      => ! empty( $data['is_vault'] ) ? 1 : 0,
				'template_slug' => sanitize_key( $data['template_slug'] ?? '' ),
				'version'       => sanitize_text_field( $data['version'] ?? '1.0.0' ),
				'created_by'    => get_current_user_id(),
				'created_at'    => current_time( 'mysql', true ),
				'updated_at'    => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		$id = (int) $wpdb->insert_id;
		LAUNCHDEK_Audit_Log::log( 'checklist_created', array( 'checklist_id' => $id, 'title' => $data['title'] ?? '' ) );
		LAUNCHDEK_Dashboard_Cache::invalidate_stats();
		LAUNCHDEK_Dashboard_Cache::invalidate_quick_launch_picker();

		return $id;
	}

	/**
	 * Update checklist.
	 *
	 * @param int   $id   Checklist ID.
	 * @param array $data Data.
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		$fields = array( 'updated_at' => current_time( 'mysql', true ) );
		$format = array( '%s' );

		foreach ( array( 'title', 'description', 'template_slug', 'version' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$fields[ $key ] = 'description' === $key
					? sanitize_textarea_field( $data[ $key ] )
					: sanitize_text_field( $data[ $key ] );
				$format[]       = '%s';
			}
		}

		if ( isset( $data['steps'] ) ) {
			$fields['steps_json'] = wp_json_encode( self::normalize_steps( $data['steps'] ) );
			$format[]             = '%s';
		}

		foreach ( array( 'is_template', 'is_vault' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$fields[ $key ] = $data[ $key ] ? 1 : 0;
				$format[]       = '%d';
			}
		}

		$result = $wpdb->update( self::table(), $fields, array( 'id' => absint( $id ) ), $format, array( '%d' ) );

		if ( false !== $result ) {
			LAUNCHDEK_Audit_Log::log( 'checklist_updated', array( 'checklist_id' => $id ) );
			LAUNCHDEK_Dashboard_Cache::invalidate_stats();
			LAUNCHDEK_Dashboard_Cache::invalidate_quick_launch_picker();
		}

		return false !== $result;
	}

	/**
	 * Delete checklist.
	 *
	 * @param int $id Checklist ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;

		$result = $wpdb->delete( self::table(), array( 'id' => absint( $id ) ), array( '%d' ) );

		if ( $result ) {
			LAUNCHDEK_Audit_Log::log( 'checklist_deleted', array( 'checklist_id' => $id ) );
			LAUNCHDEK_Dashboard_Cache::invalidate_stats();
			LAUNCHDEK_Dashboard_Cache::invalidate_quick_launch_picker();
		}

		return (bool) $result;
	}

	/**
	 * Import checklist from array/JSON structure.
	 *
	 * @param array $payload Import payload.
	 * @return int|false
	 */
	public static function import( $payload ) {
		if ( empty( $payload['title'] ) && empty( $payload['steps'] ) ) {
			return false;
		}

		return self::create(
			array(
				'title'       => $payload['title'] ?? __( 'Imported Checklist', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => $payload['description'] ?? '',
				'steps'       => $payload['steps'] ?? array(),
				'version'     => $payload['version'] ?? '1.0.0',
				'is_template' => ! empty( $payload['is_template'] ),
				'is_vault'    => ! empty( $payload['is_vault'] ),
			)
		);
	}

	/**
	 * Export checklist as portable array.
	 *
	 * @param int $id Checklist ID.
	 * @return array|null
	 */
	public static function export( $id ) {
		$checklist = self::find( $id );

		if ( ! $checklist ) {
			return null;
		}

		unset( $checklist['id'], $checklist['created_at'], $checklist['updated_at'], $checklist['created_by'] );

		return $checklist;
	}

	/**
	 * Normalize and validate steps array.
	 *
	 * @param array $steps Raw steps.
	 * @return array
	 */
	public static function normalize_steps( $steps ) {
		if ( ! is_array( $steps ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $steps as $index => $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}

			$type = in_array( $step['type'] ?? 'manual', array( 'manual', 'api' ), true ) ? $step['type'] : 'manual';

			$target_roles = array();
			if ( ! empty( $step['target_roles'] ) && is_array( $step['target_roles'] ) ) {
				foreach ( $step['target_roles'] as $role_slug ) {
					$role_slug = sanitize_key( $role_slug );
					if ( $role_slug && get_role( $role_slug ) ) {
						$target_roles[] = $role_slug;
					}
				}
				$target_roles = array_values( array_unique( $target_roles ) );
			}

			$show_note_field = array_key_exists( 'show_note_field', $step )
				? ! empty( $step['show_note_field'] )
				: ( 'manual' === $type );

			$show_screenshot_field = array_key_exists( 'show_screenshot_field', $step )
				? ! empty( $step['show_screenshot_field'] )
				: ( 'manual' === $type && $show_note_field );

			$deep_link = LAUNCHDEK_Admin_Deep_Links::normalize_stored_path( $step['deep_link'] ?? '' );
			if ( '' === $deep_link ) {
				$deep_link = LAUNCHDEK_Admin_Deep_Links::resolve_path( $step );
			}

			$normalized[] = array(
				'id'              => ! empty( $step['id'] ) ? sanitize_key( $step['id'] ) : 'step_' . ( $index + 1 ),
				'title'           => sanitize_text_field( $step['title'] ?? sprintf( __( 'Step %d', LAUNCHDEK_TEXT_DOMAIN ), $index + 1 ) ),
				'instructions'    => sanitize_textarea_field( $step['instructions'] ?? '' ),
				'deep_link'       => $deep_link,
				'type'            => $type,
				'target_roles'          => $target_roles,
				'show_note_field'       => $show_note_field,
				'show_screenshot_field' => $show_screenshot_field,
				'api'                   => self::normalize_api_config( $step['api'] ?? array() ),
			);
		}

		return $normalized;
	}

	/**
	 * Normalize API step configuration.
	 *
	 * @param array $api API config.
	 * @return array
	 */
	protected static function normalize_api_config( $api ) {
		if ( ! is_array( $api ) ) {
			return array();
		}

		$method = strtoupper( sanitize_text_field( $api['method'] ?? 'GET' ) );
		if ( ! in_array( $method, array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
			$method = 'GET';
		}

		$payload = $api['payload'] ?? array();
		if ( is_string( $payload ) ) {
			$decoded = json_decode( $payload, true );
			$payload = is_array( $decoded ) ? $decoded : array();
		}

		return array(
			'method'  => $method,
			'route'   => sanitize_text_field( $api['route'] ?? '' ),
			'payload' => $payload,
		);
	}

	/**
	 * Format row for output.
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	public static function format( $row ) {
		$steps = json_decode( (string) $row['steps_json'], true );

		return array(
			'id'            => (int) $row['id'],
			'title'         => $row['title'],
			'description'   => $row['description'],
			'steps'         => is_array( $steps ) ? $steps : array(),
			'is_template'   => (bool) $row['is_template'],
			'is_vault'      => (bool) $row['is_vault'],
			'template_slug' => $row['template_slug'],
			'version'       => $row['version'],
			'created_by'    => (int) $row['created_by'],
			'created_at'    => $row['created_at'],
			'updated_at'    => $row['updated_at'],
		);
	}
}
