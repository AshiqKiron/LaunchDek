<?php
/**
 * Private Agency Vault — reusable team checklist templates on the hub.
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Agency vault persistence, REST routes, admin UI, and assets.
 */
class CHECKLISTDEK_Agency_Vault {

	/**
	 * Ensure checklist is_vault schema exists when the vault module is bundled.
	 *
	 * @return void
	 */
	public static function migrate_schema() {
		global $wpdb;

		$table = CHECKLISTDEK_Checklist_Repository::table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$column = $wpdb->get_results(
			$wpdb->prepare(
				'SHOW COLUMNS FROM %i LIKE %s',
				$table,
				'is_vault'
			)
		);

		if ( ! empty( $column ) ) {
			CHECKLISTDEK_Installer::add_index_if_missing( $table, 'is_vault', 'is_vault' );
			return;
		}

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
			$wpdb->prepare(
				'ALTER TABLE %i ADD `is_vault` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_template`', // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
				$table
			)
		);
		CHECKLISTDEK_Installer::add_index_if_missing( $table, 'is_vault', 'is_vault' );
	}

	/**
	 * Wire vault UI, REST routes, and admin assets.
	 *
	 * @return void
	 */
	public static function register() {
		if ( ! class_exists( 'CHECKLISTDEK_Licensing', false ) || ! CHECKLISTDEK_Licensing::can_use_agency_vault() ) {
			return;
		}

		add_action( 'checklistdek_checklists_after_builtin_templates', array( __CLASS__, 'render_templates_section' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ), 20 );
	}

	/**
	 * @return void
	 */
	public static function render_templates_section() {
		$partial = CHECKLISTDEK_PLUGIN_DIR . 'admin/partials/checklistdek-checklists-vault-section.php';
		if ( is_readable( $partial ) ) {
			require $partial;
		}
	}

	/**
	 * @return void
	 */
	public static function register_rest_routes() {
		register_rest_route(
			CHECKLISTDEK_REST_NAMESPACE,
			'/templates/vault',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( __CLASS__, 'rest_get_vault' ),
					'permission_callback' => array( 'CHECKLISTDEK_REST_API', 'can_edit_checklists' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( __CLASS__, 'rest_save_to_vault' ),
					'permission_callback' => array( 'CHECKLISTDEK_REST_API', 'can_edit_checklists' ),
				),
			)
		);
	}

	/**
	 * @return WP_REST_Response
	 */
	public static function rest_get_vault() {
		return rest_ensure_response( self::summary_list() );
	}

	/**
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function rest_save_to_vault( $request ) {
		$data = $request->get_json_params();
		$id   = absint( $data['checklist_id'] ?? 0 );
		if ( ! $id ) {
			return new WP_Error( 'missing_id', __( 'Checklist ID required.', 'checklistdek' ), array( 'status' => 400 ) );
		}

		if ( ! self::save_to_vault( $id ) ) {
			return new WP_Error( 'vault_save_failed', __( 'Failed to save checklist to vault.', 'checklistdek' ), array( 'status' => 500 ) );
		}

		$checklist = CHECKLISTDEK_Checklist_Repository::find( $id );
		if ( ! $checklist ) {
			return new WP_Error( 'not_found', __( 'Checklist not found.', 'checklistdek' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response( $checklist );
	}

	/**
	 * Vault checklist summaries for the Templates tab grid.
	 *
	 * @return array
	 */
	public static function summary_list() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT id, title, description, steps_json FROM %i WHERE is_vault = %d ORDER BY title ASC LIMIT %d',
				CHECKLISTDEK_Checklist_Repository::table(),
				1,
				100
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( 'CHECKLISTDEK_Checklist_Repository', 'format_summary' ), $rows );
	}

	/**
	 * Mark a custom checklist as a vault template.
	 *
	 * @param int $checklist_id Checklist ID.
	 * @return bool
	 */
	public static function save_to_vault( $checklist_id ) {
		global $wpdb;

		$checklist_id = absint( $checklist_id );
		if ( $checklist_id <= 0 ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			CHECKLISTDEK_Checklist_Repository::table(),
			array(
				'is_vault'    => 1,
				'is_template' => 1,
				'updated_at'  => current_time( 'mysql', true ),
			),
			array( 'id' => $checklist_id ),
			array( '%d', '%d', '%s' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			CHECKLISTDEK_Audit_Log::log( 'checklist_updated', array( 'checklist_id' => $checklist_id ) );
			CHECKLISTDEK_Dashboard_Cache::invalidate_stats();
			CHECKLISTDEK_Dashboard_Cache::invalidate_quick_launch_picker();
		}

		return false !== $result;
	}

	/**
	 * Enqueue vault admin script on the Checklists screen.
	 *
	 * @param string $hook Admin hook suffix.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook ) {
		if ( CHECKLISTDEK_Admin::PAGE_SLUG . '_page_' . CHECKLISTDEK_Admin::PAGE_SLUG . '-checklists' !== $hook ) {
			return;
		}

		$path = CHECKLISTDEK_PLUGIN_DIR . 'admin/js/checklistdek-admin-vault.js';
		if ( ! is_readable( $path ) ) {
			return;
		}

		wp_enqueue_script(
			'checklistdek-admin-vault',
			CHECKLISTDEK_PLUGIN_URL . 'admin/js/checklistdek-admin-vault.js',
			array( 'checklistdek-admin' ),
			(string) filemtime( $path ),
			true
		);

		wp_localize_script(
			'checklistdek-admin-vault',
			'checklistdekAdminVault',
			array(
				'strings' => array(
					'savedToVault'     => __( 'Saved to vault.', 'checklistdek' ),
					'noVaultTemplates' => __( 'No vault templates yet.', 'checklistdek' ),
					'selectChecklist'  => __( 'Select checklist…', 'checklistdek' ),
					'vaultBadge'       => __( 'Vault', 'checklistdek' ),
				),
			)
		);
	}
}
