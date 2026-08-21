<?php
/**
 * Better SEO - Sitemap WP Posts
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

use Better_SEO\{
	Data,
	Sitemap,
	Helper\Format\Time,
};

/**
 * Class Better_SEO\Sitemap\WP\Posts
 *
 * Extends the WordPress core WP_Sitemaps_Posts provider to integrate
 * Better SEO's post inclusion checks, lastmod timestamps, and blog
 * front page handling into the WordPress core sitemap output.
 *
 * @since 1.0.0
 */
class Posts extends \WP_Sitemaps_Posts {

	/**
	 * Returns the URL list for the given post type and page number.
	 *
	 * Overrides WP_Sitemaps_Posts::get_url_list() to:
	 * - Apply Better SEO's post inclusion checks (noindex, redirects, exclusions)
	 * - Add lastmod timestamps when the sitemaps_modified option is enabled
	 * - Include the blog front page URL when applicable
	 *
	 * @since 1.0.0
	 *
	 * @param int    $page_num       The page number of results to retrieve.
	 * @param string $object_subtype The post type slug. Default ''.
	 * @return array<int, array<string, mixed>> The URL list for the sitemap.
	 */
	public function get_url_list( int $page_num, string $object_subtype = '' ): array {

		$post_type = $object_subtype;

		$supported_types = $this->get_object_subtypes();

		if ( ! isset( $supported_types[ $post_type ] ) ) {
			return [];
		}

		$url_list = \apply_filters(
			'wp_sitemaps_posts_pre_url_list',
			null,
			$post_type,
			$page_num,
		);

		if ( null !== $url_list ) {
			return $url_list;
		}

		$args          = $this->get_posts_query_args( $post_type );
		$args['paged'] = $page_num;

		$query = new \WP_Query( $args );

		$url_list = [];

		$show_modified = (bool) Data\Plugin::get_option( 'sitemaps_modified' );

		// Add the blog front page URL when using a static page as the front page
		// and the 'page' post type is being processed on page 1.
		if ( 'page' === $post_type && 1 === $page_num && 'posts' === \get_option( 'show_on_front' ) ) {
			if ( Sitemap\Utils::is_post_included_in_sitemap( 0 ) ) {
				$sitemap_entry = [
					'loc' => \home_url( '/' ),
				];

				if ( $show_modified ) {
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
					$lastmod = (string) \apply_filters(
						'better_seo_sitemap_blog_lastmod',
						$latests_posts[0]->post_date_gmt ?? '0000-00-00 00:00:00',
					);

					if ( '0000-00-00 00:00:00' !== $lastmod ) {
						// XML safe — Time::convert_to_preferred_format() returns a sanitized string.
						$sitemap_entry['lastmod'] = Time::convert_to_preferred_format( $lastmod );
					}
				}

				$sitemap_entry = \apply_filters( 'wp_sitemaps_posts_show_on_front_entry', $sitemap_entry );
				$url_list[]    = $sitemap_entry;
			}
		}

		foreach ( $query->posts as $post ) {
			if ( ! Sitemap\Utils::is_post_included_in_sitemap( $post->ID ) ) {
				continue;
			}

			$sitemap_entry = [
				'loc' => \get_permalink( $post ),
			];

			if ( $show_modified ) {
				$lastmod = $post->post_modified_gmt ?? '0000-00-00 00:00:00';

				if ( '0000-00-00 00:00:00' !== $lastmod ) {
					// XML safe — Time::convert_to_preferred_format() returns a sanitized string.
					$sitemap_entry['lastmod'] = Time::convert_to_preferred_format( $lastmod );
				}
			}

			$sitemap_entry = \apply_filters( 'wp_sitemaps_posts_entry', $sitemap_entry, $post, $post_type );
			$url_list[]    = $sitemap_entry;
		}

		return $url_list;
	}
}