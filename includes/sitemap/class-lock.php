<?php
/**
 * Better SEO - Sitemap Lock
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

use Better_SEO\Helper;

/**
 * Class Better_SEO\Sitemap\Lock
 *
 * Provides sitemap generation locking via WordPress transients for Better SEO.
 * Prevents concurrent sitemap generation by locking the sitemap endpoint
 * for the duration of the PHP max execution time (capped at 3 minutes).
 *
 * @since 1.0.0
 */
class Lock {

	/**
	 * Returns the transient lock key for the given sitemap endpoint ID.
	 *
	 * Returns false if the endpoint is not registered or has no lock ID.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sitemap_id The sitemap endpoint ID.
	 * @return string|false The full transient lock key, or false if not applicable.
	 */
	public static function get_lock_key( string $sitemap_id ): string|false {

		$ep_list = Registry::get_sitemap_endpoint_list();

		if ( ! isset( $ep_list[ $sitemap_id ] ) ) {
			return false;
		}

		$lock_id = $ep_list[ $sitemap_id ]['lock_id'] ?? $sitemap_id;

		return Cache::build_sitemap_cache_key( Cache::get_transient_prefix() . 'lock' ) . "_{$lock_id}";
	}

	/**
	 * Outputs a 503 Service Unavailable response indicating the sitemap is locked.
	 *
	 * Displays the remaining lock time in seconds if available, or a generic
	 * locked message otherwise. Exits after output.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sitemap_id The sitemap endpoint ID.
	 * @return void
	 */
	public static function output_locked_header( string $sitemap_id ): void {

		Helper\Headers::clean_response_header();

		\status_header( 503 );
		\nocache_headers();

		$lock_key = self::get_lock_key( $sitemap_id );
		$timeout  = $lock_key ? \get_transient( $lock_key ) : false;

		if ( $timeout ) {
			printf(
				/* translators: %d = number of seconds */
				\esc_html__( 'Sitemap is locked for %d seconds. Try again later.', 'better-seo' ),
				(int) ( $timeout - time() ),
			);
		} else {
			\esc_html_e( 'Sitemap is locked temporarily. Try again later.', 'better-seo' );
		}

		echo "\n";
		exit;
	}

	/**
	 * Locks the sitemap endpoint for the duration of the PHP max execution time.
	 *
	 * The lock timeout is capped at 3 minutes. If max_execution_time is 0
	 * (unlimited), a 3-minute timeout is used.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sitemap_id The sitemap endpoint ID.
	 * @return bool True if the lock transient was set successfully, false otherwise.
	 */
	public static function lock_sitemap( string $sitemap_id ): bool {

		$lock_key = self::get_lock_key( $sitemap_id );

		if ( ! $lock_key ) {
			return false;
		}

		$ini_max_execution_time = (int) ini_get( 'max_execution_time' );

		if ( 0 === $ini_max_execution_time ) {
			// Unlimited execution time — cap the lock at 3 minutes.
			$timeout = 3 * \MINUTE_IN_SECONDS;
		} else {
			$timeout = (int) min( $ini_max_execution_time, 3 * \MINUTE_IN_SECONDS );
		}

		return \set_transient( $lock_key, time() + $timeout, $timeout );
	}

	/**
	 * Unlocks the sitemap endpoint by deleting the lock transient.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sitemap_id The sitemap endpoint ID.
	 * @return bool True if the lock was deleted, false if no lock key exists.
	 */
	public static function unlock_sitemap( string $sitemap_id ): bool {

		$lock_key = self::get_lock_key( $sitemap_id );

		return $lock_key ? \delete_transient( $lock_key ) : false;
	}

	/**
	 * Returns whether the sitemap endpoint is currently locked.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sitemap_id The sitemap endpoint ID.
	 * @return mixed The lock expiry timestamp if locked, false if not locked or no lock key.
	 */
	public static function is_sitemap_locked( string $sitemap_id ): mixed {

		$lock_key = self::get_lock_key( $sitemap_id );

		return $lock_key ? \get_transient( $lock_key ) : false;
	}
}