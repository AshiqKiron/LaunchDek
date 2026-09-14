<?php
/**
 * WordPress REST API client for remote sites.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Remote HTTP client using Application Passwords.
 */
class LAUNCHDEK_Remote_Client {

	/**
	 * Site ID for logging.
	 *
	 * @var int
	 */
	protected $site_id;

	/**
	 * Remote site URL.
	 *
	 * @var string
	 */
	protected $url;

	/**
	 * Basic auth username.
	 *
	 * @var string
	 */
	protected $username;

	/**
	 * Application password.
	 *
	 * @var string
	 */
	protected $password;

	/**
	 * Constructor.
	 *
	 * @param int   $site_id  Site ID.
	 * @param array $creds    Credentials array.
	 */
	public function __construct( $site_id, $creds ) {
		$this->site_id  = absint( $site_id );
		$this->url      = untrailingslashit( $creds['url'] ?? '' );
		$this->username = $creds['admin_username'] ?? '';
		$this->password = $creds['app_password'] ?? '';
	}

	/**
	 * Factory from site ID.
	 *
	 * @param int $site_id Site ID.
	 * @return self|null
	 */
	public static function from_site( $site_id ) {
		$creds = LAUNCHDEK_Site_Repository::get_credentials( $site_id );

		if ( ! $creds || empty( $creds['url'] ) ) {
			return null;
		}

		return new self( $site_id, $creds );
	}

	/**
	 * Get the remote site URL.
	 *
	 * @return string
	 */
	public function get_url() {
		return $this->url;
	}

	/**
	 * Ping remote site and return environment info.
	 *
	 * @return array
	 */
	public function ping() {
		$response = $this->request( 'GET', '/wp/v2/users/me' );

		if ( is_wp_error( $response ) ) {
			$this->log_connection( 'error', $response->get_error_message() );
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $body ) && ! empty( $body['message'] ) ? $body['message'] : __( 'Authentication failed.', LAUNCHDEK_TEXT_DOMAIN );
			$this->log_connection( 'error', $message );
			return array(
				'success' => false,
				'message' => $message,
				'code'    => $code,
			);
		}

		$env = $this->request( 'GET', '/' );

		$wp_version  = '';
		$php_version = '';

		if ( ! is_wp_error( $env ) ) {
			$env_body = json_decode( wp_remote_retrieve_body( $env ), true );
			if ( is_array( $env_body ) ) {
				$wp_version = $env_body['gmt_offset'] ?? '';
				if ( ! empty( $env_body['description'] ) ) {
					$wp_version = get_bloginfo( 'version' ); // fallback
				}
			}
		}

		$site_health = $this->request( 'GET', '/wp/v2/plugins' );
		if ( ! is_wp_error( $site_health ) ) {
			$headers = wp_remote_retrieve_headers( $site_health );
			if ( isset( $headers['x-powered-by'] ) ) {
				$php_version = (string) $headers['x-powered-by'];
			}
		}

		// Try to read versions from client agent if available.
		$about = $this->request( 'GET', '/launchdek/v1/client/info' );
		$client_agent = false;

		if ( ! is_wp_error( $about ) ) {
			$about_body = json_decode( wp_remote_retrieve_body( $about ), true );
			if ( is_array( $about_body ) ) {
				$client_agent = ! empty( $about_body['panel'] );
				$wp_version   = $about_body['wp_version'] ?? $wp_version;
				$php_version  = $about_body['php_version'] ?? $php_version;
			}
		}

		$this->log_connection( 'success', __( 'Connection verified.', LAUNCHDEK_TEXT_DOMAIN ) );

		LAUNCHDEK_Site_Repository::update(
			$this->site_id,
			array(
				'health_status' => 'healthy',
				'last_ping_at'  => current_time( 'mysql', true ),
				'last_error'    => '',
				'wp_version'    => $wp_version ?: ( is_array( $body ) ? ( $body['slug'] ?? '' ) : '' ),
				'php_version'   => $php_version,
				'client_agent'  => $client_agent ? 1 : 0,
			)
		);

		return array(
			'success'      => true,
			'message'      => __( 'Connection verified.', LAUNCHDEK_TEXT_DOMAIN ),
			'user'         => is_array( $body ) ? $body : array(),
			'wp_version'   => $wp_version,
			'php_version'  => $php_version,
			'client_agent' => $client_agent,
		);
	}

	/**
	 * Execute a REST request against the remote site.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  REST route (with leading slash).
	 * @param array  $body   Request body.
	 * @return array|WP_Error Parsed response or error.
	 */
	public function rest( $method, $route, $body = array() ) {
		$response = $this->request( $method, $route, $body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code        = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$parsed_body = json_decode( $raw_body, true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $parsed_body ) && ! empty( $parsed_body['message'] )
				? $parsed_body['message']
				: sprintf( __( 'Remote request failed with status %d.', LAUNCHDEK_TEXT_DOMAIN ), $code );

			return new WP_Error( 'launchdek_remote_error', $message, array(
				'status' => $code,
				'body'   => $parsed_body,
			) );
		}

		return array(
			'code' => $code,
			'body' => $parsed_body,
		);
	}

	/**
	 * Build admin deep link URL on remote site.
	 *
	 * @param string $path Admin path or full URL.
	 * @return string
	 */
	public function admin_link( $path ) {
		if ( filter_var( $path, FILTER_VALIDATE_URL ) ) {
			return esc_url_raw( $path );
		}

		$path = ltrim( $path, '/' );

		return trailingslashit( $this->url ) . 'wp-admin/' . $path;
	}

	/**
	 * Perform HTTP request.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  REST route.
	 * @param array  $body   Body payload.
	 * @return array|WP_Error
	 */
	protected function request( $method, $route, $body = array() ) {
		if ( empty( $this->url ) || empty( $this->username ) || empty( $this->password ) ) {
			return new WP_Error( 'launchdek_missing_creds', __( 'Missing remote credentials.', LAUNCHDEK_TEXT_DOMAIN ) );
		}

		$route  = '/' . ltrim( $route, '/' );
		$url    = trailingslashit( $this->url ) . 'wp-json' . $route;
		$method = strtoupper( $method );

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $this->username . ':' . $this->password ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
		);

		if ( in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) && ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		return wp_remote_request( $url, $args );
	}

	/**
	 * Log connection event for ticker.
	 *
	 * @param string $status  success|error.
	 * @param string $message Message.
	 * @return void
	 */
	protected function log_connection( $status, $message ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'launchdek_connection_events',
			array(
				'site_id'    => $this->site_id,
				'site_url'   => $this->url,
				'status'     => sanitize_key( $status ),
				'message'    => sanitize_textarea_field( $message ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		if ( 'error' === $status ) {
			LAUNCHDEK_Site_Repository::update(
				$this->site_id,
				array(
					'health_status' => 'unhealthy',
					'last_ping_at'  => current_time( 'mysql', true ),
					'last_error'    => $message,
				)
			);
		}
	}

	/**
	 * Get recent connection events.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function get_connection_ticker( $limit = 20 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'launchdek_connection_events';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
				max( 1, absint( $limit ) )
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}
}
