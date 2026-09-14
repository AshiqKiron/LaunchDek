<?php
/**
 * Resolve WordPress admin paths for checklist steps.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps checklist step definitions to wp-admin deep-link paths.
 */
class LAUNCHDEK_Admin_Deep_Links {

	/**
	 * Resolve an admin path for a checklist step definition.
	 *
	 * @param array $step Step definition from a checklist.
	 * @return string Admin path (e.g. options-general.php) or empty string.
	 */
	public static function resolve_path( array $step ) {
		if ( ! empty( $step['deep_link'] ) ) {
			return self::normalize_stored_path( $step['deep_link'] );
		}

		$type = sanitize_key( $step['type'] ?? 'manual' );

		if ( 'api' === $type && ! empty( $step['api'] ) && is_array( $step['api'] ) ) {
			$path = self::path_from_api_step( $step['api'] );
			if ( $path ) {
				return $path;
			}
		}

		return self::infer_path_from_text(
			$step['title'] ?? '',
			$step['instructions'] ?? ''
		);
	}

	/**
	 * Infer a wp-admin path from step title and instructions.
	 *
	 * @param string $title        Step title.
	 * @param string $instructions Step instructions.
	 * @return string
	 */
	public static function infer_path_from_text( $title, $instructions = '' ) {
		$haystack = strtolower( wp_strip_all_tags( (string) $title . ' ' . (string) $instructions ) );

		if ( '' === trim( $haystack ) ) {
			return '';
		}

		foreach ( self::get_text_inference_rules() as $rule ) {
			foreach ( $rule['patterns'] as $pattern ) {
				if ( preg_match( $pattern, $haystack ) ) {
					return $rule['path'];
				}
			}
		}

		return '';
	}

	/**
	 * Ordered keyword rules for inferring admin paths from natural-language steps.
	 *
	 * @return array<int, array{path: string, patterns: string[]}>
	 */
	private static function get_text_inference_rules() {
		return array(
			array(
				'path'     => 'options-permalink.php',
				'patterns' => array(
					'/settings\s*(?:→|>|\/|-)\s*permalinks?/',
					'/\bpermalinks?\b/',
					'/\bpost name\b/',
					'/\/%postname%/',
					'/\brewrite rules?\b/',
				),
			),
			array(
				'path'     => 'options-reading.php',
				'patterns' => array(
					'/settings\s*(?:→|>|\/|-)\s*reading/',
					'/\bsearch engine visibility\b/',
					'/\bhomepage displays\b/',
					'/\bposts page\b/',
					'/\bblog public\b/',
				),
			),
			array(
				'path'     => 'options-discussion.php',
				'patterns' => array(
					'/settings\s*(?:→|>|\/|-)\s*discussion/',
					'/\bcomment settings\b/',
					'/\bdefault comment status\b/',
				),
			),
			array(
				'path'     => 'options-media.php',
				'patterns' => array(
					'/settings\s*(?:→|>|\/|-)\s*media/',
					'/\bmedia settings\b/',
					'/\bthumbnail size\b/',
				),
			),
			array(
				'path'     => 'options-privacy.php',
				'patterns' => array(
					'/settings\s*(?:→|>|\/|-)\s*privacy/',
					'/\bprivacy policy page\b/',
				),
			),
			array(
				'path'     => 'options-general.php',
				'patterns' => array(
					'/settings\s*(?:→|>|\/|-)\s*general/',
					'/\bsite title\b/',
					'/\btagline\b/',
					'/\badmin(?:istration)? email\b/',
					'/\btimezone\b/',
					'/\bdate format\b/',
					'/\btime format\b/',
					'/\bsite language\b/',
					'/\bwp_lang\b/',
				),
			),
			array(
				'path'     => 'plugins.php',
				'patterns' => array(
					'/\bplugins?\b/',
					'/\bactivate (?:the )?plugin\b/',
					'/\bdeactivate (?:the )?plugin\b/',
				),
			),
			array(
				'path'     => 'plugin-install.php',
				'patterns' => array(
					'/\binstall (?:a )?plugin\b/',
					'/\badd new plugin\b/',
					'/\bplugin install\b/',
				),
			),
			array(
				'path'     => 'users.php',
				'patterns' => array(
					'/\busers?\b/',
					'/\buser roles?\b/',
					'/\badmin user\b/',
				),
			),
			array(
				'path'     => 'profile.php',
				'patterns' => array(
					'/\bprofile\b/',
					'/\baccount settings\b/',
				),
			),
			array(
				'path'     => 'upload.php',
				'patterns' => array(
					'/\bmedia library\b/',
					'/\bupload(?:s|ed)? media\b/',
					'/\bbroken images?\b/',
				),
			),
			array(
				'path'     => 'nav-menus.php',
				'patterns' => array(
					'/\bnav(?:igation)? menus?\b/',
					'/\bmenu items?\b/',
				),
			),
			array(
				'path'     => 'edit-comments.php',
				'patterns' => array(
					'/\bcomments?\b/',
					'/\bcomment spam\b/',
					'/\bmoderate comments\b/',
				),
			),
			array(
				'path'     => 'themes.php',
				'patterns' => array(
					'/\bthemes?\b/',
					'/\bswitch theme\b/',
				),
			),
			array(
				'path'     => 'customize.php',
				'patterns' => array(
					'/\bcustomizer\b/',
					'/\bcustomize\b/',
					'/\bsite identity\b/',
				),
			),
			array(
				'path'     => 'site-health.php',
				'patterns' => array(
					'/\bsite health\b/',
				),
			),
			array(
				'path'     => 'update-core.php',
				'patterns' => array(
					'/\bupdate core\b/',
					'/\bwordpress updates?\b/',
					'/\bcore updates?\b/',
				),
			),
			array(
				'path'     => 'edit.php',
				'patterns' => array(
					'/\b(?:edit|manage|create|add|new) (?:blog )?posts?\b/',
					'/\bposts? list\b/',
				),
			),
			array(
				'path'     => 'edit.php?post_type=page',
				'patterns' => array(
					'/\b(?:edit|manage|create|add|new) pages?\b/',
					'/\bpages? list\b/',
				),
			),
		);
	}

	/**
	 * Infer an admin path from an API step definition.
	 *
	 * @param array $api API step config (route, method, payload).
	 * @return string
	 */
	public static function path_from_api_step( array $api ) {
		$route = self::normalize_rest_route( $api['route'] ?? '' );

		if ( '' === $route ) {
			return '';
		}

		if ( '/wp/v2/settings' === $route ) {
			return self::path_from_settings_payload( $api['payload'] ?? array() );
		}

		$route_map = array(
			'/wp/v2/plugins'      => 'plugins.php',
			'/wp/v2/users'        => 'users.php',
			'/wp/v2/media'        => 'upload.php',
			'/wp/v2/comments'     => 'edit-comments.php',
			'/wp/v2/themes'       => 'themes.php',
			'/wp/v2/navigation'   => 'nav-menus.php',
			'/wp/v2/menu-items'   => 'nav-menus.php',
			'/wp/v2/pages'        => 'edit.php?post_type=page',
			'/wp/v2/posts'        => 'edit.php',
			'/wp/v2/categories'   => 'edit-tags.php?taxonomy=category',
			'/wp/v2/tags'         => 'edit-tags.php?taxonomy=post_tag',
			'/wp/v2/block-types'  => 'site-editor.php',
			'/wp/v2/templates'    => 'site-editor.php',
			'/wp/v2/template-parts' => 'site-editor.php',
			'/wp/v2/global-styles' => 'site-editor.php',
		);

		foreach ( $route_map as $prefix => $path ) {
			if ( 0 === strpos( $route, $prefix ) ) {
				return $path;
			}
		}

		return '';
	}

	/**
	 * Map REST settings payload fields to a wp-admin screen.
	 *
	 * @param array $payload Settings payload keys.
	 * @return string
	 */
	public static function path_from_settings_payload( array $payload ) {
		if ( empty( $payload ) ) {
			return 'options-general.php';
		}

		$field_paths = self::get_settings_field_paths();
		$paths       = array();

		foreach ( array_keys( $payload ) as $field ) {
			$field = LAUNCHDEK_Settings::normalize_exclude_option( (string) $field );

			if ( isset( $field_paths[ $field ] ) ) {
				$paths[] = $field_paths[ $field ];
			}
		}

		if ( empty( $paths ) ) {
			return 'options-general.php';
		}

		return self::pick_settings_admin_path( $paths );
	}

	/**
	 * REST settings field names mapped to wp-admin screens.
	 *
	 * @return array<string, string>
	 */
	public static function get_settings_field_paths() {
		return array(
			'title'                  => 'options-general.php',
			'description'            => 'options-general.php',
			'url'                    => 'options-general.php',
			'email'                  => 'options-general.php',
			'timezone_string'        => 'options-general.php',
			'date_format'            => 'options-general.php',
			'time_format'            => 'options-general.php',
			'start_of_week'          => 'options-general.php',
			'language'               => 'options-general.php',
			'WPLANG'                 => 'options-general.php',
			'wp_environment_type'    => 'options-general.php',
			'site_logo'              => 'options-general.php',
			'site_icon'              => 'options-general.php',
			'blog_public'            => 'options-reading.php',
			'show_on_front'          => 'options-reading.php',
			'page_on_front'          => 'options-reading.php',
			'page_for_posts'         => 'options-reading.php',
			'posts_per_page'         => 'options-reading.php',
			'default_comment_status' => 'options-discussion.php',
			'default_ping_status'    => 'options-discussion.php',
			'comment_moderation'     => 'options-discussion.php',
			'permalink_structure'    => 'options-permalink.php',
		);
	}

	/**
	 * Choose the most relevant settings screen when multiple fields are present.
	 *
	 * @param string[] $paths Candidate admin paths.
	 * @return string
	 */
	private static function pick_settings_admin_path( array $paths ) {
		$unique = array_values( array_unique( $paths ) );

		if ( 1 === count( $unique ) ) {
			return $unique[0];
		}

		$priority = array(
			'options-permalink.php',
			'options-reading.php',
			'options-discussion.php',
			'options-media.php',
			'options-privacy.php',
			'options-general.php',
		);

		foreach ( $priority as $path ) {
			if ( in_array( $path, $unique, true ) ) {
				return $path;
			}
		}

		return $unique[0];
	}

	/**
	 * Normalize a REST route to /wp/v2/... format.
	 *
	 * @param string $route REST route.
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
	 * Sanitize a stored admin path or pass through a full URL unchanged.
	 *
	 * @param string $path Admin path or URL.
	 * @return string
	 */
	public static function normalize_stored_path( $path ) {
		$path = trim( (string) $path );

		if ( '' === $path ) {
			return '';
		}

		if ( filter_var( $path, FILTER_VALIDATE_URL ) ) {
			return esc_url_raw( $path );
		}

		return sanitize_text_field( ltrim( $path, '/' ) );
	}
}
