/**
 * Better SEO — Primary Term Selector (List Edit)
 *
 * Provides the primary term selector UI for the WordPress post list table
 * quick edit and bulk edit panels. Renders a dropdown when multiple terms
 * are selected for a supported taxonomy, allowing the user to designate one
 * as the primary term.
 *
 * Exposed as: window.BetterSeoPTLE
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - L10n object:    BetterSeoPTL10n (shared with pt.js and pt-gb.js)
 *   - L10n keys:      BetterSeoPTL10n.taxonomies
 *   - Quick edit input name: better-seo-quick[primary_term_{taxonomy}]
 *                            (read by class-post.php:141,145)
 *   - Bulk edit input name:  better-seo-bulk[primary_term_{taxonomy}]
 *                            (read by class-post.php:161,165)
 *   - Template IDs (set by templates/list/primary-term-selector.php):
 *       tmpl-better-seo-primary-term-selector-quick — quick edit dropdown template
 *       tmpl-better-seo-primary-term-selector-bulk  — bulk edit dropdown template
 *   - Custom events dispatched:
 *       'better-seo-updated-primary-term' — fired when the primary term changes
 *   - No 'load' event — this module is called directly by le.js via:
 *       BetterSeoPTLE._prepareQuickEditTerms( postId, primaryTerms )
 *       BetterSeoPTLE._prepareBulkEditTerms()
 */

'use strict';

/**
 * Primary term selector module for the list edit (quick/bulk edit) panels.
 *
 * @namespace BetterSeoPTLE
 */
window.BetterSeoPTLE = ( function () {

	/**
	 * Localisation data passed from PHP via wp_localize_script().
	 * Shared with pt.js and pt-gb.js.
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
	 * Renders an Underscore.js template by ID with the given data.
	 *
	 * Returns an empty string if wp.template is unavailable or the template is not found.
	 *
	 * @param {string} templateId The Underscore.js template script ID (without 'tmpl-' prefix).
	 * @param {Object} data       The data to pass to the template.
	 * @return {string} The rendered HTML string, or an empty string on failure.
	 */
	function _renderTemplate( templateId, data ) {

		if ( ! window.wp || 'function' !== typeof window.wp.template ) {
			return '';
		}

		const template = window.wp.template( templateId );

		if ( 'function' !== typeof template ) {
			return '';
		}

		return template( data );
	}

	/**
	 * Dispatches the 'better-seo-updated-primary-term' event on the document.
	 *
	 * Consumed by canonical.js and le.js to update the canonical URL placeholder.
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

	// ─── TERM CHECKBOXES ───────────────────────────────────────────────────────

	/**
	 * Creates a term checkbox controller for the given taxonomy and edit row.
	 *
	 * Handles both quick edit rows (editId = post ID) and bulk edit (editId = 'bulk').
	 * Filters out indeterminate checkboxes — WordPress only implements indeterminate
	 * states for the built-in 'category' taxonomy, not custom taxonomies. Indeterminate
	 * checkboxes represent an ambiguous state (only some posts have the term) and are
	 * unsuitable for primary term selection.
	 *
	 * @param {string}        taxonomySlug The taxonomy slug.
	 * @param {number|string} editId       The post ID for quick edit, or 'bulk' for bulk edit.
	 * @return {{ getWrap: Function, getInputs: Function, getInputsChecked: Function, getInputsCheckedValues: Function, subscribe: Function }}
	 */
	function _termCheckboxes( taxonomySlug, editId ) {

		/**
		 * Returns the taxonomy checklist wrapper element for this edit row.
		 *
		 * @return {Element|null}
		 */
		const getWrap = () => document.querySelector(
			editId === 'bulk'
				? `#bulk-edit .${taxonomySlug}-checklist`
				: `#edit-${editId} .${taxonomySlug}-checklist`,
		);

		/**
		 * Returns all checkbox inputs in the checklist, sorted by value (term ID).
		 *
		 * @return {HTMLInputElement[]}
		 */
		const getInputs = () => [ ...getWrap().querySelectorAll( 'input[type=checkbox]' ) ]
			.sort( ( a, b ) => a.value - b.value );

		/**
		 * Returns only the checked, non-indeterminate checkbox inputs.
		 *
		 * @return {HTMLInputElement[]}
		 */
		const getInputsChecked = () => getInputs().filter( el => el.checked && ! el.indeterminate );

		/**
		 * Returns the numeric values of all checked, non-indeterminate checkbox inputs.
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
				for ( const el of getInputs() ) {
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
			tick();
		};

		return {
			getWrap,
			getInputs,
			getInputsChecked,
			getInputsCheckedValues,
			subscribe,
		};
	}

	// ─── QUICK EDIT PRIMARY TERM FIELD ─────────────────────────────────────────

	/**
	 * Creates a primary term field controller for the quick edit panel.
	 *
	 * Manages a hidden input named better-seo-quick[primary_term_{taxonomy}]
	 * within the quick edit row for the given post ID.
	 *
	 * @param {string}        taxonomySlug The taxonomy slug.
	 * @param {number|string} postId       The post ID.
	 * @return {{ get: Function, set: Function, revalidate: Function, registerPostField: Function, isPostFieldRegistered: Function }}
	 */
	function _primaryTermSelectorQuick( taxonomySlug, postId ) {

		const fieldName         = `better-seo-quick[primary_term_${taxonomySlug}]`;
		const _primaryTermField = () => document.getElementById( fieldName );
		const getFieldContainer = () => document.getElementById( `edit-${postId}` );

		/**
		 * Returns the current primary term ID from the hidden input.
		 *
		 * @return {number}
		 */
		const get = () => +( _primaryTermField()?.value ?? 0 );

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
		 * Inserts the hidden primary term input into the quick edit row.
		 *
		 * If the field already exists, optionally sets its value to defaultValue.
		 *
		 * @param {number|undefined} defaultValue Optional initial value for the field.
		 * @return {void}
		 */
		const registerPostField = defaultValue => {
			const wrap = getFieldContainer();
			if ( ! wrap ) {
				return;
			}

			let field = _primaryTermField();

			if ( ! field ) {
				wrap.insertAdjacentHTML(
					'beforeend',
					`<input type="hidden" id="${fieldName}" name="${fieldName}" value="0">`,
				);
				field = _primaryTermField();
			}

			if ( field && undefined !== defaultValue ) {
				const normalizedValue = +defaultValue;
				field.value = isNaN( normalizedValue ) ? 0 : normalizedValue;
			}
		};

		/**
		 * Returns whether the primary term field exists in the DOM.
		 *
		 * @return {boolean}
		 */
		const isPostFieldRegistered = () => !! _primaryTermField();

		return { get, set, revalidate, registerPostField, isPostFieldRegistered };
	}

	// ─── BULK EDIT PRIMARY TERM FIELD ──────────────────────────────────────────

	/**
	 * Creates a primary term field controller for the bulk edit panel.
	 *
	 * Manages a hidden input named better-seo-bulk[primary_term_{taxonomy}]
	 * within the bulk edit row.
	 *
	 * @param {string} taxonomySlug The taxonomy slug.
	 * @return {{ get: Function, set: Function, revalidate: Function, registerPostField: Function, isPostFieldRegistered: Function }}
	 */
	function _primaryTermSelectorBulk( taxonomySlug ) {

		const fieldName         = `better-seo-bulk[primary_term_${taxonomySlug}]`;
		const _primaryTermField = () => document.getElementById( fieldName );
		const getFieldContainer = () => document.getElementById( 'bulk-edit' );

		/**
		 * Returns the current primary term ID from the hidden input.
		 *
		 * @return {number}
		 */
		const get = () => +( _primaryTermField()?.value ?? 0 );

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
		 * Inserts the hidden primary term input into the bulk edit row.
		 *
		 * @param {number|undefined} defaultValue Optional initial value for the field.
		 * @return {void}
		 */
		const registerPostField = defaultValue => {
			const wrap = getFieldContainer();
			if ( ! wrap ) {
				return;
			}

			let field = _primaryTermField();

			if ( ! field ) {
				wrap.insertAdjacentHTML(
					'beforeend',
					`<input type="hidden" id="${fieldName}" name="${fieldName}" value="0">`,
				);
				field = _primaryTermField();
			}

			if ( field && undefined !== defaultValue ) {
				const normalizedValue = +defaultValue;
				field.value = isNaN( normalizedValue ) ? 0 : normalizedValue;
			}
		};

		/**
		 * Returns whether the primary term field exists in the DOM.
		 *
		 * @return {boolean}
		 */
		const isPostFieldRegistered = () => !! _primaryTermField();

		return { get, set, revalidate, registerPostField, isPostFieldRegistered };
	}

	// ─── QUICK EDIT INITIALISATION ─────────────────────────────────────────────

	/**
	 * Initialises the primary term selector for all supported taxonomies in the
	 * quick edit row for the given post.
	 *
	 * Called by le.js when a quick edit row is opened.
	 *
	 * @param {number|string} postId       The post ID of the quick edit row.
	 * @param {Object|null}   primaryTerms Map of primary_term_{taxonomy} → { value } from PHP.
	 * @return {void}
	 */
	function _prepareQuickEditTerms( postId, primaryTerms ) {

		if ( ! Object.keys( supportedTaxonomies ).length ) {
			return;
		}

		const getStoredTermValue = taxonomySlug => +(
			primaryTerms?.[ `primary_term_${taxonomySlug}` ]?.value ?? 0
		) || 0;

		const initQuickEditSelector = taxonomySlug => {

			const primaryTerm    = _primaryTermSelectorQuick( taxonomySlug, postId );
			const termCheckboxes = _termCheckboxes( taxonomySlug, postId );

			const selectorWrapId = `better-seo-pt-le-${taxonomySlug}-${postId}`;
			const selectId       = `${selectorWrapId}-select`;

			let selectorWrapShown = false;

			const termCheckboxWrap = termCheckboxes.getWrap();
			const storedTermValue  = getStoredTermValue( taxonomySlug );

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

				BetterSeo.selectByValue( selectElement, `${primaryTerm.get()}` );
			};

			const showSelectorWrap = () => {
				let selectElement = document.getElementById( selectId );

				if ( ! selectElement ) {
					const markup = _renderTemplate(
						'better-seo-primary-term-selector-quick',
						{
							wrapId:     selectorWrapId,
							selectId,
							selectName: selectId,
							i18n:       {
								selectPrimary: _geti18n( taxonomySlug, 'selectPrimary' ),
							},
						},
					);

					if ( markup.length ) {
						termCheckboxWrap.insertAdjacentHTML( 'afterend', markup );
					}

					selectElement = document.getElementById( selectId );

					selectElement?.addEventListener(
						'change',
						event => primaryTerm.set( event.target.value ),
					);
				}

				if ( selectElement ) {
					selectorWrapShown = true;
				}

				return selectElement;
			};

			termCheckboxes.subscribe( () => {
				if ( termCheckboxes.getInputsChecked().length > 1 ) {
					if ( ! selectorWrapShown ) {
						selectorWrapShown = true;
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
						|| storedTermValue
						|| 0,
					);
				}
			} );
		};

		for ( const taxonomySlug in supportedTaxonomies ) {
			if ( _termCheckboxes( taxonomySlug, postId ).getWrap() ) {
				const primaryTerm = _primaryTermSelectorQuick( taxonomySlug, postId );
				primaryTerm.registerPostField( getStoredTermValue( taxonomySlug ) );

				if ( primaryTerm.isPostFieldRegistered() ) {
					initQuickEditSelector( taxonomySlug );
				}
			}
		}
	}

	// ─── BULK EDIT INITIALISATION ──────────────────────────────────────────────

	/**
	 * Initialises the primary term selector for all supported taxonomies in the
	 * bulk edit panel.
	 *
	 * Called by le.js when the bulk edit panel is opened.
	 *
	 * @return {void}
	 */
	function _prepareBulkEditTerms() {

		const initBulkSelector = taxonomySlug => {

			const termCheckboxes = _termCheckboxes( taxonomySlug, 'bulk' );
			const primaryTerm    = _primaryTermSelectorBulk( taxonomySlug );
			const checklist      = termCheckboxes.getWrap();
			const selectorWrapId = `better-seo-pt-le-${taxonomySlug}-bulk`;
			const selectId       = `${selectorWrapId}-select`;

			let selectorWrapShown = false;

			const showSelectorWrap = () => {
				let selectElement = document.getElementById( selectId );

				if ( ! selectElement ) {
					const markup = _renderTemplate(
						'better-seo-primary-term-selector-bulk',
						{
							wrapId:     selectorWrapId,
							selectId,
							selectName: selectId,
							i18n:       {
								selectPrimary: _geti18n( taxonomySlug, 'selectPrimary' ),
							},
						},
					);

					if ( markup.length ) {
						checklist.insertAdjacentHTML( 'afterend', markup );
					}

					selectElement = document.getElementById( selectId );

					selectElement?.addEventListener(
						'change',
						event => {
							// 'nochange' is the default option — skip setting the primary term.
							if ( 'nochange' === event.target.value ) {
								return;
							}
							primaryTerm.set( event.target.value );
						},
					);
				}

				if ( selectElement ) {
					selectorWrapShown = true;
				}

				return selectElement;
			};

			const repopulateSelect = () => {
				const optionElement = document.createElement( 'option' );
				const selectElement = document.getElementById( selectId );

				const previousValue = selectElement.value;

				selectElement.innerHTML = '';

				// Add the "No Change" default option.
				const defaultOption = optionElement.cloneNode();
				defaultOption.value = 'nochange';
				defaultOption.label = '— No Change —';
				selectElement.appendChild( defaultOption );

				let restorePreviousValue = false;

				selectElement.append(
					...termCheckboxes.getInputsChecked().map( el => {
						const option = optionElement.cloneNode();
						option.value = el.value;
						option.label = BetterSeo.decodeEntities( el.parentElement.textContent.trim() );

						if ( el.value === previousValue ) {
							restorePreviousValue = true;
						}

						return option;
					} ),
				);

				// Restore the previous selection if it's still available, otherwise reset to 'nochange'.
				const nextValue = restorePreviousValue ? previousValue : 'nochange';
				BetterSeo.selectByValue( selectElement, nextValue );
			};

			termCheckboxes.subscribe( () => {
				if ( termCheckboxes.getInputsChecked().length > 1 ) {
					if ( ! selectorWrapShown ) {
						selectorWrapShown = true;
						showSelectorWrap();
					}
					repopulateSelect();
					return;
				}

				if ( selectorWrapShown ) {
					document.getElementById( selectorWrapId )?.remove();
					selectorWrapShown = false;
				}
			} );
		};

		for ( const taxonomySlug in supportedTaxonomies ) {
			if ( _termCheckboxes( taxonomySlug, 'bulk' ).getWrap() ) {
				const primaryTerm = _primaryTermSelectorBulk( taxonomySlug );
				primaryTerm.registerPostField();

				if ( primaryTerm.isPostFieldRegistered() ) {
					initBulkSelector( taxonomySlug );
				}
			}
		}
	}

	// ─── PUBLIC API ────────────────────────────────────────────────────────────

	return {
		_prepareQuickEditTerms,
		_prepareBulkEditTerms,
		l10n,
	};

}() );