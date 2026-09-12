<?php
/**
 * Sticky footer bar: product name, price, and a Customize button.
 *
 * Printed once, in wp_footer, only when the "Sticky bar" setting is on
 * and the current product has a customizer assigned. Hidden by
 * default; modal.js shows it via an IntersectionObserver once the
 * in-page Customize button scrolls out of view.
 *
 * @package Webcasata_Visual_Product_Builder
 *
 * @var WC_Product $product Set by WVPB_Frontend::render_sticky_bar().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="wvpb-sticky-bar" class="wvpb-sticky-bar" hidden>
	<div class="wvpb-sticky-bar-info">
		<span class="wvpb-sticky-bar-name"><?php echo esc_html( $product->get_name() ); ?></span>
		<span class="wvpb-sticky-bar-price">
			<?php echo wp_kses_post( $product->get_price_html() ); ?>
		</span>
	</div>
	<button type="button" id="wvpb-sticky-customize" class="button alt wvpb-customize-button wvpb-customize-trigger">
		<?php esc_html_e( 'Customize', 'webcasata-visual-product-builder' ); ?>
	</button>
</div>
