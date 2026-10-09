<?php
/**
 * Sites admin page.
 *
 * @package LaunchDek
 *
 * @var array  $settings   Plugin settings.
 * @var string $page       Page identifier.
 * @var array  $sites_list Cached sites list for the default table view.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Admin view partial; variables are template-scoped.

$sites_list           = isset( $sites_list ) && is_array( $sites_list ) ? $sites_list : LAUNCHDEK_Dashboard_Cache::get_sites_list();
$site_group_catalog   = LAUNCHDEK_Settings::get_site_group_catalog();

/**
 * Render translated health label for the sites table.
 *
 * @param string $status Health status slug.
 * @return string
 */
if ( ! function_exists( 'launchdek_sites_health_label' ) ) :
function launchdek_sites_health_label( $status ) {
	if ( 'healthy' === $status ) {
		return __( 'OK', 'launchdek' );
	}
	if ( 'unhealthy' === $status ) {
		return __( 'Fail', 'launchdek' );
	}

	return __( 'Unknown', 'launchdek' );
}
endif;

/**
 * Render combined connection and health status markup for a site row.
 *
 * @param array $site Site payload.
 * @return string
 */
if ( ! function_exists( 'launchdek_sites_status_html' ) ) :
function launchdek_sites_status_html( $site ) {
	$status     = sanitize_key( $site['health_status'] ?? 'unknown' );
	$last_error = isset( $site['last_error'] ) ? wp_strip_all_tags( (string) $site['last_error'] ) : '';

	if ( 'healthy' === $status ) {
		$label = __( 'Connection OK', 'launchdek' );
	} elseif ( 'unhealthy' === $status ) {
		$label = __( 'Connection not working', 'launchdek' );
		if ( '' !== $last_error ) {
			$label .= ': ' . $last_error;
		}
	} else {
		$label = __( 'Unknown', 'launchdek' );
	}

	$html  = '<div class="launchdek-site-status" title="' . esc_attr( $label ) . '">';
	$html .= '<span class="launchdek-site-connection" aria-hidden="true">';
	$html .= '<span class="launchdek-connection-dot ' . esc_attr( $status ) . '"></span>';
	if ( 'unhealthy' === $status ) {
		$html .= '<span class="dashicons dashicons-warning launchdek-connection-warning"></span>';
	}
	$html .= '</span>';
	$html .= '<span class="launchdek-badge launchdek-site-health-badge ' . esc_attr( $status ) . '">' . esc_html( launchdek_sites_health_label( $status ) ) . '</span>';
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
if ( ! function_exists( 'launchdek_site_row_display_name' ) ) :
function launchdek_site_row_display_name( $site ) {
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
if ( ! function_exists( 'launchdek_site_url_display_label' ) ) :
function launchdek_site_url_display_label( $url ) {
	return trim( (string) $url );
}
endif;

/**
 * Render the site name / URL cell for the sites table.
 *
 * @param array $site Site payload.
 * @return string
 */
if ( ! function_exists( 'launchdek_site_row_name_cell_html' ) ) :
function launchdek_site_row_name_cell_html( $site ) {
	$site_id = absint( $site['id'] ?? 0 );
	$name    = trim( (string) ( $site['name'] ?? '' ) );
	$url     = trim( (string) ( $site['url'] ?? '' ) );

	$html  = launchdek_sites_history_toggle_html( $site_id );
	$html .= '<div class="launchdek-site-name-cell">';

	$show_distinct_name = $name !== '' && $url !== '' && 0 !== strcasecmp( $name, $url );

	if ( $show_distinct_name ) {
		$html .= '<span class="launchdek-site-name-label">' . esc_html( $name ) . '</span>';
		if ( $url !== '' ) {
			$url_label = launchdek_site_url_display_label( $url );
			$html     .= '<a class="launchdek-site-url-link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener" title="' . esc_attr( $url ) . '">' . esc_html( $url_label ) . '</a>';
		}
	} elseif ( $url !== '' ) {
		$url_label = launchdek_site_url_display_label( $url );
		$link_text = $url_label !== '' ? $url_label : launchdek_site_row_display_name( $site );
		$html     .= '<a class="launchdek-site-url-link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener" title="' . esc_attr( $url ) . '">' . esc_html( $link_text ) . '</a>';
	} else {
		$html .= '<span class="launchdek-site-name-label">' . esc_html( $name !== '' ? $name : '—' ) . '</span>';
	}

	if ( ! empty( $site['client_agent'] ) ) {
		$html .= '<span class="launchdek-badge healthy launchdek-client-agent-badge">' . esc_html__( 'Client panel', 'launchdek' ) . '</span>';
	}

	$html .= '</div>';

	return $html;
}
endif;

/**
 * Render site row actions dropdown markup.
 *
 * @param array $site Site payload.
 * @return string
 */
if ( ! function_exists( 'launchdek_sites_actions_html' ) ) :
function launchdek_sites_actions_html( $site ) {
	$site_id            = absint( $site['id'] ?? 0 );
	$health_status      = sanitize_key( $site['health_status'] ?? 'unknown' );
	$connection_blocked = 'unhealthy' === $health_status;
	$menu_label         = __( 'More site actions', 'launchdek' );

	$html  = '<div class="launchdek-site-actions-dropdown">';
	$html .= '<div class="launchdek-site-actions-split">';
	$html .= '<button type="button" class="button button-small launchdek-edit-site" data-id="' . esc_attr( (string) $site_id ) . '">' . esc_html__( 'Edit', 'launchdek' ) . '</button>';
	$html .= '<button type="button" class="button button-small launchdek-site-actions-toggle" data-id="' . esc_attr( (string) $site_id ) . '" aria-haspopup="true" aria-expanded="false" aria-label="' . esc_attr( $menu_label ) . '">';
	$html .= '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>';
	$html .= '</button>';
	$html .= '</div>';
	$html .= '<div class="launchdek-site-actions-menu" role="menu" hidden>';
	$html .= '<button type="button" role="menuitem" class="launchdek-push-checklist" data-id="' . esc_attr( (string) $site_id ) . '" data-name="' . esc_attr( $site['name'] ?? '' ) . '"' . disabled( $connection_blocked, true, false ) . '>' . esc_html__( 'Push Checklist', 'launchdek' ) . '</button>';
	$html .= '<button type="button" role="menuitem" class="launchdek-test-site" data-id="' . esc_attr( (string) $site_id ) . '">' . esc_html__( 'Test', 'launchdek' ) . '</button>';
	$html .= '<button type="button" role="menuitem" class="launchdek-site-more-info" data-id="' . esc_attr( (string) $site_id ) . '">' . esc_html__( 'More info', 'launchdek' ) . '</button>';
	$html .= '<button type="button" role="menuitem" class="launchdek-add-to-group" data-id="' . esc_attr( (string) $site_id ) . '" data-name="' . esc_attr( $site['name'] ?? '' ) . '">' . esc_html__( 'Add to group', 'launchdek' ) . '</button>';
	$activity_url = admin_url( 'admin.php?page=' . LAUNCHDEK_Admin::PAGE_SLUG . '-activity-logs&site_id=' . $site_id );
	$html .= '<a href="' . esc_url( $activity_url ) . '" role="menuitem" class="launchdek-site-activity-log">' . esc_html__( 'Activity Log', 'launchdek' ) . '</a>';
	$html .= '<button type="button" role="menuitem" class="launchdek-delete-site" data-id="' . esc_attr( (string) $site_id ) . '">' . esc_html__( 'Delete', 'launchdek' ) . '</button>';
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
if ( ! function_exists( 'launchdek_sites_history_toggle_html' ) ) :
function launchdek_sites_history_toggle_html( $site_id ) {
	$site_id = absint( $site_id );
	$label   = __( 'Show checklist history', 'launchdek' );

	$html  = '<button type="button" class="button-link launchdek-site-history-toggle" data-site-id="' . esc_attr( (string) $site_id ) . '" aria-expanded="false" title="' . esc_attr( $label ) . '">';
	$html .= '<span class="dashicons dashicons-arrow-right-alt2 launchdek-site-history-icon" aria-hidden="true"></span>';
	$html .= '<span class="screen-reader-text">' . esc_html( $label ) . '</span>';
	$html .= '</button> ';

	return $html;
}
endif;
?>
<div class="wrap launchdek-admin" data-launchdek-page="sites">
	<h1>
		<?php echo esc_html( get_admin_page_title() ); ?>
		<span class="launchdek-page-title-actions">
			<button type="button" class="button button-primary page-title-action" id="launchdek-add-site"><?php esc_html_e( 'Add Site', 'launchdek' ); ?></button>
			<button type="button" class="button page-title-action" id="launchdek-connection-tester"><?php esc_html_e( 'Connection Tester', 'launchdek' ); ?></button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . LAUNCHDEK_Admin::PAGE_SLUG . '-activity-logs' ) ); ?>" class="button page-title-action" id="launchdek-activity-logs"><?php esc_html_e( 'Activity Logs', 'launchdek' ); ?></a>
		</span>
	</h1>

	<div class="launchdek-card launchdek-sites-table-card">
		<div class="launchdek-sites-toolbar-filters" role="search" aria-label="<?php esc_attr_e( 'Tagging and grouping filters', 'launchdek' ); ?>">
			<label class="launchdek-filter-label">
				<span class="screen-reader-text"><?php esc_html_e( 'Filter by tag', 'launchdek' ); ?></span>
				<select id="launchdek-filter-tag" class="launchdek-select" aria-label="<?php esc_attr_e( 'Filter by tag', 'launchdek' ); ?>">
					<option value=""><?php esc_html_e( 'All tags', 'launchdek' ); ?></option>
				</select>
			</label>
			<label class="launchdek-filter-label">
				<span class="screen-reader-text"><?php esc_html_e( 'Filter by group', 'launchdek' ); ?></span>
				<select id="launchdek-filter-group" class="launchdek-select" aria-label="<?php esc_attr_e( 'Filter by group', 'launchdek' ); ?>" data-launchdek-preloaded="1">
					<option value=""><?php esc_html_e( 'All groups', 'launchdek' ); ?></option>
					<?php foreach ( $site_group_catalog as $site_group ) : ?>
						<option value="<?php echo esc_attr( $site_group['slug'] ); ?>"><?php echo esc_html( $site_group['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>
		<div class="launchdek-table-scroll">
		<table class="wp-list-table widefat fixed striped" id="launchdek-sites-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Site Name', 'launchdek' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', 'launchdek' ); ?></th>
					<th scope="col"><?php esc_html_e( 'WP Ver', 'launchdek' ); ?></th>
					<th scope="col"><?php esc_html_e( 'PHP Ver', 'launchdek' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', 'launchdek' ); ?></th>
				</tr>
			</thead>
			<tbody data-launchdek-preloaded="1">
				<?php if ( empty( $sites_list ) ) : ?>
					<tr>
						<td colspan="5" class="launchdek-muted"><?php esc_html_e( 'No sites registered yet.', 'launchdek' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $sites_list as $site ) : ?>
						<tr class="launchdek-site-row" data-site-id="<?php echo esc_attr( (string) ( $site['id'] ?? 0 ) ); ?>">
							<td class="launchdek-site-name-col">
								<?php echo launchdek_site_row_name_cell_html( $site ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</td>
							<td class="launchdek-site-status-cell"><?php echo launchdek_sites_status_html( $site ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td class="launchdek-site-wp-version"><?php echo esc_html( $site['wp_version'] ?? '—' ); ?></td>
							<td class="launchdek-site-php-version"><?php echo esc_html( $site['php_version'] ?? '—' ); ?></td>
							<td class="launchdek-actions">
								<?php echo launchdek_sites_actions_html( $site ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</td>
						</tr>
						<tr class="launchdek-site-runs-row" data-site-id="<?php echo esc_attr( (string) ( $site['id'] ?? 0 ) ); ?>" hidden>
							<td colspan="5">
								<div class="launchdek-site-runs-panel" data-site-id="<?php echo esc_attr( (string) ( $site['id'] ?? 0 ) ); ?>"></div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		</div>
	</div>

	<div id="launchdek-site-modal" class="launchdek-modal" hidden>
		<div class="launchdek-modal-backdrop"></div>
		<div class="launchdek-modal-content launchdek-card">
			<h2 id="launchdek-site-modal-title"><?php esc_html_e( 'Add Remote Site', 'launchdek' ); ?></h2>
			<form id="launchdek-site-form">
				<input type="hidden" id="launchdek-site-id" value="" />
				<p><label><?php esc_html_e( 'Site Name', 'launchdek' ); ?><br><input type="text" id="launchdek-site-name" class="regular-text" required /></label></p>
				<p><label><?php esc_html_e( 'Site URL', 'launchdek' ); ?><br><input type="url" id="launchdek-site-url" class="regular-text" placeholder="https://example.com" required /></label></p>
				<p><label><?php esc_html_e( 'Admin Username', 'launchdek' ); ?><br><input type="text" id="launchdek-site-username" class="regular-text" required /></label></p>
				<p><label><?php esc_html_e( 'Application Password', 'launchdek' ); ?><br><input type="password" id="launchdek-site-password" class="regular-text" autocomplete="new-password" /></label></p>
				<p><label><?php esc_html_e( 'Tags (comma-separated)', 'launchdek' ); ?><br><input type="text" id="launchdek-site-tags" class="regular-text" placeholder="E-Commerce, Client ABC" /></label></p>
				<p>
					<label for="launchdek-site-group"><?php esc_html_e( 'Tag Group', 'launchdek' ); ?></label><br>
					<select id="launchdek-site-group" class="launchdek-select" data-launchdek-preloaded="1">
						<?php foreach ( $site_group_catalog as $site_group ) : ?>
							<option value="<?php echo esc_attr( $site_group['slug'] ); ?>"><?php echo esc_html( $site_group['label'] ); ?></option>
						<?php endforeach; ?>
						<option value="__add_group__"><?php esc_html_e( 'Add group…', 'launchdek' ); ?></option>
					</select>
				</p>
				<div id="launchdek-site-group-add" class="launchdek-site-group-add" hidden>
					<p class="launchdek-site-group-add-field">
						<label for="launchdek-site-group-add-name"><?php esc_html_e( 'New group name', 'launchdek' ); ?></label><br>
						<input type="text" id="launchdek-site-group-add-name" class="regular-text" maxlength="80" autocomplete="off" />
					</p>
					<p class="launchdek-site-group-add-actions">
						<button type="button" class="button button-primary" id="launchdek-site-group-add-save"><?php esc_html_e( 'Add group', 'launchdek' ); ?></button>
						<button type="button" class="button" id="launchdek-site-group-add-cancel"><?php esc_html_e( 'Back', 'launchdek' ); ?></button>
					</p>
					<div id="launchdek-site-group-add-notice" class="launchdek-notice-area" aria-live="polite"></div>
				</div>
				<div id="launchdek-site-test-result" class="launchdek-notice-area"></div>
				<?php
				$launchdek_panel_setup_id_prefix = '';
				$launchdek_panel_setup_hidden      = true;
				require LAUNCHDEK_PLUGIN_DIR . 'admin/partials/launchdek-panel-setup-block.php';
				?>
				<p class="launchdek-modal-actions">
					<button type="button" class="button" id="launchdek-site-test"><?php esc_html_e( 'Test Connection', 'launchdek' ); ?></button>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Site', 'launchdek' ); ?></button>
					<button type="button" class="button button-link-delete" id="launchdek-site-delete" hidden><?php esc_html_e( 'Delete Site', 'launchdek' ); ?></button>
					<button type="button" class="button launchdek-modal-close"><?php esc_html_e( 'Cancel', 'launchdek' ); ?></button>
				</p>
			</form>
		</div>
	</div>

	<div id="launchdek-push-checklist-modal" class="launchdek-modal" hidden>
		<div class="launchdek-modal-backdrop"></div>
		<div class="launchdek-modal-content launchdek-card">
			<h2><?php esc_html_e( 'Push Checklist', 'launchdek' ); ?></h2>
			<p class="launchdek-muted">
				<?php esc_html_e( 'Start a checklist run on', 'launchdek' ); ?>
				<strong id="launchdek-push-site-name"></strong>
			</p>
			<p>
				<label for="launchdek-push-checklist-select"><?php esc_html_e( 'Checklist from master', 'launchdek' ); ?></label><br>
				<select id="launchdek-push-checklist-select" class="launchdek-select"></select>
			</p>
			<p>
				<label>
					<input type="checkbox" id="launchdek-push-to-client" value="1" checked />
					<?php esc_html_e( 'Show checklist on client admin panel (requires one-time panel setup on the client site)', 'launchdek' ); ?>
				</label>
			</p>
			<div id="launchdek-push-checklist-result" class="launchdek-notice-area"></div>
			<p class="launchdek-modal-actions">
				<button type="button" class="button button-primary" id="launchdek-push-checklist-submit"><?php esc_html_e( 'Push & Start Run', 'launchdek' ); ?></button>
				<button type="button" class="button launchdek-modal-close"><?php esc_html_e( 'Cancel', 'launchdek' ); ?></button>
			</p>
		</div>
	</div>

	<div id="launchdek-site-info-modal" class="launchdek-modal" hidden>
		<div class="launchdek-modal-backdrop"></div>
		<div class="launchdek-modal-content launchdek-card">
			<h2 id="launchdek-site-info-modal-title"><?php esc_html_e( 'Site details', 'launchdek' ); ?></h2>
			<div id="launchdek-site-info-body" class="launchdek-site-info-body" aria-live="polite"></div>
			<p class="launchdek-modal-actions">
				<button type="button" class="button button-primary" id="launchdek-site-info-edit"><?php esc_html_e( 'Edit site', 'launchdek' ); ?></button>
				<button type="button" class="button launchdek-modal-close"><?php esc_html_e( 'Close', 'launchdek' ); ?></button>
			</p>
		</div>
	</div>

	<div id="launchdek-add-to-group-modal" class="launchdek-modal" hidden>
		<div class="launchdek-modal-backdrop"></div>
		<div class="launchdek-modal-content launchdek-card">
			<h2><?php esc_html_e( 'Add to group', 'launchdek' ); ?></h2>
			<p class="launchdek-muted">
				<?php esc_html_e( 'Assign a tag and group for', 'launchdek' ); ?>
				<strong id="launchdek-add-to-group-site-name"></strong>
			</p>
			<div id="launchdek-add-to-group-fields">
				<p>
					<label for="launchdek-add-to-group-tag"><?php esc_html_e( 'Tag', 'launchdek' ); ?></label><br>
					<input type="text" id="launchdek-add-to-group-tag" class="regular-text" autocomplete="off" />
				</p>
				<p>
					<label for="launchdek-add-to-group-group"><?php esc_html_e( 'Tag group', 'launchdek' ); ?></label><br>
					<select id="launchdek-add-to-group-group" class="launchdek-select"></select>
				</p>
			</div>
			<div id="launchdek-add-to-group-group-add" class="launchdek-site-group-add" hidden>
				<p class="launchdek-site-group-add-field">
					<label for="launchdek-add-to-group-group-add-name"><?php esc_html_e( 'New group name', 'launchdek' ); ?></label><br>
					<input type="text" id="launchdek-add-to-group-group-add-name" class="regular-text" maxlength="80" autocomplete="off" />
				</p>
				<p class="launchdek-site-group-add-actions">
					<button type="button" class="button button-primary" id="launchdek-add-to-group-group-add-save"><?php esc_html_e( 'Add group', 'launchdek' ); ?></button>
					<button type="button" class="button" id="launchdek-add-to-group-group-add-cancel"><?php esc_html_e( 'Back', 'launchdek' ); ?></button>
				</p>
				<div id="launchdek-add-to-group-group-add-notice" class="launchdek-notice-area" aria-live="polite"></div>
			</div>
			<div id="launchdek-add-to-group-result" class="launchdek-notice-area"></div>
			<p class="launchdek-modal-actions">
				<button type="button" class="button button-primary" id="launchdek-add-to-group-submit"><?php esc_html_e( 'Save', 'launchdek' ); ?></button>
				<button type="button" class="button launchdek-modal-close"><?php esc_html_e( 'Cancel', 'launchdek' ); ?></button>
			</p>
		</div>
	</div>

	<div id="launchdek-connection-tester-modal" class="launchdek-modal" hidden>
		<div class="launchdek-modal-backdrop"></div>
		<div class="launchdek-modal-content launchdek-card">
			<h2><?php esc_html_e( 'Connection Tester', 'launchdek' ); ?></h2>
			<p class="launchdek-muted"><?php esc_html_e( 'Test credentials against a remote site without saving.', 'launchdek' ); ?></p>
			<form id="launchdek-connection-tester-form">
				<p><label><?php esc_html_e( 'Site URL', 'launchdek' ); ?><br><input type="url" id="launchdek-tester-url" class="regular-text" placeholder="https://example.com" required /></label></p>
				<p><label><?php esc_html_e( 'Admin Username', 'launchdek' ); ?><br><input type="text" id="launchdek-tester-username" class="regular-text" required /></label></p>
				<p><label><?php esc_html_e( 'Application Password', 'launchdek' ); ?><br><input type="password" id="launchdek-tester-password" class="regular-text" autocomplete="new-password" required /></label></p>
				<div id="launchdek-tester-result" class="launchdek-notice-area"></div>
				<?php
				$launchdek_panel_setup_id_prefix   = 'tester';
				$launchdek_panel_setup_hidden      = true;
				$launchdek_panel_setup_show_retry  = false;
				require LAUNCHDEK_PLUGIN_DIR . 'admin/partials/launchdek-panel-setup-block.php';
				?>
				<p class="launchdek-modal-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Test Connection', 'launchdek' ); ?></button>
					<button type="button" class="button launchdek-modal-close"><?php esc_html_e( 'Close', 'launchdek' ); ?></button>
				</p>
			</form>
		</div>
	</div>
</div>
