<?php
/**
 * Client panel bootstrap download + retry install (shared markup).
 *
 * @package ChecklistDek
 *
 * @var string $checklistdek_panel_setup_id_prefix Element ID segment after checklistdek- (empty = Sites modal IDs).
 * @var bool   $checklistdek_panel_setup_hidden    Whether the root block starts hidden.
 * @var bool   $checklistdek_panel_setup_show_retry Whether to show Retry panel install.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$checklistdek_panel_setup_prefix = isset( $checklistdek_panel_setup_id_prefix ) ? sanitize_key( (string) $checklistdek_panel_setup_id_prefix ) : '';
$checklistdek_panel_setup_is_hidden = ! empty( $checklistdek_panel_setup_hidden );
$checklistdek_panel_setup_show_retry_button = ! isset( $checklistdek_panel_setup_show_retry ) || ! empty( $checklistdek_panel_setup_show_retry );

if ( '' !== $checklistdek_panel_setup_prefix ) {
	$checklistdek_panel_setup_root_id     = 'checklistdek-' . $checklistdek_panel_setup_prefix . '-panel-setup';
	$checklistdek_panel_setup_download_id = 'checklistdek-' . $checklistdek_panel_setup_prefix . '-download-panel-bootstrap';
	$checklistdek_panel_setup_retry_id    = 'checklistdek-' . $checklistdek_panel_setup_prefix . '-retry-panel-install';
	$checklistdek_panel_setup_result_id   = 'checklistdek-' . $checklistdek_panel_setup_prefix . '-panel-setup-result';
} else {
	$checklistdek_panel_setup_root_id     = 'checklistdek-panel-setup';
	$checklistdek_panel_setup_download_id = 'checklistdek-download-panel-bootstrap';
	$checklistdek_panel_setup_retry_id    = 'checklistdek-retry-panel-install';
	$checklistdek_panel_setup_result_id   = 'checklistdek-panel-setup-result';
}
?>
<div id="<?php echo esc_attr( $checklistdek_panel_setup_root_id ); ?>" class="checklistdek-panel-setup"<?php if ( $checklistdek_panel_setup_is_hidden ) : ?> hidden<?php endif; ?>>
	<h3 class="checklistdek-panel-setup-title"><?php esc_html_e( 'Client checklist panel setup', 'checklistdek' ); ?></h3>
	<p class="checklistdek-muted"><?php esc_html_e( 'One time setup so clients see the checklist in their wp-admin.', 'checklistdek' ); ?></p>
	<ol class="checklistdek-panel-setup-steps">
		<li><?php esc_html_e( 'Download the bootstrap file below.', 'checklistdek' ); ?></li>
		<li><?php esc_html_e( 'Upload it to wp-content/mu-plugins/ on the client site (create the mu-plugins folder if needed).', 'checklistdek' ); ?></li>
		<?php if ( $checklistdek_panel_setup_show_retry_button ) : ?>
			<li><?php esc_html_e( 'Click Retry panel install — ChecklistDek will deploy the full panel and verify the connection.', 'checklistdek' ); ?></li>
		<?php else : ?>
			<li><?php esc_html_e( 'Save the site in ChecklistDek, then use Retry panel install on Sites to deploy the full panel.', 'checklistdek' ); ?></li>
		<?php endif; ?>
	</ol>
	<p class="checklistdek-panel-setup-actions">
		<button type="button" class="button" id="<?php echo esc_attr( $checklistdek_panel_setup_download_id ); ?>"><?php esc_html_e( 'Download checklistdek-client.php', 'checklistdek' ); ?></button>
		<?php if ( $checklistdek_panel_setup_show_retry_button ) : ?>
			<button type="button" class="button button-secondary" id="<?php echo esc_attr( $checklistdek_panel_setup_retry_id ); ?>"><?php esc_html_e( 'Retry panel install', 'checklistdek' ); ?></button>
		<?php endif; ?>
	</p>
	<p class="checklistdek-muted checklistdek-panel-setup-path"><code>wp-content/mu-plugins/checklistdek-client.php</code></p>
	<div id="<?php echo esc_attr( $checklistdek_panel_setup_result_id ); ?>" class="checklistdek-notice-area"></div>
</div>
