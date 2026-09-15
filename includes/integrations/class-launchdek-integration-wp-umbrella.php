<?php
/**
 * WP Umbrella integration adapter.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Umbrella integration.
 */
class LAUNCHDEK_Integration_WP_Umbrella implements LAUNCHDEK_Integration_Interface {

	const API_BASE_URL = 'https://public-api.wp-umbrella.com';

	const PER_PAGE = 100;

	public function get_slug() {
		return 'wp-umbrella';
	}

	public function get_name() {
		return __( 'WP Umbrella Connector', LAUNCHDEK_TEXT_DOMAIN );
	}

	public function is_available() {
		return LAUNCHDEK_Settings::has_wp_umbrella_api_token();
	}

	public function supports_site_sync() {
		return true;
	}

	public function fetch_platform_sites() {
		if ( ! $this->is_available() ) {
			return new WP_Error(
				'launchdek_wp_umbrella_unconfigured',
				__( 'Add your WP Umbrella Public API token in connector setup before syncing sites.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => 400 )
			);
		}

		$rows  = array();
		$page  = 1;
		$token = LAUNCHDEK_Settings::get_wp_umbrella_api_token();

		while ( true ) {
			$response = $this->api_request(
				'/projects',
				array(
					'page'     => $page,
					'per_page' => self::PER_PAGE,
					'sort'     => 'name',
					'order'    => 'asc',
				),
				$token
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$projects = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : array();

			foreach ( $projects as $project ) {
				$row = $this->normalize_project_row( $project );

				if ( $row ) {
					$rows[] = $row;
				}
			}

			if ( count( $projects ) < self::PER_PAGE ) {
				break;
			}

			++$page;

			if ( $page > 100 ) {
				break;
			}
		}

		return $rows;
	}

	public function push_agent( $site_ids = array() ) {
		if ( ! $this->is_available() ) {
			return array(
				'error' => __( 'WP Umbrella API token is not configured.', LAUNCHDEK_TEXT_DOMAIN ),
			);
		}

		$targets = $this->resolve_push_targets( $site_ids );
		$results = array(
			'sites'   => array(),
			'summary' => array(
				'success' => 0,
				'failed'  => 0,
				'skipped' => 0,
			),
		);

		foreach ( $targets as $site ) {
			$site_id = (int) ( $site['id'] ?? 0 );
			$result  = $this->push_agent_to_site( $site );

			$results['sites'][ $site_id ] = $result;

			if ( ! empty( $result['success'] ) ) {
				++$results['summary']['success'];
			} elseif ( ! empty( $result['skipped'] ) ) {
				++$results['summary']['skipped'];
			} else {
				++$results['summary']['failed'];
			}
		}

		if ( empty( $targets ) ) {
			$results['message'] = __( 'No WP Umbrella-linked LaunchDek sites were found to push. Sync sites from WP Umbrella first.', LAUNCHDEK_TEXT_DOMAIN );
		}

		/**
		 * Fires when LaunchDek pushes the client panel for WP Umbrella sites.
		 *
		 * @param array $site_ids LaunchDek site IDs.
		 * @param array $results  Results array passed by reference.
		 */
		do_action( 'launchdek_wp_umbrella_push_agent', $site_ids, $results );

		LAUNCHDEK_Audit_Log::log(
			'integration_push',
			array(
				'integration' => 'wp-umbrella',
				'summary'     => $results['summary'],
			)
		);

		return $results;
	}

	public function get_status() {
		return array(
			'description'          => __( 'Import sites from your WP Umbrella account and deploy the LaunchDek client checklist panel to connected sites with Application Passwords.', LAUNCHDEK_TEXT_DOMAIN ),
			'docs_url'             => 'https://support.wp-umbrella.com/en/articles/3-how-to-use-the-wp-umbrella-public-api',
			'supports_sync'        => true,
			'api_token_configured' => LAUNCHDEK_Settings::has_wp_umbrella_api_token(),
			'requires_api_token'   => true,
		);
	}

	/**
	 * Build sync diagnostics for empty WP Umbrella inventories.
	 *
	 * @return array
	 */
	public function get_sync_diagnostics() {
		$diagnostics = array(
			'api_token_configured' => LAUNCHDEK_Settings::has_wp_umbrella_api_token(),
			'supports_sync'        => $this->supports_site_sync(),
		);

		if ( ! $diagnostics['api_token_configured'] ) {
			$diagnostics['hint'] = __( 'Generate a Public API token in WP Umbrella (Profile → Public API for developers), paste it in connector setup, then click Sync Sites.', LAUNCHDEK_TEXT_DOMAIN );
			return $diagnostics;
		}

		$probe = $this->api_request(
			'/projects',
			array(
				'page'     => 1,
				'per_page' => 1,
			)
		);

		if ( is_wp_error( $probe ) ) {
			$diagnostics['hint'] = $probe->get_error_message();
			return $diagnostics;
		}

		$total = isset( $probe['data'] ) && is_array( $probe['data'] ) ? count( $probe['data'] ) : 0;

		if ( 0 === $total ) {
			$diagnostics['hint'] = __( 'Your WP Umbrella account has no connected projects yet. Add sites in WP Umbrella first, then sync again.', LAUNCHDEK_TEXT_DOMAIN );
		} else {
			$diagnostics['hint'] = __( 'WP Umbrella returned projects but none were eligible to import. Disconnected sites are skipped — reconnect them in WP Umbrella, then sync again.', LAUNCHDEK_TEXT_DOMAIN );
		}

		return $diagnostics;
	}

	/**
	 * Push the client panel to a single LaunchDek site.
	 *
	 * @param array $site LaunchDek site row.
	 * @return array
	 */
	protected function push_agent_to_site( $site ) {
		$site_id = (int) ( $site['id'] ?? 0 );

		if ( $site_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid site.', LAUNCHDEK_TEXT_DOMAIN ),
			);
		}

		if ( ! LAUNCHDEK_Site_Repository::has_credentials( $site_id ) ) {
			return array(
				'skipped' => true,
				'message' => __( 'Add Application Password credentials on the Sites page before pushing the client panel.', LAUNCHDEK_TEXT_DOMAIN ),
			);
		}

		$panel = LAUNCHDEK_Mu_Plugin_Installer::ensure_installed( $site_id );

		if ( is_wp_error( $panel ) ) {
			return array(
				'success' => false,
				'message' => $panel->get_error_message(),
			);
		}

		return array_merge(
			array( 'success' => true ),
			is_array( $panel ) ? $panel : array()
		);
	}

	/**
	 * Resolve LaunchDek sites to push.
	 *
	 * @param array $site_ids Optional LaunchDek site IDs.
	 * @return array
	 */
	protected function resolve_push_targets( $site_ids ) {
		$site_ids = array_filter( array_map( 'absint', (array) $site_ids ) );

		if ( ! empty( $site_ids ) ) {
			$targets = array();

			foreach ( $site_ids as $site_id ) {
				$site = LAUNCHDEK_Site_Repository::find( $site_id );

				if ( $site && 'wp-umbrella' === ( $site['integration_source'] ?? '' ) ) {
					$targets[] = $site;
				}
			}

			return $targets;
		}

		return LAUNCHDEK_Site_Repository::list_by_integration( 'wp-umbrella' );
	}

	/**
	 * Normalize a WP Umbrella project into telemetry field keys.
	 *
	 * @param mixed $project Project payload from the Public API.
	 * @return array|null
	 */
	protected function normalize_project_row( $project ) {
		if ( is_object( $project ) ) {
			$project = (array) $project;
		}

		if ( ! is_array( $project ) ) {
			return null;
		}

		if ( ! empty( $project['is_disconnected'] ) ) {
			return null;
		}

		$connectivity = isset( $project['connectivity'] ) && is_array( $project['connectivity'] )
			? $project['connectivity']
			: array();

		if ( ! empty( $connectivity['status'] ) && 'paired' !== (string) $connectivity['status'] ) {
			return null;
		}

		$url = LAUNCHDEK_Site_Repository::normalize_url( $project['base_url'] ?? '' );

		if ( '' === $url ) {
			return null;
		}

		$warnings = isset( $project['warnings'] ) && is_array( $project['warnings'] )
			? $project['warnings']
			: array();

		return array(
			'external_id' => (string) ( $project['id'] ?? '' ),
			'url'         => $url,
			'name'        => sanitize_text_field( (string) ( $project['name'] ?? $url ) ),
			'wp_version'  => sanitize_text_field( (string) ( $warnings['wordpress_version'] ?? '' ) ),
			'php_version' => sanitize_text_field( (string) ( $warnings['php_current_version'] ?? '' ) ),
		);
	}

	/**
	 * Perform an authenticated WP Umbrella Public API request.
	 *
	 * @param string $path    API path beginning with /.
	 * @param array  $query   Optional query args.
	 * @param string $token   Optional bearer token override.
	 * @return array|WP_Error
	 */
	protected function api_request( $path, $query = array(), $token = '' ) {
		$token = '' !== (string) $token ? (string) $token : LAUNCHDEK_Settings::get_wp_umbrella_api_token();

		if ( '' === trim( $token ) ) {
			return new WP_Error(
				'launchdek_wp_umbrella_unconfigured',
				__( 'WP Umbrella API token is not configured.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => 400 )
			);
		}

		$url = self::API_BASE_URL . $path;

		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$response = wp_remote_get(
			esc_url_raw( $url ),
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'launchdek_wp_umbrella_request_failed',
				__( 'Could not reach the WP Umbrella API. Check your network connection and try again.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => 502 )
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$data   = json_decode( $body, true );

		if ( 401 === $status || 403 === $status ) {
			return new WP_Error(
				'launchdek_wp_umbrella_unauthorized',
				__( 'WP Umbrella rejected the API token. Regenerate your Public API token and save it again.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => $status )
			);
		}

		if ( $status < 200 || $status >= 300 ) {
			$message = __( 'WP Umbrella API request failed.', LAUNCHDEK_TEXT_DOMAIN );

			if ( is_array( $data ) && ! empty( $data['message'] ) ) {
				$message = sanitize_text_field( (string) $data['message'] );
			}

			return new WP_Error(
				'launchdek_wp_umbrella_api_error',
				$message,
				array( 'status' => $status )
			);
		}

		return is_array( $data ) ? $data : array();
	}
}
