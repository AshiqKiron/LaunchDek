<?php
/**
 * Dashboard admin page template.
 *
 * @package LaunchDek
 *
 * @var array $settings Plugin settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap launchdek-admin">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="launchdek-card">
		<h2><?php esc_html_e( 'Welcome to LaunchDek', LAUNCHDEK_TEXT_DOMAIN ); ?></h2>
		<p>
			<?php esc_html_e( 'Your plugin scaffold is ready. Start building launch decks from here.', LAUNCHDEK_TEXT_DOMAIN ); ?>
		</p>
		<p>
			<strong><?php esc_html_e( 'Status:', LAUNCHDEK_TEXT_DOMAIN ); ?></strong>
			<?php echo ! empty( $settings['enabled'] ) ? esc_html__( 'Enabled', LAUNCHDEK_TEXT_DOMAIN ) : esc_html__( 'Disabled', LAUNCHDEK_TEXT_DOMAIN ); ?>
		</p>
		<p>
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=' . LAUNCHDEK_Admin::PAGE_SLUG . '-settings' ) ); ?>">
				<?php esc_html_e( 'Open Settings', LAUNCHDEK_TEXT_DOMAIN ); ?>
			</a>
		</p>
	</div>
</div>
