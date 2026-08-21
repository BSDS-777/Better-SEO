<?php
/**
 * Better SEO - Helper Query Utils
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Helper\Query
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

namespace Better_SEO\Helper\Query;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\memo;

use Better_SEO\Data;
use Better_SEO\Helper\{
	Post_Type,
	Query,
	Taxonomy,
};

/**
 * Class Better_SEO\Helper\Query\Utils
 *
 * Provides query utility methods for Better SEO, including SEO support detection,
 * query exploitation detection, and permalink/front page configuration checks.
 *
 * @since 1.0.0
 */
class Utils {

	/**
	 * Returns whether the site uses pretty permalinks, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if a custom permalink structure is configured.
	 */
	public static function using_pretty_permalinks(): bool {
		return memo() ?? memo( '' !== \get_option( 'permalink_structure' ) );
	}

	/**
	 * Returns whether the current query supports Better SEO output, memoized.
	 *
	 * Checks post type support, taxonomy support, and query exploitation.
	 * Applies the better_seo_query_supports_seo filter.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current query supports SEO output.
	 */
	public static function query_supports_seo(): bool {

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition -- Intentional memo pattern.
		if ( null !== $memo = memo() ) {
			return $memo;
		}

		$supported = match ( true ) {
			\is_feed(),
			\is_customize_preview(),
			\defined( 'REST_REQUEST' ) && \REST_REQUEST
				=> false,

			Query::is_singular()
				// Most likely scenario — may collide with is_feed() et al.
				=> Post_Type::is_supported() && ( Query::get_the_real_id() || Query::is_real_front_page() ),

			\is_post_type_archive()
				=> Post_Type::is_pta_supported(),

			Query::is_category() || Query::is_tag() || Query::is_tax()
				// When a term has no posts, get_post_type() may fail — test taxonomy support instead.
				=> Taxonomy::is_supported() && Query::get_the_real_id(),

			default
				// Everything else: homepage, 404, search, edge-cases.
				=> true,
		};

		// Exploited queries always need SEO output (robots noindex etc.).
		if ( ! $supported && self::is_query_exploited() ) {
			$supported = true;
		}

		/**
		 * Filters whether the current query supports Better SEO output.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $supported Whether the current query supports SEO output.
		 */
		return memo( (bool) \apply_filters( 'better_seo_query_supports_seo', $supported ) );
	}

	/**
	 * Returns whether the current query appears to be exploited (invalid/malicious), memoized.
	 *
	 * Checks for numeric, array, search-required, home-as-page, and 404-expected
	 * query variable patterns that indicate an exploited query.
	 * Applies the better_seo_exploitable_query_endpoints filter.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current query appears to be exploited.
	 */
	public static function is_query_exploited(): bool {

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition -- Intentional memo pattern.
		if ( null !== $memo = memo() ) {
			return $memo;
		}

		if ( ! Data\Plugin::get_option( 'advanced_query_protection' ) ) {
			return memo( false );
		}

		// When the page ID is not 0, a real page will always be returned.
		if ( Query::get_the_real_id() ) {
			return memo( false );
		}

		global $wp_query;

		if ( ! isset( $wp_query->query ) ) {
			return false;
		}

		/**
		 * Filters the exploitable query endpoints for Better SEO.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, array<int, string>> $exploitables Map of exploit type to query variable names.
		 */
		$exploitables = \apply_filters(
			'better_seo_exploitable_query_endpoints',
			[
				'numeric'          => [
					'page_id',
					'attachment_id',
					'year',
					'monthnum',
					'day',
					'w',
					'm',
					'p',
					'paged', // 'page' is mitigated by WordPress.
					'hour',
					'minute',
					'second',
					'subpost_id',
				],
				'numeric_array'    => [
					'cat',
					'author',
				],
				'requires_s'       => [
					'sentence',
				],
				'not_home_as_page' => array_keys( $GLOBALS['wp']->query_vars ?? [] ),
				'should_be_404'    => [
					'sitemap',
					'sitemap-subtype',
				],
			],
		);

		$query = $wp_query->query;

		foreach ( $exploitables as $type => $qvs ) {
			foreach ( $qvs as $qv ) {
				if ( ! isset( $query[ $qv ] ) ) {
					continue;
				}

				switch ( $type ) {
					case 'numeric':
						if ( '0' === $query[ $qv ] || ! is_numeric( $query[ $qv ] ) ) {
							return memo( true );
						}
						break;

					case 'numeric_array':
						if ( ! self::using_pretty_permalinks() ) {
							break;
						}

						if ( ! preg_match( '/^[1-9]\d*$/', $query[ $qv ] ) ) {
							return memo( true );
						}
						break;

					case 'requires_s':
						if ( ! isset( $query['s'] ) ) {
							return memo( true );
						}
						break;

					case 'not_home_as_page':
						if ( Query::is_blog_as_page() ) {
							return memo( true );
						} else {
							continue 3; // 1: switch, 2: loop $qvs, 3: loop $exploitables.
						}
						break; // unreachable — kept for static analysis.

					case 'should_be_404':
						if ( \is_404() ) {
							return memo( true );
						} else {
							continue 3; // 1: switch, 2: loop $qvs, 3: loop $exploitables.
						}
				}
			}
		}

		return memo( false );
	}

	/**
	 * Returns whether WordPress is configured to show a static page on the front.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if 'show_on_front' is set to 'page'.
	 */
	public static function has_page_on_front(): bool {
		return 'page' === \get_option( 'show_on_front' );
	}

	/**
	 * Returns whether a specific page is assigned as the front page.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if a page is assigned as the static front page.
	 */
	public static function has_assigned_page_on_front(): bool {
		return self::has_page_on_front() && (bool) \get_option( 'page_on_front' );
	}

	/**
	 * Returns whether the site has a blog posts page configured.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if a blog posts page exists or the front page shows posts.
	 */
	public static function has_blog_page(): bool {
		return ! self::has_page_on_front() || (bool) \get_option( 'page_for_posts' );
	}
}