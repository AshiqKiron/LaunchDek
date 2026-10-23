<?php
/**
 * Client panel layout catalog for the full / Pro distribution build.
 *
 * @package ChecklistDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'sidebar'        => array(
		'label'       => __( 'Right sidebar floater', 'checklistdek' ),
		'description' => __( 'Sticky panel on the right edge with a vertical expand tab when collapsed.', 'checklistdek' ),
	),
	'live_topbar'    => array(
		'label'       => __( 'Live top bar', 'checklistdek' ),
		'description' => __( 'Compact progress strip below the admin bar with a slide-down step drawer.', 'checklistdek' ),
	),
	'bottom_dock'    => array(
		'label'       => __( 'Bottom dock', 'checklistdek' ),
		'description' => __( 'Slim progress bar docked above the footer with a slide-up step drawer.', 'checklistdek' ),
	),
	'floating_pill'  => array(
		'label'       => __( 'Floating pill', 'checklistdek' ),
		'description' => __( 'Small progress chip in the bottom-right corner that expands into a compact panel.', 'checklistdek' ),
	),
	'toast'          => array(
		'label'       => __( 'Notification toast', 'checklistdek' ),
		'description' => __( 'Dismissible toast with current-step summary; expands into the full checklist on click.', 'checklistdek' ),
	),
	'admin_menu'     => array(
		'label'       => __( 'Admin bar flyout', 'checklistdek' ),
		'description' => __( 'Hidden until opened from the WordPress admin bar checklist shortcut.', 'checklistdek' ),
	),
	'fullscreen'     => array(
		'label'       => __( 'Fullscreen overlay', 'checklistdek' ),
		'description' => __( 'Modal takeover centered on screen for focused checklist completion.', 'checklistdek' ),
	),
	'focus_mode'     => array(
		'label'       => __( 'Focus mode', 'checklistdek' ),
		'description' => __( 'Single current-step card centered on screen with minimal distractions.', 'checklistdek' ),
	),
	'inline_metabox' => array(
		'label'       => __( 'Inline metabox', 'checklistdek' ),
		'description' => __( 'Embeds the checklist in the post/page editor sidebar metabox area.', 'checklistdek' ),
	),
);
