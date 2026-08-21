/**
 * Better SEO — Title Module
 *
 * Manages meta title input behaviour across all Better SEO admin screens.
 * Handles the floating prefix/additions overlay, reference title spans,
 * character/pixel counters, placeholder updates, and state management for
 * multiple independent title input instances.
 *
 * Exposed as: window.BetterSeoTitle
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - L10n object:    BetterSeoTitleL10n (registered in class-loader.php get_title_scripts())
 *   - L10n keys:
 *       BetterSeoTitleL10n.params.untitledTitle    — fallback title for untitled posts
 *       BetterSeoTitleL10n.params.stripTitleTags   — whether to strip HTML tags from titles
 *       BetterSeoTitleL10n.i18n.protectedTitle     — prefix string for password-protected posts
 *       BetterSeoTitleL10n.i18n.privateTitle       — prefix string for private posts
 *       BetterSeoTitleL10n.states.titleSeparator   — current title separator character
 *       BetterSeoTitleL10n.states.prefixPlacement  — 'before' or 'after'
 *   - DOM element IDs managed by this module (set by class-input.php):
 *       better-seo-title-reference_{id}              — live title preview span (with additions)
 *       better-seo-title-noadditions-reference_{id}  — live title preview span (without additions)
 *       better-seo-title-placeholder-prefix_{id}     — floating prefix overlay element
 *       better-seo-title-placeholder-additions_{id}  — floating additions overlay element
 *       better-seo-title-offset_{id}                 — hidden text-width measurement element
 *       {id}_chars                                   — character counter element
 *       {id}_pixels                                  — pixel counter element
 *   - dataset attributes used:
 *       data-better-seo-cor-pad   — corrective padding value stored on hover elements
 *       data-for                  — title input ID stored on hover overlay elements
 *   - Custom events dispatched:
 *       'better-seo-update-title-counter'  — triggers counter-only update on the input element
 *   - Custom events consumed:
 *       'better-seo-onload'                — fires _initAllTitleActions on page load
 *       'better-seo-resize'                — triggers input re-evaluation on window resize
 *       'better-seo-counter-updated'       — triggers enqueueUnregisteredInputTrigger
 *       'input'                            — on title input elements
 *   - Dependencies:
 *       BetterSeo.escapeString(), BetterSeo.decodeEntities()
 *       BetterSeo.coalesceStrlen(), BetterSeo.sDoubleSpace()
 *       BetterSeo.sTabs(), BetterSeo.sSingleLine()
 *       BetterSeoC?.updateCharacterCounter()  — optional counter module
 *       BetterSeoC?.updatePixelCounter()      — optional counter module
 *       BetterSeoAys.areSettingsChanged()     — optional AYS module
 *       BetterSeoAys.reset()                  — optional AYS module
 */

'use strict';

/**
 * Title input manager.
 *
 * @namespace BetterSeoTitle
 */
window.BetterSeoTitle = ( function () {

	/**
	 * Localisation data passed from PHP via wp_localize_script().
	 *
	 * @type {{ params: Object, i18n: Object, states: Object }}
	 */
	const l10n = BetterSeoTitleL10n;

	/** @type {string} Escaped fallback title for untitled posts/terms. */
	const untitledTitle = BetterSeo.escapeString( l10n.params.untitledTitle );

	/** @type {string} Escaped prefix for password-protected content titles. */
	const protectedPrefix = BetterSeo.escapeString( l10n.i18n.protectedTitle );

	/** @type {string} Escaped prefix for private content titles. */
	const privatePrefix = BetterSeo.escapeString( l10n.i18n.privateTitle );

	/** @type {boolean} Whether to strip HTML tags from title values. */
	const stripTitleTags = !! l10n.params.stripTitleTags;

	/**
	 * Map of input element ID → HTMLInputElement.
	 *
	 * @type {Map<string, HTMLInputElement>}
	 */
	const titleInputInstances = new Map();

	/**
	 * Map of input element ID → state object.
	 *
	 * @type {Object.<string, Object>}
	 */
	const states = {};

	/**
	 * Map of input element ID → computed additions string (separator + addition value).
	 *
	 * @type {Map<string, string>}
	 */
	const additionsStack = new Map();

	/**
	 * Map of input element ID → computed prefix string.
	 *
	 * @type {Map<string, string>}
	 */
	const prefixStack = new Map();

	// ─── STACK HELPERS ─────────────────────────────────────────────────────────

	/**
	 * Returns the cached additions string for the given input ID.
	 *
	 * @param {string} id The title input element ID.
	 * @return {string} The additions string, or empty string if not set.
	 */
	function _getAdditionsValue( id ) {
		return additionsStack.get( id ) ?? '';
	}

	/**
	 * Returns the cached prefix string for the given input ID.
	 *
	 * @param {string} id The title input element ID.
	 * @return {string} The prefix string, or empty string if not set.
	 */
	function _getPrefixValue( id ) {
		return prefixStack.get( id ) ?? '';
	}

	/**
	 * Stores the additions string for the given input ID and returns it.
	 *
	 * @param {string} id    The title input element ID.
	 * @param {string} value The additions string to store.
	 * @return {string} The stored additions string.
	 */
	function _setAdditionsValue( id, value ) {
		additionsStack.set( id, value );
		return _getAdditionsValue( id );
	}

	/**
	 * Stores the prefix string for the given input ID and returns it.
	 *
	 * @param {string} id    The title input element ID.
	 * @param {string} value The prefix string to store.
	 * @return {string} The stored prefix string.
	 */
	function _setPrefixValue( id, value ) {
		prefixStack.set( id, value );
		return _getPrefixValue( id );
	}

	// ─── HOVER ELEMENT HELPERS ─────────────────────────────────────────────────

	/**
	 * Returns the floating prefix overlay element for the given input ID.
	 * Falls back to a detached span if the element is not found.
	 *
	 * @param {string} id The title input element ID.
	 * @return {HTMLElement} The prefix overlay element.
	 */
	function _getHoverPrefixElement( id ) {
		return document.getElementById( `better-seo-title-placeholder-prefix_${id}` )
			?? document.createElement( 'span' );
	}

	/**
	 * Returns the floating additions overlay element for the given input ID.
	 * Falls back to a detached span if the element is not found.
	 *
	 * @param {string} id The title input element ID.
	 * @return {HTMLElement} The additions overlay element.
	 */
	function _getHoverAdditionsElement( id ) {
		return document.getElementById( `better-seo-title-placeholder-additions_${id}` )
			?? document.createElement( 'span' );
	}

	// ─── INSTANCE MANAGEMENT ───────────────────────────────────────────────────

	/**
	 * Registers a title input element and initialises its state and event listeners.
	 *
	 * @param {HTMLInputElement} element The title input element to register.
	 * @return {HTMLInputElement} The registered input element.
	 */
	function setInputElement( element ) {
		titleInputInstances.set( element.id, element );
		states[ element.id ] = {
			showPrefix:           true,
			allowReferenceChange: true,
			defaultTitle:         '',
			separator:            l10n.states.titleSeparator,
			prefixPlacement:      l10n.states.prefixPlacement,
		};
		_loadTitleActions( element );
		return getInputElement( element.id );
	}

	/**
	 * Returns the registered title input element for the given ID.
	 *
	 * @param {string} id The title input element ID.
	 * @return {HTMLInputElement|undefined} The input element, or undefined if not registered.
	 */
	function getInputElement( id ) {
		return titleInputInstances.get( id );
	}

	// ─── STATE MANAGEMENT ──────────────────────────────────────────────────────

	/**
	 * Returns the state value for the given input ID and optional part.
	 *
	 * @param {string}           id   The title input element ID.
	 * @param {string|undefined} part The state property to retrieve. Omit to get the full state object.
	 * @return {*} The state value, or the full state object if part is omitted.
	 */
	function getStateOf( id, part ) {
		return part ? states[ id ]?.[ part ] : states[ id ];
	}

	/**
	 * Updates a single state property for the given input ID and triggers side effects.
	 *
	 * No-ops if the value is unchanged.
	 *
	 * @param {string} id    The title input element ID.
	 * @param {string} part  The state property to update.
	 * @param {*}      value The new value.
	 * @return {void}
	 */
	function updateStateOf( id, part, value ) {

		if ( states[ id ][ part ] === value ) {
			return;
		}

		states[ id ][ part ] = value;

		switch ( part ) {
			case 'showPrefix':
			case 'prefixValue':
			case 'prefixPlacement':
				_updatePrefixValue( id );
				enqueueTriggerInput( id );
				break;

			case 'addAdditions':
			case 'separator':
			case 'additionValue':
			case 'additionPlacement':
				_updateAdditionsValue( id );
				enqueueTriggerInput( id );
				break;

			case 'allowReferenceChange':
			case 'defaultTitle':
			default:
				enqueueTriggerInput( id );
				break;
		}
	}

	/**
	 * Updates a state property across all registered title inputs, optionally
	 * excluding one or more inputs by ID.
	 *
	 * @param {string}          part   The state property to update.
	 * @param {*}               value  The new value.
	 * @param {string|string[]} except Input ID(s) to skip. May be a single string or an array.
	 * @return {void}
	 */
	function updateStateAll( part, value, except ) {

		const excluded = Array.isArray( except ) ? except : [ except ];

		for ( const element of titleInputInstances.values() ) {
			if ( excluded.includes( element.id ) ) {
				continue;
			}
			updateStateOf( element.id, part, value );
		}
	}

	// ─── REFERENCE ELEMENTS ────────────────────────────────────────────────────

	/**
	 * Returns the title reference span array for the given input ID.
	 *
	 * @param {string} id The title input element ID.
	 * @return {HTMLElement[]} Array containing the reference span (may contain null).
	 */
	function _getTitleReferences( id ) {
		return [ document.getElementById( `better-seo-title-reference_${id}` ) ];
	}

	/**
	 * Returns the title-without-additions reference span array for the given input ID.
	 *
	 * @param {string} id The title input element ID.
	 * @return {HTMLElement[]} Array containing the no-additions reference span (may contain null).
	 */
	function _getTitleNaReferences( id ) {
		return [ document.getElementById( `better-seo-title-noadditions-reference_${id}` ) ];
	}

	// ─── REFERENCE TITLE UPDATE ────────────────────────────────────────────────

	/**
	 * Updates the title reference spans with the current computed title value.
	 *
	 * Builds the full title string (with prefix and additions) and the no-additions
	 * variant, HTML-escapes both, and sets them as innerHTML on the reference spans.
	 * Dispatches a deferred 'change' event on each span so dependent modules update.
	 *
	 * @param {Event} event The 'input' event from the title input element.
	 * @return {void}
	 */
	function _setReferenceTitle( event ) {

		const references   = _getTitleReferences( event.target.id );
		const referencesNa = _getTitleNaReferences( event.target.id );

		if ( ! references[0] || ! referencesNa[0] ) {
			return;
		}

		const allowReferenceChange = getStateOf( event.target.id, 'allowReferenceChange' );

		let text = BetterSeo.coalesceStrlen( allowReferenceChange && event.target.value.trim() )
			?? BetterSeo.coalesceStrlen( getStateOf( event.target.id, 'defaultTitle' ) )
			?? '';
		let textNa = text;

		if ( text.length && allowReferenceChange ) {
			const prefix    = _getPrefixValue( event.target.id );
			const additions = _getAdditionsValue( event.target.id );

			if ( prefix.length && getStateOf( event.target.id, 'showPrefix' ) ) {
				switch ( getStateOf( event.target.id, 'prefixPlacement' ) ) {
					case 'before':
						text = window.isRtl ? text + prefix : prefix + text;
						break;
					case 'after':
						text = window.isRtl ? prefix + text : text + prefix;
						break;
				}
				textNa = text;
			}

			if ( additions.length ) {
				switch ( getStateOf( event.target.id, 'additionPlacement' ) ) {
					case 'before':
						text = additions + text;
						break;
					case 'after':
						text = text + additions;
						break;
				}
			}
		}

		/**
		 * Normalises and HTML-escapes a title string for safe innerHTML assignment.
		 *
		 * @param {string} str The raw title string.
		 * @return {string} The escaped title string.
		 */
		const normalise = str => BetterSeo.escapeString(
			BetterSeo.decodeEntities(
				BetterSeo.sDoubleSpace(
					BetterSeo.sTabs(
						BetterSeo.sSingleLine( str ),
					).trim(),
				),
			),
		);

		const referenceValue   = normalise( text );
		const referenceNaValue = normalise( textNa );
		const changeEvent      = new Event( 'change' );

		for ( const reference of references ) {
			reference.innerHTML = referenceValue;
			// Deferred dispatch — allows other synchronous listeners to complete first.
			setTimeout( () => { reference.dispatchEvent( changeEvent ); }, 0 );
		}

		for ( const referenceNa of referencesNa ) {
			referenceNa.innerHTML = referenceNaValue;
			setTimeout( () => { referenceNa.dispatchEvent( changeEvent ); }, 0 );
		}
	}

	// ─── ADDITIONS & PREFIX VALUE UPDATES ──────────────────────────────────────

	/**
	 * Recomputes the additions string from the current state and updates the
	 * additions overlay element's innerHTML.
	 *
	 * @param {string} id The title input element ID.
	 * @return {void}
	 */
	function _updateAdditionsValue( id ) {
		let value          = '';
		let additionsValue = '';
		let separator      = '';

		if ( getStateOf( id, 'addAdditions' ) ) {
			additionsValue = BetterSeo.escapeString( BetterSeo.decodeEntities( getStateOf( id, 'additionValue' ) ) );
			separator      = getStateOf( id, 'separator' );
		}

		if ( additionsValue ) {
			switch ( getStateOf( id, 'additionPlacement' ) ) {
				case 'before':
					value = `${additionsValue} ${separator} `;
					break;
				case 'after':
					value = ` ${separator} ${additionsValue}`;
					break;
			}
		}

		_getHoverAdditionsElement( id ).innerHTML = _setAdditionsValue( id, value || '' );
	}

	/**
	 * Recomputes the prefix string from the current state and updates the
	 * prefix overlay element's innerHTML.
	 *
	 * @param {string} id The title input element ID.
	 * @return {void}
	 */
	function _updatePrefixValue( id ) {
		let value       = '';
		const showPrefix  = getStateOf( id, 'showPrefix' );
		const prefixValue = getStateOf( id, 'prefixValue' );

		if ( showPrefix && prefixValue ) {
			switch ( getStateOf( id, 'prefixPlacement' ) ) {
				case 'before':
					value = window.isRtl ? ` ${prefixValue}` : `${prefixValue} `;
					break;
				case 'after':
					value = window.isRtl ? `${prefixValue} ` : ` ${prefixValue}`;
					break;
			}
		}

		_getHoverPrefixElement( id ).innerHTML = _setPrefixValue( id, value || '' );
	}

	// ─── HOVER PLACEMENT ───────────────────────────────────────────────────────

	/**
	 * Repositions and resizes the floating prefix and additions overlay elements
	 * to align with the current input value text width.
	 *
	 * Reads computed styles from the input to match font metrics, measures text
	 * width via a hidden offset element, and applies maxWidth/offset/visibility
	 * styles to the overlay elements. Also sets textIndent on the input to push
	 * the cursor past the prefix/additions overlays.
	 *
	 * @param {Event} event The 'input' event from the title input element.
	 * @return {void}
	 */
	function _updateHoverPlacement( event ) {

		const hoverAdditionsElement = _getHoverAdditionsElement( event.target.id );
		const hoverPrefixElement    = _getHoverPrefixElement( event.target.id );

		if ( ! hoverAdditionsElement && ! hoverPrefixElement ) {
			return;
		}

		const input      = event.target;
		const inputValue = event.target.value;

		const hasPrefixValue    = !! ( _getPrefixValue( event.target.id ).length && getStateOf( event.target.id, 'showPrefix' ) );
		const hasAdditionsValue = !! _getAdditionsValue( event.target.id ).length;

		if ( ! hasPrefixValue && hoverPrefixElement ) {
			hoverPrefixElement.style.display = 'none';
		}
		if ( ! hasAdditionsValue && hoverAdditionsElement ) {
			hoverAdditionsElement.style.display = 'none';
		}

		if ( ! hasPrefixValue && ! hasAdditionsValue ) {
			input.style.textIndent = 'initial';
			return;
		}

		if ( ! inputValue.length ) {
			input.style.textIndent = 'initial';
			if ( hoverPrefixElement ) {
				hoverPrefixElement.style.display = 'none';
			}
			if ( hoverAdditionsElement ) {
				hoverAdditionsElement.style.display = 'none';
			}
			return;
		}

		const inputStyles = getComputedStyle( input );
		const inputRect   = input.getBoundingClientRect();

		const paddingRight = parseFloat( inputStyles.paddingRight );
		const paddingLeft  = parseFloat( inputStyles.paddingLeft );
		const borderRight  = parseFloat( inputStyles.borderRightWidth );
		const borderLeft   = parseFloat( inputStyles.borderLeftWidth );
		const marginRight  = parseFloat( inputStyles.marginRight );
		const marginLeft   = parseFloat( inputStyles.marginLeft );

		const offsetPosition = window.isRtl ? 'right' : 'left';
		const corPaddingProp = window.isRtl ? 'paddingLeft' : 'paddingRight';
		const leftOffset     = paddingLeft + borderLeft + marginLeft;
		const rightOffset    = paddingRight + borderRight + marginRight;

		const fontStyleCSS = new Map( [
			[ 'border', '0 solid transparent' ],
		] );

		for ( const type of [
			'display',
			'lineHeight',
			'fontFamily',
			'fontWeight',
			'fontSize',
			'letterSpacing',
			'marginTop',
			'marginBottom',
			'paddingTop',
			'paddingBottom',
			'borderTopWidth',
			'borderBottomWidth',
			'verticalAlign',
			'boxSizing',
			'textTransform',
		] ) {
			fontStyleCSS.set( type, inputStyles?.[ type ] || '' );
		}

		const offsetElement = document.getElementById( `better-seo-title-offset_${event.target.id}` );
		offsetElement.textContent = inputValue;
		Object.assign(
			offsetElement.style,
			{
				fontFamily:    fontStyleCSS.get( 'fontFamily' )    || '',
				fontWeight:    fontStyleCSS.get( 'fontWeight' )    || '',
				fontSize:      fontStyleCSS.get( 'fontSize' )      || '',
				letterSpacing: fontStyleCSS.get( 'letterSpacing' ) || '',
				textTransform: fontStyleCSS.get( 'textTransform' ) || '',
			},
		);
		const textWidth = offsetElement.getBoundingClientRect().width;

		const oneCh              = parseFloat( fontStyleCSS.get( 'fontSize' ) ) || 0;
		const overflowCorrection = oneCh * 0.33;

		let additionsMaxWidth = 0;
		let additionsOffset   = 0;
		let additionsCorPad   = 0;
		let prefixMaxWidth    = 0;
		let prefixOffset      = 0;
		let prefixCorPad      = 0;
		let totalIndent       = 0;

		let prefixWidth    = 0;
		let additionsWidth = 0;

		// Additions collapse before the prefix — calculate prefix width first.
		if ( hasPrefixValue ) {
			Object.assign(
				hoverPrefixElement.style,
				Object.fromEntries( fontStyleCSS.entries() ),
				{ maxWidth: 'initial' },
			);
			prefixWidth = hoverPrefixElement.getBoundingClientRect().width
				- ( +hoverPrefixElement.dataset.betterSeoCorPad || 0 );

			prefixMaxWidth  = prefixWidth;
			prefixOffset   += leftOffset;
		}

		if ( hasAdditionsValue ) {
			Object.assign(
				hoverAdditionsElement.style,
				Object.fromEntries( fontStyleCSS.entries() ),
				{ maxWidth: 'initial' },
			);
			additionsWidth = hoverAdditionsElement.getBoundingClientRect().width
				- ( +hoverAdditionsElement.dataset.betterSeoCorPad || 0 );

			switch ( getStateOf( event.target.id, 'additionPlacement' ) ) {
				case 'before':
					additionsMaxWidth = inputRect.width - rightOffset - paddingLeft - borderLeft - textWidth - prefixMaxWidth;
					additionsMaxWidth = Math.max( 0, Math.min( additionsMaxWidth, additionsWidth ) );
					additionsCorPad   = additionsMaxWidth < additionsWidth ? overflowCorrection : 0;

					totalIndent     += additionsMaxWidth;
					prefixOffset    += additionsMaxWidth;
					additionsOffset += leftOffset;
					break;

				case 'after':
					additionsMaxWidth = inputRect.width - leftOffset - paddingRight - borderRight - textWidth - prefixMaxWidth;
					additionsMaxWidth = Math.max( 0, Math.min( additionsMaxWidth, additionsWidth ) );
					additionsOffset  += leftOffset + textWidth + prefixMaxWidth;
					break;
			}
		}

		if ( hasPrefixValue ) {
			if ( ! additionsMaxWidth || ! hasAdditionsValue ) {
				// Collapse prefix when additions are absent or zero-width.
				prefixMaxWidth = inputRect.width - leftOffset - paddingRight - borderRight - textWidth;
				prefixMaxWidth = Math.max( 0, Math.min( prefixMaxWidth, prefixWidth ) );
				prefixCorPad   = additionsMaxWidth < additionsWidth ? overflowCorrection : 0;
			}

			totalIndent += prefixMaxWidth;

			Object.assign(
				hoverPrefixElement.style,
				{
					[ offsetPosition ]: `${prefixOffset}px`,
					maxWidth:           `${prefixMaxWidth}px`,
					[ corPaddingProp ]: `${prefixCorPad}px`,
					visibility:          prefixMaxWidth < oneCh ? 'hidden' : 'visible',
				},
			);
			hoverPrefixElement.dataset.betterSeoCorPad = prefixCorPad;
		}

		if ( hasAdditionsValue ) {
			Object.assign(
				hoverAdditionsElement.style,
				{
					[ offsetPosition ]: `${additionsOffset}px`,
					maxWidth:           `${additionsMaxWidth}px`,
					[ corPaddingProp ]: `${additionsCorPad}px`,
					visibility:          additionsMaxWidth < oneCh ? 'hidden' : 'visible',
				},
			);
			hoverAdditionsElement.dataset.betterSeoCorPad = additionsCorPad;
		}

		input.style.textIndent = `${totalIndent}px`;
	}

	// ─── PLACEHOLDER & COUNTER UPDATES ─────────────────────────────────────────

	/**
	 * Updates the title input's placeholder text from the reference span's textContent.
	 *
	 * @param {Event} event The 'input' event from the title input element.
	 * @return {void}
	 */
	function _updatePlaceholder( event ) {
		event.target.placeholder = _getTitleReferences( event.target.id )[0]?.textContent ?? '';
	}

	/**
	 * Updates the character counter for the title input.
	 *
	 * @param {Event} event The 'input' or counter event from the title input element.
	 * @return {void}
	 */
	function _updateCounter( event ) {

		const counter   = document.getElementById( `${event.target.id}_chars` );
		const reference = _getTitleReferences( event.target.id )[0];

		if ( ! counter ) {
			return;
		}

		BetterSeoC?.updateCharacterCounter( {
			e:     counter,
			text:  reference.innerHTML,
			field: 'title',
			type:  'search',
		} );
	}

	/**
	 * Updates the pixel counter for the title input.
	 *
	 * @param {Event} event The 'input' or counter event from the title input element.
	 * @return {void}
	 */
	function _updatePixels( event ) {

		const pixels    = document.getElementById( `${event.target.id}_pixels` );
		const reference = _getTitleReferences( event.target.id )[0];

		if ( ! pixels ) {
			return;
		}

		BetterSeoC?.updatePixelCounter( {
			e:     pixels,
			text:  reference.innerHTML,
			field: 'title',
			type:  'search',
		} );
	}

	// ─── INPUT TRIGGERS ────────────────────────────────────────────────────────

	/**
	 * Dispatches an 'input' event on the title input element for the given ID,
	 * or on all registered inputs if no ID is provided.
	 *
	 * @param {string|undefined} id The title input element ID, or undefined for all.
	 * @return {void}
	 */
	function triggerInput( id ) {
		if ( id ) {
			getInputElement( id )?.dispatchEvent( new Event( 'input' ) );
		} else {
			for ( const element of titleInputInstances.values() ) {
				if ( element.id ) {
					triggerInput( element.id );
				}
			}
		}
	}

	/**
	 * Dispatches a 'better-seo-update-title-counter' event on the title input element
	 * for the given ID, or on all registered inputs if no ID is provided.
	 *
	 * @param {string|undefined} id The title input element ID, or undefined for all.
	 * @return {void}
	 */
	function triggerCounter( id ) {
		if ( id ) {
			getInputElement( id )?.dispatchEvent( new CustomEvent( 'better-seo-update-title-counter' ) );
		} else {
			for ( const element of titleInputInstances.values() ) {
				if ( element.id ) {
					triggerCounter( element.id );
				}
			}
		}
	}

	/**
	 * Handles the full title update sequence: hover placement, reference title,
	 * placeholder, and counters.
	 *
	 * @param {Event} event The 'input' event from the title input element.
	 * @return {void}
	 */
	function _onUpdateTitlesTrigger( event ) {
		_updateHoverPlacement( event );
		_setReferenceTitle( event );
		_updatePlaceholder( event );
		_onUpdateCounterTrigger( event );
	}

	/**
	 * Handles the counter-only update sequence: character counter and pixel counter.
	 *
	 * @param {Event} event The counter event from the title input element.
	 * @return {void}
	 */
	function _onUpdateCounterTrigger( event ) {
		_updateCounter( event );
		_updatePixels( event );
	}

	/** @type {Object.<string, number>} Pending setTimeout IDs for enqueueTriggerInput. */
	let _enqueueTriggerInputBuffer = {};

	/**
	 * Debounces a triggerInput call for the given ID at ~60 fps.
	 *
	 * @param {string} id The title input element ID.
	 * @return {void}
	 */
	function enqueueTriggerInput( id ) {
		if ( id in _enqueueTriggerInputBuffer ) {
			clearTimeout( _enqueueTriggerInputBuffer[ id ] );
		}
		_enqueueTriggerInputBuffer[ id ] = setTimeout( () => triggerInput( id ), 1000 / 60 );
	}

	/**
	 * Triggers an input event without registering a settings change in BetterSeoAys.
	 *
	 * If BetterSeoAys is present and no change was previously registered, resets
	 * the AYS state after triggering to avoid false "unsaved changes" warnings.
	 *
	 * @param {string|undefined} id The title input element ID, or undefined for all.
	 * @return {void}
	 */
	function triggerUnregisteredInput( id ) {
		if ( 'BetterSeoAys' in window ) {
			const wereSettingsChanged = BetterSeoAys.areSettingsChanged();
			triggerInput( id );
			if ( ! wereSettingsChanged && BetterSeoAys.areSettingsChanged() ) {
				BetterSeoAys.reset();
			}
		} else {
			triggerInput( id );
		}
	}

	/** @type {Object.<string, number>} Pending setTimeout IDs for enqueueUnregisteredInputTrigger. */
	let _unregisteredTriggerBuffer = {};

	/**
	 * Debounces a triggerUnregisteredInput call for the given ID at ~60 fps.
	 *
	 * @param {string|undefined} id The title input element ID, or undefined for all.
	 * @return {void}
	 */
	function enqueueUnregisteredInputTrigger( id ) {
		if ( id in _unregisteredTriggerBuffer ) {
			clearTimeout( _unregisteredTriggerBuffer[ id ] );
		}
		_unregisteredTriggerBuffer[ id ] = setTimeout( () => triggerUnregisteredInput( id ), 1000 / 60 );
	}

	// ─── HOVER FOCUS HANDLER ───────────────────────────────────────────────────

	/**
	 * Focuses the associated title input when a hover overlay element is clicked,
	 * and sets the cursor position based on click count and overlay type.
	 *
	 * - Single click: moves cursor to start (prefix) or end (additions after).
	 * - Double click: selects the first or last word.
	 * - Triple click: selects all text.
	 *
	 * @param {MouseEvent} event The click event on the hover overlay element.
	 * @return {void}
	 */
	function _focusTitleInput( event ) {

		const input = document.getElementById( event.target.dataset.for );

		if ( ! input ) {
			return;
		}

		const type       = event.target.classList.contains( 'better-seo-title-placeholder-additions' ) ? 'additions' : 'prefix';
		const inputValue = input.value;

		input.focus();

		switch ( event.detail ) {
			case 3:
				input.setSelectionRange( 0, inputValue.length );
				break;

			case 2: {
				let start, end;
				if (
					   ( 'additions' === type && 'after' === getStateOf( input.id, 'additionPlacement' ) )
					|| ( 'prefix' === type && window.isRtl )
				) {
					start = inputValue.replace( /(\w+|\s+)$/u, '' ).length;
					end   = inputValue.length;
				} else {
					start = 0;
					end   = inputValue.length - inputValue.replace( /^(\s+|\w+)/u, '' ).length;
				}
				input.setSelectionRange( start, end );
				break;
			}

			case 1:
			default: {
				const length = ( 'additions' === type && 'after' === getStateOf( input.id, 'additionPlacement' ) )
					? inputValue.length
					: 0;
				input.setSelectionRange( length, length );
				break;
			}
		}
	}

	/**
	 * Prevents the default mousedown behaviour on hover overlay elements to avoid
	 * stealing focus from the title input.
	 *
	 * @param {MouseEvent} event The mousedown event.
	 * @return {void}
	 */
	function _preventFocus( event ) {
		event.preventDefault();
	}

	// ─── RESIZE HANDLER ────────────────────────────────────────────────────────

	/**
	 * Re-triggers all title inputs on window resize to recalculate hover placement.
	 *
	 * @return {void}
	 */
	function _doResize() {
		triggerUnregisteredInput();
	}

	// ─── GLOBAL INIT ───────────────────────────────────────────────────────────

	/**
	 * Registers global event listeners shared across all title input instances.
	 * Called once on 'better-seo-onload'.
	 *
	 * @return {void}
	 */
	function _initAllTitleActions() {
		window.addEventListener( 'better-seo-resize', _doResize );
		window.addEventListener( 'better-seo-counter-updated', () => enqueueUnregisteredInputTrigger() );
	}

	// ─── PER-INSTANCE INIT ─────────────────────────────────────────────────────

	/**
	 * Attaches all event listeners for a single title input instance.
	 *
	 * @param {HTMLInputElement} titleInput The title input element.
	 * @return {void}
	 */
	function _loadTitleActions( titleInput ) {

		if ( ! ( titleInput instanceof Element ) ) {
			return;
		}

		titleInput.addEventListener( 'input', _onUpdateTitlesTrigger );
		titleInput.addEventListener( 'better-seo-update-title-counter', _onUpdateCounterTrigger );

		const hoverPrefix    = _getHoverPrefixElement( titleInput.id );
		const hoverAdditions = _getHoverAdditionsElement( titleInput.id );

		hoverPrefix.addEventListener( 'click', _focusTitleInput );
		hoverAdditions.addEventListener( 'click', _focusTitleInput );

		// Prevent focus theft from the floating overlay elements.
		hoverPrefix.addEventListener( 'mousedown', _preventFocus );
		hoverAdditions.addEventListener( 'mousedown', _preventFocus );

		_updateAdditionsValue( titleInput.id );
		_updatePrefixValue( titleInput.id );
		enqueueUnregisteredInputTrigger( titleInput.id );
	}

	// ─── PUBLIC API ────────────────────────────────────────────────────────────

	return {
		/**
		 * Registers the 'better-seo-onload' listener that triggers global title actions.
		 * Called automatically on script load.
		 *
		 * @return {void}
		 */
		load: () => {
			document.body.addEventListener( 'better-seo-onload', _initAllTitleActions );
		},

		// Instance management
		setInputElement,
		getInputElement,

		// State management
		getStateOf,
		updateStateOf,
		updateStateAll,

		// Input triggers
		triggerCounter,
		triggerInput,
		enqueueTriggerInput,
		triggerUnregisteredInput,
		enqueueUnregisteredInputTrigger,

		// Alias — enqueueTriggerUnregisteredInput was the originally intended name.
		enqueueTriggerUnregisteredInput: enqueueUnregisteredInputTrigger,

		// Constants
		l10n,
		untitledTitle,
		privatePrefix,
		protectedPrefix,
		stripTitleTags,
	};

}() );

// Auto-initialise — registers the 'better-seo-onload' listener.
window.BetterSeoTitle.load();