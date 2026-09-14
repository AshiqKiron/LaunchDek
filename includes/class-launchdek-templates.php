<?php
/**
 * Built-in checklist templates and agency vault.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template manager.
 */
class LAUNCHDEK_Templates {

	/**
	 * Built-in template category definitions (display order).
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function get_categories() {
		return array(
			'security'    => array(
				'label'       => __( 'Security', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Hardening, audits, and access controls for client sites.', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'launch'      => array(
				'label'       => __( 'Launch & Migration', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Go-live, migration, and client onboarding checklists.', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'performance' => array(
				'label'       => __( 'Performance', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Speed, caching, and Core Web Vitals baselines.', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'seo'         => array(
				'label'       => __( 'SEO & Growth', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Search, analytics, and local visibility setup.', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'ecommerce'   => array(
				'label'       => __( 'E-Commerce', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'WooCommerce store launch and optimization.', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'maintenance' => array(
				'label'       => __( 'Maintenance', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Recurring care, updates, and backup verification.', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'compliance'  => array(
				'label'       => __( 'Compliance & Privacy', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'GDPR, cookies, accessibility, and legal pages.', LAUNCHDEK_TEXT_DOMAIN ),
			),
			'troubleshooting' => array(
				'label'       => __( 'Troubleshooting', LAUNCHDEK_TEXT_DOMAIN ),
				'description' => __( 'Step-by-step fixes for common WordPress errors and outages.', LAUNCHDEK_TEXT_DOMAIN ),
			),
		);
	}

	/**
	 * Templates directory.
	 *
	 * @return string
	 */
	public static function templates_dir() {
		return LAUNCHDEK_PLUGIN_DIR . 'templates/';
	}

	/**
	 * Normalize category slug against known categories.
	 *
	 * @param string $category Raw category slug.
	 * @return string
	 */
	public static function normalize_category( $category ) {
		$category = sanitize_key( $category );
		$allowed  = array_keys( self::get_categories() );

		if ( in_array( $category, $allowed, true ) ) {
			return $category;
		}

		return 'maintenance';
	}

	/**
	 * Get built-in template definitions from JSON files.
	 *
	 * @return array
	 */
	public static function get_builtin() {
		$templates = array();
		$dir       = self::templates_dir();

		if ( ! is_dir( $dir ) ) {
			return $templates;
		}

		foreach ( glob( $dir . '*.json' ) as $file ) {
			$contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$data     = json_decode( $contents, true );

			if ( ! is_array( $data ) ) {
				continue;
			}

			$data['template_slug'] = basename( $file, '.json' );
			$data['category']      = self::normalize_category( $data['category'] ?? '' );
			$data['is_template']   = true;
			$data['source']        = 'builtin';
			$templates[]           = $data;
		}

		$category_order = array_keys( self::get_categories() );

		usort(
			$templates,
			static function ( $a, $b ) use ( $category_order ) {
				$a_idx = array_search( $a['category'], $category_order, true );
				$b_idx = array_search( $b['category'], $category_order, true );

				if ( false === $a_idx ) {
					$a_idx = count( $category_order );
				}
				if ( false === $b_idx ) {
					$b_idx = count( $category_order );
				}

				if ( $a_idx !== $b_idx ) {
					return $a_idx - $b_idx;
				}

				return strcasecmp( $a['title'] ?? '', $b['title'] ?? '' );
			}
		);

		return $templates;
	}

	/**
	 * Install built-in templates into database on first run.
	 *
	 * @return void
	 */
	public static function seed_builtin() {
		if ( get_option( 'launchdek_templates_seeded' ) ) {
			return;
		}

		foreach ( self::get_builtin() as $template ) {
			$existing = LAUNCHDEK_Checklist_Repository::all( array(
				'is_template' => true,
				'limit'       => 100,
			) );

			$found = false;
			foreach ( $existing as $wf ) {
				if ( $wf['template_slug'] === ( $template['template_slug'] ?? '' ) ) {
					$found = true;
					break;
				}
			}

			if ( ! $found ) {
				LAUNCHDEK_Checklist_Repository::create( $template );
			}
		}

		update_option( 'launchdek_templates_seeded', 1, false );

		self::sync_builtin();
	}

	/**
	 * Ensure any new built-in JSON templates exist in the database.
	 *
	 * @return void
	 */
	public static function sync_builtin() {
		$existing = LAUNCHDEK_Checklist_Repository::all(
			array(
				'is_template' => true,
				'limit'       => 200,
			)
		);

		$known_slugs = array();
		foreach ( $existing as $checklist ) {
			if ( ! empty( $checklist['template_slug'] ) ) {
				$known_slugs[ $checklist['template_slug'] ] = true;
			}
		}

		foreach ( self::get_builtin() as $template ) {
			$slug = $template['template_slug'] ?? '';
			if ( ! $slug || isset( $known_slugs[ $slug ] ) ) {
				continue;
			}

			LAUNCHDEK_Checklist_Repository::create( $template );
			$known_slugs[ $slug ] = true;
		}
	}

	/**
	 * Get agency vault checklists.
	 *
	 * @return array
	 */
	public static function get_vault() {
		return LAUNCHDEK_Checklist_Repository::all( array(
			'is_vault' => true,
			'limit'    => 100,
		) );
	}

	/**
	 * Save checklist to agency vault.
	 *
	 * @param int $checklist_id Checklist ID.
	 * @return bool
	 */
	public static function save_to_vault( $checklist_id ) {
		return LAUNCHDEK_Checklist_Repository::update( $checklist_id, array(
			'is_vault'    => true,
			'is_template' => true,
		) );
	}

	/**
	 * Clone a template into an editable checklist.
	 *
	 * @param array $template Template data.
	 * @return int|false
	 */
	public static function clone_template( $template ) {
		unset( $template['id'], $template['source'] );

		$template['is_template'] = false;
		$template['is_vault']    = false;

		return LAUNCHDEK_Checklist_Repository::create( $template );
	}
}
