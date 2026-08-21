/**
 * Better SEO — Primary Term Selector (Gutenberg Block Editor)
 *
 * Provides the primary term selector React component for the WordPress block
 * editor (Gutenberg). Renders a SelectControl dropdown within the taxonomy
 * panel when multiple terms are selected, allowing the user to designate one
 * as the primary term for canonical URL and breadcrumb generation.
 *
 * Exposed as: window.BetterSeoPTGB
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - L10n object:    BetterSeoPTL10n (shared with pt.js — registered in get_primaryterm_scripts())
 *   - L10n keys:      BetterSeoPTL10n.taxonomies — map of taxonomy slug → { i18n, primary, ... }
 *   - Input name:     better-seo[_primary_term_{taxonomy}] (read by class-post.php:131)
 *   - Template ID:    tmpl-better-seo-primary-term-selector (set by templates/inpost/primary-term-selector.php)
 *   - Data holder ID: better-seo-gutenberg-data-holder (set by gutenberg-data.php)
 *   - WordPress filter: editor.PostTaxonomyType (namespace: better-seo/pt)
 *   - Custom events dispatched:
 *       'better-seo-updated-primary-term' — fired when the primary term changes
 *   - Custom events consumed:
 *       'better-seo-ready'                — fired by better-seo.js when block editor UI is ready
 */

'use strict';

/**
 * Primary term selector module for the Gutenberg block editor.
 *
 * @namespace BetterSeoPTGB
 */
window.BetterSeoPTGB = ( function () {

	/**
	 * Localisation data passed from PHP via wp_localize_script().
	 * Shared with pt.js — must include: taxonomies.
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

	/**
	 * WordPress block editor React and component APIs.
	 */
	const { createElement, Fragment, Component, useState, useEffect } = wp.element;
	const { SelectControl } = wp.components;
	const { useSelect }     = wp.data;

	/**
	 * Stable empty array reference to prevent unnecessary re-renders in useSelect.
	 *
	 * @type {Array}
	 */
	const EMPTY_ARRAY = [];

	/**
	 * Default REST API query parameters for fetching taxonomy terms.
	 *
	 * @type {{ per_page: number, orderby: string, order: string, _fields: string }}
	 */
	const DEFAULT_QUERY = {
		per_page: -1,
		orderby:  'id',
		order:    'asc',
		_fields:  'id,name',
	};

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

	/**
	 * Map of taxonomy slug → registration status (true = registered, false = holder not found).
	 *
	 * @type {Map<string, boolean>}
	 */
	const _registeredFields = new Map();

	// ─── PRIMARY TERM FIELD ────────────────────────────────────────────────────

	/**
	 * Creates a primary term field controller for the given taxonomy.
	 *
	 * Manages the hidden input that stores the primary term ID, inserted into
	 * the better-seo-gutenberg-data-holder element by registerPostField().
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
		 * Inserts the primary term hidden input template into the Gutenberg data holder.
		 *
		 * Uses the 'tmpl-better-seo-primary-term-selector' Underscore.js template
		 * registered by templates/inpost/primary-term-selector.php.
		 *
		 * @return {void}
		 */
		const registerPostField = () => {
			const wrap = document.getElementById( 'better-seo-gutenberg-data-holder' );

			if ( wrap ) {
				wrap.insertAdjacentHTML(
					'beforeend',
					wp.template( 'better-seo-primary-term-selector' )( {
						taxonomy: supportedTaxonomies[ taxonomySlug ],
					} ),
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

	// ─── REACT COMPONENT ───────────────────────────────────────────────────────

	/**
	 * Initialises the primary term selector React component and registers the
	 * WordPress block editor filter.
	 *
	 * @return {void}
	 */
	function _initPrimaryTerm() {

		if ( ! Object.keys( supportedTaxonomies ).length ) {
			return;
		}

		/**
		 * React functional component for the primary term SelectControl.
		 *
		 * Subscribes to the block editor data store to get the currently selected
		 * terms and available terms for the taxonomy. Renders a SelectControl when
		 * more than one term is selected, or null otherwise.
		 *
		 * @param {{ taxonomySlug: string }} props
		 * @return {Element|null}
		 */
		function primaryTermSelector( props ) {

			const { taxonomySlug }            = props;
			const primaryTerm                 = _primaryTermSelector( taxonomySlug );
			const [ selection, setSelection ] = useState( primaryTerm.get() );

			// Ref: https://github.com/WordPress/gutenberg/pull/33418#issuecomment-903686737
			const {
				selectedTerms,
				loading,
				availableTerms,
			} = useSelect(
				select => {
					const { getTaxonomy, getEntityRecords, isResolving } = select( 'core' );
					const { getEditedPostAttribute }                     = select( 'core/editor' );

					const _taxonomy = getTaxonomy( taxonomySlug );
					const _query    = [ 'taxonomy', taxonomySlug, DEFAULT_QUERY ];

					return {
						// EMPTY_ARRAY prevents unnecessary re-renders from new array references.
						selectedTerms:  getEditedPostAttribute( _taxonomy?.rest_base ) || EMPTY_ARRAY,
						loading:        isResolving( 'getEntityRecords', _query ),
						availableTerms: getEntityRecords( ..._query ) || EMPTY_ARRAY,
					};
				},
				[ taxonomySlug ],
			);

			// Sync the hidden input with the block editor selection state.
			useEffect(
				() => {
					if ( ! selectedTerms.includes( +selection ) || primaryTerm.get() !== +selection ) {
						primaryTerm.revalidate( selectedTerms );
						setSelection( primaryTerm.get() );
					}
				},
				[ selectedTerms ],
			);

			if ( selectedTerms?.length < 2 ) {
				// Reset stored selection and fall back to the first available term.
				primaryTerm.set(
					0,
					   selectedTerms?.[0]
					|| availableTerms?.[0]?.id
					|| 0,
				);
				// Hide the selector when fewer than 2 terms are selected.
				return null;
			}

			const onChange = termId => {
				if ( ! selectedTerms.includes( +termId ) ) {
					return;
				}
				primaryTerm.set( termId );
				setSelection( primaryTerm.get() );
				BetterSeoAys.registerChange();
			};

			const getSelectOptions = () => availableTerms.map(
				term =>
					   selectedTerms.includes( term?.id )
					&& {
						value: term.id,
						label: BetterSeo.decodeEntities( term?.name ),
					},
			).filter( Boolean ) || [];

			const isDisabled = () => ! ( selectedTerms.length && availableTerms.length && ! loading );

			return createElement(
				SelectControl,
				{
					label:                  _geti18n( taxonomySlug, 'selectPrimary' ),
					value:                  selection,
					className:              'better-seo-pt-gb-selector',
					onChange:               onChange,
					options:                getSelectOptions(),
					disabled:               isDisabled(),
					__nextHasNoMarginBottom: true, // WP 6.7+ 'next/future' default.
				},
			);
		}

		/**
		 * Higher-order component that wraps the WordPress PostTaxonomyType component
		 * to append the Better SEO primary term selector below it.
		 *
		 * Bails silently if the primary term field is not registered for this taxonomy.
		 *
		 * @param {Component} OriginalComponent The original PostTaxonomyType component.
		 * @return {Component} The wrapped component.
		 */
		const PrimaryTermSelectorFilter = OriginalComponent => class extends Component {
			render() {
				// If the primary term field is not registered for this taxonomy, render unchanged.
				if ( ! _primaryTermSelector( this.props?.slug ).isPostFieldRegistered() ) {
					return createElement( OriginalComponent, { ...this.props } );
				}

				return createElement(
					Fragment,
					null,
					createElement( OriginalComponent, { ...this.props } ),
					createElement( primaryTermSelector, { taxonomySlug: this.props?.slug } ),
				);
			}
		};

		// Register hidden inputs for all supported taxonomies.
		for ( const taxonomySlug in supportedTaxonomies ) {
			_primaryTermSelector( taxonomySlug ).registerPostField();
		}

		// Inject the primary term selector into the block editor taxonomy panel.
		wp.hooks.addFilter(
			'editor.PostTaxonomyType',
			'better-seo/pt',
			PrimaryTermSelectorFilter,
		);
	}

	// ─── PUBLIC API ────────────────────────────────────────────────────────────

	return {
		/**
		 * Attaches the Gutenberg primary term module to the 'better-seo-ready' event.
		 * Called automatically on script load.
		 *
		 * @return {void}
		 */
		load: () => {
			document.body.addEventListener( 'better-seo-ready', _initPrimaryTerm );
		},
		l10n,
	};

}() );

// Auto-initialise — registers the 'better-seo-ready' listener immediately.
window.BetterSeoPTGB.load();