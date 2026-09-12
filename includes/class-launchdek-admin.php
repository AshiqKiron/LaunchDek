<?php
/**
 * Admin area — menus, settings pages, asset enqueuing.
 *
 * Purpose: Register admin UI via admin_menu and Settings API.
 *          Render dashboard/settings partials. Enqueue assets only on plugin screens.
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin handler.
 */
class LAUNCHDEK_Admin {

	/**
	 * Admin page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = LAUNCHDEK_PLUGIN_SLUG;

	/**
	 * Register the admin menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'LaunchDek', LAUNCHDEK_TEXT_DOMAIN ),
			__( 'LaunchDek', LAUNCHDEK_TEXT_DOMAIN ),
			LAUNCHDEK_Settings::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_admin_page' ),
			'dashicons-slides',
			73
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Dashboard', LAUNCHDEK_TEXT_DOMAIN ),
			__( 'Dashboard', LAUNCHDEK_TEXT_DOMAIN ),
			LAUNCHDEK_Settings::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_admin_page' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Settings', LAUNCHDEK_TEXT_DOMAIN ),
			__( 'Settings', LAUNCHDEK_TEXT_DOMAIN ),
			LAUNCHDEK_Settings::CAPABILITY,
			self::PAGE_SLUG . '-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			LAUNCHDEK_Settings::SETTINGS_GROUP,
			LAUNCHDEK_Settings::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'LAUNCHDEK_Settings', 'sanitize' ),
				'default'           => LAUNCHDEK_Settings::get_defaults(),
			)
		);

		add_settings_section(
			'launchdek_general_section',
			__( 'General', LAUNCHDEK_TEXT_DOMAIN ),
			array( $this, 'render_general_section' ),
			self::PAGE_SLUG . '-settings'
		);

		add_settings_field(
			'launchdek_enabled',
			__( 'Enable LaunchDek', LAUNCHDEK_TEXT_DOMAIN ),
			array( $this, 'render_enabled_field' ),
			self::PAGE_SLUG . '-settings',
			'launchdek_general_section'
		);
	}

	/**
	 * Redirect to the dashboard after activation.
	 *
	 * @return void
	 */
	public function maybe_activation_redirect() {
		if ( ! get_transient( 'launchdek_activation_redirect' ) ) {
			return;
		}

		delete_transient( 'launchdek_activation_redirect' );

		if ( wp_doing_ajax() || is_network_admin() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	/**
	 * Enqueue admin styles on plugin screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_styles( $hook ) {
		if ( ! $this->is_plugin_screen( $hook ) ) {
			return;
		}

		$stylesheet_path = LAUNCHDEK_PLUGIN_DIR . 'admin/css/launchdek-admin.css';

		wp_enqueue_style(
			'launchdek-admin',
			LAUNCHDEK_PLUGIN_URL . 'admin/css/launchdek-admin.css',
			array(),
			file_exists( $stylesheet_path ) ? (string) filemtime( $stylesheet_path ) : LAUNCHDEK_VERSION
		);
	}

	/**
	 * Enqueue admin scripts on plugin screens.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_scripts( $hook ) {
		if ( ! $this->is_plugin_screen( $hook ) ) {
			return;
		}

		$script_path = LAUNCHDEK_PLUGIN_DIR . 'admin/js/launchdek-admin.js';

		wp_enqueue_script(
			'launchdek-admin',
			LAUNCHDEK_PLUGIN_URL . 'admin/js/launchdek-admin.js',
			array(),
			file_exists( $script_path ) ? (string) filemtime( $script_path ) : LAUNCHDEK_VERSION,
			true
		);
	}

	/**
	 * Add a Settings link on the Plugins screen.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function add_settings_link( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-settings' ) ),
			esc_html__( 'Settings', LAUNCHDEK_TEXT_DOMAIN )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Add documentation link to plugin row meta.
	 *
	 * @param array  $links Plugin row meta links.
	 * @param string $file  Plugin file path.
	 * @return array
	 */
	public function add_plugin_row_meta( $links, $file ) {
		if ( LAUNCHDEK_PLUGIN_BASENAME !== $file ) {
			return $links;
		}

		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( LAUNCHDEK_PLUGIN_DOCS_URL ),
			esc_html__( 'Docs', LAUNCHDEK_TEXT_DOMAIN )
		);

		return $links;
	}

	/**
	 * Render the dashboard page.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( LAUNCHDEK_Settings::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', LAUNCHDEK_TEXT_DOMAIN ) );
		}

		$settings = LAUNCHDEK_Settings::get();

		require LAUNCHDEK_PLUGIN_DIR . 'admin/partials/launchdek-admin-page.php';
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( LAUNCHDEK_Settings::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', LAUNCHDEK_TEXT_DOMAIN ) );
		}

		require LAUNCHDEK_PLUGIN_DIR . 'admin/partials/launchdek-settings-page.php';
	}

	/**
	 * Render the general settings section description.
	 *
	 * @return void
	 */
	public function render_general_section() {
		echo '<p>' . esc_html__( 'Configure core LaunchDek behavior.', LAUNCHDEK_TEXT_DOMAIN ) . '</p>';
	}

	/**
	 * Render the enabled checkbox field.
	 *
	 * @return void
	 */
	public function render_enabled_field() {
		$settings = LAUNCHDEK_Settings::get();
		?>
		<label for="launchdek_enabled">
			<input
				type="checkbox"
				id="launchdek_enabled"
				name="<?php echo esc_attr( LAUNCHDEK_Settings::OPTION_NAME ); ?>[enabled]"
				value="1"
				<?php checked( ! empty( $settings['enabled'] ) ); ?>
			/>
			<?php esc_html_e( 'Enable LaunchDek features site-wide.', LAUNCHDEK_TEXT_DOMAIN ); ?>
		</label>
		<?php
	}

	/**
	 * Determine whether the current screen belongs to this plugin.
	 *
	 * @param string $hook Current admin page hook.
	 * @return bool
	 */
	protected function is_plugin_screen( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $screen && 0 === strpos( $screen->id, 'toplevel_page_' . self::PAGE_SLUG ) ) {
			return true;
		}

		return false !== strpos( $hook, self::PAGE_SLUG );
	}
}
