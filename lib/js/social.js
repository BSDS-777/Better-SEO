/**
 * Better SEO — Social Meta Module
 *
 * Manages Open Graph and Twitter Card meta input placeholders and character
 * counters for all social meta input groups across the plugin's admin UI.
 * Supports multiple independent input groups (homepage, post type archives,
 * individual posts/terms) via a group-keyed instance map.
 *
 * Exposed as: window.BetterSeoSocial
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - No direct AJAX calls — purely a UI state/placeholder manager.
 *   - Input elements are located via data attributes set by class-input.php:
 *       data-better-seo-social-group="{group}"
 *       data-better-seo-social-type="{ogTitle|twTitle|ogDesc|twDesc}"
 *   - Reference element IDs (set by class-input.php):
 *       better-seo-title-reference_{titleRef}          — live title preview span
 *       better-seo-title-noadditions-reference_{titleRef} — title without additions span
 *       better-seo-description-reference_{descRef}     — live description preview span
 *   - Counter element IDs (set by class-input.php):
 *       {inputId}_chars                                — character counter element
 *   - Dependencies:
 *       BetterSeo.sDoubleSpace(), BetterSeo.sTabs(), BetterSeo.sSingleLine()
 *       BetterSeo.decodeEntities()
 *       BetterSeoUtils.debounce()
 *       BetterSeoC?.updateCharacterCounter()           — optional counter module
 *       BetterSeoTitle.enqueueUnregisteredInputTrigger() — title module
 *   - Custom events consumed:
 *       'change' on title reference spans (triggers placeholder refresh)
 *       'change' on description reference span (triggers placeholder refresh)
 *       'input'  on OG/Twitter title inputs (triggers placeholder + counter refresh)
 *       'input'  on OG/Twitter description inputs (triggers placeholder + counter refresh)
 */

'use strict';

/**
 * Social meta input group manager.
 *
 * @namespace BetterSeoSocial
 */
window.BetterSeoSocial = ( function () {

	/**
	 * Map of group key → { group, inputs, refs } instance data.
	 *
	 * @type {Map<string, { group: string, inputs: Object, refs: Object }>}
	 */
	const inputInstances = new Map();

	/**
	 * Map of group key → state object for that group.
	 *
	 * @type {Object.<string, Object>}
	 */
	const states = {};

	// ─── STATE MANAGEMENT ──────────────────────────────────────────────────────

	/**
	 * Reacts to a state change for the given group and part.
	 *
	 * When `addAdditions` changes, re-triggers the title input so that
	 * placeholder values are recalculated with the new additions state.
	 *
	 * @param {string} group The social input group key.
	 * @param {string} part  The state property that changed.
	 * @return {void}
	 */
	function _tickState( group, part ) {
		if ( 'addAdditions' === part ) {
			const titleRef = getInputInstance( group )?.refs.title?.dataset?.for;
			if ( titleRef ) {
				BetterSeoTitle.enqueueUnregisteredInputTrigger( titleRef );
			}
		}
	}

	/**
	 * Returns the state value for the given group and optional part.
	 *
	 * @param {string}          group The social input group key.
	 * @param {string|undefined} part  The state property to retrieve. Omit to get the full state object.
	 * @return {*} The state value, or the full state object if part is omitted.
	 */
	function getStateOf( group, part ) {
		return part ? states[ group ]?.[ part ] : states[ group ];
	}

	/**
	 * Updates a single state property for the given group and triggers side effects.
	 *
	 * No-ops if the value is unchanged.
	 *
	 * @param {string} group The social input group key.
	 * @param {string} part  The state property to update.
	 * @param {*}      value The new value.
	 * @return {void}
	 */
	function updateStateOf( group, part, value ) {

		if ( states[ group ][ part ] === value ) {
			return;
		}

		states[ group ][ part ] = value;

		_tickState( group, part );
	}

	/**
	 * Updates a state property across all registered groups, optionally
	 * excluding one or more groups by key.
	 *
	 * @param {string}          part   The state property to update.
	 * @param {*}               value  The new value.
	 * @param {string|string[]} except Group key(s) to skip. May be a single string or an array.
	 * @return {void}
	 */
	function updateStateAll( part, value, except ) {

		const excluded = Array.isArray( except ) ? except : [ except ];

		for ( const [ , { group } ] of inputInstances ) {
			if ( excluded.includes( group ) ) {
				continue;
			}
			updateStateOf( group, part, value );
		}
	}

	// ─── INSTANCE MANAGEMENT ───────────────────────────────────────────────────

	/**
	 * Registers a social input group and initialises its state and event listeners.
	 *
	 * Locates OG/Twitter title and description inputs via data attributes, and
	 * resolves reference elements by ID. Initialises title and description action
	 * listeners for the group.
	 *
	 * @param {string} group    The unique group key for this social input set.
	 * @param {string} titleRef The element ID of the associated meta title input.
	 * @param {string} descRef  The element ID of the associated meta description input.
	 * @return {{ group: string, inputs: Object, refs: Object }} The registered instance.
	 */
	function setInputInstance( group, titleRef, descRef ) {

		/**
		 * Finds a social input element by group and type data attributes.
		 *
		 * @param {string} type The social input type (ogTitle, twTitle, ogDesc, twDesc).
		 * @return {HTMLElement|null}
		 */
		const _getElement = type => document.querySelector(
			`[data-better-seo-social-group="${group}"][data-better-seo-social-type="${type}"]`,
		);

		const inputs = {
			ogTitle: _getElement( 'ogTitle' ),
			twTitle: _getElement( 'twTitle' ),
			ogDesc:  _getElement( 'ogDesc' ),
			twDesc:  _getElement( 'twDesc' ),
		};

		const refs = {
			titleInput: document.getElementById( titleRef ),
			descInput:  document.getElementById( descRef ),
			title:      document.getElementById( `better-seo-title-reference_${titleRef}` ),
			titleNa:    document.getElementById( `better-seo-title-noadditions-reference_${titleRef}` ),
			desc:       document.getElementById( `better-seo-description-reference_${descRef}` ),
		};

		inputInstances.set( group, { group, inputs, refs } );

		states[ group ] = {
			defaults: {
				ogTitle: '',
				twTitle: '',
				ogDesc:  '',
				twDesc:  '',
			},
			inputLocks: {
				ogTitle: false,
				twTitle: false,
				ogDesc:  false,
				twDesc:  false,
			},
			placeholderLocks: {
				ogTitle: false,
				twTitle: false,
				ogDesc:  false,
				twDesc:  false,
			},
		};

		_loadTitleActions( group );
		_loadDescriptionActions( group );

		return getInputInstance( group );
	}

	/**
	 * Returns the registered instance data for the given group.
	 *
	 * @param {string} group The social input group key.
	 * @return {{ group: string, inputs: Object, refs: Object }|undefined} The instance, or undefined if not registered.
	 */
	function getInputInstance( group ) {
		return inputInstances.get( group );
	}

	// ─── TITLE ACTIONS ─────────────────────────────────────────────────────────

	/**
	 * Attaches title placeholder and counter update listeners for the given group.
	 *
	 * Uses a generator-based cascade to resolve the active display value for
	 * OG and Twitter title inputs, falling back through: custom input → OG input
	 * → meta title reference → title-without-additions reference.
	 *
	 * @param {string} group The social input group key.
	 * @return {void}
	 */
	function _loadTitleActions( group ) {

		const { inputs, refs } = getInputInstance( group );

		/** @param {string} part @return {*} */
		const getState = part => getStateOf( group, part );

		/**
		 * Generator that yields candidate title values in cascade order.
		 * Falls through to the next case when the current value is empty.
		 *
		 * @generator
		 * @param {'twitter'|'og'|'meta'|'ref'} what The cascade starting point.
		 * @yields {string} A candidate title value.
		 */
		function* _generateActiveValue( what ) {
			const locks   = getState( 'inputLocks' );
			const phLocks = getState( 'placeholderLocks' );

			switch ( what ) {
				case 'twitter':
					yield locks.twTitle
						? getState( 'defaults' ).twTitle
						: ( inputs.twTitle?.value.trim() ?? '' );

					if ( locks.twTitle || phLocks.twTitle ) {
						yield getState( 'defaults' ).twTitle;
						break;
					}
					// Falls through to 'og' when Twitter value is empty.
				case 'og':
					yield locks.ogTitle
						? getState( 'defaults' ).ogTitle
						: ( inputs.ogTitle?.value.trim() ?? '' );

					if ( locks.ogTitle || phLocks.ogTitle ) {
						yield getState( 'defaults' ).ogTitle;
						break;
					}
					// Falls through to 'meta' when OG value is empty.
				case 'meta':
					// Title complexity is handled entirely via the reference element.
				case 'ref':
					yield getState( 'addAdditions' )
						? ( refs.title?.innerHTML ?? '' )
						: ( refs.titleNa?.innerHTML ?? '' );
					break;
			}
		}

		/**
		 * Returns the first non-empty candidate value from the cascade generator.
		 *
		 * @param {'twitter'|'og'|'meta'|'ref'} what The cascade starting point.
		 * @return {string} The resolved active value, or empty string if none found.
		 */
		const getActiveValue = what => {
			const generator = _generateActiveValue( what );
			let val         = '';

			while ( 'undefined' !== typeof val && ! val.length ) {
				val = generator.next().value;
				if ( val?.length ) {
					val = BetterSeo.sDoubleSpace( BetterSeo.sTabs( BetterSeo.sSingleLine( val ) ) );
				}
			}

			return val?.length ? val : '';
		};

		/**
		 * Updates the placeholder text for OG and Twitter title inputs.
		 *
		 * @return {void}
		 */
		const setPlaceholders = () => {
			const locks   = getState( 'inputLocks' );
			const phLocks = getState( 'placeholderLocks' );

			if ( inputs.ogTitle ) {
				inputs.ogTitle.placeholder = ( locks.ogTitle || phLocks.ogTitle )
					? BetterSeo.decodeEntities( getState( 'defaults' ).ogTitle )
					: BetterSeo.decodeEntities( getActiveValue( 'meta' ) );
			}

			if ( inputs.twTitle ) {
				inputs.twTitle.placeholder = ( locks.twTitle || phLocks.twTitle )
					? BetterSeo.decodeEntities( getState( 'defaults' ).twTitle )
					: BetterSeo.decodeEntities( getActiveValue( 'og' ) );
			}
		};

		/**
		 * Updates the character counter for a social title input.
		 *
		 * @param {HTMLElement} target The input element.
		 * @param {string}      text   The text to measure.
		 * @param {string}      type   The counter context ('opengraph' or 'twitter').
		 * @return {void}
		 */
		const updateCounter = ( target, text, type ) => {
			const counter = document.getElementById( `${target.id}_chars` );
			if ( counter ) {
				BetterSeoC?.updateCharacterCounter( {
					e:     counter,
					text,
					field: 'title',
					type,
				} );
			}
		};

		/**
		 * Refreshes character counters for both OG and Twitter title inputs.
		 *
		 * @return {void}
		 */
		const updateSocialCounters = () => {
			if ( inputs.ogTitle ) {
				updateCounter( inputs.ogTitle, getActiveValue( 'og' ),      'opengraph' );
			}
			if ( inputs.twTitle ) {
				updateCounter( inputs.twTitle, getActiveValue( 'twitter' ), 'twitter' );
			}
		};

		/**
		 * Debounced handler that refreshes placeholders and counters when the
		 * title reference element changes (i.e. the meta title preview updates).
		 *
		 * @type {Function}
		 */
		const updateRefTitle = BetterSeoUtils.debounce(
			() => {
				setPlaceholders();
				updateSocialCounters();
			},
			1000 / 60, // ~60 fps
		);
		refs.title?.addEventListener( 'change', updateRefTitle );
		refs.titleNa?.addEventListener( 'change', updateRefTitle );

		/**
		 * Debounced handler that refreshes placeholders and counters when the
		 * user types in an OG or Twitter title input.
		 *
		 * @type {Function}
		 */
		const updateTitle = BetterSeoUtils.debounce(
			() => {
				setPlaceholders();
				updateSocialCounters();
			},
			1000 / 60, // ~60 fps
		);
		inputs.ogTitle?.addEventListener( 'input', updateTitle );
		inputs.twTitle?.addEventListener( 'input', updateTitle );
	}

	// ─── DESCRIPTION ACTIONS ───────────────────────────────────────────────────

	/**
	 * Attaches description placeholder and counter update listeners for the given group.
	 *
	 * Uses a generator-based cascade to resolve the active display value for
	 * OG and Twitter description inputs, falling back through: custom input →
	 * OG input → meta description input → description reference element.
	 *
	 * @param {string} group The social input group key.
	 * @return {void}
	 */
	function _loadDescriptionActions( group ) {

		const { inputs, refs } = getInputInstance( group );

		/** @param {string} part @return {*} */
		const getState = part => getStateOf( group, part );

		/**
		 * Generator that yields candidate description values in cascade order.
		 * Falls through to the next case when the current value is empty.
		 * The context parameter adjusts which default is used at the meta level.
		 *
		 * @generator
		 * @param {'twitter'|'og'|'meta'|'ref'} what    The cascade starting point.
		 * @param {'twitter'|'og'}              context  The social context for meta-level fallback.
		 * @yields {string} A candidate description value.
		 */
		function* _generateActiveValue( what, context ) {
			const locks   = getState( 'inputLocks' );
			const phLocks = getState( 'placeholderLocks' );

			switch ( what ) {
				case 'twitter':
					yield locks.twDesc
						? getState( 'defaults' ).twDesc
						: ( inputs.twDesc?.value.trim() ?? '' );

					if ( locks.twDesc || phLocks.twDesc ) {
						yield getState( 'defaults' ).twDesc;
						break;
					}
					// Falls through to 'og' when Twitter value is empty.
				case 'og':
					yield locks.ogDesc
						? getState( 'defaults' ).ogDesc
						: ( inputs.ogDesc?.value.trim() ?? '' );

					if ( locks.ogDesc || phLocks.ogDesc ) {
						yield getState( 'defaults' ).ogDesc;
						break;
					}
					// Falls through to 'meta' when OG value is empty.
				case 'meta':
					if ( ! refs.descInput?.value.length ) {
						if ( 'twitter' === context ) {
							yield getState( 'defaults' ).twDesc;
						} else if ( 'og' === context ) {
							yield getState( 'defaults' ).ogDesc;
						}
					}
					// Falls through to 'ref' when meta description input is empty.
				case 'ref':
					yield refs.desc?.innerHTML ?? '';
					break;
			}
		}

		/**
		 * Returns the first non-empty candidate value from the cascade generator.
		 *
		 * @param {'twitter'|'og'|'meta'|'ref'} what    The cascade starting point.
		 * @param {'twitter'|'og'}              context  The social context for meta-level fallback.
		 * @return {string} The resolved active value, or empty string if none found.
		 */
		const getActiveValue = ( what, context ) => {
			const generator = _generateActiveValue( what, context );
			let val         = '';

			// 'undefined' signals the generator is exhausted.
			while ( 'undefined' !== typeof val && ! val.length ) {
				val = generator.next().value;
				if ( val?.length ) {
					val = BetterSeo.sDoubleSpace( BetterSeo.sTabs( BetterSeo.sSingleLine( val ) ) );
				}
			}

			return val?.length ? val : '';
		};

		/**
		 * Updates the placeholder text for OG and Twitter description inputs.
		 *
		 * @return {void}
		 */
		const setPlaceholders = () => {
			const locks   = getState( 'inputLocks' );
			const phLocks = getState( 'placeholderLocks' );

			if ( inputs.ogDesc ) {
				inputs.ogDesc.placeholder = ( locks.ogDesc || phLocks.ogDesc )
					? BetterSeo.decodeEntities( getState( 'defaults' ).ogDesc )
					: BetterSeo.decodeEntities( getActiveValue( 'meta', 'og' ) );
			}

			if ( inputs.twDesc ) {
				inputs.twDesc.placeholder = ( locks.twDesc || phLocks.twDesc )
					? BetterSeo.decodeEntities( getState( 'defaults' ).twDesc )
					: BetterSeo.decodeEntities( getActiveValue( 'og', 'twitter' ) );
			}
		};

		/**
		 * Updates the character counter for a social description input.
		 *
		 * @param {HTMLElement} target The input element.
		 * @param {string}      text   The text to measure.
		 * @param {string}      type   The counter context ('opengraph' or 'twitter').
		 * @return {void}
		 */
		const updateCounter = ( target, text, type ) => {
			const counter = document.getElementById( `${target.id}_chars` );
			if ( counter ) {
				BetterSeoC?.updateCharacterCounter( {
					e:     counter,
					text,
					field: 'description',
					type,
				} );
			}
		};

		/**
		 * Refreshes character counters for both OG and Twitter description inputs.
		 *
		 * @return {void}
		 */
		const updateSocialCounters = () => {
			if ( inputs.ogDesc ) {
				updateCounter( inputs.ogDesc, getActiveValue( 'og',      'og' ),      'opengraph' );
			}
			if ( inputs.twDesc ) {
				updateCounter( inputs.twDesc, getActiveValue( 'twitter', 'twitter' ), 'twitter' );
			}
		};

		/**
		 * Debounced handler that refreshes placeholders and counters when the
		 * description reference element changes (i.e. the meta description preview updates).
		 *
		 * @type {Function}
		 */
		const updateRefDesc = BetterSeoUtils.debounce(
			() => {
				setPlaceholders();
				updateSocialCounters();
			},
			1000 / 60, // ~60 fps
		);
		refs.desc?.addEventListener( 'change', updateRefDesc );

		/**
		 * Debounced handler that refreshes placeholders and counters when the
		 * user types in an OG or Twitter description input.
		 *
		 * @type {Function}
		 */
		const updateDesc = BetterSeoUtils.debounce(
			() => {
				setPlaceholders();
				updateSocialCounters();
			},
			1000 / 60, // ~60 fps
		);
		inputs.ogDesc?.addEventListener( 'input', updateDesc );
		inputs.twDesc?.addEventListener( 'input', updateDesc );
	}

	// ─── PUBLIC API ────────────────────────────────────────────────────────────

	return {
		getStateOf,
		updateStateOf,
		updateStateAll,
		setInputInstance,
		getInputInstance,
	};

}() );