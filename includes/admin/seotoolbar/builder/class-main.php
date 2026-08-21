<?php
/**
 * Better SEO - Admin SEO Toolbar Builder Main
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Admin\SEOToolbar\Builder
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

namespace Better_SEO\Admin\SEOToolbar\Builder;

\defined( 'BETTER_SEO_PRESENT' ) or exit;

/**
 * Class Better_SEO\Admin\SEOToolbar\Builder\Main
 *
 * Abstract base class for Better SEO Toolbar builders.
 * Provides shared test registration, caching, and query management
 * for post/page and term toolbar builder implementations.
 *
 * @since 1.0.0
 */
abstract class Main {

	/**
	 * Registered test names for this builder.
	 *
	 * @since 1.0.0
	 * @var   array<int, string>
	 */
	protected static array $tests = [];

	/**
	 * Shared static cache for builder data across requests.
	 *
	 * @since 1.0.0
	 * @var   array<string, mixed>
	 */
	private static array $cache = [];

	/**
	 * The current query arguments being processed.
	 *
	 * @since 1.0.0
	 * @var   array<string, mixed>
	 */
	protected static array $query = [];

	/**
	 * Per-instance query cache for the current bar generation.
	 *
	 * @since 1.0.0
	 * @var   array<string, mixed>
	 */
	private array $query_cache = [];

	/**
	 * Returns the singleton instance, creating it on first call.
	 *
	 * @since 1.0.0
	 *
	 * @return static The singleton instance.
	 */
	public static function get_instance(): static {
		static $instances = [];

		$class = static::class;

		if ( ! isset( $instances[ $class ] ) ) {
			$instances[ $class ] = new static();
		}

		return $instances[ $class ];
	}

	/**
	 * Runs all registered tests and yields the results.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $query The query arguments.
	 * @return \Generator<string, array<string, mixed>>
	 */
	public function run_all_tests( array $query ): \Generator {

		self::$query = &$query;

		foreach ( static::$tests as $test ) {
			if ( method_exists( $this, $test ) ) {
				yield $test => $this->$test();
			}
		}
	}

	/**
	 * Sets a value in the shared static cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   The cache key.
	 * @param mixed  $value The value to cache.
	 * @return mixed The cached value.
	 */
	protected static function set_cache( string $key, mixed $value ): mixed {
		return self::$cache[ $key ] = $value;
	}

	/**
	 * Retrieves a value from the shared static cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The cache key.
	 * @return mixed The cached value, or null if not found.
	 */
	protected static function get_cache( string $key ): mixed {
		return self::$cache[ $key ] ?? null;
	}

	/**
	 * Clears the per-instance query cache.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function clear_query_cache(): void {
		$this->query_cache = [];
	}

	/**
	 * Sets a value in the per-instance query cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   The cache key.
	 * @param mixed  $value The value to cache.
	 * @return mixed The cached value.
	 */
	protected function set_query_cache( string $key, mixed $value ): mixed {
		return $this->query_cache[ $key ] = $value;
	}

	/**
	 * Retrieves a value from the per-instance query cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The cache key.
	 * @return mixed The cached value, or null if not found.
	 */
	protected function get_query_cache( string $key ): mixed {
		return $this->query_cache[ $key ] ?? null;
	}
}
