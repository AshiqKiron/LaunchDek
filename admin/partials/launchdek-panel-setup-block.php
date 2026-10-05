<?php
/**
 * Client panel bootstrap download + retry install (shared markup).
 *
 * @package LaunchDek
 *
 * @var string $launchdek_panel_setup_id_prefix Element ID segment after launchdek- (empty = Sites modal IDs).
 * @var bool   $launchdek_panel_setup_hidden    Whether the root block starts hidden.
 * @var bool   $launchdek_panel_setup_show_retry Whether to show Retry panel install.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$panel_setup_prefix = isset( $launchdek_panel_setup_id_prefix ) ? sanitize_key( (string) $launchdek_panel_setup_id_prefix ) : '';
$panel_setup_hidden = ! empty( $launchdek_panel_setup_hidden );
$panel_setup_retry  = ! isset( $launchdek_panel_setup_show_retry ) || ! empty( $launchdek_panel_setup_show_retry );

if ( '' !== $panel_setup_prefix ) {
	$root_id     = 'launchdek-' . $panel_setup_prefix . '-panel-setup';
	$download_id = 'launchdek-' . $panel_setup_prefix . '-download-panel-bootstrap';
	$retry_id    = 'launchdek-' . $panel_setup_prefix . '-retry-panel-install';
	$result_id   = 'launchdek-' . $panel_setup_prefix . '-panel-setup-result';
} else {
	$root_id     = 'launchdek-panel-setup';
	$download_id = 'launchdek-download-panel-bootstrap';
	$retry_id    = 'launchdek-retry-panel-install';
	$result_id   = 'launchdek-panel-setup-result';
}
?>
<div id="<?php echo esc_attr( $root_id ); ?>" class="launchdek-panel-setup"<?php echo $panel_setup_hidden ? ' hidden' : ''; ?>>
	<h3 class="launchdek-panel-setup-title"><?php esc_html_e( 'Client checklist panel setup', LAUNCHDEK_TEXT_DOMAIN ); ?></h3>
	<p class="launchdek-muted"><?php esc_html_e( 'Optional one-time setup so clients see the checklist in their wp-admin. Core automation works without this step.', LAUNCHDEK_TEXT_DOMAIN ); ?></p>
	<ol class="launchdek-panel-setup-steps">
		<li><?php esc_html_e( 'Download the bootstrap file below.', LAUNCHDEK_TEXT_DOMAIN ); ?></li>
		<li><?php esc_html_e( 'Upload it to wp-content/mu-plugins/ on the client site (create the mu-plugins folder if needed).', LAUNCHDEK_TEXT_DOMAIN ); ?></li>
		<?php if ( $panel_setup_retry ) : ?>
			<li><?php esc_html_e( 'Click Retry panel install — LaunchDek will deploy the full panel and verify the connection.', LAUNCHDEK_TEXT_DOMAIN ); ?></li>
		<?php else : ?>
			<li><?php esc_html_e( 'Save the site in LaunchDek, then use Retry panel install on Sites to deploy the full panel.', LAUNCHDEK_TEXT_DOMAIN ); ?></li>
		<?php endif; ?>
	</ol>
	<p class="launchdek-panel-setup-actions">
		<button type="button" class="button" id="<?php echo esc_attr( $download_id ); ?>"><?php esc_html_e( 'Download launchdek-client.php', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
		<?php if ( $panel_setup_retry ) : ?>
			<button type="button" class="button button-secondary" id="<?php echo esc_attr( $retry_id ); ?>"><?php esc_html_e( 'Retry panel install', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
		<?php endif; ?>
	</p>
	<p class="launchdek-muted launchdek-panel-setup-path"><code>wp-content/mu-plugins/launchdek-client.php</code></p>
	<div id="<?php echo esc_attr( $result_id ); ?>" class="launchdek-notice-area"></div>
</div>
