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

		$filtered = self::filter_excluded_payload( $route, $method, $payload );

		if ( is_wp_error( $filtered ) ) {
			return $filtered;
		}

		$payload = $filtered['payload'];
		$stripped = $filtered['stripped'];

		$result = $client->rest( $method, $route, $payload );

		if ( ! is_wp_error( $result ) ) {
			$audit_details = array(
				'route'   => $route,
				'method'  => $method,
				'payload' => $payload,
				'code'    => $result['code'],
			);

			if ( ! empty( $stripped ) ) {
				$audit_details['excluded_fields'] = $stripped;
			}

			LAUNCHDEK_Audit_Log::log(
				'api_step_executed',
				$audit_details,
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
		$errors   = array();
		$warnings = array();

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

		if ( empty( $errors ) ) {
			$filtered = self::filter_excluded_payload(
				$api['route'],
				$method,
				$api['payload'] ?? array(),
				false
			);

			if ( is_wp_error( $filtered ) ) {
				$errors[] = $filtered->get_error_message();
			} elseif ( ! empty( $filtered['stripped'] ) ) {
				$warnings[] = sprintf(
					/* translators: %s: comma-separated setting field names */
					__( 'These fields are excluded by hub settings and will be skipped on remote sites: %s', LAUNCHDEK_TEXT_DOMAIN ),
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

	/**
	 * Strip excluded settings fields from a mutating API payload.
	 *
	 * @param string $route   REST route.
	 * @param string $method  HTTP method.
	 * @param array  $payload Request body.
	 * @param bool   $error_when_empty Whether to return WP_Error when all fields are stripped.
	 * @return array|WP_Error { payload: array, stripped: string[] }
	 */
	public static function filter_excluded_payload( $route, $method, $payload, $error_when_empty = true ) {
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		$method = strtoupper( $method );

		if ( ! in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			return array(
				'payload'  => $payload,
				'stripped' => array(),
			);
		}

		if ( '/wp/v2/settings' !== $route ) {
			return array(
				'payload'  => $payload,
				'stripped' => array(),
			);
		}

		$excluded = LAUNCHDEK_Settings::get_exclude_options();

		if ( empty( $excluded ) || empty( $payload ) ) {
			return array(
				'payload'  => $payload,
				'stripped' => array(),
			);
		}

		$stripped = array();
		$filtered = array();

		foreach ( $payload as $key => $value ) {
			$normalized = LAUNCHDEK_Settings::normalize_exclude_option( $key );

			if ( in_array( $normalized, $excluded, true ) || in_array( $key, $excluded, true ) ) {
				$stripped[] = (string) $key;
				continue;
			}

			$filtered[ $key ] = $value;
		}

		if ( $error_when_empty && ! empty( $stripped ) && empty( $filtered ) ) {
			return new WP_Error(
				'launchdek_all_fields_excluded',
				sprintf(
					/* translators: %s: comma-separated setting field names */
					__( 'All settings in this step are excluded by hub settings: %s', LAUNCHDEK_TEXT_DOMAIN ),
					implode( ', ', $stripped )
				)
			);
		}

		return array(
			'payload'  => $filtered,
			'stripped' => $stripped,
		);
	}
}
