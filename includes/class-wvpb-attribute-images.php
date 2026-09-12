<?php
/**
 * Adds PNG/SVG layer-image fields to WooCommerce product attribute
 * terms (e.g. Attributes → Flavour → Mango), so an existing attribute
 * can be reused as a visual layer without rebuilding it as a
 * customizer option from scratch.
 *
 * @package Webcasata_Visual_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WVPB_Attribute_Images.
 */
class WVPB_Attribute_Images {

	const META_KEY_PNG = '_wvpb_term_image_png';
	const META_KEY_SVG = '_wvpb_term_image_svg';
	const NONCE_ACTION  = 'wvpb_save_term_images';
	const NONCE_FIELD   = 'wvpb_term_images_nonce';

	/**
	 * Hooks registration into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		// Deferred to `init`, after WooCommerce's own default-priority
		// taxonomy registration, so wc_get_attribute_taxonomies() below
		// reflects every pa_* taxonomy that actually exists this request.
		add_action( 'init', array( __CLASS__, 'register_term_field_hooks' ), 20 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Hooks our field renderer and save handler onto every currently
	 * registered WooCommerce product attribute taxonomy.
	 *
	 * WordPress has no wildcard hook name for "any pa_* taxonomy," so —
	 * the same technique WooCommerce attribute-swatch extensions use —
	 * we loop over the known attribute taxonomies and register one
	 * add/edit/save hook per taxonomy, by its actual name.
	 *
	 * @return void
	 */
	public static function register_term_field_hooks() {

		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return;
		}

		$attributes = wc_get_attribute_taxonomies();

		if ( empty( $attributes ) ) {
			return;
		}

		foreach ( $attributes as $attribute ) {
			$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );

			add_action( "{$taxonomy}_add_form_fields", array( __CLASS__, 'render_add_form_fields' ) );
			add_action( "{$taxonomy}_edit_form_fields", array( __CLASS__, 'render_edit_form_fields' ), 10, 2 );
			add_action( "created_{$taxonomy}", array( __CLASS__, 'save_term_fields' ) );
			add_action( "edited_{$taxonomy}", array( __CLASS__, 'save_term_fields' ) );
		}
	}

	/**
	 * Renders the two image fields on the "Add New" term screen.
	 *
	 * The Add screen uses a plain <div class="form-field"> layout —
	 * there's no existing term yet, so nothing to preload.
	 *
	 * @return void
	 */
	public static function render_add_form_fields() {

		if ( ! self::current_user_can_manage() ) {
			return;
		}

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<div class="form-field wvpb-term-image-field">
			<label><?php esc_html_e( 'Layer Image (PNG)', 'webcasata-visual-product-builder' ); ?></label>
			<?php self::render_image_picker_field( self::META_KEY_PNG, 0 ); ?>
			<p><?php esc_html_e( 'Used as an image layer when this term is selected in a customizer.', 'webcasata-visual-product-builder' ); ?></p>
		</div>
		<div class="form-field wvpb-term-image-field">
			<label><?php esc_html_e( 'Layer Image (SVG)', 'webcasata-visual-product-builder' ); ?></label>
			<?php self::render_image_picker_field( self::META_KEY_SVG, 0 ); ?>
			<p><?php esc_html_e( 'Optional alternate SVG version of the same layer.', 'webcasata-visual-product-builder' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Renders the two image fields on the term Edit screen, preloaded
	 * with whatever is already saved.
	 *
	 * @param WP_Term $term Term being edited.
	 * @return void
	 */
	public static function render_edit_form_fields( $term ) {

		if ( ! self::current_user_can_manage() ) {
			return;
		}

		$png_id = (int) get_term_meta( $term->term_id, self::META_KEY_PNG, true );
		$svg_id = (int) get_term_meta( $term->term_id, self::META_KEY_SVG, true );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<tr class="form-field wvpb-term-image-field">
			<th scope="row"><label><?php esc_html_e( 'Layer Image (PNG)', 'webcasata-visual-product-builder' ); ?></label></th>
			<td>
				<?php self::render_image_picker_field( self::META_KEY_PNG, $png_id ); ?>
				<p class="description"><?php esc_html_e( 'Used as an image layer when this term is selected in a customizer.', 'webcasata-visual-product-builder' ); ?></p>
			</td>
		</tr>
		<tr class="form-field wvpb-term-image-field">
			<th scope="row"><label><?php esc_html_e( 'Layer Image (SVG)', 'webcasata-visual-product-builder' ); ?></label></th>
			<td>
				<?php self::render_image_picker_field( self::META_KEY_SVG, $svg_id ); ?>
				<p class="description"><?php esc_html_e( 'Optional alternate SVG version of the same layer.', 'webcasata-visual-product-builder' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders one media-picker field: hidden attachment-ID input,
	 * thumbnail preview, and Select/Remove buttons. Reuses the same
	 * .wvpb-media-field / .wvpb-media-preview markup and styling the
	 * Base Image field on the Customizer screen already uses.
	 *
	 * @param string $meta_key      Term meta key this field saves to.
	 * @param int    $attachment_id Currently saved attachment ID, or 0.
	 * @return void
	 */
	private static function render_image_picker_field( $meta_key, $attachment_id ) {

		$url      = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';
		$field_id = 'wvpb-term-image-' . sanitize_key( $meta_key );
		?>
		<div class="wvpb-media-field">
			<div class="wvpb-media-preview" <?php echo $url ? '' : 'style="display:none;"'; ?>>
				<img id="<?php echo esc_attr( $field_id ); ?>-preview" src="<?php echo esc_url( $url ); ?>" alt="" />
			</div>
			<input
				type="hidden"
				id="<?php echo esc_attr( $field_id ); ?>"
				name="<?php echo esc_attr( $meta_key ); ?>"
				value="<?php echo esc_attr( $attachment_id ); ?>"
			/>
			<button type="button" class="button wvpb-term-media-upload" data-target="<?php echo esc_attr( $field_id ); ?>">
				<?php esc_html_e( 'Select Image', 'webcasata-visual-product-builder' ); ?>
			</button>
			<button type="button" class="button-link wvpb-term-media-remove" data-target="<?php echo esc_attr( $field_id ); ?>" <?php echo $url ? '' : 'style="display:none;"'; ?>>
				<?php esc_html_e( 'Remove', 'webcasata-visual-product-builder' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Saves both image fields for a term.
	 *
	 * Fires from `created_{$taxonomy}` (the "Add New Tag" screen, which
	 * submits over admin-ajax.php, still carrying our fields since
	 * they're part of the same serialized form) and `edited_{$taxonomy}`
	 * (a normal page submit on the Edit Term screen).
	 *
	 * @param int $term_id Term being saved.
	 * @return void
	 */
	public static function save_term_fields( $term_id ) {

		if (
			! isset( $_POST[ self::NONCE_FIELD ] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ),
				self::NONCE_ACTION
			)
		) {
			return;
		}

		if ( ! self::current_user_can_manage() ) {
			return;
		}

		$png_id = isset( $_POST[ self::META_KEY_PNG ] ) ? absint( $_POST[ self::META_KEY_PNG ] ) : 0;
		$svg_id = isset( $_POST[ self::META_KEY_SVG ] ) ? absint( $_POST[ self::META_KEY_SVG ] ) : 0;

		self::update_or_delete_term_meta( $term_id, self::META_KEY_PNG, $png_id );
		self::update_or_delete_term_meta( $term_id, self::META_KEY_SVG, $svg_id );
	}

	/**
	 * Saves a term meta value, or deletes the key entirely when the
	 * value is empty — keeps termmeta free of stray zero-value rows
	 * for terms that never had an image attached.
	 *
	 * @param int    $term_id Term ID.
	 * @param string $key     Meta key.
	 * @param int    $value   Sanitized attachment ID, or 0.
	 * @return void
	 */
	private static function update_or_delete_term_meta( $term_id, $key, $value ) {
		if ( $value > 0 ) {
			update_term_meta( $term_id, $key, $value );
		} else {
			delete_term_meta( $term_id, $key );
		}
	}

	/**
	 * Builds a flat, JS-friendly list of every WooCommerce attribute
	 * and its terms, each with whatever layer image is resolved for
	 * it (PNG preferred, falling back to SVG). Used by the Customizer
	 * Builder screen to power its "link option to attribute term" mode.
	 *
	 * @return array[] One entry per attribute taxonomy, each with a `terms` array.
	 */
	public static function get_attributes_for_js() {

		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return array();
		}

		$result = array();

		foreach ( wc_get_attribute_taxonomies() as $attribute ) {

			$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );

			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
				)
			);

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}

			$term_list = array();

			foreach ( $terms as $term ) {

				$png_id   = (int) get_term_meta( $term->term_id, self::META_KEY_PNG, true );
				$svg_id   = (int) get_term_meta( $term->term_id, self::META_KEY_SVG, true );
				$image_id = $png_id ? $png_id : $svg_id;

				$term_list[] = array(
					'id'        => $term->term_id,
					'name'      => $term->name,
					'slug'      => $term->slug,
					'image_url' => $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '',
				);
			}

			$result[] = array(
				'taxonomy' => $taxonomy,
				'label'    => $attribute->attribute_label,
				'terms'    => $term_list,
			);
		}

		return $result;
	}

	/**
	 * Capability gate for reading and writing these fields — the same
	 * capability used everywhere else in the plugin, and the one
	 * WooCommerce itself grants to Administrator and Shop Manager.
	 *
	 * @return bool
	 */
	private static function current_user_can_manage() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Enqueues the media-picker JS, only on WooCommerce attribute
	 * term screens — never loaded anywhere else in wp-admin.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {

		if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 0 !== strpos( (string) $screen->taxonomy, 'pa_' ) ) {
			return;
		}

		wp_enqueue_media();

		// Reuses the Customizer builder's stylesheet: it already styles
		// .wvpb-media-field / .wvpb-media-preview, and there's nothing
		// term-screen-specific to add on top of that.
		wp_enqueue_style(
			'wvpb-admin-builder',
			WVPB_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			WVPB_VERSION
		);

		wp_enqueue_script(
			'wvpb-attribute-images',
			WVPB_PLUGIN_URL . 'admin/js/attribute-images.js',
			array( 'jquery' ),
			WVPB_VERSION,
			true
		);

		wp_localize_script(
			'wvpb-attribute-images',
			'wvpbTermImagesL10n',
			array(
				'selectImage'  => __( 'Select or Upload Image', 'webcasata-visual-product-builder' ),
				'useThisImage' => __( 'Use this image', 'webcasata-visual-product-builder' ),
			)
		);
	}
}
