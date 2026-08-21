/**
 * Better SEO — UI Module
 *
 * Provides shared UI utilities for the Better SEO admin interface:
 * postbox validation handling, persistent notice dismissal, and
 * CSS animation helpers (fadeIn, fadeOut, traceAnimation).
 *
 * Exposed as: window.BetterSeoUI
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - AJAX action: 'better_seo_dismiss_notice'
 *       Registered in class-ajax.php as wp_ajax_better_seo_dismiss_notice
 *       → AJAX::dismiss_notice()
 *   - POST params:
 *       better_seo_dismiss_key   — sanitized notice key
 *       better_seo_dismiss_nonce — nonce value from data-nonce attribute
 *   - CSS classes managed by this module:
 *       better-seo-notice    — persistent notice wrapper element
 *       better-seo-dismiss   — dismiss button/link element
 *   - data attributes consumed:
 *       data-key   — notice key stored on dismiss button
 *       data-nonce — nonce value stored on dismiss button
 *   - data attributes managed:
 *       data-better-seo-is-animating — animation trace token stored on animated elements
 *   - Postbox selectors (WordPress admin metaboxes):
 *       .postbox[id^="better-seo-"]  — Better SEO postboxes
 *       .postbox#better-seo-inpost-box — Better SEO inpost metabox
 *   - Custom events consumed:
 *       'better-seo-ready'                  — fires _initPostboxToggle and _initNotices
 *       'better-seo-reset-notice-listeners' — re-attaches dismiss listeners after DOM changes
 *   - CSS animations used (defined in better-seo.css):
 *       better-seo-fade-in   — fade-in keyframe animation
 *       better-seo-fade-out  — fade-out keyframe animation
 *   - Dependencies:
 *       BetterSeoUtils.delay() — Promise-based delay helper
 *       jQuery ($)             — required for WordPress postbox-toggled event and postbox manipulation
 *       wp.ajax.post()         — WordPress AJAX helper
 */

'use strict';

/**
 * Shared UI utilities.
 *
 * @namespace BetterSeoUI
 * @param {jQuery} $ jQuery instance passed as IIFE argument.
 */
window.BetterSeoUI = ( function ( $ ) {

	// ─── POSTBOX TOGGLE ────────────────────────────────────────────────────────

	/**
	 * Initialises postbox validation handling for Better SEO metaboxes.
	 *
	 * When a Better SEO postbox is toggled (opened/closed/hidden), checks for
	 * invalid form inputs inside it. If found, ensures the postbox is visible
	 * and open, then calls reportValidity() on the first invalid input.
	 *
	 * Uses jQuery because WordPress's postbox-toggled event is jQuery-only.
	 *
	 * @return {void}
	 */
	function _initPostboxToggle() {

		const $postboxes = $( '.postbox[id^="better-seo-"], .postbox#better-seo-inpost-box' );

		$( document ).on(
			'postbox-toggled',
			( event, $postbox ) => {
				if ( ! $postbox || ! $postboxes.is( $postbox ) ) {
					return;
				}

				// WordPress sends the postbox as an array — normalise to jQuery.
				$postbox = $( $postbox );

				const $input = $postbox.find( 'input:invalid, select:invalid, textarea:invalid' );
				if ( ! $input.length ) {
					return;
				}

				// Defer from the event to allow WordPress to finish its own postbox handling.
				setTimeout( () => {
					if ( $postbox.is( ':hidden' ) ) {
						// Postbox is hidden — unhide it, which will re-trigger this handler.
						$( `#${$postbox.attr( 'id' )}-hide` ).trigger( 'click.postboxes' );
					} else if ( $postbox.hasClass( 'closed' ) ) {
						// Postbox is closed — reopen it, which will re-trigger this handler.
						$postbox.find( '.hndle, .handlediv' ).first().trigger( 'click.postboxes' );
					} else {
						// Postbox is visible and open — report validity on the first invalid input.
						const firstInput = $input.get( 0 );
						if ( $( firstInput ).is( ':visible' ) ) {
							firstInput.reportValidity();
						}
					}
				} );
			},
		);
	}

	// ─── NOTICE DISMISSAL ──────────────────────────────────────────────────────

	/**
	 * Initialises persistent notice dismissal listeners.
	 *
	 * Attaches click listeners to all `.better-seo-dismiss` elements. On click,
	 * animates the notice out and sends an AJAX request to persist the dismissal.
	 * Re-attaches listeners when the 'better-seo-reset-notice-listeners' event fires.
	 *
	 * @return {void}
	 */
	function _initNotices() {

		/**
		 * Handles a dismiss button click: animates the notice out and sends the
		 * dismissal AJAX request if a key and nonce are present.
		 *
		 * @param {MouseEvent} event The click event on the dismiss button.
		 * @return {void}
		 */
		const dismissNotice = event => {

			const notice = event.target.closest( '.better-seo-notice' );
			const key    = event.target.dataset.key;
			const nonce  = event.target.dataset.nonce;

			// Mimics WordPress's jQuery fadeTo().slideUp() animation.
			notice.style.transformOrigin = 'bottom';
			const animation = notice.animate(
				[
					{ transform: 'scaleY(1)', maxHeight: `${notice.clientHeight}px`, opacity: 1 },
					{ transform: 'scaleY(1)', opacity: 0 },
					{ transform: 'scaleY(0)', maxHeight: '0', paddingTop: '0', paddingBottom: '0', marginTop: '0', marginBottom: '0', opacity: 0 },
				],
				{
					duration:   200,
					iterations: 1,
				},
			);
			animation.onfinish = () => notice.remove();

			if ( key && nonce ) {
				// The notice is removed regardless of AJAX completion.
				// Avoid informing the user of completion — it adds unnecessary noise.
				// Rely on keeping the 'count' low to limit repeat appearances.
				wp.ajax.post(
					'better_seo_dismiss_notice',
					{
						better_seo_dismiss_key:   key,
						better_seo_dismiss_nonce: nonce,
					},
				);
			}
		};

		/**
		 * Attaches dismiss listeners to all current `.better-seo-dismiss` elements.
		 *
		 * @return {void}
		 */
		const reset = () => {
			for ( const el of document.querySelectorAll( '.better-seo-dismiss' ) ) {
				el.addEventListener( 'click', dismissNotice );
			}
		};

		document.body.addEventListener( 'better-seo-reset-notice-listeners', reset );
		reset();
	}

	// ─── ANIMATION HELPERS ─────────────────────────────────────────────────────

	/**
	 * Applies CSS animation styles to the given element and resolves after the
	 * animation duration. Optionally invokes a callback on completion.
	 *
	 * Used internally by fadeOut() and directly by tabs.js for tab transitions.
	 *
	 * @async
	 * @param {HTMLElement}          element          The element to animate.
	 * @param {number}               [duration=125]   The animation duration in milliseconds.
	 * @param {Function|undefined}   [cb]             Optional callback invoked after the animation.
	 * @param {Object.<string,string>} [css={}]       Additional CSS properties to merge into the animation styles.
	 * @return {Promise<void>} Resolves when the animation duration has elapsed.
	 */
	async function fadeIn( element, duration = 125, cb = undefined, css = {} ) {

		const styles = {
			opacity:                 '1',
			animation:               'better-seo-fade-in',
			animationDuration:       `${duration}ms`,
			animationTimingFunction: 'cubic-bezier(.54,.12,.90,.60)',
			...css,
		};

		for ( const [ prop, value ] of Object.entries( styles ) ) {
			element.style[ prop ] = value;
		}

		const animationTrace = traceAnimation( element );

		await BetterSeoUtils.delay( duration );

		animationTrace.unsetIfUnchanged();

		if ( 'function' === typeof cb ) {
			cb();
		}
	}

	/**
	 * Applies a fade-out CSS animation to the given element.
	 *
	 * Delegates to fadeIn() with opacity:0 and the fade-out animation name.
	 *
	 * @async
	 * @param {HTMLElement}          element          The element to fade out.
	 * @param {number}               [duration=125]   The animation duration in milliseconds.
	 * @param {Function|undefined}   [cb]             Optional callback invoked after the animation.
	 * @param {Object.<string,string>} [css={}]       Additional CSS properties to merge.
	 * @return {Promise<void>} Resolves when the animation duration has elapsed.
	 */
	function fadeOut( element, duration = 125, cb = undefined, css = {} ) {
		return fadeIn(
			element,
			duration,
			cb,
			{
				opacity:   '0',
				animation: 'better-seo-fade-out',
				...css,
			},
		);
	}

	/**
	 * Records the current animation state of the given element as a unique token,
	 * stored on the element's dataset. Returns helpers to check whether the
	 * animation is still the active one and to clear it if unchanged.
	 *
	 * Used to prevent stale animation cleanup from interfering with newer animations
	 * started on the same element before the previous one completed.
	 *
	 * @param {HTMLElement}    element The element to trace.
	 * @param {string|undefined} [name] The animation name to record. Defaults to the element's current animation style.
	 * @return {{ unchanged: Function, unsetIfUnchanged: Function }} Animation trace helpers.
	 */
	function traceAnimation( element, name ) {

		name ??= element.style.animation ?? '';

		const animation = `${name}:${Date.now()}`;

		element.dataset.betterSeoIsAnimating = animation;

		return {
			/**
			 * Returns true if the element's animation token matches the recorded token.
			 *
			 * @return {boolean}
			 */
			unchanged: () => animation === element.dataset.betterSeoIsAnimating,

			/**
			 * Clears the element's animation style if the token is still current.
			 *
			 * @return {void}
			 */
			unsetIfUnchanged: () => {
				if ( animation === element.dataset.betterSeoIsAnimating ) {
					element.style.animation = null;
				}
			},
		};
	}

	// ─── PUBLIC API ────────────────────────────────────────────────────────────

	return {
		/**
		 * Registers 'better-seo-ready' listeners for postbox toggle handling
		 * and notice dismissal initialisation.
		 * Called automatically on script load.
		 *
		 * @return {void}
		 */
		load: () => {
			document.body.addEventListener( 'better-seo-ready', _initPostboxToggle );
			document.body.addEventListener( 'better-seo-ready', _initNotices );
		},

		fadeIn,
		fadeOut,
		traceAnimation,
	};

}( jQuery ) );

// Auto-initialise — registers 'better-seo-ready' listeners.
window.BetterSeoUI.load();