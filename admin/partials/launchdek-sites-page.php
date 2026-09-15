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

$sites_list = isset( $sites_list ) && is_array( $sites_list ) ? $sites_list : LAUNCHDEK_Dashboard_Cache::get_sites_list();

/**
 * Render translated health label for the sites table.
 *
 * @param string $status Health status slug.
 * @return string
 */
if ( ! function_exists( 'launchdek_sites_health_label' ) ) :
function launchdek_sites_health_label( $status ) {
	if ( 'healthy' === $status ) {
		return __( 'OK', LAUNCHDEK_TEXT_DOMAIN );
	}
	if ( 'unhealthy' === $status ) {
		return __( 'Fail', LAUNCHDEK_TEXT_DOMAIN );
	}

	return __( 'Unknown', LAUNCHDEK_TEXT_DOMAIN );
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
	$last_error = isset( $site['last_error'] ) ? (string) $site['last_error'] : '';

	if ( 'healthy' === $status ) {
		$label = __( 'Connection OK', LAUNCHDEK_TEXT_DOMAIN );
	} elseif ( 'unhealthy' === $status ) {
		$label = __( 'Connection not working', LAUNCHDEK_TEXT_DOMAIN );
		if ( '' !== $last_error ) {
			$label .= ': ' . $last_error;
		}
	} else {
		$label = __( 'Unknown', LAUNCHDEK_TEXT_DOMAIN );
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
	$menu_label         = __( 'More site actions', LAUNCHDEK_TEXT_DOMAIN );

	$html  = '<div class="launchdek-site-actions-dropdown">';
	$html .= '<div class="launchdek-site-actions-split">';
	$html .= '<button type="button" class="button button-small launchdek-edit-site" data-id="' . esc_attr( (string) $site_id ) . '">' . esc_html__( 'Edit', LAUNCHDEK_TEXT_DOMAIN ) . '</button>';
	$html .= '<button type="button" class="button button-small launchdek-site-actions-toggle" data-id="' . esc_attr( (string) $site_id ) . '" aria-haspopup="true" aria-expanded="false" aria-label="' . esc_attr( $menu_label ) . '">';
	$html .= '<span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>';
	$html .= '</button>';
	$html .= '</div>';
	$html .= '<div class="launchdek-site-actions-menu" role="menu" hidden>';
	$html .= '<button type="button" role="menuitem" class="launchdek-push-checklist" data-id="' . esc_attr( (string) $site_id ) . '" data-name="' . esc_attr( $site['name'] ?? '' ) . '"' . disabled( $connection_blocked, true, false ) . '>' . esc_html__( 'Push Checklist', LAUNCHDEK_TEXT_DOMAIN ) . '</button>';
	$html .= '<button type="button" role="menuitem" class="launchdek-test-site" data-id="' . esc_attr( (string) $site_id ) . '">' . esc_html__( 'Test', LAUNCHDEK_TEXT_DOMAIN ) . '</button>';
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
	$label   = __( 'Show checklist history', LAUNCHDEK_TEXT_DOMAIN );

	$html  = '<button type="button" class="button-link launchdek-site-history-toggle" data-site-id="' . esc_attr( (string) $site_id ) . '" aria-expanded="false" title="' . esc_attr( $label ) . '">';
	$html .= '<span class="dashicons dashicons-arrow-right-alt2 launchdek-site-history-icon" aria-hidden="true"></span>';
	$html .= '<span class="screen-reader-text">' . esc_html( $label ) . '</span>';
	$html .= '</button> ';

	return $html;
}
endif;
?>
<div class="wrap launchdek-admin" data-launchdek-page="sites">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="launchdek-sites-toolbar launchdek-card">
		<div class="launchdek-sites-toolbar-actions">
			<button type="button" class="button button-primary" id="launchdek-add-site"><?php esc_html_e( 'Add Site', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
			<button type="button" class="button" id="launchdek-connection-tester"><?php esc_html_e( 'Connection Tester', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . LAUNCHDEK_Admin::PAGE_SLUG . '-activity-logs' ) ); ?>" class="button" id="launchdek-activity-logs"><?php esc_html_e( 'Activity Logs', LAUNCHDEK_TEXT_DOMAIN ); ?></a>
		</div>
		<div class="launchdek-sites-toolbar-filters" role="search" aria-label="<?php esc_attr_e( 'Tagging and grouping filters', LAUNCHDEK_TEXT_DOMAIN ); ?>">
			<label class="launchdek-filter-label">
				<span class="screen-reader-text"><?php esc_html_e( 'Filter by tag', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
				<select id="launchdek-filter-tag" class="launchdek-select" aria-label="<?php esc_attr_e( 'Filter by tag', LAUNCHDEK_TEXT_DOMAIN ); ?>">
					<option value=""><?php esc_html_e( 'All tags', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
				</select>
			</label>
			<label class="launchdek-filter-label">
				<span class="screen-reader-text"><?php esc_html_e( 'Filter by group', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
				<select id="launchdek-filter-group" class="launchdek-select" aria-label="<?php esc_attr_e( 'Filter by group', LAUNCHDEK_TEXT_DOMAIN ); ?>">
					<option value=""><?php esc_html_e( 'All groups', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
					<option value="client"><?php esc_html_e( 'Client', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
					<option value="project"><?php esc_html_e( 'Project Type', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
					<option value="tier"><?php esc_html_e( 'Hosting Tier', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
					<option value="general"><?php esc_html_e( 'General', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
				</select>
			</label>
		</div>
	</div>

	<div class="launchdek-card">
		<table class="wp-list-table widefat fixed striped" id="launchdek-sites-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Site Name', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
					<th scope="col"><?php esc_html_e( 'URL', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
					<th scope="col"><?php esc_html_e( 'Status', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
					<th scope="col"><?php esc_html_e( 'WP Ver', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
					<th scope="col"><?php esc_html_e( 'PHP Ver', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
				</tr>
			</thead>
			<tbody data-launchdek-preloaded="1">
				<?php if ( empty( $sites_list ) ) : ?>
					<tr>
						<td colspan="6" class="launchdek-muted"><?php esc_html_e( 'No sites registered yet.', LAUNCHDEK_TEXT_DOMAIN ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $sites_list as $site ) : ?>
						<tr class="launchdek-site-row" data-site-id="<?php echo esc_attr( (string) ( $site['id'] ?? 0 ) ); ?>">
							<td>
								<?php echo launchdek_sites_history_toggle_html( (int) ( $site['id'] ?? 0 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php echo esc_html( $site['name'] ?? '' ); ?>
								<?php if ( ! empty( $site['client_agent'] ) ) : ?>
									<span class="launchdek-badge healthy launchdek-client-agent-badge"><?php esc_html_e( 'Client panel', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
								<?php endif; ?>
							</td>
							<td>
								<a href="<?php echo esc_url( $site['url'] ?? '' ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $site['url'] ?? '' ); ?></a>
							</td>
							<td class="launchdek-site-status-cell"><?php echo launchdek_sites_status_html( $site ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td class="launchdek-site-wp-version"><?php echo esc_html( $site['wp_version'] ?? '—' ); ?></td>
							<td class="launchdek-site-php-version"><?php echo esc_html( $site['php_version'] ?? '—' ); ?></td>
							<td class="launchdek-actions">
								<?php echo launchdek_sites_actions_html( $site ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</td>
						</tr>
						<tr class="launchdek-site-runs-row" data-site-id="<?php echo esc_attr( (string) ( $site['id'] ?? 0 ) ); ?>" hidden>
							<td colspan="6">
								<div class="launchdek-site-runs-panel" data-site-id="<?php echo esc_attr( (string) ( $site['id'] ?? 0 ) ); ?>"></div>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<div id="launchdek-site-modal" class="launchdek-modal" hidden>
		<div class="launchdek-modal-backdrop"></div>
		<div class="launchdek-modal-content launchdek-card">
			<h2 id="launchdek-site-modal-title"><?php esc_html_e( 'Add Remote Site', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<form id="launchdek-site-form">
				<input type="hidden" id="launchdek-site-id" value="" />
				<p><label><?php esc_html_e( 'Site Name', LAUNCHDEK_TEXT_DOMAIN ); ?><br><input type="text" id="launchdek-site-name" class="regular-text" required /></label></p>
				<p><label><?php esc_html_e( 'Site URL', LAUNCHDEK_TEXT_DOMAIN ); ?><br><input type="url" id="launchdek-site-url" class="regular-text" placeholder="https://example.com" required /></label></p>
				<p><label><?php esc_html_e( 'Admin Username', LAUNCHDEK_TEXT_DOMAIN ); ?><br><input type="text" id="launchdek-site-username" class="regular-text" required /></label></p>
				<p><label><?php esc_html_e( 'Application Password', LAUNCHDEK_TEXT_DOMAIN ); ?><br><input type="password" id="launchdek-site-password" class="regular-text" autocomplete="new-password" /></label></p>
				<p><label><?php esc_html_e( 'Tags (comma-separated)', LAUNCHDEK_TEXT_DOMAIN ); ?><br><input type="text" id="launchdek-site-tags" class="regular-text" placeholder="E-Commerce, Client ABC" /></label></p>
				<p><label><?php esc_html_e( 'Tag Group', LAUNCHDEK_TEXT_DOMAIN ); ?><br>
					<select id="launchdek-site-group" class="launchdek-select">
						<option value="general"><?php esc_html_e( 'General', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
						<option value="client"><?php esc_html_e( 'Client', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
						<option value="project"><?php esc_html_e( 'Project Type', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
						<option value="tier"><?php esc_html_e( 'Hosting Tier', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
					</select>
				</label></p>
				<div id="launchdek-site-test-result" class="launchdek-notice-area"></div>
				<div id="launchdek-panel-setup" class="launchdek-panel-setup" hidden>
					<h3 class="launchdek-panel-setup-title"><?php esc_html_e( 'Client checklist panel setup', LAUNCHDEK_TEXT_DOMAIN ); ?></h3>
					<p class="launchdek-muted"><?php esc_html_e( 'Optional one-time setup so clients see the checklist in their wp-admin. Core automation works without this step.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
					<ol class="launchdek-panel-setup-steps">
						<li><?php esc_html_e( 'Download the bootstrap file below.', LAUNCHDEK_TEXT_DOMAIN ); ?></li>
						<li><?php esc_html_e( 'Upload it to wp-content/mu-plugins/ on the client site (create the mu-plugins folder if needed).', LAUNCHDEK_TEXT_DOMAIN ); ?></li>
						<li><?php esc_html_e( 'Click Retry panel install — LaunchDek will deploy the full panel and verify the connection.', LAUNCHDEK_TEXT_DOMAIN ); ?></li>
					</ol>
					<p class="launchdek-panel-setup-actions">
						<button type="button" class="button" id="launchdek-download-panel-bootstrap"><?php esc_html_e( 'Download launchdek-client.php', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
						<button type="button" class="button button-secondary" id="launchdek-retry-panel-install"><?php esc_html_e( 'Retry panel install', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
					</p>
					<p class="launchdek-muted launchdek-panel-setup-path"><code>wp-content/mu-plugins/launchdek-client.php</code></p>
					<div id="launchdek-panel-setup-result" class="launchdek-notice-area"></div>
				</div>
				<p class="launchdek-modal-actions">
					<button type="button" class="button" id="launchdek-site-test"><?php esc_html_e( 'Test Connection', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Site', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
					<button type="button" class="button button-link-delete" id="launchdek-site-delete" hidden><?php esc_html_e( 'Delete Site', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
					<button type="button" class="button launchdek-modal-close"><?php esc_html_e( 'Cancel', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				</p>
			</form>
		</div>
	</div>

	<div id="launchdek-push-checklist-modal" class="launchdek-modal" hidden>
		<div class="launchdek-modal-backdrop"></div>
		<div class="launchdek-modal-content launchdek-card">
			<h2><?php esc_html_e( 'Push Checklist', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<p class="launchdek-muted">
				<?php esc_html_e( 'Start a checklist run on', LAUNCHDEK_TEXT_DOMAIN ); ?>
				<strong id="launchdek-push-site-name"></strong>
			</p>
			<p>
				<label for="launchdek-push-checklist-select"><?php esc_html_e( 'Checklist from master', LAUNCHDEK_TEXT_DOMAIN ); ?></label><br>
				<select id="launchdek-push-checklist-select" class="launchdek-select"></select>
			</p>
			<p>
				<label>
					<input type="checkbox" id="launchdek-push-to-client" value="1" checked />
					<?php esc_html_e( 'Show checklist on client admin panel (requires one-time panel setup on the client site)', LAUNCHDEK_TEXT_DOMAIN ); ?>
				</label>
			</p>
			<div id="launchdek-push-checklist-result" class="launchdek-notice-area"></div>
			<p class="launchdek-modal-actions">
				<button type="button" class="button button-primary" id="launchdek-push-checklist-submit"><?php esc_html_e( 'Push & Start Run', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				<button type="button" class="button launchdek-modal-close"><?php esc_html_e( 'Cancel', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
			</p>
		</div>
	</div>

	<div id="launchdek-connection-tester-modal" class="launchdek-modal" hidden>
		<div class="launchdek-modal-backdrop"></div>
		<div class="launchdek-modal-content launchdek-card">
			<h2><?php esc_html_e( 'Connection Tester', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<p class="launchdek-muted"><?php esc_html_e( 'Test credentials against a remote site without saving.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
			<form id="launchdek-connection-tester-form">
				<p><label><?php esc_html_e( 'Site URL', LAUNCHDEK_TEXT_DOMAIN ); ?><br><input type="url" id="launchdek-tester-url" class="regular-text" placeholder="https://example.com" required /></label></p>
				<p><label><?php esc_html_e( 'Admin Username', LAUNCHDEK_TEXT_DOMAIN ); ?><br><input type="text" id="launchdek-tester-username" class="regular-text" required /></label></p>
				<p><label><?php esc_html_e( 'Application Password', LAUNCHDEK_TEXT_DOMAIN ); ?><br><input type="password" id="launchdek-tester-password" class="regular-text" autocomplete="new-password" required /></label></p>
				<div id="launchdek-tester-result" class="launchdek-notice-area"></div>
				<p class="launchdek-modal-actions">
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Test Connection', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
					<button type="button" class="button launchdek-modal-close"><?php esc_html_e( 'Close', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				</p>
			</form>
		</div>
	</div>
</div>
