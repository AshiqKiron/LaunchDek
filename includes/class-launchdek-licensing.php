<?php
/**
 * Plan tiers, feature gates, and site limits (Community / Pro / Agency).
 *
 * @package LaunchDek
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Billing and licensing helpers.
 */
class LAUNCHDEK_Licensing {

	const OPTION_NAME = 'launchdek_billing';

	const PLAN_COMMUNITY = 'community';
	const PLAN_PRO       = 'pro';
	const PLAN_AGENCY    = 'agency';

	/**
	 * Built-in template categories included on Community.
	 *
	 * @return string[]
	 */
	public static function get_community_template_categories() {
		return array( 'security', 'maintenance', 'performance', 'ecommerce' );
	}

	/**
	 * Client panel layouts included on Community.
	 *
	 * @return string[]
	 */
	public static function get_community_panel_layouts() {
		return array( 'live_topbar', 'bottom_dock', 'floating_pill' );
	}

	/**
	 * Raw billing option.
	 *
	 * @return array{plan: string, license_key?: string}
	 */
	public static function get_billing() {
		$stored = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		if ( launchdek_is_community_build() ) {
			return array(
				'plan'        => self::PLAN_COMMUNITY,
				'license_key' => '',
			);
		}

		$plan = sanitize_key( (string) ( $stored['plan'] ?? self::PLAN_COMMUNITY ) );
		if ( ! in_array( $plan, array( self::PLAN_COMMUNITY, self::PLAN_PRO, self::PLAN_AGENCY ), true ) ) {
			$plan = self::PLAN_COMMUNITY;
		}

		$license_key = $stored['license_key'] ?? '';

		return array(
			'plan'        => apply_filters( 'launchdek_billing_plan', $plan ),
			'license_key' => is_string( $license_key ) ? $license_key : '',
		);
	}

	/**
	 * Active plan slug.
	 *
	 * @return string
	 */
	public static function get_plan() {
		return self::get_billing()['plan'];
	}

	/**
	 * Whether Pro-level features are unlocked (Pro or Agency).
	 *
	 * @return bool
	 */
	public static function has_pro_features() {
		if ( launchdek_is_community_build() ) {
			return false;
		}

		return in_array( self::get_plan(), array( self::PLAN_PRO, self::PLAN_AGENCY ), true );
	}

	/**
	 * Connected site cap for paid plans; null means unlimited (Community).
	 *
	 * @return int|null
	 */
	public static function get_site_limit() {
		switch ( self::get_plan() ) {
			case self::PLAN_PRO:
				return 99;
			case self::PLAN_AGENCY:
				return 199;
			default:
				return null;
		}
	}

	/**
	 * Plan list price labels (USD / month) for UI.
	 *
	 * @return array<string, int>
	 */
	public static function get_plan_prices() {
		return array(
			self::PLAN_COMMUNITY => 0,
			self::PLAN_PRO       => 49,
			self::PLAN_AGENCY    => 99,
		);
	}

	/**
	 * Plan slugs in comparison table column order.
	 *
	 * @return string[]
	 */
	public static function get_compare_plan_slugs() {
		return array( self::PLAN_COMMUNITY, self::PLAN_PRO, self::PLAN_AGENCY );
	}

	/**
	 * External purchase URL for paid plans (filterable).
	 *
	 * @param string $plan Plan slug.
	 * @return string
	 */
	public static function get_purchase_url( $plan ) {
		$plan = sanitize_key( $plan );
		$base = apply_filters( 'launchdek_billing_purchase_url', 'https://asphaltthemes.com/launchdek' );

		/**
		 * Per-plan purchase URL override.
		 *
		 * @param string $url  Default purchase URL.
		 * @param string $plan Plan slug.
		 */
		return apply_filters( 'launchdek_billing_purchase_url_' . $plan, $base, $plan );
	}

	/**
	 * CTA button label for a plan column.
	 *
	 * @param string $plan Plan slug.
	 * @return string
	 */
	public static function get_plan_cta_label( $plan ) {
		switch ( sanitize_key( $plan ) ) {
			case self::PLAN_PRO:
				return __( 'Get Pro', LAUNCHDEK_TEXT_DOMAIN );
			case self::PLAN_AGENCY:
				return __( 'Get Agency', LAUNCHDEK_TEXT_DOMAIN );
			default:
				return __( 'Current plan', LAUNCHDEK_TEXT_DOMAIN );
		}
	}

	/**
	 * Count all built-in JSON templates (unfiltered).
	 *
	 * @return int
	 */
	public static function count_all_builtin_templates() {
		static $count = null;

		if ( null !== $count ) {
			return $count;
		}

		$files = glob( LAUNCHDEK_Templates::templates_dir() . '*.json' );
		$count = is_array( $files ) ? count( $files ) : 0;

		return $count;
	}

	/**
	 * Count built-in templates in Community-included categories.
	 *
	 * @return int
	 */
	public static function count_community_builtin_templates() {
		static $count = null;

		if ( null !== $count ) {
			return $count;
		}

		$count   = 0;
		$allowed = self::get_community_template_categories();
		$files   = glob( LAUNCHDEK_Templates::templates_dir() . '*.json' );

		if ( ! is_array( $files ) ) {
			return 0;
		}

		foreach ( $files as $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin JSON only.
			$raw  = file_get_contents( $file );
			$data = json_decode( $raw, true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$category = LAUNCHDEK_Templates::normalize_category( $data['category'] ?? '' );
			if ( in_array( $category, $allowed, true ) ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Feature rows for the Billing comparison table.
	 *
	 * Each row: label, tooltip, values keyed by plan slug with type bool|text and value.
	 *
	 * @return array<int, array{label: string, tooltip: string, values: array<string, array{type: string, value: string|bool}>}>
	 */
	public static function get_compare_features() {
		$plans              = self::get_compare_plan_slugs();
		$community_cats     = count( self::get_community_template_categories() );
		$all_cats           = count( LAUNCHDEK_Templates::get_categories() );
		$community_tpl      = (string) self::count_community_builtin_templates();
		$all_tpl            = (string) self::count_all_builtin_templates();
		$community_layouts  = (string) count( self::get_community_panel_layouts() );
		$all_layouts        = (string) count( LAUNCHDEK_Settings::get_client_panel_layouts() );

		$rows = array(
			array(
				'label'   => __( 'Connected sites', LAUNCHDEK_TEXT_DOMAIN ),
				'tooltip' => __( 'Maximum client sites you can register on this hub. Community has no cap; paid plans include a higher licensed limit.', LAUNCHDEK_TEXT_DOMAIN ),
				'values'  => array(
					self::PLAN_COMMUNITY => array(
						'type'  => 'text',
						'value' => __( 'Unlimited', LAUNCHDEK_TEXT_DOMAIN ),
					),
					self::PLAN_PRO       => array(
						'type'  => 'text',
						'value' => '99',
					),
					self::PLAN_AGENCY    => array(
						'type'  => 'text',
						'value' => '199',
					),
				),
			),
			array(
				'label'   => __( 'Built-in template categories', LAUNCHDEK_TEXT_DOMAIN ),
				'tooltip' => __( 'Standard checklist library groupings on the Checklists → Templates tab.', LAUNCHDEK_TEXT_DOMAIN ),
				'values'  => array(
					self::PLAN_COMMUNITY => array(
						'type'  => 'text',
						'value' => (string) $community_cats,
					),
					self::PLAN_PRO       => array(
						'type'  => 'text',
						'value' => (string) $all_cats,
					),
					self::PLAN_AGENCY    => array(
						'type'  => 'text',
						'value' => (string) $all_cats,
					),
				),
			),
			array(
				'label'   => __( 'Built-in checklist templates', LAUNCHDEK_TEXT_DOMAIN ),
				'tooltip' => __( 'Pre-built SOP stacks you can clone into My Checklists.', LAUNCHDEK_TEXT_DOMAIN ),
				'values'  => array(
					self::PLAN_COMMUNITY => array(
						'type'  => 'text',
						'value' => $community_tpl,
					),
					self::PLAN_PRO       => array(
						'type'  => 'text',
						'value' => $all_tpl,
					),
					self::PLAN_AGENCY    => array(
						'type'  => 'text',
						'value' => $all_tpl,
					),
				),
			),
			array(
				'label'   => __( 'Private Agency Vault', LAUNCHDEK_TEXT_DOMAIN ),
				'tooltip' => __( 'Save reusable agency checklists to a private vault on the Templates tab.', LAUNCHDEK_TEXT_DOMAIN ),
				'values'  => self::compare_bool_row( false, true, true ),
			),
			array(
				'label'   => __( 'Auto-capture', LAUNCHDEK_TEXT_DOMAIN ),
				'tooltip' => __( 'Record admin actions on a connected client site and import them as checklist steps.', LAUNCHDEK_TEXT_DOMAIN ),
				'values'  => self::compare_bool_row( false, true, true ),
			),
			array(
				'label'   => __( 'Email & webhook notifications', LAUNCHDEK_TEXT_DOMAIN ),
				'tooltip' => __( 'Opt-in Slack, Discord, Teams webhooks and email alerts for checklist run events.', LAUNCHDEK_TEXT_DOMAIN ),
				'values'  => self::compare_bool_row( false, true, true ),
			),
			array(
				'label'   => __( 'Scheduled drift verification', LAUNCHDEK_TEXT_DOMAIN ),
				'tooltip' => __( 'Twice-daily automated configuration drift checks across connected sites.', LAUNCHDEK_TEXT_DOMAIN ),
				'values'  => self::compare_bool_row( false, true, true ),
			),
			array(
				'label'   => __( 'Client panel layouts', LAUNCHDEK_TEXT_DOMAIN ),
				'tooltip' => __( 'How the checklist panel appears in client wp-admin (sidebar, top bar, dock, and more).', LAUNCHDEK_TEXT_DOMAIN ),
				'values'  => array(
					self::PLAN_COMMUNITY => array(
						'type'  => 'text',
						'value' => $community_layouts,
					),
					self::PLAN_PRO       => array(
						'type'  => 'text',
						'value' => $all_layouts,
					),
					self::PLAN_AGENCY    => array(
						'type'  => 'text',
						'value' => $all_layouts,
					),
				),
			),
			array(
				'label'   => __( 'Sites registry & connection tests', LAUNCHDEK_TEXT_DOMAIN ),
				'tooltip' => __( 'Register remote sites with Application Passwords and monitor connection health.', LAUNCHDEK_TEXT_DOMAIN ),
				'values'  => self::compare_bool_row( true, true, true ),
			),
			array(
				'label'   => __( 'Custom checklists & import/export', LAUNCHDEK_TEXT_DOMAIN ),
				'tooltip' => __( 'Build your own checklists, export JSON, and import on other hubs.', LAUNCHDEK_TEXT_DOMAIN ),
				'values'  => self::compare_bool_row( true, true, true ),
			),
			array(
				'label'   => __( 'Batch run & manual drift monitor', LAUNCHDEK_TEXT_DOMAIN ),
				'tooltip' => __( 'Run checklists from the hub, queue batch jobs, and verify drift on demand.', LAUNCHDEK_TEXT_DOMAIN ),
				'values'  => self::compare_bool_row( true, true, true ),
			),
			array(
				'label'   => __( 'MainWP & WP Umbrella sync', LAUNCHDEK_TEXT_DOMAIN ),
				'tooltip' => __( 'Import site inventory from supported connectors and push the client panel.', LAUNCHDEK_TEXT_DOMAIN ),
				'values'  => self::compare_bool_row( true, true, true ),
			),
			array(
				'label'   => __( 'Activity logs & audit trail', LAUNCHDEK_TEXT_DOMAIN ),
				'tooltip' => __( 'Immutable-style activity history with filters and detail views.', LAUNCHDEK_TEXT_DOMAIN ),
				'values'  => self::compare_bool_row( true, true, true ),
			),
			array(
				'label'   => __( 'Role permission matrix', LAUNCHDEK_TEXT_DOMAIN ),
				'tooltip' => __( 'Map LaunchDek capabilities to agency roles in Settings.', LAUNCHDEK_TEXT_DOMAIN ),
				'values'  => self::compare_bool_row( true, true, true ),
			),
			array(
				'label'   => __( 'Encrypted credential vault', LAUNCHDEK_TEXT_DOMAIN ),
				'tooltip' => __( 'Optional AES-256 encryption for stored Application Passwords.', LAUNCHDEK_TEXT_DOMAIN ),
				'values'  => self::compare_bool_row( true, true, true ),
			),
		);

		/**
		 * Filter Billing comparison table rows.
		 *
		 * @param array  $rows  Feature rows.
		 * @param string[] $plans Plan column order.
		 */
		return apply_filters( 'launchdek_billing_compare_features', $rows, $plans );
	}

	/**
	 * Build bool cells for Community / Pro / Agency columns.
	 *
	 * @param bool $community Community value.
	 * @param bool $pro       Pro value.
	 * @param bool $agency    Agency value.
	 * @return array<string, array{type: string, value: bool}>
	 */
	private static function compare_bool_row( $community, $pro, $agency ) {
		return array(
			self::PLAN_COMMUNITY => array(
				'type'  => 'bool',
				'value' => (bool) $community,
			),
			self::PLAN_PRO       => array(
				'type'  => 'bool',
				'value' => (bool) $pro,
			),
			self::PLAN_AGENCY    => array(
				'type'  => 'bool',
				'value' => (bool) $agency,
			),
		);
	}

	/**
	 * Format a plan price for the comparison header.
	 *
	 * @param string $plan Plan slug.
	 * @return string
	 */
	public static function format_plan_price_label( $plan ) {
		$price = self::get_plan_prices()[ sanitize_key( $plan ) ] ?? 0;

		if ( 0 === (int) $price ) {
			return __( '$0', LAUNCHDEK_TEXT_DOMAIN );
		}

		return sprintf(
			/* translators: %d: USD price per month */
			__( '$%d', LAUNCHDEK_TEXT_DOMAIN ),
			(int) $price
		);
	}

	/**
	 * Human-readable plan label.
	 *
	 * @param string|null $plan Plan slug or null for current.
	 * @return string
	 */
	public static function get_plan_label( $plan = null ) {
		$plan = $plan ? sanitize_key( $plan ) : self::get_plan();

		switch ( $plan ) {
			case self::PLAN_PRO:
				return __( 'Pro', LAUNCHDEK_TEXT_DOMAIN );
			case self::PLAN_AGENCY:
				return __( 'Agency', LAUNCHDEK_TEXT_DOMAIN );
			default:
				return __( 'Community', LAUNCHDEK_TEXT_DOMAIN );
		}
	}

	/**
	 * Whether another site can be registered on this hub.
	 *
	 * @return bool
	 */
	public static function can_add_site() {
		$limit = self::get_site_limit();
		if ( null === $limit ) {
			return true;
		}

		return LAUNCHDEK_Site_Repository::count() < $limit;
	}

	/**
	 * REST error when the site cap is reached.
	 *
	 * @return WP_Error
	 */
	public static function site_limit_error() {
		$limit = self::get_site_limit();

		return new WP_Error(
			'launchdek_site_limit',
			sprintf(
				/* translators: %d: maximum sites allowed on the current plan */
				__( 'Your %1$s plan allows up to %2$d connected sites. Upgrade on Billing to add more.', LAUNCHDEK_TEXT_DOMAIN ),
				self::get_plan_label(),
				(int) $limit
			),
			array(
				'status'     => 403,
				'site_limit' => (int) $limit,
				'plan'       => self::get_plan(),
			)
		);
	}

	/**
	 * REST error when a Pro feature is required.
	 *
	 * @param string $feature_name Short feature label for the message.
	 * @return WP_Error
	 */
	public static function pro_required_error( $feature_name ) {
		return new WP_Error(
			'launchdek_pro_required',
			sprintf(
				/* translators: %s: feature name */
				__( '%s is available on LaunchDek Pro and Agency plans.', LAUNCHDEK_TEXT_DOMAIN ),
				$feature_name
			),
			array(
				'status' => 403,
				'plan'   => self::get_plan(),
			)
		);
	}

	/**
	 * @return bool
	 */
	public static function can_use_all_templates() {
		return self::has_pro_features();
	}

	/**
	 * @return bool
	 */
	public static function can_use_agency_vault() {
		return self::has_pro_features();
	}

	/**
	 * @return bool
	 */
	public static function can_use_auto_capture() {
		return self::has_pro_features();
	}

	/**
	 * @return bool
	 */
	public static function can_use_notifications() {
		return self::has_pro_features();
	}

	/**
	 * @return bool
	 */
	public static function can_use_scheduled_drift() {
		return self::has_pro_features();
	}

	/**
	 * Whether a built-in template category is available on the current plan.
	 *
	 * @param string $category Category slug.
	 * @return bool
	 */
	public static function is_template_category_allowed( $category ) {
		if ( self::can_use_all_templates() ) {
			return true;
		}

		return in_array( sanitize_key( $category ), self::get_community_template_categories(), true );
	}

	/**
	 * Filter built-in template rows for the current plan.
	 *
	 * @param array $templates Template definitions.
	 * @return array
	 */
	public static function filter_builtin_templates( $templates ) {
		if ( self::can_use_all_templates() || ! is_array( $templates ) ) {
			return $templates;
		}

		return array_values(
			array_filter(
				$templates,
				function ( $row ) {
					if ( ! is_array( $row ) ) {
						return false;
					}
					$category = LAUNCHDEK_Templates::normalize_category( $row['category'] ?? '' );

					return self::is_template_category_allowed( $category );
				}
			)
		);
	}

	/**
	 * Template category metadata for admin UI (Community hides locked categories).
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function get_template_categories_for_plan() {
		$all = LAUNCHDEK_Templates::get_categories();

		if ( self::can_use_all_templates() ) {
			return $all;
		}

		$allowed = self::get_community_template_categories();
		$out     = array();
		foreach ( $allowed as $slug ) {
			if ( isset( $all[ $slug ] ) ) {
				$out[ $slug ] = $all[ $slug ];
			}
		}

		return $out;
	}

	/**
	 * Panel layout choices for Settings UI on the current plan.
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function get_panel_layouts_for_plan() {
		$all = LAUNCHDEK_Settings::get_client_panel_layouts();

		if ( self::has_pro_features() ) {
			return $all;
		}

		$allowed = self::get_community_panel_layouts();
		$out     = array();
		foreach ( $allowed as $slug ) {
			if ( isset( $all[ $slug ] ) ) {
				$out[ $slug ] = $all[ $slug ];
			}
		}

		return $out;
	}

	/**
	 * Sanitize a panel layout slug for the current plan.
	 *
	 * @param mixed $layout Raw layout.
	 * @return string
	 */
	public static function sanitize_panel_layout( $layout ) {
		$layout  = sanitize_key( (string) $layout );
		$allowed = self::get_panel_layouts_for_plan();

		if ( isset( $allowed[ $layout ] ) ) {
			return $layout;
		}

		$community = self::get_community_panel_layouts();

		return $community[0];
	}

	/**
	 * Layout pushed to client snapshots (respects plan if settings still hold a legacy value).
	 *
	 * @return string
	 */
	public static function get_effective_panel_layout() {
		$settings = LAUNCHDEK_Settings::get();
		$stored   = sanitize_key( (string) ( $settings['client_panel_layout'] ?? 'live_topbar' ) );

		return self::sanitize_panel_layout( $stored );
	}

	/**
	 * Persist plan (license validation hook point).
	 *
	 * @param string $plan Plan slug.
	 * @return bool|WP_Error
	 */
	public static function set_plan( $plan ) {
		if ( launchdek_is_community_build() ) {
			return new WP_Error(
				'launchdek_community_build',
				__( 'Plan changes are not available in the Community edition.', LAUNCHDEK_TEXT_DOMAIN ),
				array( 'status' => 403 )
			);
		}

		$plan = sanitize_key( $plan );
		if ( ! in_array( $plan, array( self::PLAN_COMMUNITY, self::PLAN_PRO, self::PLAN_AGENCY ), true ) ) {
			return new WP_Error( 'invalid_plan', __( 'Invalid plan.', LAUNCHDEK_TEXT_DOMAIN ), array( 'status' => 400 ) );
		}

		$billing         = self::get_billing();
		$billing['plan'] = $plan;
		update_option( self::OPTION_NAME, $billing, false );

		self::sync_drift_cron();

		return true;
	}

	/**
	 * Schedule or clear automated drift verification based on plan + settings.
	 *
	 * @return void
	 */
	public static function sync_drift_cron() {
		if ( self::can_use_scheduled_drift() ) {
			LAUNCHDEK_Drift_Cron::activate();
			return;
		}

		LAUNCHDEK_Drift_Cron::deactivate();
	}

	/**
	 * Summary for REST and admin JS.
	 *
	 * @return array
	 */
	public static function get_summary() {
		$limit = self::get_site_limit();
		$count = LAUNCHDEK_Site_Repository::count();

		return array(
			'plan'              => self::get_plan(),
			'plan_label'        => self::get_plan_label(),
			'price_usd'         => self::get_plan_prices()[ self::get_plan() ] ?? 0,
			'sites_connected'   => $count,
			'site_limit'        => $limit,
			'sites_unlimited'   => null === $limit,
			'can_add_site'      => self::can_add_site(),
			'has_pro_features'  => self::has_pro_features(),
			'can_auto_capture'  => self::can_use_auto_capture(),
			'can_vault'         => self::can_use_agency_vault(),
			'can_notifications' => self::can_use_notifications(),
			'can_scheduled_drift' => self::can_use_scheduled_drift(),
			'template_categories' => array_keys( self::get_template_categories_for_plan() ),
			'panel_layouts'     => array_keys( self::get_panel_layouts_for_plan() ),
		);
	}
}
