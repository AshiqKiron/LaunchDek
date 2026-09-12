<?php
/**
 * Executes individual workflow steps.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Step executor.
 */
class LAUNCHDEK_Step_Executor {

	/**
	 * Execute a step within a run context.
	 *
	 * @param int   $run_id     Run ID.
	 * @param int   $step_index Step index.
	 * @param array $step_def   Step definition from workflow.
	 * @param int   $site_id    Site ID.
	 * @return array
	 */
	public static function execute( $run_id, $step_index, $step_def, $site_id ) {
		LAUNCHDEK_Run_Repository::update_step(
			$run_id,
			$step_index,
			array(
				'status'     => 'running',
				'started_at' => current_time( 'mysql', true ),
			)
		);

		$type = $step_def['type'] ?? 'manual';

		if ( 'api' === $type ) {
			$result = LAUNCHDEK_Payload_Mapper::execute( $site_id, $step_def );

			if ( is_wp_error( $result ) ) {
				LAUNCHDEK_Run_Repository::update_step(
					$run_id,
					$step_index,
					array(
						'status'        => 'failed',
						'error_message' => $result->get_error_message(),
						'completed_at'  => current_time( 'mysql', true ),
					)
				);

				LAUNCHDEK_Webhook_Dispatcher::dispatch( 'step_failed', array(
					'run_id'      => $run_id,
					'step_index'  => $step_index,
					'step_title'  => $step_def['title'] ?? '',
					'error'       => $result->get_error_message(),
				) );

				return array(
					'success' => false,
					'message' => $result->get_error_message(),
				);
			}

			LAUNCHDEK_Run_Repository::update_step(
				$run_id,
				$step_index,
				array(
					'status'       => 'completed',
					'response'     => $result,
					'completed_at' => current_time( 'mysql', true ),
				)
			);

			return array(
				'success'  => true,
				'response' => $result,
			);
		}

		// Manual steps require explicit checkmark.
		$client = LAUNCHDEK_Remote_Client::from_site( $site_id );
		$link   = '';

		if ( $client && ! empty( $step_def['deep_link'] ) ) {
			$link = $client->admin_link( $step_def['deep_link'] );
		}

		LAUNCHDEK_Run_Repository::update_step(
			$run_id,
			$step_index,
			array(
				'status' => 'awaiting_manual',
				'response' => array( 'deep_link' => $link ),
			)
		);

		return array(
			'success'   => true,
			'manual'    => true,
			'deep_link' => $link,
		);
	}

	/**
	 * Mark a manual step complete.
	 *
	 * @param int $run_id     Run ID.
	 * @param int $step_index Step index.
	 * @return bool
	 */
	public static function mark_manual_complete( $run_id, $step_index ) {
		$result = LAUNCHDEK_Run_Repository::update_step(
			$run_id,
			$step_index,
			array(
				'status'         => 'completed',
				'manual_checked' => true,
				'completed_at'   => current_time( 'mysql', true ),
			)
		);

		if ( $result ) {
			LAUNCHDEK_Audit_Log::log(
				'manual_step_completed',
				array( 'step_index' => $step_index ),
				0,
				$run_id
			);
		}

		return $result;
	}
}
