<?php
/**
 * Hub proxy for remote client auto-capture sessions.
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remote auto-capture orchestration.
 */
class CHECKLISTDEK_Auto_Capture {

	const CAPTURE_ROUTE = '/checklistdek/v1/client/capture';

	/**
	 * Fetch capture status from a remote site.
	 *
	 * @param int $site_id Site ID.
	 * @return array|WP_Error
	 */
	public static function get_status( $site_id ) {
		return self::request( $site_id, 'GET' );
	}

	/**
	 * Start capture on a remote site.
	 *
	 * @param int $site_id Site ID.
	 * @return array|WP_Error
	 */
	public static function start( $site_id ) {
		return self::request( $site_id, 'POST', array( 'action' => 'start' ) );
	}

	/**
	 * Stop capture on a remote site.
	 *
	 * @param int $site_id Site ID.
	 * @return array|WP_Error
	 */
	public static function stop( $site_id ) {
		return self::request( $site_id, 'POST', array( 'action' => 'stop' ) );
	}

	/**
	 * Clear captured steps on a remote site.
	 *
	 * @param int $site_id Site ID.
	 * @return array|WP_Error
	 */
	public static function clear( $site_id ) {
		return self::request( $site_id, 'DELETE' );
	}

	/**
	 * Proxy a capture request to the client panel.
	 *
	 * @param int    $site_id Site ID.
	 * @param string $method  HTTP method.
	 * @param array  $body    Optional JSON body.
	 * @return array|WP_Error
	 */
	protected static function request( $site_id, $method, array $body = array() ) {
		$client = CHECKLISTDEK_Remote_Client::from_site( $site_id );

		if ( ! $client ) {
			return new WP_Error(
				'checklistdek_no_client',
				__( 'Unable to connect to remote site.', 'checklistdek' )
			);
		}

		if ( ! CHECKLISTDEK_Client_Push::agent_available( $site_id ) ) {
			return new WP_Error(
				'checklistdek_capture_panel_required',
				__( 'Auto-capture requires the client checklist panel on the remote site.', 'checklistdek' ),
				array( 'status' => 400 )
			);
		}

		$result = $client->rest( $method, self::CAPTURE_ROUTE, $body );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$payload = is_array( $result['body'] ) ? $result['body'] : array();

		if ( 'POST' === strtoupper( $method ) && ! empty( $body['action'] ) ) {
			CHECKLISTDEK_Audit_Log::log(
				'capture_' . sanitize_key( $body['action'] ),
				array(
					'count' => (int) ( $payload['count'] ?? 0 ),
				),
				$site_id
			);
		}

		return $payload;
	}
}
