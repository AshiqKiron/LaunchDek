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
	 * Build a hub callback URL for a run step action.
	 *
	 * @param array  $run        Active run snapshot.
	 * @param string $path       Path relative to the LaunchDek REST namespace.
	 * @return string
	 */
	protected static function build_callback_url( $run, $path ) {
		$path = ltrim( $path, '/' );

		if ( ! empty( $run['hub_rest_url'] ) ) {
			return trailingslashit( untrailingslashit( $run['hub_rest_url'] ) ) . $path;
		}

		$hub_url = untrailingslashit( $run['hub_url'] ?? '' );

		return trailingslashit( $hub_url ) . 'wp-json/launchdek/v1/' . $path;
	}

	/**
	 * Shared POST helper for hub callbacks.
	 *
	 * @param array   $run        Active run snapshot.
	 * @param string  $path       REST path relative to the LaunchDek namespace.
	 * @param array   $payload    JSON body.
	 * @param WP_User $user       Acting user.
	 * @param string  $fallback   Error message when the hub response has no details.
	 * @return array|WP_Error
	 */
	protected static function post_to_hub( $run, $path, $payload, $user, $fallback ) {
		$run_id = absint( $run['run_id'] ?? 0 );
		$token  = (string) ( $run['client_token'] ?? '' );

		if ( ! $run_id || '' === $token ) {
			return new WP_Error( 'launchdek_client_missing_hub', __( 'Hub connection details are missing for this checklist.', LAUNCHDEK_CLIENT_TEXT_DOMAIN ) );
		}

		$body = array_merge(
			array(
				'client_user'       => sanitize_text_field( $user->display_name ),
				'client_user_id'    => (int) $user->ID,
				'client_user_email' => sanitize_email( $user->user_email ),
				'client_token'      => $token,
			),
			is_array( $payload ) ? $payload : array()
		);

		$response = wp_remote_post(
			self::build_callback_url( $run, $path ),
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

		$code         = wp_remote_retrieve_response_code( $response );
		$raw_body     = wp_remote_retrieve_body( $response );
		$decoded_body = json_decode( $raw_body, true );

		if ( $code >= 200 && $code < 300 ) {
			return is_array( $decoded_body ) ? $decoded_body : array( 'success' => true );
		}

		$message = self::extract_error_message( $decoded_body, $code, $fallback );

		return new WP_Error( 'launchdek_client_hub_error', $message, array( 'status' => $code ) );
	}

	/**
	 * Extract a useful error message from a hub response.
	 *
	 * @param mixed  $body     Decoded response body.
	 * @param int    $code     HTTP status code.
	 * @param string $fallback Fallback message.
	 * @return string
	 */
	protected static function extract_error_message( $body, $code, $fallback ) {
		if ( is_array( $body ) ) {
			if ( ! empty( $body['message'] ) ) {
				return (string) $body['message'];
			}

			if ( ! empty( $body['code'] ) ) {
				return sprintf(
					/* translators: 1: error code, 2: HTTP status code */
					__( 'Hub error: %1$s (HTTP %2$d).', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					(string) $body['code'],
					(int) $code
				);
			}
		}

		if ( 401 === (int) $code || 403 === (int) $code ) {
			return __( 'The hub rejected this request because the checklist token is invalid or expired. Re-push the checklist from the hub and try again.', LAUNCHDEK_CLIENT_TEXT_DOMAIN );
		}

		if ( 404 === (int) $code ) {
			return __( 'The hub could not find the checklist callback route. Re-push the checklist from the hub and confirm the hub REST API is reachable.', LAUNCHDEK_CLIENT_TEXT_DOMAIN );
		}

		return $fallback;
	}

	/**
	 * Notify the hub that a manual step was completed on the client site.
	 *
	 * @param array   $run        Active run snapshot.
	 * @param int     $step_index Step index.
	 * @param WP_User $user       Completing user.
	 * @return array|WP_Error
	 */
	public static function complete_step( $run, $step_index, $user ) {
		return self::post_to_hub(
			$run,
			'client-runs/' . absint( $run['run_id'] ?? 0 ) . '/steps/' . absint( $step_index ) . '/complete',
			array(),
			$user,
			__( 'The hub rejected the step completion request.', LAUNCHDEK_CLIENT_TEXT_DOMAIN )
		);
	}

	/**
	 * Notify the hub that a completed step was reverted on the client site.
	 *
	 * @param array   $run        Active run snapshot.
	 * @param int     $step_index Step index.
	 * @param WP_User $user       User reverting the step.
	 * @return array|WP_Error
	 */
	public static function uncomplete_step( $run, $step_index, $user ) {
		return self::post_to_hub(
			$run,
			'client-runs/' . absint( $run['run_id'] ?? 0 ) . '/steps/' . absint( $step_index ) . '/uncomplete',
			array(),
			$user,
			__( 'The hub rejected the step update request.', LAUNCHDEK_CLIENT_TEXT_DOMAIN )
		);
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
		$text = trim( sanitize_textarea_field( $payload['text'] ?? '' ) );

		$body = array(
			'text'       => $text,
			'created_at' => sanitize_text_field( $payload['created_at'] ?? current_time( 'mysql', true ) ),
		);

		$attachment_id = absint( $payload['attachment_id'] ?? 0 );
		if ( $attachment_id > 0 ) {
			$body['attachment_id'] = $attachment_id;
			if ( ! empty( $payload['attachment_url'] ) ) {
				$body['attachment_url'] = esc_url_raw( $payload['attachment_url'] );
			}
		}

		return self::post_to_hub(
			$run,
			'client-runs/' . absint( $run['run_id'] ?? 0 ) . '/steps/' . absint( $step_index ) . '/notes',
			$body,
			$user,
			__( 'The hub rejected the note.', LAUNCHDEK_CLIENT_TEXT_DOMAIN )
		);
	}
}
