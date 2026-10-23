<?php
/**
 * Sites admin page.
 *
 * @package ChecklistDek
 *
 * @var array  $settings   Plugin settings.
 * @var string $page       Page identifier.
 * @var array  $sites_list Cached sites list for the default table view.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Admin view partial; variables are template-scoped.

$sites_list           = isset( $sites_list ) && is_array( $sites_list ) ? $sites_list : CHECKLISTDEK_Dashboard_Cache::get_sites_list();
$site_group_catalog   = CHECKLISTDEK_Settings::get_site_group_catalog();

/**
 * Render translated health label for the sites table.
 *
 * @param string $status Health status slug.
 * @return string
 */
if ( ! function_exists( 'checklistdek_sites_health_label' ) ) :
function checklistdek_sites_health_label( $status ) {
	if ( 'healthy' === $status ) {
		return __( 'OK', 'checklistdek' );
	}
	if ( 'unhealthy' === $status ) {
		return __( 'Fail', 'checklistdek' );
	}

	return __( 'Unknown', 'checklistdek' );
}
endif;

/**
 * Render combined connection and health status markup for a site row.
 *
 * @param array $site Site payload.
 * @return string Escaped HTML.
 */
if ( ! function_exists( 'checklistdek_sites_status_html' ) ) :
function checklistdek_sites_status_html( $site ) {
	$status     = sanitize_key( $site['health_status'] ?? 'unknown' );
	$last_error = isset( $site['last_error'] ) ? sanitize_textarea_field( wp_strip_all_tags( (string) $site['last_error'] ) ) : '';

	if ( 'healthy' === $status ) {
		$label = __( 'Connection OK', 'checklistdek' );
	} elseif ( 'unhealthy' === $status ) {
		$label = __( 'Connection not working', 'checklistdek' );
		if ( '' !== $last_error ) {
			$label .= ': ' . $last_error;
		}
	} else {
		$label = __( 'Unknown', 'checklistdek' );
	}

	$html  = '<div class="checklistdek-site-status" title="' . esc_attr( $label ) . '">';
	$html .= '<span class="checklistdek-site-connection" aria-hidden="true">';
	$html .= '<span class="checklistdek-connection-dot ' . esc_attr( $status ) . '"></span>';
	if ( 'unhealthy' === $status ) {
		$html .= '<span class="dashicons dashicons-warning checklistdek-connection-warning"></span>';
	}
	$html .= '</span>';
	$html .= '<span class="checklistdek-badge checklistdek-site-health-badge ' . esc_attr( $status ) . '">' . esc_html( checklistdek_sites_health_label( $status ) ) . '</span>';
	$html .= '<span class="screen-reader-text">' . esc_html( $label ) . '</span>';
	$html .= '</div>';

	return $html;
}
endif;

/**
 * Site label for the sites table (name when set, otherwise URL host).
 *
 * @param array $site Site payload.
 * @return string
 */
if ( ! function_exists( 'checklistdek_site_row_display_name' ) ) :
function checklistdek_site_row_display_name( $site ) {
	$name = trim( (string) ( $site['name'] ?? '' ) );
	$url  = trim( (string) ( $site['url'] ?? '' ) );

	if ( $name !== '' && ( $url === '' || 0 !== strcasecmp( $name, $url ) ) ) {
		return $name;
	}

	if ( $url !== '' ) {
		return $url;
	}

	return $name;
}
endif;

/**
 * URL label for the sites table (full stored URL).
 *
 * @param string $url Site URL.
 * @return string
 */
if ( ! function_exists( 'checklistdek_site_url_display_label' ) ) :
function checklistdek_site_url_display_label( $url ) {
	return trim( (string) $url );
}
endif;

/**
 * Render the site name / URL cell for the sites table.
 *
 * @param array $site Site payload.
 * @return string Escaped HTML.
 */
if ( ! function_exists( 'checklistdek_site_row_name_cell_html' ) ) :
function checklistdek_site_row_name_cell_html( $site ) {
	$site_id = absint( $site['id'] ?? 0 );
	$name    = trim( (string) ( $site['name'] ?? '' ) );
	$url     = trim( (string) ( $site['url'] ?? '' ) );

	$html  = checklistdek_sites_history_toggle_html( $site_id );
	$html .= '<div class="checklistdek-site-name-cell">';

	$show_distinct_name = $name !== '' && $url !== '' && 0 !== strcasecmp( $name, $url );

	if ( $show_distinct_name ) {
		$html .= '<span class="checklistdek-site-name-label">' . esc_html( $name ) . '</span>';
		if ( $url !== '' ) {
			$url_label = checklistdek_site_url_display_label( $url );
			$html     .= '<a class="checklistdek-site-url-link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener" title="' . esc_attr( $url ) . '">' . esc_html( $url_label ) . '</a>';
		}
	} elseif ( $url !== '' ) {
		$url_label = checklistdek_site_url_display_label( $url );
		$link_text = $url_label !== '' ? $url_label : checklistdek_site_row_display_name( $site );
		$html     .= '<a class="checklistdek-site-url-link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener" title="' . esc_attr( $url ) . '">' . esc_html( $link_text ) . '</a>';
	} else {
		$html .= '<span class="checklistdek-site-name-label">' . esc_html( $name !== '' ? $name : '—' ) . '</span>';
	}

	if ( ! empty( $site['client_agent'] ) ) {
		$html .= '<span class="checklistdek-badge healthy checklistdek-client-agent-badge">' . esc_html__( 'Client panel', 'checklistdek' ) . '</span>';
	}

	$html .= '</div>';

	return $html;
}
endif;

/**
 * Render site row actions dropdown markup.
 *
 * @param array $site Site payload.
 * @return string Escaped HTML.
 */
if ( ! function_exists( 'checklistdek_sites_actions_html' ) ) :
function checklistdek_sites_actions_html( $site ) {
	$site_id            = absint( $site['id'] ?? 0 );
	$health_status      = sanitize_key( $site['health_status'] ?? 'unknown' );
	$connection_blocked = 'unhealthy' === $health_status;
	$menu_label         = __( 'More site actions', 'checklistdek' );

	$html  = '<div class="checklistdek-site-actions-dropdown">';
	$html .= '<div class="checklistdek-site-actions-split">';
	$html .= '<button type="button" class="button button-small checklistdek-edit-site" data-id="' . esc_attr( (string) $site_id ) . '">' . esc_html__( 'Edit', 'checklistdek' ) . '</button>';
	$html .= '<button type="button" class="button button-small checklistdek-site-actions-toggle" data-id="' . esc_attr( (string) $site_id ) . '" aria-haspopup="true" aria-expanded="false" aria-label="' . esc_attr( $menu_label ) . '">';
	$html .= '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>';
	$html .= '</button>';
	$html .= '</div>';
	$html .= '<div class="checklistdek-site-actions-menu" role="menu" hidden>';
	$html .= '<button type="button" role="menuitem" class="checklistdek-push-checklist" data-id="' . esc_attr( (string) $site_id ) . '" data-name="' . esc_attr( $site['name'] ?? '' ) . '"' . disabled( $connection_blocked, true, false ) . '>' . esc_html__( 'Push Checklist', 'checklistdek' ) . '</button>';
	$html .= '<button type="button" role="menuitem" class="checklistdek-test-site" data-id="' . esc_attr( (string) $site_id ) . '">' . esc_html__( 'Test', 'checklistdek' ) . '</button>';
	$html .= '<button type="button" role="menuitem" class="checklistdek-site-more-info" data-id="' . esc_attr( (string) $site_id ) . '">' . esc_html__( 'More info', 'checklistdek' ) . '</button>';
	$html .= '<button type="button" role="menuitem" class="checklistdek-add-to-group" data-id="' . esc_attr( (string) $site_id ) . '" data-name="' . esc_attr( $site['name'] ?? '' ) . '">' . esc_html__( 'Add to group', 'checklistdek' ) . '</button>';
	$activity_url = admin_url( 'admin.php?page=' . CHECKLISTDEK_Admin::PAGE_SLUG . '-activity-logs&site_id=' . $site_id );
	$html .= '<a href="' . esc_url( $activity_url ) . '" role="menuitem" class="checklistdek-site-activity-log">' . esc_html__( 'Activity Log', 'checklistdek' ) . '</a>';
	$html .= '<button type="button" role="menuitem" class="checklistdek-delete-site" data-id="' . esc_attr( (string) $site_id ) . '">' . esc_html__( 'Delete', 'checklistdek' ) . '</button>';
	$html .= '</div>';
	$html .= '</div>';

	return $html;
}
endif;

/**
 * Render checklist history toggle for a site row.
 *
 * @param int $site_id Site ID.
 * @return string
 */
if ( ! function_exists( 'checklistdek_sites_history_toggle_html' ) ) :
function checklistdek_sites_history_toggle_html( $site_id ) {
	$site_id = absint( $site_id );
	$label   = __( 'Show checklist history', 'checklistdek' );

	$html  = '<button type="button" class="button-link checklistdek-site-history-toggle" data-site-id="' . esc_attr( (string) $site_id ) . '" aria-expanded="false" title="' . esc_attr( $label ) . '">';
	$html .= '<span class="dashicons dashicons-arrow-right-alt2 checklistdek-site-history-icon" aria-hidden="true"></span>';
	$html .= '<span class="screen-reader-text">' . esc_html( $label ) . '</span>';
	$html .= '</button> ';

	return $html;
}
endif;
?>
<div class="wrap checklistdek-admin" data-checklistdek-page="sites">
	<h1>
		<?php echo esc_html( get_admin_page_title() ); ?>
		<span class="checklistdek-page-title-actions">
			<button type="button" class="button button-primary page-title-action" id="checklistdek-add-site"><?php esc_html_e( 'Add Site', 'checklistdek' ); ?></button>
			<button type="button" class="button page-title-action" id="checklistdek-connection-tester"><?php esc_html_e( 'Connection Tester', 'checklistdek' ); ?></button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . CHECKLISTDEK_Admin::PAGE_SLUG . '-activity-logs' ) ); ?>" class="button page-title-action" id="checklistdek-activity-logs"><?php esc_html_e( 'Activity Logs', 'checklistdek' ); ?></a>
		</span>
	</h1>

	<div class="checklistdek-card checklistdek-sites-table-card">
		<div class="checklistdek-sites-toolbar-filters" role="search" aria-label="<?php esc_attr_e( 'Tagging and grouping filters', 'checklistdek' ); ?>">
			<label class="checklistdek-filter-label">
				<span class="screen-reader-text"><?php esc_html_e( 'Filter by tag', 'checklistdek' ); ?></span>
				<select id="checklistdek-filter-tag" class="checklistdek-select" aria-label="<?php esc_attr_e( 'Filter by tag', 'checklistdek' ); ?>">
					<option value=""><?php esc_html_e( 'All tags', 'checklistdek' ); ?></option>
				</select>
			</label>
			<label class="checklistdek-filter-label">
				<span class="screen-reader-text"><?php esc_html_e( 'Filter by group', 'checklistdek' ); ?></span>
				<select id="checklistdek-filter-group" class="checklistdek-select" aria-label="<?php esc_attr_e( 'Filter by group', 'checklistdek' ); ?>" data-checklistdek-preloaded="1">
					<option value=""><?php esc_html_e( 'All groups', 'checklistdek' ); ?></option>
					<?php foreach ( $site_group_catalog as $site_group ) : ?>
						<option value="<?php echo esc_attr( $site_group['slug'] ); ?>"><?php echo esc_html( $site_group['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>
		<div class="checklistdek-table-scroll">
		<table class="wp-list-table widefat fixed striped" id="checklistdek-sites-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Site Name', 'checklistdek' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'checklistdek' ); ?></th>
					<th scope="col"><?php esc_html_e( 'WP Ver', 'checklistdek' ); ?></th>
					<th scope="col"><?php esc_html_e( 'PHP Ver', 'checklistdek' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'checklistdek' ); ?></th>
				</tr>
			</thead>
			<tbody data-checklistdek-preloaded="1">
				<?php if ( empty( $sites_list ) ) : ?>
					<tr>
						<td colspan="5" class="checklistdek-muted"><?php esc_html_e( 'No sites registered yet.', 'checklistdek' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $sites_list as $site ) : ?>
						<tr class="checklistdek-site-row" data-site-id="<?php echo esc_attr( (string) ( $site['id'] ?? 0 ) ); ?>">
							<td class="checklistdek-site-name-col">
								<?php echo checklistdek_site_row_name_cell_html( $site ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</td>
							<td class="checklistdek-site-status-cell"><?php echo checklistdek_sites_status_html( $site ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td class="checklistdek-site-wp-version"><?php echo esc_html( $site['wp_version'] ?? '—' ); ?></td>
							<td class="checklistdek-site-php-version"><?php echo esc_html( $site['php_version'] ?? '—' ); ?></td>
							<td class="checklistdek-actions">
								<?php echo checklistdek_sites_actions_html( $site ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</td>
						</tr>
						<tr class="checklistdek-site-runs-row" data-site-id="<?php echo esc_attr( (string) ( $site['id'] ?? 0 ) ); ?>" hidden>
							<td colspan="5">
								<div class="checklistdek-site-runs-panel" data-site-id="<?php echo esc_attr( (string) ( $site['id'] ?? 0 ) ); ?>"></div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		</div>
	</div>

	<div id="checklistdek-site-modal" class="checklistdek-modal" hidden>
		<div class="checklistdek-modal-backdrop"></div>
		<div class="checklistdek-modal-content checklistdek-card">
			<h2 id="checklistdek-site-modal-title"><?php esc_html_e( 'Add Remote Site', 'checklistdek' ); ?></h2>
			<form id="checklistdek-site-form">
				<input type="hidden" id="checklistdek-site-id" value="" />
				<p><label><?php esc_html_e( 'Site Name', 'checklistdek' ); ?><br><input type="text" id="checklistdek-site-name" class="regular-text" required /></label></p>
				<p><label><?php esc_html_e( 'Site URL', 'checklistdek' ); ?><br><input type="url" id="checklistdek-site-url" class="regular-text" placeholder="https://example.com" required /></label></p>
				<p><label><?php esc_html_e( 'Admin Username', 'checklistdek' ); ?><br><input type="text" id="checklistdek-site-username" class="regular-text" required /></label></p>
				<p><label><?php esc_html_e( 'Application Password', 'checklistdek' ); ?><br><input type="password" id="checklistdek-site-password" class="regular-text" autocomplete="new-password" /></label></p>
				<p><label><?php esc_html_e( 'Tags (comma-separated)', 'checklistdek' ); ?><br><input type="text" id="checklistdek-site-tags" class="regular-text" placeholder="E-Commerce, Client ABC" /></label></p>
				<p>
					<label for="checklistdek-site-group"><?php esc_html_e( 'Tag Group', 'checklistdek' ); ?></label><br>
					<select id="checklistdek-site-group" class="checklistdek-select" data-checklistdek-preloaded="1">
						<?php foreach ( $site_group_catalog as $site_group ) : ?>
							<option value="<?php echo esc_attr( $site_group['slug'] ); ?>"><?php echo esc_html( $site_group['label'] ); ?></option>
						<?php endforeach; ?>
						<option value="__add_group__"><?php esc_html_e( 'Add group…', 'checklistdek' ); ?></option>
					</select>
				</p>
				<div id="checklistdek-site-group-add" class="checklistdek-site-group-add" hidden>
					<p class="checklistdek-site-group-add-field">
						<label for="checklistdek-site-group-add-name"><?php esc_html_e( 'New group name', 'checklistdek' ); ?></label><br>
						<input type="text" id="checklistdek-site-group-add-name" class="regular-text" maxlength="80" autocomplete="off" />
					</p>
					<p class="checklistdek-site-group-add-actions">
						<button type="button" class="button button-primary" id="checklistdek-site-group-add-save"><?php esc_html_e( 'Add group', 'checklistdek' ); ?></button>
						<button type="button" class="button" id="checklistdek-site-group-add-cancel"><?php esc_html_e( 'Back', 'checklistdek' ); ?></button>
					</p>
					<div id="checklistdek-site-group-add-notice" class="checklistdek-notice-area" aria-live="polite"></div>
				</div>
				<div id="checklistdek-site-test-result" class="checklistdek-notice-area"></div>
				<?php
				$checklistdek_panel_setup_id_prefix = '';
				$checklistdek_panel_setup_hidden      = true;
				require CHECKLISTDEK_PLUGIN_DIR . 'admin/partials/checklistdek-panel-setup-block.php';
				?>
				<p class="checklistdek-modal-actions">
					<button type="button" class="button" id="checklistdek-site-test"><?php esc_html_e( 'Test Connection', 'checklistdek' ); ?></button>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Site', 'checklistdek' ); ?></button>
					<button type="button" class="button button-link-delete" id="checklistdek-site-delete" hidden><?php esc_html_e( 'Delete Site', 'checklistdek' ); ?></button>
					<button type="button" class="button checklistdek-modal-close"><?php esc_html_e( 'Cancel', 'checklistdek' ); ?></button>
				</p>
			</form>
		</div>
	</div>

	<div id="checklistdek-push-checklist-modal" class="checklistdek-modal" hidden>
		<div class="checklistdek-modal-backdrop"></div>
		<div class="checklistdek-modal-content checklistdek-card">
			<h2><?php esc_html_e( 'Push Checklist', 'checklistdek' ); ?></h2>
			<p class="checklistdek-muted">
				<?php esc_html_e( 'Start a checklist run on', 'checklistdek' ); ?>
				<strong id="checklistdek-push-site-name"></strong>
			</p>
			<p>
				<label for="checklistdek-push-checklist-select"><?php esc_html_e( 'Checklist from master', 'checklistdek' ); ?></label><br>
				<select id="checklistdek-push-checklist-select" class="checklistdek-select"></select>
			</p>
			<p>
				<label>
					<input type="checkbox" id="checklistdek-push-to-client" value="1" checked />
					<?php esc_html_e( 'Show checklist on client admin panel (requires one-time panel setup on the client site)', 'checklistdek' ); ?>
				</label>
			</p>
			<div id="checklistdek-push-checklist-result" class="checklistdek-notice-area"></div>
			<p class="checklistdek-modal-actions">
				<button type="button" class="button button-primary" id="checklistdek-push-checklist-submit"><?php esc_html_e( 'Push & Start Run', 'checklistdek' ); ?></button>
				<button type="button" class="button checklistdek-modal-close"><?php esc_html_e( 'Cancel', 'checklistdek' ); ?></button>
			</p>
		</div>
	</div>

	<div id="checklistdek-site-info-modal" class="checklistdek-modal" hidden>
		<div class="checklistdek-modal-backdrop"></div>
		<div class="checklistdek-modal-content checklistdek-card">
			<h2 id="checklistdek-site-info-modal-title"><?php esc_html_e( 'Site details', 'checklistdek' ); ?></h2>
			<div id="checklistdek-site-info-body" class="checklistdek-site-info-body" aria-live="polite"></div>
			<p class="checklistdek-modal-actions">
				<button type="button" class="button button-primary" id="checklistdek-site-info-edit"><?php esc_html_e( 'Edit site', 'checklistdek' ); ?></button>
				<button type="button" class="button checklistdek-modal-close"><?php esc_html_e( 'Close', 'checklistdek' ); ?></button>
			</p>
		</div>
	</div>

	<div id="checklistdek-add-to-group-modal" class="checklistdek-modal" hidden>
		<div class="checklistdek-modal-backdrop"></div>
		<div class="checklistdek-modal-content checklistdek-card">
			<h2><?php esc_html_e( 'Add to group', 'checklistdek' ); ?></h2>
			<p class="checklistdek-muted">
				<?php esc_html_e( 'Assign a tag and group for', 'checklistdek' ); ?>
				<strong id="checklistdek-add-to-group-site-name"></strong>
			</p>
			<div id="checklistdek-add-to-group-fields">
				<p>
					<label for="checklistdek-add-to-group-tag"><?php esc_html_e( 'Tag', 'checklistdek' ); ?></label><br>
					<input type="text" id="checklistdek-add-to-group-tag" class="regular-text" autocomplete="off" />
				</p>
				<p>
					<label for="checklistdek-add-to-group-group"><?php esc_html_e( 'Tag group', 'checklistdek' ); ?></label><br>
					<select id="checklistdek-add-to-group-group" class="checklistdek-select"></select>
				</p>
			</div>
			<div id="checklistdek-add-to-group-group-add" class="checklistdek-site-group-add" hidden>
				<p class="checklistdek-site-group-add-field">
					<label for="checklistdek-add-to-group-group-add-name"><?php esc_html_e( 'New group name', 'checklistdek' ); ?></label><br>
					<input type="text" id="checklistdek-add-to-group-group-add-name" class="regular-text" maxlength="80" autocomplete="off" />
				</p>
				<p class="checklistdek-site-group-add-actions">
					<button type="button" class="button button-primary" id="checklistdek-add-to-group-group-add-save"><?php esc_html_e( 'Add group', 'checklistdek' ); ?></button>
					<button type="button" class="button" id="checklistdek-add-to-group-group-add-cancel"><?php esc_html_e( 'Back', 'checklistdek' ); ?></button>
				</p>
				<div id="checklistdek-add-to-group-group-add-notice" class="checklistdek-notice-area" aria-live="polite"></div>
			</div>
			<div id="checklistdek-add-to-group-result" class="checklistdek-notice-area"></div>
			<p class="checklistdek-modal-actions">
				<button type="button" class="button button-primary" id="checklistdek-add-to-group-submit"><?php esc_html_e( 'Save', 'checklistdek' ); ?></button>
				<button type="button" class="button checklistdek-modal-close"><?php esc_html_e( 'Cancel', 'checklistdek' ); ?></button>
			</p>
		</div>
	</div>

	<div id="checklistdek-connection-tester-modal" class="checklistdek-modal" hidden>
		<div class="checklistdek-modal-backdrop"></div>
		<div class="checklistdek-modal-content checklistdek-card">
			<h2><?php esc_html_e( 'Connection Tester', 'checklistdek' ); ?></h2>
			<p class="checklistdek-muted"><?php esc_html_e( 'Test credentials against a remote site without saving.', 'checklistdek' ); ?></p>
			<form id="checklistdek-connection-tester-form">
				<p><label><?php esc_html_e( 'Site URL', 'checklistdek' ); ?><br><input type="url" id="checklistdek-tester-url" class="regular-text" placeholder="https://example.com" required /></label></p>
				<p><label><?php esc_html_e( 'Admin Username', 'checklistdek' ); ?><br><input type="text" id="checklistdek-tester-username" class="regular-text" required /></label></p>
				<p><label><?php esc_html_e( 'Application Password', 'checklistdek' ); ?><br><input type="password" id="checklistdek-tester-password" class="regular-text" autocomplete="new-password" required /></label></p>
				<div id="checklistdek-tester-result" class="checklistdek-notice-area"></div>
				<?php
				$checklistdek_panel_setup_id_prefix   = 'tester';
				$checklistdek_panel_setup_hidden      = true;
				$checklistdek_panel_setup_show_retry  = false;
				require CHECKLISTDEK_PLUGIN_DIR . 'admin/partials/checklistdek-panel-setup-block.php';
				?>
				<p class="checklistdek-modal-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Test Connection', 'checklistdek' ); ?></button>
					<button type="button" class="button checklistdek-modal-close"><?php esc_html_e( 'Close', 'checklistdek' ); ?></button>
				</p>
			</form>
		</div>
	</div>
</div>
