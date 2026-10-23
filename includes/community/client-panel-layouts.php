<?php
/**
 * Client panel layout catalog shipped in the WordPress.org Community zip.
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'live_topbar'   => array(
		'label'       => __( 'Live top bar', 'checklistdek' ),
		'description' => __( 'Compact progress strip below the admin bar with a slide-down step drawer.', 'checklistdek' ),
	),
	'bottom_dock'   => array(
		'label'       => __( 'Bottom dock', 'checklistdek' ),
		'description' => __( 'Slim progress bar docked above the footer with a slide-up step drawer.', 'checklistdek' ),
	),
	'floating_pill' => array(
		'label'       => __( 'Floating pill', 'checklistdek' ),
		'description' => __( 'Small progress chip in the bottom-right corner that expands into a compact panel.', 'checklistdek' ),
	),
);
