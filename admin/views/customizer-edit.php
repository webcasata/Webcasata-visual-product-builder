<?php
/**
 * Customizer Builder screen.
 *
 * Included from WVPB_Customizer_Builder::render_builder_metabox() on the
 * `wvpb_customizer` CPT edit screen (Stage 2). Expects:
 *   $config (array) — decoded customizer config (post meta '_wvpb_config')
 *
 * All actual repeater rows (steps + their options) are rendered
 * client-side by admin-builder.js from the JSON in data-config,
 * using the two <script type="text/html"> templates below as the
 * single source of markup. This way "existing data on load" and
 * "brand new row on click" go through the exact same code path.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$config = wp_parse_args(
	is_array( $config ) ? $config : array(),
	array(
		'canvas_width'  => 600,
		'canvas_height' => 600,
		'base_image_id' => 0,
		'steps'         => array(),
	)
);

wp_nonce_field( 'wvpb_save_customizer', 'wvpb_customizer_nonce' );
?>

<div id="wvpb-builder" class="wvpb-builder" data-config="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">

	<div class="postbox wvpb-canvas-settings">
		<h2 class="wvpb-section-title"><?php esc_html_e( 'Canvas Settings', 'webcasata-visual-product-builder' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><label for="wvpb-canvas-width"><?php esc_html_e( 'Canvas Width (px)', 'webcasata-visual-product-builder' ); ?></label></th>
				<td><input type="number" id="wvpb-canvas-width" name="wvpb_config[canvas_width]"
					value="<?php echo esc_attr( $config['canvas_width'] ); ?>" min="100" /></td>
			</tr>
			<tr>
				<th><label for="wvpb-canvas-height"><?php esc_html_e( 'Canvas Height (px)', 'webcasata-visual-product-builder' ); ?></label></th>
				<td><input type="number" id="wvpb-canvas-height" name="wvpb_config[canvas_height]"
					value="<?php echo esc_attr( $config['canvas_height'] ); ?>" min="100" /></td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Base Image', 'webcasata-visual-product-builder' ); ?></label></th>
				<td class="wvpb-media-field">
					<?php
					$base_id  = (int) $config['base_image_id'];
					$base_url = ! empty( $config['base_image_url'] ) ? $config['base_image_url'] : '';
					?>
					<div class="wvpb-media-preview" <?php echo $base_url ? '' : 'style="display:none;"'; ?>>
						<img src="<?php echo esc_url( $base_url ); ?>" alt="" />
					</div>
					<input type="hidden" class="wvpb-media-id" name="wvpb_config[base_image_id]" value="<?php echo esc_attr( $base_id ); ?>" />
					<button type="button" class="button wvpb-media-upload"><?php esc_html_e( 'Select Image', 'webcasata-visual-product-builder' ); ?></button>
					<button type="button" class="button-link wvpb-media-remove" <?php echo $base_url ? '' : 'style="display:none;"'; ?>><?php esc_html_e( 'Remove', 'webcasata-visual-product-builder' ); ?></button>
					<p class="description"><?php esc_html_e( 'PNG or SVG. The bottom-most layer every option is composited on top of.', 'webcasata-visual-product-builder' ); ?></p>
				</td>
			</tr>
		</table>
	</div>

	<h2 class="wvpb-section-title"><?php esc_html_e( 'Steps', 'webcasata-visual-product-builder' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Each step is a section in the left column of the customizer popup — e.g. Weight, Layers, Flavour. Drag the handle to reorder.', 'webcasata-visual-product-builder' ); ?>
	</p>

	<div id="wvpb-steps" class="wvpb-steps-list"><!-- rendered by admin-builder.js --></div>

	<button type="button" id="wvpb-add-step" class="button button-secondary">
		<?php esc_html_e( '+ Add Step', 'webcasata-visual-product-builder' ); ?>
	</button>

</div>

<!-- ========================================================= -->
<!-- Underscore.js templates (wp.template) — one Step row       -->
<!-- ========================================================= -->
<script type="text/html" id="tmpl-wvpb-step">
	<div class="wvpb-step" data-step-index="{{ data.stepIndex }}">

		<div class="wvpb-row-handle wvpb-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'webcasata-visual-product-builder' ); ?>">⠿</div>

		<div class="wvpb-step-header">

			<input type="text" class="wvpb-step-title regular-text"
				placeholder="<?php esc_attr_e( 'Step title, e.g. Flavour', 'webcasata-visual-product-builder' ); ?>"
				name="wvpb_config[steps][{{ data.stepIndex }}][title]" value="{{ data.title }}" />

			<select class="wvpb-step-layer-group" name="wvpb_config[steps][{{ data.stepIndex }}][layer_group]">
				<option value="base" <# if ( data.layer_group === 'base' ) { #>selected<# } #>><?php esc_html_e( 'Layer: Base', 'webcasata-visual-product-builder' ); ?></option>
				<option value="structure" <# if ( data.layer_group === 'structure' ) { #>selected<# } #>><?php esc_html_e( 'Layer: Structure', 'webcasata-visual-product-builder' ); ?></option>
				<option value="flavour" <# if ( data.layer_group === 'flavour' ) { #>selected<# } #>><?php esc_html_e( 'Layer: Flavour', 'webcasata-visual-product-builder' ); ?></option>
				<option value="topping" <# if ( data.layer_group === 'topping' ) { #>selected<# } #>><?php esc_html_e( 'Layer: Topping', 'webcasata-visual-product-builder' ); ?></option>
				<option value="decoration" <# if ( data.layer_group === 'decoration' ) { #>selected<# } #>><?php esc_html_e( 'Layer: Decoration', 'webcasata-visual-product-builder' ); ?></option>
			</select>

			<select class="wvpb-step-display-type" name="wvpb_config[steps][{{ data.stepIndex }}][display_type]">
				<option value="radio" <# if ( data.display_type === 'radio' ) { #>selected<# } #>><?php esc_html_e( 'Show as: Radio buttons', 'webcasata-visual-product-builder' ); ?></option>
				<option value="swatch" <# if ( data.display_type === 'swatch' ) { #>selected<# } #>><?php esc_html_e( 'Show as: Image swatches', 'webcasata-visual-product-builder' ); ?></option>
				<option value="dropdown" <# if ( data.display_type === 'dropdown' ) { #>selected<# } #>><?php esc_html_e( 'Show as: Dropdown', 'webcasata-visual-product-builder' ); ?></option>
				<option value="button" <# if ( data.display_type === 'button' ) { #>selected<# } #>><?php esc_html_e( 'Show as: Button group', 'webcasata-visual-product-builder' ); ?></option>
			</select>

			<button type="button" class="button-link wvpb-toggle-step" aria-label="<?php esc_attr_e( 'Collapse step', 'webcasata-visual-product-builder' ); ?>">▾</button>
			<button type="button" class="button-link-delete wvpb-delete-step" aria-label="<?php esc_attr_e( 'Delete step', 'webcasata-visual-product-builder' ); ?>">✕</button>
		</div>

		<div class="wvpb-step-body">
			<div class="wvpb-options-list"><!-- options rendered here --></div>
			<button type="button" class="button wvpb-add-option">
				<?php esc_html_e( '+ Add Option', 'webcasata-visual-product-builder' ); ?>
			</button>
		</div>
	</div>
</script>

<!-- ========================================================= -->
<!-- Underscore.js template — one Option row (nested in a Step)  -->
<!-- ========================================================= -->
<script type="text/html" id="tmpl-wvpb-option">
	<div class="wvpb-option" data-step-index="{{ data.stepIndex }}" data-option-index="{{ data.optionIndex }}">

		<div class="wvpb-row-handle wvpb-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'webcasata-visual-product-builder' ); ?>">⠿</div>

		<select class="wvpb-option-source" name="wvpb_config[steps][{{ data.stepIndex }}][options][{{ data.optionIndex }}][source]">
			<option value="custom" <# if ( 'attribute' !== data.source ) { #>selected<# } #>><?php esc_html_e( 'Custom Image', 'webcasata-visual-product-builder' ); ?></option>
			<option value="attribute" <# if ( 'attribute' === data.source ) { #>selected<# } #>><?php esc_html_e( 'From Attribute Term', 'webcasata-visual-product-builder' ); ?></option>
		</select>

		<div class="wvpb-option-custom-fields" <# if ( 'attribute' === data.source ) { #>style="display:none;"<# } #>>

			<div class="wvpb-option-media" <# if ( data.use_svg_recolor ) { #>style="display:none;"<# } #>>
				<div class="wvpb-media-preview wvpb-png-preview" <# if ( ! data.image_png_url || 'attribute' === data.source ) { #>style="display:none;"<# } #>>
					<img src="{{ data.image_png_url }}" alt="" />
				</div>
				<input type="hidden" class="wvpb-png-id"
					name="wvpb_config[steps][{{ data.stepIndex }}][options][{{ data.optionIndex }}][image_png_id]"
					value="{{ data.image_png_id }}" />
				<button type="button" class="button button-small wvpb-upload-png"><?php esc_html_e( 'Layer (PNG/SVG)', 'webcasata-visual-product-builder' ); ?></button>
			</div>

			<div class="wvpb-option-recolor">
				<label>
					<input type="checkbox" class="wvpb-toggle-recolor" <# if ( data.use_svg_recolor ) { #>checked<# } #> />
					<?php esc_html_e( 'Recolor SVG instead of a layer', 'webcasata-visual-product-builder' ); ?>
				</label>
				<input type="color" class="wvpb-option-fill" <# if ( ! data.use_svg_recolor ) { #>style="display:none;"<# } #>
					name="wvpb_config[steps][{{ data.stepIndex }}][options][{{ data.optionIndex }}][svg_fill_color]"
					value="{{ data.svg_fill_color || '#cccccc' }}" />
				<input type="hidden" class="wvpb-use-recolor"
					name="wvpb_config[steps][{{ data.stepIndex }}][options][{{ data.optionIndex }}][use_svg_recolor]"
					value="{{ data.use_svg_recolor ? 1 : 0 }}" />
			</div>
		</div>

		<div class="wvpb-option-attribute-fields" <# if ( 'attribute' !== data.source ) { #>style="display:none;"<# } #>>
			<select class="wvpb-option-attribute-taxonomy">
				<option value=""><?php esc_html_e( '— choose attribute —', 'webcasata-visual-product-builder' ); ?></option>
			</select>
			<select class="wvpb-option-attribute-term">
				<option value=""><?php esc_html_e( '— choose term —', 'webcasata-visual-product-builder' ); ?></option>
			</select>
			<div class="wvpb-media-preview wvpb-attribute-term-preview" <# if ( ! data.image_png_url || 'attribute' !== data.source ) { #>style="display:none;"<# } #>>
				<img src="{{ data.image_png_url }}" alt="" />
			</div>
			<input type="hidden" class="wvpb-option-attribute-term-id"
				name="wvpb_config[steps][{{ data.stepIndex }}][options][{{ data.optionIndex }}][attribute_term_id]"
				value="{{ data.attribute_term_id }}" />
			<input type="hidden" class="wvpb-option-attribute-taxonomy-hidden"
				name="wvpb_config[steps][{{ data.stepIndex }}][options][{{ data.optionIndex }}][attribute_taxonomy]"
				value="{{ data.attribute_taxonomy }}" />
			<p class="description"><?php esc_html_e( 'Label and image are pulled from the term. Editing the term later updates every customizer using it.', 'webcasata-visual-product-builder' ); ?></p>
		</div>

		<div class="wvpb-option-fields">
			<input type="text" class="wvpb-option-label"
				placeholder="<?php esc_attr_e( 'Label, e.g. Mango', 'webcasata-visual-product-builder' ); ?>"
				name="wvpb_config[steps][{{ data.stepIndex }}][options][{{ data.optionIndex }}][label]" value="{{ data.label }}" />

			<input type="text" class="wvpb-option-value"
				placeholder="<?php esc_attr_e( 'value-slug', 'webcasata-visual-product-builder' ); ?>"
				name="wvpb_config[steps][{{ data.stepIndex }}][options][{{ data.optionIndex }}][value]" value="{{ data.value }}" />

			<input type="number" class="wvpb-option-price" step="0.01"
				placeholder="<?php esc_attr_e( '+/- price', 'webcasata-visual-product-builder' ); ?>"
				name="wvpb_config[steps][{{ data.stepIndex }}][options][{{ data.optionIndex }}][price]" value="{{ data.price }}" />

			<input type="number" class="wvpb-option-zindex"
				placeholder="<?php esc_attr_e( 'z-index', 'webcasata-visual-product-builder' ); ?>"
				name="wvpb_config[steps][{{ data.stepIndex }}][options][{{ data.optionIndex }}][z_index]" value="{{ data.z_index }}" />
		</div>

		<div class="wvpb-option-condition">
			<label>
				<input type="checkbox" class="wvpb-toggle-condition" <# if ( data.conditional_on ) { #>checked<# } #> />
				<?php esc_html_e( 'Only show if…', 'webcasata-visual-product-builder' ); ?>
			</label>
			<div class="wvpb-condition-fields" <# if ( ! data.conditional_on ) { #>style="display:none;"<# } #>>
				<select class="wvpb-condition-step"
					name="wvpb_config[steps][{{ data.stepIndex }}][options][{{ data.optionIndex }}][conditional_on][step]">
					<option value=""><?php esc_html_e( '— choose step —', 'webcasata-visual-product-builder' ); ?></option>
				</select>
				<select class="wvpb-condition-value"
					name="wvpb_config[steps][{{ data.stepIndex }}][options][{{ data.optionIndex }}][conditional_on][value]">
					<option value=""><?php esc_html_e( '— choose value —', 'webcasata-visual-product-builder' ); ?></option>
				</select>
			</div>
		</div>

		<button type="button" class="button-link-delete wvpb-delete-option" aria-label="<?php esc_attr_e( 'Delete option', 'webcasata-visual-product-builder' ); ?>">✕</button>
	</div>
</script>
