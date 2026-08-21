<?php
/**
 * Better SEO - Sitemap WP Taxonomies
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Sitemap\WP
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 * @link       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Better SEO plugin
 * Copyright (C) 2026 Brian Smith
 * Licensed under the GNU General Public License v2.0.
 *
 * This program is free software: you may redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2 as
 * published by the Free Software Foundation.
 *
 * This program is provided without any warranty; without even the
 * implied warranty of merchantability or fitness for a particular purpose.
 * See the GNU General Public License for more details.
 */

declare( strict_types=1 );

namespace Better_SEO\Sitemap\WP;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\Sitemap;

/**
 * Class Better_SEO\Sitemap\WP\Taxonomies
 *
 * Extends the WordPress core WP_Sitemaps_Taxonomies provider to integrate
 * Better SEO's term inclusion checks into the WordPress core sitemap output.
 * Excludes noindexed terms and terms with redirect URLs set.
 *
 * @since 1.0.0
 */
class Taxonomies extends \WP_Sitemaps_Taxonomies {

	/**
	 * Returns the URL list for the given taxonomy and page number.
	 *
	 * Overrides WP_Sitemaps_Taxonomies::get_url_list() to apply Better SEO's
	 * term inclusion checks (noindex, redirects, exclusions) before adding
	 * each term to the sitemap URL list.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $page_num       The page number of results to retrieve.
	 * @param string $object_subtype The taxonomy slug. Default ''.
	 * @return array<int, array<string, mixed>> The URL list for the sitemap.
	 */
	public function get_url_list( int $page_num, string $object_subtype = '' ): array {

		$taxonomy        = $object_subtype;
		$supported_types = $this->get_object_subtypes();

		if ( ! isset( $supported_types[ $taxonomy ] ) ) {
			return [];
		}

		$url_list = \apply_filters(
			'wp_sitemaps_taxonomies_pre_url_list',
			null,
			$taxonomy,
			$page_num,
		);

		if ( null !== $url_list ) {
			return $url_list;
		}

		$url_list = [];

		$offset = ( $page_num - 1 ) * \wp_sitemaps_get_max_urls( $this->object_type );

		$args           = $this->get_taxonomies_query_args( $taxonomy );
		$args['fields'] = 'all';
		$args['offset'] = $offset;

		$taxonomy_terms = new \WP_Term_Query( $args );

		foreach ( $taxonomy_terms->terms ?? [] as $term ) {
			if ( ! Sitemap\Utils::is_term_included_in_sitemap( $term->term_id, $taxonomy ) ) {
				continue;
			}

			$term_link = \get_term_link( $term, $taxonomy );

			if ( \is_wp_error( $term_link ) ) {
				continue;
			}

			$sitemap_entry = [
				'loc' => $term_link,
			];

			$sitemap_entry = \apply_filters( 'wp_sitemaps_taxonomies_entry', $sitemap_entry, $term->term_id, $taxonomy, $term );
			$url_list[]    = $sitemap_entry;
		}

		return $url_list;
	}
}