<?php
/**
 * Billing admin page.
 *
 * @package LaunchDek
 *
 * @var array  $settings Plugin settings.
 * @var string $page     Page identifier.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_plan   = LAUNCHDEK_Licensing::get_plan();
$plan_slugs     = LAUNCHDEK_Licensing::get_compare_plan_slugs();
$compare_rows   = LAUNCHDEK_Licensing::get_compare_features();
$included_label = esc_attr__( 'Included', LAUNCHDEK_TEXT_DOMAIN );
$not_included   = esc_attr__( 'Not included', LAUNCHDEK_TEXT_DOMAIN );

/**
 * Render one comparison table cell.
 *
 * @param array{type: string, value: string|bool} $cell Cell definition.
 * @return void
 */
$launchdek_render_compare_cell = function ( $cell ) use ( $included_label, $not_included ) {
	if ( ! is_array( $cell ) ) {
		echo '<span class="launchdek-billing-no" aria-hidden="true">&mdash;</span>';
		return;
	}

	$type = isset( $cell['type'] ) ? (string) $cell['type'] : 'text';

	if ( 'bool' === $type ) {
		if ( ! empty( $cell['value'] ) ) {
			echo '<span class="launchdek-billing-yes" aria-label="' . esc_attr( $included_label ) . '">';
			echo '<span class="dashicons dashicons-yes" aria-hidden="true"></span>';
			echo '</span>';
			return;
		}

		echo '<span class="launchdek-billing-no" aria-label="' . esc_attr( $not_included ) . '">&mdash;</span>';
		return;
	}

	echo esc_html( (string) ( $cell['value'] ?? '' ) );
};
?>
<div class="wrap launchdek-admin" data-launchdek-page="billing">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<p class="launchdek-billing-intro">
		<?php esc_html_e( 'Compare Community and paid plans below. Purchase Pro or Agency on our website for expanded templates, automation alerts, and higher licensed site limits.', LAUNCHDEK_TEXT_DOMAIN ); ?>
	</p>

	<div class="launchdek-billing-compare-wrap">
		<table class="widefat striped launchdek-billing-compare">
			<thead>
				<tr>
					<th scope="col" class="launchdek-billing-compare-feature-col">
						<?php esc_html_e( 'Features', LAUNCHDEK_TEXT_DOMAIN ); ?>
					</th>
					<?php foreach ( $plan_slugs as $plan_slug ) : ?>
						<th scope="col" class="launchdek-billing-compare-plan-col">
							<span class="launchdek-billing-plan-name"><?php echo esc_html( LAUNCHDEK_Licensing::get_plan_label( $plan_slug ) ); ?></span>
							<span class="launchdek-billing-plan-price"><?php echo esc_html( LAUNCHDEK_Licensing::format_plan_price_label( $plan_slug ) ); ?></span>
						</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $compare_rows as $row ) : ?>
					<tr>
						<th scope="row" class="launchdek-billing-compare-feature-col">
							<span class="launchdek-billing-feature-label">
								<?php if ( ! empty( $row['tooltip'] ) ) : ?>
									<button
										type="button"
										class="launchdek-field-info launchdek-has-tooltip"
										data-tooltip="<?php echo esc_attr( $row['tooltip'] ); ?>"
										aria-label="<?php echo esc_attr( $row['tooltip'] ); ?>"
									>
										<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
									</button>
								<?php endif; ?>
								<span class="launchdek-billing-feature-label-text"><?php echo esc_html( $row['label'] ?? '' ); ?></span>
							</span>
						</th>
						<?php
						foreach ( $plan_slugs as $plan_slug ) {
							$cell = $row['values'][ $plan_slug ] ?? null;
							echo '<td class="launchdek-billing-compare-value-col">';
							$launchdek_render_compare_cell( $cell );
							echo '</td>';
						}
						?>
					</tr>
				<?php endforeach; ?>
				<tr class="launchdek-billing-compare-cta-row">
					<th scope="row" class="launchdek-billing-compare-feature-col"></th>
					<?php foreach ( $plan_slugs as $plan_slug ) : ?>
						<td class="launchdek-billing-compare-value-col launchdek-billing-compare-cta-cell">
							<?php if ( $current_plan === $plan_slug ) : ?>
								<button type="button" class="button launchdek-billing-cta launchdek-billing-cta--current" disabled>
									<?php esc_html_e( 'Current plan', LAUNCHDEK_TEXT_DOMAIN ); ?>
								</button>
							<?php elseif ( LAUNCHDEK_Licensing::PLAN_COMMUNITY !== $plan_slug ) : ?>
								<a
									class="button button-primary launchdek-billing-cta"
									href="<?php echo esc_url( LAUNCHDEK_Licensing::get_purchase_url( $plan_slug ) ); ?>"
									target="_blank"
									rel="noopener noreferrer"
								>
									<?php echo esc_html( LAUNCHDEK_Licensing::get_plan_cta_label( $plan_slug ) ); ?>
								</a>
							<?php endif; ?>
						</td>
					<?php endforeach; ?>
				</tr>
			</tbody>
		</table>
	</div>
</div>
