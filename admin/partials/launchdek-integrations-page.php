<?php
/**
 * Integrations admin page.
 *
 * @package LaunchDek
 *
 * @var array  $settings               Plugin settings.
 * @var string $page                   Page identifier.
 * @var array  $integrations_bootstrap Preloaded connector + telemetry data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$integrations_bootstrap = isset( $integrations_bootstrap ) && is_array( $integrations_bootstrap )
	? $integrations_bootstrap
	: LAUNCHDEK_Integrations::get_page_bootstrap();
$connectors             = isset( $integrations_bootstrap['connectors'] ) && is_array( $integrations_bootstrap['connectors'] )
	? $integrations_bootstrap['connectors']
	: array();
$telemetry              = isset( $integrations_bootstrap['telemetry'] ) && is_array( $integrations_bootstrap['telemetry'] )
	? $integrations_bootstrap['telemetry']
	: array();
$telemetry_rules        = isset( $telemetry['rules'] ) && is_array( $telemetry['rules'] ) ? $telemetry['rules'] : array();
$telemetry_fields       = isset( $telemetry['fields'] ) && is_array( $telemetry['fields'] ) ? $telemetry['fields'] : array();
?>
<div class="wrap launchdek-admin" data-launchdek-page="integrations">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="launchdek-card">
		<h2><?php esc_html_e( 'Platform Connectors Hub', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
		<p><?php esc_html_e( 'Connect LaunchDek with site management platforms to push the agent to child sites.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
		<div id="launchdek-connectors-notice" class="launchdek-notice-area"></div>
		<ul id="launchdek-connectors-list" class="launchdek-connector-list" aria-label="<?php esc_attr_e( 'Platform connectors', LAUNCHDEK_TEXT_DOMAIN ); ?>" data-launchdek-preloaded="1">
			<?php foreach ( $connectors as $connector ) : ?>
				<?php
				$slug      = sanitize_key( (string) ( $connector['slug'] ?? '' ) );
				$available = ! empty( $connector['available'] );
				$row_class = 'launchdek-connector-row ' . ( $available ? 'available' : 'unavailable' );
				?>
				<li class="<?php echo esc_attr( $row_class ); ?>">
					<div class="launchdek-connector-meta">
						<span class="launchdek-connector-name"><?php echo esc_html( (string) ( $connector['name'] ?? '' ) ); ?></span>
						<?php if ( ! empty( $connector['supports_sync'] ) && ! empty( $connector['synced_sites'] ) ) : ?>
							<span class="launchdek-connector-sync-count launchdek-muted">
								<?php
								printf(
									/* translators: %s: synced site count */
									esc_html__( '%s synced sites', LAUNCHDEK_TEXT_DOMAIN ),
									esc_html( number_format_i18n( (int) $connector['synced_sites'] ) )
								);
								?>
							</span>
						<?php endif; ?>
					</div>
					<div class="launchdek-connector-actions">
						<span class="launchdek-badge <?php echo esc_attr( $available ? 'healthy' : 'unknown' ); ?>">
							<?php echo esc_html( $available ? __( 'Connected', LAUNCHDEK_TEXT_DOMAIN ) : __( 'Not Detected', LAUNCHDEK_TEXT_DOMAIN ) ); ?>
						</span>
						<button type="button" class="button" data-launchdek-connector-setup="1" data-connector-slug="<?php echo esc_attr( $slug ); ?>">
							<?php esc_html_e( 'Setup', LAUNCHDEK_TEXT_DOMAIN ); ?>
						</button>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
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
			<tbody data-launchdek-preloaded="1">
				<?php foreach ( $telemetry_rules as $rule ) : ?>
					<tr>
						<td class="check-column">
							<input type="checkbox" data-rule-id="<?php echo esc_attr( (string) ( $rule['id'] ?? '' ) ); ?>" <?php checked( ! empty( $rule['enabled'] ) ); ?> />
						</td>
						<td><?php echo esc_html( (string) ( $rule['integration_name'] ?? ( $rule['integration'] ?? '' ) ) ); ?></td>
						<td><?php echo esc_html( (string) ( $rule['platform_field'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $telemetry_fields[ $rule['launchdek_field'] ?? '' ] ?? ( $rule['launchdek_field'] ?? '' ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
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
			<div id="launchdek-connector-config" class="launchdek-connector-config" hidden>
				<div id="launchdek-connector-wp-umbrella-config" class="launchdek-connector-config-panel" hidden>
					<p class="launchdek-connector-config-status" id="launchdek-wp-umbrella-token-status" hidden></p>
					<label for="launchdek-wp-umbrella-api-token"><?php esc_html_e( 'Public API token', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
					<input type="password" id="launchdek-wp-umbrella-api-token" class="regular-text" autocomplete="off" spellcheck="false" placeholder="<?php esc_attr_e( 'Paste your WP Umbrella Public API token', LAUNCHDEK_TEXT_DOMAIN ); ?>">
					<p class="description launchdek-muted"><?php esc_html_e( 'Generate this in WP Umbrella under Profile → Public API (for developers). It is stored encrypted and never shown again after saving.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
					<p class="launchdek-inline-form">
						<button type="button" class="button button-primary" id="launchdek-wp-umbrella-save-token"><?php esc_html_e( 'Save API Token', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
						<button type="button" class="button button-link-delete" id="launchdek-wp-umbrella-clear-token" hidden><?php esc_html_e( 'Remove API Token', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
					</p>
				</div>
			</div>
			<div id="launchdek-connector-modal-notice" class="launchdek-notice-area"></div>
			<p class="launchdek-modal-actions">
				<button type="button" class="button button-primary" id="launchdek-connector-sync" hidden><?php esc_html_e( 'Sync Sites', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				<button type="button" class="button" id="launchdek-connector-preview" hidden><?php esc_html_e( 'Preview Sync', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				<button type="button" class="button" id="launchdek-connector-push"><?php esc_html_e( 'Push Client Panel', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
				<a href="#" id="launchdek-connector-docs" class="button" target="_blank" rel="noopener noreferrer" hidden><?php esc_html_e( 'Documentation', LAUNCHDEK_TEXT_DOMAIN ); ?></a>
				<button type="button" class="button launchdek-modal-close"><?php esc_html_e( 'Close', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
			</p>
		</div>
	</div>
</div>
