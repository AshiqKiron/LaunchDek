<?php
/**
 * HTTP client for hub callbacks from the client panel (mu-plugin).
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calls the LaunchDek hub using a run-scoped token.
 */
class LAUNCHDEK_Client_Hub_Client {

	/**
	 * Notify the hub that a manual step was completed on the client site.
	 *
	 * @param array   $run        Active run snapshot.
	 * @param int     $step_index Step index.
	 * @param WP_User $user       Completing user.
	 * @return array|WP_Error
	 */
	public static function complete_step( $run, $step_index, $user ) {
		$hub_url = untrailingslashit( $run['hub_url'] ?? '' );
		$run_id  = absint( $run['run_id'] ?? 0 );
		$token   = (string) ( $run['client_token'] ?? '' );

		if ( ! $hub_url || ! $run_id || '' === $token ) {
			return new WP_Error( 'launchdek_client_missing_hub', __( 'Hub connection details are missing for this checklist.', LAUNCHDEK_CLIENT_TEXT_DOMAIN ) );
		}

		$url = trailingslashit( $hub_url ) . 'wp-json/launchdek/v1/client-runs/' . $run_id . '/steps/' . absint( $step_index ) . '/complete';

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type'          => 'application/json',
					'Accept'                => 'application/json',
					'X-LaunchDek-Run-Token' => $token,
				),
				'body'    => wp_json_encode(
					array(
						'client_user'    => sanitize_text_field( $user->display_name ),
						'client_user_id' => (int) $user->ID,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $body ) && ! empty( $body['message'] )
				? $body['message']
				: __( 'The hub rejected the step completion request.', LAUNCHDEK_CLIENT_TEXT_DOMAIN );

			return new WP_Error( 'launchdek_client_hub_error', $message, array( 'status' => $code ) );
		}

		return is_array( $body ) ? $body : array( 'success' => true );
	}

	/**
	 * Send a step note to the hub.
	 *
	 * @param array   $run        Active run snapshot.
	 * @param int     $step_index Step index.
	 * @param WP_User $user       Note author.
	 * @param array   $payload    Note payload (text, attachment_id, attachment_url).
	 * @return array|WP_Error
	 */
	public static function add_step_note( $run, $step_index, $user, $payload = array() ) {
		$hub_url = untrailingslashit( $run['hub_url'] ?? '' );
		$run_id  = absint( $run['run_id'] ?? 0 );
		$token   = (string) ( $run['client_token'] ?? '' );

		if ( ! $hub_url || ! $run_id || '' === $token ) {
			return new WP_Error( 'launchdek_client_missing_hub', __( 'Hub connection details are missing for this checklist.', LAUNCHDEK_CLIENT_TEXT_DOMAIN ) );
		}

		$url = trailingslashit( $hub_url ) . 'wp-json/launchdek/v1/client-runs/' . $run_id . '/steps/' . absint( $step_index ) . '/notes';

		$body = array(
			'client_user'    => sanitize_text_field( $user->display_name ),
			'client_user_id' => (int) $user->ID,
			'text'           => sanitize_textarea_field( $payload['text'] ?? '' ),
		);

		$attachment_id = absint( $payload['attachment_id'] ?? 0 );
		if ( $attachment_id > 0 ) {
			$body['attachment_id'] = $attachment_id;
			if ( ! empty( $payload['attachment_url'] ) ) {
				$body['attachment_url'] = esc_url_raw( $payload['attachment_url'] );
			}
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type'          => 'application/json',
					'Accept'                => 'application/json',
					'X-LaunchDek-Run-Token' => $token,
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $body ) && ! empty( $body['message'] )
				? $body['message']
				: __( 'The hub rejected the note.', LAUNCHDEK_CLIENT_TEXT_DOMAIN );

			return new WP_Error( 'launchdek_client_hub_error', $message, array( 'status' => $code ) );
		}

		return is_array( $body ) ? $body : array( 'success' => true );
	}
}
