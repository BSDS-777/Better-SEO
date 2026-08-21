/**
 * Better SEO — Meta Description Field Module
 *
 * Manages meta description input fields across the post edit, term edit,
 * and settings pages. Handles reference element updates, placeholder sync,
 * character/pixel counter updates, and resize events.
 *
 * Exposed as: window.BetterSeoDescription
 *
 * Usage:
 *   BetterSeoDescription.setInputElement( element );
 *   BetterSeoDescription.updateStateOf( id, 'defaultDescription', text );
 *   BetterSeoDescription.triggerInput( id );
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - No L10n object — description module uses no PHP-side localisation
 *   - No AJAX actions — all processing is client-side
 *   - Custom events consumed:
 *       'better-seo-onload'                      — fired by better-seo.js when UI is ready
 *       'better-seo-resize'                      — fired on window resize (debounced)
 *       'better-seo-counter-updated'             — fired by c.js when counter type changes
 *       'better-seo-update-description-counter'  — fired to force counter re-render
 *   - DOM IDs expected:
 *       `better-seo-description-reference_${id}` — hidden reference element for counter/placeholder
 *       `${id}_chars`                             — character counter element
 *       `${id}_pixels`                            — pixel counter element
 */

'use strict';

/**
 * Meta description field management module.
 *
 * @namespace BetterSeoDescription
 */
window.BetterSeoDescription = ( function () {

	/**
	 * Map of input element ID → Element for all registered description inputs.
	 *
	 * @type {Map<string, Element>}
	 */
	const descriptionInputInstances = new Map();

	/**
	 * Per-input state objects keyed by element ID.
	 *
	 * @type {Object.<string, Object>}
	 */
	const states = {};

	// ─── INPUT REGISTRATION ────────────────────────────────────────────────────

	/**
	 * Registers a description input element and initialises its state.
	 *
	 * @param {Element} element The description textarea element.
	 * @return {Element} The registered element.
	 */
	function setInputElement( element ) {
		descriptionInputInstances.set( element.id, element );
		states[ element.id ] = {
			allowReferenceChange:  true,
			defaultDescription:    '',
			useDefaultDescription: true,
		};
		_loadDescriptionActions( element );
		return getInputElement( element.id );
	}

	/**
	 * Returns the registered description input element for the given ID.
	 *
	 * @param {string} id The element ID.
	 * @return {Element|undefined}
	 */
	function getInputElement( id ) {
		return descriptionInputInstances.get( id );
	}

	// ─── STATE MANAGEMENT ──────────────────────────────────────────────────────

	/**
	 * Returns the full state object or a specific state property for the given input ID.
	 *
	 * @param {string}      id   The element ID.
	 * @param {string|null} part Optional state property name.
	 * @return {*}
	 */
	function getStateOf( id, part ) {
		return part ? states[ id ]?.[ part ] : states[ id ];
	}

	/**
	 * Updates a specific state property for the given input ID and triggers a re-render.
	 *
	 * @param {string} id    The element ID.
	 * @param {string} part  The state property name.
	 * @param {*}      value The new value.
	 * @return {void}
	 */
	function updateStateOf( id, part, value ) {

		if ( states[ id ][ part ] === value ) {
			return;
		}

		states[ id ][ part ] = value;

		// All state changes trigger a description re-render.
		enqueueTriggerInput( id );
	}

	/**
	 * Updates a state property for all registered inputs except the given exceptions.
	 *
	 * @param {string}          part   The state property name.
	 * @param {*}               value  The new value.
	 * @param {string|string[]} except Element ID(s) to skip.
	 * @return {void}
	 */
	function updateStateAll( part, value, except ) {

		const exceptions = Array.isArray( except ) ? except : [ except ];

		for ( const element of descriptionInputInstances.values() ) {
			if ( exceptions.includes( element.id ) ) {
				continue;
			}
			updateStateOf( element.id, part, value );
		}
	}

	// ─── REFERENCE ELEMENT HELPERS ─────────────────────────────────────────────

	/**
	 * Returns the hidden reference element(s) for the given input ID.
	 * The reference element holds the processed description text for counter/placeholder use.
	 *
	 * @param {string} id The input element ID.
	 * @return {Element[]}
	 */
	function _getDescriptionReferences( id ) {
		return [ document.getElementById( `better-seo-description-reference_${id}` ) ];
	}

	// ─── DESCRIPTION PROCESSING ────────────────────────────────────────────────

	/**
	 * Updates the hidden reference element with the processed description text.
	 *
	 * Resolves the description from the input value, default description, or empty string,
	 * then normalises whitespace and dispatches a 'change' event on the reference element.
	 *
	 * @param {InputEvent} event
	 * @return {void}
	 */
	function _setReferenceDescription( event ) {

		const references = _getDescriptionReferences( event.target.id );

		if ( ! references[0] ) {
			return;
		}

		const allowReferenceChange  = getStateOf( event.target.id, 'allowReferenceChange' );
		const useDefaultDescription = allowReferenceChange
			? getStateOf( event.target.id, 'useDefaultDescription' )
			: true;

		const text = BetterSeo.coalesceStrlen( allowReferenceChange && event.target.value.trim() )
			?? BetterSeo.coalesceStrlen( useDefaultDescription && getStateOf( event.target.id, 'defaultDescription' ) )
			?? '';

		const referenceValue = BetterSeo.escapeString(
			BetterSeo.decodeEntities(
				BetterSeo.sDoubleSpace(
					BetterSeo.sTabs(
						BetterSeo.sSingleLine( text ).trim(),
					),
				),
			),
		);

		const changeEvent = new Event( 'change' );

		for ( const reference of references ) {
			reference.innerHTML = referenceValue;
			// Dispatch change event asynchronously to avoid blocking the input handler.
			setTimeout( () => reference.dispatchEvent( changeEvent ) );
		}
	}

	/**
	 * Updates the description input's placeholder from the reference element's text content.
	 *
	 * @param {InputEvent} event
	 * @return {void}
	 */
	function _updatePlaceholder( event ) {
		event.target.placeholder = _getDescriptionReferences( event.target.id )[0].textContent;
	}

	// ─── COUNTER UPDATES ───────────────────────────────────────────────────────

	/**
	 * Updates the character counter for the description field.
	 *
	 * @param {InputEvent} event
	 * @return {void}
	 */
	function _updateCounter( event ) {

		const counter   = document.getElementById( `${event.target.id}_chars` );
		const reference = _getDescriptionReferences( event.target.id )[0];

		if ( ! counter ) {
			return;
		}

		BetterSeoC?.updateCharacterCounter( {
			e:     counter,
			text:  reference.innerHTML,
			field: 'description',
			type:  'search',
		} );
	}

	/**
	 * Updates the pixel counter for the description field.
	 *
	 * @param {InputEvent} event
	 * @return {void}
	 */
	function _updatePixels( event ) {

		const pixels    = document.getElementById( `${event.target.id}_pixels` );
		const reference = _getDescriptionReferences( event.target.id )[0];

		if ( ! pixels ) {
			return;
		}

		BetterSeoC?.updatePixelCounter( {
			e:     pixels,
			text:  reference.innerHTML,
			field: 'description',
			type:  'search',
		} );
	}

	// ─── INPUT TRIGGERING ──────────────────────────────────────────────────────

	/**
	 * Dispatches an 'input' event on the description field for the given ID,
	 * or on all registered fields if no ID is provided.
	 *
	 * @param {string|null} id Optional element ID.
	 * @return {void}
	 */
	function triggerInput( id ) {
		if ( id ) {
			getInputElement( id )?.dispatchEvent( new Event( 'input' ) );
		} else {
			// Guard against infinite loops — only trigger if element has an ID.
			for ( const element of descriptionInputInstances.values() ) {
				if ( element.id ) {
					triggerInput( element.id );
				}
			}
		}
	}

	/**
	 * Dispatches a 'better-seo-update-description-counter' event to force counter re-render.
	 *
	 * @param {string|null} id Optional element ID.
	 * @return {void}
	 */
	function triggerCounter( id ) {
		if ( id ) {
			getInputElement( id )?.dispatchEvent( new CustomEvent( 'better-seo-update-description-counter' ) );
		} else {
			// Guard against infinite loops — only trigger if element has an ID.
			for ( const element of descriptionInputInstances.values() ) {
				if ( element.id ) {
					triggerCounter( element.id );
				}
			}
		}
	}

	// ─── EVENT HANDLERS ────────────────────────────────────────────────────────

	/**
	 * Handles the 'input' event — updates reference, placeholder, and counters.
	 *
	 * @param {InputEvent} event
	 * @return {void}
	 */
	function _onUpdateDescriptionsTrigger( event ) {
		_setReferenceDescription( event );
		_updatePlaceholder( event );
		_onUpdateCounterTrigger( event );
	}

	/**
	 * Handles counter update events — refreshes character and pixel counters.
	 *
	 * @param {InputEvent} event
	 * @return {void}
	 */
	function _onUpdateCounterTrigger( event ) {
		_updateCounter( event );
		_updatePixels( event );
	}

	/** @type {Object.<string, number>} */
	let _enqueueTriggerInputBuffer = {};

	/**
	 * Debounces triggerInput() to ~60fps to prevent excessive re-renders.
	 *
	 * @param {string} id The element ID.
	 * @return {void}
	 */
	function enqueueTriggerInput( id ) {
		if ( id in _enqueueTriggerInputBuffer ) {
			clearTimeout( _enqueueTriggerInputBuffer[ id ] );
		}
		_enqueueTriggerInputBuffer[ id ] = setTimeout( () => triggerInput( id ), 1000 / 60 ); // ~60fps
	}

	/**
	 * Triggers input without marking settings as changed in the AYS module.
	 *
	 * @param {string|null} id Optional element ID.
	 * @return {void}
	 */
	function triggerUnregisteredInput( id ) {
		if ( 'BetterSeoAys' in window ) {
			const wereSettingsChanged = BetterSeoAys.areSettingsChanged();

			triggerInput( id );

			// Reset AYS state only if we caused a spurious change detection.
			if ( ! wereSettingsChanged && BetterSeoAys.areSettingsChanged() ) {
				BetterSeoAys.reset();
			}
		} else {
			triggerInput( id );
		}
	}

	/** @type {Object.<string, number>} */
	let _unregisteredTriggerBuffer = {};

	/**
	 * Debounces triggerUnregisteredInput() to ~60fps.
	 *
	 * @param {string|null} id Optional element ID.
	 * @return {void}
	 */
	function enqueueUnregisteredInputTrigger( id ) {
		if ( id in _unregisteredTriggerBuffer ) {
			clearTimeout( _unregisteredTriggerBuffer[ id ] );
		}
		_unregisteredTriggerBuffer[ id ] = setTimeout( () => triggerUnregisteredInput( id ), 1000 / 60 ); // ~60fps
	}

	/**
	 * Handles window resize — re-renders all description fields.
	 *
	 * @return {void}
	 */
	function _doResize() {
		triggerUnregisteredInput();
	}

	// ─── INITIALISATION ────────────────────────────────────────────────────────

	/**
	 * Attaches event listeners to a description input element.
	 *
	 * @param {Element} descriptionInput
	 * @return {void}
	 */
	function _loadDescriptionActions( descriptionInput ) {

		if ( ! ( descriptionInput instanceof Element ) ) {
			return;
		}

		descriptionInput.addEventListener( 'input', _onUpdateDescriptionsTrigger );
		descriptionInput.addEventListener( 'better-seo-update-description-counter', _onUpdateCounterTrigger );

		enqueueUnregisteredInputTrigger( descriptionInput.id );
	}

	/**
	 * Initialises global description module listeners.
	 * Called once when the 'better-seo-onload' event fires.
	 *
	 * @return {void}
	 */
	function _initAllDescriptionActions() {

		// Re-render all descriptions on window resize (e.g. pixel counter width changes).
		window.addEventListener( 'better-seo-resize', _doResize );

		// Re-render all counters when the counter display type changes.
		window.addEventListener( 'better-seo-counter-updated', () => enqueueUnregisteredInputTrigger() );
	}

	// ─── PUBLIC API ────────────────────────────────────────────────────────────

	return {
		/**
		 * Attaches the description module to the 'better-seo-onload' event.
		 * Called automatically on script load.
		 *
		 * @return {void}
		 */
		load: () => {
			document.body.addEventListener( 'better-seo-onload', _initAllDescriptionActions );
		},
		setInputElement,
		getInputElement,
		getStateOf,
		updateStateOf,
		updateStateAll,
		triggerCounter,
		triggerInput,
		enqueueTriggerInput,
		triggerUnregisteredInput,
		enqueueUnregisteredInputTrigger,
	};

}() );

// Auto-initialise — registers the 'better-seo-onload' listener immediately.
window.BetterSeoDescription.load();