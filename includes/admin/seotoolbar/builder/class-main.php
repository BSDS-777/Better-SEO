<?php
/**
 * Better SEO - Admin SEO Bar Builder Main
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Admin\SEOBar\Builder
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

namespace Better_SEO\Admin\SEOBar\Builder;

\defined( 'BETTER_SEO_PRESENT' ) or die;

/**
 * Class Better_SEO\Admin\SEOBar\Builder\Main
 *
 * Abstract base class for Better SEO Bar builders.
 * Provides shared test registration, caching, and query management
 * for post/page and term SEO Bar builder implementations.
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
	protected array $query_cache = [];

	/**
	 * The singleton instance of the builder subclass.
	 *
	 * @since 1.0.0
	 * @var   static|null
	 */
	protected static ?self $instance = null;

	/**
	 * Constructor. Primes the shared cache on instantiation.
	 *
	 * @since 1.0.0
	 */
	final protected function __construct() {
		$this->prime_cache();
	}

	/**
	 * Returns the singleton instance of the builder subclass.
	 *
	 * Uses late static binding to allow subclass-specific instantiation.
	 *
	 * @since 1.0.0
	 *
	 * @return static The singleton builder instance.
	 */
	final public static function get_instance(): static {
		return static::$instance ??= new static();
	}

	/**
	 * Stores a value in the shared static cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   The cache key.
	 * @param mixed  $value The value to cache.
	 * @return mixed The cached value.
	 */
	final protected static function set_cache( string $key, mixed $value ): mixed {
		return self::$cache[ $key ] = $value;
	}

	/**
	 * Retrieves a value from the shared static cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The cache key.
	 * @return mixed The cached value, or null if not set.
	 */
	final protected static function get_cache( string $key ): mixed {
		return self::$cache[ $key ] ?? null;
	}

	/**
	 * Runs all registered tests for the given query and yields results.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $query The current query arguments.
	 * @return \Generator Yields test key => test result pairs.
	 */
	public function run_all_tests( array $query ): \Generator {
		yield from $this->run_test( static::$tests, $query );
	}

	/**
	 * Runs a specific subset of tests for the given query and yields results.
	 *
	 * If a blocking redirect is detected, only the redirect test is run.
	 * Uses late static binding for $tests and $query to allow subclass overrides.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string>   $tests The test names to run.
	 * @param array<string, mixed> $query The current query arguments.
	 * @return \Generator Yields test key => test result pairs.
	 */
	final public function run_test( array $tests, array $query ): \Generator {

		// Intersect with registered tests to prevent running unregistered tests.
		$tests = array_intersect( static::$tests, $tests );

		// Use late static binding to allow subclass query overrides.
		static::$query = $query;

		$this->prime_query_cache();

		if ( \in_array( 'redirect', $tests, true ) && $this->has_blocking_redirect() ) {
			$tests = [ 'redirect' ];
		}

		foreach ( $tests as $test ) {
			// Dynamic dispatch: calls test_redirect(), test_title(), etc.
			yield $test => $this->{"test_{$test}"}();
		}
	}

	/**
	 * Clears the per-instance query cache.
	 *
	 * Should be called after bar generation to prevent memory leaks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	final public function clear_query_cache(): void {
		$this->query_cache = [];
	}

	/**
	 * Returns the current per-instance query cache.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The current query cache.
	 */
	final public function get_query_cache(): array {
		return $this->query_cache;
	}

	/**
	 * Primes the shared static cache for this builder.
	 *
	 * Called once on instantiation. Subclasses should populate
	 * any data that is shared across multiple bar generations.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	abstract protected function prime_cache(): void;

	/**
	 * Primes the per-instance query cache for the current bar generation.
	 *
	 * Called at the start of each run_test() invocation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	abstract protected function prime_query_cache(): void;

	/**
	 * Determines whether a blocking redirect exists for the current query.
	 *
	 * If true, only the redirect test will be run for this bar.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if a blocking redirect is detected, false otherwise.
	 */
	abstract protected function has_blocking_redirect(): bool;
}