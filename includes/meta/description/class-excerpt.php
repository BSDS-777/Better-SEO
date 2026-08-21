<?php
/**
 * Better SEO - Meta Description Excerpt
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Meta\Description
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

namespace Better_SEO\Meta\Description;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\{
	get_query_type_from_args,
	memo,
	normalize_generation_args,
};

use Better_SEO\{
	Data,
	Helper\Query,
	Helper\Format,
};

/**
 * Class Better_SEO\Meta\Description\Excerpt
 *
 * Provides excerpt extraction for Better SEO auto-description generation,
 * supporting singular posts, archive pages, terms, PTAs, and author pages.
 *
 * @since 1.0.0
 */
class Excerpt {

	/**
	 * Returns the excerpt for the given args or current query context.
	 *
	 * Applies the better_seo_get_excerpt filter.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The excerpt string.
	 */
	public static function get_excerpt( ?array $args = null ): string {
		/**
		 * Filters the Better SEO excerpt for description generation.
		 *
		 * @since 1.0.0
		 *
		 * @param string                    $excerpt The excerpt string.
		 * @param array<string, mixed>|null $args    The generation args, or null.
		 */
		return \apply_filters(
			'better_seo_get_excerpt',
			isset( $args )
				? self::get_excerpt_from_args( $args )
				: self::get_excerpt_from_query(),
			$args,
		);
	}

	/**
	 * Returns the post excerpt for the given args or current query context.
	 *
	 * Alias of get_excerpt() for backward compatibility.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The excerpt string.
	 */
	public static function get_post_excerpt( ?array $args = null ): string {
		return self::get_excerpt( $args );
	}

	/**
	 * Returns the excerpt from the current query context, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return string The excerpt string.
	 */
	public static function get_excerpt_from_query(): string {

		if ( null !== $memo = memo() ) {
			return $memo;
		}

		if ( Query::is_blog_as_page() ) {
			$excerpt = self::get_blog_page_excerpt();
		} elseif ( Query::is_singular() ) {
			$excerpt = self::get_singular_excerpt();
		} elseif ( Query::is_archive() ) {
			$excerpt = self::get_archive_excerpt();
		}

		return memo( $excerpt ?? '' ?: '' );
	}

	/**
	 * Returns the excerpt from the given generation args.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args The generation args.
	 * @return string The excerpt string.
	 */
	public static function get_excerpt_from_args( array $args ): string {

		normalize_generation_args( $args );

		$excerpt = match ( get_query_type_from_args( $args ) ) {
			'single'   => Query::is_blog_as_page( $args['id'] )
				? self::get_blog_page_excerpt()
				: self::get_singular_excerpt( $args['id'] ),
			'term'     => self::get_archive_excerpt( \get_term( $args['id'], $args['tax'] ) ),
			'homeblog' => self::get_blog_page_excerpt(),
			'pta'      => self::get_archive_excerpt( \get_post_type_object( $args['pta'] ) ),
			'user'     => self::get_archive_excerpt( Data\User::get_userdata( $args['uid'] ) ),
			default    => null,
		};

		return $excerpt ?? '';
	}

	/**
	 * Returns the blog page excerpt (site name with "Latest posts:" prefix).
	 *
	 * @since 1.0.0
	 *
	 * @return string The blog page excerpt string.
	 */
	private static function get_blog_page_excerpt(): string {
		return \sprintf(
			\__( 'Latest posts: %s', 'better-seo' ),
			Data\Blog::get_public_blog_name(),
		);
	}

	/**
	 * Returns the archive excerpt for the given object or current archive query.
	 *
	 * Handles terms, post type archives, author archives, and date archives.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Term|\WP_Post_Type|\WP_User|null $object Optional archive object. Default null (current query).
	 * @return string The archive excerpt string.
	 */
	private static function get_archive_excerpt( mixed $object = null ): string {

		if ( \is_null( $object ) ) {
			$in_the_loop = true;
			$object      = \get_queried_object();
		} else {
			if ( \is_wp_error( $object ) ) {
				return '';
			}

			$in_the_loop = false;
		}

		// Check for deprecated filter — replaced by better_seo_get_excerpt.
		$excerpt = (string) \apply_filters_deprecated(
			'better_seo_generated_archive_excerpt',
			[ '', $object ],
			'1.0.0 of Better SEO',
			'better_seo_get_excerpt',
		);

		if ( $excerpt ) {
			return $excerpt;
		}

		if ( $in_the_loop ) {
			if ( Query::is_category() || Query::is_tag() || Query::is_tax() ) {
				$excerpt = $object->description ?? '';
			} elseif ( Query::is_author() ) {
				$excerpt = Format\HTML::extract_content( \get_the_author_meta(
					'description',
					(int) \get_query_var( 'author' ),
				) );
			} elseif ( \is_post_type_archive() ) {
				// Check for deprecated PTA description filter — replaced by better_seo_get_excerpt.
				$excerpt = (string) \apply_filters_deprecated(
					'better_seo_pta_description_excerpt',
					[
						$object->description ?? '',
						$object,
					],
					'1.0.0 of Better SEO',
					'better_seo_get_excerpt',
				);
			} else {
				// Check for deprecated fallback archive description filter — replaced by better_seo_get_excerpt.
				$excerpt = (string) \apply_filters_deprecated(
					'better_seo_fallback_archive_description_excerpt',
					[ '', $object ],
					'1.0.0 of Better SEO',
					'better_seo_get_excerpt',
				);
			}
		} else {
			$excerpt = $object->description ?? '';
		}

		return $excerpt;
	}

	/**
	 * Returns the singular post excerpt for the given post ID or current post.
	 *
	 * Extracts from the post excerpt field first, then falls back to post content.
	 * Returns empty string for protected posts or posts using non-HTML page builders.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $id Optional post ID. Default null (current post).
	 * @return string The singular excerpt string.
	 */
	private static function get_singular_excerpt( ?int $id = null ): string {

		$post = \get_post( $id ?? Query::get_the_real_id() );

		if ( ! $post || Data\Post::is_protected( $post ) ) {
			return '';
		}

		$excerpt = Data\Post::get_excerpt( $post );

		if ( empty( $excerpt ) && ! Data\Post::uses_non_html_page_builder( $post->ID ) ) {
			$excerpt = Data\Post::get_content( $post );

			if ( $excerpt ) {
				$excerpt = Format\HTML::strip_paragraph_urls( Format\HTML::strip_newline_urls( $excerpt ) );
			}
		}

		if ( empty( $excerpt ) ) {
			return '';
		}

		return Format\HTML::extract_content( $excerpt );
	}
}
