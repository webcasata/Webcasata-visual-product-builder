<?php
/**
 * Calculates a customizer's price server-side, and exposes it as an
 * AJAX endpoint the frontend calls to confirm its own instant, purely
 * client-side estimate.
 *
 * The client-side number in modal.js is only ever a fast guess to keep
 * the UI feeling responsive — it must never be treated as the real
 * price, since it's trivial to tamper with in the browser. This class
 * is what actually gets trusted, and it recomputes the total from the
 * product's real price and the customizer's saved option prices, not
 * from anything the client sent.
 *
 * @package Webcasata_Visual_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WVPB_Pricing.
 */
class WVPB_Pricing {

	const NONCE_ACTION = 'wvpb_calculate_price';
	const AJAX_ACTION   = 'wvpb_calculate_price';

	/**
	 * Hooks registration into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( __CLASS__, 'handle_ajax_calculate' ) );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, array( __CLASS__, 'handle_ajax_calculate' ) );
	}

	/**
	 * AJAX handler: validates the request, then returns the
	 * server-computed total.
	 *
	 * @return void Always ends the request via wp_send_json_success()/error().
	 */
	public static function handle_ajax_calculate() {

		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$product_id    = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$customizer_id = isset( $_POST['customizer_id'] ) ? absint( $_POST['customizer_id'] ) : 0;

		$product = $product_id ? wc_get_product( $product_id ) : false;

		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'webcasata-visual-product-builder' ) ) );
		}

		// The customizer priced must actually be the one assigned to
		// this product — otherwise a request could ask to price this
		// product against a completely unrelated customizer's options.
		$assigned_id = class_exists( 'WVPB_Product_Assign' )
			? WVPB_Product_Assign::get_assigned_customizer_id( $product_id )
			: 0;

		if ( ! $customizer_id || $customizer_id !== $assigned_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid customizer.', 'webcasata-visual-product-builder' ) ) );
		}

		$config = class_exists( 'WVPB_Customizer_Builder' )
			? WVPB_Customizer_Builder::get_config( $customizer_id )
			: array();

		$selections = self::sanitize_selections(
			isset( $_POST['selections'] ) ? wp_unslash( $_POST['selections'] ) : ''
		);

		$result = self::calculate( $product, $config, $selections );

		wp_send_json_success(
			array(
				'total'      => $result['total'],
				'total_html' => wc_price( $result['total'] ),
				'breakdown'  => $result['breakdown'],
			)
		);
	}

	/**
	 * The actual calculation: the product's own price, plus the price
	 * of whichever option is currently selected in each step — read
	 * entirely from the customizer's saved config, never from a price
	 * the client might have sent alongside its selections.
	 *
	 * @param WC_Product $product    The product being customized.
	 * @param array      $config     Decoded customizer config (steps/options).
	 * @param array      $selections stepIndex (int) => selected option value (string).
	 * @return array{total: float, breakdown: array} Result; total is never negative.
	 */
	public static function calculate( $product, $config, $selections ) {

		$base  = (float) $product->get_price();
		$total = $base;

		$breakdown   = array();
		$breakdown[] = array(
			'label' => __( 'Base Price', 'webcasata-visual-product-builder' ),
			'price' => $base,
		);

		if ( ! empty( $config['steps'] ) && is_array( $config['steps'] ) ) {
			foreach ( $config['steps'] as $step_index => $step ) {

				if ( ! isset( $selections[ $step_index ] ) ) {
					continue;
				}

				$selected_value = $selections[ $step_index ];

				foreach ( ( $step['options'] ?? array() ) as $option ) {
					if ( isset( $option['value'] ) && $option['value'] === $selected_value ) {

						$price  = isset( $option['price'] ) ? (float) $option['price'] : 0.0;
						$total += $price;

						$step_title  = ! empty( $step['title'] ) ? $step['title'] : __( 'Option', 'webcasata-visual-product-builder' );
						$option_label = ! empty( $option['label'] ) ? $option['label'] : $option['value'];

						$breakdown[] = array(
							'label' => $step_title . ': ' . $option_label,
							'price' => $price,
						);
						break;
					}
				}
			}
		}

		return array(
			'total'     => max( 0.0, $total ),
			'breakdown' => $breakdown,
		);
	}

	/**
	 * Decodes and sanitizes the JSON selections map from the client.
	 *
	 * @param string $raw_json Raw JSON string from $_POST.
	 * @return array<int, string> stepIndex => sanitized value.
	 */
	private static function sanitize_selections( $raw_json ) {

		$decoded = json_decode( (string) $raw_json, true );

		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$clean = array();

		foreach ( $decoded as $step_index => $value ) {
			if ( ! is_scalar( $step_index ) || ! is_scalar( $value ) ) {
				continue;
			}
			$clean[ absint( $step_index ) ] = sanitize_text_field( (string) $value );
		}

		return $clean;
	}
}
