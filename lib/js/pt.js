/**
 * Better SEO — Primary Term Selector Module
 *
 * Provides the primary term selector UI for the WordPress post edit screen.
 * When multiple terms are selected for a supported taxonomy, displays a dropdown
 * allowing the user to designate one as the primary term for canonical URL and
 * breadcrumb generation.
 *
 * Exposed as: window.BetterSeoPT
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - L10n object:    BetterSeoPTL10n (must be registered in get_primaryterm_scripts() in class-loader.php)
 *   - L10n keys:      BetterSeoPTL10n.taxonomies — map of taxonomy slug → { i18n, primary, ... }
 *   - Input name:     better-seo[_primary_term_{taxonomy}] (read by class-post.php:131)
 *   - Template ID:    tmpl-better-seo-primary-term-selector (set by templates/inpost/primary-term-selector.php)
 *   - CSS class:      .better-seo-primary-term-selector-wrap (set by templates/list/primary-term-selector.php)
 *   - Custom events dispatched:
 *       'better-seo-updated-primary-term' — fired when the primary term changes
 *                                           (consumed by canonical.js and le.js)
 *   - Custom events consumed:
 *       'better-seo-onload'               — fired by better-seo.js when UI is ready
 */

'use strict';

/**
 * Primary term selector module.
 *
 * @namespace BetterSeoPT
 */
window.BetterSeoPT = ( function () {

	/**
	 * Localisation data passed from PHP via wp_localize_script().
	 * Must include: taxonomies (map of taxonomy slug → taxonomy data with i18n).
	 *
	 * @type {{ taxonomies: Object.<string, Object> }}
	 */
	const l10n = BetterSeoPTL10n;

	/**
	 * Map of taxonomy slug → taxonomy configuration data.
	 *
	 * @type {Object.<string, Object>}
	 */
	const supportedTaxonomies = l10n.taxonomies;

	// ─── UTILITIES ─────────────────────────────────────────────────────────────

	/**
	 * Returns an i18n string for the given taxonomy and key.
	 *
	 * @param {string} taxonomySlug The taxonomy slug.
	 * @param {string} what         The i18n key to retrieve.
	 * @return {string} The i18n string, or an empty string if not found.
	 */
	function _geti18n( taxonomySlug, what ) {
		return supportedTaxonomies[ taxonomySlug ]?.i18n[ what ] ?? '';
	}

	/**
	 * Dispatches the 'better-seo-updated-primary-term' event on the document.
	 *
	 * Consumed by canonical.js and le.js to update the canonical URL placeholder
	 * when the primary term changes.
	 *
	 * @param {number} id           The new primary term ID (or fallback term ID).
	 * @param {string} taxonomySlug The taxonomy slug.
	 * @return {void}
	 */
	function dispatchUpdateEvent( id, taxonomySlug ) {
		document.dispatchEvent(
			new CustomEvent(
				'better-seo-updated-primary-term',
				{
					detail: { id, taxonomy: taxonomySlug },
				},
			),
		);
	}

	/**
	 * Map of taxonomy slug → registration status (true = registered, false = wrap not found).
	 *
	 * @type {Map<string, boolean>}
	 */
	const _registeredFields = new Map();

	// ─── PRIMARY TERM FIELD ────────────────────────────────────────────────────

	/**
	 * Creates a primary term field controller for the given taxonomy.
	 *
	 * Manages the hidden input that stores the primary term ID, and provides
	 * get/set/revalidate methods for interacting with it.
	 *
	 * @param {string} taxonomySlug The taxonomy slug.
	 * @return {{ get: Function, set: Function, revalidate: Function, registerPostField: Function, isPostFieldRegistered: Function }}
	 */
	function _primaryTermSelector( taxonomySlug ) {

		const _primaryTermField = () => document.getElementById( `better-seo[_primary_term_${taxonomySlug}]` );

		/**
		 * Returns the current primary term ID from the hidden input.
		 *
		 * @return {number}
		 */
		const get = () => +( _primaryTermField().value );

		/**
		 * Sets the primary term ID in the hidden input and dispatches the update event.
		 *
		 * @param {number|string} id       The new primary term ID.
		 * @param {number|string} fallback Optional fallback ID if id is falsy.
		 * @return {number} The set primary term ID.
		 */
		const set = ( id, fallback ) => {
			id = +id;
			_primaryTermField().value = id;
			dispatchUpdateEvent( id || +fallback, taxonomySlug );
			return id;
		};

		/**
		 * Revalidates the primary term against the currently selected terms.
		 *
		 * If the current primary term is still selected, returns it unchanged.
		 * Otherwise, resets to the first selected term (or 0 if none).
		 *
		 * @param {number[]} selectedTerms Array of currently selected term IDs.
		 * @return {number} The validated primary term ID.
		 */
		const revalidate = selectedTerms => {
			const primaryTerm = get();

			if ( selectedTerms.includes( primaryTerm ) ) {
				return primaryTerm;
			}

			return set( selectedTerms?.[0] || 0 );
		};

		/**
		 * Inserts the primary term hidden input template into the taxonomy metabox.
		 *
		 * Uses the 'tmpl-better-seo-primary-term-selector' Underscore.js template
		 * registered by templates/inpost/primary-term-selector.php.
		 *
		 * @return {void}
		 */
		const registerPostField = () => {
			const wrap = document.getElementById( `${taxonomySlug}div` );

			if ( wrap ) {
				wrap.insertAdjacentHTML(
					'beforeend',
					wp.template( 'better-seo-primary-term-selector' )(
						{ taxonomy: supportedTaxonomies[ taxonomySlug ] },
					),
				);
				_registeredFields.set( taxonomySlug, true );
			} else {
				_registeredFields.set( taxonomySlug, false );
			}
		};

		/**
		 * Returns whether the primary term field has been successfully registered.
		 *
		 * @return {boolean}
		 */
		const isPostFieldRegistered = () => !! _registeredFields.get( taxonomySlug );

		return { get, set, revalidate, registerPostField, isPostFieldRegistered };
	}

	// ─── TERM CHECKBOXES ───────────────────────────────────────────────────────

	/**
	 * Creates a term checkbox controller for the given taxonomy.
	 *
	 * Provides methods to query the taxonomy checklist checkboxes and subscribe
	 * to changes via MutationObserver (for dynamically added terms).
	 *
	 * @param {string} taxonomySlug The taxonomy slug.
	 * @return {{ getWrap: Function, getInputs: Function, getAllInputs: Function, getInputsChecked: Function, getInputsCheckedValues: Function, subscribe: Function }}
	 */
	function _termCheckboxes( taxonomySlug ) {

		/**
		 * Returns the taxonomy checklist wrapper element.
		 *
		 * @return {Element|null}
		 */
		const getWrap = () => document.getElementById( `${taxonomySlug}checklist` );

		/**
		 * Returns all checkbox inputs in the checklist, sorted by value (term ID).
		 *
		 * @return {HTMLInputElement[]}
		 */
		const getInputs = () => [ ...getWrap().querySelectorAll( 'input[type=checkbox]' ) ]
			.sort( ( a, b ) => a.value - b.value );

		/**
		 * Returns all checkbox inputs in the full taxonomy panel (including the "add new" section).
		 *
		 * @return {NodeList}
		 */
		const getAllInputs = () => document.getElementById( `taxonomy-${taxonomySlug}` )
			.querySelectorAll( '.categorychecklist input[type=checkbox]' );

		/**
		 * Returns only the checked checkbox inputs.
		 *
		 * @return {HTMLInputElement[]}
		 */
		const getInputsChecked = () => getInputs().filter( el => el.checked );

		/**
		 * Returns the numeric values of all checked checkbox inputs.
		 *
		 * @return {number[]}
		 */
		const getInputsCheckedValues = () => getInputsChecked().map( el => +el.value );

		/**
		 * Subscribes to term checkbox changes.
		 *
		 * Registers change listeners on all current checkboxes and uses a
		 * MutationObserver to re-register when new terms are dynamically added.
		 * Fires the callback immediately on subscription.
		 *
		 * @param {Function} callback The function to call on any checkbox change.
		 * @return {void}
		 */
		const subscribe = callback => {

			const tick = () => callback();

			const registerListeners = () => {
				for ( const el of getAllInputs() ) {
					el.addEventListener( 'change', tick );
				}
			};

			new MutationObserver( () => {
				// Re-register listeners when new terms are added to the checklist.
				registerListeners();
				tick();
			} ).observe(
				getWrap(),
				{ childList: true },
			);

			registerListeners();

			// Fire immediately to set the initial state.
			tick();
		};

		return {
			getWrap,
			getInputs,
			getAllInputs,
			getInputsChecked,
			getInputsCheckedValues,
			subscribe,
		};
	}

	// ─── PRIMARY TERM INITIALISATION ───────────────────────────────────────────

	/**
	 * Initialises the primary term selector for all supported taxonomies.
	 *
	 * For each taxonomy with a checklist present on the page, registers the
	 * hidden primary term input and sets up the primary term selector dropdown.
	 * The dropdown is shown only when more than one term is selected.
	 *
	 * @return {void}
	 */
	function _initPrimaryTerm() {

		if ( ! Object.keys( supportedTaxonomies ).length ) {
			return;
		}

		const initPrimaryTermSelector = taxonomySlug => {

			const primaryTerm    = _primaryTermSelector( taxonomySlug );
			const termCheckboxes = _termCheckboxes( taxonomySlug );

			const selectorWrapId = `better-seo-primary-term-${taxonomySlug}`;
			const selectId       = `${selectorWrapId}-select`;

			let selectorWrapShown = false;

			/**
			 * Repopulates the primary term select dropdown with the currently checked terms.
			 *
			 * @return {void}
			 */
			const repopulateSelect = () => {
				const optionElement = document.createElement( 'option' );
				const selectElement = document.getElementById( selectId );

				selectElement.innerHTML = '';
				selectElement.append(
					...termCheckboxes.getInputsChecked().map( el => {
						const option = optionElement.cloneNode();
						option.value = el.value;
						option.label = BetterSeo.decodeEntities( el.parentElement.textContent.trim() );
						return option;
					} ),
				);

				BetterSeo.selectByValue( selectElement, primaryTerm.get() );
			};

			/**
			 * Creates and inserts the primary term selector dropdown into the taxonomy panel.
			 * Only called once — subsequent calls are guarded by selectorWrapShown.
			 *
			 * @return {void}
			 */
			const showSelectorWrap = () => {
				const selectorWrap  = document.createElement( 'div' );
				const labelElement  = document.createElement( 'label' );
				const selectElement = document.createElement( 'select' );

				selectorWrap.id = selectorWrapId;
				selectorWrap.classList.add( 'better-seo-primary-term-selector-wrap' );

				labelElement.innerText = _geti18n( taxonomySlug, 'selectPrimary' );

				selectElement.name = selectElement.id = selectId;
				labelElement.setAttribute( 'for', selectId );

				selectorWrap.append( labelElement, selectElement );

				// Insert after the term adder if present, otherwise at the end of the taxonomy panel.
				document.getElementById( `taxonomy-${taxonomySlug}` )
					.insertBefore(
						selectorWrap,
						document.getElementById( `${taxonomySlug}-adder` )?.nextSibling ?? null,
					);

				selectElement.addEventListener(
					'change',
					event => primaryTerm.set( event.target.value ),
				);

				BetterSeoAys.registerChangeListener( selectElement, 'change' );
			};

			termCheckboxes.subscribe( () => {
				if ( termCheckboxes.getInputsChecked().length > 1 ) {
					if ( ! selectorWrapShown ) {
						selectorWrapShown = true; // Set first to mitigate race conditions.
						showSelectorWrap();
					}
					repopulateSelect();
					primaryTerm.revalidate( termCheckboxes.getInputsCheckedValues() );
				} else {
					if ( selectorWrapShown ) {
						document.getElementById( selectorWrapId )?.remove();
						selectorWrapShown = false;
					}

					// Reset stored selection and fall back to the first available term.
					primaryTerm.set(
						0,
						   termCheckboxes.getInputsChecked()[0]?.value
						|| termCheckboxes.getInputs()[0]?.value
						|| 0,
					);
				}
			} );
		};

		for ( const taxonomySlug in supportedTaxonomies ) {
			if ( _termCheckboxes( taxonomySlug ).getWrap() ) {
				const primaryTerm = _primaryTermSelector( taxonomySlug );
				primaryTerm.registerPostField();

				if ( primaryTerm.isPostFieldRegistered() ) {
					initPrimaryTermSelector( taxonomySlug );
				}
			}
		}
	}

	// ─── PUBLIC API ────────────────────────────────────────────────────────────

	return {
		/**
		 * Attaches the primary term module to the 'better-seo-onload' event.
		 * Called automatically on script load.
		 *
		 * @return {void}
		 */
		load: () => {
			document.body.addEventListener( 'better-seo-onload', _initPrimaryTerm );
		},
		l10n,
	};

}() );

// Auto-initialise — registers the 'better-seo-onload' listener immediately.
window.BetterSeoPT.load();