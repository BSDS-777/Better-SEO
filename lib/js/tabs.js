/**
 * Better SEO — Tabs Module
 *
 * Manages tab navigation stacks across the Better SEO admin UI.
 * Supports multiple independent tab stacks (e.g. settings page, post edit page,
 * PTA settings), each identified by a unique stack ID. Handles animated tab
 * transitions, history-aware focus correction, validity checking before switching,
 * and programmatic show/hide/toggle of individual tabs.
 *
 * Exposed as: window.BetterSeoTabs
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - No direct AJAX calls — purely a UI tab navigation manager.
 *   - Tab stack IDs are registered by callers (e.g. settings.js, post.js):
 *       'betterSeoSettings'   — settings page (settings.js)
 *       'betterSeoPost'       — post edit page (post.js)
 *   - HTMLClasses passed per stack (set by PHP view templates):
 *       wrapper          — outer nav wrapper element class
 *       tabRadio         — radio input class for each tab
 *       tabLabel         — label element class for each tab
 *       activeTab        — class added to the currently active radio
 *       activeTabContent — class added to the currently visible content panel
 *   - Tab content element IDs follow the pattern: {radioId}-content
 *   - Custom events dispatched:
 *       tabToggledEvent (CustomEvent) — dispatched on the radio when a tab becomes active;
 *                                       the event instance is passed in per-stack args
 *   - Dependencies:
 *       BetterSeoUI.fadeIn()    — fade-in animation helper
 *       BetterSeoUI.fadeOut()   — fade-out animation helper
 *       BetterSeoUtils.delay()  — Promise-based delay helper
 */

'use strict';

/**
 * Tab navigation stack manager.
 *
 * @namespace BetterSeoTabs
 */
window.BetterSeoTabs = ( function () {

	/**
	 * Map of stackId → stack args object.
	 *
	 * @type {Map<string, Object>}
	 */
	const tabStack = new Map();

	/**
	 * Shared transition cache, keyed by sub-map name.
	 * Sub-maps: 'promises', 'target', 'wrap', 'container'.
	 *
	 * @type {Map<string, Map>}
	 */
	const _toggleCache = new Map();

	// ─── FOCUS CORRECTION ──────────────────────────────────────────────────────

	/**
	 * Re-dispatches a 'change' event on all checked tab radios in stacks that
	 * have `fixHistory: true`, so that browser history navigation restores the
	 * correct active tab state.
	 *
	 * Bound to the window 'load' event.
	 *
	 * @return {void}
	 */
	function _correctTabFocus() {
		const changeEvent = new Event( 'change' );

		for ( const args of tabStack.values() ) {
			if ( ! args.fixHistory ) {
				continue;
			}
			for ( const el of document.querySelectorAll( `.${args.HTMLClasses.tabRadio}:checked` ) ) {
				el.dispatchEvent( changeEvent );
			}
		}
	}

	// ─── TAB VISIBILITY ────────────────────────────────────────────────────────

	/**
	 * Hides and disables a tab by name within the given stack.
	 *
	 * If the tab is currently checked, immediately switches to the first
	 * available non-disabled tab in the same wrapper.
	 *
	 * @param {string} stackId The tab stack identifier.
	 * @param {string} name    The element ID of the tab radio to hide.
	 * @return {void}
	 */
	function hideTab( stackId, name ) {

		const stack = getStack( stackId );
		const radio = document.getElementById( name );

		radio?.setAttribute( 'disabled', '' );
		document.querySelector( `.${stack.HTMLClasses.tabLabel}[for="${name}"]` )?.classList.add( 'hidden' );

		if ( radio?.checked ) {
			toggleToInstant(
				stackId,
				// Separate queries because :not in closest() selects only the first match.
				radio.closest( `.${stack.HTMLClasses.wrapper}` )
					?.querySelector( `.${stack.HTMLClasses.tabRadio}:not([disabled])` ),
			);
		}
	}

	/**
	 * Shows and enables a previously hidden tab by name within the given stack.
	 *
	 * @param {string} stackId The tab stack identifier.
	 * @param {string} name    The element ID of the tab radio to show.
	 * @return {void}
	 */
	function showTab( stackId, name ) {

		const stack = getStack( stackId );

		document.getElementById( name )?.removeAttribute( 'disabled' );
		document.querySelector( `.${stack.HTMLClasses.tabLabel}[for="${name}"]` )?.classList.remove( 'hidden' );
	}

	/**
	 * Toggles a tab's visibility within the given stack.
	 *
	 * When `toggle` is omitted, the current disabled state is inverted.
	 * When `toggle` is true, the tab is shown; when false, it is hidden.
	 *
	 * @param {string}           stackId The tab stack identifier.
	 * @param {string}           name    The element ID of the tab radio to toggle.
	 * @param {boolean|undefined} toggle  Explicit show (true) / hide (false), or undefined to invert.
	 * @return {void}
	 */
	function toggleTab( stackId, name, toggle ) {

		if ( toggle === undefined ) {
			if ( document.getElementById( name )?.disabled ) {
				showTab( stackId, name );
			} else {
				hideTab( stackId, name );
			}
		} else if ( toggle ) {
			showTab( stackId, name );
		} else {
			hideTab( stackId, name );
		}
	}

	// ─── TAB SWITCHING ─────────────────────────────────────────────────────────

	/**
	 * Instantly switches to the given tab target without animation.
	 *
	 * Checks the radio, swaps the active content class, and dispatches the
	 * stack's tabToggledEvent on the radio element.
	 *
	 * @param {string}      stackId The tab stack identifier.
	 * @param {HTMLElement} target  The tab radio element to activate.
	 * @return {void}
	 */
	function toggleToInstant( stackId, target ) {

		const stack      = getStack( stackId );
		const newContent = document.getElementById( `${target.id}-content` );
		const radio      = document.getElementById( target.id );

		radio.checked = true;

		if ( newContent && ! newContent.classList.contains( stack.HTMLClasses.activeTabContent ) ) {
			for ( const element of document.querySelectorAll( `.${target.name}-content` ) ) {
				element.classList.remove( stack.HTMLClasses.activeTabContent );
			}
			newContent.classList.add( stack.HTMLClasses.activeTabContent );
		}

		radio.dispatchEvent( stack.tabToggledEvent );
	}

	/**
	 * Switches to the given tab target with a fade-out/fade-in animation.
	 *
	 * Uses a Promise-based queue per tab group (keyed by radio name) to prevent
	 * race conditions when the user switches tabs rapidly. If a transition is
	 * already in progress, the target is updated in the cache and the running
	 * Promise will pick it up on completion.
	 *
	 * @param {string}      stackId The tab stack identifier.
	 * @param {HTMLElement} target  The tab radio element to activate.
	 * @return {void}
	 */
	function toggleTo( stackId, target ) {

		const cacheId = target.name;
		const stack   = getStack( stackId );

		const fadeOutTimeout = 125;
		const fadeInTimeout  = 175;

		const container  = _toggleCache.get( 'container' ).get( cacheId );
		const allContent = document.querySelectorAll( `.${target.name}-content` );

		/**
		 * Locks the container height to prevent layout shift during transition.
		 *
		 * @return {void}
		 */
		const lockHeight = () => {
			container.style.boxSizing = 'border-box';
			container.style.minHeight = `${container.getBoundingClientRect().height}px`;
		};

		/**
		 * Releases the locked container height.
		 *
		 * @return {void}
		 */
		const unLockHeight = () => {
			container.style.minHeight = '';
		};

		/**
		 * Activates the correct tab content and fades it in.
		 * Re-checks the target cache in case it changed during the transition.
		 *
		 * @async
		 * @return {Promise<void>}
		 */
		const setCorrectTab = async () => {
			const newContent = document.getElementById(
				`${_toggleCache.get( 'target' ).get( cacheId )}-content`,
			);

			lockHeight();
			for ( const el of allContent ) {
				el.classList.remove( stack.HTMLClasses.activeTabContent );
			}

			newContent.classList.add( stack.HTMLClasses.activeTabContent );
			unLockHeight();
			BetterSeoUI.fadeIn( newContent, fadeInTimeout );

			// Resolve early at 2/3 of fade-in time — content is already well visible.
			await BetterSeoUtils.delay( fadeInTimeout * 2 / 3 );

			return testTab();
		};

		/**
		 * Verifies the correct tab is active after the transition.
		 * If the target changed during the transition, retries via requestAnimationFrame.
		 *
		 * @async
		 * @return {Promise<void>}
		 */
		const testTab = async () => {
			const newContent = document.getElementById(
				`${_toggleCache.get( 'target' ).get( cacheId )}-content`,
			);

			if ( ! newContent || newContent.classList.contains( stack.HTMLClasses.activeTabContent ) ) {
				clearPromise();
				document.getElementById( _toggleCache.get( 'target' ).get( cacheId ) )
					.dispatchEvent( stack.tabToggledEvent );
			} else {
				// Target changed during transition — retry via animation frame to avoid CPU spin.
				lockHeight();
				for ( const el of allContent ) {
					el.classList.remove( stack.HTMLClasses.activeTabContent );
				}
				requestAnimationFrame( () => {
					setCorrectTab().then( clearPromise );
				} );
			}
		};

		/**
		 * Runs the full fade-out → fade-in transition Promise.
		 *
		 * @return {Promise<void>}
		 */
		const doPromise = () => {
			const { promise, resolve } = Promise.withResolvers();

			( async () => {
				for ( const el of allContent ) {
					BetterSeoUI.fadeOut( el, fadeOutTimeout );
				}
				// Await fade-out before starting fade-in.
				await BetterSeoUtils.delay( fadeOutTimeout );
				await setCorrectTab();
				resolve();
			} )();

			return promise;
		};

		/** Removes the running Promise entry from the cache. */
		const clearPromise = () => _toggleCache.get( 'promises' ).delete( cacheId );

		/**
		 * Starts the transition Promise if one is not already running for this group.
		 *
		 * @return {void}
		 */
		const runPromise = () => {
			if ( _toggleCache.get( 'promises' ).has( cacheId ) ) {
				return;
			}
			_toggleCache.get( 'promises' ).set( cacheId, doPromise );
			_toggleCache.get( 'promises' ).get( cacheId )();
		};

		runPromise();
	}

	// ─── INTERNAL TOGGLE HANDLER ───────────────────────────────────────────────

	/**
	 * Handles a tab radio 'change' event for the given stack.
	 *
	 * Performs a validity check on the current tab's content before switching.
	 * If an invalid input is found, the switch is cancelled and the previous tab
	 * is restored. On page load (non-trusted events), switches instantly without
	 * animation or validity checks.
	 *
	 * @param {string} stackId The tab stack identifier.
	 * @param {Event}  event   The 'change' event from the tab radio.
	 * @return {boolean|void} Returns false if the switch was cancelled due to validation failure.
	 */
	function _toggle( stackId, event ) {

		const stack = getStack( stackId );

		const currentToggle = event.target;
		const onload        = ! event.isTrusted;

		const toggleId   = event.target.id;
		const toggleName = event.target.name;
		const cacheId    = toggleName;

		if ( ! _toggleCache.get( 'wrap' ).has( cacheId ) ) {
			_toggleCache.get( 'wrap' ).set(
				cacheId,
				currentToggle.closest( `.${stack.HTMLClasses.wrapper}` ),
			);
		}

		const previousToggle = _toggleCache.get( 'wrap' ).get( cacheId )
			.querySelector( `.${stack.HTMLClasses.activeTab}` );

		if ( ! onload ) {
			// Validate the current tab's content before switching.
			const invalidInput = document.querySelector(
				`.${stack.HTMLClasses.activeTabContent} :invalid`,
			);

			if ( invalidInput ) {
				invalidInput.reportValidity();

				if ( previousToggle ) {
					previousToggle.checked = true;
				}
				currentToggle.checked = false;

				event.stopPropagation();
				event.preventDefault();
				return false;
			}
		}

		if ( previousToggle ) {
			previousToggle.classList.remove( stack.HTMLClasses.activeTab );
			document.querySelector( `.${stack.HTMLClasses.tabLabel}[for="${previousToggle.id}"]` )
				?.classList.remove( 'better-seo-no-focus-ring' );
		}
		currentToggle.classList.add( stack.HTMLClasses.activeTab );

		if ( onload ) {
			toggleToInstant( stackId, event.target );
		} else {
			if ( ! _toggleCache.get( 'container' ).has( cacheId ) ) {
				_toggleCache.get( 'container' ).set(
					cacheId,
					currentToggle.closest( '.inside' ),
				);
			}

			// Set the toggle target early so any in-flight Promise picks up the latest value.
			_toggleCache.get( 'target' ).set( cacheId, toggleId );

			// If a Promise is already running for this group, let it finish with the updated target.
			if ( _toggleCache.get( 'promises' ).has( cacheId ) ) {
				return;
			}

			toggleTo( stackId, event.target );
		}
	}

	// ─── STACK MANAGEMENT ──────────────────────────────────────────────────────

	/**
	 * Returns the registered stack args for the given stack ID.
	 *
	 * @param {string} stackId The tab stack identifier.
	 * @return {Object|undefined} The stack args, or undefined if not registered.
	 */
	function getStack( stackId ) {
		return tabStack.get( stackId );
	}

	/**
	 * Registers a new tab stack and attaches change and click listeners to all
	 * tab radios and labels within the stack's wrapper.
	 *
	 * @param {string} stackId The unique tab stack identifier.
	 * @param {Object} args    Stack configuration:
	 * @param {CustomEvent}  args.tabToggledEvent  Event dispatched when a tab becomes active.
	 * @param {Object}       args.HTMLClasses       CSS class names for the stack's elements.
	 * @param {string}       args.HTMLClasses.wrapper          Outer nav wrapper class.
	 * @param {string}       args.HTMLClasses.tabRadio         Tab radio input class.
	 * @param {string}       args.HTMLClasses.tabLabel         Tab label class.
	 * @param {string}       args.HTMLClasses.activeTab        Active radio class.
	 * @param {string}       args.HTMLClasses.activeTabContent Active content panel class.
	 * @param {boolean}      [args.fixHistory=false]           Whether to re-dispatch change on load for history fix.
	 * @return {void}
	 */
	function initStack( stackId, args ) {

		tabStack.set( stackId, args );

		const stack = getStack( stackId );

		/** @param {Event} event */
		const toggleForwarder = event => _toggle( stackId, event );

		/** @param {Event} event */
		const addNoFocusClass = event => event.currentTarget.classList.add( 'better-seo-no-focus-ring' );

		for ( const el of document.querySelectorAll( `.${stack.HTMLClasses.tabRadio}` ) ) {
			el.addEventListener( 'change', toggleForwarder );
		}

		for ( const el of document.querySelectorAll(
			`.${stack.HTMLClasses.wrapper} .${stack.HTMLClasses.tabLabel}`,
		) ) {
			el.addEventListener( 'click', addNoFocusClass );
		}
	}

	// ─── PUBLIC API ────────────────────────────────────────────────────────────

	return {
		/**
		 * Initialises the shared toggle cache maps and registers the focus
		 * correction handler on the window 'load' event.
		 * Called automatically on script load.
		 *
		 * @return {void}
		 */
		load: () => {
			_toggleCache.set( 'promises',  new Map() );
			_toggleCache.set( 'target',    new Map() );
			_toggleCache.set( 'wrap',      new Map() );
			_toggleCache.set( 'container', new Map() );

			// Delay focus fix until after load to avoid blocking the interactive state.
			// This addresses an edge case where browser history navigation restores a non-default tab.
			window.addEventListener( 'load', _correctTabFocus );
		},
		hideTab,
		showTab,
		toggleTab,
		toggleToInstant,
		toggleTo,
		getStack,
		initStack,
	};

}() );

// Auto-initialise — sets up the toggle cache and registers the focus correction handler.
window.BetterSeoTabs.load();