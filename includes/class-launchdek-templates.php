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

	const CATALOG_OPTION = 'launchdek_templates_catalog';

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
	 * Lightweight built-in template catalog for admin UI (metadata + step titles only).
	 *
	 * @return array
	 */
	public static function get_catalog() {
		$cache_key = self::get_catalog_cache_key();
		$stored    = get_option( self::CATALOG_OPTION, null );

		if (
			is_array( $stored )
			&& isset( $stored['key'], $stored['catalog'] )
			&& is_array( $stored['catalog'] )
			&& $stored['key'] === $cache_key
		) {
			return $stored['catalog'];
		}

		$catalog = self::build_catalog();

		update_option(
			self::CATALOG_OPTION,
			array(
				'key'     => $cache_key,
				'catalog' => $catalog,
			),
			false
		);

		return $catalog;
	}

	/**
	 * Drop cached built-in template catalog.
	 *
	 * @return void
	 */
	public static function clear_catalog_cache() {
		delete_option( self::CATALOG_OPTION );
	}

	/**
	 * Get a single built-in template definition from JSON.
	 *
	 * @param string $slug Template slug.
	 * @return array|null
	 */
	public static function get_builtin_by_slug( $slug ) {
		$slug = sanitize_key( $slug );
		if ( ! $slug ) {
			return null;
		}

		$file = self::templates_dir() . $slug . '.json';
		if ( ! is_readable( $file ) ) {
			return null;
		}

		return self::load_template_from_file( $file );
	}

	/**
	 * Get built-in template definitions from JSON files.
	 *
	 * @return array
	 */
	public static function get_builtin() {
		static $cache = null;

		if ( null !== $cache ) {
			return $cache;
		}

		$templates = array();

		foreach ( self::template_json_files() as $file ) {
			$data = self::load_template_from_file( $file );
			if ( ! $data ) {
				continue;
			}

			$templates[] = $data;
		}

		self::sort_templates( $templates );
		$cache = $templates;

		return $cache;
	}

	/**
	 * List built-in template JSON files.
	 *
	 * @return array<int, string>
	 */
	private static function template_json_files() {
		$dir = self::templates_dir();
		if ( ! is_dir( $dir ) ) {
			return array();
		}

		$files = glob( $dir . '*.json' );

		return is_array( $files ) ? $files : array();
	}

	/**
	 * Cache-busting key for the built-in template catalog.
	 *
	 * @return string
	 */
	private static function get_catalog_cache_key() {
		$files     = self::template_json_files();
		$max_mtime = 0;

		foreach ( $files as $file ) {
			$mtime = filemtime( $file );
			if ( false !== $mtime && $mtime > $max_mtime ) {
				$max_mtime = $mtime;
			}
		}

		return LAUNCHDEK_VERSION . ':' . count( $files ) . ':' . $max_mtime;
	}

	/**
	 * Build lightweight catalog entries from JSON files.
	 *
	 * @return array
	 */
	private static function build_catalog() {
		$templates = array();

		foreach ( self::template_json_files() as $file ) {
			$data = self::load_template_from_file( $file );
			if ( ! $data ) {
				continue;
			}

			$templates[] = self::template_to_catalog_entry( $data );
		}

		self::sort_templates( $templates );

		return $templates;
	}

	/**
	 * Parse and normalize a built-in template JSON file.
	 *
	 * @param string $file Absolute file path.
	 * @return array|null
	 */
	private static function load_template_from_file( $file ) {
		$contents = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$data     = json_decode( $contents, true );

		if ( ! is_array( $data ) ) {
			return null;
		}

		$data['template_slug'] = basename( $file, '.json' );
		$data['category']      = self::normalize_category( $data['category'] ?? '' );
		$data['is_template']   = true;
		$data['source']        = 'builtin';

		return $data;
	}

	/**
	 * Reduce a full template to catalog metadata and step titles.
	 *
	 * @param array $data Full template data.
	 * @return array
	 */
	private static function template_to_catalog_entry( $data ) {
		$steps          = is_array( $data['steps'] ?? null ) ? $data['steps'] : array();
		$step_summaries = array();

		foreach ( $steps as $step ) {
			if ( ! is_array( $step ) ) {
				continue;
			}

			$step_summaries[] = array(
				'title' => sanitize_text_field( $step['title'] ?? '' ),
			);
		}

		return array(
			'template_slug' => $data['template_slug'] ?? '',
			'title'         => sanitize_text_field( $data['title'] ?? '' ),
			'description'   => sanitize_textarea_field( $data['description'] ?? '' ),
			'category'      => $data['category'] ?? self::normalize_category( '' ),
			'version'       => sanitize_text_field( $data['version'] ?? '' ),
			'is_template'   => true,
			'source'        => 'builtin',
			'steps'         => $step_summaries,
		);
	}

	/**
	 * Sort templates by category order then title.
	 *
	 * @param array $templates Template list (passed by reference).
	 * @return void
	 */
	private static function sort_templates( array &$templates ) {
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

		self::repair_builtin_deep_links();
	}

	/**
	 * Restore admin-path deep links on built-in templates stripped by legacy esc_url_raw sanitization.
	 *
	 * @return void
	 */
	public static function repair_builtin_deep_links() {
		if ( get_option( 'launchdek_deep_links_repaired' ) ) {
			return;
		}

		$json_steps = array();
		foreach ( self::get_builtin() as $template ) {
			$slug = $template['template_slug'] ?? '';
			if ( $slug ) {
				$json_steps[ $slug ] = is_array( $template['steps'] ?? null ) ? $template['steps'] : array();
			}
		}

		$existing = LAUNCHDEK_Checklist_Repository::all(
			array(
				'is_template' => true,
				'limit'       => 200,
			)
		);

		foreach ( $existing as $checklist ) {
			$slug = $checklist['template_slug'] ?? '';
			if ( ! $slug || empty( $json_steps[ $slug ] ) || empty( $checklist['steps'] ) || ! is_array( $checklist['steps'] ) ) {
				continue;
			}

			$steps   = $checklist['steps'];
			$source  = $json_steps[ $slug ];
			$changed = false;

			foreach ( $steps as $index => $step ) {
				if ( ! is_array( $step ) || ! empty( $step['deep_link'] ) ) {
					continue;
				}

				$source_link = $source[ $index ]['deep_link'] ?? '';
				if ( '' === $source_link ) {
					continue;
				}

				$steps[ $index ]['deep_link'] = LAUNCHDEK_Admin_Deep_Links::normalize_stored_path( $source_link );
				$changed                      = true;
			}

			if ( $changed ) {
				LAUNCHDEK_Checklist_Repository::update(
					(int) $checklist['id'],
					array(
						'steps' => $steps,
					)
				);
			}
		}

		update_option( 'launchdek_deep_links_repaired', 1, false );
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
