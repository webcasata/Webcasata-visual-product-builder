/**
 * Webcasata Visual Product Builder — Customizer repeater UI.
 *
 * Requires (enqueue these before this file):
 *   - jquery
 *   - jquery-ui-sortable
 *   - wp-util        (provides wp.template)
 *   - media-upload / wp.media (call wp_enqueue_media() on this screen)
 *
 * Everything is rendered from a single JSON blob (the #wvpb-builder
 * data-config attribute) through the two Underscore templates in
 * customizer-edit.php, so "load existing data" and "add a new row"
 * both run through renderStep()/renderOption() — one code path only.
 */
( function ( $, wp ) {
	'use strict';

	var $builder   = $( '#wvpb-builder' );
	var $stepsList = $( '#wvpb-steps' );

	var stepTpl   = wp.template( 'wvpb-step' );
	var optionTpl = wp.template( 'wvpb-option' );

	var stepDefaults = {
		title: '',
		layer_group: 'flavour',
		display_type: 'swatch',
		options: []
	};

	var optionDefaults = {
		label: '',
		value: '',
		price: 0,
		z_index: 10,
		source: 'custom',
		image_png_id: '',
		image_png_url: '',
		use_svg_recolor: false,
		svg_fill_color: '#cccccc',
		attribute_taxonomy: '',
		attribute_term_id: '',
		conditional_on: null
	};

	/* -----------------------------------------------------------
	 * Boot: render whatever was saved, or an empty first step
	 * --------------------------------------------------------- */
	function init() {
		var config = {};
		try {
			config = JSON.parse( $builder.attr( 'data-config' ) || '{}' );
		} catch ( e ) {
			config = {};
		}

		var steps = config.steps && config.steps.length ? config.steps : [];

		steps.forEach( function ( step, stepIndex ) {
			renderStep( step, stepIndex );
		} );

		initializeSavedConditions( steps );

		bindStaticEvents();
	}

	/* -----------------------------------------------------------
	 * Rendering
	 * --------------------------------------------------------- */
	function renderStep( stepData, stepIndex ) {
		var data = $.extend( {}, stepDefaults, stepData, { stepIndex: stepIndex } );
		var $step = $( stepTpl( data ) );

		$stepsList.append( $step );

		var options = stepData && stepData.options ? stepData.options : [];
		var $optionsList = $step.find( '.wvpb-options-list' );

		options.forEach( function ( option, optionIndex ) {
			renderOption( $optionsList, option, stepIndex, optionIndex );
		} );

		makeOptionsSortable( $optionsList );
	}

	function renderOption( $optionsList, optionData, stepIndex, optionIndex ) {
		var data = $.extend( {}, optionDefaults, optionData, {
			stepIndex: stepIndex,
			optionIndex: optionIndex
		} );
		var $option = $( optionTpl( data ) );
		$optionsList.append( $option );

		if ( 'attribute' === data.source ) {
			var $taxonomySelect = $option.find( '.wvpb-option-attribute-taxonomy' );
			populateAttributeDropdown( $taxonomySelect );
			$taxonomySelect.val( data.attribute_taxonomy );
			populateAttributeTermDropdown( $taxonomySelect );
			$option.find( '.wvpb-option-attribute-term' ).val( data.attribute_term_id );
		}
	}

	/* -----------------------------------------------------------
	 * Static, top-level controls
	 * --------------------------------------------------------- */
	function bindStaticEvents() {

		$( '#wvpb-add-step' ).on( 'click', function () {
			var stepIndex = $stepsList.children( '.wvpb-step' ).length;
			renderStep( $.extend( {}, stepDefaults ), stepIndex );
			reindexAll();
		} );

		makeStepsSortable();

		// Base image picker (single, top of screen).
		$( '.wvpb-canvas-settings .wvpb-media-upload' ).on( 'click', function () {
			var $field = $( this ).closest( '.wvpb-media-field' );
			openMediaFrame( function ( attachment ) {
				$field.find( '.wvpb-media-id' ).val( attachment.id );
				$field.find( '.wvpb-media-preview img' ).attr( 'src', attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url );
				$field.find( '.wvpb-media-preview' ).show();
				$field.find( '.wvpb-media-remove' ).show();
			} );
		} );

		$( '.wvpb-canvas-settings .wvpb-media-remove' ).on( 'click', function () {
			var $field = $( this ).closest( '.wvpb-media-field' );
			$field.find( '.wvpb-media-id' ).val( '' );
			$field.find( '.wvpb-media-preview' ).hide();
			$( this ).hide();
		} );

		/* All Step/Option controls are delegated from #wvpb-builder
		 * since rows are created and destroyed constantly. */

		$builder.on( 'click', '.wvpb-add-option', function () {
			var $step = $( this ).closest( '.wvpb-step' );
			var stepIndex = $step.data( 'step-index' );
			var $optionsList = $step.find( '.wvpb-options-list' );
			var optionIndex = $optionsList.children( '.wvpb-option' ).length;

			renderOption( $optionsList, $.extend( {}, optionDefaults ), stepIndex, optionIndex );
			makeOptionsSortable( $optionsList );
			reindexAll();
		} );

		$builder.on( 'click', '.wvpb-delete-step', function () {
			if ( window.confirm( wvpbBuilderL10n.confirmDeleteStep ) ) {
				$( this ).closest( '.wvpb-step' ).remove();
				reindexAll();
			}
		} );

		$builder.on( 'click', '.wvpb-delete-option', function () {
			$( this ).closest( '.wvpb-option' ).remove();
			reindexAll();
		} );

		$builder.on( 'click', '.wvpb-toggle-step', function () {
			var $step = $( this ).closest( '.wvpb-step' );
			$step.find( '.wvpb-step-body' ).slideToggle( 120 );
			$( this ).text( $( this ).text() === '▾' ? '▸' : '▾' );
		} );

		// PNG/SVG layer upload, scoped to the option row it's in.
		$builder.on( 'click', '.wvpb-upload-png', function () {
			var $option = $( this ).closest( '.wvpb-option' );
			openMediaFrame( function ( attachment ) {
				$option.find( '.wvpb-png-id' ).val( attachment.id );
				$option.find( '.wvpb-png-preview img' ).attr( 'src', attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url );
				$option.find( '.wvpb-png-preview' ).show();
			} );
		} );

		// Recolor toggle: swap between "upload a layer" and "pick a fill color".
		$builder.on( 'change', '.wvpb-toggle-recolor', function () {
			var $option = $( this ).closest( '.wvpb-option' );
			var useRecolor = $( this ).is( ':checked' );

			$option.find( '.wvpb-use-recolor' ).val( useRecolor ? 1 : 0 );
			$option.find( '.wvpb-option-media' ).toggle( ! useRecolor );
			$option.find( '.wvpb-option-fill' ).toggle( useRecolor );
		} );

		// Source toggle: swap between a custom-uploaded image and an
		// existing WooCommerce attribute term's image.
		$builder.on( 'change', '.wvpb-option-source', function () {
			var $option = $( this ).closest( '.wvpb-option' );
			var isAttribute = 'attribute' === $( this ).val();

			$option.find( '.wvpb-option-custom-fields' ).toggle( ! isAttribute );
			$option.find( '.wvpb-option-attribute-fields' ).toggle( isAttribute );

			if ( isAttribute ) {
				populateAttributeDropdown( $option.find( '.wvpb-option-attribute-taxonomy' ) );
			}
		} );

		// Populate the Attribute dropdown lazily, the first time it's opened.
		$builder.on( 'mousedown focus', '.wvpb-option-attribute-taxonomy', function () {
			populateAttributeDropdown( $( this ) );
		} );

		$builder.on( 'change', '.wvpb-option-attribute-taxonomy', function () {
			populateAttributeTermDropdown( $( this ) );
		} );

		$builder.on( 'change', '.wvpb-option-attribute-term', function () {
			applyAttributeTermSelection( $( this ) );
		} );

		// Conditional-visibility toggle.
		$builder.on( 'change', '.wvpb-toggle-condition', function () {
			var $conditionBlock = $( this ).closest( '.wvpb-option-condition' );
			var isEnabled = $( this ).is( ':checked' );

			$conditionBlock.find( '.wvpb-condition-enabled' ).val( isEnabled ? 1 : 0 );
			$conditionBlock.find( '.wvpb-condition-fields' ).toggle( isEnabled );
		} );

		// Populate "choose step" dropdown lazily, each time it's opened,
		// since the list of steps can change after this option was built.
		$builder.on( 'mousedown focus', '.wvpb-condition-step', function () {
			populateConditionStepDropdown( $( this ) );
		} );

		// Once a step is chosen, populate that step's option values.
		$builder.on( 'change', '.wvpb-condition-step', function () {
			populateConditionValueDropdown( $( this ) );
		} );

		// Auto-slug: keep the value field in sync with the label until
		// the user edits the slug by hand (tracked via data-autoslug).
		$builder.on( 'keyup', '.wvpb-option-label', function () {
			var $value = $( this ).closest( '.wvpb-option' ).find( '.wvpb-option-value' );
			if ( $value.data( 'autoslug' ) === false ) {
				return;
			}
			$value.val( slugify( $( this ).val() ) );
		} );

		$builder.on( 'input', '.wvpb-option-value', function () {
			$( this ).data( 'autoslug', false );
		} );
	}

	/* -----------------------------------------------------------
	 * Drag reorder
	 * --------------------------------------------------------- */
	function makeStepsSortable() {
		$stepsList.sortable( {
			handle: '> .wvpb-drag-handle',
			axis: 'y',
			update: reindexAll
		} );
	}

	function makeOptionsSortable( $optionsList ) {
		$optionsList.sortable( {
			handle: '> .wvpb-drag-handle',
			axis: 'y',
			update: reindexAll
		} );
	}

	/* -----------------------------------------------------------
	 * Reindexing — after any add / remove / reorder, walk the DOM
	 * and rewrite every field name + data-index so PHP receives a
	 * clean, sequential wvpb_config[steps][i][options][j][...] array.
	 * --------------------------------------------------------- */
	function reindexAll() {
		$stepsList.children( '.wvpb-step' ).each( function ( stepIndex, stepEl ) {
			var $step = $( stepEl );
			$step.attr( 'data-step-index', stepIndex );
			renameFieldsIn( $step, 'steps', stepIndex );

			$step.find( '.wvpb-options-list' ).children( '.wvpb-option' ).each( function ( optionIndex, optionEl ) {
				var $option = $( optionEl );
				$option.attr( 'data-step-index', stepIndex );
				$option.attr( 'data-option-index', optionIndex );
				renameFieldsIn( $option, 'steps', stepIndex );
				renameFieldsIn( $option, 'options', optionIndex );
			} );
		} );
	}

	// Rewrites `wvpb_config[steps][OLD][...]` -> `wvpb_config[steps][NEW][...]`
	// (or the same for `options`) on every [name] inside $scope, but only
	// on fields that belong directly to $scope — not on a nested step's
	// own option fields when $scope is the step wrapper itself, which is
	// why options are re-scoped separately in the loop above.
	function renameFieldsIn( $scope, segment, newIndex ) {
		var re = new RegExp( '(\\[' + segment + '\\]\\[)\\d+(\\])' );
		$scope.find( '[name]' ).addBack( '[name]' ).each( function () {
			var name = $( this ).attr( 'name' );
			if ( name && re.test( name ) ) {
				$( this ).attr( 'name', name.replace( re, '$1' + newIndex + '$2' ) );
			}
		} );
	}

	/* -----------------------------------------------------------
	 * Attribute-linked option helpers
	 * --------------------------------------------------------- */
	function getAttributesData() {
		return ( window.wvpbBuilderData && wvpbBuilderData.attributes ) || [];
	}

	function findAttribute( taxonomy ) {
		var attributes = getAttributesData();
		for ( var i = 0; i < attributes.length; i++ ) {
			if ( attributes[ i ].taxonomy === taxonomy ) {
				return attributes[ i ];
			}
		}
		return null;
	}

	function populateAttributeDropdown( $select ) {
		var current = $select.val();

		$select.empty().append( $( '<option>', { value: '', text: wvpbBuilderL10n.chooseAttribute } ) );

		getAttributesData().forEach( function ( attribute ) {
			$select.append( $( '<option>', { value: attribute.taxonomy, text: attribute.label } ) );
		} );

		$select.val( current );
	}

	function populateAttributeTermDropdown( $taxonomySelect ) {
		var $fields = $taxonomySelect.closest( '.wvpb-option-attribute-fields' );
		var $termSelect = $fields.find( '.wvpb-option-attribute-term' );
		var attribute = findAttribute( $taxonomySelect.val() );
		var current = $termSelect.val();

		$termSelect.empty().append( $( '<option>', { value: '', text: wvpbBuilderL10n.chooseTerm } ) );

		if ( attribute ) {
			attribute.terms.forEach( function ( term ) {
				$termSelect.append( $( '<option>', { value: term.id, text: term.name } ) );
			} );
		}

		$termSelect.val( current );
	}

	// Applies a term choice: fills label/value from the term, updates
	// the read-only preview, and records which taxonomy+term this
	// option now points at. Only runs on an explicit user selection —
	// never during initial render — so a saved custom label an admin
	// typed on purpose is never silently overwritten on page load.
	function applyAttributeTermSelection( $termSelect ) {
		var $optionRow = $termSelect.closest( '.wvpb-option' );
		var $fields = $termSelect.closest( '.wvpb-option-attribute-fields' );
		var taxonomy = $fields.find( '.wvpb-option-attribute-taxonomy' ).val();
		var attribute = findAttribute( taxonomy );
		var termId = $termSelect.val();

		var term = null;
		if ( attribute && termId ) {
			term = attribute.terms.filter( function ( t ) {
				return String( t.id ) === String( termId );
			} )[ 0 ] || null;
		}

		$fields.find( '.wvpb-option-attribute-term-id' ).val( termId );
		$fields.find( '.wvpb-option-attribute-taxonomy-hidden' ).val( taxonomy );

		var $preview = $fields.find( '.wvpb-attribute-term-preview' );

		if ( term ) {
			$optionRow.find( '.wvpb-option-label' ).val( term.name );
			$optionRow.find( '.wvpb-option-value' ).val( term.slug );

			if ( term.image_url ) {
				$preview.find( 'img' ).attr( 'src', term.image_url );
				$preview.show();
			} else {
				$preview.hide();
			}
		} else {
			$preview.hide();
		}
	}

	/* -----------------------------------------------------------
	 * Conditional-logic dropdown helpers
	 * --------------------------------------------------------- */
	// Pre-selects every option's saved "Only show if…" step/value
	// dropdowns. Run once, after every step has already been rendered
	// (not inline during renderOption()) — a condition can reference a
	// step that comes later in the list, which wouldn't exist in the
	// DOM yet if this ran during the same pass that creates it.
	function initializeSavedConditions( steps ) {
		steps.forEach( function ( step, stepIndex ) {
			( step.options || [] ).forEach( function ( option, optionIndex ) {
				if ( ! option.conditional_on ) {
					return;
				}

				var $option = $stepsList.find(
					'.wvpb-option[data-step-index="' + stepIndex + '"][data-option-index="' + optionIndex + '"]'
				);
				var $stepSelect = $option.find( '.wvpb-condition-step' );
				var $valueSelect = $option.find( '.wvpb-condition-value' );

				if ( ! $stepSelect.length ) {
					return;
				}

				populateConditionStepDropdown( $stepSelect );
				$stepSelect.val( String( option.conditional_on.step ) );

				populateConditionValueDropdown( $stepSelect );
				$valueSelect.val( option.conditional_on.value );
			} );
		} );
	}

	function populateConditionStepDropdown( $select ) {
		var $ownOption = $select.closest( '.wvpb-option' );
		var ownStepIndex = $ownOption.data( 'step-index' );
		var current = $select.val();

		$select.empty().append( $( '<option>', { value: '', text: wvpbBuilderL10n.chooseStep } ) );

		$stepsList.children( '.wvpb-step' ).each( function () {
			var $step = $( this );
			var stepIndex = $step.data( 'step-index' );
			if ( stepIndex === ownStepIndex ) {
				return; // can't condition on your own step
			}
			var title = $step.find( '.wvpb-step-title' ).val() || wvpbBuilderL10n.untitledStep;
			$select.append( $( '<option>', { value: stepIndex, text: title } ) );
		} );

		$select.val( current );
	}

	function populateConditionValueDropdown( $stepSelect ) {
		var $fields = $stepSelect.closest( '.wvpb-condition-fields' );
		var $valueSelect = $fields.find( '.wvpb-condition-value' );
		var chosenStepIndex = $stepSelect.val();
		var current = $valueSelect.val();

		$valueSelect.empty().append( $( '<option>', { value: '', text: wvpbBuilderL10n.chooseValue } ) );

		if ( chosenStepIndex === '' ) {
			return;
		}

		var $targetStep = $stepsList.children( '.wvpb-step[data-step-index="' + chosenStepIndex + '"]' );
		$targetStep.find( '.wvpb-options-list > .wvpb-option' ).each( function () {
			var $option = $( this );
			var value = $option.find( '.wvpb-option-value' ).val();
			var label = $option.find( '.wvpb-option-label' ).val() || value;
			if ( value ) {
				$valueSelect.append( $( '<option>', { value: value, text: label } ) );
			}
		} );

		$valueSelect.val( current );
	}

	/* -----------------------------------------------------------
	 * Media uploader
	 * --------------------------------------------------------- */
	function openMediaFrame( onSelect ) {
		var frame = wp.media( {
			title: wvpbBuilderL10n.selectImage,
			library: { type: [ 'image/png', 'image/svg+xml' ] },
			button: { text: wvpbBuilderL10n.useThisImage },
			multiple: false
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			onSelect( attachment );
		} );

		frame.open();
	}

	/* -----------------------------------------------------------
	 * Utilities
	 * --------------------------------------------------------- */
	function slugify( text ) {
		return ( text || '' )
			.toString()
			.toLowerCase()
			.trim()
			.replace( /[^a-z0-9]+/g, '-' )
			.replace( /^-+|-+$/g, '' );
	}

	$( init );

} )( jQuery, wp );

/**
 * wvpbBuilderL10n is expected from wp_localize_script(), e.g.:
 *
 * wp_localize_script( 'wvpb-admin-builder', 'wvpbBuilderL10n', array(
 *     'confirmDeleteStep' => __( 'Delete this step and all its options?', 'webcasata-visual-product-builder' ),
 *     'chooseStep'        => __( '— choose step —', 'webcasata-visual-product-builder' ),
 *     'chooseValue'       => __( '— choose value —', 'webcasata-visual-product-builder' ),
 *     'untitledStep'      => __( '(untitled step)', 'webcasata-visual-product-builder' ),
 *     'selectImage'       => __( 'Select or Upload Image', 'webcasata-visual-product-builder' ),
 *     'useThisImage'      => __( 'Use this image', 'webcasata-visual-product-builder' ),
 * ) );
 */
