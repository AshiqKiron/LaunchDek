<?php
/**
 * Active checklist run storage on the client site (mu-plugin).
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Persists the hub-pushed run snapshot locally.
 */
class LAUNCHDEK_Client_Run_Store {

	const OPTION_KEY = 'launchdek_client_active_run';

	/**
	 * Get the active run snapshot.
	 *
	 * @return array|null
	 */
	public static function get() {
		$run = get_option( self::OPTION_KEY, null );

		return is_array( $run ) ? $run : null;
	}

	/**
	 * Save a run snapshot from the hub.
	 *
	 * @param array $snapshot Run snapshot.
	 * @return array|null Sanitized snapshot or null when cleared.
	 */
	public static function save( $snapshot ) {
		if ( ! is_array( $snapshot ) ) {
			return null;
		}

		$status = sanitize_key( $snapshot['run_status'] ?? '' );

		if ( in_array( $status, array( 'failed', 'cancelled' ), true ) ) {
			self::clear();
			return null;
		}

		$existing = self::get();
		$steps    = array();
		foreach ( (array) ( $snapshot['steps'] ?? array() ) as $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}

			$local_notes = self::get_step_notes_from_run( $existing, $step['step_index'] ?? -1 );

			$notes = self::filter_step_notes( (array) ( $step['notes'] ?? array() ) );

			$notes = self::merge_step_notes( $local_notes, $notes );

			$step_type       = sanitize_key( $step['type'] ?? 'manual' );
			$show_note_field = array_key_exists( 'show_note_field', $step )
				? ! empty( $step['show_note_field'] )
				: ( 'manual' === $step_type );

			$completed_by = null;
			if ( ! empty( $step['completed_by'] ) && is_array( $step['completed_by'] ) ) {
				$completed_by = array(
					'name'  => sanitize_text_field( $step['completed_by']['name'] ?? '' ),
					'email' => sanitize_email( $step['completed_by']['email'] ?? '' ),
				);
				if ( '' === $completed_by['name'] && '' === $completed_by['email'] ) {
					$completed_by = null;
				}
			}

			$steps[] = array(
				'step_index'      => absint( $step['step_index'] ?? 0 ),
				'title'           => sanitize_text_field( $step['title'] ?? '' ),
				'type'            => $step_type,
				'status'          => sanitize_key( $step['status'] ?? 'pending' ),
				'instructions'    => sanitize_textarea_field( $step['instructions'] ?? '' ),
				'target_roles'    => array_values( array_map( 'sanitize_key', (array) ( $step['target_roles'] ?? array() ) ) ),
				'deep_link'       => self::resolve_step_deep_link( $step['deep_link'] ?? '', $step['admin_path'] ?? '' ),
				'admin_path'      => sanitize_text_field( $step['admin_path'] ?? '' ),
				'show_note_field' => $show_note_field,
				'manual_checked'  => ! empty( $step['manual_checked'] ),
				'notes'           => $notes,
				'completed_at'    => sanitize_text_field( $step['completed_at'] ?? '' ),
				'completed_by'    => $completed_by,
			);
		}

		$sanitized = array(
			'run_id'          => absint( $snapshot['run_id'] ?? 0 ),
			'checklist_title' => sanitize_text_field( $snapshot['checklist_title'] ?? '' ),
			'run_status'      => $status,
			'started_at'      => sanitize_text_field( $snapshot['started_at'] ?? ( $existing['started_at'] ?? $snapshot['pushed_at'] ?? '' ) ),
			'completed_at'    => sanitize_text_field( $snapshot['completed_at'] ?? ( $existing['completed_at'] ?? '' ) ),
			'hub_url'         => esc_url_raw( untrailingslashit( $snapshot['hub_url'] ?? '' ) ),
			'hub_rest_url'    => esc_url_raw( untrailingslashit( $snapshot['hub_rest_url'] ?? '' ) ),
			'client_token'    => sanitize_text_field( $snapshot['client_token'] ?? '' ),
			'panel_layout'    => self::sanitize_panel_layout( $snapshot['panel_layout'] ?? ( $existing['panel_layout'] ?? 'sidebar' ) ),
			'steps'           => $steps,
			'pushed_at'       => sanitize_text_field( $snapshot['pushed_at'] ?? '' ),
		);

		if ( 'completed' === $status && '' === $sanitized['completed_at'] ) {
			$sanitized['completed_at'] = current_time( 'mysql', true );
		}

		update_option( self::OPTION_KEY, $sanitized, false );

		return $sanitized;
	}

	/**
	 * Whether every step in the snapshot is completed.
	 *
	 * @param array|null $run Run snapshot.
	 * @return bool
	 */
	public static function all_steps_completed( $run = null ) {
		$run = is_array( $run ) ? $run : self::get();

		if ( ! $run || empty( $run['steps'] ) || ! is_array( $run['steps'] ) ) {
			return false;
		}

		foreach ( $run['steps'] as $step ) {
			if ( ! is_array( $step ) || 'completed' !== ( $step['status'] ?? '' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Clear the active run.
	 *
	 * @return void
	 */
	public static function clear() {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Whether the current user may complete a step.
	 *
	 * @param array   $step Step snapshot.
	 * @param WP_User $user Optional user.
	 * @return bool
	 */
	public static function user_can_complete_step( $step, $user = null ) {
		if ( ! is_array( $step ) ) {
			return false;
		}

		$user = $user instanceof WP_User ? $user : wp_get_current_user();

		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		$roles = array_filter( (array) ( $step['target_roles'] ?? array() ) );

		if ( empty( $roles ) ) {
			return is_user_logged_in();
		}

		foreach ( $roles as $role ) {
			if ( in_array( $role, (array) $user->roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find a step in the active run.
	 *
	 * @param int $step_index Step index.
	 * @return array|null
	 */
	public static function get_step( $step_index ) {
		$run = self::get();

		if ( ! $run ) {
			return null;
		}

		foreach ( (array) ( $run['steps'] ?? array() ) as $step ) {
			if ( (int) ( $step['step_index'] ?? -1 ) === (int) $step_index ) {
				return $step;
			}
		}

		return null;
	}

	/**
	 * Append a note to a step in the local run snapshot.
	 *
	 * @param int   $step_index Step index.
	 * @param array $note       Note payload.
	 * @return array|null Updated run snapshot.
	 */
	public static function add_step_note( $step_index, $note ) {
		$run = self::get();

		if ( ! $run ) {
			return null;
		}

		$entry = self::sanitize_step_note( $note );

		if ( null === $entry ) {
			return null;
		}

		foreach ( (array) ( $run['steps'] ?? array() ) as $index => $step ) {
			if ( (int) ( $step['step_index'] ?? -1 ) !== (int) $step_index ) {
				continue;
			}

			$notes = self::filter_step_notes( $step['notes'] ?? null );

			foreach ( $notes as $existing ) {
				if ( self::step_notes_match( $existing, $entry ) ) {
					return $run;
				}
			}

			$notes[]                         = $entry;
			$run['steps'][ $index ]['notes'] = $notes;
			update_option( self::OPTION_KEY, $run, false );

			return $run;
		}

		return null;
	}

	/**
	 * Merge hub run step statuses into the local snapshot when a full snapshot is unavailable.
	 *
	 * @param array $hub_run Hub run payload from a callback response.
	 * @return array|null Updated local snapshot.
	 */
	public static function patch_from_hub_run( $hub_run ) {
		$local = self::get();

		if ( ! $local || ! is_array( $hub_run ) || empty( $hub_run['steps'] ) || ! is_array( $hub_run['steps'] ) ) {
			return $local;
		}

		$hub_steps = array();
		foreach ( $hub_run['steps'] as $hub_step ) {
			if ( ! is_array( $hub_step ) ) {
				continue;
			}
			$hub_steps[ (int) ( $hub_step['step_index'] ?? -1 ) ] = $hub_step;
		}

		foreach ( $local['steps'] as $index => $local_step ) {
			$key = (int) ( $local_step['step_index'] ?? -1 );
			if ( ! isset( $hub_steps[ $key ] ) ) {
				continue;
			}

			$hub_step = $hub_steps[ $key ];
			$local['steps'][ $index ]['status'] = sanitize_key( $hub_step['status'] ?? $local_step['status'] );
			$local['steps'][ $index ]['manual_checked'] = ! empty( $hub_step['manual_checked'] );

			if ( ! empty( $hub_step['completed_at'] ) ) {
				$local['steps'][ $index ]['completed_at'] = sanitize_text_field( $hub_step['completed_at'] );
			} else {
				$local['steps'][ $index ]['completed_at'] = '';
			}

			$response = is_array( $hub_step['response'] ?? null ) ? $hub_step['response'] : array();
			if ( ! empty( $response['completed_by'] ) && is_array( $response['completed_by'] ) ) {
				$local['steps'][ $index ]['completed_by'] = array(
					'name'  => sanitize_text_field( $response['completed_by']['name'] ?? '' ),
					'email' => sanitize_email( $response['completed_by']['email'] ?? '' ),
				);
			} elseif ( 'completed' !== ( $local['steps'][ $index ]['status'] ?? '' ) ) {
				unset( $local['steps'][ $index ]['completed_by'] );
			}

			if ( is_array( $hub_step['notes'] ?? null ) ) {
				$local_notes = is_array( $local_step['notes'] ?? null ) ? $local_step['notes'] : array();
				$local['steps'][ $index ]['notes'] = self::merge_step_notes( $local_notes, $hub_step['notes'] );
			}
		}

		if ( ! empty( $hub_run['status'] ) ) {
			$local['run_status'] = sanitize_key( $hub_run['status'] );
		}

		if ( ! empty( $hub_run['started_at'] ) ) {
			$local['started_at'] = sanitize_text_field( $hub_run['started_at'] );
		}

		if ( ! empty( $hub_run['completed_at'] ) ) {
			$local['completed_at'] = sanitize_text_field( $hub_run['completed_at'] );
		} elseif ( 'completed' === ( $local['run_status'] ?? '' ) && empty( $local['completed_at'] ) ) {
			$local['completed_at'] = current_time( 'mysql', true );
		}

		update_option( self::OPTION_KEY, $local, false );

		return $local;
	}

	/**
	 * Read notes for a step from a run snapshot.
	 *
	 * @param array|null $run        Run snapshot.
	 * @param int        $step_index Step index.
	 * @return array
	 */
	protected static function get_step_notes_from_run( $run, $step_index ) {
		if ( ! is_array( $run ) ) {
			return array();
		}

		foreach ( (array) ( $run['steps'] ?? array() ) as $step ) {
			if ( (int) ( $step['step_index'] ?? -1 ) === (int) $step_index ) {
				return is_array( $step['notes'] ?? null ) ? $step['notes'] : array();
			}
		}

		return array();
	}

	/**
	 * Merge local and incoming step notes without dropping client-only entries.
	 *
	 * @param array $local_notes    Existing notes on the client snapshot.
	 * @param array $incoming_notes Notes from the hub snapshot.
	 * @return array
	 */
	protected static function merge_step_notes( $local_notes, $incoming_notes ) {
		return self::filter_step_notes(
			array_merge(
				self::filter_step_notes( $incoming_notes ),
				self::filter_step_notes( $local_notes )
			)
		);
	}

	/**
	 * Sanitize a user-authored note entry.
	 *
	 * @param mixed $note Raw note payload.
	 * @return array|null
	 */
	protected static function sanitize_step_note( $note ) {
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
	 * Keep only valid, de-duplicated notes.
	 *
	 * @param mixed $notes Raw notes array.
	 * @return array
	 */
	protected static function filter_step_notes( $notes ) {
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
	 * Compare notes by author/content rather than sync timestamps.
	 *
	 * @param array $left  First note.
	 * @param array $right Second note.
	 * @return bool
	 */
	protected static function step_notes_match( $left, $right ) {
		return self::note_fingerprint( $left ) === self::note_fingerprint( $right );
	}

	/**
	 * Build a stable fingerprint for deduplicating step notes.
	 *
	 * @param array $note Note payload.
	 * @return string
	 */
	protected static function note_fingerprint( $note ) {
		if ( ! is_array( $note ) ) {
			return '';
		}

		return md5(
			strtolower( trim( (string) ( $note['user'] ?? '' ) ) ) . '|' .
			trim( (string) ( $note['text'] ?? '' ) ) . '|' .
			(string) absint( $note['attachment_id'] ?? 0 )
		);
	}

	/**
	 * Resolve a step deep link to a local wp-admin URL.
	 *
	 * @param string $link       Full URL, admin path, or empty string.
	 * @param string $admin_path Fallback admin path from the hub snapshot.
	 * @return string
	 */
	public static function resolve_step_deep_link( $link, $admin_path = '' ) {
		$link = trim( (string) $link );

		if ( '' !== $link ) {
			if ( filter_var( $link, FILTER_VALIDATE_URL ) ) {
				return esc_url_raw( $link );
			}

			return esc_url_raw( admin_url( ltrim( $link, '/' ) ) );
		}

		$admin_path = trim( (string) $admin_path );

		if ( '' === $admin_path ) {
			return '';
		}

		return esc_url_raw( admin_url( ltrim( $admin_path, '/' ) ) );
	}

	/**
	 * Sanitize a panel layout slug from the hub snapshot.
	 *
	 * @param mixed $layout Raw layout value.
	 * @return string
	 */
	public static function sanitize_panel_layout( $layout ) {
		$layout = sanitize_key( (string) $layout );
		$valid  = array(
			'sidebar',
			'live_topbar',
			'bottom_dock',
			'floating_pill',
			'toast',
			'admin_menu',
			'fullscreen',
			'focus_mode',
			'inline_metabox',
		);

		return in_array( $layout, $valid, true ) ? $layout : 'sidebar';
	}

	/**
	 * Get the panel layout for a run snapshot.
	 *
	 * @param array|null $run Run snapshot.
	 * @return string
	 */
	public static function get_panel_layout( $run = null ) {
		if ( null === $run ) {
			$run = self::get();
		}

		if ( ! is_array( $run ) ) {
			return 'sidebar';
		}

		return self::sanitize_panel_layout( $run['panel_layout'] ?? 'sidebar' );
	}

	/**
	 * Prepare a run snapshot for API/UI output.
	 *
	 * @param array|null $run Run snapshot.
	 * @param bool       $include_token Whether to include the hub callback token.
	 * @return array|null
	 */
	public static function format_for_api( $run, $include_token = false ) {
		if ( ! is_array( $run ) ) {
			return null;
		}

		$formatted = $run;

		if ( ! $include_token ) {
			unset( $formatted['client_token'] );
		}

		if ( ! empty( $formatted['steps'] ) && is_array( $formatted['steps'] ) ) {
			foreach ( $formatted['steps'] as $index => $step ) {
				$formatted['steps'][ $index ]['can_complete'] = self::user_can_complete_step( $step );
				$formatted['steps'][ $index ]['notes']         = self::filter_step_notes( $step['notes'] ?? null );
				$formatted['steps'][ $index ]['deep_link']     = self::resolve_step_deep_link(
					$step['deep_link'] ?? '',
					$step['admin_path'] ?? ''
				);
			}
		}

		return $formatted;
	}
}
