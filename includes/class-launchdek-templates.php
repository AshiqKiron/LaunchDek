<?php
/**
 * Built-in workflow templates and agency vault.
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
	 * Templates directory.
	 *
	 * @return string
	 */
	public static function templates_dir() {
		return LAUNCHDEK_PLUGIN_DIR . 'templates/';
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
			$data['is_template']   = true;
			$data['source']        = 'builtin';
			$templates[]           = $data;
		}

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
			$existing = LAUNCHDEK_Workflow_Repository::all( array(
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
				LAUNCHDEK_Workflow_Repository::create( $template );
			}
		}

		update_option( 'launchdek_templates_seeded', 1, false );
	}

	/**
	 * Get agency vault workflows.
	 *
	 * @return array
	 */
	public static function get_vault() {
		return LAUNCHDEK_Workflow_Repository::all( array(
			'is_vault' => true,
			'limit'    => 100,
		) );
	}

	/**
	 * Save workflow to agency vault.
	 *
	 * @param int $workflow_id Workflow ID.
	 * @return bool
	 */
	public static function save_to_vault( $workflow_id ) {
		return LAUNCHDEK_Workflow_Repository::update( $workflow_id, array(
			'is_vault'    => true,
			'is_template' => true,
		) );
	}

	/**
	 * Clone a template into an editable workflow.
	 *
	 * @param array $template Template data.
	 * @return int|false
	 */
	public static function clone_template( $template ) {
		unset( $template['id'], $template['source'] );

		$template['is_template'] = false;
		$template['is_vault']    = false;

		return LAUNCHDEK_Workflow_Repository::create( $template );
	}
}
