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

$launchdek_panel_setup_prefix = isset( $launchdek_panel_setup_id_prefix ) ? sanitize_key( (string) $launchdek_panel_setup_id_prefix ) : '';
$launchdek_panel_setup_is_hidden = ! empty( $launchdek_panel_setup_hidden );
$launchdek_panel_setup_show_retry_button = ! isset( $launchdek_panel_setup_show_retry ) || ! empty( $launchdek_panel_setup_show_retry );

if ( '' !== $launchdek_panel_setup_prefix ) {
	$launchdek_panel_setup_root_id     = 'launchdek-' . $launchdek_panel_setup_prefix . '-panel-setup';
	$launchdek_panel_setup_download_id = 'launchdek-' . $launchdek_panel_setup_prefix . '-download-panel-bootstrap';
	$launchdek_panel_setup_retry_id    = 'launchdek-' . $launchdek_panel_setup_prefix . '-retry-panel-install';
	$launchdek_panel_setup_result_id   = 'launchdek-' . $launchdek_panel_setup_prefix . '-panel-setup-result';
} else {
	$launchdek_panel_setup_root_id     = 'launchdek-panel-setup';
	$launchdek_panel_setup_download_id = 'launchdek-download-panel-bootstrap';
	$launchdek_panel_setup_retry_id    = 'launchdek-retry-panel-install';
	$launchdek_panel_setup_result_id   = 'launchdek-panel-setup-result';
}
?>
<div id="<?php echo esc_attr( $launchdek_panel_setup_root_id ); ?>" class="launchdek-panel-setup"<?php echo $launchdek_panel_setup_is_hidden ? ' hidden' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Boolean attribute fragment. ?>>
	<h3 class="launchdek-panel-setup-title"><?php esc_html_e( 'Client checklist panel setup', 'launchdek' ); ?></h3>
	<p class="launchdek-muted"><?php esc_html_e( 'One time setup so clients see the checklist in their wp-admin.', 'launchdek' ); ?></p>
	<ol class="launchdek-panel-setup-steps">
		<li><?php esc_html_e( 'Download the bootstrap file below.', 'launchdek' ); ?></li>
		<li><?php esc_html_e( 'Upload it to wp-content/mu-plugins/ on the client site (create the mu-plugins folder if needed).', 'launchdek' ); ?></li>
		<?php if ( $launchdek_panel_setup_show_retry_button ) : ?>
			<li><?php esc_html_e( 'Click Retry panel install — LaunchDek will deploy the full panel and verify the connection.', 'launchdek' ); ?></li>
		<?php else : ?>
			<li><?php esc_html_e( 'Save the site in LaunchDek, then use Retry panel install on Sites to deploy the full panel.', 'launchdek' ); ?></li>
		<?php endif; ?>
	</ol>
	<p class="launchdek-panel-setup-actions">
		<button type="button" class="button" id="<?php echo esc_attr( $launchdek_panel_setup_download_id ); ?>"><?php esc_html_e( 'Download launchdek-client.php', 'launchdek' ); ?></button>
		<?php if ( $launchdek_panel_setup_show_retry_button ) : ?>
			<button type="button" class="button button-secondary" id="<?php echo esc_attr( $launchdek_panel_setup_retry_id ); ?>"><?php esc_html_e( 'Retry panel install', 'launchdek' ); ?></button>
		<?php endif; ?>
	</p>
	<p class="launchdek-muted launchdek-panel-setup-path"><code>wp-content/mu-plugins/launchdek-client.php</code></p>
	<div id="<?php echo esc_attr( $launchdek_panel_setup_result_id ); ?>" class="launchdek-notice-area"></div>
</div>
