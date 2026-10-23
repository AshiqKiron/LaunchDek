<?php
/**
 * Integrations admin page.
 *
 * @package ChecklistDek
 *
 * @var array  $settings               Plugin settings.
 * @var string $page                   Page identifier.
 * @var array  $integrations_bootstrap Preloaded connector + telemetry data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Admin view partial; variables are template-scoped.

$integrations_bootstrap = isset( $integrations_bootstrap ) && is_array( $integrations_bootstrap )
	? $integrations_bootstrap
	: CHECKLISTDEK_Integrations::get_page_bootstrap();
$connectors             = isset( $integrations_bootstrap['connectors'] ) && is_array( $integrations_bootstrap['connectors'] )
	? $integrations_bootstrap['connectors']
	: array();
$telemetry              = isset( $integrations_bootstrap['telemetry'] ) && is_array( $integrations_bootstrap['telemetry'] )
	? $integrations_bootstrap['telemetry']
	: array();
$telemetry_rules        = isset( $telemetry['rules'] ) && is_array( $telemetry['rules'] ) ? $telemetry['rules'] : array();
$telemetry_fields       = isset( $telemetry['fields'] ) && is_array( $telemetry['fields'] ) ? $telemetry['fields'] : array();
$section_tooltips       = array(
	'connectors' => __(
		'Detect installed platform plugins on this hub, sync site inventory when supported, and push the ChecklistDek client checklist panel to connected sites. Sync and push run only when you start them from Setup—ChecklistDek does not send undisclosed telemetry.',
		'checklistdek'
	),
	'telemetry'  => __(
		'Choose which platform fields map onto ChecklistDek site records when you sync from a connector. These rules store mapping preferences on this hub only—they are not tracking or outbound telemetry.',
		'checklistdek'
	),
);
?>
<div class="wrap checklistdek-admin" data-checklistdek-page="integrations">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="checklistdek-card">
		<div class="checklistdek-card-heading-row checklistdek-card-heading-row--info-first">
			<button type="button" class="checklistdek-field-info checklistdek-has-tooltip" data-tooltip="<?php echo esc_attr( $section_tooltips['connectors'] ); ?>" aria-label="<?php echo esc_attr( $section_tooltips['connectors'] ); ?>">
				<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
			</button>
			<h2><?php esc_html_e( 'Platform Connectors Hub', 'checklistdek' ); ?></h2>
		</div>
		<p><?php esc_html_e( 'Connect ChecklistDek with site management platforms to push the agent to child sites.', 'checklistdek' ); ?></p>
		<div id="checklistdek-connectors-notice" class="checklistdek-notice-area"></div>
		<ul id="checklistdek-connectors-list" class="checklistdek-connector-list" aria-label="<?php esc_attr_e( 'Platform connectors', 'checklistdek' ); ?>" data-checklistdek-preloaded="1">
			<?php foreach ( $connectors as $connector ) : ?>
				<?php
				$slug      = sanitize_key( (string) ( $connector['slug'] ?? '' ) );
				$available = ! empty( $connector['available'] );
				$row_class = 'checklistdek-connector-row ' . ( $available ? 'available' : 'unavailable' );
				?>
				<li class="<?php echo esc_attr( $row_class ); ?>">
					<div class="checklistdek-connector-meta">
						<span class="checklistdek-connector-name"><?php echo esc_html( (string) ( $connector['name'] ?? '' ) ); ?></span>
						<?php if ( ! empty( $connector['supports_sync'] ) && ! empty( $connector['synced_sites'] ) ) : ?>
							<span class="checklistdek-connector-sync-count checklistdek-muted">
								<?php
								printf(
									/* translators: %s: synced site count */
									esc_html__( '%s synced sites', 'checklistdek' ),
									esc_html( number_format_i18n( (int) $connector['synced_sites'] ) )
								);
								?>
							</span>
						<?php endif; ?>
					</div>
					<div class="checklistdek-connector-actions">
						<span class="checklistdek-badge <?php echo esc_attr( $available ? 'healthy' : 'unknown' ); ?>">
							<?php echo esc_html( $available ? __( 'Connected', 'checklistdek' ) : __( 'Not Detected', 'checklistdek' ) ); ?>
						</span>
						<button type="button" class="button" data-checklistdek-connector-setup="1" data-connector-slug="<?php echo esc_attr( $slug ); ?>">
							<?php esc_html_e( 'Setup', 'checklistdek' ); ?>
						</button>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>

	<div class="checklistdek-card">
		<div class="checklistdek-card-heading-row checklistdek-card-heading-row--info-first">
			<button type="button" class="checklistdek-field-info checklistdek-has-tooltip" data-tooltip="<?php echo esc_attr( $section_tooltips['telemetry'] ); ?>" aria-label="<?php echo esc_attr( $section_tooltips['telemetry'] ); ?>">
				<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
			</button>
			<h2><?php esc_html_e( 'Telemetry Sync Mapping Rules', 'checklistdek' ); ?></h2>
		</div>
		<p><?php esc_html_e( 'Map platform telemetry fields to ChecklistDek site records when connectors sync remote site data.', 'checklistdek' ); ?></p>
		<div id="checklistdek-telemetry-notice" class="checklistdek-notice-area"></div>
		<div class="checklistdek-table-scroll">
		<table class="wp-list-table widefat fixed striped" id="checklistdek-telemetry-rules-table">
			<thead>
				<tr>
					<th scope="col" class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Enabled', 'checklistdek' ); ?></span></th>
					<th scope="col"><?php esc_html_e( 'Connector', 'checklistdek' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Platform Field', 'checklistdek' ); ?></th>
					<th scope="col"><?php esc_html_e( 'ChecklistDek Field', 'checklistdek' ); ?></th>
				</tr>
			</thead>
			<tbody data-checklistdek-preloaded="1">
				<?php foreach ( $telemetry_rules as $rule ) : ?>
					<tr>
						<td class="check-column">
							<input type="checkbox" data-rule-id="<?php echo esc_attr( (string) ( $rule['id'] ?? '' ) ); ?>" <?php checked( ! empty( $rule['enabled'] ) ); ?> />
						</td>
						<td><?php echo esc_html( (string) ( $rule['integration_name'] ?? ( $rule['integration'] ?? '' ) ) ); ?></td>
						<td><?php echo esc_html( (string) ( $rule['platform_field'] ?? '' ) ); ?></td>
						<td><?php echo esc_html( (string) ( $telemetry_fields[ $rule['checklistdek_field'] ?? '' ] ?? ( $rule['checklistdek_field'] ?? '' ) ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		</div>
		<p class="checklistdek-inline-form">
			<button type="button" class="button button-primary" id="checklistdek-save-telemetry-rules"><?php esc_html_e( 'Save Mapping Rules', 'checklistdek' ); ?></button>
		</p>
	</div>

	<div id="checklistdek-connector-modal" class="checklistdek-modal" hidden>
		<div class="checklistdek-modal-backdrop"></div>
		<div class="checklistdek-modal-content checklistdek-card">
			<h2 id="checklistdek-connector-modal-title"><?php esc_html_e( 'Connector Setup', 'checklistdek' ); ?></h2>
			<p id="checklistdek-connector-modal-description" class="checklistdek-muted"></p>
			<div id="checklistdek-connector-config" class="checklistdek-connector-config" hidden>
				<div id="checklistdek-connector-wp-umbrella-config" class="checklistdek-connector-config-panel" hidden>
					<p class="checklistdek-connector-config-status" id="checklistdek-wp-umbrella-token-status" hidden></p>
					<label for="checklistdek-wp-umbrella-api-token"><?php esc_html_e( 'Public API token', 'checklistdek' ); ?></label>
					<input type="password" id="checklistdek-wp-umbrella-api-token" class="regular-text" autocomplete="off" spellcheck="false" placeholder="<?php esc_attr_e( 'Paste your WP Umbrella Public API token', 'checklistdek' ); ?>">
					<p class="description checklistdek-muted"><?php esc_html_e( 'Generate this in WP Umbrella under Profile → Public API (for developers). Do not use the per-site connection key from the WP Umbrella plugin on a client site. The token is stored encrypted and never shown again after saving.', 'checklistdek' ); ?></p>
					<p class="checklistdek-inline-form">
						<button type="button" class="button button-primary" id="checklistdek-wp-umbrella-save-token"><?php esc_html_e( 'Save API Token', 'checklistdek' ); ?></button>
						<button type="button" class="button button-link-delete" id="checklistdek-wp-umbrella-clear-token" hidden><?php esc_html_e( 'Remove API Token', 'checklistdek' ); ?></button>
					</p>
				</div>
			</div>
			<div id="checklistdek-connector-modal-notice" class="checklistdek-notice-area"></div>
			<p class="checklistdek-modal-actions">
				<button type="button" class="button button-primary" id="checklistdek-connector-sync" hidden><?php esc_html_e( 'Sync Sites', 'checklistdek' ); ?></button>
				<button type="button" class="button" id="checklistdek-connector-preview" hidden><?php esc_html_e( 'Preview Sync', 'checklistdek' ); ?></button>
				<button type="button" class="button" id="checklistdek-connector-push"><?php esc_html_e( 'Push Client Panel', 'checklistdek' ); ?></button>
				<a href="#" id="checklistdek-connector-docs" class="button" target="_blank" rel="noopener noreferrer" hidden><?php esc_html_e( 'Documentation', 'checklistdek' ); ?></a>
				<button type="button" class="button checklistdek-modal-close"><?php esc_html_e( 'Close', 'checklistdek' ); ?></button>
			</p>
		</div>
	</div>
</div>
