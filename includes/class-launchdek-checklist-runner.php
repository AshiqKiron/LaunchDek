<?php
/**
 * Checklist execution orchestrator.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checklist runner.
 */
class LAUNCHDEK_Checklist_Runner {

	/**
	 * Start a checklist run on a remote site.
	 *
	 * @param int $checklist_id Checklist ID.
	 * @param int $site_id     Site ID.
	 * @return array|WP_Error
	 */
	public static function start( $checklist_id, $site_id ) {
		$site = LAUNCHDEK_Site_Repository::find( $site_id );

		if ( ! $site ) {
			return new WP_Error( 'launchdek_site_not_found', __( 'Site not found.', LAUNCHDEK_TEXT_DOMAIN ) );
		}

		$checklist = LAUNCHDEK_Checklist_Repository::find( $checklist_id );

		if ( ! $checklist ) {
			return new WP_Error( 'launchdek_checklist_not_found', __( 'Checklist not found.', LAUNCHDEK_TEXT_DOMAIN ) );
		}

		$run_id = LAUNCHDEK_Run_Repository::create( $checklist_id, $site_id );

		if ( ! $run_id ) {
			return new WP_Error( 'launchdek_run_failed', __( 'Failed to create run.', LAUNCHDEK_TEXT_DOMAIN ) );
		}

		return array(
			'run_id'    => $run_id,
			'run'       => LAUNCHDEK_Run_Repository::find( $run_id ),
			'checklist' => $checklist,
		);
	}

	/**
	 * Start checklist runs for multiple sites.
	 *
	 * @param int   $checklist_id Checklist ID.
	 * @param int[] $site_ids    Site IDs.
	 * @return array
	 */
	public static function start_batch( $checklist_id, $site_ids ) {
		$runs   = array();
		$errors = array();

		foreach ( $site_ids as $site_id ) {
			$result = self::start( $checklist_id, $site_id );

			if ( is_wp_error( $result ) ) {
				$errors[] = array(
					'site_id' => $site_id,
					'message' => $result->get_error_message(),
				);
				continue;
			}

			$runs[] = $result;
		}

		return array(
			'runs'   => $runs,
			'errors' => $errors,
		);
	}

	/**
	 * Execute the next pending step in a run.
	 *
	 * @param int $run_id Run ID.
	 * @return array|WP_Error
	 */
	public static function execute_next( $run_id ) {
		$run = LAUNCHDEK_Run_Repository::find( $run_id );

		if ( ! $run ) {
			return new WP_Error( 'launchdek_run_not_found', __( 'Run not found.', LAUNCHDEK_TEXT_DOMAIN ) );
		}

		$checklist = LAUNCHDEK_Checklist_Repository::find( $run['checklist_id'] );

		if ( ! $checklist ) {
			return new WP_Error( 'launchdek_checklist_not_found', __( 'Checklist not found.', LAUNCHDEK_TEXT_DOMAIN ) );
		}

		foreach ( $run['steps'] as $step ) {
			if ( in_array( $step['status'], array( 'pending', 'running' ), true ) ) {
				$step_def = $checklist['steps'][ $step['step_index'] ] ?? array();
				$result   = LAUNCHDEK_Step_Executor::execute( $run_id, $step['step_index'], $step_def, $run['site_id'] );

				self::maybe_complete_run( $run_id );

				return array_merge( $result, array(
					'step_index' => $step['step_index'],
					'run'        => LAUNCHDEK_Run_Repository::find( $run_id ),
				) );
			}
		}

		self::check_completion( $run_id );

		return array(
			'success' => true,
			'message' => __( 'All steps processed.', LAUNCHDEK_TEXT_DOMAIN ),
			'run'     => LAUNCHDEK_Run_Repository::find( $run_id ),
		);
	}

	/**
	 * Public wrapper to finalize run status after step updates.
	 *
	 * @param int $run_id Run ID.
	 * @return void
	 */
	public static function check_completion( $run_id ) {
		self::maybe_complete_run( $run_id );
	}

	/**
	 * Execute all automated (API) steps sequentially.
	 *
	 * @param int $run_id Run ID.
	 * @return array
	 */
	public static function execute_all_auto( $run_id ) {
		$results = array();

		while ( true ) {
			$run = LAUNCHDEK_Run_Repository::find( $run_id );
			if ( ! $run ) {
				break;
			}

			$checklist  = LAUNCHDEK_Checklist_Repository::find( $run['checklist_id'] );
			$next_index = null;

			foreach ( $run['steps'] as $step ) {
				if ( 'pending' === $step['status'] ) {
					$step_def = $checklist['steps'][ $step['step_index'] ] ?? array();
					if ( ( $step_def['type'] ?? 'manual' ) === 'api' ) {
						$next_index = $step['step_index'];
						break;
					}
					break; // Stop at first manual step.
				}
			}

			if ( null === $next_index ) {
				break;
			}

			$step_def = $checklist['steps'][ $next_index ];
			$result   = LAUNCHDEK_Step_Executor::execute( $run_id, $next_index, $step_def, $run['site_id'] );
			$results[] = $result;

			if ( empty( $result['success'] ) ) {
				LAUNCHDEK_Run_Repository::update_status( $run_id, 'failed' );
				break;
			}
		}

		self::maybe_complete_run( $run_id );

		return array(
			'results' => $results,
			'run'     => LAUNCHDEK_Run_Repository::find( $run_id ),
		);
	}

	/**
	 * Complete run if all steps are done.
	 *
	 * @param int $run_id Run ID.
	 * @return void
	 */
	protected static function maybe_complete_run( $run_id ) {
		$run = LAUNCHDEK_Run_Repository::find( $run_id );

		if ( ! $run ) {
			return;
		}

		$all_done = true;
		$any_failed = false;

		foreach ( $run['steps'] as $step ) {
			if ( 'failed' === $step['status'] ) {
				$any_failed = true;
			}
			if ( ! in_array( $step['status'], array( 'completed', 'failed', 'skipped' ), true ) ) {
				$all_done = false;
			}
		}

		if ( $any_failed ) {
			LAUNCHDEK_Run_Repository::update_status( $run_id, 'failed' );
		} elseif ( $all_done ) {
			LAUNCHDEK_Run_Repository::update_status( $run_id, 'completed' );
		}
	}
}
