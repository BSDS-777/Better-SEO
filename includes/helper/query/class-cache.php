<?php
/**
 * Better SEO - Helper Query Cache
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Helper\Query
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

namespace Better_SEO\Helper\Query;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\Helper\Query;

/**
 * Class Better_SEO\Helper\Query\Cache
 *
 * Provides a caller-aware memoization cache for Better SEO query helper methods.
 * Caching is disabled during WP-CLI and before the query or screen is initialized.
 *
 * @since 1.0.0
 */
class Cache {

	/**
	 * Whether the current query context supports caching.
	 *
	 * @since 1.0.0
	 * @var   bool|null
	 */
	private static ?bool $can_cache_query = null;

	/**
	 * The memoization store keyed by caller/args hash.
	 *
	 * @since 1.0.0
	 * @var   array<string, mixed>
	 */
	private static array $memo = [];

	/**
	 * Gets or sets a memoized value for the calling method and given arguments.
	 *
	 * Returns null if caching is not available. Returns the stored value if
	 * $value_to_set is null and a cached value exists. Stores and returns
	 * $value_to_set if provided.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value_to_set The value to store, or null to retrieve. Default null.
	 * @param mixed ...$args      Additional arguments used to differentiate cache entries.
	 * @return mixed The cached value, the stored value, or null if not cacheable.
	 */
	public static function memo( mixed $value_to_set = null, mixed ...$args ): mixed {

		if (
			! self::$can_cache_query
			&& ! self::can_cache_query()
		) {
			return $value_to_set;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions -- debug_backtrace is required for caller detection.
		$caller = debug_backtrace( \DEBUG_BACKTRACE_IGNORE_ARGS, 2 )[1]['function'] ?? '';

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- serialize is used for cache key generation only.
		$hash = "{$caller}/" . serialize( $args );

		if ( isset( $value_to_set ) ) {
			return self::$memo[ $hash ] = $value_to_set;
		}

		return self::$memo[ $hash ] ?? null;
	}

	/**
	 * Returns whether the current query context supports caching, memoized.
	 *
	 * Caching is disabled during WP-CLI. Caching is enabled once the WP query
	 * or admin screen is initialized.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if query caching is available.
	 */
	public static function can_cache_query(): bool {

		if ( isset( self::$can_cache_query ) ) {
			return self::$can_cache_query;
		}

		if ( \defined( 'WP_CLI' ) && \WP_CLI ) {
			return self::$can_cache_query = false;
		}

		if ( isset( $GLOBALS['wp_query']->query ) || isset( $GLOBALS['current_screen'] ) ) {
			return self::$can_cache_query = true;
		}

		if ( \BETTER_SEO_DEBUG ) {
			self::do_query_error_notice();
		}

		return false;
	}

	/**
	 * Outputs a debug error notice when a query method is called too early.
	 *
	 * Only runs when BETTER_SEO_DEBUG is enabled. Logs a backtrace on first call.
	 *
	 * @since    1.0.0
	 * @internal Debug utility — not part of the public API.
	 *
	 * @return void
	 */
	private static function do_query_error_notice(): void {

		$backtrace = debug_backtrace( \DEBUG_BACKTRACE_PROVIDE_OBJECT, 8 );

		if ( ! $backtrace ) {
			return;
		}

		$error = $backtrace[3];

		foreach ( array_reverse( \array_slice( $backtrace, 3 ) ) as $trace ) {
			if (
				isset( $trace['object'] )
				&& is_a( $trace['object'], better_seo_class(), false )
			) {
				$error = $trace;
				break;
			}
		}

		$message = "You've initiated a method that uses queries too early.";

		if ( ! empty( $error ) ) {
			$message .= " - In file: {$error['file']}";
			$message .= " - On line: {$error['line']}";
		}

		better_seo()->_doing_it_wrong( \esc_html( $error['function'] ?? '' ), \esc_html( $message ), '1.0.0' );

		$depth = 10;
		static $_more = true;

		if ( $_more ) {
			error_log( var_export( debug_backtrace( \DEBUG_BACKTRACE_PROVIDE_OBJECT, $depth ), true ) );
			$_more = false;
		}
	}
}