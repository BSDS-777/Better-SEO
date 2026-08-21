<?php
/**
 * Better SEO - Helper Query
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Helper
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

namespace Better_SEO\Helper;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\umemo;

use Better_SEO\{
	Admin,
	Data,
};

/**
 * Class Better_SEO\Helper\Query
 *
 * Provides query detection and ID resolution utilities for Better SEO,
 * covering both frontend and admin contexts including singular, archive,
 * taxonomy, author, search, and pagination states.
 *
 * @since 1.0.0
 */
class Query {

	/**
	 * Returns the real post type for the current or given post/query context.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post|int|null $post Optional post object or ID. Default null.
	 * @return string|false The post type slug, or false if not determinable.
	 */
	public static function get_post_type_real_id( \WP_Post|int|null $post = null ): string|false {

		if ( isset( $post ) ) {
			return \get_post_type( $post );
		}

		if ( self::is_archive() ) {
			if ( self::is_category() || self::is_tag() || self::is_tax() ) {
				$post_type = Taxonomy::get_post_types();
				$post_type = \is_array( $post_type ) ? reset( $post_type ) : $post_type;
			} elseif ( \is_post_type_archive() ) {
				$post_type = \get_query_var( 'post_type' );
				$post_type = \is_array( $post_type ) ? reset( $post_type ) : $post_type;
			} else {
				// Let WP guess — works reliably enough on non-404 queries.
				$post_type = \get_post_type();
			}
		} else {
			$post_type = \get_post_type( self::get_the_real_id() );
		}

		return $post_type;
	}

	/**
	 * Returns the post type of the current admin screen.
	 *
	 * @since 1.0.0
	 *
	 * @return string The current admin screen post type, or empty string if not set.
	 */
	public static function get_admin_post_type(): string {
		return $GLOBALS['current_screen']->post_type ?? '';
	}

	/**
	 * Returns the real ID for the current query context, memoized.
	 *
	 * In admin context, delegates to get_the_real_admin_id().
	 * On the frontend, applies the better_seo_real_id and better_seo_current_object_id filters.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $use_cache Whether to use the memo cache. Default true.
	 * @return int The current object ID.
	 */
	public static function get_the_real_id( bool $use_cache = true ): int {

		if ( \is_admin() ) {
			return self::get_the_real_admin_id();
		}

		if ( $use_cache && ( null !== $memo = umemo( __METHOD__ ) ) ) {
			return $memo;
		}

		if ( $use_cache ) {
			/**
			 * Filters the real ID for feed requests.
			 *
			 * @since 1.0.0
			 * @param int $id The feed post ID, or 0.
			 */
			$id = \apply_filters(
				'better_seo_real_id',
				\is_feed() ? \get_the_id() : 0,
			);
		}

		/**
		 * Filters the current object ID for Better SEO.
		 *
		 * @since 1.0.0
		 *
		 * @param int  $id        The resolved object ID.
		 * @param bool $use_cache Whether caching is enabled.
		 */
		$id = (int) \apply_filters(
			'better_seo_current_object_id',
			( $id ?? 0 ) ?: \get_queried_object_id(),
			$use_cache,
		);

		return $use_cache
			? umemo( __METHOD__, $id )
			: $id;
	}

	/**
	 * Returns the real ID for the current admin context.
	 *
	 * Applies the better_seo_current_admin_id filter.
	 *
	 * @since 1.0.0
	 *
	 * @return int The current admin object ID.
	 */
	public static function get_the_real_admin_id(): int {
		/**
		 * Filters the current admin object ID for Better SEO.
		 *
		 * @since 1.0.0
		 * @param int $id The resolved admin object ID.
		 */
		return (int) \apply_filters(
			'better_seo_current_admin_id',
			   \get_the_id()
			?: self::get_admin_post_id()
			?: self::get_admin_term_id()
		);
	}

	/**
	 * Returns the front page post ID, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return int The front page post ID, or 0 if no page is set on front.
	 */
	public static function get_the_front_page_id(): int {
		return umemo( __METHOD__ )
			?? umemo(
				__METHOD__,
				Query\Utils::has_page_on_front()
					? (int) \get_option( 'page_on_front' )
					: 0,
			);
	}

	/**
	 * Returns the post ID from the current admin post edit screen.
	 *
	 * @since 1.0.0
	 *
	 * @return int The admin post ID, or 0 if not on a post edit screen.
	 */
	public static function get_admin_post_id(): int {
		return self::is_post_edit()
			// phpcs:ignore WordPress.Security.NonceVerification -- current_screen validated the 'post' object.
			? \absint( $_GET['post'] ?? $_GET['post_id'] ?? 0 )
			: 0;
	}

	/**
	 * Returns the term ID from the current admin term edit screen.
	 *
	 * @since 1.0.0
	 *
	 * @return int The admin term ID, or 0 if not on a term edit screen.
	 */
	public static function get_admin_term_id(): int {
		return self::is_archive_admin()
			? \absint( $GLOBALS['tag_ID'] ?? 0 )
			: 0;
	}

	/**
	 * Returns the current taxonomy slug for the admin or frontend context, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return string The current taxonomy slug, or empty string if not applicable.
	 */
	public static function get_current_taxonomy(): string {
		return Query\Cache::memo()
			?? Query\Cache::memo(
				( \is_admin() ? $GLOBALS['current_screen'] : \get_queried_object() )
					->taxonomy ?? '',
			);
	}

	/**
	 * Returns the current post type slug for the admin or frontend context, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return string The current post type slug, or empty string if not applicable.
	 */
	public static function get_current_post_type(): string {
		return Query\Cache::memo()
			?? Query\Cache::memo(
				\is_admin()
					? self::get_admin_post_type()
					: self::get_post_type_real_id()
			);
	}

	/**
	 * Returns whether the current request is for an attachment.
	 *
	 * @since 1.0.0
	 *
	 * @param string|\WP_Post|int $attachment Optional attachment identifier. Default empty.
	 * @return bool True if the current request is for an attachment.
	 */
	public static function is_attachment( string|\WP_Post|int $attachment = '' ): bool {

		if ( \is_admin() ) {
			return self::is_attachment_admin();
		}

		if ( ! $attachment ) {
			return \is_attachment();
		}

		return Query\Cache::memo( null, $attachment )
			?? Query\Cache::memo(
				\is_attachment( $attachment ),
				$attachment,
			);
	}

	/**
	 * Returns whether the current admin screen is an attachment edit screen.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if on an attachment admin screen.
	 */
	public static function is_attachment_admin(): bool {
		return self::is_singular_admin() && 'attachment' === self::is_singular_admin();
	}

	/**
	 * Returns whether the given post is a singular archive (blog-as-page).
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post|int|null $post Optional post object or ID. Default null.
	 * @return bool True if the post is a singular archive.
	 */
	public static function is_singular_archive( \WP_Post|int|null $post = null ): bool {

		if ( isset( $post ) ) {
			$id = \is_int( $post )
				? $post
				: ( \get_post( $post )->ID ?? 0 );
		} else {
			$id = null;
		}

		return Query\Cache::memo( null, $id )
			?? Query\Cache::memo(
				/**
				 * Filters whether the current post is a singular archive.
				 *
				 * @since 1.0.0
				 *
				 * @param bool     $is_singular_archive Whether the post is a singular archive.
				 * @param int|null $id                  The post ID, or null for current.
				 */
				(bool) \apply_filters(
					'better_seo_is_singular_archive',
					self::is_blog_as_page( $id ),
					$id,
				),
				$id,
			);
	}

	/**
	 * Returns whether the current request is an archive.
	 *
	 * Handles both frontend and admin contexts.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current request is an archive.
	 */
	public static function is_archive(): bool {

		if ( \is_admin() ) {
			return self::is_archive_admin();
		}

		if ( null !== $memo = Query\Cache::memo() ) {
			return $memo;
		}

		if ( \is_archive() && false === self::is_singular() ) {
			return Query\Cache::memo( true );
		}

		if ( isset( $GLOBALS['wp_query']->query ) && false === self::is_singular() ) {
			global $wp_query;

			if (
				$wp_query->is_tax
				|| $wp_query->is_category
				|| $wp_query->is_tag
				|| $wp_query->is_post_type_archive
				|| $wp_query->is_author
				|| $wp_query->is_date
			) {
				return Query\Cache::memo( true );
			}
		}

		return Query\Cache::memo( false );
	}

	/**
	 * Returns whether the current admin screen is an archive (term/tag) screen.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if on an archive admin screen.
	 */
	public static function is_archive_admin(): bool {
		return match ( $GLOBALS['current_screen']->base ?? '' ) {
			'edit-tags', 'term' => true,
			default             => false,
		};
	}

	/**
	 * Returns whether the current admin screen is a term edit screen.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if on a term edit screen.
	 */
	public static function is_term_edit(): bool {
		return 'term' === ( $GLOBALS['current_screen']->base ?? '' );
	}

	/**
	 * Returns whether the current admin screen is a post edit screen.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if on a post edit screen.
	 */
	public static function is_post_edit(): bool {
		return 'post' === ( $GLOBALS['current_screen']->base ?? '' );
	}

	/**
	 * Returns whether the current admin screen is a list table edit screen.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if on a list table (edit or edit-tags) screen.
	 */
	public static function is_wp_lists_edit(): bool {
		return match ( $GLOBALS['current_screen']->base ?? '' ) {
			'edit-tags', 'edit' => true,
			default             => false,
		};
	}

	/**
	 * Returns whether the current admin screen is a user profile edit screen.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if on a profile or user edit screen.
	 */
	public static function is_profile_edit(): bool {
		return match ( $GLOBALS['current_screen']->base ?? '' ) {
			'profile', 'profile-network', 'user-edit', 'user-edit-network' => true,
			default => false,
		};
	}

	/**
	 * Returns whether the current request is an author archive.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int|\WP_User $author Optional author identifier. Default empty.
	 * @return bool True if the current request is an author archive.
	 */
	public static function is_author( string|int|\WP_User $author = '' ): bool {

		if ( ! $author ) {
			return \is_author();
		}

		return Query\Cache::memo( null, $author )
			?? Query\Cache::memo(
				\is_author( $author ),
				$author,
			);
	}

	/**
	 * Returns whether the current request or given post is the blog posts page.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post|int|null $post Optional post object or ID. Default null.
	 * @return bool True if the current request or post is the blog page.
	 */
	public static function is_blog( \WP_Post|int|null $post = null ): bool {

		if ( isset( $post ) ) {
			$id = \is_int( $post )
				? ( $post ?: null )
				: ( \get_post( $post )->ID ?? null );

			return ( (int) \get_option( 'page_for_posts' ) ) === $id;
		}

		return Query\Utils::has_blog_page() && \is_home();
	}

	/**
	 * Returns whether the current request or given post is the blog page when a static front page is set.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post|int|null $post Optional post object or ID. Default null.
	 * @return bool True if the post is the blog-as-page in a static front page setup.
	 */
	public static function is_blog_as_page( \WP_Post|int|null $post = null ): bool {
		return Query\Utils::has_page_on_front() ? self::is_blog( $post ) : false;
	}

	/**
	 * Returns whether the current request is a category archive.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int|\WP_Term $category Optional category identifier. Default empty.
	 * @return bool True if the current request is a category archive.
	 */
	public static function is_category( string|int|\WP_Term $category = '' ): bool {

		if ( \is_admin() ) {
			return self::is_category_admin();
		}

		return Query\Cache::memo( null, $category )
			?? Query\Cache::memo(
				\is_category( $category ),
				$category,
			);
	}

	/**
	 * Returns whether the current admin screen is a category archive screen.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if on a category admin screen.
	 */
	public static function is_category_admin(): bool {
		return self::is_archive_admin() && 'category' === self::get_current_taxonomy();
	}

	/**
	 * Returns whether the current request is an editable term (category, tag, or taxonomy).
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current request is an editable term.
	 */
	public static function is_editable_term(): bool {
		return Query\Cache::memo()
			?? Query\Cache::memo(
				Query::is_category() || Query::is_tag() || Query::is_tax()
			);
	}

	/**
	 * Returns whether the current request is the real front page.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current request is the real front page.
	 */
	public static function is_real_front_page(): bool {
		return Query\Cache::memo()
			?? Query\Cache::memo(
				\is_front_page()
					?: (
						   self::is_blog()
						&& 0 === self::get_the_real_id()
						&& 'post' !== \get_option( 'show_on_front' )
					),
			);
	}

	/**
	 * Returns whether the given post ID is the real front page.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The post ID to check.
	 * @return bool True if the given ID matches the front page ID.
	 */
	public static function is_real_front_page_by_id( int $id ): bool {
		return self::get_the_front_page_id() === $id;
	}

	/**
	 * Returns whether the current request is a page.
	 *
	 * @since 1.0.0
	 *
	 * @param string|\WP_Post|int $page Optional page identifier. Default empty.
	 * @return bool True if the current request is a page.
	 */
	public static function is_page( string|\WP_Post|int $page = '' ): bool {

		if ( \is_admin() ) {
			return self::is_page_admin();
		}

		if ( empty( $page ) ) {
			return \is_page();
		}

		return Query\Cache::memo( null, $page )
			?? Query\Cache::memo(
				\is_int( $page ) || $page instanceof \WP_Post
					? \in_array(
						\get_post_type( $page ),
						Post_Type::get_all_hierarchical(),
						true,
					)
					: \is_page( $page ),
				$page,
			);
	}

	/**
	 * Returns whether the current admin screen is a hierarchical post type edit screen.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if on a page-type admin screen.
	 */
	public static function is_page_admin(): bool {
		return self::is_singular_admin()
			&& \in_array( self::is_singular_admin(), Post_Type::get_all_hierarchical(), true );
	}

	/**
	 * Returns whether the current request is a valid post preview.
	 *
	 * Verifies user login, singular context, edit capability, and preview nonce.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current request is a valid post preview.
	 */
	public static function is_preview(): bool {

		$is_preview = false;

		if (
			\is_preview()
			&& \is_user_logged_in()
			&& \is_singular()
			&& \current_user_can( 'edit_post', \get_the_id() )
			&& isset( $_GET['preview_id'], $_GET['preview_nonce'] )
			&& \wp_verify_nonce( $_GET['preview_nonce'], 'post_preview_' . (int) $_GET['preview_id'] )
		) {
			$is_preview = true;
		}

		return $is_preview;
	}

	/**
	 * Returns whether the current request is a frontend search.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current request is a search and not in admin.
	 */
	public static function is_search(): bool {
		return \is_search() && ! \is_admin();
	}

	/**
	 * Returns whether the current request is a single post.
	 *
	 * @since 1.0.0
	 *
	 * @param string|\WP_Post|int $post Optional post identifier. Default empty.
	 * @return bool True if the current request is a single post.
	 */
	public static function is_single( string|\WP_Post|int $post = '' ): bool {

		if ( \is_admin() ) {
			return self::is_single_admin();
		}

		return Query\Cache::memo( null, $post )
			?? Query\Cache::memo(
				\is_int( $post ) || $post instanceof \WP_Post
					? \in_array(
						\get_post_type( $post ),
						Post_Type::get_all_nonhierarchical(),
						true,
					)
					: \is_single( $post ),
				$post,
			);
	}

	/**
	 * Returns whether the current admin screen is a non-hierarchical post type edit screen.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if on a single post-type admin screen.
	 */
	public static function is_single_admin(): bool {
		return self::is_singular_admin()
			&& \in_array( self::is_singular_admin(), Post_Type::get_all_nonhierarchical(), true );
	}

	/**
	 * Returns whether the current request is singular.
	 *
	 * @since 1.0.0
	 *
	 * @param string|array<int, string> $post_types Optional post type(s) to check. Default empty.
	 * @return bool|string True/false on frontend; post type string or false in admin.
	 */
	public static function is_singular( string|array $post_types = '' ): bool|string {

		if ( \is_admin() ) {
			return self::is_singular_admin();
		}

		if ( $post_types ) {
			return \is_singular( $post_types );
		}

		return Query\Cache::memo()
			?? Query\Cache::memo( \is_singular() || self::is_singular_archive() );
	}

	/**
	 * Returns whether the current admin screen is a singular post edit screen.
	 *
	 * Returns the screen base string ('edit' or 'post') if true, false otherwise.
	 *
	 * @since 1.0.0
	 *
	 * @return string|false The screen base if singular admin, false otherwise.
	 */
	public static function is_singular_admin(): string|false {
		return match ( $GLOBALS['current_screen']->base ?? '' ) {
			'edit', 'post' => $GLOBALS['current_screen']->base,
			default        => false,
		};
	}

	/**
	 * Returns whether the given post ID is the static front page.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id The post ID to check. Default 0 (current post).
	 * @return bool True if the given ID is the static front page.
	 */
	public static function is_static_front_page( int $id = 0 ): bool {

		$front_id = umemo( __METHOD__ )
			?? umemo(
				__METHOD__,
				Query\Utils::has_assigned_page_on_front()
					? (int) \get_option( 'page_on_front' )
					: false,
			);

		return false !== $front_id
			&& ( $id ?: self::get_the_real_id() ) === $front_id;
	}

	/**
	 * Returns whether the current request is a tag archive.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int|\WP_Term $tag Optional tag identifier. Default empty.
	 * @return bool True if the current request is a tag archive.
	 */
	public static function is_tag( string|int|\WP_Term $tag = '' ): bool {

		if ( \is_admin() ) {
			return self::is_tag_admin();
		}

		return Query\Cache::memo( null, $tag )
			?? Query\Cache::memo(
				\is_tag( $tag ),
				$tag,
			);
	}

	/**
	 * Returns whether the current admin screen is a post_tag archive screen.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if on a post_tag admin screen.
	 */
	public static function is_tag_admin(): bool {
		return self::is_archive_admin() && 'post_tag' === self::get_current_taxonomy();
	}

	/**
	 * Returns whether the current request is a custom taxonomy archive.
	 *
	 * @since 1.0.0
	 *
	 * @param string $taxonomy Optional taxonomy slug. Default empty.
	 * @param string $term     Optional term slug or ID. Default empty.
	 * @return bool True if the current request is a taxonomy archive.
	 */
	public static function is_tax( string $taxonomy = '', string $term = '' ): bool {
		return Query\Cache::memo( null, $taxonomy, $term )
			?? Query\Cache::memo(
				\is_tax( $taxonomy, $term ),
				$taxonomy,
				$term,
			);
	}

	/**
	 * Returns whether the current request is a shop page.
	 *
	 * Applies the better_seo_is_shop filter for extensibility.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post|int|null $post Optional post object or ID. Default null.
	 * @return bool True if the current request is a shop page.
	 */
	public static function is_shop( \WP_Post|int|null $post = null ): bool {
		return Query\Cache::memo( null, $post )
			?? Query\Cache::memo(
				/**
				 * Filters whether the current request is a shop page.
				 *
				 * @since 1.0.0
				 * @param bool                  $is_shop Whether the current request is a shop.
				 * @param \WP_Post|int|null     $post    The post object or ID.
				 */
				(bool) \apply_filters( 'better_seo_is_shop', false, $post ),
				$post,
			);
	}

	/**
	 * Returns whether the current request is a product page.
	 *
	 * Applies the better_seo_is_product filter for extensibility.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Post|int|null $post Optional post object or ID. Default null.
	 * @return bool True if the current request is a product page.
	 */
	public static function is_product( \WP_Post|int|null $post = null ): bool {

		if ( \is_admin() ) {
			return self::is_product_admin();
		}

		return Query\Cache::memo( null, $post )
			?? Query\Cache::memo(
				/**
				 * Filters whether the current request is a product page.
				 *
				 * @since 1.0.0
				 * @param bool              $is_product Whether the current request is a product.
				 * @param \WP_Post|int|null $post       The post object or ID.
				 */
				(bool) \apply_filters( 'better_seo_is_product', false, $post ),
				$post,
			);
	}

	/**
	 * Returns whether the current admin screen is a product admin screen.
	 *
	 * Applies the better_seo_is_product_admin filter for extensibility.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current admin screen is a product screen.
	 */
	public static function is_product_admin(): bool {
		return Query\Cache::memo()
			?? Query\Cache::memo(
				/**
				 * Filters whether the current admin screen is a product screen.
				 *
				 * @since 1.0.0
				 * @param bool $is_product_admin Whether the current admin screen is a product screen.
				 */
				(bool) \apply_filters( 'better_seo_is_product_admin', false ),
			);
	}

	/**
	 * Returns whether the current request uses SSL, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current request is over SSL.
	 */
	public static function is_ssl(): bool {
		return umemo( __METHOD__ )
			?? umemo( __METHOD__, \is_ssl() );
	}

	/**
	 * Returns whether the current admin screen is the Better SEO settings page.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $secure Whether to use the secure page hook check. Default true.
	 * @return bool True if on the Better SEO settings page.
	 */
	public static function is_seo_settings_page( bool $secure = true ): bool {

		if ( ! \is_admin() ) {
			return false;
		}

		if ( ! $secure ) {
			return self::is_menu_page( '', \BETTER_SEO_SITE_OPTIONS_SLUG );
		}

		return Query\Cache::memo()
			?? Query\Cache::memo( self::is_menu_page( Admin\Menu::get_page_hook_name() ) );
	}

	/**
	 * Returns whether the current admin page matches the given page hook or slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $pagehook Optional page hook to match. Default empty.
	 * @param string $pageslug Optional page slug to match via $_GET['page']. Default empty.
	 * @return bool True if the current admin page matches.
	 */
	public static function is_menu_page( string $pagehook = '', string $pageslug = '' ): bool {
		global $page_hook;

		if ( isset( $page_hook ) ) {
			return $page_hook === $pagehook;
		} elseif ( \is_admin() && $pageslug ) {
			return ( $_GET['page'] ?? '' ) === $pageslug;
		}

		return false;
	}

	/**
	 * Returns the current page number for multipage posts, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return int The current page number (minimum 1).
	 */
	public static function page(): int {

		if ( null !== $memo = Query\Cache::memo() ) {
			return $memo;
		}

		if ( self::is_multipage() ) {
			$page = ( (int) \get_query_var( 'page' ) ) ?: 1;
			$max  = self::numpages();

			if ( $page > $max ) {
				$page = self::is_static_front_page() ? $max : 1;
			}
		} else {
			$page = 1;
		}

		return Query\Cache::memo( $page );
	}

	/**
	 * Returns the current paged number for archive pagination, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return int The current paged number (minimum 1).
	 */
	public static function paged(): int {

		if ( null !== $memo = Query\Cache::memo() ) {
			return $memo;
		}

		if ( self::is_multipage() ) {
			$paged = ( (int) \get_query_var( 'paged' ) ) ?: 1;
			$max   = self::numpages();

			if ( $paged > $max ) {
				// On overflow, WP returns the last page.
				$paged = $max;
			}
		} else {
			$paged = 1;
		}

		return Query\Cache::memo( $paged );
	}

	/**
	 * Returns the total number of pages for the current query, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return int The total number of pages.
	 */
	public static function numpages(): int {

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition -- Intentional memo pattern.
		if ( null !== $memo = Query\Cache::memo() ) {
			return $memo;
		}

		if ( \is_admin() ) {
			return Query\Cache::memo( 1 );
		}

		global $wp_query;

		if ( self::is_singular() && ! self::is_singular_archive() ) {
			$post = \get_post( self::get_the_real_id() );
		}

		if ( ( $post ?? null ) instanceof \WP_Post ) {
			$content = Data\Post::get_content( $post );

			if ( str_contains( $content, '<!--nextpage-->' ) ) {
				$content = str_replace( "\n<!--nextpage-->", '<!--nextpage-->', $content );

				// Ignore nextpage at the beginning of the content.
				if ( str_starts_with( $content, '<!--nextpage-->' ) ) {
					$content = substr( $content, 15 );
				}

				$pages = explode( '<!--nextpage-->', $content );
			} else {
				$pages = [ $content ];
			}

			$pages    = \apply_filters( 'content_pagination', $pages, $post );
			$numpages = \count( $pages );
		} elseif ( isset( $wp_query->max_num_pages ) ) {
			$numpages = (int) $wp_query->max_num_pages;
		} else {
			$numpages = 0;
		}

		return Query\Cache::memo( $numpages );
	}

	/**
	 * Returns whether the current query has multiple pages.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current query has more than one page.
	 */
	public static function is_multipage(): bool {
		return self::numpages() > 1;
	}

	/**
	 * Returns whether the current request has comment pagination, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current request has comment pagination.
	 */
	public static function is_comment_paged(): bool {

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition -- Intentional memo pattern.
		if ( null !== $memo = Query\Cache::memo() ) {
			return $memo;
		}

		$is_cpaged = (int) \get_query_var( 'cpage', 0 ) > 0;

		if ( $is_cpaged && \did_action( 'parse_comment_query' ) ) {
			if ( ! self::is_singular() ) {
				return Query\Cache::memo( false );
			}

			$is_cpaged = (int) ( $GLOBALS['wp_query']->query['cpage'] ?? 0 ) > 0;
		}

		return Query\Cache::memo( $is_cpaged );
	}

	/**
	 * Returns or sets whether the current request is a sitemap, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $set Whether to mark the current request as a sitemap. Default false.
	 * @return bool True if the current request is a sitemap.
	 */
	public static function is_sitemap( bool $set = false ): bool {
		return umemo( __METHOD__, $set ?: null ) ?? false;
	}

	/**
	 * Returns the author ID for the given post, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID. Default 0 (current post).
	 * @return int The post author user ID, or 0 if not applicable.
	 */
	public static function get_post_author_id( int $post_id = 0 ): int {

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition -- Intentional memo pattern.
		if ( null !== $memo = Query\Cache::memo( null, $post_id ) ) {
			return $memo;
		}

		if ( $post_id || Query::is_singular() ) {
			$post = \get_post( $post_id ?: Query::get_the_real_id() );

			$author_id = isset( $post->post_author ) && \post_type_supports( $post->post_type, 'author' )
				? $post->post_author
				: 0;
		}

		return Query\Cache::memo( $author_id ?? 0, $post_id );
	}

	/**
	 * Returns the current logged-in user ID, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return int The current user ID, or 0 if not logged in.
	 */
	public static function get_current_user_id(): int {

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition -- Intentional memo pattern.
		if ( null !== $memo = Query\Cache::memo() ) {
			return $memo;
		}

		$user = \wp_get_current_user();

		return Query\Cache::memo( $user->exists() ? (int) $user->ID : 0 );
	}

	/**
	 * Returns whether the current admin screen is the block editor.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current admin screen is the block editor.
	 */
	public static function is_block_editor(): bool {
		return $GLOBALS['current_screen']->is_block_editor ?? false;
	}
}