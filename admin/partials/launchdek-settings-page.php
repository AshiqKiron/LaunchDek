<?php
/**
 * Settings admin page template.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap launchdek-admin">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<form method="post" action="options.php">
		<?php
		settings_fields( LAUNCHDEK_Settings::SETTINGS_GROUP );
		do_settings_sections( LAUNCHDEK_Admin::PAGE_SLUG . '-settings' );
		submit_button();
		?>
	</form>
</div>
