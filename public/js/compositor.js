/**
 * Webcasata Visual Product Builder — layer compositor.
 *
 * Given a customizer config and a live `selections` map (stepIndex ->
 * selected option value), renders the base image plus one image layer
 * per step, stacked by each option's z-index, and re-stacks whenever
 * update() is called with new selections.
 *
 * Two image sources per option are supported:
 *   - A plain PNG/SVG file, shown as an absolutely-positioned <img>.
 *   - "Recolor" mode, which changes the fill of the BASE image instead
 *     of adding a layer — this only works when the base image is
 *     itself an SVG, since a bitmap has no fill to change. The SVG is
 *     fetched once and inlined into the page (an <img src="…svg"> is
 *     opaque to JS/CSS; an inlined <svg> is not), then every shape
 *     element inside it has its `fill` attribute overridden directly.
 *     A base SVG authored with fills set via an internal <style> block
 *     rather than plain `fill="…"` attributes won't recolor reliably —
 *     that's a real constraint of this approach, not a bug.
 *
 * Deliberately framework-free beyond jQuery (already a WordPress
 * dependency) and exposed as window.WVPBCompositor, so this file can
 * be reused by an admin-side live preview later without depending on
 * anything specific to the customer-facing modal.
 */
window.WVPBCompositor = ( function ( $ ) {
	'use strict';

	/**
	 * @param {jQuery} $container Element the compositor renders into.
	 * @param {Object} config     Enriched customizer config (steps, base_image_url, canvas_width/height).
	 */
	function Compositor( $container, config ) {
		this.$container = $container;
		this.config = config || {};
		this.layerElements = {}; // stepIndex -> jQuery <img>
		this.baseSvgReady = null; // Promise, only set when the base is a recolorable SVG
		this.$baseSvg = null;

		this._init();
	}

	Compositor.prototype._init = function () {
		this.$container.empty();

		this.$canvas = $( '<div>', { 'class': 'wvpb-compositor-canvas' } )
			.css( 'aspect-ratio', ( this.config.canvas_width || 600 ) + ' / ' + ( this.config.canvas_height || 600 ) )
			.appendTo( this.$container );

		this._setupBase();
	};

	/**
	 * Whether any option anywhere in this config uses recolor mode —
	 * only worth fetching and inlining the base SVG if something
	 * actually needs to recolor it.
	 *
	 * @return {boolean}
	 */
	Compositor.prototype._needsRecolorableBase = function () {
		return ( this.config.steps || [] ).some( function ( step ) {
			return ( step.options || [] ).some( function ( option ) {
				return !! option.use_svg_recolor;
			} );
		} );
	};

	Compositor.prototype._setupBase = function () {
		var self = this;
		var url = this.config.base_image_url || '';
		var looksLikeSvg = /\.svg(\?|$)/i.test( url );

		if ( url && looksLikeSvg && this._needsRecolorableBase() && window.fetch ) {
			this.baseSvgReady = fetch( url )
				.then( function ( response ) {
					if ( ! response.ok ) {
						throw new Error( 'wvpb: base SVG fetch failed' );
					}
					return response.text();
				} )
				.then( function ( svgText ) {
					var doc = new DOMParser().parseFromString( svgText, 'image/svg+xml' );
					var svgEl = doc.documentElement;

					if ( ! svgEl || 'svg' !== svgEl.nodeName.toLowerCase() ) {
						throw new Error( 'wvpb: base file is not a valid SVG' );
					}

					self.$baseSvg = $( svgEl ).addClass( 'wvpb-compositor-base wvpb-recolor-target' );
					self.$canvas.prepend( self.$baseSvg );
				} )
				.catch( function () {
					// Cross-origin host without CORS, malformed SVG, etc.
					// Recoloring won't work, but the base image still shows.
					self._setupBasePlain();
				} );
		} else {
			this._setupBasePlain();
		}
	};

	Compositor.prototype._setupBasePlain = function () {
		if ( this.config.base_image_url ) {
			$( '<img>', {
				'class': 'wvpb-compositor-base',
				src: this.config.base_image_url,
				alt: ''
			} ).prependTo( this.$canvas );
		} else {
			$( '<div>', {
				'class': 'wvpb-modal-preview-placeholder',
				text: ( window.wvpbModalL10n && wvpbModalL10n.previewPlaceholder ) || ''
			} ).appendTo( this.$canvas );
		}
	};

	/**
	 * Re-stacks every step's layer to match the given selections.
	 * Safe to call as often as needed — each call fully reconciles the
	 * visible layers against the current selection, it doesn't assume
	 * anything about what was previously shown.
	 *
	 * @param {Object} selections stepIndex (number) -> selected option value (string)
	 */
	Compositor.prototype.update = function ( selections ) {
		var self = this;
		var steps = this.config.steps || [];

		steps.forEach( function ( step, stepIndex ) {
			var selectedValue = selections[ stepIndex ];
			var option = ( step.options || [] ).filter( function ( candidate ) {
				return candidate.value === selectedValue;
			} )[ 0 ] || null;

			self._updateLayer( stepIndex, option );
			self._updateRecolor( option );
		} );
	};

	Compositor.prototype._updateLayer = function ( stepIndex, option ) {
		var $layer = this.layerElements[ stepIndex ];
		var showImage = !! ( option && option.image_png_url && ! option.use_svg_recolor );

		if ( ! showImage ) {
			if ( $layer ) {
				$layer.hide();
			}
			return;
		}

		if ( ! $layer ) {
			$layer = $( '<img>', { 'class': 'wvpb-compositor-layer', alt: '' } ).appendTo( this.$canvas );
			this.layerElements[ stepIndex ] = $layer;
		}

		$layer
			.attr( 'src', option.image_png_url )
			.css( 'z-index', option.z_index || 10 )
			.show();
	};

	Compositor.prototype._updateRecolor = function ( option ) {
		if ( ! option || ! option.use_svg_recolor || ! this.baseSvgReady ) {
			return;
		}

		var color = option.svg_fill_color || '#cccccc';
		var self = this;

		this.baseSvgReady.then( function () {
			if ( ! self.$baseSvg ) {
				return;
			}
			self.$baseSvg
				.find( 'path, circle, ellipse, rect, polygon, polyline' )
				.attr( 'fill', color );
		} );
	};

	return {
		/**
		 * @param {jQuery} $container
		 * @param {Object} config
		 * @return {Compositor}
		 */
		create: function ( $container, config ) {
			return new Compositor( $container, config );
		}
	};

} )( jQuery );
