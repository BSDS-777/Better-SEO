<?php
/**
 * Better SEO - Front Query
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Front
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

namespace Better_SEO\Front;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\{
	Data,
	Helper,
	Helper\Query\Exclusion,
};

/**
 * Class Better_SEO\Front\Query
 *
 * Handles WordPress query modifications for Better SEO, including
 * exclusion of posts from search and archive queries based on meta settings.
 *
 * @since 1.0.0
 */
final class Query {

	/**
	 * Excludes posts from search queries via post__not_in (in_query method).
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Query $wp_query The current WP_Query instance.
	 * @return void
	 */
	public static function alter_search_query_in( \WP_Query $wp_query ): void {

		if ( $wp_query->is_search ) {
			if ( ! isset( $wp_query->query['s'] ) ) {
				return;
			}

			if ( self::is_query_adjustment_blocked( $wp_query ) ) {
				return;
			}

			$excluded = Exclusion::get_excluded_ids_from_cache()['search'];

			if ( ! $excluded ) {
				return;
			}

			$post__not_in = $wp_query->get( 'post__not_in' );

			if ( ! empty( $post__not_in ) ) {
				$excluded = array_unique( array_merge(
					(array) $post__not_in,
					$excluded,
				) );
			}

			$wp_query->set( 'post__not_in', $excluded );
		}
	}

	/**
	 * Excludes posts from search results via post filtering (post_query method).
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, \WP_Post> $posts    The array of post objects.
	 * @param \WP_Query            $wp_query The current WP_Query instance.
	 * @return array<int, \WP_Post> The filtered posts array.
	 */
	public static function alter_search_query_post( array $posts, \WP_Query $wp_query ): array {

		if ( $wp_query->is_search ) {
			// Only interact with an actual search query.
			if ( ! isset( $wp_query->query['s'] ) ) {
				return $posts;
			}

			if ( self::is_query_adjustment_blocked( $wp_query ) ) {
				return $posts;
			}

			foreach ( $posts as $n => $post ) {
				if ( Data\Plugin\Post::get_meta_item( 'exclude_local_search', $post->ID ) ) {
					unset( $posts[ $n ] );
				}
			}

			// Reset numeric index.
			$posts = array_values( $posts );
		}

		return $posts;
	}

	/**
	 * Excludes posts from archive queries via post__not_in (in_query method).
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Query $wp_query The current WP_Query instance.
	 * @return void
	 */
	public static function alter_archive_query_in( \WP_Query $wp_query ): void {

		if ( $wp_query->is_archive || $wp_query->is_home ) {
			if ( self::is_query_adjustment_blocked( $wp_query ) ) {
				return;
			}

			$excluded = Exclusion::get_excluded_ids_from_cache()['archive'];

			if ( ! $excluded ) {
				return;
			}

			$post__not_in = $wp_query->get( 'post__not_in' );

			if ( ! empty( $post__not_in ) ) {
				$excluded = array_unique( array_merge(
					(array) $post__not_in,
					$excluded,
				) );
			}

			$wp_query->set( 'post__not_in', $excluded );
		}
	}

	/**
	 * Excludes posts from archive results via post filtering (post_query method).
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, \WP_Post> $posts    The array of post objects.
	 * @param \WP_Query            $wp_query The current WP_Query instance.
	 * @return array<int, \WP_Post> The filtered posts array.
	 */
	public static function alter_archive_query_post( array $posts, \WP_Query $wp_query ): array {

		if ( $wp_query->is_archive || $wp_query->is_home ) {
			if ( self::is_query_adjustment_blocked( $wp_query ) ) {
				return $posts;
			}

			foreach ( $posts as $n => $post ) {
				if ( Data\Plugin\Post::get_meta_item( 'exclude_from_archive', $post->ID ) ) {
					unset( $posts[ $n ] );
				}
			}

			// Reset numeric index.
			$posts = array_values( $posts );
		}

		return $posts;
	}

	/**
	 * Determines whether query adjustment should be blocked for the given query.
	 *
	 * Blocks adjustment during REST requests from post editors, sitemap generation,
	 * and when the better_seo_do_adjust_archive_query filter returns false.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Query $wp_query The current WP_Query instance.
	 * @return bool True if query adjustment should be blocked, false otherwise.
	 */
	private static function is_query_adjustment_blocked( \WP_Query $wp_query ): bool {

		static $has_filter;

		$has_filter ??= \has_filter( 'better_seo_do_adjust_archive_query' );

		if ( $has_filter && ! \apply_filters( 'better_seo_do_adjust_archive_query', true, $wp_query ) ) {
			return true;
		}

		if ( ! \did_action( 'wp_loaded' ) ) {
			return true;
		}

		if ( \defined( 'REST_REQUEST' ) && \REST_REQUEST ) {
			$referer = \wp_get_referer();
			if ( str_contains( $referer, 'post.php' ) || str_contains( $referer, 'post-new.php' ) ) {
				if ( \current_user_can( 'edit_posts' ) ) {
					return true;
				}
			}
		}

		if ( Helper\Query::is_sitemap() ) {
			return true;
		}

		if ( ! empty( $wp_query->tax_query->queries ) ) {
			$supported = true;

			foreach ( $wp_query->tax_query->queries as $_query ) {
				if ( isset( $_query['taxonomy'] ) ) {
					$supported = Helper\Taxonomy::is_supported( $_query['taxonomy'] );
					// If just one taxonomy is supported for this query, greenlight it — all must be blocking.
					if ( $supported ) {
						break;
					}
				}
			}

			if ( ! $supported ) {
				return true;
			}
		}

		return false;
	}
}