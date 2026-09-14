<?php
/**
 * Push checklist runs to the client checklist panel (mu-plugin) on remote sites.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hub → client checklist panel sync.
 */
class LAUNCHDEK_Client_Push {

	const CLIENT_INFO_ROUTE = '/launchdek/v1/client/info';
	const CLIENT_RUN_ROUTE  = '/launchdek/v1/client/run';

	/**
	 * Whether the remote site has the client checklist panel installed.
	 *
	 * @param int $site_id Site ID.
	 * @return bool
	 */
	public static function panel_available( $site_id ) {
		$client = LAUNCHDEK_Remote_Client::from_site( $site_id );

		if ( ! $client ) {
			return false;
		}

		return LAUNCHDEK_Mu_Plugin_Installer::panel_available( $client );
	}

	/**
	 * @deprecated 1.0.7 Use panel_available().
	 * @param int $site_id Site ID.
	 * @return bool
	 */
	public static function agent_available( $site_id ) {
		return self::panel_available( $site_id );
	}

	/**
	 * Prepare a run for the client panel: auto API steps, then first manual step.
	 *
	 * @param int $run_id Run ID.
	 * @return void
	 */
	public static function prepare_run_for_client( $run_id ) {
		LAUNCHDEK_Checklist_Runner::execute_all_auto( $run_id );
		self::advance_to_client_actionable( $run_id );
	}

	/**
	 * Advance run until a manual step is awaiting client action or run is done.
	 *
	 * @param int $run_id Run ID.
	 * @return void
	 */
	protected static function advance_to_client_actionable( $run_id ) {
		$guard = 0;

		while ( $guard < 50 ) {
			++$guard;
			$run = LAUNCHDEK_Run_Repository::find( $run_id );

			if ( ! $run ) {
				break;
			}

			foreach ( $run['steps'] as $step ) {
				if ( 'awaiting_manual' === $step['status'] ) {
					return;
				}
			}

			$has_pending = false;
			foreach ( $run['steps'] as $step ) {
				if ( 'pending' === $step['status'] ) {
					$has_pending = true;
					break;
				}
			}

			if ( ! $has_pending ) {
				break;
			}

			$result = LAUNCHDEK_Checklist_Runner::execute_next( $run_id );

			if ( is_wp_error( $result ) ) {
				break;
			}

			if ( ! empty( $result['manual'] ) ) {
				break;
			}

			if ( empty( $result['success'] ) ) {
				break;
			}
		}
	}

	/**
	 * Build a client-safe run snapshot (includes run token for hub callbacks).
	 *
	 * @param int $run_id Run ID.
	 * @return array|WP_Error
	 */
	public static function build_snapshot( $run_id ) {
		$run = LAUNCHDEK_Run_Repository::find( $run_id );

		if ( ! $run ) {
			return new WP_Error( 'launchdek_run_not_found', __( 'Run not found.', LAUNCHDEK_TEXT_DOMAIN ) );
		}

		$checklist = LAUNCHDEK_Checklist_Repository::find( $run['checklist_id'] );
		$token     = LAUNCHDEK_Run_Repository::get_client_token( $run_id );
		$remote    = LAUNCHDEK_Remote_Client::from_site( $run['site_id'] );
		$steps     = array();

		foreach ( $run['steps'] as $step ) {
			$def       = is_array( $checklist['steps'] ?? null ) ? ( $checklist['steps'][ $step['step_index'] ] ?? array() ) : array();
			$deep_link = '';

			if ( $remote && ! empty( $def['deep_link'] ) ) {
				$deep_link = $remote->admin_link( $def['deep_link'] );
			}

			$show_note_field = array_key_exists( 'show_note_field', $def )
				? ! empty( $def['show_note_field'] )
				: ( 'manual' === ( $step['step_type'] ?? 'manual' ) );

			$steps[] = array(
				'step_index'      => (int) $step['step_index'],
				'title'           => $step['title'],
				'type'            => $step['step_type'],
				'status'          => $step['status'],
				'instructions'    => sanitize_textarea_field( $def['instructions'] ?? '' ),
				'target_roles'    => array_values( array_map( 'sanitize_key', (array) ( $def['target_roles'] ?? array() ) ) ),
				'deep_link'       => $deep_link ? esc_url_raw( $deep_link ) : '',
				'show_note_field' => $show_note_field,
				'notes'           => is_array( $step['notes'] ?? null ) ? $step['notes'] : array(),
			);
		}

		return array(
			'run_id'          => (int) $run_id,
			'checklist_title' => $run['checklist_title'],
			'run_status'      => $run['status'],
			'hub_url'         => esc_url_raw( home_url( '/' ) ),
			'client_token'    => $token,
			'steps'           => $steps,
			'pushed_at'       => current_time( 'mysql', true ),
		);
	}

	/**
	 * Push a prepared run snapshot to the client agent.
	 *
	 * @param int  $run_id Run ID.
	 * @param bool $prepare Whether to auto-advance steps before pushing.
	 * @return array|WP_Error
	 */
	public static function push_run( $run_id, $prepare = true ) {
		$run = LAUNCHDEK_Run_Repository::find( $run_id );

		if ( ! $run ) {
			return new WP_Error( 'launchdek_run_not_found', __( 'Run not found.', LAUNCHDEK_TEXT_DOMAIN ) );
		}

		if ( ! self::panel_available( $run['site_id'] ) ) {
			return new WP_Error(
				'launchdek_no_client_panel',
				__( 'Client checklist panel is not installed on this site.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => 404 )
			);
		}

		if ( $prepare ) {
			self::prepare_run_for_client( $run_id );
		}

		$snapshot = self::build_snapshot( $run_id );

		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}

		$client = LAUNCHDEK_Remote_Client::from_site( $run['site_id'] );

		if ( ! $client ) {
			return new WP_Error( 'launchdek_no_client', __( 'Unable to connect to remote site.', LAUNCHDEK_TEXT_DOMAIN ) );
		}

		$result = $client->rest( 'POST', self::CLIENT_RUN_ROUTE, $snapshot );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		LAUNCHDEK_Site_Repository::update(
			$run['site_id'],
			array(
				'client_agent' => 1,
			)
		);

		LAUNCHDEK_Audit_Log::log(
			'client_run_pushed',
			array(
				'run_id'    => $run_id,
				'checklist' => $run['checklist_title'],
			),
			$run['site_id'],
			$run_id
		);

		return array(
			'success'  => true,
			'message'  => __( 'Checklist pushed to client admin panel.', LAUNCHDEK_TEXT_DOMAIN ),
			'run'      => LAUNCHDEK_Run_Repository::find( $run_id ),
			'snapshot' => $snapshot,
		);
	}

	/**
	 * Re-sync the current run state to the client agent (no step advancement).
	 *
	 * @param int $run_id Run ID.
	 * @return array|WP_Error|null Null when agent unavailable.
	 */
	public static function sync_run( $run_id ) {
		$run = LAUNCHDEK_Run_Repository::find( $run_id );

		if ( ! $run || ! self::panel_available( $run['site_id'] ) ) {
			return null;
		}

		return self::push_run( $run_id, false );
	}

	/**
	 * Handle a manual step completion initiated from the client agent.
	 *
	 * @param int   $run_id     Run ID.
	 * @param int   $step_index Step index.
	 * @param array $meta       Optional client metadata.
	 * @return array|WP_Error
	 */
	public static function complete_client_step( $run_id, $step_index, $meta = array() ) {
		$run = LAUNCHDEK_Run_Repository::find( $run_id );

		if ( ! $run ) {
			return new WP_Error( 'launchdek_run_not_found', __( 'Run not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}

		$step = null;
		foreach ( $run['steps'] as $row ) {
			if ( (int) $row['step_index'] === (int) $step_index ) {
				$step = $row;
				break;
			}
		}

		if ( ! $step ) {
			return new WP_Error( 'launchdek_step_not_found', __( 'Step not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}

		if ( 'manual' !== $step['step_type'] ) {
			return new WP_Error(
				'launchdek_step_not_manual',
				__( 'Only manual steps can be completed from the client panel.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => 400 )
			);
		}

		if ( 'awaiting_manual' !== $step['status'] ) {
			return new WP_Error(
				'launchdek_step_not_ready',
				__( 'This step is not ready to be completed yet.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => 409 )
			);
		}

		$ok = LAUNCHDEK_Step_Executor::mark_manual_complete( $run_id, $step_index );

		if ( ! $ok ) {
			return new WP_Error( 'launchdek_step_update_failed', __( 'Failed to update step.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 500 ) );
		}

		LAUNCHDEK_Audit_Log::log(
			'client_step_completed',
			array(
				'step_index'  => $step_index,
				'step_title'  => $step['title'],
				'client_user' => sanitize_text_field( $meta['client_user'] ?? '' ),
				'client_id'   => absint( $meta['client_user_id'] ?? 0 ),
			),
			$run['site_id'],
			$run_id
		);

		LAUNCHDEK_Webhook_Dispatcher::dispatch(
			'client_step_completed',
			array(
				'run_id'      => $run_id,
				'checklist'   => $run['checklist_title'],
				'site_id'     => $run['site_id'],
				'site_name'   => $run['site_name'],
				'step_index'  => $step_index,
				'step_title'  => $step['title'],
				'client_user' => sanitize_text_field( $meta['client_user'] ?? '' ),
			)
		);

		LAUNCHDEK_Checklist_Runner::execute_next( $run_id );
		LAUNCHDEK_Checklist_Runner::check_completion( $run_id );

		$run = LAUNCHDEK_Run_Repository::find( $run_id );

		if ( in_array( $run['status'], array( 'completed', 'failed', 'cancelled' ), true ) ) {
			self::sync_run( $run_id );
		} else {
			self::prepare_run_for_client( $run_id );
			self::sync_run( $run_id );
		}

		$snapshot = self::build_snapshot( $run_id );

		return array(
			'success'  => true,
			'run'      => LAUNCHDEK_Run_Repository::find( $run_id ),
			'snapshot' => is_wp_error( $snapshot ) ? null : $snapshot,
		);
	}

	/**
	 * Handle a step note added from the client panel.
	 *
	 * @param int   $run_id     Run ID.
	 * @param int   $step_index Step index.
	 * @param array $meta       Note metadata from client.
	 * @return array|WP_Error
	 */
	public static function add_client_step_note( $run_id, $step_index, $meta = array() ) {
		$run = LAUNCHDEK_Run_Repository::find( $run_id );

		if ( ! $run ) {
			return new WP_Error( 'launchdek_run_not_found', __( 'Run not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}

		if ( in_array( $run['status'], array( 'completed', 'failed', 'cancelled' ), true ) ) {
			return new WP_Error(
				'launchdek_run_closed',
				__( 'This checklist run is no longer active.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => 409 )
			);
		}

		$step = null;
		foreach ( $run['steps'] as $row ) {
			if ( (int) $row['step_index'] === (int) $step_index ) {
				$step = $row;
				break;
			}
		}

		if ( ! $step ) {
			return new WP_Error( 'launchdek_step_not_found', __( 'Step not found.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 404 ) );
		}

		$text = sanitize_textarea_field( $meta['text'] ?? '' );
		$attachment_id = absint( $meta['attachment_id'] ?? 0 );

		if ( '' === $text && ! $attachment_id ) {
			return new WP_Error(
				'launchdek_note_empty',
				__( 'A note must include text or a screenshot.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => 400 )
			);
		}

		$note = array(
			'user'       => sanitize_text_field( $meta['client_user'] ?? '' ),
			'text'       => $text,
			'created_at' => current_time( 'mysql', true ),
		);

		if ( $attachment_id > 0 ) {
			$note['attachment_id'] = $attachment_id;
			if ( ! empty( $meta['attachment_url'] ) ) {
				$note['attachment_url'] = esc_url_raw( $meta['attachment_url'] );
			}
		}

		$notes = LAUNCHDEK_Run_Repository::add_step_note( $run_id, $step_index, $note );

		if ( false === $notes ) {
			return new WP_Error( 'launchdek_note_save_failed', __( 'Failed to save note.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 500 ) );
		}

		LAUNCHDEK_Audit_Log::log(
			'client_step_note_added',
			array(
				'step_index'  => $step_index,
				'step_title'  => $step['title'],
				'client_user' => $note['user'],
				'client_id'   => absint( $meta['client_user_id'] ?? 0 ),
				'has_attachment' => $attachment_id > 0,
				'note_preview' => wp_trim_words( $text, 12, '…' ),
			),
			$run['site_id'],
			$run_id
		);

		LAUNCHDEK_Webhook_Dispatcher::dispatch(
			'client_note_added',
			array(
				'run_id'      => $run_id,
				'checklist'   => $run['checklist_title'],
				'site_id'     => $run['site_id'],
				'site_name'   => $run['site_name'],
				'step_index'  => $step_index,
				'step_title'  => $step['title'],
				'client_user' => $note['user'],
				'note'        => $text,
			)
		);

		self::sync_run( $run_id );

		$snapshot = self::build_snapshot( $run_id );

		return array(
			'success'  => true,
			'notes'    => $notes,
			'run'      => LAUNCHDEK_Run_Repository::find( $run_id ),
			'snapshot' => is_wp_error( $snapshot ) ? null : $snapshot,
		);
	}
}
