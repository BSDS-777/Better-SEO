<?php
/**
 * Better SEO - Sitemap Optimized Base
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Sitemap\Optimized
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

namespace Better_SEO\Sitemap\Optimized;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\{
	Data,
	Data\Filter\Escape,
	Meta,
	Sitemap,
};
use Better_SEO\Helper\{
	Format\Time,
	Post_Type,
	Query,
};

/**
 * Class Better_SEO\Sitemap\Optimized\Base
 *
 * Generates the base XML sitemap for Better SEO, including front page,
 * blog page, hierarchical and non-hierarchical post type URLs, additional
 * custom URLs, and optional lastmod timestamps.
 *
 * Supports both on-demand generation and transient-cached prerendering
 * via the sitemap lock system.
 *
 * @since 1.0.0
 */
class Base extends Main {

	/**
	 * Whether the sitemap was regenerated during this request.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public bool $base_is_regenerated = false;

	/**
	 * Whether the sitemap is currently being prerendered via cron.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	public bool $base_is_prerendering = false;

	/**
	 * Prerenders and caches the sitemap for the given endpoint ID.
	 *
	 * Skips if caching is disabled, the cache already exists, or the lock
	 * cannot be acquired. Raises the execution time limit if needed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sitemap_id The sitemap endpoint ID. Default 'base'.
	 * @return void
	 */
	public static function prerender_sitemap( string $sitemap_id = 'base' ): void {

		if ( ! Sitemap\Cache::is_sitemap_cache_enabled() ) {
			return;
		}

		// Already cached — no need to prerender.
		if ( false !== Sitemap\Cache::get_cached_sitemap_content( $sitemap_id ) ) {
			return;
		}

		$ini_max_execution_time = (int) ini_get( 'max_execution_time' );
		if ( 0 !== $ini_max_execution_time && \function_exists( 'set_time_limit' ) ) {
			set_time_limit( max( $ini_max_execution_time, 3 * \MINUTE_IN_SECONDS ) );
		}

		// Bail if the lock cannot be acquired (another process is generating).
		if ( ! Sitemap\Lock::lock_sitemap( $sitemap_id ) ) {
			return;
		}

		$sitemap_base = new self();

		$sitemap_base->prepare_generation();
		$sitemap_base->base_is_prerendering = true;

		Sitemap\Cache::cache_sitemap_content( $sitemap_base->build_sitemap(), $sitemap_id );

		Sitemap\Lock::unlock_sitemap( $sitemap_id );

		$sitemap_base->shutdown_generation();
		$sitemap_base->base_is_regenerated = true;
	}

	/**
	 * Generates the sitemap content, using the transient cache when available.
	 *
	 * If no cached content exists, generates fresh content, caches it,
	 * and marks the sitemap as regenerated.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sitemap_id The sitemap endpoint ID. Default 'base'.
	 * @return string The sitemap XML content.
	 */
	public function generate_sitemap( string $sitemap_id = 'base' ): string {

		$_caching_enabled = Sitemap\Cache::is_sitemap_cache_enabled();

		$sitemap_content = $_caching_enabled
			? Sitemap\Cache::get_cached_sitemap_content( $sitemap_id )
			: false;

		if ( false === $sitemap_content ) {

			$this->prepare_generation();

			if ( $_caching_enabled ) {
				Sitemap\Lock::lock_sitemap( $sitemap_id );
			}

			$sitemap_content = $this->build_sitemap();

			$this->shutdown_generation();
			$this->base_is_regenerated = true;

			if ( $_caching_enabled ) {
				Sitemap\Cache::cache_sitemap_content( $sitemap_content, $sitemap_id );
				Sitemap\Lock::unlock_sitemap( $sitemap_id );
			}
		}

		return $sitemap_content;
	}

	/**
	 * Builds and returns the full base sitemap XML content string.
	 *
	 * Fires the better_seo_build_sitemap_base action before building.
	 * Applies filters for timestamp, supported post types, query args,
	 * item list, additional URLs, and sitemap extension.
	 *
	 * @since 1.0.0
	 *
	 * @return string The generated sitemap XML content.
	 */
	public function build_sitemap(): string {

		/**
		 * Fires before the Better SEO base sitemap is built.
		 *
		 * @since 1.0.0
		 * @param static $sitemap_base The current Base instance.
		 */
		\do_action( 'better_seo_build_sitemap_base', $this );

		$content         = '';
		$this->url_count = 0;

		$show_modified = (bool) Data\Plugin::get_option( 'sitemaps_modified' );

		/**
		 * Filters whether to include a generation timestamp comment in the sitemap.
		 *
		 * @since 1.0.0
		 * @param bool $timestamp Whether to output the timestamp comment. Default true.
		 */
		$timestamp = (bool) \apply_filters( 'better_seo_sitemap_timestamp', true );

		if ( $timestamp ) {
			$content .= \sprintf(
				"<!-- %s -->\n",
				\sprintf(
					$this->base_is_prerendering
						/* translators: %s = timestamp */
						? \esc_html__( 'Sitemap is prerendered on %s', 'better-seo' )
						/* translators: %s = timestamp */
						: \esc_html__( 'Sitemap is generated on %s', 'better-seo' ),
					\current_time( 'Y-m-d H:i:s \G\M\T' ),
				),
			);
		}

		foreach ( $this->generate_front_and_blog_url_items(
			[ 'show_modified' => $show_modified ],
		) as $_values ) {
			$content .= $this->build_url_item( $_values );
		}

		$post_types = array_diff( Post_Type::get_all_supported(), [ 'attachment' ] );

		/**
		 * Filters the list of post types included in the Better SEO base sitemap.
		 *
		 * @since 1.0.0
		 * @param array<int, string> $post_types The supported post type slugs.
		 */
		$post_types = (array) \apply_filters( 'better_seo_sitemap_supported_post_types', $post_types );

		$non_hierarchical_post_types = [];
		$hierarchical_post_types     = [];

		foreach ( $post_types as $_post_type ) {
			if ( \is_post_type_hierarchical( $_post_type ) ) {
				$hierarchical_post_types[] = $_post_type;
			} else {
				$non_hierarchical_post_types[] = $_post_type;
			}
		}

		$wp_query = new \WP_Query();
		$wp_query->init();
		$hierarchical_post_ids     = [];
		$non_hierarchical_post_ids = [];

		if ( $hierarchical_post_types ) {
			$_exclude_ids = array_filter( [
				(int) \get_option( 'page_on_front' ),
				(int) \get_option( 'page_for_posts' ),
			] );

			$_hierarchical_posts_limit = Sitemap\Utils::get_sitemap_post_limit( 'hierarchical' );

			/**
			 * Filters the WP_Query args for hierarchical post type sitemap queries.
			 *
			 * @since 1.0.0
			 * @param array<string, mixed> $args The WP_Query arguments.
			 */
			$_args = (array) \apply_filters(
				'better_seo_sitemap_hpt_query_args',
				[
					'posts_per_page' => $_hierarchical_posts_limit + \count( $_exclude_ids ),
					'post_type'      => $hierarchical_post_types,
					'orderby'        => 'lastmod',
					'order'          => 'DESC',
					'post_status'    => 'publish',
					'has_password'   => false,
					'fields'         => 'ids',
					'cache_results'  => false,
					'no_found_rows'  => true,
				],
			);

			if ( $_args['post_type'] ) {
				$wp_query->query = $wp_query->query_vars = $_args;

				$hierarchical_post_ids = array_diff( $wp_query->get_posts(), $_exclude_ids );

				if ( \count( $hierarchical_post_ids ) > $_hierarchical_posts_limit ) {
					array_splice( $hierarchical_post_ids, $_hierarchical_posts_limit );
				}
			}
		}

		if ( $non_hierarchical_post_types ) {
			/**
			 * Filters the WP_Query args for non-hierarchical post type sitemap queries.
			 *
			 * @since 1.0.0
			 * @param array<string, mixed> $args The WP_Query arguments.
			 */
			$_args = (array) \apply_filters(
				'better_seo_sitemap_nhpt_query_args',
				[
					'posts_per_page' => Sitemap\Utils::get_sitemap_post_limit( 'nonhierarchical' ),
					'post_type'      => $non_hierarchical_post_types,
					'orderby'        => 'lastmod',
					'order'          => 'DESC',
					'post_status'    => 'publish',
					'has_password'   => false,
					'fields'         => 'ids',
					'cache_results'  => false,
					'no_found_rows'  => true,
				],
			);

			if ( $_args['post_type'] ) {
				$wp_query->query = $wp_query->query_vars = $_args;

				$non_hierarchical_post_ids = $wp_query->get_posts();
			}
		}

		// Release WP_Query memory before building URL items.
		$wp_query = null;

		/**
		 * Filters the merged list of post IDs included in the Better SEO base sitemap.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, int> $items                     The merged post ID list.
		 * @param array<int, int> $hierarchical_post_ids     The hierarchical post IDs.
		 * @param array<int, int> $non_hierarchical_post_ids The non-hierarchical post IDs.
		 */
		$_items      = (array) \apply_filters(
			'better_seo_sitemap_items',
			array_merge( $hierarchical_post_ids, $non_hierarchical_post_ids ),
			$hierarchical_post_ids,
			$non_hierarchical_post_ids,
		);
		$total_items = \count( $_items );

		// Hard cap at 44,498 items to stay within sitemap spec limits.
		if ( $total_items > 44498 ) {
			array_splice( $_items, 44498 );
		}

		foreach ( $this->generate_url_item_values(
			$_items,
			[
				'show_modified' => $show_modified,
				'total_items'   => $total_items,
			],
		) as $_values ) {
			$content .= static::build_url_item( $_values );
		}

		// Only run additional URL generation if the filter has callbacks registered.
		if ( \has_filter( 'better_seo_sitemap_additional_urls' ) ) {
			foreach ( $this->generate_additional_base_urls(
				[
					'show_modified' => $show_modified,
					'count'         => $this->url_count,
				]
			) as $_values ) {
				$content .= static::build_url_item( $_values );
			}
		}

		/**
		 * Filters additional raw XML content to append to the Better SEO base sitemap.
		 *
		 * @since 1.0.0
		 *
		 * @param string               $extend The raw XML string to append.
		 * @param array<string, mixed> $args   The sitemap generation args (show_modified, count).
		 */
		$extend = (string) \apply_filters(
			'better_seo_sitemap_extend',
			'',
			[
				'show_modified' => $show_modified,
				'count'         => $this->url_count,
			],
		);

		if ( $extend ) {
			$content .= "\t{$extend}\n";
		}

		return $content;
	}

	/**
	 * Generates URL item values for the front page and blog page.
	 *
	 * When a static front page is set, yields URL items for both the front page
	 * and the posts page (with lastmod updated to the most recent post date).
	 * When using the blog as the front page, yields a single URL item for the
	 * home URL with the most recent post date as lastmod.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args The generation args (show_modified).
	 * @return \Generator Yields URL item value arrays.
	 */
	protected function generate_front_and_blog_url_items( array $args ): \Generator {

		if ( Query\Utils::has_page_on_front() ) {
			$front_page_id = (int) \get_option( 'page_on_front' );
			$posts_page_id = (int) \get_option( 'page_for_posts' );

			if (
				   $front_page_id // Might not be assigned.
				&& ! Data\Post::is_protected( $front_page_id )
				&& ! Data\Post::is_draft( $front_page_id )
				&& Sitemap\Utils::is_post_included_in_sitemap( $front_page_id )
			) {
				yield from $this->generate_url_item_values(
					[ $front_page_id ],
					$args,
				);
			}

			if (
				   $posts_page_id // Might not be assigned.
				&& ! Data\Post::is_protected( $posts_page_id )
				&& ! Data\Post::is_draft( $posts_page_id )
				&& Sitemap\Utils::is_post_included_in_sitemap( $posts_page_id )
			) {
				foreach ( $this->generate_url_item_values(
					[ $posts_page_id ],
					$args,
				) as $_values ) {
					if ( $_values['loc'] && $args['show_modified'] ) {
						$latests_posts = \wp_get_recent_posts(
							[
								'numberposts'  => 1,
								'post_type'    => 'post',
								'post_status'  => 'publish',
								'has_password' => false,
								'orderby'      => 'post_date',
								'order'        => 'DESC',
								'offset'       => 0,
							],
							\OBJECT,
						);
						$_publish_post = $latests_posts[0]->post_date_gmt ?? '0000-00-00 00:00:00';
						$_lastmod_blog = $_values['lastmod']; // Inferred from generate_url_item_values().

						/**
						 * Filters the lastmod date for the blog posts page in the sitemap.
						 *
						 * @since 1.0.0
						 * @param string $lastmod The resolved lastmod date string.
						 */
						$_values['lastmod'] = (string) \apply_filters(
							'better_seo_sitemap_blog_lastmod',
							strtotime( $_publish_post ) > strtotime( $_lastmod_blog )
								? $_publish_post
								: $_lastmod_blog,
						);
					}

					yield $_values;
				}
			}
		} else {
			// Blog is the front page — yield a single home URL entry.
			if ( Sitemap\Utils::is_post_included_in_sitemap( 0 ) ) {
				$_values        = [];
				$_values['loc'] = Meta\URI::get_bare_front_page_url();

				if ( $args['show_modified'] ) {
					$latests_posts = \wp_get_recent_posts(
						[
							'numberposts'  => 1,
							'post_type'    => 'post',
							'post_status'  => 'publish',
							'has_password' => false,
							'orderby'      => 'post_date',
							'order'        => 'DESC',
							'offset'       => 0,
						],
						\OBJECT,
					);

					/**
					 * Filters the lastmod date for the blog front page in the sitemap.
					 *
					 * @since 1.0.0
					 * @param string $lastmod The most recent post date (GMT).
					 */
					$_values['lastmod'] = (string) \apply_filters(
						'better_seo_sitemap_blog_lastmod',
						$latests_posts[0]->post_date_gmt ?? '0000-00-00 00:00:00',
					);
				}

				++$this->url_count;
				yield $_values;
			}
		}
	}

	/**
	 * Generates URL item value arrays for the given post IDs.
	 *
	 * Skips posts excluded from the sitemap. Cleans the post cache after
	 * each post when WP_CACHE is not active to prevent memory buildup.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, int>      $post_ids The post IDs to generate URL items for.
	 * @param array<string, mixed> $args     The generation args (show_modified, total_items).
	 * @return \Generator Yields URL item value arrays.
	 */
	protected function generate_url_item_values( array $post_ids, array $args ): \Generator {

		foreach ( $post_ids as $post_id ) {
			$post = \get_post( $post_id );

			if ( Sitemap\Utils::is_post_included_in_sitemap( $post_id ) ) {
				$_values = [
					'loc' => Meta\URI::get_bare_singular_url( $post_id ),
				];

				if ( $args['show_modified'] ) {
					$_values['lastmod'] = $post->post_modified_gmt ?? '0000-00-00 00:00:00';
				}

				++$this->url_count;
				yield $_values;
			}

			// Clean post cache to prevent memory buildup on large sitemaps.
			if ( ! \WP_CACHE ) {
				\clean_post_cache( $post );
			}
		}
	}

	/**
	 * Builds a single <url> XML entry string from the given URL item values.
	 *
	 * Returns empty string if no 'loc' value is present.
	 * Converts lastmod to the preferred timestamp format if set and valid.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args The URL item values (loc, lastmod).
	 * @return string The XML <url> entry string, or empty string if no loc.
	 */
	protected static function build_url_item( array $args ): string {

		if ( empty( $args['loc'] ) ) {
			return '';
		}

		$xml = [
			'loc' => Escape::xml_uri( $args['loc'] ),
		];

		if ( isset( $args['lastmod'] ) && '0000-00-00 00:00:00' !== $args['lastmod'] ) {
			// XML safe — Time::convert_to_preferred_format() returns a sanitized string.
			$xml['lastmod'] = Time::convert_to_preferred_format( $args['lastmod'] );
		}

		return static::create_xml_entry( [ 'url' => $xml ], 1 );
	}

	/**
	 * Generates URL item value arrays for additional custom URLs.
	 *
	 * Applies the better_seo_sitemap_additional_urls filter to retrieve
	 * custom URL entries. Skips entries with invalid URLs.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args The generation args (show_modified, count).
	 * @return \Generator Yields URL item value arrays.
	 */
	protected function generate_additional_base_urls( array $args ): \Generator {

		/**
		 * Filters additional custom URLs to include in the Better SEO base sitemap.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string|int, string|array<string, mixed>> $custom_urls Map of URL => values or indexed URL strings.
		 * @param array<string, mixed>                           $args        The generation args.
		 */
		$custom_urls = (array) \apply_filters( 'better_seo_sitemap_additional_urls', [], $args );

		foreach ( $custom_urls as $url => $values ) {
			if ( ! \is_array( $values ) ) {
				// Indexed entry — the value itself is the URL.
				$url = $values;
			}

			// Skip entries with invalid or unsupported URL schemes.
			if ( ! \sanitize_url( $url, [ 'https', 'http' ] ) ) {
				continue;
			}

			$_values        = [];
			$_values['loc'] = $url;

			if ( $args['show_modified'] ) {
				$_values['lastmod'] = ! empty( $values['lastmod'] ) ? $values['lastmod'] : '0000-00-00 00:00:00';
			}

			++$this->url_count;
			yield $_values;
		}
	}
}