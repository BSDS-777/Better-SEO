/**
 * Better SEO — Author Slug Fetcher
 *
 * Provides an async API for fetching and caching WordPress author slugs
 * by author ID via the Better SEO AJAX endpoint.
 *
 * Exposed as: window.BetterSeoAuthorSlugs
 *
 * Usage:
 *   const slug = await BetterSeoAuthorSlugs.get( authorId );
 *   BetterSeoAuthorSlugs.store( [ { id: 1, slug: 'brian' } ] );
 *
 * @package    Better_SEO
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 */

'use strict';

/**
 * Author slug cache and fetcher module.
 *
 * @namespace BetterSeoAuthorSlugs
 */
window.BetterSeoAuthorSlugs = ( function () {

	/**
	 * In-memory cache of author ID → slug mappings.
	 *
	 * @type {Map<number, string>}
	 */
	const cache = new Map();

	/**
	 * Returns the slug for the given author ID.
	 *
	 * Fetches from the server if not already cached.
	 * Returns an empty string if the author ID is invalid or the fetch fails.
	 *
	 * @param {number|string} authorId The WordPress author user ID.
	 * @return {Promise<string>} The author slug, or an empty string.
	 */
	async function get( authorId ) {

		authorId = +authorId;

		if ( ! authorId || authorId < 1 ) {
			return '';
		}

		if ( ! cache.has( authorId ) ) {
			await fetchAuthor( authorId );
		}

		return cache.get( authorId ) || '';
	}

	/**
	 * Stores an array of author ID/slug pairs in the local cache.
	 *
	 * Can be called proactively to pre-populate the cache from
	 * server-side data without making an AJAX request.
	 *
	 * @param {Array<{id: number|string, slug: string}>} authors Array of author objects.
	 * @return {void}
	 */
	function store( authors ) {
		authors.forEach( author => {
			cache.set( +author.id, author.slug );
		} );
	}

	/**
	 * Fetches the author slug from the server via WordPress AJAX.
	 *
	 * Stores the result in the local cache on success.
	 * Named fetchAuthor to avoid shadowing the native window.fetch API.
	 *
	 * @param {number} authorId The WordPress author user ID.
	 * @return {Promise<void>} Resolves when the slug is cached, rejects on failure.
	 */
	function fetchAuthor( authorId ) {

		return new Promise( ( resolve, reject ) => {

			if ( ! authorId ) {
				reject( new Error( 'BetterSeoAuthorSlugs: authorId is required.' ) );
				return;
			}

			wp.ajax.send(
				'better_seo_get_author_slug',
				{
					data: {
						nonce:     BetterSeoL10n.nonces.edit_posts,
						author_id: authorId,
					},
					timeout: 7000,
				},
			).done( response => {
				store( BetterSeo.convertJSONResponse( response ) );
				resolve();
			} ).fail( reject );
		} );
	}

	// Public API — store is exposed to allow pre-population from PHP-side data.
	return {
		get,
		store,
	};

}() );