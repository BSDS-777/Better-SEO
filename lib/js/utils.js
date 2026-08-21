/**
 * Better SEO — Utils Module
 *
 * Provides shared utility functions used across all Better SEO JavaScript modules.
 * Must be loaded before all other Better SEO JS files.
 *
 * Exposed as: window.BetterSeoUtils
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - No direct PHP dependencies — pure utility functions.
 *   - Registered in class-loader.php as 'better-seo-utils' (id: 'BS-utils').
 *   - All other Better SEO JS modules depend on this file via the 'BS-utils' script dependency.
 */

'use strict';

/**
 * Shared utility functions.
 *
 * @namespace BetterSeoUtils
 */
window.BetterSeoUtils = ( function () {

	// ─── DEBOUNCE ──────────────────────────────────────────────────────────────

	/**
	 * Returns a debounced version of the given function.
	 *
	 * The returned function delays invoking `func` until `timeout` milliseconds
	 * have elapsed since the last invocation. Each call returns an object with
	 * the active `timeoutId` and a `cancel()` method.
	 *
	 * @template {(...args: any[]) => any} T
	 * @param {T}      func    The function to debounce.
	 * @param {number} [timeout=0] The debounce delay in milliseconds.
	 * @return {(...args: Parameters<T>) => { timeoutId: number, cancel: Function }}
	 *   The debounced function.
	 */
	function debounce( func, timeout = 0 ) {
		let timeoutId;
		return ( ...args ) => {
			clearTimeout( timeoutId );
			return {
				timeoutId: timeoutId = setTimeout( () => func( ...args ), timeout ),
				cancel:    () => clearTimeout( timeoutId ),
			};
		};
	}

	// ─── DELAY ─────────────────────────────────────────────────────────────────

	/**
	 * Returns a Promise that resolves after the given number of milliseconds.
	 *
	 * @param {number} ms The delay duration in milliseconds.
	 * @return {Promise<void>} A Promise that resolves after `ms` milliseconds.
	 */
	function delay( ms ) {
		const { promise, resolve } = Promise.withResolvers();
		setTimeout( resolve, ms );
		return promise;
	}

	// ─── PUBLIC API ────────────────────────────────────────────────────────────

	return {
		debounce,
		delay,
	};

}() );