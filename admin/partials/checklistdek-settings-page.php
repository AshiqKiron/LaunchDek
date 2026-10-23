<?php
/**
 * Settings admin page template.
 *
 * @package ChecklistDek
 *
 * @var array $settings Plugin settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Admin view partial; variables are template-scoped.

$option_name   = CHECKLISTDEK_Settings::OPTION_NAME;
$active_events       = (array) ( $settings['notification_events'] ?? array() );
$active_email_events = (array) ( $settings['email_notification_events'] ?? array() );
$email_address       = (string) ( $settings['email_notification_address'] ?? '' );
$default_admin_email = sanitize_email( get_option( 'admin_email' ) );
$role_perms    = (array) ( $settings['role_permissions'] ?? array() );
$wp_roles      = wp_roles() ? wp_roles()->get_names() : array();
$cap_labels    = CHECKLISTDEK_Capabilities::get_capability_labels();
$guard_caps    = array(
	CHECKLISTDEK_Capabilities::MANAGE_SITES,
	CHECKLISTDEK_Capabilities::EDIT_CHECKLISTS,
	CHECKLISTDEK_Capabilities::EXECUTE_CHECKLISTS,
	CHECKLISTDEK_Capabilities::VIEW_AUDIT,
);
$exclude_options   = CHECKLISTDEK_Settings::get_exclude_options();
$exclude_labels    = CHECKLISTDEK_Settings::get_exclude_option_labels();
$panel_layouts            = CHECKLISTDEK_Licensing::get_panel_layouts_for_plan();
$panel_layout             = CHECKLISTDEK_Settings::get_client_panel_layout();
$show_notifications       = checklistdek_includes_pro_package() && CHECKLISTDEK_Licensing::can_use_notifications();
$show_scheduled_drift     = checklistdek_includes_pro_package() && CHECKLISTDEK_Licensing::can_use_scheduled_drift();
$section_tooltips = array(
	'platform'        => $show_scheduled_drift
		? __(
			'Turn ChecklistDek on or off for this hub and schedule automated drift checks that compare remote site state twice daily.',
			'checklistdek'
		)
		: __(
			'Turn ChecklistDek on or off for this hub.',
			'checklistdek'
		),
	'credential_vault' => __(
		'AES-256-CBC encryption using local WordPress salts. When enabled, application passwords are encrypted at rest and decrypted only when a remote request is made.',
		'checklistdek'
	),
	'client_panel'    => __(
		'Choose how the optional checklist panel appears on connected client sites. Layout changes sync on the next checklist push or client panel refresh.',
		'checklistdek'
	),
	'exclude_options' => __(
		'Sensitive settings the hub should never push to remote sites during API checklist steps. One REST field or legacy option name per line; matching fields are stripped from /wp/v2/settings payloads before they run.',
		'checklistdek'
	),
	'webhooks'        => __(
		'Route ChecklistDek events to Slack, Microsoft Teams, or Discord. Choose which run, step, drift, and client-panel events trigger a notification.',
		'checklistdek'
	),
	'access_guardrails' => __(
		'Map WordPress roles to ChecklistDek capabilities using agency presets. Administrators always retain full access regardless of this matrix.',
		'checklistdek'
	),
	'email_notifications' => __(
		'Send email alerts for key checklist events on this hub. Choose which events trigger a message and enter one or more comma-separated recipient addresses.',
		'checklistdek'
	),
	'onboarding'      => __(
		'Preview the first-run onboarding wizard without leaving Settings. Dismissal is stored separately and is not reset when you save these settings.',
		'checklistdek'
	),
);
$panel_layout_help = __(
	'Controls where and how the checklist panel appears in remote wp-admin. Use the preview to compare sidebar, top bar, dock, pill, and other layouts.',
	'checklistdek'
);
$channels        = array(
	'slack'   => array(
		'label'       => __( 'Slack', 'checklistdek' ),
		'placeholder' => 'https://hooks.slack.com/services/...',
	),
	'teams'   => array(
		'label'       => __( 'Microsoft Teams', 'checklistdek' ),
		'placeholder' => 'https://outlook.office.com/webhook/...',
	),
	'discord' => array(
		'label'       => __( 'Discord', 'checklistdek' ),
		'placeholder' => 'https://discord.com/api/webhooks/...',
	),
);
?>
<div class="wrap checklistdek-admin checklistdek-settings" data-checklistdek-page="settings">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<form method="post" action="options.php" class="checklistdek-settings-form">
		<?php settings_fields( CHECKLISTDEK_Settings::SETTINGS_GROUP ); ?>

		<nav class="checklistdek-settings-nav nav-tab-wrapper" role="tablist" aria-label="<?php esc_attr_e( 'Settings sections', 'checklistdek' ); ?>">
			<button type="button" class="nav-tab nav-tab-active" id="checklistdek-settings-tab-platform-security" data-checklistdek-settings-tab="platform-security" role="tab" aria-selected="true" aria-controls="checklistdek-settings-panel-platform-security"><?php esc_html_e( 'Platform & Security', 'checklistdek' ); ?></button>
			<button type="button" class="nav-tab" id="checklistdek-settings-tab-panel-exclude" data-checklistdek-settings-tab="panel-exclude" role="tab" aria-selected="false" aria-controls="checklistdek-settings-panel-panel-exclude"><?php esc_html_e( 'Panel layout & Exclude', 'checklistdek' ); ?></button>
			<?php if ( $show_notifications ) : ?>
			<button type="button" class="nav-tab" id="checklistdek-settings-tab-webhooks-email" data-checklistdek-settings-tab="webhooks-email" role="tab" aria-selected="false" aria-controls="checklistdek-settings-panel-webhooks-email"><?php esc_html_e( 'Webhooks & Email', 'checklistdek' ); ?></button>
			<?php endif; ?>
			<button type="button" class="nav-tab" id="checklistdek-settings-tab-access-roles" data-checklistdek-settings-tab="access-roles" role="tab" aria-selected="false" aria-controls="checklistdek-settings-panel-access-roles"><?php esc_html_e( 'Access & Roles', 'checklistdek' ); ?></button>
		</nav>

		<div id="checklistdek-settings-panel-platform-security" class="checklistdek-settings-tab-panel" data-checklistdek-settings-panel="platform-security" role="tabpanel" aria-labelledby="checklistdek-settings-tab-platform-security">
		<div class="checklistdek-card checklistdek-settings-card checklistdek-settings-general">
			<div class="checklistdek-card-heading-row checklistdek-card-heading-row--info-first">
				<button type="button" class="checklistdek-field-info checklistdek-has-tooltip" data-tooltip="<?php echo esc_attr( $section_tooltips['platform'] ); ?>" aria-label="<?php echo esc_attr( $section_tooltips['platform'] ); ?>">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				</button>
				<h2><?php esc_html_e( 'Platform', 'checklistdek' ); ?></h2>
			</div>
			<div class="checklistdek-settings-toggles">
				<label class="checklistdek-settings-toggle">
					<input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
					<span><?php esc_html_e( 'Enable ChecklistDek features', 'checklistdek' ); ?></span>
				</label>
				<?php if ( $show_scheduled_drift ) : ?>
				<label class="checklistdek-settings-toggle">
					<input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[drift_verification_enabled]" value="1" <?php checked( ! empty( $settings['drift_verification_enabled'] ) ); ?> />
					<span><?php esc_html_e( 'Run automated drift checks twice daily', 'checklistdek' ); ?></span>
				</label>
				<?php endif; ?>
			</div>
		</div>

		<div class="checklistdek-card checklistdek-settings-card">
			<div class="checklistdek-card-heading-row checklistdek-card-heading-row--info-first">
				<button type="button" class="checklistdek-field-info checklistdek-has-tooltip" data-tooltip="<?php echo esc_attr( $section_tooltips['onboarding'] ); ?>" aria-label="<?php echo esc_attr( $section_tooltips['onboarding'] ); ?>">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				</button>
				<h2><?php esc_html_e( 'Onboarding', 'checklistdek' ); ?></h2>
			</div>
			<button type="button" class="button button-secondary" id="checklistdek-show-onboarding">
				<?php esc_html_e( 'Show onboarding wizard', 'checklistdek' ); ?>
			</button>
		</div>

		<div class="checklistdek-card checklistdek-settings-card">
			<div class="checklistdek-card-heading-row checklistdek-card-heading-row--info-first">
				<button type="button" class="checklistdek-field-info checklistdek-has-tooltip" data-tooltip="<?php echo esc_attr( $section_tooltips['credential_vault'] ); ?>" aria-label="<?php echo esc_attr( $section_tooltips['credential_vault'] ); ?>">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				</button>
				<h2><?php esc_html_e( 'Credential Vault Security', 'checklistdek' ); ?></h2>
			</div>
			<label class="checklistdek-settings-toggle">
				<input type="checkbox" name="<?php echo esc_attr( $option_name ); ?>[encrypt_credentials]" value="1" <?php checked( ! empty( $settings['encrypt_credentials'] ) ); ?> />
				<span><?php esc_html_e( 'Encrypt stored application passwords at rest (recommended)', 'checklistdek' ); ?></span>
			</label>
			<p class="checklistdek-muted checklistdek-settings-note">
				<?php esc_html_e( 'Credentials are encrypted with OpenSSL AES-256-CBC and a key derived from your site salts. Decryption happens only when a remote request is made.', 'checklistdek' ); ?>
			</p>
		</div>
		</div>

		<div id="checklistdek-settings-panel-panel-exclude" class="checklistdek-settings-tab-panel" data-checklistdek-settings-panel="panel-exclude" role="tabpanel" aria-labelledby="checklistdek-settings-tab-panel-exclude" hidden>
		<div class="checklistdek-card checklistdek-settings-card">
			<div class="checklistdek-card-heading-row checklistdek-card-heading-row--info-first">
				<button type="button" class="checklistdek-field-info checklistdek-has-tooltip" data-tooltip="<?php echo esc_attr( $section_tooltips['client_panel'] ); ?>" aria-label="<?php echo esc_attr( $section_tooltips['client_panel'] ); ?>">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				</button>
				<h2><?php esc_html_e( 'Client Checklist Panel', 'checklistdek' ); ?></h2>
			</div>
			<div class="checklistdek-settings-panel-layout-picker">
				<label for="checklistdek-panel-layout" class="checklistdek-settings-panel-layout-label">
					<span class="checklistdek-field-label-row">
						<button type="button" class="checklistdek-field-info checklistdek-has-tooltip" data-tooltip="<?php echo esc_attr( $panel_layout_help ); ?>" aria-label="<?php echo esc_attr( $panel_layout_help ); ?>">
							<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
						</button>
						<span><?php esc_html_e( 'Panel layout', 'checklistdek' ); ?></span>
					</span>
				</label>
				<div class="checklistdek-settings-panel-layout-body">
					<div class="checklistdek-settings-panel-layout-controls">
						<select
							id="checklistdek-panel-layout"
							class="checklistdek-select checklistdek-settings-panel-layout-select"
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
						<p id="checklistdek-panel-layout-description" class="checklistdek-panel-layout-preview-description">
							<?php echo esc_html( $panel_layouts[ $panel_layout ]['description'] ?? '' ); ?>
						</p>
					</div>
					<div
						id="checklistdek-panel-layout-preview"
						class="checklistdek-panel-layout-preview"
						data-layout="<?php echo esc_attr( $panel_layout ); ?>"
						aria-live="polite"
					>
					<div class="checklistdek-panel-layout-preview-frame" aria-hidden="true">
						<span class="checklistdek-preview-adminbar"></span>
						<span class="checklistdek-preview-content"></span>
						<span class="checklistdek-preview-panel checklistdek-preview-panel--right"></span>
						<span class="checklistdek-preview-bar checklistdek-preview-bar--top"></span>
						<span class="checklistdek-preview-bar checklistdek-preview-bar--bottom"></span>
						<span class="checklistdek-preview-pill"></span>
						<span class="checklistdek-preview-toast"></span>
						<span class="checklistdek-preview-flyout"></span>
						<span class="checklistdek-preview-overlay"></span>
						<span class="checklistdek-preview-focus"></span>
						<span class="checklistdek-preview-metabox"></span>
					</div>
				</div>
				</div>
			</div>
		</div>

		<div class="checklistdek-card checklistdek-settings-card">
			<div class="checklistdek-card-heading-row checklistdek-card-heading-row--info-first">
				<button type="button" class="checklistdek-field-info checklistdek-has-tooltip" data-tooltip="<?php echo esc_attr( $section_tooltips['exclude_options'] ); ?>" aria-label="<?php echo esc_attr( $section_tooltips['exclude_options'] ); ?>">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				</button>
				<h2><?php esc_html_e( 'Exclude Options', 'checklistdek' ); ?></h2>
			</div>
			<label for="checklistdek-exclude-options" class="screen-reader-text"><?php esc_html_e( 'Excluded settings fields', 'checklistdek' ); ?></label>
			<textarea
				class="large-text code checklistdek-settings-exclude-options"
				id="checklistdek-exclude-options"
				name="<?php echo esc_attr( $option_name ); ?>[exclude_options]"
				rows="8"
				spellcheck="false"
				placeholder="<?php echo esc_attr( CHECKLISTDEK_Settings::format_exclude_options_textarea( CHECKLISTDEK_Settings::get_default_exclude_options() ) ); ?>"
			><?php echo esc_textarea( CHECKLISTDEK_Settings::format_exclude_options_textarea( $exclude_options ) ); ?></textarea>
			<p class="checklistdek-muted checklistdek-settings-note">
				<?php esc_html_e( 'One field per line. Use WordPress REST settings names (url, email, title) or legacy option names (siteurl, admin_email). Matching fields are removed from /wp/v2/settings API step payloads before they run on client sites.', 'checklistdek' ); ?>
			</p>
			<?php if ( ! empty( $exclude_labels ) ) : ?>
				<div class="checklistdek-settings-exclude-hints">
					<p class="checklistdek-muted checklistdek-settings-note checklistdek-settings-exclude-hints-label">
						<?php esc_html_e( 'Common fields:', 'checklistdek' ); ?>
					</p>
					<ul class="checklistdek-settings-exclude-common-fields">
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
		</div>

		<?php if ( $show_notifications ) : ?>
		<div id="checklistdek-settings-panel-webhooks-email" class="checklistdek-settings-tab-panel" data-checklistdek-settings-panel="webhooks-email" role="tabpanel" aria-labelledby="checklistdek-settings-tab-webhooks-email" hidden>
		<div class="checklistdek-card checklistdek-settings-card">
			<div class="checklistdek-card-heading-row checklistdek-card-heading-row--info-first">
				<button type="button" class="checklistdek-field-info checklistdek-has-tooltip" data-tooltip="<?php echo esc_attr( $section_tooltips['webhooks'] ); ?>" aria-label="<?php echo esc_attr( $section_tooltips['webhooks'] ); ?>">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				</button>
				<h2><?php esc_html_e( 'Webhooks & Notifications', 'checklistdek' ); ?></h2>
			</div>
			<p class="checklistdek-settings-lead"><?php esc_html_e( 'Channel Routing', 'checklistdek' ); ?></p>

			<div class="checklistdek-settings-channels" role="group" aria-label="<?php esc_attr_e( 'Notification channel routing', 'checklistdek' ); ?>">
				<?php foreach ( $channels as $key => $channel ) : ?>
					<div class="checklistdek-settings-channel">
						<label for="checklistdek-webhook-<?php echo esc_attr( $key ); ?>">
							<strong><?php echo esc_html( $channel['label'] ); ?></strong>
						</label>
						<input
							type="url"
							class="regular-text"
							id="checklistdek-webhook-<?php echo esc_attr( $key ); ?>"
							name="<?php echo esc_attr( $option_name ); ?>[<?php echo esc_attr( $key ); ?>_webhook]"
							value="<?php echo esc_attr( $settings[ $key . '_webhook' ] ?? '' ); ?>"
							placeholder="<?php echo esc_attr( $channel['placeholder'] ); ?>"
						/>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="checklistdek-settings-events">
				<h3><?php esc_html_e( 'Notification Events', 'checklistdek' ); ?></h3>
				<div class="checklistdek-settings-event-grid">
					<?php foreach ( CHECKLISTDEK_Settings::get_notification_events() as $event_key => $event_label ) : ?>
						<label class="checklistdek-settings-toggle">
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

		<div class="checklistdek-card checklistdek-settings-card">
			<div class="checklistdek-card-heading-row checklistdek-card-heading-row--info-first">
				<button type="button" class="checklistdek-field-info checklistdek-has-tooltip" data-tooltip="<?php echo esc_attr( $section_tooltips['email_notifications'] ); ?>" aria-label="<?php echo esc_attr( $section_tooltips['email_notifications'] ); ?>">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				</button>
				<h2><?php esc_html_e( 'Email Notifications', 'checklistdek' ); ?></h2>
			</div>
			<p class="checklistdek-settings-lead"><?php esc_html_e( 'Send email alerts for key checklist events on this site.', 'checklistdek' ); ?></p>

			<div class="checklistdek-settings-email-events" role="group" aria-label="<?php esc_attr_e( 'Email notification events', 'checklistdek' ); ?>">
				<?php foreach ( CHECKLISTDEK_Settings::get_email_notification_events() as $event_key => $event ) : ?>
					<div class="checklistdek-settings-email-event">
						<label class="checklistdek-settings-email-event-label">
							<input
								type="checkbox"
								name="<?php echo esc_attr( $option_name ); ?>[email_notification_events][]"
								value="<?php echo esc_attr( $event_key ); ?>"
								<?php checked( in_array( $event_key, $active_email_events, true ) ); ?>
							/>
							<span><?php echo esc_html( $event['label'] ); ?></span>
						</label>
						<p class="checklistdek-muted checklistdek-settings-email-event-description"><?php echo esc_html( $event['description'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="checklistdek-settings-email-address">
				<label for="checklistdek-email-notification-address">
					<strong><?php esc_html_e( 'Notification email addresses', 'checklistdek' ); ?></strong>
				</label>
				<input
					type="text"
					class="regular-text"
					id="checklistdek-email-notification-address"
					name="<?php echo esc_attr( $option_name ); ?>[email_notification_address]"
					value="<?php echo esc_attr( $email_address ); ?>"
					placeholder="<?php echo esc_attr( $default_admin_email ); ?>"
					autocomplete="email"
					inputmode="email"
				/>
				<p class="checklistdek-muted checklistdek-settings-note">
					<?php esc_html_e( 'Where these emails are sent. Separate multiple addresses with commas. Defaults to the site admin email.', 'checklistdek' ); ?>
				</p>
			</div>
		</div>
		</div>
		<?php endif; ?>

		<div id="checklistdek-settings-panel-access-roles" class="checklistdek-settings-tab-panel" data-checklistdek-settings-panel="access-roles" role="tabpanel" aria-labelledby="checklistdek-settings-tab-access-roles" hidden>
		<div class="checklistdek-card checklistdek-settings-card">
			<div class="checklistdek-card-heading-row checklistdek-card-heading-row--info-first">
				<button type="button" class="checklistdek-field-info checklistdek-has-tooltip" data-tooltip="<?php echo esc_attr( $section_tooltips['access_guardrails'] ); ?>" aria-label="<?php echo esc_attr( $section_tooltips['access_guardrails'] ); ?>">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				</button>
				<h2><?php esc_html_e( 'Access Guardrails', 'checklistdek' ); ?></h2>
			</div>
			<p class="checklistdek-settings-lead"><?php esc_html_e( 'Agency Role Permissions (Admin vs. Developer vs. Auditor)', 'checklistdek' ); ?></p>

			<div class="checklistdek-settings-role-presets">
				<?php foreach ( CHECKLISTDEK_Capabilities::get_agency_role_presets() as $preset ) : ?>
					<div class="checklistdek-settings-role-preset">
						<h3><?php echo esc_html( $preset['label'] ); ?></h3>
						<p><?php echo esc_html( $preset['description'] ); ?></p>
						<ul class="checklistdek-settings-role-caps">
							<?php foreach ( $preset['caps'] as $cap ) : ?>
								<?php if ( isset( $cap_labels[ $cap ] ) ) : ?>
									<li><?php echo esc_html( $cap_labels[ $cap ] ); ?></li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="checklistdek-card checklistdek-settings-card">
			<div class="checklistdek-card-heading-row checklistdek-card-heading-row--info-first">
				<button type="button" class="checklistdek-field-info checklistdek-has-tooltip" data-tooltip="<?php echo esc_attr( $section_tooltips['access_guardrails'] ); ?>" aria-label="<?php echo esc_attr( $section_tooltips['access_guardrails'] ); ?>">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
				</button>
				<h2><?php esc_html_e( 'WordPress Roles', 'checklistdek' ); ?></h2>
			</div>
			<p class="checklistdek-muted"><?php esc_html_e( 'Map each WordPress role to ChecklistDek capabilities. Administrators always retain full access.', 'checklistdek' ); ?></p>

				<div class="checklistdek-settings-perm-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'WordPress role permission matrix', 'checklistdek' ); ?>">
					<table class="checklistdek-settings-perm-table widefat">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Capability', 'checklistdek' ); ?></th>
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
											<label class="checklistdek-settings-perm-check">
												<span class="screen-reader-text">
													<?php
													printf(
														/* translators: 1: capability label, 2: role name */
														esc_html__( 'Allow %1$s for %2$s', 'checklistdek' ),
														esc_html( $cap_labels[ $cap ] ?? $cap ),
														esc_html( translate_user_role( $role_name ) )
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

		<?php submit_button(); ?>
	</form>

	<?php require CHECKLISTDEK_PLUGIN_DIR . 'admin/partials/checklistdek-onboarding-modal.php'; ?>
	<?php require CHECKLISTDEK_PLUGIN_DIR . 'admin/partials/checklistdek-template-picker-modal.php'; ?>
</div>
