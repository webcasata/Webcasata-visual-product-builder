<?php
/**
 * Frontend customizer modal shell.
 *
 * Printed once, in wp_footer, only on a single product page whose
 * product has a customizer assigned. Hidden by default; shown by
 * modal.js when the "Customize" button is clicked. Both columns are
 * populated entirely client-side from the wvpbModalData.config JSON —
 * the right column is driven by compositor.js (Stage 6).
 *
 * @package Webcasata_Visual_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="wvpb-modal-overlay" class="wvpb-modal-overlay" hidden>
	<div id="wvpb-modal" class="wvpb-modal" role="dialog" aria-modal="true" aria-labelledby="wvpb-modal-title">

		<div class="wvpb-modal-header">
			<h2 id="wvpb-modal-title"><?php esc_html_e( 'Customize Your Order', 'webcasata-visual-product-builder' ); ?></h2>
			<button type="button" id="wvpb-modal-close" class="wvpb-modal-close" aria-label="<?php esc_attr_e( 'Close', 'webcasata-visual-product-builder' ); ?>">
				&times;
			</button>
		</div>

		<div class="wvpb-modal-body">
			<div id="wvpb-modal-steps" class="wvpb-modal-steps"><!-- rendered by modal.js --></div>
			<div id="wvpb-modal-preview" class="wvpb-modal-preview"><!-- rendered by modal.js --></div>
		</div>

		<div class="wvpb-modal-footer">
			<button
				type="button"
				id="wvpb-modal-add-to-cart"
				class="button alt"
				disabled="disabled"
				title="<?php esc_attr_e( 'Coming in a later stage', 'webcasata-visual-product-builder' ); ?>"
			>
				<?php esc_html_e( 'Add to Cart', 'webcasata-visual-product-builder' ); ?>
			</button>
		</div>

	</div>
</div>
