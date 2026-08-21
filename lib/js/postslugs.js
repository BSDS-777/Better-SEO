/**
 * Better SEO — Post Slug Fetcher
 *
 * Provides an async API for fetching and caching WordPress post parent slug
 * chains by post ID via the Better SEO AJAX endpoint. Used by canonical.js
 * and le.js to build dynamic canonical URL placeholders for hierarchical posts.
 *
 * Exposed as: window.BetterSeoPostSlugs
 *
 * Usage:
 *   const slugs = await BetterSeoPostSlugs.get( postId );
 *   BetterSeoPostSlugs.store( [ { id: 5, slug: 'parent' }, { id: 12, slug: 'child' } ] );
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 *
 * @note  PHP cross-reference:
 *   - AJAX action: better_seo_get_post_parent_slugs
 *                  (wp_ajax_better_seo_get_post_parent_slugs in class-ajax.php:351)
 *   - Nonce:       BetterSeoL10n.nonces.edit_posts
 *   - POST data:   { nonce, post_id }
 *   - Response:    Array of { id, slug } objects representing the ancestral chain
 */

'use strict';

/**
 * Post parent slug cache and fetcher module.
 *
 * @namespace BetterSeoPostSlugs
 */
window.BetterSeoPostSlugs = ( function () {

	/**
	 * In-memory cache of post ID → ordered slug chain mappings.
	 *
	 * Each entry maps a post ID to an array of slugs representing the full
	 * ancestral path from root to that post (inclusive).
	 *
	 * @type {Map<number, string[]>}
	 */
	const cache = new Map();

	/**
	 * Returns the ordered slug chain for the given post ID.
	 *
	 * Fetches from the server if not already cached.
	 * Returns an empty array if the post ID is invalid or the fetch fails.
	 *
	 * @param {number|string} postId The WordPress post ID.
	 * @return {Promise<string[]>} The ordered slug chain, or an empty array.
	 */
	async function get( postId ) {

		postId = +postId;

		if ( ! postId || postId < 1 ) {
			return [];
		}

		if ( ! cache.has( postId ) ) {
			await fetchPostSlugs( postId );
		}

		return cache.get( postId ) ?? [];
	}

	/**
	 * Stores an array of post ID/slug pairs in the local cache.
	 *
	 * Walks the ancestral tree and stores each post's full slug chain
	 * (from root to that post, inclusive) for O(1) lookup later.
	 * Skips posts that are already cached to avoid overwriting fresher data.
	 *
	 * Can be called proactively to pre-populate the cache from PHP-side data
	 * without making an AJAX request.
	 *
	 * @param {Array<{id: number|string, slug: string}>} posts Ordered array of post objects (root first).
	 * @return {void}
	 */
	function store( posts ) {
		for ( const [ index, post ] of posts.entries() ) {
			const postId = +post.id;
			// Skip if already cached — existing data may be more up-to-date.
			if ( ! cache.has( postId ) ) {
				cache.set(
					postId,
					// Slice from root to this post (inclusive) to build the full slug chain.
					posts.slice( 0, index + 1 ).map( p => p.slug ),
				);
			}
		}
	}

	/**
	 * Fetches the post parent slug chain from the server via WordPress AJAX.
	 *
	 * Stores the result in the local cache on success.
	 * Named fetchPostSlugs to avoid shadowing the native window.fetch API.
	 *
	 * @param {number} postId The WordPress post ID.
	 * @return {Promise<void>} Resolves when the slug chain is cached, rejects on failure.
	 */
	function fetchPostSlugs( postId ) {

		const { promise, resolve, reject } = Promise.withResolvers();

		if ( ! postId ) {
			reject( new Error( 'BetterSeoPostSlugs: postId is required.' ) );
			return promise;
		}

		wp.ajax.send(
			'better_seo_get_post_parent_slugs',
			{
				data: {
					nonce:   BetterSeoL10n.nonces.edit_posts,
					post_id: postId,
				},
				timeout: 7000,
			},
		).done( response => {
			store( BetterSeo.convertJSONResponse( response ) );
			resolve();
		} ).fail( reject );

		return promise;
	}

	// Public API — store is exposed to allow pre-population from PHP-side data.
	return {
		get,
		store,
	};

}() );