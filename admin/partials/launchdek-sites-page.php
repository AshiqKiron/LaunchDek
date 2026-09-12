<?php
/**
 * Sites admin page.
 *
 * @package LaunchDek
 *
 * @var array  $settings Plugin settings.
 * @var string $page     Page identifier.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap launchdek-admin" data-launchdek-page="sites">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="launchdek-sites-toolbar launchdek-card">
		<div class="launchdek-sites-toolbar-actions">
			<button type="button" class="button button-primary" id="launchdek-add-site"><?php esc_html_e( 'Add Site', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
			<button type="button" class="button" id="launchdek-connection-tester"><?php esc_html_e( 'Connection Tester', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
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
					<th scope="col"><?php esc_html_e( 'WP Ver', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
					<th scope="col"><?php esc_html_e( 'PHP Ver', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
					<th scope="col"><?php esc_html_e( 'Health', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
					<th scope="col"><?php esc_html_e( 'Actions', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
				</tr>
			</thead>
			<tbody></tbody>
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
				<p class="launchdek-modal-actions">
					<button type="button" class="button" id="launchdek-site-test"><?php esc_html_e( 'Test Connection', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Site', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
					<button type="button" class="button button-link-delete" id="launchdek-site-delete" hidden><?php esc_html_e( 'Delete Site', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
					<button type="button" class="button launchdek-modal-close"><?php esc_html_e( 'Cancel', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				</p>
			</form>
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
