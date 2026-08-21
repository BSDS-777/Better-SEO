/**
 * Better SEO — Media Uploader Module
 *
 * Manages the WordPress media library integration for Better SEO social image
 * fields. Handles image selection, cropping, removal, button state management,
 * and image preview/warning tooltip notifications.
 *
 * Exposed as: window.BetterSeoMedia
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - L10n object:    BetterSeoMediaL10n (must be registered in get_media_scripts() in class-loader.php)
 *   - L10n keys:      BetterSeoMediaL10n.{nonce, labels, warning}
 *   - AJAX action:    better_seo_crop_image (wp_ajax_better_seo_crop_image in class-ajax.php:131)
 *   - Crop context:   'better-seo-image' (verified against class-ajax.php context check)
 *   - CSS classes (set by class-form.php):
 *       .better-seo-set-image-button      — image select/change button
 *       .better-seo-remove-image-button   — image remove button (JS-generated)
 *       .better-seo-enable-media-if-js    — hidden inputs enabled when JS is available
 *       .better-seo-image-notifications   — wrapper for preview and warning icons
 *       .better-seo-media-warning         — warning state class on image warning element
 *       .better-seo-media-error           — error state class on image warning element
 *   - Custom events consumed:
 *       'better-seo-onload'               — fired by better-seo.js when admin UI is ready
 *   - Dataset attributes:
 *       data-better-seo-notifications-loaded — tracks whether notifications have been initialised
 *       data-better-seo-current-input-time   — timestamp of the most recent input event
 *
 * @note  Missing PHP: get_media_scripts() in class-loader.php must register BetterSeoMediaL10n
 *        with nonce, labels (per image type), and warning (forbiddenTypes, warnedTypes, i18n).
 */

'use strict';

/**
 * Media uploader and image management module.
 *
 * @namespace BetterSeoMedia
 */
window.BetterSeoMedia = ( function () {

	/**
	 * Localisation data passed from PHP via wp_localize_script().
	 * Must include: nonce, labels (per imageType), warning.{forbiddenTypes, warnedTypes, i18n}
	 *
	 * @type {Object}
	 */
	const l10n = BetterSeoMediaL10n;

	/**
	 * The extended WordPress media Cropper constructor.
	 * Populated by _extendCropper() on first use.
	 *
	 * @type {Object}
	 */
	let Cropper = {};

	// ─── IMAGE EDITOR ──────────────────────────────────────────────────────────

	/**
	 * Opens the WordPress media library frame for image selection and optional cropping.
	 *
	 * Reads crop dimensions and flex settings from the button's data attributes,
	 * then opens a media frame with Library and Cropper states. Dispatches 'change'
	 * events on the URL and ID inputs after selection or crop.
	 *
	 * @param {MouseEvent} event The click event from the set-image button.
	 * @return {void}
	 */
	function _openImageEditor( event ) {

		const button = event.target;

		if ( button.disabled || 'undefined' === typeof wp.media ) {
			event.preventDefault();
			event.stopPropagation();
			return;
		}

		const imageType = button.dataset.inputType ?? '';
		const imageId   = button.dataset.inputId   ?? '';

		event.preventDefault();
		event.stopPropagation();

		_extendCropper();

		const _states = {
			suggestedWidth:  +( button.dataset.width     || 1200 ),
			suggestedHeight: +( button.dataset.height    || 630 ),
			isFlex:          +( button.dataset.flex      || 1 ),
			minWidth:        +( button.dataset.minWidth  || 200 ),
			minHeight:       +( button.dataset.minHeight || 200 ),
		};

		Cropper.control = {
			params: {
				flex_width:  _states.isFlex ? 4096 : 0,
				flex_height: _states.isFlex ? 4096 : 0,
				width:       _states.suggestedWidth,
				height:      _states.suggestedHeight,
				isFlex:      _states.isFlex,
				minWidth:    _states.minWidth,
				minHeight:   _states.minHeight,
			},
		};

		const frame = wp.media( {
			button: {
				text:  l10n.labels[ imageType ].imgFrameButton,
				close: false,
			},
			states: [
				new wp.media.controller.Library( {
					title:           l10n.labels[ imageType ].imgFrameTitle,
					library:         wp.media.query( { 'type': 'image' } ),
					multiple:        false,
					date:            false,
					priority:        20,
					suggestedWidth:  _states.suggestedWidth,
					suggestedHeight: _states.suggestedHeight,
				} ),
				new Cropper( {
					imgSelectOptions: _calculateImageSelectOptions,
				} ),
			],
		} );

		const inputUrl = document.getElementById( `${imageId}-url` );
		const inputId  = document.getElementById( `${imageId}-id` );

		const onSelect = () => frame.setState( 'cropper' );
		frame.off( 'select', onSelect );
		frame.on( 'select', onSelect );

		const onCropped = croppedImage => {
			if ( inputUrl ) {
				inputUrl.value = croppedImage.url;
				inputUrl.dispatchEvent( new Event( 'change' ) );
			}
			if ( inputId ) {
				inputId.value = croppedImage.id;
				inputId.dispatchEvent( new Event( 'change' ) );
			}
		};
		frame.off( 'cropped', onCropped );
		frame.on( 'cropped', onCropped );

		const onSkippedCrop = selection => {
			if ( inputUrl ) {
				inputUrl.value = selection.get( 'url' );
				inputUrl.dispatchEvent( new Event( 'change' ) );
			}
			if ( inputId ) {
				inputId.value = selection.get( 'id' );
				inputId.dispatchEvent( new Event( 'change' ) );
			}
		};
		frame.off( 'skippedcrop', onSkippedCrop );
		frame.on( 'skippedcrop', onSkippedCrop );

		const onDone = () => {
			button.textContent = l10n.labels[ imageType ].imgChange;

			if ( inputUrl ) {
				inputUrl.readOnly = true;
			}

			_appendRemoveButton( button, true );

			if ( 'BetterSeoAys' in window ) {
				BetterSeoAys.registerChange();
			}
		};
		frame.off( 'skippedcrop cropped', onDone );
		frame.on( 'skippedcrop cropped', onDone );

		frame.open();
	}

	/**
	 * Appends a remove button after the given set-image button.
	 *
	 * Skips if a remove button already exists for this image ID.
	 * Optionally fades the button in for a smooth appearance.
	 *
	 * @param {Element} target  The set-image button element.
	 * @param {boolean} animate Whether to fade the button in.
	 * @return {void}
	 */
	function _appendRemoveButton( target, animate ) {

		const inputId   = target.dataset?.inputId;
		const inputType = target.dataset?.inputType;

		if ( ! inputId || ! inputType ) {
			return;
		}

		// Skip if a remove button already exists for this image.
		if ( document.getElementById( `${inputId}-remove` ) ) {
			return;
		}

		const button = document.createElement( 'button' );

		button.type              = 'button';
		button.id                = `${inputId}-remove`;
		button.dataset.inputId   = inputId;
		button.dataset.inputType = inputType;
		button.title             = BetterSeo.decodeEntities( l10n.labels[ inputType ].imgRemoveTitle );
		button.innerHTML         = BetterSeo.escapeString( BetterSeo.decodeEntities( l10n.labels[ inputType ].imgRemove ) );

		button.classList.add(
			'better-seo-remove-image-button',
			...( JSON.parse( target.dataset?.buttonClass || '0' )?.remove || [] ),
		);

		target.insertAdjacentElement( 'afterend', button );

		if ( animate ) {
			// 250ms: double the default so the button doesn't disappear too quickly.
			BetterSeoUI.fadeIn( button, 250 );
		}

		_resetImageEditorRemovalActions();
	}

	/**
	 * Handles the remove-image button click — clears the image URL and ID inputs
	 * and fades out the remove button.
	 *
	 * @param {MouseEvent} event The click event from the remove-image button.
	 * @return {void}
	 */
	function _removeEditorImage( event ) {

		const imageId   = event.target.dataset.inputId;
		const imageType = event.target.dataset.inputType;

		if ( ! imageId || ! imageType ) {
			return;
		}

		const inputSelect = document.getElementById( `${imageId}-select` );

		// Another image remover is probably handling this entry.
		if ( inputSelect.disabled ) {
			return;
		}

		inputSelect.disabled = true;
		inputSelect.classList.add( 'disabled' );

		const inputRemove = document.getElementById( `${imageId}-remove` );
		if ( inputRemove ) {
			inputRemove.disabled = true;
			inputRemove.classList.add( 'disabled' );

			BetterSeoUI.fadeOut(
				inputRemove,
				125,
				() => {
					inputRemove.remove();

					inputSelect.textContent = l10n.labels[ imageType ].imgSelect;
					inputSelect.classList.remove( 'disabled' );
					inputSelect.disabled = false;
				},
			);
		}

		const inputUrl = document.getElementById( `${imageId}-url` );
		if ( inputUrl ) {
			inputUrl.value = '';
			inputUrl.dispatchEvent( new Event( 'change' ) );
			// Honor the data-readonly attribute — don't restore readOnly if it was set externally.
			if ( ! inputUrl.dataset.readonly ) {
				inputUrl.readOnly = false;
			}
		}

		const inputId = document.getElementById( `${imageId}-id` );
		if ( inputId ) {
			inputId.value = '';
			inputId.dispatchEvent( new Event( 'change' ) );
		}

		if ( 'BetterSeoAys' in window ) {
			BetterSeoAys.registerChange();
		}
	}

	// ─── CROPPER EXTENSION ─────────────────────────────────────────────────────

	/**
	 * Extends the WordPress media Cropper with Better SEO-specific crop behaviour.
	 *
	 * Creates TSFView (extended Cropper view with shift-key aspect ratio lock) and
	 * TSFCropper (extended Cropper controller with flex-aware crop dimension calculation
	 * and AJAX crop submission to better_seo_crop_image).
	 *
	 * Only runs once — subsequent calls return immediately if already extended.
	 *
	 * @return {void}
	 */
	function _extendCropper() {

		if ( 'undefined' !== typeof Cropper.control ) {
			return;
		}

		const View = wp.media.view;

		const TSFView = View.Cropper.extend( {
			className:   'crop-content better-seo-image',
			ready:       function () {
				View.Cropper.prototype.ready.apply( this, arguments );
			},
			onImageLoad: function () {
				let imgOptions = this.controller.get( 'imgSelectOptions' );
				let imgSelect;

				if ( typeof imgOptions === 'function' ) {
					imgOptions = imgOptions( this.options.attachment, this.controller );
				}

				if ( 'undefined' === typeof imgOptions.aspectRatio ) {
					imgOptions = Object.assign(
						imgOptions,
						{
							parent: this.$el,
							onInit: function () {
								this.parent.children().on( 'mousedown touchstart', function ( e ) {
									if ( e.shiftKey ) {
										imgSelect.setOptions( { aspectRatio: '1:1' } );
									} else {
										imgSelect.setOptions( { aspectRatio: false } );
									}
								} );
							},
						},
					);
				}

				this.trigger( 'image-loaded' );
				imgSelect = this.controller.imgSelect = this.$image.imgAreaSelect( imgOptions );
			},
		} );

		const TSFCropper = wp.media.controller.Cropper.extend( {
			createCropContent: function () {
				this.cropperView = new TSFView( {
					controller: this,
					attachment: this.get( 'selection' ).first(),
				} );
				this.cropperView.on( 'image-loaded', this.createCropToolbar, this );
				this.frame.content.set( this.cropperView );
			},
			doCrop: function ( attachment ) {
				const cropDetails = attachment.get( 'cropDetails' );
				const control     = Cropper.control;

				if ( control.params.flex_width && control.params.flex_height ) {
					if ( cropDetails.width === cropDetails.height ) {
						// Square — cap at flex width.
						if ( cropDetails.width > control.params.flex_width ) {
							cropDetails.dst_width = cropDetails.dst_height = control.params.flex_width;
						}
					} else {
						// Landscape or portrait — resize to fit within flex dimensions.
						if ( cropDetails.width > control.params.flex_width || cropDetails.height > control.params.flex_height ) {
							if ( cropDetails.width > cropDetails.height ) {
								// Landscape
								const _ratio = cropDetails.width / control.params.flex_width;
								cropDetails.dst_width  = control.params.flex_width;
								cropDetails.dst_height = Math.round( cropDetails.height / _ratio );
							} else {
								// Portrait
								const _ratio = cropDetails.height / control.params.flex_height;
								cropDetails.dst_height = control.params.flex_height;
								cropDetails.dst_width  = Math.round( cropDetails.width / _ratio );
							}
						}
					}
				}

				// No crop dimensions set — let PHP determine the output size.
				if ( 'undefined' === typeof cropDetails.dst_width ) {
					cropDetails.dst_width  = 0;
					cropDetails.dst_height = 0;
				}

				return wp.ajax.post(
					'better_seo_crop_image',
					{
						nonce:       l10n.nonce,
						id:          attachment.get( 'id' ),
						context:     'better-seo-image',
						cropDetails: cropDetails,
					},
				);
			},
		} );

		TSFCropper.prototype.control = {};

		Cropper = TSFCropper;
	}

	// ─── IMAGE SELECT OPTIONS ──────────────────────────────────────────────────

	/**
	 * Calculates the imgAreaSelect options for the crop tool based on the
	 * attachment dimensions and the configured crop parameters.
	 *
	 * @param {Object} attachment  The WordPress media attachment Backbone model.
	 * @param {Object} controller  The WordPress media controller.
	 * @return {Object} The imgAreaSelect options object.
	 */
	function _calculateImageSelectOptions( attachment, controller ) {

		const control = Cropper.control;

		let xInit = parseInt( control.params.width, 10 );
		let yInit = parseInt( control.params.height, 10 );

		const flexWidth  = !! parseInt( control.params.flex_width, 10 );
		const flexHeight = !! parseInt( control.params.flex_height, 10 );

		const realWidth  = attachment.get( 'width' );
		const realHeight = attachment.get( 'height' );
		const ratio      = xInit / yInit;
		const xImg       = xInit;
		const yImg       = yInit;

		let canSkipCrop;
		if ( control.params.isFlex ) {
			canSkipCrop = ! _mustBeCropped( control.params.flex_width, control.params.flex_height, realWidth, realHeight );
		} else {
			canSkipCrop = ratio === realWidth / realHeight;
		}

		controller.set( 'control', control.params );
		controller.set( 'canSkipCrop', canSkipCrop );

		// Correct aspect ratio to fit within the real image dimensions.
		if ( realWidth / realHeight > ratio ) {
			yInit = realHeight;
			xInit = yInit * ratio;
		} else {
			xInit = realWidth;
			yInit = xInit / ratio;
		}

		// Starting selection coordinates (centred within the image).
		const x1 = ( realWidth - xInit ) / 2;
		const y1 = ( realHeight - yInit ) / 2;

		const imgSelectOptions = {
			handles:     true,
			keys:        true,
			instance:    true,
			persistent:  true,
			imageWidth:  realWidth,
			imageHeight: realHeight,
			minWidth:    xImg > xInit ? xInit : xImg,
			minHeight:   yImg > yInit ? yInit : yImg,
			x1:          x1,
			y1:          y1,
			x2:          xInit + x1,
			y2:          yInit + y1,
		};

		if ( ! control.params.isFlex ) {
			imgSelectOptions.handles     = 'corners';
			imgSelectOptions.aspectRatio = `${xInit}:${yInit}`;
		} else if ( ! flexHeight && ! flexWidth ) {
			imgSelectOptions.aspectRatio = `${xInit}:${yInit}`;
		} else {
			if ( flexHeight ) {
				imgSelectOptions.minHeight = control.params.minHeight;
				imgSelectOptions.maxWidth  = realWidth;
			}
			if ( flexWidth ) {
				imgSelectOptions.minWidth  = control.params.minWidth;
				imgSelectOptions.maxHeight = realHeight;
			}
		}

		return imgSelectOptions;
	}

	/**
	 * Returns whether the image must be cropped to fit within the given dimensions.
	 *
	 * @param {number} dstW The maximum destination width.
	 * @param {number} dstH The maximum destination height.
	 * @param {number} imgW The actual image width.
	 * @param {number} imgH The actual image height.
	 * @return {boolean} True if the image exceeds either dimension.
	 */
	function _mustBeCropped( dstW, dstH, imgW, imgH ) {
		return imgW > dstW || imgH > dstH;
	}

	// ─── BUTTON TEXT ───────────────────────────────────────────────────────────

	/**
	 * Updates the set-image button text based on whether the URL input has a value.
	 *
	 * @param {Event} event The 'change' event from the URL input.
	 * @return {void}
	 */
	function _updateButtonText( event ) {

		const imageId   = event.target.dataset.id   ?? '';
		const imageType = event.target.dataset.type ?? '';

		if ( ! imageId || ! imageType ) {
			return;
		}

		const inputSelect = document.getElementById( `${imageId}-select` );

		// The image remover is probably handling this entry.
		if ( inputSelect.disabled ) {
			return;
		}

		inputSelect.textContent = event.target.value.length
			? l10n.labels[ imageType ].imgChange
			: l10n.labels[ imageType ].imgSelect;
	}

	// ─── EDITOR INITIALISATION ─────────────────────────────────────────────────

	/**
	 * Checks all set-image buttons on the page and restores their state
	 * (remove button, readOnly URL input) if an image ID is already set.
	 *
	 * @return {void}
	 */
	function _checkImageEditorInput() {

		for ( const element of document.querySelectorAll( '.better-seo-set-image-button' ) ) {
			const imageId  = element.dataset.inputId ?? '';
			const inputId  = imageId && document.getElementById( `${imageId}-id` );
			const inputUrl = imageId && document.getElementById( `${imageId}-url` );

			if ( inputId && inputId.value > 0 ) {
				if ( inputUrl ) {
					inputUrl.readOnly = true;
				}
				_appendRemoveButton( element, false );
			}

			if ( inputUrl ) {
				inputUrl.addEventListener( 'change', _updateButtonText );
				inputUrl.dispatchEvent( new Event( 'change' ) );
			}
		}
	}

	/**
	 * Attaches click listeners to all set-image buttons.
	 *
	 * @return {void}
	 */
	function _resetImageEditorSetActions() {
		for ( const el of document.querySelectorAll( '.better-seo-set-image-button' ) ) {
			el.addEventListener( 'click', _openImageEditor );
		}
	}

	/**
	 * Attaches click listeners to all remove-image buttons.
	 *
	 * @return {void}
	 */
	function _resetImageEditorRemovalActions() {
		for ( const el of document.querySelectorAll( '.better-seo-remove-image-button' ) ) {
			el.addEventListener( 'click', _removeEditorImage );
		}
	}

	/**
	 * Initialises all image editor actions on the page.
	 *
	 * Enables hidden media inputs, attaches set/remove listeners,
	 * checks existing image state, and prepares notification tooltips.
	 *
	 * @return {void}
	 */
	function _setupImageEditorActions() {

		_resetImageEditorSetActions();
		_resetImageEditorRemovalActions();

		for ( const el of document.querySelectorAll( '.better-seo-enable-media-if-js' ) ) {
			el.disabled = false;
			el.classList.remove( 'better-seo-enable-media-if-js' );
		}

		_checkImageEditorInput();
		_prepareTooltip();
	}

	// ─── IMAGE NOTIFICATIONS ───────────────────────────────────────────────────

	/**
	 * Debounced notification buffer keyed by image ID.
	 * Prevents rapid successive tooltip updates on fast typing.
	 *
	 * @type {Map<string, number>}
	 */
	let _notificationBuffer = new Map();

	/**
	 * Updates the image preview and warning tooltip for the given URL input.
	 *
	 * Loads the image asynchronously to check if it exists, then updates the
	 * preview tooltip with the image or an error message. Also checks the file
	 * extension against forbidden and warned type lists.
	 *
	 * Debounced at ~522ms (1000/(115/60)) — 115 keys/min is considered a slow typer.
	 *
	 * @param {Event} event The 'input' or 'change' event from the URL input.
	 * @return {void}
	 */
	function _updateImageNotifications( event ) {

		const imageId = event.target.id?.replace( /-[a-z]+$/, '' );

		if ( ! imageId ) {
			return;
		}

		const preview      = document.getElementById( `${imageId}-preview` );
		const imageWarning = document.getElementById( `${imageId}-image-warning` );
		const imageType    = document.getElementById( `${imageId}-select` )?.dataset?.inputType;

		if ( _notificationBuffer.has( imageId ) ) {
			clearTimeout( _notificationBuffer.get( imageId ) );
		}

		const firstLoad = ! ( event.target.dataset.betterSeoNotificationsLoaded || false );

		const inputSrc = event.target.value || event.target.placeholder || '';

		const showElement = el => {
			if ( firstLoad ) {
				el.classList.remove( 'hidden' );
				el.style.opacity = '1';
			} else {
				if ( el.classList.contains( 'hidden' ) ) {
					el.classList.remove( 'hidden' );
					BetterSeoUI.fadeIn( el );
				}
			}
			BetterSeoTT.triggerUpdate( el );
		};

		// Hide without fading — fading causes layout shifts when elements are rearranged.
		const hideElement = el => {
			el.classList.add( 'hidden' );
			el.style.opacity = '0';
		};

		const updateToolTip = () => {
			if ( ! inputSrc.length ) {
				[ preview, imageWarning ].forEach( hideElement );
				return;
			}

			const currentUpdateTime = Date.now();
			event.target.dataset.betterSeoCurrentInputTime = currentUpdateTime;

			const imageObject = new Image();

			const writeTooltips = success => {

				// Bail if the input has changed since this update was queued.
				if ( currentUpdateTime !== +event.target.dataset.betterSeoCurrentInputTime ) {
					return;
				}

				let warning     = '';
				let warningType = 'warning';

				if ( preview ) {
					if ( success ) {
						// 225px max: 250px tooltip max width minus 24px padding minus 1px for subpixel rounding.
						imageObject.style    = 'max-width:225px;max-height:225px;min-width:60px;min-height:60px;border-radius:3px;display:block;';
						preview.dataset.desc = imageObject.outerHTML;
						showElement( preview );
					} else {
						warning     = l10n.warning.i18n.notLoaded;
						warningType = 'error';
						hideElement( preview );
					}
				}

				if ( imageWarning ) {
					if ( ! warning.length ) {
						const ext = imageObject.src?.split( '.' ).pop().toLowerCase();

						if ( ext?.length ) {
							if (
								   l10n.warning.forbiddenTypes?.[ imageType ]?.hasOwnProperty( ext )
								|| l10n.warning.forbiddenTypes?.all?.hasOwnProperty( ext )
							) {
								warning     = l10n.warning.i18n.extForbidden.replace( '%s', BetterSeo.escapeString( ext ) );
								warningType = 'error';
							} else if (
								   l10n.warning.warnedTypes?.[ imageType ]?.hasOwnProperty( ext )
								|| l10n.warning.warnedTypes?.all?.hasOwnProperty( ext )
							) {
								warning = l10n.warning.i18n.extWarned.replace( '%s', BetterSeo.escapeString( ext ) );
							}
						}
					}

					if ( warning.length ) {
						imageWarning.classList.toggle( 'better-seo-media-warning', warningType === 'warning' );
						imageWarning.classList.toggle( 'better-seo-media-error',   warningType === 'error' );
						imageWarning.dataset.desc = warning;
						showElement( imageWarning );
					} else {
						hideElement( imageWarning );
					}
				}
			};

			let invokedTooltipUpdate = false;

			imageObject.onload = () => {
				writeTooltips( true );
				invokedTooltipUpdate = true;
			};
			imageObject.onerror = () => {
				writeTooltips( false );
				invokedTooltipUpdate = true;
			};

			// Fallback after 7s for slow connections or very large images.
			setTimeout(
				() => {
					if ( ! invokedTooltipUpdate ) {
						imageObject.src = '';
						writeTooltips( false );
					}
				},
				7000,
			);

			imageObject.src = inputSrc;
		};

		event.target.dataset.betterSeoNotificationsLoaded = true;

		_notificationBuffer.set(
			imageId,
			setTimeout(
				updateToolTip,
				// ~522ms debounce: 1000/(115/60) — 115 keys/min is considered a slow typer.
				firstLoad && 0 ? inputSrc.length : 1000 / ( 115 / 60 ),
			),
		);
	}

	/**
	 * Attaches input and change listeners to all image URL inputs for notification updates.
	 *
	 * @return {void}
	 */
	function _prepareTooltip() {
		for ( const el of document.querySelectorAll( '.better-seo-image-notifications' ) ) {
			const inputUrl = document.getElementById( `${el.dataset.for}-url` );
			if ( ! inputUrl ) {
				continue;
			}

			inputUrl.addEventListener( 'input',  _updateImageNotifications );
			inputUrl.addEventListener( 'change', _updateImageNotifications );

			inputUrl.dispatchEvent( new Event( 'change' ) );
		}
	}

	// ─── PUBLIC API ────────────────────────────────────────────────────────────

	/**
	 * Debounced wrapper for _setupImageEditorActions().
	 * 500ms delay prevents rapid re-initialisation after dynamic content changes.
	 *
	 * @type {Function}
	 */
	const resetImageEditorActions = BetterSeoUtils.debounce( _setupImageEditorActions, 500 );

	return {
		/**
		 * Attaches the media module to the 'better-seo-onload' event.
		 * Called automatically on script load.
		 *
		 * @return {void}
		 */
		load: () => {
			document.body.addEventListener( 'better-seo-onload', _setupImageEditorActions );
		},
		resetImageEditorActions,
		l10n,
	};

}() );

// Auto-initialise — registers the 'better-seo-onload' listener immediately.
window.BetterSeoMedia.load();