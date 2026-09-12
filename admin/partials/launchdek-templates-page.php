<?php
/**
 * Templates admin page.
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
<div class="wrap launchdek-admin" data-launchdek-page="templates">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="launchdek-card">
		<h2><?php esc_html_e( 'Built-In Standard Stacks', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
		<div id="launchdek-builtin-stacks" class="launchdek-stack-list" role="tablist" aria-label="<?php esc_attr_e( 'Built-in standard stacks', LAUNCHDEK_TEXT_DOMAIN ); ?>"></div>
		<div id="launchdek-builtin-detail" class="launchdek-template-detail"></div>
		<div id="launchdek-builtin-notice" class="launchdek-notice-area"></div>
	</div>

	<div class="launchdek-card">
		<h2><?php esc_html_e( 'Private Agency Vault', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
		<p><?php esc_html_e( 'Secure local repository for proprietary agency checklists.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
		<div class="launchdek-inline-form">
			<label class="screen-reader-text" for="launchdek-vault-workflow"><?php esc_html_e( 'Select workflow to vault', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
			<select id="launchdek-vault-workflow" class="launchdek-select">
				<option value=""><?php esc_html_e( 'Select workflow to vault…', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
			</select>
			<button type="button" class="button button-primary" id="launchdek-save-vault"><?php esc_html_e( 'Save to Vault', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
		</div>
		<div id="launchdek-vault-notice" class="launchdek-notice-area"></div>
		<div id="launchdek-vault-list" class="launchdek-template-grid"></div>
	</div>
</div>
