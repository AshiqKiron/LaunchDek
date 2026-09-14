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

		if ( in_array( $status, array( 'completed', 'failed', 'cancelled' ), true ) ) {
			self::clear();
			return null;
		}

		$steps = array();
		foreach ( (array) ( $snapshot['steps'] ?? array() ) as $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}

			$notes = array();
			foreach ( (array) ( $step['notes'] ?? array() ) as $note ) {
				if ( ! is_array( $note ) ) {
					continue;
				}
				$sanitized = array(
					'user'       => sanitize_text_field( $note['user'] ?? '' ),
					'text'       => sanitize_textarea_field( $note['text'] ?? '' ),
					'created_at' => sanitize_text_field( $note['created_at'] ?? '' ),
				);
				$attachment_id = absint( $note['attachment_id'] ?? 0 );
				if ( $attachment_id > 0 ) {
					$sanitized['attachment_id'] = $attachment_id;
				}
				if ( ! empty( $note['attachment_url'] ) ) {
					$sanitized['attachment_url'] = esc_url_raw( $note['attachment_url'] );
				}
				$notes[] = $sanitized;
			}

			$steps[] = array(
				'step_index'   => absint( $step['step_index'] ?? 0 ),
				'title'        => sanitize_text_field( $step['title'] ?? '' ),
				'type'         => sanitize_key( $step['type'] ?? 'manual' ),
				'status'       => sanitize_key( $step['status'] ?? 'pending' ),
				'instructions' => sanitize_textarea_field( $step['instructions'] ?? '' ),
				'target_roles' => array_values( array_map( 'sanitize_key', (array) ( $step['target_roles'] ?? array() ) ) ),
				'deep_link'    => ! empty( $step['deep_link'] ) ? esc_url_raw( $step['deep_link'] ) : '',
				'notes'        => $notes,
			);
		}

		$sanitized = array(
			'run_id'          => absint( $snapshot['run_id'] ?? 0 ),
			'checklist_title' => sanitize_text_field( $snapshot['checklist_title'] ?? '' ),
			'run_status'      => $status,
			'hub_url'         => esc_url_raw( untrailingslashit( $snapshot['hub_url'] ?? '' ) ),
			'client_token'    => sanitize_text_field( $snapshot['client_token'] ?? '' ),
			'steps'           => $steps,
			'pushed_at'       => sanitize_text_field( $snapshot['pushed_at'] ?? '' ),
		);

		update_option( self::OPTION_KEY, $sanitized, false );

		return $sanitized;
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
			return user_can( $user, 'manage_options' );
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
			}
		}

		return $formatted;
	}
}
