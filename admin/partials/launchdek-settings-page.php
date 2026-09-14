<?php
/**
 * Settings admin page template.
 *
 * @package LaunchDek
 *
 * @var array $settings Plugin settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$option_name   = LAUNCHDEK_Settings::OPTION_NAME;
$active_events = (array) ( $settings['notification_events'] ?? array() );
$role_perms    = (array) ( $settings['role_permissions'] ?? array() );
$wp_roles      = wp_roles() ? wp_roles()->get_names() : array();
$cap_labels    = LAUNCHDEK_Capabilities::get_capability_labels();
$guard_caps    = array(
	LAUNCHDEK_Capabilities::MANAGE_SITES,
	LAUNCHDEK_Capabilities::EDIT_CHECKLISTS,
	LAUNCHDEK_Capabilities::EXECUTE_CHECKLISTS,
	LAUNCHDEK_Capabilities::VIEW_AUDIT,
);
$exclude_options   = LAUNCHDEK_Settings::get_exclude_options();
$exclude_labels    = LAUNCHDEK_Settings::get_exclude_option_labels();
$panel_layouts     = LAUNCHDEK_Settings::get_client_panel_layouts();
$panel_layout      = LAUNCHDEK_Settings::get_client_panel_layout();
$channels        = array(
	'slack'   => array(
		'label'       => __( 'Slack', LAUNCHDEK_TEXT_DOMAIN ),
		'placeholder' => 'https://hooks.slack.com/services/...',
	),
	'teams'   => array(
		'label'       => __( 'Microsoft Teams', LAUNCHDEK_TEXT_DOMAIN ),
		'placeholder' => 'https://outlook.office.com/webhook/...',
	),
	'discord' => array(
		'label'       => __( 'Discord', LAUNCHDEK_TEXT_DOMAIN ),
		'placeholder' => 'https://discord.com/api/webhooks/...',
	),
);
?>
<div class="wrap launchdek-admin launchdek-settings" data-launchdek-page="settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<form method="post" action="options.php" class="launchdek-settings-form">
		<?php settings_fields( LAUNCHDEK_Settings::SETTINGS_GROUP ); ?>

		<div class="launchdek-card launchdek-settings-card launchdek-settings-general">
			<h2><?php esc_html_e( 'Platform', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<div class="launchdek-settings-toggles">
				<label class="launchdek-settings-toggle">
					<input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
					<span><?php esc_html_e( 'Enable LaunchDek features', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
				</label>
				<label class="launchdek-settings-toggle">
					<input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[drift_verification_enabled]" value="1" <?php checked( ! empty( $settings['drift_verification_enabled'] ) ); ?> />
					<span><?php esc_html_e( 'Run automated drift checks twice daily', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
				</label>
			</div>
		</div>

		<div class="launchdek-card launchdek-settings-card">
			<h2><?php esc_html_e( 'Credential Vault Security', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<p class="launchdek-settings-lead"><?php esc_html_e( 'AES-256-CBC encryption using local WordPress salts.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
			<label class="launchdek-settings-toggle">
				<input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[encrypt_credentials]" value="1" <?php checked( ! empty( $settings['encrypt_credentials'] ) ); ?> />
				<span><?php esc_html_e( 'Encrypt stored application passwords at rest (recommended)', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
			</label>
			<p class="launchdek-muted launchdek-settings-note">
				<?php esc_html_e( 'Credentials are encrypted with OpenSSL AES-256-CBC and a key derived from your site salts. Decryption happens only when a remote request is made.', LAUNCHDEK_TEXT_DOMAIN ); ?>
			</p>
		</div>

		<div class="launchdek-card launchdek-settings-card">
			<h2><?php esc_html_e( 'Client Checklist Panel', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<p class="launchdek-settings-lead"><?php esc_html_e( 'Choose how the optional checklist panel appears on connected client sites.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
			<div class="launchdek-settings-panel-layout-picker">
				<div class="launchdek-settings-panel-layout-controls">
					<label for="launchdek-panel-layout"><?php esc_html_e( 'Panel layout', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
					<select
						id="launchdek-panel-layout"
						class="launchdek-select launchdek-settings-panel-layout-select"
						name="<?php echo esc_attr( $option_name ); ?>[client_panel_layout]"
					>
						<?php foreach ( $panel_layouts as $layout_key => $layout ) : ?>
							<option
								value="<?php echo esc_attr( $layout_key ); ?>"
								data-description="<?php echo esc_attr( $layout['description'] ); ?>"
								<?php selected( $panel_layout, $layout_key ); ?>
							><?php echo esc_html( $layout['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
					<p id="launchdek-panel-layout-description" class="launchdek-panel-layout-preview-description">
						<?php echo esc_html( $panel_layouts[ $panel_layout ]['description'] ?? '' ); ?>
					</p>
				</div>
				<div
					id="launchdek-panel-layout-preview"
					class="launchdek-panel-layout-preview"
					data-layout="<?php echo esc_attr( $panel_layout ); ?>"
					aria-live="polite"
				>
					<div class="launchdek-panel-layout-preview-frame" aria-hidden="true">
						<span class="launchdek-preview-adminbar"></span>
						<span class="launchdek-preview-content"></span>
						<span class="launchdek-preview-panel launchdek-preview-panel--right"></span>
						<span class="launchdek-preview-panel launchdek-preview-panel--left"></span>
						<span class="launchdek-preview-panel launchdek-preview-panel--split"></span>
						<span class="launchdek-preview-bar launchdek-preview-bar--top"></span>
						<span class="launchdek-preview-bar launchdek-preview-bar--bottom"></span>
						<span class="launchdek-preview-pill"></span>
						<span class="launchdek-preview-toast"></span>
						<span class="launchdek-preview-flyout"></span>
						<span class="launchdek-preview-overlay"></span>
						<span class="launchdek-preview-focus"></span>
						<span class="launchdek-preview-metabox"></span>
					</div>
				</div>
			</div>
		</div>

		<div class="launchdek-card launchdek-settings-card">
			<h2><?php esc_html_e( 'Exclude Options', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<p class="launchdek-settings-lead"><?php esc_html_e( 'Sensitive settings the hub should never push to remote sites during API checklist steps.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
			<label for="launchdek-exclude-options" class="screen-reader-text"><?php esc_html_e( 'Excluded settings fields', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
			<textarea
				class="large-text code launchdek-settings-exclude-options"
				id="launchdek-exclude-options"
				name="<?php echo esc_attr( $option_name ); ?>[exclude_options]"
				rows="6"
				placeholder="<?php echo esc_attr( implode( "\n", LAUNCHDEK_Settings::get_default_exclude_options() ) ); ?>"
			><?php echo esc_textarea( implode( "\n", $exclude_options ) ); ?></textarea>
			<p class="launchdek-muted launchdek-settings-note">
				<?php esc_html_e( 'One field per line. Use WordPress REST settings names (url, email, title) or legacy option names (siteurl, admin_email). Matching fields are removed from /wp/v2/settings API step payloads before they run on client sites.', LAUNCHDEK_TEXT_DOMAIN ); ?>
			</p>
			<?php if ( ! empty( $exclude_labels ) ) : ?>
				<p class="launchdek-muted launchdek-settings-note launchdek-settings-exclude-hints">
					<?php
					$hints = array();
					foreach ( $exclude_labels as $key => $label ) {
						$hints[] = sprintf( '%s (%s)', $label, $key );
					}
					echo esc_html(
						sprintf(
							/* translators: %s: comma-separated field hints */
							__( 'Common fields: %s', LAUNCHDEK_TEXT_DOMAIN ),
							implode( ', ', $hints )
						)
					);
					?>
				</p>
			<?php endif; ?>
		</div>

		<div class="launchdek-card launchdek-settings-card">
			<h2><?php esc_html_e( 'Webhooks & Notifications', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<p class="launchdek-settings-lead"><?php esc_html_e( 'Channel Routing', LAUNCHDEK_TEXT_DOMAIN ); ?></p>

			<div class="launchdek-settings-channels" role="group" aria-label="<?php esc_attr_e( 'Notification channel routing', LAUNCHDEK_TEXT_DOMAIN ); ?>">
				<?php foreach ( $channels as $key => $channel ) : ?>
					<div class="launchdek-settings-channel">
						<label for="launchdek-webhook-<?php echo esc_attr( $key ); ?>">
							<strong><?php echo esc_html( $channel['label'] ); ?></strong>
						</label>
						<input
							type="url"
							class="regular-text"
							id="launchdek-webhook-<?php echo esc_attr( $key ); ?>"
							name="<?php echo esc_attr( $option_name ); ?>[<?php echo esc_attr( $key ); ?>_webhook]"
							value="<?php echo esc_attr( $settings[ $key . '_webhook' ] ?? '' ); ?>"
							placeholder="<?php echo esc_attr( $channel['placeholder'] ); ?>"
						/>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="launchdek-settings-events">
				<h3><?php esc_html_e( 'Notification Events', LAUNCHDEK_TEXT_DOMAIN ); ?></h3>
				<div class="launchdek-settings-event-grid">
					<?php foreach ( LAUNCHDEK_Settings::get_notification_events() as $event_key => $event_label ) : ?>
						<label class="launchdek-settings-toggle">
							<input
								type="checkbox"
								name="<?php echo esc_attr( $option_name ); ?>[notification_events][]"
								value="<?php echo esc_attr( $event_key ); ?>"
								<?php checked( in_array( $event_key, $active_events, true ) ); ?>
							/>
							<span><?php echo esc_html( $event_label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		</div>

		<div class="launchdek-card launchdek-settings-card">
			<h2><?php esc_html_e( 'Access Guardrails', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<p class="launchdek-settings-lead"><?php esc_html_e( 'Agency Role Permissions (Admin vs. Developer vs. Auditor)', LAUNCHDEK_TEXT_DOMAIN ); ?></p>

			<div class="launchdek-settings-role-presets">
				<?php foreach ( LAUNCHDEK_Capabilities::get_agency_role_presets() as $preset ) : ?>
					<div class="launchdek-settings-role-preset">
						<h3><?php echo esc_html( $preset['label'] ); ?></h3>
						<p><?php echo esc_html( $preset['description'] ); ?></p>
						<ul class="launchdek-settings-role-caps">
							<?php foreach ( $preset['caps'] as $cap ) : ?>
								<?php if ( isset( $cap_labels[ $cap ] ) ) : ?>
									<li><?php echo esc_html( $cap_labels[ $cap ] ); ?></li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="launchdek-settings-role-mapping">
				<h3><?php esc_html_e( 'Assign WordPress Roles', LAUNCHDEK_TEXT_DOMAIN ); ?></h3>
				<p class="launchdek-muted"><?php esc_html_e( 'Map each WordPress role to LaunchDek capabilities. Administrators always retain full access.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>

				<div class="launchdek-settings-perm-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'WordPress role permission matrix', LAUNCHDEK_TEXT_DOMAIN ); ?>">
					<table class="launchdek-settings-perm-table widefat">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Capability', LAUNCHDEK_TEXT_DOMAIN ); ?></th>
								<?php foreach ( $wp_roles as $role_slug => $role_name ) : ?>
									<?php if ( 'administrator' === $role_slug ) : ?>
										<?php continue; ?>
									<?php endif; ?>
									<th scope="col"><?php echo esc_html( translate_user_role( $role_name ) ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $guard_caps as $cap ) : ?>
								<tr>
									<th scope="row"><?php echo esc_html( $cap_labels[ $cap ] ?? $cap ); ?></th>
									<?php foreach ( $wp_roles as $role_slug => $role_name ) : ?>
										<?php if ( 'administrator' === $role_slug ) : ?>
											<?php continue; ?>
										<?php endif; ?>
										<td>
											<label class="launchdek-settings-perm-check">
												<span class="screen-reader-text">
													<?php
													printf(
														/* translators: 1: capability label, 2: role name */
														esc_html__( 'Allow %1$s for %2$s', LAUNCHDEK_TEXT_DOMAIN ),
														$cap_labels[ $cap ] ?? $cap,
														translate_user_role( $role_name )
													);
													?>
												</span>
												<input
													type="checkbox"
													name="<?php echo esc_attr( $option_name ); ?>[role_permissions][<?php echo esc_attr( $cap ); ?>][]"
													value="<?php echo esc_attr( $role_slug ); ?>"
													<?php checked( ! empty( $role_perms[ $cap ] ) && in_array( $role_slug, $role_perms[ $cap ], true ) ); ?>
												/>
											</label>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<div class="launchdek-card launchdek-settings-card">
			<h2><?php esc_html_e( 'Onboarding', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			<p class="launchdek-settings-lead"><?php esc_html_e( 'Preview the first-run onboarding wizard without leaving Settings.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
			<button type="button" class="button button-secondary" id="launchdek-show-onboarding">
				<?php esc_html_e( 'Show onboarding wizard', LAUNCHDEK_TEXT_DOMAIN ); ?>
			</button>
		</div>

		<?php submit_button(); ?>
	</form>

	<?php require LAUNCHDEK_PLUGIN_DIR . 'admin/partials/launchdek-onboarding-modal.php'; ?>
	<?php require LAUNCHDEK_PLUGIN_DIR . 'admin/partials/launchdek-template-picker-modal.php'; ?>
</div>
