<?php
/**
 * Better SEO - Sitemap Utils
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Sitemap
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

namespace Better_SEO\Sitemap;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use const Better_SEO\ROBOTS_IGNORE_PROTECTION;

use function Better_SEO\memo;

use Better_SEO\{
	Data,
	Data\Filter\Sanitize,
	Meta,
};

/**
 * Class Better_SEO\Sitemap\Utils
 *
 * Provides utility methods for Better SEO sitemap generation, including
 * post/term inclusion checks, sitemap post limits, color settings,
 * and core sitemap compatibility detection.
 *
 * @since 1.0.0
 */
class Utils {

	/**
	 * Returns the sitemap post query limit for the given post type hierarchy.
	 *
	 * Applies the better_seo_sitemap_post_limit filter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The post type hierarchy type ('nonhierarchical' or 'hierarchical'). Default 'nonhierarchical'.
	 * @return int The sitemap post query limit.
	 */
	public static function get_sitemap_post_limit( string $type = 'nonhierarchical' ): int {
		/**
		 * Filters the Better SEO sitemap post query limit.
		 *
		 * @since 1.0.0
		 *
		 * @param int  $limit        The configured sitemap query limit.
		 * @param bool $hierarchical Whether the post type is hierarchical.
		 */
		return (int) \apply_filters(
			'better_seo_sitemap_post_limit',
			Data\Plugin::get_option( 'sitemap_query_limit' ),
			'hierarchical' === $type,
		);
	}

	/**
	 * Returns whether the given post ID should be included in the sitemap.
	 *
	 * Checks against the excluded IDs list, robots noindex meta, and redirect URL.
	 * Uses a static cache for the excluded IDs list.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID to check.
	 * @return bool True if the post should be included in the sitemap, false otherwise.
	 */
	public static function is_post_included_in_sitemap( int $post_id ): bool {

		static $excluded;

		if ( ! isset( $excluded ) ) {
			/**
			 * Filters the list of post IDs excluded from the Better SEO sitemap.
			 *
			 * @since 1.0.0
			 * @param array<int, int> $excluded_ids The excluded post IDs.
			 */
			$excluded = (array) \apply_filters( 'better_seo_sitemap_exclude_ids', [] );

			// Flip to associative for O(1) lookup.
			$excluded = $excluded ? array_flip( $excluded ) : [];
		}

		$included = ! isset( $excluded[ $post_id ] );

		while ( $included ) {
			$generator_args = [ 'id' => $post_id ];

			// Exclude noindexed posts.
			$included = 'noindex'
				!== (
					Meta\Robots::get_generated_meta(
						$generator_args,
						[ 'noindex' ],
						ROBOTS_IGNORE_PROTECTION,
					)['noindex'] ?? false
				);

			if ( ! $included ) {
				break;
			}

			// Exclude posts with a redirect URL set.
			$included = ! Meta\URI::get_redirect_url( $generator_args );
			break;
		}

		return $included;
	}

	/**
	 * Returns whether the given term should be included in the sitemap.
	 *
	 * Checks against the excluded term IDs list, robots noindex meta, and redirect URL.
	 * Uses a static cache for the excluded term IDs list.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $term_id  The term ID to check.
	 * @param string $taxonomy The taxonomy slug.
	 * @return bool True if the term should be included in the sitemap, false otherwise.
	 */
	public static function is_term_included_in_sitemap( int $term_id, string $taxonomy ): bool {

		static $excluded;

		if ( ! isset( $excluded ) ) {
			/**
			 * Filters the list of term IDs excluded from the Better SEO sitemap.
			 *
			 * @since 1.0.0
			 * @param array<int, int> $excluded_ids The excluded term IDs.
			 */
			$excluded = (array) \apply_filters( 'better_seo_sitemap_exclude_term_ids', [] );

			// Flip to associative for O(1) lookup.
			$excluded = $excluded ? array_flip( $excluded ) : [];
		}

		$included = ! isset( $excluded[ $term_id ] );

		while ( $included ) {
			$generator_args = [
				'id'  => $term_id,
				'tax' => $taxonomy,
			];

			// Exclude noindexed terms.
			$included = 'noindex'
				!== (
					Meta\Robots::get_generated_meta(
						$generator_args,
						[ 'noindex' ],
						ROBOTS_IGNORE_PROTECTION,
					)['noindex'] ?? false
				);

			if ( ! $included ) {
				break;
			}

			// Exclude terms with a redirect URL set.
			$included = ! Meta\URI::get_redirect_url( $generator_args );
			break;
		}

		return $included;
	}

	/**
	 * Returns the sitemap color settings array.
	 *
	 * Returns defaults (Navy primary, Gold accent) when $get_defaults is true
	 * or when the configured colors are empty.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $get_defaults Whether to return only the default colors. Default false.
	 * @return array{main: string, accent: string} The sitemap color settings.
	 */
	public static function get_sitemap_colors( bool $get_defaults = false ): array {

		$defaults = [
			'main'   => '#1a1a2e', // Navy — Primary dark.
			'accent' => '#c9a84c', // Gold — Primary accent.
		];

		if ( $get_defaults ) {
			return $defaults;
		}

		$main   = Sanitize::rgb_hex( Data\Plugin::get_option( 'sitemap_color_main' ) );
		$accent = Sanitize::rgb_hex( Data\Plugin::get_option( 'sitemap_color_accent' ) );

		return array_merge(
			$defaults,
			array_filter( [
				'main'   => $main ? "#{$main}" : '',
				'accent' => $accent ? "#{$accent}" : '',
			] ),
		);
	}

	/**
	 * Returns whether WordPress core sitemaps should be used instead of Better SEO sitemaps.
	 *
	 * Returns false if Better SEO sitemaps are enabled. Otherwise checks whether
	 * the WordPress core sitemap server has sitemaps enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if core sitemaps should be used, false otherwise.
	 */
	public static function use_core_sitemaps(): bool {

		if ( null !== $memo = memo() ) {
			return $memo;
		}

		if ( Data\Plugin::get_option( 'sitemaps_output' ) ) {
			return memo( false );
		}

		$wp_sitemaps_server = \wp_sitemaps_get_server();

		return memo(
			method_exists( $wp_sitemaps_server, 'sitemaps_enabled' ) && $wp_sitemaps_server->sitemaps_enabled()
		);
	}

	/**
	 * Returns whether the optimized Better SEO sitemap may be output.
	 *
	 * Requires sitemaps output to be enabled and the site to not be spam or deleted.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the optimized sitemap may be output.
	 */
	public static function may_output_optimized_sitemap(): bool {
		return Data\Plugin::get_option( 'sitemaps_output' )
			&& ! Data\Blog::is_spam_or_deleted();
	}

	/**
	 * Returns whether a sitemap.xml file exists at the WordPress root path, memoized.
	 *
	 * Loads wp-admin/includes/file.php if get_home_path() is not yet available.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if a sitemap.xml file exists at the root, false otherwise.
	 */
	public static function has_root_sitemap_xml(): bool {

		if ( null !== $memo = memo() ) {
			return $memo;
		}

		if ( ! \function_exists( 'get_home_path' ) ) {
			require_once \ABSPATH . 'wp-admin/includes/file.php';
		}

		$path = \get_home_path() . 'sitemap.xml';

		return memo( file_exists( $path ) );
	}
}