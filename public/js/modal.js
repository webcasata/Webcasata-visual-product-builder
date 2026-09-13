/**
 * Webcasata Visual Product Builder — frontend customizer modal.
 *
 * Renders the interactive step/option UI, conditional show/hide logic,
 * and (as of Stage 6) drives compositor.js to keep the right-hand
 * preview in sync with the customer's live selections. The Add to
 * Cart button is intentionally inert until Stage 8 wires up the cart.
 */
( function ( $ ) {
	'use strict';

	var config = ( window.wvpbModalData && wvpbModalData.config ) || {};
	var steps  = config.steps || [];
	var autoOpen = !! ( window.wvpbModalData && wvpbModalData.autoOpen );

	// { stepIndex: selectedOptionValue }
	var selections = {};
	var compositor = null;
	var isMyDesignActive = false;

	var $overlay, $stepsContainer, $preview, $customDesignSection, $enquiryPanel, $addToCartBtn, $submitEnquiryBtn;

	function init() {
		$overlay             = $( '#wvpb-modal-overlay' );
		$stepsContainer      = $( '#wvpb-modal-steps' );
		$preview             = $( '#wvpb-modal-preview' );
		$customDesignSection = $( '#wvpb-custom-design-section' );
		$enquiryPanel        = $( '#wvpb-enquiry-form-panel' );
		$addToCartBtn        = $( '#wvpb-modal-add-to-cart' );
		$submitEnquiryBtn    = $( '#wvpb-modal-submit-enquiry' );

		if ( ! $overlay.length ) {
			return;
		}

		// Both the in-page button and the sticky bar's button share this
		// class — one modal, opened from wherever the customer clicked.
		$( document ).on( 'click', '.wvpb-customize-trigger', openModal );
		$( document ).on( 'click', '#wvpb-modal-close', closeModal );

		// Click on the dimmed backdrop (not the modal itself) closes it.
		$overlay.on( 'click', function ( e ) {
			if ( e.target === this ) {
				closeModal();
			}
		} );

		$( document ).on( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && ! $overlay.prop( 'hidden' ) ) {
				closeModal();
			}
		} );

		$stepsContainer.on( 'change', '.wvpb-step-input', handleInputChange );
		$stepsContainer.on( 'click', '.wvpb-swatch-option, .wvpb-button-option', handleClickSelect );
		$stepsContainer.on( 'click', '.wvpb-step-clear', handleClearStep );

		$submitEnquiryBtn.on( 'click', handleFooterSubmitEnquiryClick );

		initStickyBar();

		// Sent here by a shop/category loop's Customize link — open
		// straight to the customizer instead of making the customer
		// click Customize a second time.
		if ( autoOpen ) {
			openModal();
		}
	}

	/* -----------------------------------------------------------
	 * Sticky bar — shows once the in-page Customize button (wherever
	 * the "Customize button position" setting placed it) scrolls out
	 * of view, hides again once it's back in view. No-ops quietly if
	 * the sticky bar setting is off (its markup won't exist at all) or
	 * the browser lacks IntersectionObserver.
	 * --------------------------------------------------------- */
	function initStickyBar() {
		var $bar = $( '#wvpb-sticky-bar' );
		var trigger = document.getElementById( 'wvpb-open-customizer' );

		if ( ! $bar.length || ! trigger || ! ( 'IntersectionObserver' in window ) ) {
			return;
		}

		var observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				$bar.prop( 'hidden', entry.isIntersecting );
			} );
		}, { threshold: 0 } );

		observer.observe( trigger );
	}

	function openModal() {
		isMyDesignActive = false;

		initPreview();
		renderSteps();
		renderCustomDesignSection();

		$stepsContainer.prop( 'hidden', false );
		$customDesignSection.prop( 'hidden', ! config.allow_custom_design );
		$enquiryPanel.prop( 'hidden', true ).empty();
		$addToCartBtn.prop( 'hidden', false );
		$submitEnquiryBtn.prop( 'hidden', true );

		$overlay.prop( 'hidden', false );
		$( 'body' ).addClass( 'wvpb-modal-open' );
	}

	function closeModal() {
		$overlay.prop( 'hidden', true );
		$( 'body' ).removeClass( 'wvpb-modal-open' );
	}

	/* -----------------------------------------------------------
	 * Right column — live layer compositing (Stage 6). Falls back to
	 * a static base image, or the placeholder text, if compositor.js
	 * failed to load for some reason.
	 * --------------------------------------------------------- */
	function initPreview() {
		if ( window.WVPBCompositor ) {
			compositor = window.WVPBCompositor.create( $preview, config );
		} else {
			compositor = null;
			renderStaticPreviewFallback();
		}
	}

	function renderStaticPreviewFallback() {
		$preview.empty();

		if ( config.base_image_url ) {
			$( '<img>', { src: config.base_image_url, alt: '' } ).appendTo( $preview );
		} else {
			$( '<div>', {
				'class': 'wvpb-modal-preview-placeholder',
				text: wvpbModalL10n.previewPlaceholder
			} ).appendTo( $preview );
		}
	}

	function refreshPreview() {
		if ( compositor ) {
			compositor.update( selections );
		}
	}

	/* -----------------------------------------------------------
	 * Left column — steps & options
	 * --------------------------------------------------------- */
	function renderSteps() {
		$stepsContainer.empty();
		selections = {};

		if ( ! steps.length ) {
			$( '<p>', { text: wvpbModalL10n.noOptions } ).appendTo( $stepsContainer );
			return;
		}

		steps.forEach( function ( step, stepIndex ) {
			$stepsContainer.append( renderStep( step, stepIndex ) );
		} );

		updateConditionalVisibility();
	}

	function renderStep( step, stepIndex ) {
		var $step = $( '<div>', { 'class': 'wvpb-modal-step', 'data-step-index': stepIndex } );

		var $header = $( '<div>', { 'class': 'wvpb-modal-step-header' } );
		var $titleGroup = $( '<div>', { 'class': 'wvpb-modal-step-title-group' } );
		$( '<h3>', { 'class': 'wvpb-modal-step-title', text: step.title || '' } ).appendTo( $titleGroup );
		if ( step.required ) {
			$( '<span>', { 'class': 'wvpb-required-badge', text: wvpbModalL10n.requiredBadge } ).appendTo( $titleGroup );
		}
		$header.append( $titleGroup );
		$( '<button>', {
			type: 'button',
			'class': 'wvpb-step-clear',
			text: wvpbModalL10n.clearSelection
		} ).appendTo( $header );
		$step.append( $header );

		var $optionsWrap = $( '<div>', {
			'class': 'wvpb-modal-options wvpb-display-' + step.display_type
		} );

		if ( 'dropdown' === step.display_type ) {
			$optionsWrap.append( renderDropdown( step ) );
		} else {
			( step.options || [] ).forEach( function ( option, optionIndex ) {
				$optionsWrap.append( renderOptionWrap( step, stepIndex, option, optionIndex ) );
			} );
		}

		$step.append( $optionsWrap );
		return $step;
	}

	function renderDropdown( step ) {
		var $select = $( '<select>', { 'class': 'wvpb-step-input' } );

		$( '<option>', { value: '', text: wvpbModalL10n.chooseOption } ).appendTo( $select );

		( step.options || [] ).forEach( function ( option, optionIndex ) {
			$( '<option>', {
				value: option.value,
				text: optionLabel( option ),
				'data-option-index': optionIndex
			} ).appendTo( $select );
		} );

		return $select;
	}

	function renderOptionWrap( step, stepIndex, option, optionIndex ) {
		var $wrap = $( '<div>', {
			'class': 'wvpb-option-wrap',
			'data-option-index': optionIndex,
			'data-value': option.value
		} );

		if ( 'swatch' === step.display_type ) {
			var $swatch = $( '<button>', {
				type: 'button',
				'class': 'wvpb-swatch-option',
				title: optionLabel( option )
			} );

			if ( option.image_png_url ) {
				$( '<img>', { src: option.image_png_url, alt: '' } ).appendTo( $swatch );
			} else {
				$swatch.text( option.label || option.value );
			}

			$wrap.append( $swatch );
		} else if ( 'button' === step.display_type ) {
			$wrap.append(
				$( '<button>', {
					type: 'button',
					'class': 'wvpb-button-option',
					text: optionLabel( option )
				} )
			);
		} else {
			// radio (default)
			var $label = $( '<label>', { 'class': 'wvpb-radio-option' } );
			$( '<input>', {
				type: 'radio',
				'class': 'wvpb-step-input',
				name: 'wvpb-step-' + stepIndex,
				value: option.value
			} ).appendTo( $label );
			$label.append( $( '<span>' ).text( optionLabel( option ) ) );
			$wrap.append( $label );
		}

		return $wrap;
	}

	function optionLabel( option ) {
		var label = option.label || option.value;

		// Rough placeholder formatting only — real currency-aware
		// pricing and running totals are Stage 7's job.
		if ( option.price ) {
			label += ' (' + ( option.price > 0 ? '+' : '' ) + Number( option.price ).toFixed( 2 ) + ')';
		}

		return label;
	}

	/* -----------------------------------------------------------
	 * Selection handling
	 * --------------------------------------------------------- */
	function handleInputChange( e ) {
		var $input = $( e.currentTarget );
		var stepIndex = $input.closest( '.wvpb-modal-step' ).data( 'step-index' );

		selections[ stepIndex ] = $input.val();
		updateConditionalVisibility();
	}

	function handleClickSelect( e ) {
		var $button = $( e.currentTarget );
		var $wrap = $button.closest( '.wvpb-option-wrap' );
		var $step = $button.closest( '.wvpb-modal-step' );
		var stepIndex = $step.data( 'step-index' );

		$step.find( '.wvpb-swatch-option, .wvpb-button-option' ).removeClass( 'is-selected' );
		$button.addClass( 'is-selected' );

		selections[ stepIndex ] = $wrap.data( 'value' );
		updateConditionalVisibility();
	}

	// Resets a single step back to "nothing selected" — same state the
	// modal opens in — regardless of that step's display type.
	function handleClearStep( e ) {
		var $step = $( e.currentTarget ).closest( '.wvpb-modal-step' );
		var stepIndex = $step.data( 'step-index' );
		var step = steps[ stepIndex ];

		if ( ! step ) {
			return;
		}

		delete selections[ stepIndex ];

		if ( 'dropdown' === step.display_type ) {
			$step.find( 'select.wvpb-step-input' ).val( '' );
		} else if ( 'swatch' === step.display_type || 'button' === step.display_type ) {
			$step.find( '.wvpb-swatch-option, .wvpb-button-option' ).removeClass( 'is-selected' );
		} else {
			$step.find( 'input.wvpb-step-input' ).prop( 'checked', false );
		}

		updateConditionalVisibility();
	}

	/* -----------------------------------------------------------
	 * Conditional visibility
	 * --------------------------------------------------------- */
	function updateConditionalVisibility() {
		$stepsContainer.find( '.wvpb-required-warning' ).remove();

		steps.forEach( function ( step, stepIndex ) {
			var $step = $stepsContainer.find( '.wvpb-modal-step[data-step-index="' + stepIndex + '"]' );
			var visibleValues = [];

			( step.options || [] ).forEach( function ( option, optionIndex ) {
				var visible = isOptionVisible( option );
				if ( visible ) {
					visibleValues.push( option.value );
				}
				setOptionVisibility( $step, step.display_type, optionIndex, visible );
			} );

			// A step with every option conditionally hidden right now
			// has nothing to show — hide the whole step rather than
			// leave an empty titled section sitting in the list.
			$step.toggle( visibleValues.length > 0 );

			// If this step's current selection just got hidden by a
			// change upstream, fall back to the first still-visible
			// option, so a hidden choice never silently stays
			// "selected" underneath what the customer can see. This
			// only applies when the customer had actually chosen
			// something — it must never invent a selection for a step
			// nothing has been picked in yet.
			if (
				undefined !== selections[ stepIndex ] &&
				visibleValues.length &&
				-1 === visibleValues.indexOf( selections[ stepIndex ] )
			) {
				selectOptionByValue( $step, step, stepIndex, visibleValues[ 0 ] );
			}
		} );

		refreshPreview();
	}

	function isOptionVisible( option ) {
		if ( ! option.conditional_on ) {
			return true;
		}
		return selections[ option.conditional_on.step ] === option.conditional_on.value;
	}

	function setOptionVisibility( $step, displayType, optionIndex, visible ) {
		if ( 'dropdown' === displayType ) {
			$step.find( 'option[data-option-index="' + optionIndex + '"]' )
				.prop( 'hidden', ! visible )
				.prop( 'disabled', ! visible );
		} else {
			$step.find( '.wvpb-option-wrap[data-option-index="' + optionIndex + '"]' ).toggle( visible );
		}
	}

	function selectOptionByValue( $step, step, stepIndex, value ) {
		selections[ stepIndex ] = value;

		if ( 'dropdown' === step.display_type ) {
			$step.find( 'select.wvpb-step-input' ).val( value );
		} else if ( 'swatch' === step.display_type || 'button' === step.display_type ) {
			$step.find( '.wvpb-option-wrap' ).each( function () {
				var $wrap = $( this );
				var isMatch = $wrap.data( 'value' ) === value;
				$wrap.find( '.wvpb-swatch-option, .wvpb-button-option' ).toggleClass( 'is-selected', isMatch );
			} );
		} else {
			$step.find( 'input.wvpb-step-input' ).each( function () {
				$( this ).prop( 'checked', $( this ).val() === value );
			} );
		}
	}

	/* -----------------------------------------------------------
	 * "My Design" toggle — lets the customer skip every non-Required
	 * step and upload their own reference image instead. Only shown
	 * at all if this customizer's "Custom Design Option" is enabled
	 * in the admin builder.
	 * --------------------------------------------------------- */
	function renderCustomDesignSection() {
		$customDesignSection.empty().off( 'change' );

		if ( ! config.allow_custom_design ) {
			return;
		}

		var $toggleLabel = $( '<label>', { 'class': 'wvpb-toggle-switch' } );
		var $toggleInput = $( '<input>', { type: 'checkbox', id: 'wvpb-my-design-toggle' } );
		$toggleLabel.append( $toggleInput );
		$toggleLabel.append( $( '<span>', { 'class': 'wvpb-toggle-slider' } ) );
		$toggleLabel.append( document.createTextNode( ' ' + wvpbModalL10n.myDesignLabel ) );
		$customDesignSection.append( $toggleLabel );

		var $upload = $( '<div>', { id: 'wvpb-custom-design-upload', 'class': 'wvpb-custom-design-upload' } );
		$upload.prop( 'hidden', true );
		$( '<label>', { text: wvpbModalL10n.uploadYourDesign, for: 'wvpb-design-image' } ).appendTo( $upload );
		$( '<input>', {
			type: 'file',
			id: 'wvpb-design-image',
			accept: 'image/png,image/jpeg,image/webp,image/gif'
		} ).appendTo( $upload );
		$customDesignSection.append( $upload );

		$toggleInput.on( 'change', handleMyDesignToggle );
	}

	function handleMyDesignToggle( e ) {
		isMyDesignActive = $( e.currentTarget ).is( ':checked' );

		$( '#wvpb-custom-design-upload' ).prop( 'hidden', ! isMyDesignActive );
		$addToCartBtn.prop( 'hidden', isMyDesignActive );
		$submitEnquiryBtn.prop( 'hidden', ! isMyDesignActive );

		if ( isMyDesignActive ) {
			disableNonRequiredSteps();
		} else {
			enableAllSteps();
		}
	}

	// Dims and disables every step that isn't marked Required, and
	// clears any selection it held — a skipped step's prior choice
	// shouldn't still count once the customer says they're providing
	// their own design instead. updateConditionalVisibility() (called
	// at the end) re-composites the preview to match.
	function disableNonRequiredSteps() {
		steps.forEach( function ( step, stepIndex ) {
			if ( step.required ) {
				return;
			}

			var $step = $stepsContainer.find( '.wvpb-modal-step[data-step-index="' + stepIndex + '"]' );
			$step.addClass( 'wvpb-step-disabled' );
			$step.find( 'input, select, button' ).prop( 'disabled', true );

			delete selections[ stepIndex ];
		} );

		updateConditionalVisibility();
	}

	function enableAllSteps() {
		steps.forEach( function ( step, stepIndex ) {
			var $step = $stepsContainer.find( '.wvpb-modal-step[data-step-index="' + stepIndex + '"]' );
			$step.removeClass( 'wvpb-step-disabled' );
			$step.find( 'input, select, button' ).prop( 'disabled', false );
		} );

		updateConditionalVisibility();
	}

	// Steps marked Required apply regardless of My Design — that's the
	// whole point of the flag: a Base selection, say, still has to be
	// made even if the customer is supplying their own design for
	// everything else.
	function getUnmetRequiredSteps() {
		var missing = [];

		steps.forEach( function ( step, stepIndex ) {
			if ( step.required && undefined === selections[ stepIndex ] ) {
				missing.push( step.title || wvpbModalL10n.requiredBadge );
			}
		} );

		return missing;
	}

	function showRequiredWarning() {
		var $existing = $stepsContainer.find( '.wvpb-required-warning' );

		if ( $existing.length ) {
			$existing.text( wvpbModalL10n.pleaseComplete );
		} else {
			$( '<div>', { 'class': 'wvpb-required-warning', text: wvpbModalL10n.pleaseComplete } )
				.prependTo( $stepsContainer );
		}

		$stepsContainer.animate( { scrollTop: 0 }, 150 );
	}

	/* -----------------------------------------------------------
	 * Enquiry form — shown when the footer's Submit Enquiry button is
	 * clicked (only reachable once My Design is on). Swaps the left
	 * column's content rather than opening a second modal.
	 * --------------------------------------------------------- */
	function handleFooterSubmitEnquiryClick() {
		var missing = getUnmetRequiredSteps();

		if ( missing.length ) {
			showRequiredWarning();
			return;
		}

		$stepsContainer.prop( 'hidden', true );
		$customDesignSection.prop( 'hidden', true );
		$addToCartBtn.prop( 'hidden', true );
		$submitEnquiryBtn.prop( 'hidden', true );

		renderEnquiryForm();
		$enquiryPanel.prop( 'hidden', false );
	}

	function backToSteps() {
		$enquiryPanel.prop( 'hidden', true );
		$stepsContainer.prop( 'hidden', false );
		$customDesignSection.prop( 'hidden', false );

		$addToCartBtn.prop( 'hidden', isMyDesignActive );
		$submitEnquiryBtn.prop( 'hidden', ! isMyDesignActive );
	}

	function renderEnquiryForm() {
		$enquiryPanel.empty();

		$( '<button>', { type: 'button', 'class': 'wvpb-enquiry-back', text: wvpbModalL10n.back } )
			.on( 'click', backToSteps )
			.appendTo( $enquiryPanel );

		$( '<h3>', { text: wvpbModalL10n.enquiryTitle } ).appendTo( $enquiryPanel );

		var $form = $( '<form>', { id: 'wvpb-enquiry-form' } );

		$form.append( buildEnquiryField( 'text', 'name', wvpbModalL10n.nameLabel, true ) );
		$form.append( buildEnquiryField( 'email', 'email', wvpbModalL10n.emailLabel, true ) );
		$form.append( buildEnquiryField( 'tel', 'phone', wvpbModalL10n.phoneLabel, false ) );

		var $commentField = $( '<div>', { 'class': 'wvpb-enquiry-field' } );
		$( '<label>', { text: wvpbModalL10n.commentLabel } ).appendTo( $commentField );
		$( '<textarea>', { name: 'comment', rows: 4 } ).appendTo( $commentField );
		$form.append( $commentField );

		// Honeypot: invisible to a real visitor, so a filled value is a
		// strong bot signal. Server-side handling treats a non-empty
		// value as spam and skips real processing without saying so.
		var $honeypot = $( '<div>', { 'class': 'wvpb-enquiry-honeypot', 'aria-hidden': 'true' } );
		$( '<label>', { text: wvpbModalL10n.leaveEmpty } ).appendTo( $honeypot );
		$( '<input>', { type: 'text', name: 'wvpb_hp', tabindex: '-1', autocomplete: 'off' } ).appendTo( $honeypot );
		$form.append( $honeypot );

		$( '<button>', { type: 'submit', 'class': 'button alt', text: wvpbModalL10n.submitEnquiry } ).appendTo( $form );
		$form.append( $( '<div>', { id: 'wvpb-enquiry-result', 'class': 'wvpb-enquiry-result' } ).prop( 'hidden', true ) );

		$form.on( 'submit', handleEnquirySubmit );

		$enquiryPanel.append( $form );
	}

	function buildEnquiryField( type, name, label, required ) {
		var $field = $( '<div>', { 'class': 'wvpb-enquiry-field' } );
		$( '<label>', { text: label } ).appendTo( $field );
		$( '<input>', { type: type, name: name } ).prop( 'required', !! required ).appendTo( $field );
		return $field;
	}

	function handleEnquirySubmit( e ) {
		e.preventDefault();

		var $form = $( this );
		var $submitBtn = $form.find( 'button[type="submit"]' );
		var $result = $form.find( '#wvpb-enquiry-result' );

		var formData = new FormData( this );
		formData.append( 'action', 'wvpb_submit_enquiry' );
		formData.append( 'nonce', wvpbModalData.enquiryNonce );
		formData.append( 'product_id', wvpbModalData.productId );
		formData.append( 'selections', JSON.stringify( selections ) );

		// The design image lives in the upload field shown alongside
		// the My Design toggle, not inside this form — that way a file
		// picked while browsing the steps is still there once the
		// customer reaches this form, instead of asking them to pick
		// it again in a second, empty file input.
		var fileInput = document.getElementById( 'wvpb-design-image' );
		if ( fileInput && fileInput.files && fileInput.files[ 0 ] ) {
			formData.append( 'design_image', fileInput.files[ 0 ] );
		}

		$submitBtn.prop( 'disabled', true ).text( wvpbModalL10n.sending );
		$result.prop( 'hidden', true ).removeClass( 'is-success is-error' );

		fetch( wvpbModalData.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( json ) {
				if ( json && json.success ) {
					$result
						.text( ( json.data && json.data.message ) || wvpbModalL10n.submitEnquiry )
						.addClass( 'is-success' )
						.prop( 'hidden', false );
					$form.find( 'input, textarea, button' ).prop( 'disabled', true );
				} else {
					var message = ( json && json.data && json.data.message ) || wvpbModalL10n.genericError;
					$result.text( message ).addClass( 'is-error' ).prop( 'hidden', false );
					$submitBtn.prop( 'disabled', false ).text( wvpbModalL10n.submitEnquiry );
				}
			} )
			.catch( function () {
				$result.text( wvpbModalL10n.genericError ).addClass( 'is-error' ).prop( 'hidden', false );
				$submitBtn.prop( 'disabled', false ).text( wvpbModalL10n.submitEnquiry );
			} );
	}

	$( init );

} )( jQuery );
