/**
 * Better SEO — Are You Sure (AYS) Unsaved Changes Guard
 *
 * Tracks whether any Better SEO settings fields have been modified and
 * warns the user before navigating away with unsaved changes.
 *
 * Exposed as: window.BetterSeoAys
 *
 * Usage:
 *   BetterSeoAys.load();                          // Initialize (called automatically)
 *   BetterSeoAys.areSettingsChanged();            // Returns true if unsaved changes exist
 *   BetterSeoAys.registerChange();                // Mark settings as changed
 *   BetterSeoAys.registerChangeListener( el, 'input' );
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - L10n object:  BetterSeoAysL10n (registered in class-loader.php)
 *   - i18n keys:    BetterSeoAysL10n.i18n.saveAlert
 *   - CSS classes:  .better-seo-metaboxes, #better-seo-inpost-box, .better-seo-term-meta
 *   - CSS class:    .better-seo-input-not-saved (inputs excluded from change tracking)
 *   - Custom events dispatched:
 *       'better-seo-interactive'           — fired by better-seo.js when UI is ready
 *       'better-seo-gutenberg-onsave-completed' — fired by gutenberg integration on save
 *       'better-seo-registered-ays-listeners'   — fired by this module after listener setup
 */

'use strict';

/**
 * Are You Sure unsaved changes guard module.
 *
 * @namespace BetterSeoAys
 */
window.BetterSeoAys = ( function () {

	/**
	 * Localisation strings passed from PHP via wp_localize_script().
	 *
	 * @type {{ i18n: { saveAlert: string } }}
	 */
	const l10n = BetterSeoAysL10n;

	/**
	 * Whether any tracked settings field has been changed since last save.
	 *
	 * @type {boolean}
	 */
	let _settingsChanged = false;

	/**
	 * Whether the default change/reset/unload listeners have been registered.
	 *
	 * @type {boolean}
	 */
	let _loadedListeners = false;

	/**
	 * Map of element → event type for all currently registered change listeners.
	 * Used to remove listeners once a change has been detected (one-shot pattern).
	 *
	 * @type {Map<Element, string>}
	 */
	const _registeredChangeListeners = new Map();

	// ─── DEPRECATED API ───────────────────────────────────────────────────────

	/**
	 * @deprecated 1.0.0 Use areSettingsChanged() instead.
	 * @return {boolean}
	 */
	function getChangedState() {
		BetterSeo.deprecatedFunc(
			'BetterSeoAys.getChangedState()',
			'1.0.0',
			'BetterSeoAys.areSettingsChanged()',
		);
		return areSettingsChanged();
	}

	// ─── PUBLIC STATE API ──────────────────────────────────────────────────────

	/**
	 * Returns whether any tracked settings field has unsaved changes.
	 *
	 * @return {boolean}
	 */
	function areSettingsChanged() {
		return _settingsChanged;
	}

	/**
	 * Marks settings as changed (dirty state).
	 *
	 * @return {void}
	 */
	function registerChange() {
		_settingsChanged = true;
	}

	/**
	 * Clears the changed state (clean state).
	 *
	 * @return {void}
	 */
	function deregisterChange() {
		_settingsChanged = false;
	}

	// ─── INTERNAL UTILITIES ────────────────────────────────────────────────────

	/**
	 * Normalises an element, Element, Document, selector string, or array of
	 * selectors into a flat array of DOM nodes.
	 *
	 * @param {Element|Document|string|string[]} elements
	 * @return {Element[]}
	 */
	function _getNodeArray( elements ) {
		return ( elements instanceof Element || elements instanceof Document )
			? [ elements ]
			: [ ...document.querySelectorAll( Array.isArray( elements ) ? elements.join( ', ' ) : elements ) ];
	}

	/**
	 * Removes all registered change listeners and clears the listener map.
	 * Called after the first change is detected — subsequent changes don't
	 * need to be tracked individually.
	 *
	 * @return {void}
	 */
	function _exemptFutureChanges() {
		_registeredChangeListeners.forEach( ( eventType, element ) => {
			element.removeEventListener( eventType, _triggerChange );
		} );
		_registeredChangeListeners.clear();
	}

	// ─── EVENT HANDLERS ────────────────────────────────────────────────────────

	/**
	 * Handles a trusted user input event — marks settings as changed and
	 * removes all remaining change listeners (one-shot pattern).
	 *
	 * @param {Event} event
	 * @return {void}
	 */
	function _triggerChange( event ) {
		if ( ! event.isTrusted ) {
			return;
		}
		registerChange();
		_exemptFutureChanges();
	}

	/**
	 * Handles a form submit or delete action — temporarily deregisters the
	 * changed state, then restores it after 1 second if the page did not unload.
	 * This prevents false "unsaved changes" warnings on intentional navigation.
	 *
	 * @return {void}
	 */
	function _triggerUnload() {

		const wereSettingsChanged = areSettingsChanged();

		deregisterChange();

		setTimeout( () => {
			reset();
			if ( wereSettingsChanged ) {
				registerChange();
			}
		}, 1000 );
	}

	/**
	 * Handles the browser beforeunload event — shows a native confirmation
	 * dialog if there are unsaved changes.
	 *
	 * @param {BeforeUnloadEvent} event
	 * @return {void}
	 */
	function _alertUserBeforeunload( event ) {
		if ( areSettingsChanged() ) {
			event.preventDefault();
			event.returnValue = l10n.i18n['saveAlert'];
		}
	}

	// ─── PUBLIC LISTENER REGISTRATION API ─────────────────────────────────────

	/**
	 * Registers change listeners on the given elements.
	 *
	 * Skips elements with the .better-seo-input-not-saved class (e.g. tab radio
	 * inputs) and hidden inputs, which should not trigger the unsaved warning.
	 *
	 * @param {Element|Document|string|string[]} elements
	 * @param {string} eventType The DOM event type to listen for (e.g. 'change', 'input').
	 * @return {void}
	 */
	function registerChangeListener( elements, eventType ) {
		_getNodeArray( elements )
			.filter( el => ! el.classList.contains( 'better-seo-input-not-saved' ) && 'hidden' !== el.type )
			.forEach( el => {
				_registeredChangeListeners.set( el, eventType );
				el.addEventListener( eventType, _triggerChange );
			} );
	}

	/**
	 * Registers reset listeners on the given elements.
	 * When triggered, resets the changed state and reloads default listeners.
	 *
	 * @param {Element|Document|string|string[]} elements
	 * @param {string} eventType The DOM event type to listen for.
	 * @return {void}
	 */
	function registerResetListener( elements, eventType ) {
		_getNodeArray( elements ).forEach( el => {
			el.addEventListener( eventType, reset );
		} );
	}

	/**
	 * Registers unload listeners on the given elements.
	 * When triggered, temporarily clears the changed state to allow navigation.
	 *
	 * @param {Element|Document|string|string[]} elements
	 * @param {string} eventType The DOM event type to listen for.
	 * @return {void}
	 */
	function registerUnloadListener( elements, eventType ) {
		_getNodeArray( elements ).forEach( el => {
			el.addEventListener( eventType, _triggerUnload );
		} );
	}

	// ─── DEBOUNCED RELOAD ──────────────────────────────────────────────────────

	/**
	 * Debounced wrapper for reloadDefaultListeners().
	 * Prevents rapid successive reloads (e.g. after Gutenberg block saves).
	 *
	 * @type {Function}
	 */
	const reloadDefaultListenersDebouncer = BetterSeoUtils.debounce( () => reloadDefaultListeners(), 1000 );

	/**
	 * Resets the changed state and schedules a reload of default listeners
	 * if they were previously loaded.
	 *
	 * @return {void}
	 */
	function reset() {
		deregisterChange();

		if ( _loadedListeners ) {
			reloadDefaultListenersDebouncer();
		}
	}

	// ─── DEFAULT LISTENER SETUP ────────────────────────────────────────────────

	/**
	 * Registers all default change, reset, and unload listeners for Better SEO
	 * settings fields across the settings page, post edit box, and term meta.
	 *
	 * Dispatches 'better-seo-registered-ays-listeners' when complete.
	 *
	 * @return {void}
	 */
	function reloadDefaultListeners() {

		_loadedListeners = false;

		// Mouse/keyboard input — radio, checkbox, select (fire on 'change')
		registerChangeListener(
			[
				'.better-seo-metaboxes input[type=radio][name]',
				'.better-seo-metaboxes input[type=checkbox][name]',
				'.better-seo-metaboxes select[name]',
				'#better-seo-inpost-box .inside input[type=radio][name]',
				'#better-seo-inpost-box .inside input[type=checkbox][name]',
				'#better-seo-inpost-box .inside select[name]',
				'.better-seo-term-meta input[type=radio][name]',
				'.better-seo-term-meta input[type=checkbox][name]',
				'.better-seo-term-meta select[name]',
			],
			'change',
		);

		// Text/URL/textarea input (fire on 'input' for real-time detection)
		registerChangeListener(
			[
				'.better-seo-metaboxes input:not([type=radio]):not([type=checkbox])[name]',
				'.better-seo-metaboxes textarea[name]',
				'#better-seo-inpost-box .inside input:not([type=radio]):not([type=checkbox])[name]',
				'#better-seo-inpost-box .inside textarea[name]',
				'.better-seo-term-meta input:not([type=radio]):not([type=checkbox])[name]',
				'.better-seo-term-meta textarea[name]',
			],
			'input',
		);

		// Reset on Gutenberg block save completion
		registerResetListener(
			document,
			'better-seo-gutenberg-onsave-completed',
		);

		// Unload on form submit or delete actions
		registerUnloadListener(
			[
				'.better-seo-metaboxes input[type=submit]',
				'#publishing-action input[type=submit]',
				'#save-action input[type=submit]',
				'a.submitdelete',
				'.edit-tag-actions input[type=submit]',
				'.edit-tag-actions .delete',
			],
			'click',
		);

		document.dispatchEvent( new CustomEvent( 'better-seo-registered-ays-listeners' ) );

		_loadedListeners = true;
	}

	// ─── INITIALISATION ────────────────────────────────────────────────────────

	/**
	 * Initialises the AYS module once the Better SEO UI is interactive.
	 *
	 * Loads default listeners, clears any pre-ready changed state (with a
	 * warning if settings were somehow changed before init), and registers
	 * the beforeunload guard.
	 *
	 * @return {void}
	 */
	function _readyAys() {

		reloadDefaultListeners();

		if ( areSettingsChanged() ) {
			console.warn( 'BetterSeoAys: Settings were changed before the ready state. This is unexpected — please report this.' );
		}

		deregisterChange();

		window.addEventListener( 'beforeunload', _alertUserBeforeunload );
	}

	// ─── PUBLIC API ────────────────────────────────────────────────────────────

	return Object.assign(
		{
			/**
			 * Attaches the AYS module to the 'better-seo-interactive' event.
			 * Call once on page load — handled automatically by class-loader.php.
			 *
			 * @return {void}
			 */
			load: () => {
				document.body.addEventListener( 'better-seo-interactive', _readyAys );
			},
		},
		{
			reset,
			getChangedState,
			areSettingsChanged,
			registerChange,
			deregisterChange,
			registerChangeListener,
			registerResetListener,
			registerUnloadListener,
			reloadDefaultListeners,
		},
		{
			l10n,
		},
	);

}() );

// Auto-initialise — registers the 'better-seo-interactive' listener immediately.
window.BetterSeoAys.load();