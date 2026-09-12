<?php
/**
 * Integrations admin page.
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
<div class="wrap launchdek-admin" data-launchdek-page="integrations">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="launchdek-card">
		<h2><?php esc_html_e( 'Platform Connectors Hub', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
		<p><?php esc_html_e( 'Connect LaunchDek with site management platforms to push the agent to child sites.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
		<ul id="launchdek-connectors-list" class="launchdek-connector-list" aria-label="<?php esc_attr_e( 'Platform connectors', LAUNCHDEK_TEXT_DOMAIN ); ?>"></ul>
	</div>

	<div class="launchdek-card">
		<h2><?php esc_html_e( 'Telemetry Sync Mapping Rules', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
		<p><?php esc_html_e( 'Map platform telemetry fields to LaunchDek site records when connectors sync remote site data.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
		<div id="launchdek-telemetry-notice" class="launchdek-notice-area"></div>
		<table class="wp-list-table widefat fixed striped" id="launchdek-telemetry-rules-table">
			<thead>
				<tr>
					<th scope="col" class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Enabled', LAUNCHDEK_TEXT_DOMAIN ); ?></span></th>
					<th scope="col"><?php esc_html_e( 'Connector', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
					<th scope="col"><?php esc_html_e( 'Platform Field', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
					<th scope="col"><?php esc_html_e( 'LaunchDek Field', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
		<p class="launchdek-inline-form">
			<button type="button" class="button button-primary" id="launchdek-save-telemetry-rules"><?php esc_html_e( 'Save Mapping Rules', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
		</p>
	</div>

	<div id="launchdek-connector-modal" class="launchdek-modal" hidden>
		<div class="launchdek-modal-backdrop"></div>
		<div class="launchdek-modal-content launchdek-card">
			<h2 id="launchdek-connector-modal-title"><?php esc_html_e( 'Connector Setup', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<p id="launchdek-connector-modal-description" class="launchdek-muted"></p>
			<div id="launchdek-connector-modal-notice" class="launchdek-notice-area"></div>
			<p class="launchdek-modal-actions">
				<button type="button" class="button button-primary" id="launchdek-connector-push"><?php esc_html_e( 'Push Agent', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				<a href="#" id="launchdek-connector-docs" class="button" target="_blank" rel="noopener noreferrer" hidden><?php esc_html_e( 'Documentation', LAUNCHDEK_TEXT_DOMAIN ); ?></a>
				<button type="button" class="button launchdek-modal-close"><?php esc_html_e( 'Close', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
			</p>
		</div>
	</div>
</div>
