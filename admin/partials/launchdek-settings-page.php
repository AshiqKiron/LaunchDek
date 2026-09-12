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
$channels      = array(
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

		<?php submit_button(); ?>
	</form>
</div>
