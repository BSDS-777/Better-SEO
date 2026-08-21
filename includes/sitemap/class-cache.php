<?php
/**
 * Better SEO - Sitemap Cache
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

use Better_SEO\Data;

/**
 * Class Better_SEO\Sitemap\Cache
 *
 * Provides sitemap transient cache management for Better SEO,
 * including cache key generation, storage, retrieval, and clearing.
 *
 * @since 1.0.0
 */
class Cache {

	/**
	 * Builds a locale- and blog-aware sitemap cache key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The base cache key.
	 * @return string The fully qualified cache key including blog ID and locale.
	 */
	public static function build_sitemap_cache_key( string $key ): string {
		return "{$key}_{$GLOBALS['blog_id']}_" . \get_locale();
	}

	/**
	 * Clears all registered sitemap transient caches.
	 *
	 * Fires the better_seo_cleared_sitemap_transients action after clearing.
	 * Also fires the deprecated better_seo_delete_cache_sitemap action for
	 * backward compatibility.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function clear_sitemap_caches(): void {

		foreach ( Registry::get_sitemap_endpoint_list() as $id => $data ) {
			$transient = self::get_sitemap_cache_key( $id );

			if ( $transient ) {
				\delete_transient( $transient );
			}
		}

		/**
		 * Fires after all Better SEO sitemap transient caches are cleared.
		 *
		 * @since 1.0.0
		 */
		\do_action( 'better_seo_cleared_sitemap_transients' );

		\do_action_deprecated(
			'better_seo_delete_cache_sitemap',
			[
				'sitemap',
				0,
				[ 'type' => 'sitemap' ],
				[ true ],
			],
			'1.0.0 of Better SEO',
			'better_seo_cleared_sitemap_transients',
		);
	}

	/**
	 * Returns whether the sitemap transient cache is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if sitemap caching is enabled, false otherwise.
	 */
	public static function is_sitemap_cache_enabled(): bool {
		return (bool) Data\Plugin::get_option( 'cache_sitemap' );
	}

	/**
	 * Returns the sitemap transient key prefix.
	 *
	 * @since 1.0.0
	 *
	 * @return string The transient prefix string.
	 */
	public static function get_transient_prefix(): string {
		return 'better_seo_sitemap_';
	}

	/**
	 * Returns the full transient cache key for the given sitemap endpoint ID.
	 *
	 * Returns false if the endpoint is not registered or has no cache ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sitemap_id The sitemap endpoint ID.
	 * @return string|false The full transient cache key, or false if not applicable.
	 */
	public static function get_sitemap_cache_key( string $sitemap_id ): string|false {

		$ep_list = Registry::get_sitemap_endpoint_list();

		if ( empty( $ep_list[ $sitemap_id ] ) ) {
			return false;
		}

		$cache_key = $ep_list[ $sitemap_id ]['cache_id'] ?? $sitemap_id;

		return self::build_sitemap_cache_key( self::get_transient_prefix() . $cache_key );
	}

	/**
	 * Stores sitemap content in the transient cache.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed  $content    The sitemap content to cache.
	 * @param string $sitemap_id The sitemap endpoint ID. Default ''.
	 * @param int    $expiration The cache expiration in seconds. Default WEEK_IN_SECONDS.
	 * @return bool True if the transient was set successfully, false otherwise.
	 */
	public static function cache_sitemap_content( mixed $content, string $sitemap_id = '', int $expiration = \WEEK_IN_SECONDS ): bool {

		$transient_key = self::get_sitemap_cache_key( $sitemap_id );

		if ( ! $transient_key ) {
			return false;
		}

		return \set_transient( $transient_key, $content, $expiration );
	}

	/**
	 * Returns cached sitemap content from the transient cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sitemap_id The sitemap endpoint ID. Default ''.
	 * @return mixed The cached sitemap content, or false if not cached or not applicable.
	 */
	public static function get_cached_sitemap_content( string $sitemap_id = '' ): mixed {

		$transient_key = self::get_sitemap_cache_key( $sitemap_id );

		if ( ! $transient_key ) {
			return false;
		}

		return \get_transient( $transient_key );
	}
}