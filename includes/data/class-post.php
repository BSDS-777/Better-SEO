<?php
/**
 * Better SEO - Data Post
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

use Better_SEO\{
	Helper,
	Helper\Format\Time,
	Helper\Query,
};

/**
 * Class Better_SEO\Data\Post
 *
 * Provides data helper methods for post-level information,
 * including content, protection state, draft state, timestamps, and ancestry.
 *
 * @since 1.0.0
 * @access protected
 *         Use better_seo()->data()->post() instead.
 */
class Post {

	/**
	 * Returns the post excerpt if the post type supports it.
	 *
	 * '0' is not considered valid content — returns empty string in that case.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post|int|null $post The post object or post ID. Defaults to current post.
	 * @return string The post excerpt, or empty string if not available.
	 */
	public static function get_excerpt( \WP_Post|int|null $post = null ): string {

		$post = \get_post( $post ?: Query::get_the_real_id() );

		return ! empty( $post->post_excerpt ) && \post_type_supports( $post->post_type, 'excerpt' )
			? $post->post_excerpt
			: '';
	}

	/**
	 * Returns the post content if the post type supports the editor.
	 *
	 * '0' is not considered valid content — returns empty string in that case.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post|int|null $post The post object or post ID. Defaults to current post.
	 * @return string The post content, or empty string if not available.
	 */
	public static function get_content( \WP_Post|int|null $post = null ): string {

		$post = \get_post( $post ?: Query::get_the_real_id() );

		return ! empty( $post->post_content ) && \post_type_supports( $post->post_type, 'editor' )
			? $post->post_content
			: '';
	}

	/**
	 * Returns whether the post uses a non-HTML page builder.
	 *
	 * Detects Divi (ET Builder), WPBakery (Visual Composer), and Bricks Builder.
	 * Applies the better_seo_detect_non_html_page_builder filter for extensibility.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID. Defaults to current post.
	 * @return bool True if a non-HTML page builder is detected, false otherwise.
	 */
	public static function uses_non_html_page_builder( int $post_id = 0 ): bool {

		$post_id = $post_id ?: Query::get_the_real_id();
		$meta    = \get_post_meta( $post_id );

		/**
		 * Filters the non-HTML page builder detection result.
		 *
		 * @since 1.0.0
		 *
		 * @param bool|null            $detected Null to use default detection, true/false to override.
		 * @param int                  $post_id  The post ID.
		 * @param array<string, mixed> $meta     The post meta array.
		 */
		$detected = \apply_filters( 'better_seo_detect_non_html_page_builder', null, $post_id, $meta );

		if ( \is_bool( $detected ) ) {
			return $detected;
		}

		// If there's no meta or no builder active, it doesn't use a builder.
		if ( empty( $meta ) || ! Helper\Compatibility::is_non_html_builder_active() ) {
			return false;
		}

		return ( 'on' === ( $meta['_et_pb_use_builder'][0] ?? '' ) && \defined( 'ET_BUILDER_VERSION' ) )
			|| ( 'true' === ( $meta['_wpb_vc_js_status'][0] ?? '' ) && \defined( 'WPB_VC_VERSION' ) )
			|| ( 'bricks' === ( $meta['_bricks_editor_mode'][0] ?? '' ) && \defined( 'BRICKS_VERSION' ) );
	}

	/**
	 * Returns whether the post is protected (password-protected or private).
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post|int|null $post The post object or post ID.
	 * @return bool True if the post is protected, false otherwise.
	 */
	public static function is_protected( \WP_Post|int|null $post = null ): bool {
		$post = \get_post( $post );
		return self::is_password_protected( $post ) || self::is_private( $post );
	}

	/**
	 * Returns whether the post is password-protected.
	 *
	 * Avoids fetching the post object if the password can be read directly.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post|int|null $post The post object or post ID.
	 * @return bool True if the post has a password, false otherwise.
	 */
	public static function is_password_protected( \WP_Post|int|null $post = null ): bool {
		return ! empty( $post->post_password ?? \get_post( $post )->post_password ?? '' );
	}

	/**
	 * Returns whether the post has a private status.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post|int|null $post The post object or post ID.
	 * @return bool True if the post status is 'private', false otherwise.
	 */
	public static function is_private( \WP_Post|int|null $post = null ): bool {
		return 'private' === ( $post->post_status ?? \get_post( $post )->post_status ?? false );
	}

	/**
	 * Returns whether the post is in a draft-like state.
	 *
	 * Considers 'draft', 'auto-draft', and 'pending' as draft states.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post|int|null $post The post object or post ID.
	 * @return bool True if the post is in a draft state, false otherwise.
	 */
	public static function is_draft( \WP_Post|int|null $post = null ): bool {

		return match ( $post->post_status ?? \get_post( $post )->post_status ?? '' ) {
			'draft', 'auto-draft', 'pending' => true,
			default                          => false,
		};
	}

	/**
	 * Returns the ID of the most recently published post or page, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return int|false The latest post ID, or false if none found.
	 */
	public static function get_latest_post_id(): int|false {

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition -- Intentional memo pattern.
		if ( null !== $memo = memo() ) {
			return $memo;
		}

		$query = new \WP_Query( [
			'posts_per_page'   => 1,
			'post_type'        => [ 'post', 'page' ],
			'orderby'          => 'date',
			'order'            => 'DESC',
			'post_status'      => [ 'publish', 'future', 'pending' ],
			'fields'           => 'ids',
			'cache_results'    => false,
			'suppress_filters' => true,
			'no_found_rows'    => true,
		] );

		return memo( reset( $query->posts ) );
	}

	/**
	 * Returns whether any published posts exist for a given post type archive, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type The post type slug.
	 * @return bool True if published posts exist for the post type, false otherwise.
	 */
	public static function has_posts_in_pta( string $post_type ): bool {

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition -- Intentional memo pattern.
		if ( null !== $memo = memo( null, $post_type ) ) {
			return $memo;
		}

		$query = new \WP_Query( [
			'posts_per_page' => 1,
			'post_type'      => [ $post_type ],
			'orderby'        => 'date',
			'order'          => 'ASC',
			'post_status'    => 'publish',
			'has_password'   => false,
			'fields'         => 'ids',
			'cache_results'  => false,
			'no_found_rows'  => true,
		] );

		return memo( ! empty( $query->posts ), $post_type );
	}

	/**
	 * Returns the published time of a post in the preferred format.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $id The post ID. Defaults to current post.
	 * @return string The formatted published time string.
	 */
	public static function get_published_time( ?int $id = null ): string {
		return Time::convert_to_preferred_format(
			\get_post( $id ?? Query::get_the_real_id() )->post_date_gmt ?? '',
		);
	}

	/**
	 * Returns the last modified time of a post in the preferred format.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $id The post ID. Defaults to current post.
	 * @return string The formatted modified time string.
	 */
	public static function get_modified_time( ?int $id = null ): string {
		return Time::convert_to_preferred_format(
			\get_post( $id ?? Query::get_the_real_id() )->post_modified_gmt ?? '',
		);
	}

	/**
	 * Returns an array of parent post objects for a given post, ordered from root to immediate parent.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $id           The post ID. Defaults to current post.
	 * @param bool     $include_self Whether to include the post itself in the result. Default false.
	 * @return array<int, \WP_Post> Map of post ID to WP_Post parent objects.
	 */
	public static function get_post_parents( ?int $id = null, bool $include_self = false ): array {

		$post      = \get_post( $id ?? Query::get_the_real_id() );
		$ancestors = \get_post_type_object( $post->post_type ?? '' )->hierarchical
			? $post->ancestors
			: [];

		$parents = [];

		foreach ( array_reverse( $ancestors ) as $post_id ) {
			$parent = \get_post( $post_id );

			if ( $parent ) {
				$parents[ $post_id ] = $parent;
			}
		}

		if ( $include_self ) {
			$parents[ $id ] = $post;
		}

		return $parents;
	}
}