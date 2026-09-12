<?php
/**
 * Adds a "Visual Customizer" tab to the WooCommerce Product Data
 * metabox, so a shop owner can pick which customizer (built in
 * Stage 2) applies to a given product.
 *
 * @package Webcasata_Visual_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WVPB_Product_Assign.
 */
class WVPB_Product_Assign {

	/**
	 * Product post meta key the assigned customizer's post ID is
	 * stored under. Matches the key uninstall.php already cleans up.
	 *
	 * @var string
	 */
	const META_KEY = '_wvpb_assigned_customizer';

	/**
	 * Hooks registration into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'woocommerce_product_data_tabs', array( __CLASS__, 'add_product_data_tab' ) );
		add_action( 'woocommerce_product_data_panels', array( __CLASS__, 'render_product_data_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_assignment' ) );
	}

	/**
	 * Registers the tab itself.
	 *
	 * @param array $tabs Existing Product Data tabs.
	 * @return array Modified tabs.
	 */
	public static function add_product_data_tab( $tabs ) {

		$tabs['wvpb_customizer'] = array(
			'label'    => __( 'Visual Customizer', 'webcasata-visual-product-builder' ),
			'target'   => 'wvpb_customizer_product_data',
			'class'    => array(),
			'priority' => 80,
		);

		return $tabs;
	}

	/**
	 * Renders the tab's panel: a single dropdown of every customizer.
	 *
	 * @return void
	 */
	public static function render_product_data_panel() {
		global $post;

		$assigned = (int) get_post_meta( $post->ID, self::META_KEY, true );

		$customizers = get_posts(
			array(
				'post_type'      => WVPB_CPT_CUSTOMIZER,
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		?>
		<div id="wvpb_customizer_product_data" class="panel woocommerce_options_panel">
			<div class="options_group">
				<p class="form-field">
					<label for="wvpb_assigned_customizer"><?php esc_html_e( 'Visual Customizer', 'webcasata-visual-product-builder' ); ?></label>
					<select id="wvpb_assigned_customizer" name="wvpb_assigned_customizer" class="select short">
						<option value="0"><?php esc_html_e( '— None —', 'webcasata-visual-product-builder' ); ?></option>
						<?php foreach ( $customizers as $customizer ) : ?>
							<option value="<?php echo esc_attr( $customizer->ID ); ?>" <?php selected( $assigned, $customizer->ID ); ?>>
								<?php
								echo esc_html(
									$customizer->post_title ? $customizer->post_title : __( '(untitled)', 'webcasata-visual-product-builder' )
								);
								if ( 'draft' === $customizer->post_status ) {
									echo ' ' . esc_html__( '(Draft)', 'webcasata-visual-product-builder' );
								}
								?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php
					// wc_help_tip() escapes its own output internally — it's
					// WooCommerce's own core template helper, safe to echo directly.
					echo wc_help_tip( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						__( 'Adds a "Customize" button to this product that opens the selected visual customizer.', 'webcasata-visual-product-builder' )
					);
					?>
				</p>

				<?php if ( empty( $customizers ) ) : ?>
					<p class="description">
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s: URL to create a new customizer. */
								__( 'No customizers yet. <a href="%s">Create one</a> first.', 'webcasata-visual-product-builder' ),
								esc_url( admin_url( 'post-new.php?post_type=' . WVPB_CPT_CUSTOMIZER ) )
							),
							array( 'a' => array( 'href' => array() ) )
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Saves the assignment.
	 *
	 * Hooked to `woocommerce_process_product_meta`, which WooCommerce's
	 * own product-save routine only fires after it has already verified
	 * the product-save nonce and the user's edit capability — so this
	 * doesn't re-check the nonce itself, only defensively re-checks the
	 * capability and, more importantly, validates that the submitted ID
	 * actually refers to a real customizer before trusting it.
	 *
	 * @param int $post_id Product ID being saved.
	 * @return void
	 */
	public static function save_assignment( $post_id ) {

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$customizer_id = isset( $_POST['wvpb_assigned_customizer'] ) ? absint( $_POST['wvpb_assigned_customizer'] ) : 0;

		// Never trust an ID straight from $_POST — confirm it actually
		// points at a wvpb_customizer post before storing the reference.
		if ( $customizer_id > 0 && WVPB_CPT_CUSTOMIZER !== get_post_type( $customizer_id ) ) {
			$customizer_id = 0;
		}

		if ( $customizer_id > 0 ) {
			update_post_meta( $post_id, self::META_KEY, $customizer_id );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}

	/**
	 * Returns the customizer post ID assigned to a product, or 0 if none.
	 *
	 * Small public accessor so later stages (frontend, cart) don't need
	 * to know the meta key or re-implement this lookup themselves.
	 *
	 * @param int $product_id Product ID.
	 * @return int Customizer post ID, or 0.
	 */
	public static function get_assigned_customizer_id( $product_id ) {
		return (int) get_post_meta( $product_id, self::META_KEY, true );
	}
}
