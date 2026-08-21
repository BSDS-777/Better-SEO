/**
 * Better SEO — Canonical URL Field Module
 *
 * Manages canonical URL input fields across the post edit, term edit,
 * and settings pages. Builds dynamic URL placeholders from the WordPress
 * permalink structure and registered slug data.
 *
 * Exposed as: window.BetterSeoCanonical
 *
 * Usage:
 *   BetterSeoCanonical.setInputElement( element );
 *   BetterSeoCanonical.updateStateOf( id, 'defaultCanonical', url );
 *   BetterSeoCanonical.sanitizeSlug( slug );
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - L10n object:  BetterSeoCanonicalL10n (must be registered in get_canonical_scripts() in class-loader.php)
 *   - L10n keys:    BetterSeoCanonicalL10n.params.{rootUrl, usingPermalinks, rewrite, allowCanonicalURLNotationTracker}
 *   - No AJAX actions — canonical URL is computed client-side from permalink structure data
 *   - Custom events consumed:
 *       (none — this module dispatches 'input' events on canonical fields)
 */

'use strict';

/**
 * Canonical URL field management module.
 *
 * @namespace BetterSeoCanonical
 */
window.BetterSeoCanonical = ( function () {

	/**
	 * Localisation data passed from PHP via wp_localize_script().
	 *
	 * @type {Object}
	 */
	const l10n = BetterSeoCanonicalL10n;

	/**
	 * Whether the site uses pretty permalinks.
	 *
	 * @type {boolean}
	 */
	const usingPermalinks = l10n.params.usingPermalinks;

	/**
	 * Map of input element ID → Element for all registered canonical inputs.
	 *
	 * @type {Map<string, Element>}
	 */
	const canonicalInputInstances = new Map();

	/**
	 * Per-input state objects keyed by element ID.
	 *
	 * @type {Object.<string, Object>}
	 */
	const states = {};

	// ─── INPUT REGISTRATION ────────────────────────────────────────────────────

	/**
	 * Registers a canonical URL input element and initialises its state.
	 *
	 * @param {Element} element The canonical URL input element.
	 * @return {Element} The registered element.
	 */
	function setInputElement( element ) {
		canonicalInputInstances.set( element.id, element );
		states[ element.id ] = {
			allowReferenceChange: true,
			defaultCanonical:     '',
			showUrlPlaceholder:   true,
			preferredScheme:      '',
			urlStructure:         '',
			urlDataParts:         {},
		};
		_loadCanonicalActions( element );
		return getInputElement( element.id );
	}

	/**
	 * Returns the registered canonical input element for the given ID.
	 *
	 * @param {string} id The element ID.
	 * @return {Element|undefined}
	 */
	function getInputElement( id ) {
		return canonicalInputInstances.get( id );
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

		// All state changes trigger a placeholder re-render.
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

		for ( const element of canonicalInputInstances.values() ) {
			if ( exceptions.includes( element.id ) ) {
				continue;
			}
			updateStateOf( element.id, part, value );
		}
	}

	// ─── SLUG SANITIZATION (Unicode-aware, ES2025) ─────────────────────────────

	/**
	 * Sanitizes a string for use as a URL slug.
	 *
	 * Uses Unicode normalization (NFD) to decompose accented characters,
	 * strips diacritical marks, then applies WordPress-compatible slug rules.
	 * This replaces the legacy Latin-1 character map approach with a modern
	 * Unicode-aware implementation.
	 *
	 * @param {string} slug The raw string to sanitize.
	 * @return {string} The URL-encoded sanitized slug.
	 */
	function sanitizeSlug( slug ) {

		if ( 'string' !== typeof slug || ! slug.length ) {
			return slug;
		}

		// Step 1: Unicode normalization — decompose accented chars (é → e + combining accent)
		slug = slug
			.normalize( 'NFD' )
			// Step 2: Strip all diacritical marks (Unicode property escape, requires 'u' flag)
			.replace( /\p{Diacritic}/gu, '' );

		// Step 3: Handle known multi-char ligature/digraph substitutions
		// (NFD doesn't decompose these — they need explicit mapping)
		const digraphs = [
			[ '', 'OE' ], [ '', 'oe' ],
			[ 'Æ', 'AE' ], [ 'Ð', 'DH' ],
			[ 'Þ', 'TH' ], [ 'ß', 'ss' ],
			[ 'æ', 'ae' ], [ 'ð', 'dh' ],
			[ 'þ', 'th' ],
		];

		for ( const [ char, replacement ] of digraphs ) {
			slug = slug.replaceAll( char, replacement );
		}

		// Step 4: Apply WordPress-compatible slug transformation rules
		slug = slug.toLowerCase()
			.replace( /<\/?[^>]+(>|$)/g, '' )                    // Strip HTML tags
			.replace( /%([a-f0-9][a-f0-9])/g, '---$1---' )       // Preserve escaped octets
			.replace( /%|"/g, '' )                                 // Remove bare percent signs and quotes
			.replace( /---([a-f0-9][a-f0-9])---/g, '%$1' )        // Restore octets
			.replace( /\s+/g, '-' )                                // Spaces → hyphens
			.replace( /%c2%a0|%e2%80%93|%e2%80%94|&nbsp;|&#160;|&ndash;|&#8211;|&mdash;|&#8212;|\//g, '-' )
			.replace( /%c3%97/g, 'x' )                            // &times; → 'x'
			.replace( /%c2%ad|%c2%a1|%c2%bf|%c2%ab|%c2%bb|%e2%80%b9|%e2%80%ba|%e2%80%98|%e2%80%99|%e2%80%9c|%e2%80%9d|%e2%80%9a|%e2%80%9b|%e2%80%9e|%e2%80%9f|%e2%80%a2|%c2%a9|%c2%ae|%c2%b0|%e2%80%a6|%e2%84%a2|%c2%b4|%cb%8a|%cc%81|%cd%81|%cc%80|%cc%84|%cc%8c|%e2%80%8b|%e2%80%8c|%e2%80%8d|%e2%80%8e|%e2%80%8f|%e2%80%aa|%e2%80%ab|%e2%80%ac|%e2%80%ad|%e2%80%ae|%ef%bb%bf|%ef%bf%bc/g, '' )
			.replace( /%e2%80%80|%e2%80%81|%e2%80%82|%e2%80%83|%e2%80%84|%e2%80%85|%e2%80%86|%e2%80%87|%e2%80%88|%e2%80%89|%e2%80%8a|%e2%80%a8|%e2%80%a9|%e2%80%af/g, '-' )
			.replace( /&.+?;/g, '' )                               // Remove HTML entities
			.replace( /\./g, '-' )                                 // Dots → hyphens
			.replace( /[^%a-z0-9 _-]+/g, '' )                     // Remove non-alphanumeric
			.replace( /-+/g, '-' )                                 // Collapse multiple hyphens
			.replace( /^-+|-+$/g, '' );                            // Trim leading/trailing hyphens

		return encodeURIComponent( slug );
	}

	// ─── STRUCTURE HELPERS ─────────────────────────────────────────────────────

	/**
	 * Returns whether the URL structure for the given input includes the given code(s).
	 *
	 * @param {string}          id   The element ID.
	 * @param {string|string[]} code Permalink structure code(s) to check.
	 * @return {boolean}
	 */
	function structIncludes( id, code ) {

		const urlStructure = getStateOf( id, 'urlStructure' );

		if ( Array.isArray( code ) ) {
			return code.some( c => urlStructure.includes( c ) );
		}

		return urlStructure.includes( code );
	}

	// ─── PLACEHOLDER GENERATION ────────────────────────────────────────────────

	/**
	 * Updates the canonical input's placeholder with the computed URL.
	 *
	 * Builds the URL from the permalink structure and registered slug data parts,
	 * then uses URL.parse() (ES2024) to construct the final absolute URL.
	 *
	 * @param {InputEvent} event
	 * @return {void}
	 */
	function _updatePlaceholder( event ) {

		let placeholder = '';
		const id        = event.target.id;

		if ( getStateOf( id, 'showUrlPlaceholder' ) ) {
			const rootUrl         = l10n.params.rootUrl;
			const usingPermalinks = l10n.params.usingPermalinks;

			if (
				   l10n.params.allowCanonicalURLNotationTracker
				&& usingPermalinks // TODO: Remove when non-permalink dynamic support is implemented.
				&& rootUrl.host
				&& getStateOf( id, 'allowReferenceChange' )
			) {
				const rewrite        = l10n.params.rewrite;
				const urlStructure   = getStateOf( id, 'urlStructure' );
				const urlDataParts   = getStateOf( id, 'urlDataParts' );
				const preferredScheme = getStateOf( id, 'preferredScheme' );
				const queryReplace   = usingPermalinks ? [] : rewrite.queryReplace;

				let struct = urlStructure;

				for ( const [ index, code ] of rewrite.code.entries() ) {
					if ( ! struct.includes( code ) ) {
						continue;
					}

					const replacement = ( code in urlDataParts )
						? urlDataParts[ code ]
						: null;

					if ( null !== replacement ) {
						struct = struct.replace(
							code,
							( queryReplace[ index ] ?? '' ) + replacement,
						);
					} else {
						struct = struct.replace( code, '' );
					}
				}

				struct = `${rootUrl.path}/${struct}`
					.replace( /\/{2,}/g, '/' )
					.replace( /\s+/g, '-' );

				const placeholderUrl = URL.parse(
					struct,
					`${rootUrl.scheme}://${rootUrl.host}${rootUrl.port ? `:${rootUrl.port}` : ''}`,
				);

				placeholderUrl.protocol = `${preferredScheme}:`;

				placeholder = placeholderUrl.href;
			} else {
				placeholder = getStateOf( id, 'defaultCanonical' );
			}
		}

		event.target.placeholder = BetterSeo.decodeEntities( placeholder );
	}

	// ─── INPUT TRIGGERING ──────────────────────────────────────────────────────

	/**
	 * Dispatches an 'input' event on the canonical field for the given ID,
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
			for ( const element of canonicalInputInstances.values() ) {
				if ( element.id ) {
					triggerInput( element.id );
				}
			}
		}
	}

	/**
	 * @param {InputEvent} event
	 * @return {void}
	 */
	function _onUpdateCanonicalUrlsTrigger( event ) {
		_updatePlaceholder( event );
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
	 * @param {string} id The element ID.
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
	 * @param {string} id The element ID.
	 * @return {void}
	 */
	function enqueueTriggerUnregisteredInput( id ) {
		if ( id in _unregisteredTriggerBuffer ) {
			clearTimeout( _unregisteredTriggerBuffer[ id ] );
		}
		_unregisteredTriggerBuffer[ id ] = setTimeout( () => triggerUnregisteredInput( id ), 1000 / 60 ); // ~60fps
	}

	// ─── INITIALISATION ────────────────────────────────────────────────────────

	/**
	 * Attaches event listeners to a canonical input element.
	 *
	 * @param {Element} canonicalInput
	 * @return {void}
	 */
	function _loadCanonicalActions( canonicalInput ) {

		if ( ! ( canonicalInput instanceof Element ) ) {
			return;
		}

		canonicalInput.addEventListener( 'input', _onUpdateCanonicalUrlsTrigger );

		enqueueTriggerUnregisteredInput( canonicalInput.id );
	}

	// ─── PUBLIC API ────────────────────────────────────────────────────────────

	return {
		setInputElement,
		getInputElement,
		getStateOf,
		updateStateOf,
		updateStateAll,
		structIncludes,
		sanitizeSlug,
		triggerInput,
		enqueueTriggerInput,
		triggerUnregisteredInput,
		enqueueTriggerUnregisteredInput,
		l10n,
		usingPermalinks,
	};

}() );