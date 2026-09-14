<?php
/**
 * Maps checklist API step definitions to REST calls.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API payload mapper.
 */
class LAUNCHDEK_Payload_Mapper {

	/**
	 * Execute an API step against a remote site.
	 *
	 * @param int   $site_id Site ID.
	 * @param array $step    Step definition.
	 * @return array|WP_Error
	 */
	public static function execute( $site_id, $step ) {
		$client = LAUNCHDEK_Remote_Client::from_site( $site_id );

		if ( ! $client ) {
			return new WP_Error( 'launchdek_no_client', __( 'Unable to connect to remote site.', LAUNCHDEK_TEXT_DOMAIN ) );
		}

		$api = $step['api'] ?? array();

		if ( empty( $api['route'] ) ) {
			return new WP_Error( 'launchdek_no_route', __( 'API step missing route.', LAUNCHDEK_TEXT_DOMAIN ) );
		}

		$method  = strtoupper( $api['method'] ?? 'GET' );
		$route   = $api['route'];
		$payload = $api['payload'] ?? array();

		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) && LAUNCHDEK_Settings::is_settings_route( $route ) && is_array( $payload ) ) {
			$filtered = LAUNCHDEK_Settings::filter_settings_payload( $payload );

			if ( empty( $filtered['payload'] ) && ! empty( $filtered['stripped'] ) ) {
				return new WP_Error(
					'launchdek_excluded_settings',
					sprintf(
						/* translators: %s: comma-separated setting field names */
						__( 'All requested settings are excluded by agency policy: %s', LAUNCHDEK_TEXT_DOMAIN ),
						implode( ', ', $filtered['stripped'] )
					)
				);
			}

			$payload = $filtered['payload'];
		}

		$result = $client->rest( $method, $route, $payload );

		if ( ! is_wp_error( $result ) ) {
			LAUNCHDEK_Audit_Log::log(
				'api_step_executed',
				array(
					'route'   => $route,
					'method'  => $method,
					'payload' => $payload,
					'code'    => $result['code'],
				),
				$site_id
			);
		}

		return $result;
	}

	/**
	 * Dry-run validation without executing mutating requests.
	 *
	 * @param array $step Step definition.
	 * @return array
	 */
	public static function validate( $step ) {
		$api = $step['api'] ?? array();
		$errors = array();

		if ( empty( $api['route'] ) ) {
			$errors[] = __( 'Route is required for API steps.', LAUNCHDEK_TEXT_DOMAIN );
		}

		if ( ! empty( $api['route'] ) && '/' !== $api['route'][0] ) {
			$errors[] = __( 'Route must start with /.', LAUNCHDEK_TEXT_DOMAIN );
		}

		$method = strtoupper( $api['method'] ?? 'GET' );
		if ( ! in_array( $method, array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
			$errors[] = __( 'Invalid HTTP method.', LAUNCHDEK_TEXT_DOMAIN );
		}

		$warnings = array();
		$route    = $api['route'] ?? '';
		$payload  = $api['payload'] ?? array();
		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) && LAUNCHDEK_Settings::is_settings_route( $route ) && is_array( $payload ) ) {
			$filtered = LAUNCHDEK_Settings::filter_settings_payload( $payload );

			if ( empty( $filtered['payload'] ) && ! empty( $filtered['stripped'] ) ) {
				$errors[] = sprintf(
					/* translators: %s: comma-separated setting field names */
					__( 'The API step payload only contains excluded settings: %s', LAUNCHDEK_TEXT_DOMAIN ),
					implode( ', ', $filtered['stripped'] )
				);
			} elseif ( ! empty( $filtered['stripped'] ) ) {
				$warnings[] = sprintf(
					/* translators: %s: comma-separated setting field names */
					__( 'These excluded settings will be stripped before execution: %s', LAUNCHDEK_TEXT_DOMAIN ),
					implode( ', ', $filtered['stripped'] )
				);
			}
		}

		return array(
			'valid'    => empty( $errors ),
			'errors'   => $errors,
			'warnings' => $warnings,
		);
	}
}
