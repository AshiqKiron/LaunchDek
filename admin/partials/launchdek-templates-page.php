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
		<p class="launchdek-muted" id="launchdek-category-description"></p>
		<div id="launchdek-category-tabs" class="launchdek-category-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Template categories', LAUNCHDEK_TEXT_DOMAIN ); ?>"></div>
		<div id="launchdek-builtin-templates" class="launchdek-template-grid"></div>
		<div id="launchdek-builtin-notice" class="launchdek-notice-area"></div>
	</div>

	<div class="launchdek-card">
		<h2><?php esc_html_e( 'Your Custom Checklists', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
		<p class="launchdek-muted"><?php esc_html_e( 'Checklists you create and save under Checklists appear here for reuse and cloning.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
		<div id="launchdek-custom-checklists" class="launchdek-template-grid"></div>
		<div id="launchdek-custom-notice" class="launchdek-notice-area"></div>
	</div>

	<div class="launchdek-card">
		<h2><?php esc_html_e( 'Private Agency Vault', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
		<p><?php esc_html_e( 'Secure local repository for proprietary agency checklists.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
		<div class="launchdek-inline-form">
			<label class="screen-reader-text" for="launchdek-vault-checklist"><?php esc_html_e( 'Select checklist to vault', LAUNCHDEK_TEXT_DOMAIN ); ?></label>
			<select id="launchdek-vault-checklist" class="launchdek-select">
				<option value=""><?php esc_html_e( 'Select checklist to vault…', LAUNCHDEK_TEXT_DOMAIN ); ?></option>
			</select>
			<button type="button" class="button button-primary" id="launchdek-save-vault"><?php esc_html_e( 'Save to Vault', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
		</div>
		<div id="launchdek-vault-notice" class="launchdek-notice-area"></div>
		<div id="launchdek-vault-list" class="launchdek-template-grid"></div>
	</div>
</div>
