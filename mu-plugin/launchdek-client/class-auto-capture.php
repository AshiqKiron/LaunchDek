<?php
/**
 * Watches admin configuration changes and turns them into checklist steps.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Client-side auto-capture recorder.
 */
class LAUNCHDEK_Client_Auto_Capture {

	const OPTION_KEY = 'launchdek_client_capture';

	/**
	 * Register capture hooks.
	 *
	 * @return void
	 */
	public static function register() {
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'observe_rest_mutation' ), 10, 3 );
		add_action( 'updated_option', array( __CLASS__, 'observe_option_change' ), 10, 3 );
		add_action( 'activated_plugin', array( __CLASS__, 'observe_plugin_activated' ), 10, 2 );
		add_action( 'deactivated_plugin', array( __CLASS__, 'observe_plugin_deactivated' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'render_recording_notice' ) );
	}

	/**
	 * Whether a capture session is active.
	 *
	 * @return bool
	 */
	public static function is_active() {
		$session = self::get_session();
		return ! empty( $session['active'] );
	}

	/**
	 * Start a capture session.
	 *
	 * @return array
	 */
	public static function start() {
		$user = wp_get_current_user();

		$session = array(
			'active'     => true,
			'started_at' => current_time( 'mysql', true ),
			'started_by' => $user->ID,
			'steps'      => array(),
		);

		update_option( self::OPTION_KEY, $session, false );

		return self::format_status( $session );
	}

	/**
	 * Stop the active capture session.
	 *
	 * @return array
	 */
	public static function stop() {
		$session = self::get_session();
		$session['active'] = false;
		update_option( self::OPTION_KEY, $session, false );

		return self::format_status( $session );
	}

	/**
	 * Clear captured steps and stop recording.
	 *
	 * @return array
	 */
	public static function clear() {
		delete_option( self::OPTION_KEY );

		return self::format_status( self::default_session() );
	}

	/**
	 * Return capture status for REST/UI.
	 *
	 * @return array
	 */
	public static function get_status() {
		return self::format_status( self::get_session() );
	}

	/**
	 * Convert captured entries to checklist step definitions.
	 *
	 * @return array
	 */
	public static function get_checklist_steps() {
		$session = self::get_session();
		$steps   = array();

		foreach ( $session['steps'] as $index => $entry ) {
			$steps[] = self::entry_to_checklist_step( $entry, $index );
		}

		return $steps;
	}

	/**
	 * Observe successful REST mutations while recording.
	 *
	 * @param WP_REST_Response|WP_HTTP_Response|WP_Error|mixed $response Result to send.
	 * @param array                                            $handler  Route handler.
	 * @param WP_REST_Request                                  $request  Request object.
	 * @return mixed
	 */
	public static function observe_rest_mutation( $response, $handler, $request ) {
		if ( ! self::is_active() || ! ( $request instanceof WP_REST_Request ) ) {
			return $response;
		}

		$method = strtoupper( $request->get_method() );
		if ( ! in_array( $method, array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
			return $response;
		}

		$route = (string) $request->get_route();
		if ( 0 === strpos( $route, '/launchdek/' ) ) {
			return $response;
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = 200;
		if ( $response instanceof WP_REST_Response ) {
			$status = (int) $response->get_status();
		} elseif ( $response instanceof WP_HTTP_Response ) {
			$status = (int) $response->get_status();
		}

		if ( $status < 200 || $status >= 300 ) {
			return $response;
		}

		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) || empty( $payload ) ) {
			$payload = $request->get_body_params();
		}
		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		$meta = self::describe_rest_route( $route, $method, $payload );

		self::append_entry(
			array(
				'source'     => 'rest',
				'title'      => $meta['title'],
				'instructions' => $meta['instructions'],
				'deep_link'  => self::current_admin_path(),
				'type'       => 'api',
				'api'        => array(
					'route'   => $meta['route'],
					'method'  => $method,
					'payload' => $meta['payload'],
				),
			)
		);

		return $response;
	}

	/**
	 * Observe direct option updates while recording.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $value     New value.
	 * @return void
	 */
	public static function observe_option_change( $option, $old_value, $value ) {
		if ( ! self::is_active() ) {
			return;
		}

		if ( self::should_ignore_option( $option ) ) {
			return;
		}

		if ( $old_value === $value ) {
			return;
		}

		$known = self::get_known_options();
		if ( ! isset( $known[ $option ] ) ) {
			return;
		}

		$meta = $known[ $option ];

		self::append_entry(
			array(
				'source'       => 'option',
				'title'        => $meta['title'],
				'instructions' => sprintf(
					/* translators: %s: option label */
					__( 'Set %s to the captured value when repeating this checklist.', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					$meta['title']
				),
				'deep_link'    => $meta['deep_link'],
				'type'         => 'manual',
			)
		);
	}

	/**
	 * Observe plugin activation while recording.
	 *
	 * @param string $plugin       Plugin file.
	 * @param bool   $network_wide Network activation flag.
	 * @return void
	 */
	public static function observe_plugin_activated( $plugin, $network_wide ) {
		unset( $network_wide );

		if ( ! self::is_active() ) {
			return;
		}

		$name = self::plugin_label( $plugin );

		self::append_entry(
			array(
				'source'       => 'plugin',
				'title'        => sprintf(
					/* translators: %s: plugin name */
					__( 'Activate %s', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					$name
				),
				'instructions' => __( 'Activate this plugin on the client site.', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
				'deep_link'    => 'plugins.php',
				'type'         => 'manual',
			)
		);
	}

	/**
	 * Observe plugin deactivation while recording.
	 *
	 * @param string $plugin       Plugin file.
	 * @param bool   $network_wide Network activation flag.
	 * @return void
	 */
	public static function observe_plugin_deactivated( $plugin, $network_wide ) {
		unset( $network_wide );

		if ( ! self::is_active() ) {
			return;
		}

		$name = self::plugin_label( $plugin );

		self::append_entry(
			array(
				'source'       => 'plugin',
				'title'        => sprintf(
					/* translators: %s: plugin name */
					__( 'Deactivate %s', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					$name
				),
				'instructions' => __( 'Deactivate this plugin on the client site.', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
				'deep_link'    => 'plugins.php',
				'type'         => 'manual',
			)
		);
	}

	/**
	 * Show a recording banner in wp-admin.
	 *
	 * @return void
	 */
	public static function render_recording_notice() {
		if ( ! self::is_active() || ! is_admin() ) {
			return;
		}

		$session = self::get_session();
		$count   = is_array( $session['steps'] ) ? count( $session['steps'] ) : 0;

		printf(
			'<div class="notice notice-info launchdek-client-capture-notice"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'LaunchDek auto-capture is recording.', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
			esc_html(
				sprintf(
					/* translators: %d: number of captured steps */
					_n( '%d step captured so far.', '%d steps captured so far.', $count, LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					$count
				)
			)
		);
	}

	/**
	 * Append a capture entry, skipping near-duplicates.
	 *
	 * @param array $entry Capture entry.
	 * @return void
	 */
	private static function append_entry( array $entry ) {
		$session = self::get_session();
		$steps   = is_array( $session['steps'] ) ? $session['steps'] : array();
		$hash    = self::entry_hash( $entry );

		if ( ! empty( $steps ) ) {
			$last = end( $steps );
			if ( is_array( $last ) && ( $last['hash'] ?? '' ) === $hash ) {
				return;
			}
		}

		$entry['hash']        = $hash;
		$entry['captured_at'] = current_time( 'mysql', true );
		$steps[]              = $entry;

		$session['steps'] = array_slice( $steps, -100 );
		update_option( self::OPTION_KEY, $session, false );
	}

	/**
	 * Build a stable hash for deduplication.
	 *
	 * @param array $entry Capture entry.
	 * @return string
	 */
	private static function entry_hash( array $entry ) {
		$parts = array(
			$entry['source'] ?? '',
			$entry['title'] ?? '',
			wp_json_encode( $entry['api'] ?? array() ),
		);

		return md5( implode( '|', $parts ) );
	}

	/**
	 * Convert a capture entry to a checklist step.
	 *
	 * @param array $entry Capture entry.
	 * @param int   $index Step index.
	 * @return array
	 */
	private static function entry_to_checklist_step( array $entry, $index ) {
		$step = array(
			'id'           => 'capture_' . ( $index + 1 ),
			'title'        => $entry['title'] ?? __( 'Captured step', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
			'instructions' => $entry['instructions'] ?? '',
			'deep_link'    => $entry['deep_link'] ?? '',
			'target_roles' => array(),
			'type'         => $entry['type'] ?? 'manual',
		);

		if ( 'api' === $step['type'] && ! empty( $entry['api'] ) && is_array( $entry['api'] ) ) {
			$step['api'] = array(
				'route'   => $entry['api']['route'] ?? '',
				'method'  => $entry['api']['method'] ?? 'POST',
				'payload' => $entry['api']['payload'] ?? array(),
			);
		}

		return $step;
	}

	/**
	 * Describe a REST route for capture output.
	 *
	 * @param string $route   REST route.
	 * @param string $method  HTTP method.
	 * @param array  $payload Request payload.
	 * @return array
	 */
	private static function describe_rest_route( $route, $method, array $payload ) {
		$rest_route = self::normalize_rest_route( $route );
		$title      = sprintf(
			/* translators: 1: HTTP method, 2: REST route */
			__( '%1$s %2$s', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
			$method,
			$rest_route
		);
		$instructions = __( 'Repeat this REST API change on future client sites.', LAUNCHDEK_CLIENT_TEXT_DOMAIN );

		if ( '/wp/v2/settings' === $rest_route ) {
			$fields = array_keys( $payload );
			if ( ! empty( $fields ) ) {
				$title = sprintf(
					/* translators: %s: comma-separated setting field names */
					__( 'Update settings: %s', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
					implode( ', ', $fields )
				);
			} else {
				$title = __( 'Update site settings', LAUNCHDEK_CLIENT_TEXT_DOMAIN );
			}
			$instructions = __( 'Apply the same WordPress settings change captured during recording.', LAUNCHDEK_CLIENT_TEXT_DOMAIN );
		} elseif ( 0 === strpos( $rest_route, '/wp/v2/plugins' ) ) {
			$title        = __( 'Manage plugins', LAUNCHDEK_CLIENT_TEXT_DOMAIN );
			$instructions = __( 'Repeat the plugin change captured during recording.', LAUNCHDEK_CLIENT_TEXT_DOMAIN );
		}

		return array(
			'title'        => $title,
			'instructions' => $instructions,
			'route'        => $rest_route,
			'payload'      => $payload,
		);
	}

	/**
	 * Normalize a REST route to the checklist API route format.
	 *
	 * @param string $route REST route from the request.
	 * @return string
	 */
	private static function normalize_rest_route( $route ) {
		$route = '/' . ltrim( (string) $route, '/' );

		if ( 0 === strpos( $route, '/wp-json' ) ) {
			$route = substr( $route, strlen( '/wp-json' ) );
		}

		return '/' . ltrim( $route, '/' );
	}

	/**
	 * Known WordPress options with friendly labels.
	 *
	 * @return array
	 */
	private static function get_known_options() {
		return array(
			'blogname'                => array(
				'title'      => __( 'Site Title', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
				'deep_link'  => 'options-general.php',
			),
			'blogdescription'         => array(
				'title'      => __( 'Tagline', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
				'deep_link'  => 'options-general.php',
			),
			'admin_email'             => array(
				'title'      => __( 'Administration Email', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
				'deep_link'  => 'options-general.php',
			),
			'users_can_register'      => array(
				'title'      => __( 'Membership Setting', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
				'deep_link'  => 'options-general.php',
			),
			'default_role'            => array(
				'title'      => __( 'New User Default Role', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
				'deep_link'  => 'options-general.php',
			),
			'permalink_structure'     => array(
				'title'      => __( 'Permalink Structure', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
				'deep_link'  => 'options-permalink.php',
			),
			'blog_public'             => array(
				'title'      => __( 'Search Engine Visibility', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
				'deep_link'  => 'options-reading.php',
			),
			'show_on_front'           => array(
				'title'      => __( 'Homepage Displays', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
				'deep_link'  => 'options-reading.php',
			),
			'page_on_front'           => array(
				'title'      => __( 'Homepage', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
				'deep_link'  => 'options-reading.php',
			),
			'page_for_posts'          => array(
				'title'      => __( 'Posts Page', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
				'deep_link'  => 'options-reading.php',
			),
			'default_comment_status'  => array(
				'title'      => __( 'Default Comment Status', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
				'deep_link'  => 'options-discussion.php',
			),
			'timezone_string'         => array(
				'title'      => __( 'Timezone', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
				'deep_link'  => 'options-general.php',
			),
			'date_format'             => array(
				'title'      => __( 'Date Format', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
				'deep_link'  => 'options-general.php',
			),
			'time_format'             => array(
				'title'      => __( 'Time Format', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
				'deep_link'  => 'options-general.php',
			),
			'start_of_week'           => array(
				'title'      => __( 'Week Starts On', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
				'deep_link'  => 'options-general.php',
			),
			'WPLANG'                  => array(
				'title'      => __( 'Site Language', LAUNCHDEK_CLIENT_TEXT_DOMAIN ),
				'deep_link'  => 'options-general.php',
			),
		);
	}

	/**
	 * Whether an option should be ignored during capture.
	 *
	 * @param string $option Option name.
	 * @return bool
	 */
	private static function should_ignore_option( $option ) {
		if ( 0 === strpos( $option, '_transient' ) || 0 === strpos( $option, '_site_transient' ) ) {
			return true;
		}

		$ignored = array(
			self::OPTION_KEY,
			'cron',
			'doing_cron',
			'rewrite_rules',
			'launchdek_client_active_run',
		);

		return in_array( $option, $ignored, true );
	}

	/**
	 * Resolve the current admin screen path for deep links.
	 *
	 * @return string
	 */
	private static function current_admin_path() {
		global $pagenow;

		return is_string( $pagenow ) ? $pagenow : '';
	}

	/**
	 * Resolve a plugin label from its bootstrap file.
	 *
	 * @param string $plugin Plugin bootstrap file.
	 * @return string
	 */
	private static function plugin_label( $plugin ) {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$path = WP_PLUGIN_DIR . '/' . ltrim( $plugin, '/' );
		if ( is_readable( $path ) ) {
			$data = get_plugin_data( $path, false, false );
			if ( ! empty( $data['Name'] ) ) {
				return $data['Name'];
			}
		}

		return $plugin;
	}

	/**
	 * Read the capture session option.
	 *
	 * @return array
	 */
	private static function get_session() {
		$session = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $session ) ) {
			return self::default_session();
		}

		$session = wp_parse_args( $session, self::default_session() );
		if ( ! is_array( $session['steps'] ) ) {
			$session['steps'] = array();
		}

		return $session;
	}

	/**
	 * Default capture session shape.
	 *
	 * @return array
	 */
	private static function default_session() {
		return array(
			'active'     => false,
			'started_at' => '',
			'started_by' => 0,
			'steps'      => array(),
		);
	}

	/**
	 * Format capture status for API responses.
	 *
	 * @param array $session Capture session.
	 * @return array
	 */
	private static function format_status( array $session ) {
		$steps = is_array( $session['steps'] ) ? $session['steps'] : array();

		return array(
			'active'     => ! empty( $session['active'] ),
			'started_at' => $session['started_at'] ?? '',
			'count'      => count( $steps ),
			'steps'      => self::get_checklist_steps(),
		);
	}
}
