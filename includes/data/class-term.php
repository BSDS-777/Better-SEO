<?php
/**
 * Better SEO - Data Term
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Data
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

namespace Better_SEO\Data;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\memo;

/**
 * Class Better_SEO\Data\Term
 *
 * Provides data helper methods for taxonomy term information,
 * including term population state, parent ancestry, and latest term lookup.
 *
 * @since 1.0.0
 * @access protected
 *         Use better_seo()->data()->term() instead.
 */
class Term {

	/**
	 * Returns the ID of the most recently created term for a given taxonomy, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @param string $taxonomy The taxonomy slug. Default 'category'.
	 * @return int|false The latest term ID, or false if none found.
	 */
	public static function get_latest_term_id( string $taxonomy = 'category' ): int|false {

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition -- Intentional memo pattern.
		if ( null !== $memo = memo( null, $taxonomy ) ) {
			return $memo;
		}

		$cats = \get_terms( [
			'taxonomy'   => $taxonomy,
			'fields'     => 'ids',
			'hide_empty' => false,
			'orderby'    => 'term_id',
			'order'      => 'DESC',
			'number'     => 1,
		] );

		return memo( reset( $cats ), $taxonomy );
	}

	/**
	 * Returns whether a term has any associated posts (directly or via children), memoized.
	 *
	 * Checks both the term's own post count and the counts of all child terms.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $term_id  The term ID.
	 * @param string $taxonomy The taxonomy slug.
	 * @return bool True if the term has associated posts, false otherwise.
	 */
	public static function is_term_populated( int $term_id, string $taxonomy ): bool {
		return memo( null, $term_id, $taxonomy )
			?? memo(
				// phpcs:ignore PEAR.Functions.FunctionCallSignature.Indent -- legibility
				   ! empty( \get_term( $term_id, $taxonomy )->count )
				|| array_filter( // Filter count => 0 — if all are 0, we get an empty array (Boolean false).
					array_column(
						\get_terms( [
							'taxonomy'   => $taxonomy,
							'child_of'   => $term_id,
							'childless'  => false,
							'pad_counts' => false,
							'get'        => '',
						] ),
						'count',
					),
				),
				$term_id,
				$taxonomy,
			);
	}

	/**
	 * Returns an array of parent term objects for a given term, ordered from root to immediate parent.
	 *
	 * Uses get_ancestors() to apply filters for compatibility with other plugins.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $term_id      The term ID. Returns empty array if 0.
	 * @param string $taxonomy     The taxonomy slug.
	 * @param bool   $include_self Whether to include the term itself. Default false.
	 * @return array<int, \WP_Term> Map of term ID to WP_Term parent objects.
	 */
	public static function get_term_parents( int $term_id, string $taxonomy, bool $include_self = false ): array {

		// Term ID may be 0 when no terms are present.
		if ( ! $term_id ) {
			return [];
		}

		// get_ancestors() applies filters required for compatibility with other plugins.
		$ancestors = \get_ancestors( $term_id, $taxonomy, 'taxonomy' );

		if ( $include_self ) {
			array_unshift( $ancestors, $term_id );
		}

		$parents = [];

		foreach ( array_reverse( $ancestors ) as $_term_id ) {
			$parent = \get_term( $_term_id, $taxonomy );

			if ( $parent && ! \is_wp_error( $parent ) ) {
				$parents[ $_term_id ] = $parent;
			}
		}

		return $parents;
	}
}