<?php
/**
 * Wires customizer-edit.php into a metabox on the wvpb_customizer edit
 * screen, and saves what it submits.
 *
 * @package Webcasata_Visual_Product_Builder
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WVPB_Customizer_Builder.
 */
class WVPB_Customizer_Builder {

	/**
	 * Post meta key the JSON-encoded configuration is stored under.
	 *
	 * @var string
	 */
	const META_KEY = '_wvpb_config';

	/**
	 * Nonce action/field names — must match the wp_nonce_field() call
	 * inside admin/views/customizer-edit.php.
	 *
	 * @var string
	 */
	const NONCE_ACTION = 'wvpb_save_customizer';
	const NONCE_FIELD  = 'wvpb_customizer_nonce';

	/**
	 * Hooks registration into WordPress.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_metabox' ) );
		add_action( 'save_post_' . WVPB_CPT_CUSTOMIZER, array( __CLASS__, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Registers the builder metabox on the Customizer edit screen.
	 *
	 * @return void
	 */
	public static function add_metabox() {
		add_meta_box(
			'wvpb_customizer_builder',
			__( 'Customizer Builder', 'webcasata-visual-product-builder' ),
			array( __CLASS__, 'render' ),
			WVPB_CPT_CUSTOMIZER,
			'normal',
			'high'
		);
	}

	/**
	 * Enqueues the repeater UI's JS/CSS, but only on this post type's
	 * own edit screens — never loaded elsewhere in wp-admin.
	 *
	 * @param string $hook Current admin page hook suffix.
	 * @return void
	 */
	public static function enqueue_assets( $hook ) {

		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		if ( WVPB_CPT_CUSTOMIZER !== get_current_screen()->post_type ) {
			return;
		}

		// wp.media() and wp.template() both come from these.
		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'wp-util' );

		wp_enqueue_style(
			'wvpb-admin-builder',
			WVPB_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			WVPB_VERSION
		);

		wp_enqueue_script(
			'wvpb-admin-builder',
			WVPB_PLUGIN_URL . 'admin/js/admin-builder.js',
			array( 'jquery', 'jquery-ui-sortable', 'wp-util' ),
			WVPB_VERSION,
			true
		);

		wp_localize_script(
			'wvpb-admin-builder',
			'wvpbBuilderL10n',
			array(
				'confirmDeleteStep' => __( 'Delete this step and all its options?', 'webcasata-visual-product-builder' ),
				'chooseStep'        => __( '— choose step —', 'webcasata-visual-product-builder' ),
				'chooseValue'       => __( '— choose value —', 'webcasata-visual-product-builder' ),
				'untitledStep'      => __( '(untitled step)', 'webcasata-visual-product-builder' ),
				'selectImage'       => __( 'Select or Upload Image', 'webcasata-visual-product-builder' ),
				'useThisImage'      => __( 'Use this image', 'webcasata-visual-product-builder' ),
				'chooseAttribute'   => __( '— choose attribute —', 'webcasata-visual-product-builder' ),
				'chooseTerm'        => __( '— choose term —', 'webcasata-visual-product-builder' ),
			)
		);

		// Every WooCommerce attribute + its terms (with resolved layer
		// images), preloaded once so the "link to attribute term"
		// dropdowns work entirely client-side with no extra AJAX calls.
		wp_localize_script(
			'wvpb-admin-builder',
			'wvpbBuilderData',
			array(
				'attributes' => class_exists( 'WVPB_Attribute_Images' ) ? WVPB_Attribute_Images::get_attributes_for_js() : array(),
			)
		);
	}

	/**
	 * Renders the metabox by including the repeater view, with the
	 * current saved configuration decoded and available to it as $config.
	 *
	 * @param WP_Post $post The customizer post being edited.
	 * @return void
	 */
	public static function render( $post ) {
		$config = self::get_enriched_config( $post->ID, 'thumbnail' );
		include WVPB_PLUGIN_DIR . 'admin/views/customizer-edit.php';
	}

	/**
	 * Returns a customizer's configuration, decoded and enriched with
	 * resolved image URLs (base image + every option's image, whether
	 * custom-uploaded or attribute-linked).
	 *
	 * This is the one method both the admin builder screen and the
	 * frontend customizer modal (Stage 5+) should call — image
	 * resolution only needs to exist in one place.
	 *
	 * @param int    $post_id    Customizer post ID.
	 * @param string $image_size Registered image size to resolve URLs
	 *                           at. Admin uses small thumbnails; the
	 *                           frontend preview wants full-size images.
	 * @return array Enriched configuration.
	 */
	public static function get_enriched_config( $post_id, $image_size = 'thumbnail' ) {

		$config = self::get_config( $post_id );
		$config = self::enrich_config_with_image_urls( $config, $image_size );

		$config['base_image_url'] = '';
		if ( ! empty( $config['base_image_id'] ) ) {
			$url = wp_get_attachment_image_url( (int) $config['base_image_id'], $image_size );
			if ( $url ) {
				$config['base_image_url'] = $url;
			}
		}

		return $config;
	}

	/**
	 * Adds an `image_png_url` to every option that has an image (custom
	 * or attribute-linked), so callers have something to render without
	 * needing to know how each option's image is actually sourced. Only
	 * the attachment ID / term ID is ever stored in post meta — the URL
	 * is resolved fresh here every time, so nothing breaks if the media
	 * library URL structure ever changes.
	 *
	 * @param array  $config     Decoded configuration.
	 * @param string $image_size Registered image size to resolve URLs at.
	 * @return array Configuration with image_png_url added where possible.
	 */
	private static function enrich_config_with_image_urls( $config, $image_size = 'thumbnail' ) {

		if ( empty( $config['steps'] ) || ! is_array( $config['steps'] ) ) {
			return $config;
		}

		foreach ( $config['steps'] as &$step ) {

			if ( empty( $step['options'] ) || ! is_array( $step['options'] ) ) {
				continue;
			}

			foreach ( $step['options'] as &$option ) {
				$option['image_png_url'] = '';

				if ( isset( $option['source'] ) && 'attribute' === $option['source'] && ! empty( $option['attribute_term_id'] ) ) {
					$option['image_png_url'] = self::get_attribute_term_image_url( (int) $option['attribute_term_id'], $image_size );
				} elseif ( ! empty( $option['image_png_id'] ) ) {
					$url = wp_get_attachment_image_url( (int) $option['image_png_id'], $image_size );
					if ( $url ) {
						$option['image_png_url'] = $url;
					}
				}
			}
			unset( $option );
		}
		unset( $step );

		return $config;
	}

	/**
	 * Resolves the preview image URL for an attribute-linked option, by
	 * reading the same term meta WVPB_Attribute_Images saves (PNG
	 * preferred, falling back to SVG).
	 *
	 * @param int    $term_id    Attribute term ID.
	 * @param string $image_size Registered image size to resolve URLs at.
	 * @return string Image URL, or '' if the term has no image saved.
	 */
	private static function get_attribute_term_image_url( $term_id, $image_size = 'thumbnail' ) {

		if ( ! class_exists( 'WVPB_Attribute_Images' ) ) {
			return '';
		}

		$png_id   = (int) get_term_meta( $term_id, WVPB_Attribute_Images::META_KEY_PNG, true );
		$svg_id   = (int) get_term_meta( $term_id, WVPB_Attribute_Images::META_KEY_SVG, true );
		$image_id = $png_id ? $png_id : $svg_id;

		if ( ! $image_id ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $image_id, $image_size );

		return $url ? $url : '';
	}

	/**
	 * Reads and decodes a customizer's saved configuration.
	 *
	 * @param int $post_id Customizer post ID.
	 * @return array Decoded configuration, or an empty array if none
	 *               is saved yet or the stored value is corrupt.
	 */
	public static function get_config( $post_id ) {
		$raw    = get_post_meta( $post_id, self::META_KEY, true );
		$config = json_decode( (string) $raw, true );
		return is_array( $config ) ? $config : array();
	}

	/**
	 * Validates, sanitizes, and saves the submitted configuration.
	 *
	 * @param int $post_id Post ID being saved.
	 * @return void
	 */
	public static function save( $post_id ) {

		// Never act on autosaves — there is no repeater data in an
		// autosave request, and saving over real data with nothing
		// would be a silent data-loss bug.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if (
			! isset( $_POST[ self::NONCE_FIELD ] ) ||
			! wp_verify_nonce(
				sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ),
				self::NONCE_ACTION
			)
		) {
			return;
		}

		// Checked against this specific post — required for a meta
		// capability like edit_post to resolve correctly.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['wvpb_config'] ) || ! is_array( $_POST['wvpb_config'] ) ) {
			return;
		}

		// WordPress adds slashes to all of $_POST; strip them once,
		// recursively, before any sanitizing function sees the data.
		$raw_config = wp_unslash( $_POST['wvpb_config'] );

		$sanitized = self::sanitize_config( $raw_config );

		update_post_meta( $post_id, self::META_KEY, wp_json_encode( $sanitized ) );
	}

	/**
	 * Sanitizes the top-level configuration array.
	 *
	 * @param array $raw Raw, already-unslashed configuration.
	 * @return array Sanitized configuration.
	 */
	private static function sanitize_config( $raw ) {

		$raw = is_array( $raw ) ? $raw : array();

		$config = array(
			'canvas_width'  => isset( $raw['canvas_width'] ) ? absint( $raw['canvas_width'] ) : 600,
			'canvas_height' => isset( $raw['canvas_height'] ) ? absint( $raw['canvas_height'] ) : 600,
			'base_image_id' => isset( $raw['base_image_id'] ) ? absint( $raw['base_image_id'] ) : 0,
			'steps'         => array(),
		);

		if ( empty( $raw['steps'] ) || ! is_array( $raw['steps'] ) ) {
			return $config;
		}

		foreach ( array_values( $raw['steps'] ) as $step ) {
			$config['steps'][] = self::sanitize_step( $step );
		}

		return $config;
	}

	/**
	 * Sanitizes a single step and its nested options.
	 *
	 * @param mixed $step Raw step data.
	 * @return array Sanitized step.
	 */
	private static function sanitize_step( $step ) {

		$step = is_array( $step ) ? $step : array();

		$allowed_layer_groups  = array( 'base', 'structure', 'flavour', 'topping', 'decoration' );
		$allowed_display_types = array( 'radio', 'swatch', 'dropdown', 'button' );

		$layer_group = isset( $step['layer_group'] ) ? sanitize_key( $step['layer_group'] ) : 'flavour';
		if ( ! in_array( $layer_group, $allowed_layer_groups, true ) ) {
			$layer_group = 'flavour';
		}

		$display_type = isset( $step['display_type'] ) ? sanitize_key( $step['display_type'] ) : 'swatch';
		if ( ! in_array( $display_type, $allowed_display_types, true ) ) {
			$display_type = 'swatch';
		}

		$sanitized = array(
			'title'        => isset( $step['title'] ) ? sanitize_text_field( $step['title'] ) : '',
			'layer_group'  => $layer_group,
			'display_type' => $display_type,
			'options'      => array(),
		);

		if ( ! empty( $step['options'] ) && is_array( $step['options'] ) ) {
			foreach ( array_values( $step['options'] ) as $option ) {
				$sanitized['options'][] = self::sanitize_option( $option );
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitizes a single option row.
	 *
	 * @param mixed $option Raw option data.
	 * @return array Sanitized option.
	 */
	private static function sanitize_option( $option ) {

		$option = is_array( $option ) ? $option : array();

		$source = isset( $option['source'] ) ? sanitize_key( $option['source'] ) : 'custom';
		if ( ! in_array( $source, array( 'custom', 'attribute' ), true ) ) {
			$source = 'custom';
		}

		$attribute_taxonomy = '';
		$attribute_term_id  = 0;

		if ( 'attribute' === $source ) {
			$attribute_taxonomy = isset( $option['attribute_taxonomy'] ) ? sanitize_key( $option['attribute_taxonomy'] ) : '';
			$attribute_term_id  = isset( $option['attribute_term_id'] ) && '' !== $option['attribute_term_id']
				? absint( $option['attribute_term_id'] )
				: 0;

			// An "attribute" source with no term actually chosen isn't a
			// usable link — fall back to custom rather than save a
			// broken reference that would render as an empty swatch.
			if ( '' === $attribute_taxonomy || 0 === $attribute_term_id ) {
				$source             = 'custom';
				$attribute_taxonomy = '';
				$attribute_term_id  = 0;
			}
		}

		$label = isset( $option['label'] ) ? sanitize_text_field( $option['label'] ) : '';

		$value = isset( $option['value'] ) ? sanitize_title( $option['value'] ) : '';
		if ( '' === $value && '' !== $label ) {
			// The admin JS auto-slugs this, but a submission with the
			// slug field cleared shouldn't silently produce an option
			// with an empty match value — fall back to the label.
			$value = sanitize_title( $label );
		}

		$fill_color = isset( $option['svg_fill_color'] ) ? sanitize_hex_color( $option['svg_fill_color'] ) : '';
		if ( ! $fill_color ) {
			$fill_color = '#cccccc';
		}

		return array(
			'label'              => $label,
			'value'              => $value,
			'price'              => isset( $option['price'] ) ? round( (float) $option['price'], 2 ) : 0.0,
			'z_index'            => isset( $option['z_index'] ) ? absint( $option['z_index'] ) : 10,
			'source'             => $source,
			// A custom image / recolor value is meaningless — and
			// deliberately discarded — once an option is attribute-linked,
			// so the two modes can never disagree about the image source.
			'image_png_id'       => 'custom' === $source && isset( $option['image_png_id'] ) ? absint( $option['image_png_id'] ) : 0,
			'use_svg_recolor'    => 'custom' === $source && ! empty( $option['use_svg_recolor'] ),
			'svg_fill_color'     => $fill_color,
			'attribute_taxonomy' => $attribute_taxonomy,
			'attribute_term_id'  => $attribute_term_id,
			'conditional_on'     => self::sanitize_condition( isset( $option['conditional_on'] ) ? $option['conditional_on'] : null ),
		);
	}

	/**
	 * Sanitizes a conditional-visibility rule.
	 *
	 * A rule is only kept if it names both a step index and a value —
	 * a half-filled rule (e.g. step chosen, value never picked) is
	 * treated as "no condition" rather than saved in a broken state
	 * that could hide an option from every customer.
	 *
	 * @param mixed $condition Raw conditional_on data.
	 * @return array|null Sanitized condition, or null if incomplete/absent.
	 */
	private static function sanitize_condition( $condition ) {

		if ( empty( $condition ) || ! is_array( $condition ) ) {
			return null;
		}

		$step  = isset( $condition['step'] ) && '' !== $condition['step'] ? absint( $condition['step'] ) : null;
		$value = isset( $condition['value'] ) ? sanitize_title( $condition['value'] ) : '';

		if ( null === $step || '' === $value ) {
			return null;
		}

		return array(
			'step'  => $step,
			'value' => $value,
		);
	}
}
