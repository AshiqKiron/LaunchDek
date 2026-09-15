<?php
/**
 * Shared confirmation modal for destructive or irreversible admin actions.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="launchdek-confirm-modal" class="launchdek-modal launchdek-confirm-modal" hidden role="dialog" aria-modal="true" aria-labelledby="launchdek-confirm-title">
	<div class="launchdek-modal-backdrop" data-launchdek-confirm-close></div>
	<div class="launchdek-modal-content launchdek-card">
		<h2 id="launchdek-confirm-title"><?php esc_html_e( 'Confirm action', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
		<p id="launchdek-confirm-message" class="launchdek-confirm-message"></p>
		<p class="launchdek-modal-actions launchdek-confirm-actions">
			<button type="button" class="button" id="launchdek-confirm-cancel"><?php esc_html_e( 'Cancel', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
			<button type="button" class="button button-primary" id="launchdek-confirm-ok"><?php esc_html_e( 'Confirm', LAUNCHDEK_TEXT_DOMAIN ); ?></button>
		</p>
	</div>
</div>
