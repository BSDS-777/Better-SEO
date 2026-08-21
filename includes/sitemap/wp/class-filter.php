<?php
/**
 * Better SEO - Sitemap WP Filter
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
	Helper\Query,
	Sitemap,
};

/**
 * Class Better_SEO\Sitemap\WP\Filter
 *
 * Provides WordPress core sitemap filter callbacks for Better SEO.
 * Handles sitemap query detection, provider replacement with Better SEO
 * custom providers, and URL limit enforcement.
 *
 * @since 1.0.0
 */
final class Filter {

	/**
	 * Detects whether the current request is a WordPress core sitemap request
	 * and marks it as such via Query::is_sitemap().
	 *
	 * Hooks into wp_sitemaps_init to trick the query state before the sitemap
	 * provider runs, ensuring Better SEO's query detection works correctly.
	 *
	 * @hook wp_sitemaps_init 10
	 * @since 1.0.0
	 *
	 * @param mixed $args The sitemap init args (passed through unchanged).
	 * @return mixed The unmodified $args value.
	 */
	public static function trick_filter_doing_sitemap( mixed $args ): mixed {
		global $wp_query;

		if ( isset( $wp_query->query_vars['sitemap'] ) ) {
			if ( \wp_sitemaps_get_server()->registry->get_provider( $wp_query->query_vars['sitemap'] ) ) {
				Query::is_sitemap( true );
			}
		}

		return $args;
	}

	/**
	 * Replaces WordPress core sitemap providers with Better SEO custom providers.
	 *
	 * Replaces the 'posts' provider with Better_SEO\Sitemap\WP\Posts,
	 * the 'taxonomies' provider with Better_SEO\Sitemap\WP\Taxonomies,
	 * and removes the 'users' provider if author noindex is enabled.
	 *
	 * @hook wp_sitemaps_add_provider 10
	 * @since 1.0.0
	 *
	 * @param \WP_Sitemaps_Provider|null $provider The current sitemap provider instance.
	 * @param string                     $name     The provider name ('posts', 'taxonomies', 'users').
	 * @return \WP_Sitemaps_Provider|null The replacement provider, or null to remove it.
	 */
	public static function filter_add_provider( \WP_Sitemaps_Provider|null $provider, string $name ): \WP_Sitemaps_Provider|null {

		if ( ! $provider instanceof \WP_Sitemaps_Provider ) {
			return $provider;
		}

		return match ( $name ) {
			'posts'      => new Posts(),
			'taxonomies' => new Taxonomies(),
			'users'      => Data\Plugin::get_option( 'author_noindex' ) ? null : $provider,
			default      => $provider,
		};
	}

	/**
	 * Returns the Better SEO sitemap post limit for use as the WordPress core sitemap max URLs.
	 *
	 * @hook wp_sitemaps_max_urls 10
	 * @since 1.0.0
	 *
	 * @return int The configured sitemap post query limit.
	 */
	public static function filter_max_urls(): int {
		return Sitemap\Utils::get_sitemap_post_limit();
	}
}