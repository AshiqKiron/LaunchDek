<?php
/**
 * Freemius SDK bootstrap (full / premium distribution only).
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'cld_fs' ) ) {
	/**
	 * Freemius SDK accessor.
	 *
	 * @return Freemius
	 */
	function cld_fs() {
		global $cld_fs;

		if ( ! isset( $cld_fs ) ) {
			require_once CHECKLISTDEK_PLUGIN_DIR . 'vendor/freemius/start.php';

			$cld_fs = fs_dynamic_init(
				array(
					'id'                  => '40957',
					'slug'                => 'checklistdek',
					'type'                => 'plugin',
					'public_key'          => 'pk_0fe462a7788c710c01f748d82ca0a',
					'is_premium'          => true,
					'has_premium_version' => true,
					'has_addons'          => false,
					'has_paid_plans'      => true,
					'is_org_compliant'    => true,
					'wp_org_gatekeeper'   => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
					'menu'                => array(
						'slug'    => 'checklistdek',
						'support' => false,
					),
				)
			);
		}

		return $cld_fs;
	}

	cld_fs();
	do_action( 'cld_fs_loaded' );
}
