<?php
/**
 * Infer WordPress admin deep links from checklist step text.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps step titles/instructions to wp-admin paths when no explicit deep_link is set.
 */
class LAUNCHDEK_Deep_Link_Resolver {

	/**
	 * Resolve an admin path for a checklist step.
	 *
	 * @param string $title        Step title.
	 * @param string $instructions Step instructions.
	 * @param string $existing     Existing deep_link value (admin path or URL).
	 * @return string Sanitized admin path or URL, or empty string.
	 */
	public static function resolve( $title, $instructions = '', $existing = '' ) {
		$sanitized = self::sanitize_admin_path( $existing );

		if ( '' !== $sanitized ) {
			return $sanitized;
		}

		$text = strtolower( trim( $title . ' ' . $instructions ) );

		if ( '' === $text ) {
			return '';
		}

		foreach ( self::get_rules() as $rule ) {
			if ( self::matches_rule( $text, $rule ) ) {
				return $rule['path'];
			}
		}

		return '';
	}

	/**
	 * Resolve deep_link for a step array.
	 *
	 * @param array $step Step definition.
	 * @return string
	 */
	public static function resolve_for_step( $step ) {
		if ( ! is_array( $step ) ) {
			return '';
		}

		if ( 'manual' !== ( $step['type'] ?? 'manual' ) ) {
			return self::sanitize_admin_path( $step['deep_link'] ?? '' );
		}

		return self::resolve(
			$step['title'] ?? '',
			$step['instructions'] ?? '',
			$step['deep_link'] ?? ''
		);
	}

	/**
	 * Sanitize a stored admin path or full URL.
	 *
	 * @param string $path Admin path or URL.
	 * @return string
	 */
	public static function sanitize_admin_path( $path ) {
		$path = trim( (string) $path );

		if ( '' === $path ) {
			return '';
		}

		if ( filter_var( $path, FILTER_VALIDATE_URL ) ) {
			return esc_url_raw( $path );
		}

		$path = preg_replace( '#^(?:/?wp-admin/)+#', '', $path );
		$path = ltrim( $path, '/' );

		if ( ! preg_match( '#^[a-zA-Z0-9_\-./?=&]+$#', $path ) ) {
			return '';
		}

		return $path;
	}

	/**
	 * Export inference rules for admin JavaScript.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_rules_for_js() {
		$rules = array();

		foreach ( self::get_rules() as $rule ) {
			$exported = array(
				'path' => $rule['path'],
			);

			if ( ! empty( $rule['any'] ) ) {
				$exported['any'] = array_values( $rule['any'] );
			}

			if ( ! empty( $rule['all'] ) ) {
				$exported['all'] = array_values( $rule['all'] );
			}

			$rules[] = $exported;
		}

		return $rules;
	}

	/**
	 * Whether a rule matches the normalized step text.
	 *
	 * @param string               $text Normalized lowercase text.
	 * @param array<string, mixed> $rule Rule definition.
	 * @return bool
	 */
	private static function matches_rule( $text, $rule ) {
		if ( ! empty( $rule['all'] ) ) {
			foreach ( $rule['all'] as $needle ) {
				if ( false === strpos( $text, $needle ) ) {
					return false;
				}
			}

			return true;
		}

		if ( ! empty( $rule['any'] ) ) {
			foreach ( $rule['any'] as $needle ) {
				if ( false !== strpos( $text, $needle ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Ordered inference rules — first match wins.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_rules() {
		return array(
			// WooCommerce.
			array(
				'all'  => array( 'woocommerce', 'checkout' ),
				'path' => 'admin.php?page=wc-settings&tab=checkout',
			),
			array(
				'all'  => array( 'woocommerce', 'shipping' ),
				'path' => 'admin.php?page=wc-settings&tab=shipping',
			),
			array(
				'all'  => array( 'woocommerce', 'tax' ),
				'path' => 'admin.php?page=wc-settings&tab=tax',
			),
			array(
				'all'  => array( 'woocommerce', 'email' ),
				'path' => 'admin.php?page=wc-settings&tab=email',
			),
			array(
				'all'  => array( 'woocommerce', 'payment' ),
				'path' => 'admin.php?page=wc-settings&tab=checkout',
			),
			array(
				'any'  => array( 'action scheduler', 'action-scheduler' ),
				'path' => 'admin.php?page=wc-status&tab=action-scheduler',
			),
			array(
				'any'  => array( 'woocommerce status', 'wc-status', 'system status' ),
				'path' => 'admin.php?page=wc-status',
			),
			array(
				'any'  => array( 'woocommerce', 'wc-settings' ),
				'path' => 'admin.php?page=wc-settings',
			),

			// Popular plugins.
			array(
				'any'  => array( 'wordfence', 'wordfence firewall' ),
				'path' => 'admin.php?page=Wordfence',
			),
			array(
				'any'  => array( 'yoast seo', 'yoast' ),
				'path' => 'admin.php?page=wpseo_dashboard',
			),
			array(
				'any'  => array( 'rank math', 'rank-math' ),
				'path' => 'admin.php?page=rank-math',
			),
			array(
				'any'  => array( 'google site kit', 'site kit' ),
				'path' => 'admin.php?page=googlesitekit-dashboard',
			),
			array(
				'any'  => array( 'elementor kit', 'elementor template', 'elementor library' ),
				'path' => 'edit.php?post_type=elementor_library',
			),
			array(
				'any'  => array( 'elementor settings', 'elementor role' ),
				'path' => 'admin.php?page=elementor-settings',
			),
			array(
				'any'  => array( 'elementor' ),
				'path' => 'admin.php?page=elementor',
			),
			array(
				'any'  => array( 'wpforms' ),
				'path' => 'admin.php?page=wpforms-overview',
			),
			array(
				'any'  => array( 'contact form 7', 'cf7' ),
				'path' => 'admin.php?page=wpcf7',
			),
			array(
				'any'  => array( 'litespeed cache', 'litespeed' ),
				'path' => 'admin.php?page=litespeed',
			),
			array(
				'any'  => array( 'wp super cache', 'wpsupercache' ),
				'path' => 'options-general.php?page=wpsupercache',
			),
			array(
				'any'  => array( 'updraftplus', 'updraft plus' ),
				'path' => 'options-general.php?page=updraftplus',
			),
			array(
				'any'  => array( 'optinmonster', 'optin monster' ),
				'path' => 'admin.php?page=optin-monster-dashboard',
			),
			array(
				'any'  => array( 'acf field', 'advanced custom fields', 'acf tools' ),
				'path' => 'edit.php?post_type=acf-field-group',
			),
			array(
				'any'  => array( 'loco translate', 'loco' ),
				'path' => 'admin.php?page=loco',
			),
			array(
				'any'  => array( 'easy digital downloads', 'edd ' ),
				'path' => 'edit.php?post_type=download',
			),

			// WordPress core — specific screens first.
			array(
				'any'  => array(
					'permalink',
					'permalinks',
					'rewrite rule',
					'rewrite rules',
					'flush permalinks',
					'pretty permalink',
					'post name structure',
					'/%postname%/',
					'settings → permalinks',
					'settings > permalinks',
					'settings->permalinks',
				),
				'path' => 'options-permalink.php',
			),
			array(
				'any'  => array(
					'search engine visibility',
					'discourage search engines',
					'homepage displays',
					'static front page',
					'posts page',
					'reading settings',
					'settings → reading',
					'settings > reading',
					'settings->reading',
					'block search engines',
				),
				'path' => 'options-reading.php',
			),
			array(
				'any'  => array(
					'privacy policy page',
					'privacy settings',
					'settings → privacy',
					'settings > privacy',
				),
				'path' => 'options-privacy.php',
			),
			array(
				'any'  => array(
					'default comment',
					'comment settings',
					'discussion settings',
					'pingback',
					'moderation rule',
					'settings → discussion',
					'settings > discussion',
				),
				'path' => 'options-discussion.php',
			),
			array(
				'any'  => array(
					'writing settings',
					'default category',
					'post via email',
					'settings → writing',
					'settings > writing',
				),
				'path' => 'options-writing.php',
			),
			array(
				'any'  => array(
					'media settings',
					'thumbnail size',
					'image size',
					'settings → media',
					'settings > media',
				),
				'path' => 'options-media.php',
			),
			array(
				'any'  => array(
					'site health',
					'health check',
					'tools → site health',
				),
				'path' => 'tools.php?page=site-health',
			),
			array(
				'any'  => array( 'export site', 'export content', 'tools → export' ),
				'path' => 'export.php',
			),
			array(
				'any'  => array( 'import site', 'import content', 'tools → import' ),
				'path' => 'import.php',
			),
			array(
				'any'  => array(
					'install plugin',
					'add plugin',
					'plugin install',
					'plugins → add new',
				),
				'path' => 'plugin-install.php',
			),
			array(
				'any'  => array(
					'install theme',
					'add theme',
					'theme install',
					'appearance → add new',
				),
				'path' => 'theme-install.php',
			),
			array(
				'any'  => array(
					'customize',
					'site identity',
					'site icon',
					'favicon',
					'appearance → customize',
					'appearance > customize',
				),
				'path' => 'customize.php',
			),
			array(
				'any'  => array(
					'navigation menu',
					'nav menu',
					'menus',
					'appearance → menus',
				),
				'path' => 'nav-menus.php',
			),
			array(
				'any'  => array( 'widgets', 'widget area' ),
				'path' => 'widgets.php',
			),
			array(
				'any'  => array(
					'wordpress update',
					'core update',
					'update wordpress',
					'update core',
					'security update',
					'update-core',
				),
				'path' => 'update-core.php',
			),
			array(
				'any'  => array(
					'add user',
					'new user',
					'create user',
					'invite user',
					'users → add new',
				),
				'path' => 'user-new.php',
			),
			array(
				'any'  => array(
					'admin account',
					'admin password',
					'profile password',
					'your profile',
					'users → profile',
				),
				'path' => 'profile.php',
			),
			array(
				'any'  => array(
					'spam comment',
					'moderate comment',
					'comment flood',
					'comments list',
					'edit comments',
				),
				'path' => 'edit-comments.php',
			),
			array(
				'any'  => array(
					'media library',
					'upload media',
					'upload image',
					'upload file',
					'broken image',
					'attachment',
					'media → library',
				),
				'path' => 'upload.php',
			),
			array(
				'any'  => array(
					'categories',
					'category list',
					'posts → categories',
				),
				'path' => 'edit-tags.php?taxonomy=category',
			),
			array(
				'any'  => array(
					'pages list',
					'edit pages',
					'all pages',
					'posts → pages',
					'post_type=page',
				),
				'path' => 'edit.php?post_type=page',
			),
			array(
				'any'  => array(
					'plugin conflict',
					'deactivate plugin',
					'activate plugin',
					'plugin health',
					'plugins screen',
					'plugins → installed',
				),
				'path' => 'plugins.php',
			),
			array(
				'any'  => array(
					'switch theme',
					'activate theme',
					'themes screen',
					'appearance → themes',
				),
				'path' => 'themes.php',
			),
			array(
				'any'  => array(
					'user list',
					'manage users',
					'remove user',
					'user role',
					'users screen',
				),
				'path' => 'users.php',
			),
			array(
				'any'  => array(
					'all posts',
					'edit posts',
					'blog post',
					'hello world',
					'sample content',
					'posts list',
					'posts → all posts',
				),
				'path' => 'edit.php',
			),
			array(
				'any'  => array(
					'site title',
					'tagline',
					'timezone',
					'date format',
					'time format',
					'site language',
					'administration email',
					'general settings',
					'settings → general',
					'settings > general',
					'settings->general',
					'wordpress address',
					'site address',
				),
				'path' => 'options-general.php',
			),
		);
	}
}
