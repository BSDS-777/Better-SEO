/**
 * Better SEO — Term Slugs Module
 *
 * Fetches and caches parent term slug chains for hierarchical taxonomies.
 * Used by term.js to build accurate canonical URL previews when a term's
 * parent changes on the term edit screen.
 *
 * Exposed as: window.BetterSeoTermSlugs
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - AJAX action: 'better_seo_get_term_parent_slugs'
 *       Registered in class-ajax.php as wp_ajax_better_seo_get_term_parent_slugs
 *       → AJAX::get_term_parent_slugs()
 *   - POST params:
 *       nonce    — BetterSeo.l10n.nonces.edit_posts
 *       term_id  — integer term ID
 *       taxonomy — taxonomy slug string
 *   - Response: JSON array of { id: number, slug: string } objects
 *       (returned via wp_send_json_success in class-ajax.php)
 *   - Dependencies:
 *       BetterSeo.l10n.nonces.edit_posts  — nonce for edit_posts capability
 *       BetterSeo.convertJSONResponse()   — normalises wp_send_json_success response
 *       wp.ajax.send()                    — WordPress AJAX helper
 */

'use strict';

/**
 * Term parent slug cache and fetch service.
 *
 * @namespace BetterSeoTermSlugs
 */
window.BetterSeoTermSlugs = ( function () {

	/**
	 * Two-level cache: taxonomy → Map<termId, string[]>.
	 * Each entry stores the full ancestor slug chain including the term itself.
	 *
	 * @type {Map<string, Map<number, string[]>>}
	 */
	const cache = new Map();

	// ─── PUBLIC API ────────────────────────────────────────────────────────────

	/**
	 * Returns the parent slug chain for the given term ID and taxonomy.
	 *
	 * Fetches from the server if the entry is not already cached.
	 * Returns an empty array for invalid or zero term IDs.
	 *
	 * @async
	 * @param {number|string} termId   The term ID to look up.
	 * @param {string}        taxonomy The taxonomy slug.
	 * @return {Promise<string[]>} Resolves to the ordered slug chain (ancestors → self),
	 *                             or an empty array if not found.
	 */
	async function get( termId, taxonomy ) {

		termId = +termId;

		if ( ! termId || termId < 1 ) {
			return [];
		}

		if ( ! cache.get( taxonomy )?.has( termId ) ) {
			await _fetch( termId, taxonomy );
		}

		return cache.get( taxonomy )?.get( termId ) ?? [];
	}

	/**
	 * Stores a pre-fetched array of term ancestor objects into the cache.
	 *
	 * Walks the ancestral tree and stores each sub-chain so that any ancestor
	 * can be looked up directly without a separate fetch.
	 *
	 * @param {Array<{ id: number, slug: string }>} terms    Ordered array of term objects (root → leaf).
	 * @param {string}                              taxonomy The taxonomy slug.
	 * @return {void}
	 */
	function store( terms, taxonomy ) {

		if ( ! cache.has( taxonomy ) ) {
			cache.set( taxonomy, new Map() );
		}

		const termParentCache = cache.get( taxonomy );

		for ( const [ index, term ] of terms.entries() ) {
			const termId = +term.id;

			if ( ! termParentCache.has( termId ) ) {
				// Store the slug chain from root up to and including this term.
				termParentCache.set(
					termId,
					terms.slice( 0, index + 1 ).map( t => t.slug ),
				);
			}
		}
	}

	// ─── PRIVATE ───────────────────────────────────────────────────────────────

	/**
	 * Fetches the parent slug chain for the given term from the server via AJAX
	 * and stores the result in the cache.
	 *
	 * @async
	 * @param {number} termId   The term ID to fetch.
	 * @param {string} taxonomy The taxonomy slug.
	 * @return {Promise<void>} Resolves when the fetch and cache write are complete.
	 * @throws {void} Rejects silently if the AJAX call fails or termId is falsy.
	 */
	async function _fetch( termId, taxonomy ) {

		if ( ! termId ) {
			return;
		}

		const { promise, resolve, reject } = Promise.withResolvers();

		wp.ajax.send(
			'better_seo_get_term_parent_slugs',
			{
				data: {
					nonce:    BetterSeo.l10n.nonces.edit_posts,
					term_id:  termId,
					taxonomy,
				},
				timeout: 7000,
			},
		).done( response => {
			store( BetterSeo.convertJSONResponse( response ), taxonomy );
			resolve();
		} ).fail( reject );

		return promise;
	}

	return {
		get,
		store,
	};

}() );