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
$active_events       = (array) ( $settings['notification_events'] ?? array() );
$active_email_events = (array) ( $settings['email_notification_events'] ?? array() );
$email_address       = (string) ( $settings['email_notification_address'] ?? '' );
$default_admin_email = sanitize_email( get_option( 'admin_email' ) );
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
$section_tooltips = array(
	'platform'        => __(
		'Turn LaunchDek on or off for this hub and schedule automated drift checks that compare remote site state twice daily.',
		LAUNCHDEK_TEXT_DOMAIN
	),
	'credential_vault' => __(
		'AES-256-CBC encryption using local WordPress salts. When enabled, application passwords are encrypted at rest and decrypted only when a remote request is made.',
		LAUNCHDEK_TEXT_DOMAIN
	),
	'client_panel'    => __(
		'Choose how the optional checklist panel appears on connected client sites. Layout changes sync on the next checklist push or client panel refresh.',
		LAUNCHDEK_TEXT_DOMAIN
	),
	'exclude_options' => __(
		'Sensitive settings the hub should never push to remote sites during API checklist steps. One REST field or legacy option name per line; matching fields are stripped from /wp/v2/settings payloads before they run.',
		LAUNCHDEK_TEXT_DOMAIN
	),
	'webhooks'        => __(
		'Route LaunchDek events to Slack, Microsoft Teams, or Discord. Choose which run, step, drift, and client-panel events trigger a notification.',
		LAUNCHDEK_TEXT_DOMAIN
	),
	'access_guardrails' => __(
		'Map WordPress roles to LaunchDek capabilities using agency presets. Administrators always retain full access regardless of this matrix.',
		LAUNCHDEK_TEXT_DOMAIN
	),
	'email_notifications' => __(
		'Send email alerts for key checklist events on this hub. Choose which events trigger a message and enter one or more comma-separated recipient addresses.',
		LAUNCHDEK_TEXT_DOMAIN
	),
	'onboarding'      => __(
		'Preview the first-run onboarding wizard without leaving Settings. Dismissal is stored separately and is not reset when you save these settings.',
		LAUNCHDEK_TEXT_DOMAIN
	),
);
$panel_layout_help = __(
	'Controls where and how the checklist panel appears in remote wp-admin. Use the preview to compare sidebar, top bar, dock, pill, and other layouts.',
	LAUNCHDEK_TEXT_DOMAIN
);
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
			<div class="launchdek-card-heading-row launchdek-card-heading-row--info-first">
				<button type="button" class="launchdek-field-info launchdek-has-tooltip" data-tooltip="<?php echo esc_attr( $section_tooltips['platform'] ); ?>" aria-label="<?php echo esc_attr( $section_tooltips['platform'] ); ?>">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				</button>
				<h2><?php esc_html_e( 'Platform', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			</div>
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
			<div class="launchdek-card-heading-row launchdek-card-heading-row--info-first">
				<button type="button" class="launchdek-field-info launchdek-has-tooltip" data-tooltip="<?php echo esc_attr( $section_tooltips['credential_vault'] ); ?>" aria-label="<?php echo esc_attr( $section_tooltips['credential_vault'] ); ?>">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				</button>
				<h2><?php esc_html_e( 'Credential Vault Security', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			</div>
			<label class="launchdek-settings-toggle">
				<input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[encrypt_credentials]" value="1" <?php checked( ! empty( $settings['encrypt_credentials'] ) ); ?> />
				<span><?php esc_html_e( 'Encrypt stored application passwords at rest (recommended)', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
			</label>
			<p class="launchdek-muted launchdek-settings-note">
				<?php esc_html_e( 'Credentials are encrypted with OpenSSL AES-256-CBC and a key derived from your site salts. Decryption happens only when a remote request is made.', LAUNCHDEK_TEXT_DOMAIN ); ?>
			</p>
		</div>

		<div class="launchdek-card launchdek-settings-card">
			<div class="launchdek-card-heading-row launchdek-card-heading-row--info-first">
				<button type="button" class="launchdek-field-info launchdek-has-tooltip" data-tooltip="<?php echo esc_attr( $section_tooltips['client_panel'] ); ?>" aria-label="<?php echo esc_attr( $section_tooltips['client_panel'] ); ?>">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				</button>
				<h2><?php esc_html_e( 'Client Checklist Panel', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			</div>
			<div class="launchdek-settings-panel-layout-picker">
				<label for="launchdek-panel-layout" class="launchdek-settings-panel-layout-label">
					<span class="launchdek-field-label-row">
						<button type="button" class="launchdek-field-info launchdek-has-tooltip" data-tooltip="<?php echo esc_attr( $panel_layout_help ); ?>" aria-label="<?php echo esc_attr( $panel_layout_help ); ?>">
							<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
						</button>
						<span><?php esc_html_e( 'Panel layout', LAUNCHDEK_TEXT_DOMAIN ); ?></span>
					</span>
				</label>
				<div class="launchdek-settings-panel-layout-body">
					<div class="launchdek-settings-panel-layout-controls">
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
		</div>

		<div class="launchdek-card launchdek-settings-card">
			<div class="launchdek-card-heading-row launchdek-card-heading-row--info-first">
				<button type="button" class="launchdek-field-info launchdek-has-tooltip" data-tooltip="<?php echo esc_attr( $section_tooltips['exclude_options'] ); ?>" aria-label="<?php echo esc_attr( $section_tooltips['exclude_options'] ); ?>">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				</button>
				<h2><?php esc_html_e( 'Exclude Options', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			</div>
			<label for="launchdek-exclude-options" class="screen-reader-text"><?php esc_html_e( 'Excluded settings fields', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
			<textarea
				class="large-text code launchdek-settings-exclude-options"
				id="launchdek-exclude-options"
				name="<?php echo esc_attr( $option_name ); ?>[exclude_options]"
				rows="8"
				spellcheck="false"
				placeholder="<?php echo esc_attr( LAUNCHDEK_Settings::format_exclude_options_textarea( LAUNCHDEK_Settings::get_default_exclude_options() ) ); ?>"
			><?php echo esc_textarea( LAUNCHDEK_Settings::format_exclude_options_textarea( $exclude_options ) ); ?></textarea>
			<p class="launchdek-muted launchdek-settings-note">
				<?php esc_html_e( 'One field per line. Use WordPress REST settings names (url, email, title) or legacy option names (siteurl, admin_email). Matching fields are removed from /wp/v2/settings API step payloads before they run on client sites.', LAUNCHDEK_TEXT_DOMAIN ); ?>
			</p>
			<?php if ( ! empty( $exclude_labels ) ) : ?>
				<div class="launchdek-settings-exclude-hints">
					<p class="launchdek-muted launchdek-settings-note launchdek-settings-exclude-hints-label">
						<?php esc_html_e( 'Common fields:', LAUNCHDEK_TEXT_DOMAIN ); ?>
					</p>
					<ul class="launchdek-settings-exclude-common-fields">
						<?php foreach ( $exclude_labels as $key => $label ) : ?>
							<li>
								<?php echo esc_html( $label ); ?>
								(<code><?php echo esc_html( $key ); ?></code>)
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>

		<div class="launchdek-card launchdek-settings-card">
			<div class="launchdek-card-heading-row launchdek-card-heading-row--info-first">
				<button type="button" class="launchdek-field-info launchdek-has-tooltip" data-tooltip="<?php echo esc_attr( $section_tooltips['webhooks'] ); ?>" aria-label="<?php echo esc_attr( $section_tooltips['webhooks'] ); ?>">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				</button>
				<h2><?php esc_html_e( 'Webhooks & Notifications', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			</div>
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
			<div class="launchdek-card-heading-row launchdek-card-heading-row--info-first">
				<button type="button" class="launchdek-field-info launchdek-has-tooltip" data-tooltip="<?php echo esc_attr( $section_tooltips['access_guardrails'] ); ?>" aria-label="<?php echo esc_attr( $section_tooltips['access_guardrails'] ); ?>">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				</button>
				<h2><?php esc_html_e( 'Access Guardrails', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			</div>
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
			<div class="launchdek-card-heading-row launchdek-card-heading-row--info-first">
				<button type="button" class="launchdek-field-info launchdek-has-tooltip" data-tooltip="<?php echo esc_attr( $section_tooltips['email_notifications'] ); ?>" aria-label="<?php echo esc_attr( $section_tooltips['email_notifications'] ); ?>">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				</button>
				<h2><?php esc_html_e( 'Email Notifications', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			</div>
			<p class="launchdek-settings-lead"><?php esc_html_e( 'Send email alerts for key checklist events on this site.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>

			<div class="launchdek-settings-email-events" role="group" aria-label="<?php esc_attr_e( 'Email notification events', LAUNCHDEK_TEXT_DOMAIN ); ?>">
				<?php foreach ( LAUNCHDEK_Settings::get_email_notification_events() as $event_key => $event ) : ?>
					<div class="launchdek-settings-email-event">
						<label class="launchdek-settings-email-event-label">
							<input
								type="checkbox"
								name="<?php echo esc_attr( $option_name ); ?>[email_notification_events][]"
								value="<?php echo esc_attr( $event_key ); ?>"
								<?php checked( in_array( $event_key, $active_email_events, true ) ); ?>
							/>
							<span><?php echo esc_html( $event['label'] ); ?></span>
						</label>
						<p class="launchdek-muted launchdek-settings-email-event-description"><?php echo esc_html( $event['description'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="launchdek-settings-email-address">
				<label for="launchdek-email-notification-address">
					<strong><?php esc_html_e( 'Notification email addresses', LAUNCHDEK_TEXT_DOMAIN ); ?></strong>
				</label>
				<input
					type="text"
					class="regular-text"
					id="launchdek-email-notification-address"
					name="<?php echo esc_attr( $option_name ); ?>[email_notification_address]"
					value="<?php echo esc_attr( $email_address ); ?>"
					placeholder="<?php echo esc_attr( $default_admin_email ); ?>"
					autocomplete="email"
					inputmode="email"
				/>
				<p class="launchdek-muted launchdek-settings-note">
					<?php esc_html_e( 'Where these emails are sent. Separate multiple addresses with commas. Defaults to the site admin email.', LAUNCHDEK_TEXT_DOMAIN ); ?>
				</p>
			</div>
		</div>

		<div class="launchdek-card launchdek-settings-card">
			<div class="launchdek-card-heading-row launchdek-card-heading-row--info-first">
				<button type="button" class="launchdek-field-info launchdek-has-tooltip" data-tooltip="<?php echo esc_attr( $section_tooltips['onboarding'] ); ?>" aria-label="<?php echo esc_attr( $section_tooltips['onboarding'] ); ?>">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				</button>
				<h2><?php esc_html_e( 'Onboarding', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
			</div>
			<button type="button" class="button button-secondary" id="launchdek-show-onboarding">
				<?php esc_html_e( 'Show onboarding wizard', LAUNCHDEK_TEXT_DOMAIN ); ?>
			</button>
		</div>

		<?php submit_button(); ?>
	</form>

	<?php require LAUNCHDEK_PLUGIN_DIR . 'admin/partials/launchdek-onboarding-modal.php'; ?>
	<?php require LAUNCHDEK_PLUGIN_DIR . 'admin/partials/launchdek-template-picker-modal.php'; ?>
</div>
