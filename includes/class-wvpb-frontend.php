<?php
/**
 * Frontend "Customize" button, modal shell, sticky bar, and archive
 * loop link.
 *
 * @package Webcasata_Visual_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WVPB_Frontend.
 */
class WVPB_Frontend {

	/**
	 * Hooks registration into WordPress.
	 *
	 * Where the "Customize" button appears, and whether the archive
	 * link / sticky bar are active at all, are store-wide preferences
	 * read once here from Settings — see class-wvpb-settings.php.
	 *
	 * @return void
	 */
	public static function init() {

		$settings = self::get_settings();

		list( $hook, $priority ) = self::get_button_placement( $settings['button_position'] );
		add_action( $hook, array( __CLASS__, 'render_customize_button' ), $priority );

		if ( ! empty( $settings['show_on_archive'] ) ) {
			add_action( 'woocommerce_after_shop_loop_item', array( __CLASS__, 'render_archive_customize_link' ), 15 );
		}

		add_action( 'wp_footer', array( __CLASS__, 'render_modal_shell' ) );

		if ( ! empty( $settings['show_sticky_bar'] ) ) {
			add_action( 'wp_footer', array( __CLASS__, 'render_sticky_bar' ) );
		}

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Reads this plugin's settings, filled in with defaults for any
	 * key that isn't set yet.
	 *
	 * @return array
	 */
	private static function get_settings() {
		return wp_parse_args(
			get_option( WVPB_OPTION_SETTINGS, array() ),
			array(
				'button_position' => 'before_cart',
				'show_on_archive' => false,
				'show_sticky_bar' => false,
			)
		);
	}

	/**
	 * Maps a button-position setting to the WooCommerce hook + priority
	 * that actually places it there.
	 *
	 * @param string $position One of WVPB_Settings::get_allowed_button_positions().
	 * @return array { 0: string $hook, 1: int $priority }
	 */
	private static function get_button_placement( $position ) {

		switch ( $position ) {
			case 'after_cart':
				return array( 'woocommerce_after_add_to_cart_button', 10 );

			// Both of these fire on woocommerce_single_product_summary,
			// which runs its default pieces at: title=5, rating=10,
			// price=10, excerpt=20, add_to_cart=30, meta=40, sharing=50.
			case 'before_summary':
				return array( 'woocommerce_single_product_summary', 4 );

			case 'after_summary':
				return array( 'woocommerce_single_product_summary', 25 );

			case 'before_cart':
			default:
				return array( 'woocommerce_before_add_to_cart_button', 10 );
		}
	}

	/**
	 * Finds the customizer assigned to the product currently being
	 * viewed, if any.
	 *
	 * Uses get_queried_object_id() rather than the global $product —
	 * the queried object is reliably set as soon as the main query has
	 * run, whereas WooCommerce's product global isn't guaranteed to be
	 * populated yet this early on every hook (wp_enqueue_scripts fires
	 * before the loop even starts on most themes).
	 *
	 * @return int Customizer post ID, or 0.
	 */
	private static function get_current_customizer_id() {

		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return 0;
		}

		$product_id = get_queried_object_id();

		if ( ! $product_id || ! class_exists( 'WVPB_Product_Assign' ) ) {
			return 0;
		}

		return WVPB_Product_Assign::get_assigned_customizer_id( $product_id );
	}

	/**
	 * Prints the "Customize" button, only when this product actually
	 * has a customizer assigned. Deliberately leaves WooCommerce's own
	 * Add to Cart button untouched for now — Stage 8 (cart integration)
	 * is where the decision about hiding/requiring it belongs.
	 *
	 * @return void
	 */
	public static function render_customize_button() {

		$customizer_id = self::get_current_customizer_id();

		if ( ! $customizer_id ) {
			return;
		}
		?>
		<button
			type="button"
			id="wvpb-open-customizer"
			class="button alt wvpb-customize-button wvpb-customize-trigger"
			data-customizer-id="<?php echo esc_attr( $customizer_id ); ?>"
		>
			<?php esc_html_e( 'Customize', 'webcasata-visual-product-builder' ); ?>
		</button>
		<?php
	}

	/**
	 * Prints a "Customize" link on each shop/category loop item that
	 * has a customizer assigned.
	 *
	 * This links to the product page rather than opening a modal
	 * directly in the loop — loading every visible product's full
	 * customizer configuration into one archive page just to support a
	 * click that may never happen isn't worth the extra weight. The
	 * link carries a query flag that the product page's modal.js
	 * checks on load to open the customizer automatically instead of
	 * making the customer click Customize twice.
	 *
	 * @return void
	 */
	public static function render_archive_customize_link() {

		global $product;

		if ( ! $product instanceof WC_Product || ! class_exists( 'WVPB_Product_Assign' ) ) {
			return;
		}

		$customizer_id = WVPB_Product_Assign::get_assigned_customizer_id( $product->get_id() );

		if ( ! $customizer_id ) {
			return;
		}

		$url = add_query_arg( 'wvpb_customize', '1', get_permalink( $product->get_id() ) );
		?>
		<a href="<?php echo esc_url( $url ); ?>" class="button wvpb-customize-button wvpb-archive-customize-button">
			<?php esc_html_e( 'Customize', 'webcasata-visual-product-builder' ); ?>
		</a>
		<?php
	}

	/**
	 * Prints the (initially hidden) modal shell once, in the footer.
	 *
	 * @return void
	 */
	public static function render_modal_shell() {

		if ( ! self::get_current_customizer_id() ) {
			return;
		}

		include WVPB_PLUGIN_DIR . 'public/templates/customizer-modal.php';
	}

	/**
	 * Prints the (initially hidden) sticky bar once, in the footer.
	 * modal.js shows it once the in-page Customize button scrolls out
	 * of view, and hides it again once that button is back in view.
	 *
	 * @return void
	 */
	public static function render_sticky_bar() {

		$customizer_id = self::get_current_customizer_id();

		if ( ! $customizer_id ) {
			return;
		}

		$product = wc_get_product( get_queried_object_id() );

		if ( ! $product ) {
			return;
		}

		include WVPB_PLUGIN_DIR . 'public/templates/sticky-bar.php';
	}

	/**
	 * Enqueues the frontend JS/CSS, only on single product pages with a
	 * customizer assigned — never loaded storewide.
	 *
	 * @return void
	 */
	public static function enqueue_assets() {

		$customizer_id = self::get_current_customizer_id();

		if ( ! $customizer_id || ! class_exists( 'WVPB_Customizer_Builder' ) ) {
			return;
		}

		// 'full' size here, versus 'thumbnail' on the admin screen —
		// this is what the customer actually sees on the product page,
		// not a small admin-list preview.
		$config = WVPB_Customizer_Builder::get_enriched_config( $customizer_id, 'full' );

		// Set when a shop/category loop's Customize link sent the
		// customer here — see render_archive_customize_link().
		$auto_open = isset( $_GET['wvpb_customize'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['wvpb_customize'] ) );

		wp_enqueue_style(
			'wvpb-frontend',
			WVPB_PLUGIN_URL . 'public/css/frontend.css',
			array(),
			WVPB_VERSION
		);

		wp_enqueue_script(
			'wvpb-compositor',
			WVPB_PLUGIN_URL . 'public/js/compositor.js',
			array( 'jquery' ),
			WVPB_VERSION,
			true
		);

		wp_enqueue_script(
			'wvpb-frontend-modal',
			WVPB_PLUGIN_URL . 'public/js/modal.js',
			array( 'jquery', 'wvpb-compositor' ),
			WVPB_VERSION,
			true
		);

		wp_localize_script(
			'wvpb-frontend-modal',
			'wvpbModalData',
			array(
				'config'   => $config,
				'autoOpen' => $auto_open,
			)
		);

		wp_localize_script(
			'wvpb-frontend-modal',
			'wvpbModalL10n',
			array(
				'noOptions'          => __( 'This customizer has no options configured yet.', 'webcasata-visual-product-builder' ),
				'previewPlaceholder' => __( 'Live preview coming soon', 'webcasata-visual-product-builder' ),
			)
		);
	}
}
