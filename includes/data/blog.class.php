<?php
/**
 * Better SEO - Data Blog
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

use function Better_SEO\{
	memo,
	umemo,
};

use Better_SEO\Data;

/**
 * Class Better_SEO\Data\Blog
 *
 * Provides data helper methods for blog/site-level information,
 * including blog name, description, URL, language, and multisite state.
 *
 * @since 1.0.0
 * @access protected
 *         Use better_seo()->data()->blog() instead.
 */
class Blog {

	/**
	 * Returns the public blog name (site title), memoized.
	 *
	 * Uses the plugin's custom site title option if set,
	 * otherwise falls back to the filtered WordPress blog name.
	 *
	 * Do not consider this method safe for direct output — escape before printing.
	 *
	 * @since 1.0.0
	 *
	 * @return string The sanitized blog name.
	 */
	public static function get_public_blog_name(): string {
		return umemo( __METHOD__ )
			?? umemo(
				__METHOD__,
				Data\Plugin::get_option( 'site_title' ) ?: self::get_filtered_blog_name(),
			);
	}

	/**
	 * Returns the filtered WordPress blog name (site title).
	 *
	 * Uses get_bloginfo( 'name', 'display' ) to apply WordPress filters.
	 *
	 * @since 1.0.0
	 *
	 * @return string The filtered and trimmed blog name.
	 */
	public static function get_filtered_blog_name(): string {
		/**
		 * Filters the Better SEO blog name.
		 *
		 * @since 1.0.0
		 * @param string $blog_name The blog name from get_bloginfo().
		 */
		return (string) \apply_filters(
			'better_seo_blog_name',
			trim( \get_bloginfo( 'name', 'display' ) ),
		);
	}

	/**
	 * Returns the filtered WordPress blog description (tagline).
	 *
	 * @since 1.0.0
	 *
	 * @return string The trimmed blog description.
	 */
	public static function get_filtered_blog_description(): string {
		return trim( \get_bloginfo( 'description', 'display' ) );
	}

	/**
	 * Returns the front page URL, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return string The home URL.
	 */
	public static function get_front_page_url(): string {
		return umemo( __METHOD__ ) ?? umemo( __METHOD__, \get_home_url() );
	}

	/**
	 * Returns the site language, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return string The site language string from get_bloginfo().
	 */
	public static function get_language(): string {
		return umemo( __METHOD__ ) ?? umemo( __METHOD__, \get_bloginfo( 'language' ) );
	}

	/**
	 * Returns whether the blog is publicly visible, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the blog is public, false otherwise.
	 */
	public static function is_public(): bool {
		return memo() ?? memo( (bool) \get_option( 'blog_public' ) );
	}

	/**
	 * Returns whether the current multisite blog is marked as spam or deleted.
	 *
	 * Always returns false on non-multisite installations.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the site is spam or deleted, false otherwise.
	 */
	public static function is_spam_or_deleted(): bool {

		if ( ! \function_exists( 'get_site' ) || ! \is_multisite() ) {
			return false;
		}

		$site = \get_site();

		if ( $site instanceof \WP_Site && ( '1' === $site->spam || '1' === $site->deleted ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Returns whether WordPress is installed in a subdirectory, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if installed in a subdirectory, false otherwise.
	 */
	public static function is_subdirectory_installation(): bool {
		return memo() ?? memo(
			(bool) \strlen( ltrim(
				parse_url(
					\get_option( 'home' ),
					\PHP_URL_PATH,
				) ?? '',
				' \\/',
			) ),
		);
	}

	/**
	 * Returns a list of all active plugin file paths.
	 *
	 * On multisite, merges site-wide active plugins with network-wide active plugins.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> List of active plugin file paths.
	 */
	public static function get_active_plugins(): array {

		$active_plugins = (array) \get_option( 'active_plugins', [] );

		if ( \is_multisite() ) {
			// active_sitewide_plugins stores plugins in keys (not values like active_plugins).
			// array_keys() resolves the disparity.
			$active_plugins = array_merge(
				$active_plugins,
				array_keys( \get_site_option( 'active_sitewide_plugins', [] ) ),
			);

			// $plugins is already sorted at activate_plugin.
			sort( $active_plugins );
		}

		return $active_plugins;
	}

	/**
	 * Returns a list of active theme slugs (child and parent), memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> Unique list of active theme slugs in lowercase.
	 */
	public static function get_active_themes(): array {
		return memo() ?? memo( array_unique( [
			strtolower( \get_option( 'stylesheet' ) ), // Child theme.
			strtolower( \get_option( 'template' ) ),   // Parent theme.
		] ) );
	}
}