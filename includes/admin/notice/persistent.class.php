<?php
/**
 * Better SEO - Admin Notice Persistent
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Admin\Notice
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

namespace Better_SEO\Admin\Notice;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\{
	Data,
	Helper\Query,
	Helper\Template,
};

/**
 * Class Better_SEO\Admin\Notice\Persistent
 *
 * Manages persistent admin notices that survive page loads and can be
 * conditionally displayed based on screen, user, capability, count, and timeout.
 *
 * @since 1.0.0
 */
class Persistent {

	/**
	 * Registers a persistent admin notice to be displayed on subsequent page loads.
	 *
	 * Notices are stored in the site cache and displayed until dismissed,
	 * their count reaches zero, or their timeout expires.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $message    The notice message to display.
	 * @param string|int           $key        Unique identifier for the notice. Will be sanitized.
	 * @param array<string, mixed> $args       Optional. Display arguments (type, icon, escape).
	 * @param array<string, mixed> $conditions Optional. Display conditions (screens, capability, user, count, timeout).
	 * @return void
	 */
	public static function register_notice( string $message, string|int $key, array $args = [], array $conditions = [] ): void {

		// Guard against empty keys — scalar type is enforced by union type above.
		if ( ! \strlen( (string) $key ) ) {
			return;
		}

		// Sanitize the key so HTML, JS, and PHP can communicate via it consistently.
		$key = \sanitize_key( (string) $key );

		$args += [
			'type'   => 'updated',
			'icon'   => true,
			'escape' => true,
		];

		$conditions += [
			'screens'      => [],
			'excl_screens' => [],
			'capability'   => \BETTER_SEO_SETTINGS_CAP,
			'user'         => 0,
			'count'        => 1,
			'timeout'      => -1,
		];

		// A capability is required for security — never register without one.
		if ( ! $conditions['capability'] ) {
			return;
		}

		// Timeout already expired — no point registering.
		if ( $conditions['timeout'] < -1 ) {
			return;
		}

		// Convert relative timeout to absolute Unix timestamp for later comparison.
		if ( $conditions['timeout'] > -1 ) {
			$conditions['timeout'] += time();
		}

		$notices         = Data\Plugin::get_site_cache( 'persistent_notices' ) ?? [];
		$notices[ $key ] = compact( 'message', 'args', 'conditions' );

		Data\Plugin::update_site_cache( 'persistent_notices', $notices );
	}

	/**
	 * Decrements the display count for a persistent notice.
	 *
	 * Clears the notice when the count reaches zero.
	 * Notices with a negative count are treated as permanent and are not decremented.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key   The notice key.
	 * @param int    $count The current display count.
	 * @return void
	 */
	public static function count_down_notice( string $key, int $count ): void {

		// Negative count means permanent notice — never decrement.
		if ( $count < 0 ) {
			return;
		}

		--$count;

		if ( ! $count ) {
			self::clear_notice( $key );
		} else {
			$notices = Data\Plugin::get_site_cache( 'persistent_notices' );

			if ( isset( $notices[ $key ]['conditions']['count'] ) ) {
				$notices[ $key ]['conditions']['count'] = $count;
				Data\Plugin::update_site_cache( 'persistent_notices', $notices );
			} else {
				// Notice did not conform to expected structure — remove it.
				self::clear_notice( $key );
			}
		}
	}

	/**
	 * Removes a single persistent notice from the site cache.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The notice key to remove.
	 * @return bool True if the cache was updated successfully, false otherwise.
	 */
	public static function clear_notice( string $key ): bool {

		$notices = Data\Plugin::get_site_cache( 'persistent_notices' ) ?? [];

		unset( $notices[ $key ] );

		return Data\Plugin::update_site_cache( 'persistent_notices', $notices );
	}

	/**
	 * Removes all persistent notices from the site cache.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the cache was updated successfully, false otherwise.
	 */
	public static function clear_all_notices(): bool {
		return Data\Plugin::update_site_cache( 'persistent_notices', [] );
	}

	/**
	 * Returns the nonce action string for dismissing a specific notice.
	 *
	 * @since    1.0.0
	 * @internal Used internally by notice templates and dismiss handler.
	 *
	 * @param string $key The notice key.
	 * @return string The sanitized nonce action string.
	 */
	public static function _get_dismiss_nonce_action( string $key ): string {
		return \sanitize_key( "better-seo-notice-nonce-{$key}" );
	}

	/**
	 * Outputs all eligible persistent notices for the current screen and user.
	 *
	 * Checks screen, user, capability, timeout, and count conditions before
	 * rendering each notice. Expired notices are cleared automatically.
	 *
	 * @since    1.0.0
	 * @internal Called via WordPress admin_notices action.
	 *
	 * @return void
	 */
	public static function _output_notices(): void {

		$notices    = Data\Plugin::get_site_cache( 'persistent_notices' ) ?? [];
		$screenbase = \get_current_screen()->base ?? '';

		// Output each eligible notice. On no-JS, multiple notices may appear simultaneously.
		foreach ( $notices as $key => $notice ) {
			$cond = $notice['conditions'];

			if (
				! \current_user_can( $cond['capability'] )
				|| ( $cond['user'] && Query::get_current_user_id() !== $cond['user'] )
				|| ( $cond['screens'] && ! \in_array( $screenbase, $cond['screens'], true ) )
				|| ( $cond['excl_screens'] && \in_array( $screenbase, $cond['excl_screens'], true ) )
			) {
				continue;
			}

			if ( -1 !== $cond['timeout'] && $cond['timeout'] < time() ) {
				self::clear_notice( $key );
				continue;
			}

			Template::output_view( 'notice/persistent', $notice['message'], $key, $notice['args'] );

			self::count_down_notice( $key, $cond['count'] );
		}
	}

	/**
	 * Handles the AJAX request to dismiss a persistent notice.
	 *
	 * Verifies the nonce and user capability before clearing the notice.
	 *
	 * @since    1.0.0
	 * @internal Called via WordPress wp_ajax_ action.
	 *
	 * @return void
	 */
	public static function _dismiss_notice(): void {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- POST key is used only to look up locally stored nonce for verification below.
		$key = \sanitize_key( $_POST['better-seo-notice-submit'] ?? '' );

		if ( ! $key ) {
			return;
		}

		$notices = Data\Plugin::get_site_cache( 'persistent_notices' ) ?? [];

		// Notice was already cleared elsewhere or key was invalid — ignore silently.
		if ( empty( $notices[ $key ]['conditions']['capability'] ) ) {
			return;
		}

		if (
			empty( $_POST['better_seo_notice_nonce'] )
			|| ! \current_user_can( $notices[ $key ]['conditions']['capability'] )
			|| ! \wp_verify_nonce( $_POST['better_seo_notice_nonce'], self::_get_dismiss_nonce_action( $key ) )
		) {
			\wp_die( -1, '', [ 'response' => 403 ] );
		}

		self::clear_notice( $key );
	}
}