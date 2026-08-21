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
 * for post/page and term SEO Toolbar builder implementations.
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
	 * Static pool of builder instances, keyed by class name.
	 *
	 * @since 1.0.0
	 * @var   array<class-string, self>
	 */
	private static array $instances = [];

	/**
	 * Returns a singleton instance of the builder.
	 *
	 * @since 1.0.0
	 *
	 * @return static The builder instance.
	 */
	public static function get_instance(): static {
		return static::$instances[ static::class ] ??= new static();
	}

	/**
	 * Runs all registered tests and returns results as a generator.
	 *
	 * Primes the shared cache, then the per-instance query cache,
	 * then yields the result of each test method.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $query The query arguments to test.
	 * @return \Generator<string, array<string, mixed>> Generator yielding test results.
	 */
	public function run_all_tests( array $query ): \Generator {

		static::$query = &$query;

		$this->prime_cache();
		$this->prime_query_cache();

		foreach ( static::$tests as $test ) {
			yield $test => $this->{ "test_{$test}" }();
		}
	}

	/**
	 * Primes the shared static cache with global data.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function prime_cache(): void {}

	/**
	 * Primes the per-instance query cache for the current subject.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function prime_query_cache(): void {}

	/**
	 * Gets a cached value from the shared static cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The cache key.
	 * @return mixed The cached value, or null if not found.
	 */
	protected static function get_cache( string $key ): mixed {
		return static::$cache[ $key ] ?? null;
	}

	/**
	 * Sets a cached value in the shared static cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   The cache key.
	 * @param mixed  $value The value to cache.
	 * @return mixed The cached value.
	 */
	protected static function set_cache( string $key, mixed $value ): mixed {
		return static::$cache[ $key ] = $value;
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
}
