<?php
/**
 * Better SEO - Data Plugin
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

use function Better_SEO\is_headless;

use Better_SEO\Traits\Property_Refresher;

/**
 * Class Better_SEO\Data\Plugin
 *
 * Provides data interface methods for Better SEO plugin options and site cache.
 *
 * @NOTE: All static:: calls within this class are intentional due to the Property_Refresher trait.
 *
 * @since 1.0.0
 * @access protected
 *         Use better_seo()->data()->plugin() instead.
 */
class Plugin {
	use Property_Refresher;

	/**
	 * Memoized plugin options array.
	 *
	 * @since 1.0.0
	 * @var   array<string, mixed>|null
	 */
	private static ?array $options_memo = null;

	/**
	 * Memoized site cache array.
	 *
	 * @since 1.0.0
	 * @var   array<string, mixed>|null
	 */
	private static ?array $site_cache_memo = null;

	/**
	 * Flushes all memoized plugin data caches.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		static::refresh_static_properties();
		// PTA is stored in the default plugin options — flush it too.
		Plugin\PTA::refresh_static_properties();
	}

	/**
	 * Returns a specific plugin option value by key, or null if not found.
	 *
	 * Additional parameters traverse nested array values.
	 * When called with no arguments, returns all options.
	 *
	 * @since 1.0.0
	 * @uses  \BETTER_SEO_SITE_OPTIONS
	 *
	 * @param mixed ...$key Option key(s). Additional keys traverse nested arrays.
	 * @return mixed The option value, or null if not found.
	 */
	public static function get_option( mixed ...$key ): mixed {

		$option = self::$options_memo ?? self::get_options();

		foreach ( $key as $k ) {
			$option = $option[ $k ] ?? null;
		}

		return $option ?? Plugin\Deprecated::get_deprecated_option( ...$key );
	}

	/**
	 * Returns the full plugin options array without merging with defaults.
	 *
	 * Registers an automated refresh hook and applies the better_seo_get_options filter.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The plugin options array.
	 */
	public static function get_options(): array {

		if ( isset( self::$options_memo ) ) {
			return self::$options_memo;
		}

		static::register_automated_refresh( 'options_memo' );

		$is_headless = is_headless( 'settings' );

		/**
		 * Filters the Better SEO plugin options.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $options          The plugin options array.
		 * @param string               $option_key       The WordPress option key.
		 * @param bool                 $is_headless      Whether the plugin is running headless.
		 */
		return self::$options_memo = \apply_filters(
			'better_seo_get_options',
			$is_headless
				? Plugin\Setup::get_default_options()
				: (
					// May be empty during setup — return defaults as fallback.
					\get_option( \BETTER_SEO_SITE_OPTIONS ) ?: Plugin\Setup::get_default_options()
				),
			\BETTER_SEO_SITE_OPTIONS,
			$is_headless,
		);
	}

	/**
	 * Updates a plugin option or set of options in the database.
	 *
	 * Merges with the latest database revision before saving to prevent data loss.
	 *
	 * @since 1.0.0
	 *
	 * @param string|array<string, mixed> $option The option key or array of key-value pairs.
	 * @param mixed                       $value  The option value (used when $option is a string).
	 * @return bool True if the option was updated, false otherwise.
	 */
	public static function update_option( string|array $option, mixed $value = '' ): bool {

		// Get the latest known revision from the database.
		$options = array_merge(
			\get_option( \BETTER_SEO_SITE_OPTIONS ) ?: Plugin\Setup::get_default_options(),
			\is_array( $option ) ? $option : [ $option => $value ],
		);

		// Selectively reset the options memo.
		static::$options_memo = null;
		// Reset PTA entirely — it relies on plugin options.
		Plugin\PTA::refresh_static_properties();

		return \update_option( \BETTER_SEO_SITE_OPTIONS, $options, true );
	}

	/**
	 * Returns a specific site cache value by key, or null if not found.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The cache key.
	 * @return mixed The cached value, or null if not found.
	 */
	public static function get_site_cache( string $key ): mixed {
		return (
			static::$site_cache_memo ?? static::get_site_caches()
		)[ $key ] ?? null;
	}

	/**
	 * Returns the full site cache array.
	 *
	 * Registers an automated refresh hook on first call.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The site cache array.
	 */
	public static function get_site_caches(): array {

		if ( isset( static::$site_cache_memo ) ) {
			return static::$site_cache_memo;
		}

		static::register_automated_refresh( 'site_cache_memo' );

		return static::$site_cache_memo =
			   \get_option( \BETTER_SEO_SITE_CACHE )
			?: Plugin\Setup::get_default_site_caches();
	}

	/**
	 * Updates a site cache entry or set of entries in the database.
	 *
	 * @since 1.0.0
	 *
	 * @param string|array<string, mixed> $cache The cache key or array of key-value pairs.
	 * @param mixed                       $value The cache value (used when $cache is a string).
	 * @return bool True if the cache was updated, false otherwise.
	 */
	public static function update_site_cache( string|array $cache, mixed $value = '' ): bool {

		// Get the latest known revision from the database.
		$site_cache = array_merge(
			\get_option( \BETTER_SEO_SITE_CACHE ) ?: Plugin\Setup::get_default_site_caches(),
			\is_array( $cache ) ? $cache : [ $cache => $value ],
		);

		static::$site_cache_memo = null;

		return \update_option( \BETTER_SEO_SITE_CACHE, $site_cache, true );
	}

	/**
	 * Deletes one or more site cache entries from the database.
	 *
	 * @since 1.0.0
	 *
	 * @param string|array<int, string> $cache The cache key or array of keys to delete.
	 * @return bool True if the cache was updated, false otherwise.
	 */
	public static function delete_site_cache( string|array $cache ): bool {

		$site_cache = \get_option( \BETTER_SEO_SITE_CACHE ) ?: Plugin\Setup::get_default_site_caches();

		foreach ( (array) $cache as $key ) {
			unset( $site_cache[ $key ] );
		}

		static::$site_cache_memo = null;

		return \update_option( \BETTER_SEO_SITE_CACHE, $site_cache, true );
	}
}