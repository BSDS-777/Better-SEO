/**
 * Better SEO — Core Module
 *
 * The root namespace for all Better SEO JavaScript. Provides shared utility
 * functions (string manipulation, entity encoding, AJAX loader state, DOM
 * helpers) and the plugin lifecycle event system (onload, ready, interactive,
 * resize). All other Better SEO JS modules depend on this file.
 *
 * Exposed as: window.BetterSeo
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - L10n object:    BetterSeoL10n (registered in class-loader.php get_common_scripts())
 *   - L10n keys:
 *       BetterSeoL10n.nonces.edit_posts      — nonce for edit_posts capability
 *       BetterSeoL10n.nonces.upload_files    — nonce for upload_files capability
 *       BetterSeoL10n.nonces.manage_options  — nonce for manage_options capability
 *       BetterSeoL10n.states.debug           — SCRIPT_DEBUG flag
 *   - CSS classes managed by this module:
 *       better-seo-loading   — AJAX in-progress state
 *       better-seo-success   — AJAX success state
 *       better-seo-error     — AJAX error state
 *       better-seo-unknown   — AJAX unknown state
 *   - Custom events dispatched on document.body:
 *       'better-seo-onload'       — fired when DOM is ready (DOMContentLoaded / load)
 *       'better-seo-ready'        — fired immediately after onload
 *       'better-seo-interactive'  — fired when the page becomes interactive (load or 100 ms timeout)
 *       'better-seo-reset-notice-listeners' — debounced notice reset trigger
 *   - Custom events dispatched on window:
 *       'better-seo-resize'       — debounced/throttled window resize event
 *   - Dependencies:
 *       BetterSeoUtils.debounce() — debounce helper (must load before this file)
 *       BetterSeoUI.fadeOut()     — fade-out animation helper
 */

'use strict';

/**
 * Better SEO core namespace.
 *
 * @namespace BetterSeo
 */
window.BetterSeo = ( function () {

	/**
	 * Localisation data passed from PHP via wp_localize_script().
	 *
	 * @type {{ nonces: Object, states: Object }}
	 */
	const l10n = BetterSeoL10n;

	// ─── STRING UTILITIES ──────────────────────────────────────────────────────

	/**
	 * Strips all HTML tags from the given string.
	 *
	 * @param {string} str The input string.
	 * @return {string} The string with all HTML tags removed, or empty string if input is empty.
	 */
	function stripTags( str ) {
		return ( str.length && str.replace( /(<([^>]+)?>?)/ig, '' ) ) || '';
	}

	/** @type {DOMParser|undefined} Lazily initialised DOMParser instance for entity decoding. */
	let _decodeEntitiesDOMParser;

	/**
	 * Characters that must be escaped before passing to DOMParser to prevent
	 * them from being interpreted as HTML tags or escape sequences.
	 *
	 * @type {Object.<string, string>}
	 */
	const _decodeEntitiesMap = {
		'<':  '&#x3C;',
		'>':  '&#x3E;',
		'\\': '&#x5C;',
	};

	/**
	 * Decodes HTML entities in the given string using a DOMParser.
	 *
	 * Angle brackets and backslashes are pre-escaped to prevent them from being
	 * interpreted as HTML structure during parsing.
	 *
	 * @param {string} str The HTML-encoded string to decode.
	 * @return {string} The decoded plain-text string, or empty string if input is empty.
	 */
	function decodeEntities( str ) {

		if ( ! str?.length ) {
			return '';
		}

		_decodeEntitiesDOMParser ??= new DOMParser();

		return _decodeEntitiesDOMParser.parseFromString(
			str.replace?.( /[<>\\]/g, m => _decodeEntitiesMap[ m ] ) || '',
			'text/html',
		).documentElement.textContent;
	}

	/**
	 * HTML-escapes the given string for safe insertion into HTML attributes or content.
	 *
	 * Escapes: & < > " ' \ /
	 *
	 * @param {string} str The string to escape.
	 * @return {string} The HTML-escaped string, or empty string if input is empty.
	 */
	function escapeString( str ) {

		if ( ! str?.length ) {
			return '';
		}

		const map = {
			'&':  '&#x26;',
			'<':  '&#x3C;',
			'>':  '&#x3E;',
			'"':  '&#x22;',
			"'":  '&#x27;',
			'\\': '&#x5C;',
			'/':  '&#x2F;',
		};

		return str.replace?.( /[&<>"'\\\/]/g, m => map[ m ] ) || '';
	}

	/**
	 * Converts HTML-encoded ampersands to literal ampersands.
	 *
	 * @deprecated 1.0.0 No replacement — avoid encoding ampersands in source data.
	 * @param {string} str The string to process.
	 * @return {string} The string with HTML-encoded ampersands replaced by literal `&`.
	 */
	function ampHTMLtoText( str ) {
		deprecatedFunc( 'BetterSeo.ampHTMLtoText', '1.0.0' );
		return str.replace( /&amp;|&#x0{0,3}26;|&#38;/gi, '&' );
	}

	/**
	 * Collapses two or more consecutive whitespace characters into a single space.
	 *
	 * @param {string} str The input string.
	 * @return {string} The string with consecutive whitespace collapsed.
	 */
	function sDoubleSpace( str ) {
		return str.replace( /\s{2,}/g, ' ' );
	}

	/**
	 * Replaces vertical whitespace characters (newline, vertical tab, form feed,
	 * carriage return) with a single space.
	 *
	 * @param {string} str The input string.
	 * @return {string} The single-line string.
	 */
	function sSingleLine( str ) {
		return str.replace( /[\x0A\x0B\x0C\x0D]/g, ' ' );
	}

	/**
	 * Replaces horizontal tab characters with a single space.
	 *
	 * @param {string} str The input string.
	 * @return {string} The string with tabs replaced by spaces.
	 */
	function sTabs( str ) {
		return str.replace( /\x09/g, ' ' );
	}

	/**
	 * Returns the string if it has length, otherwise returns null.
	 *
	 * @param {string|null|undefined} str The string to test.
	 * @return {string|null} The original string, or null if empty/falsy.
	 */
	function coalesceStrlen( str ) {
		return str?.length ? str : null;
	}

	/**
	 * Returns the visible character length of the given HTML-encoded string.
	 *
	 * Creates a temporary span element, sets its innerHTML to the escaped string,
	 * and reads the text node length. This correctly handles multi-byte characters
	 * and HTML entities.
	 *
	 * @param {string} str The HTML-encoded string to measure.
	 * @return {number} The visible character count, or 0 if the string is empty.
	 */
	function getStringLength( str ) {
		let length = 0;

		if ( str.length ) {
			const e   = document.createElement( 'span' );
			e.innerHTML = escapeString( str ).trim();
			// Trimming can produce empty child nodes — guard against undefined.
			length = e.childNodes?.[0]?.nodeValue?.length ?? 0;
		}

		return +length;
	}

	// ─── DOM UTILITIES ─────────────────────────────────────────────────────────

	/**
	 * Sets the selected option of a select element by value, label, or innerHTML.
	 *
	 * Tries to match by value first (loose equality), then by label or innerHTML.
	 *
	 * @param {HTMLSelectElement} element The select element to update.
	 * @param {string|number}     value   The value to select.
	 * @return {void}
	 */
	function selectByValue( element, value ) {

		if ( ! ( element instanceof HTMLSelectElement ) ) {
			return;
		}

		// Try matching by value first (loose equality to handle string/number coercion).
		for ( const option of element.options ) {
			if ( value == option.value ) { // eslint-disable-line eqeqeq
				element.selectedIndex = option.index;
				return;
			}
		}

		// Fall back to matching by label or innerHTML.
		for ( const option of element.options ) {
			if ( value == option.label || value == option.innerHTML ) { // eslint-disable-line eqeqeq
				element.selectedIndex = option.index;
				return;
			}
		}
	}

	// ─── AJAX UTILITIES ────────────────────────────────────────────────────────

	/**
	 * Attempts to parse a wp_send_json_success response as JSON if not already parsed.
	 *
	 * @param {*} response The raw AJAX response value.
	 * @return {*} The parsed response object, or the original value if parsing fails.
	 */
	function convertJSONResponse( response ) {

		if ( 1 === response?.json ) {
			return response;
		}

		const _response = response;

		try {
			response = JSON.parse( response );
		} catch {
			response = _response;
		}

		return response;
	}

	/**
	 * Adds the 'better-seo-loading' CSS class to the target element(s).
	 *
	 * Accepts a CSS selector string, a jQuery element, or a DOM element.
	 *
	 * @param {string|jQuery|HTMLElement} target The target element(s).
	 * @return {void}
	 */
	function setAjaxLoader( target ) {

		if ( 'string' === typeof target ) {
			for ( const el of document.querySelectorAll( target ) ) {
				setAjaxLoader( el );
			}
			return;
		}

		// Backward compatibility: unwrap jQuery element.
		if ( target?.[0] ) {
			target = target[0];
		}

		target?.classList.add( 'better-seo-loading' );
	}

	/**
	 * Removes the loading class and adds a success or error class to the target element(s),
	 * then fades the element out after a delay.
	 *
	 * Accepts a CSS selector string, a jQuery element, or a DOM element.
	 *
	 * @param {string|jQuery|HTMLElement} target  The target element(s).
	 * @param {boolean}                   success Whether the AJAX call succeeded.
	 * @return {void}
	 */
	function unsetAjaxLoader( target, success ) {

		if ( 'string' === typeof target ) {
			for ( const el of document.querySelectorAll( target ) ) {
				unsetAjaxLoader( el, success );
			}
			return;
		}

		const newClass = success ? 'better-seo-success' : 'better-seo-error';
		const fadeTime = success ? 2500 : 5000;

		// Backward compatibility: unwrap jQuery element.
		if ( target?.[0] ) {
			target = target[0];
		}

		if ( target ) {
			target.classList.remove(
				'better-seo-loading',
				'better-seo-error',
				'better-seo-success',
				'better-seo-unknown',
			);
			target.classList.add( newClass );
			BetterSeoUI.fadeOut( target, fadeTime );
		}
	}

	/**
	 * Resets an AJAX loader element to its initial state by removing all state
	 * classes, clearing child nodes, and resetting inline animation styles.
	 *
	 * Accepts a CSS selector string, a jQuery element, or a DOM element.
	 *
	 * @param {string|jQuery|HTMLElement} target The target element(s).
	 * @return {void}
	 */
	function resetAjaxLoader( target ) {

		if ( 'string' === typeof target ) {
			for ( const el of document.querySelectorAll( target ) ) {
				resetAjaxLoader( el );
			}
			return;
		}

		// Backward compatibility: unwrap jQuery element.
		if ( target?.[0] ) {
			target = target[0];
		}

		target.replaceChildren();
		target.style.animation = null;
		target.style.opacity   = '1';
		target.classList.remove(
			'better-seo-loading',
			'better-seo-error',
			'better-seo-success',
			'better-seo-unknown',
		);
	}

	// ─── DEPRECATION ───────────────────────────────────────────────────────────

	/**
	 * Logs a deprecation warning to the browser console.
	 *
	 * The warning is removed during minification via the removeConsole Babel plugin.
	 *
	 * @param {string}           name        The deprecated function or property name.
	 * @param {string|undefined} version     The version in which it was deprecated.
	 * @param {string|undefined} replacement Optional replacement function or property name.
	 * @return {void}
	 */
	function deprecatedFunc( name, version, replacement ) {
		console.warn(
			`[DEPRECATED]: ${name} is deprecated${version ? ` since Better SEO ${version}` : ''}.${replacement ? ` Use ${replacement} instead.` : ''}`,
		);
	}

	// ─── DEFERRED EVENT DISPATCH ───────────────────────────────────────────────

	/**
	 * Set of [element, eventName] pairs queued for dispatch at the interactive state.
	 *
	 * @type {Set<[HTMLElement, string]>}
	 */
	let _dispatchEvents      = new Set();
	let _loadedDispatchEvent = false;

	/**
	 * Queues an event to be dispatched on the given element when the page becomes
	 * interactive (i.e. when the 'better-seo-interactive' event fires).
	 *
	 * Used to trigger initial state calculations after all scripts have loaded.
	 *
	 * @param {HTMLElement} element   The element to dispatch the event on.
	 * @param {string}      eventName The event name to dispatch.
	 * @return {void}
	 */
	function dispatchAtInteractive( element, eventName ) {

		_dispatchEvents.add( [ element, eventName ] );

		if ( ! _loadedDispatchEvent ) {
			document.body.addEventListener( 'better-seo-interactive', _loopDispatchAtInteractive );
			_loadedDispatchEvent = true;
		}
	}

	/**
	 * Dispatches all queued events registered via dispatchAtInteractive().
	 *
	 * @return {void}
	 */
	function _loopDispatchAtInteractive() {
		for ( const [ element, eventName ] of _dispatchEvents ) {
			element.dispatchEvent( new Event( eventName ) );
		}
	}

	// ─── NOTICE RESET ──────────────────────────────────────────────────────────

	/**
	 * Debounced dispatcher for the 'better-seo-reset-notice-listeners' event.
	 *
	 * Debounced at 100 ms — low enough not to cause ignored clicks,
	 * high enough not to cause lag.
	 *
	 * @type {Function}
	 */
	const triggerNoticeReset = BetterSeoUtils.debounce(
		() => {
			document.body.dispatchEvent( new CustomEvent( 'better-seo-reset-notice-listeners' ) );
		},
		100,
	);

	// ─── RESIZE EVENT ──────────────────────────────────────────────────────────

	let _throttleResize = false;

	/**
	 * Debounced reset of the resize throttle flag.
	 *
	 * @type {Function}
	 */
	const _debounceResize = BetterSeoUtils.debounce(
		() => { _throttleResize = false; },
		50,
	);

	/**
	 * Debounced re-trigger of the resize handler (used when a resize is throttled).
	 *
	 * @type {Function}
	 */
	const _debounceResizeTrigger = BetterSeoUtils.debounce( _triggerResize, 50 );

	/**
	 * Dispatches the 'better-seo-resize' event on window, throttled to prevent
	 * excessive firing. Defers via debounce if a resize is already in progress.
	 *
	 * @return {void}
	 */
	function _triggerResize() {
		_debounceResize();

		if ( _throttleResize ) {
			_debounceResizeTrigger();
		} else {
			_throttleResize = true;
			window.dispatchEvent( new CustomEvent( 'better-seo-resize' ) );
		}
	}

	// ─── LIFECYCLE EVENTS ──────────────────────────────────────────────────────

	let isInteractive = false;

	/**
	 * Dispatches the 'better-seo-interactive' event on document.body.
	 * Fires at most once per page load.
	 *
	 * @return {void}
	 */
	function _triggerInteractive() {
		if ( ! isInteractive ) {
			isInteractive = true;
			document.body.dispatchEvent( new CustomEvent( 'better-seo-interactive' ) );
		}
	}

	/**
	 * Dispatches the 'better-seo-ready' event on document.body.
	 *
	 * @return {void}
	 */
	function _triggerReady() {
		document.body.dispatchEvent( new CustomEvent( 'better-seo-ready' ) );
	}

	/**
	 * Dispatches the 'better-seo-onload' event on document.body.
	 *
	 * @return {void}
	 */
	function _triggerOnLoad() {
		document.body.dispatchEvent( new CustomEvent( 'better-seo-onload' ) );
	}

	let _isReady = false;

	/**
	 * Runs the Better SEO ready sequence: dispatches onload, then ready, then
	 * schedules the interactive event. Runs at most once per page load.
	 *
	 * @return {void}
	 */
	function _doReady() {

		if ( _isReady ) {
			return;
		}

		document.removeEventListener( 'DOMContentLoaded', _doReady );
		document.removeEventListener( 'load', _doReady );

		_triggerOnLoad();
		_triggerReady();

		_isReady = true;

		// Trigger interactive on window load, or after 100 ms — whichever comes first.
		// The timeout handles slow connections where the load event may be delayed by images.
		document.addEventListener( 'load', _triggerInteractive );
		setTimeout( _triggerInteractive, 100 );
	}

	// ─── PUBLIC API ────────────────────────────────────────────────────────────

	return {
		/**
		 * Bootstraps the Better SEO lifecycle event system.
		 * Registers DOMContentLoaded / load listeners and the resize handler.
		 * Called automatically on script load.
		 *
		 * @return {void}
		 */
		load: () => {
			if ( 'complete' === document.readyState ) {
				// Document already loaded — defer to allow other scripts to register listeners first.
				setTimeout( _doReady );
			} else {
				document.addEventListener( 'DOMContentLoaded', _doReady );
				document.addEventListener( 'load', _doReady );
			}

			window.addEventListener( 'resize', _triggerResize );
		},

		// String utilities
		stripTags,
		decodeEntities,
		escapeString,
		ampHTMLtoText,
		sDoubleSpace,
		sSingleLine,
		sTabs,
		coalesceStrlen,
		getStringLength,

		// DOM utilities
		selectByValue,

		// AJAX utilities
		convertJSONResponse,
		setAjaxLoader,
		unsetAjaxLoader,
		resetAjaxLoader,

		// Misc
		deprecatedFunc,
		triggerNoticeReset,
		dispatchAtInteractive,

		// Localisation
		l10n,
	};

}() );

// Auto-initialise — bootstraps the lifecycle event system.
window.BetterSeo.load();