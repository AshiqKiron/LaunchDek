<?php
/**
 * Billing admin page.
 *
 * @package ChecklistDek
 *
 * @var array  $settings Plugin settings.
 * @var string $page     Page identifier.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_plan   = CHECKLISTDEK_Licensing::get_plan();
$plan_slugs     = CHECKLISTDEK_Licensing::get_compare_plan_slugs();
$compare_rows   = CHECKLISTDEK_Licensing::get_compare_features();
$included_label = __( 'Included', 'checklistdek' );
$not_included   = __( 'Not included', 'checklistdek' );

$checklistdek_billing_cell_allowed_html = array(
	'span' => array(
		'class'       => true,
		'aria-hidden' => true,
		'aria-label'  => true,
	),
);

/**
 * Render one comparison table cell.
 *
 * @param array{type: string, value: string|bool}|null $cell Cell definition.
 * @return void
 */
$checklistdek_render_compare_cell = function ( $cell ) use ( $included_label, $not_included, $checklistdek_billing_cell_allowed_html ) {
	if ( ! is_array( $cell ) ) {
		echo wp_kses(
			'<span class="checklistdek-billing-no" aria-hidden="true">&mdash;</span>',
			$checklistdek_billing_cell_allowed_html
		);
		return;
	}

	$type = isset( $cell['type'] ) ? (string) $cell['type'] : 'text';

	if ( 'bool' === $type ) {
		if ( ! empty( $cell['value'] ) ) {
			echo wp_kses(
				sprintf(
					'<span class="checklistdek-billing-yes" aria-label="%1$s"><span class="dashicons dashicons-yes" aria-hidden="true"></span></span>',
					esc_attr( $included_label )
				),
				$checklistdek_billing_cell_allowed_html
			);
			return;
		}

		echo wp_kses(
			sprintf(
				'<span class="checklistdek-billing-no" aria-label="%1$s">&mdash;</span>',
				esc_attr( $not_included )
			),
			$checklistdek_billing_cell_allowed_html
		);
		return;
	}

	echo esc_html( (string) ( $cell['value'] ?? '' ) );
};
?>
<div class="wrap checklistdek-admin" data-checklistdek-page="billing">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<p class="checklistdek-billing-intro">
		<?php esc_html_e( 'Compare Community and paid plans below. Purchase Pro or Agency on our website for expanded templates, automation alerts, and higher licensed site limits.', 'checklistdek' ); ?>
	</p>

	<div class="checklistdek-billing-compare-wrap">
		<table class="widefat striped checklistdek-billing-compare">
			<thead>
				<tr>
					<th scope="col" class="checklistdek-billing-compare-feature-col">
						<?php esc_html_e( 'Features', 'checklistdek' ); ?>
					</th>
					<?php foreach ( $plan_slugs as $plan_slug ) : ?>
						<th scope="col" class="checklistdek-billing-compare-plan-col">
							<span class="checklistdek-billing-plan-name"><?php echo esc_html( CHECKLISTDEK_Licensing::get_plan_label( $plan_slug ) ); ?></span>
							<span class="checklistdek-billing-plan-price"><?php echo esc_html( CHECKLISTDEK_Licensing::format_plan_price_label( $plan_slug ) ); ?></span>
						</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $compare_rows as $row ) : ?>
					<tr>
						<th scope="row" class="checklistdek-billing-compare-feature-col">
							<span class="checklistdek-billing-feature-label">
								<?php if ( ! empty( $row['tooltip'] ) ) : ?>
									<button
										type="button"
										class="checklistdek-field-info checklistdek-has-tooltip"
										data-tooltip="<?php echo esc_attr( $row['tooltip'] ); ?>"
										aria-label="<?php echo esc_attr( $row['tooltip'] ); ?>"
									>
										<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
									</button>
								<?php endif; ?>
								<span class="checklistdek-billing-feature-label-text"><?php echo esc_html( $row['label'] ?? '' ); ?></span>
							</span>
						</th>
						<?php foreach ( $plan_slugs as $plan_slug ) : ?>
							<td class="checklistdek-billing-compare-value-col">
								<?php $checklistdek_render_compare_cell( $row['values'][ $plan_slug ] ?? null ); ?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
				<tr class="checklistdek-billing-compare-cta-row">
					<th scope="row" class="checklistdek-billing-compare-feature-col"></th>
					<?php foreach ( $plan_slugs as $plan_slug ) : ?>
						<td class="checklistdek-billing-compare-value-col checklistdek-billing-compare-cta-cell">
							<?php if ( $current_plan === $plan_slug ) : ?>
								<button type="button" class="button checklistdek-billing-cta checklistdek-billing-cta--current" disabled>
									<?php esc_html_e( 'Current plan', 'checklistdek' ); ?>
								</button>
							<?php elseif ( CHECKLISTDEK_Licensing::PLAN_COMMUNITY !== $plan_slug ) : ?>
								<a
									class="button button-primary checklistdek-billing-cta"
									href="<?php echo esc_url( CHECKLISTDEK_Licensing::get_purchase_url( $plan_slug ) ); ?>"
									target="_blank"
									rel="noopener noreferrer"
								>
									<?php echo esc_html( CHECKLISTDEK_Licensing::get_plan_cta_label( $plan_slug ) ); ?>
								</a>
							<?php endif; ?>
						</td>
					<?php endforeach; ?>
				</tr>
			</tbody>
		</table>
	</div>
</div>
