<?php
/**
 * Better SEO - Data User
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

use function Better_SEO\umemo;

/**
 * Class Better_SEO\Data\User
 *
 * Provides data helper methods for user-level information,
 * including capability checks and user data retrieval.
 *
 * @since 1.0.0
 * @access protected
 *         Use better_seo()->data()->user() instead.
 *
 * Note: The @package in the original file incorrectly stated Data\Post —
 * this class belongs to Better_SEO\Data\User.
 */
class User {

	/**
	 * Returns whether a user has the Better SEO author info capability on any blog in the network.
	 *
	 * On multisite, iterates through all blogs the user belongs to and checks
	 * the BETTER_SEO_AUTHOR_INFO_CAP capability on each. Returns true on first match.
	 * On single-site, checks the capability directly on the current site.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User|int $user The user object or user ID.
	 * @return bool True if the user has the author info capability, false otherwise.
	 */
	public static function user_has_author_info_cap_on_network( \WP_User|int $user ): bool {

		if ( ! \is_object( $user ) ) {
			$user = self::get_userdata( $user );
		}

		// User is logged out or not found.
		if ( ! $user ) {
			return false;
		}

		if ( \is_multisite() ) {
			// On multisite, WordPress prevents editing other users' profiles for non-super admins.
			// Clone the user object to avoid tainting the global instance.
			$_user = clone $user;

			$user_has_cap = false;

			foreach ( \get_blogs_of_user( $_user->ID ) as $user_blog ) {
				// switch_to_blog() is required — plugins may insert custom roles per site.
				\switch_to_blog( $user_blog->userblog_id );

				// The stored/cloned user object doesn't switch with switch_to_blog() — fix that.
				$_user->for_site( $user_blog->userblog_id );

				$user_has_cap = $_user->has_cap( \BETTER_SEO_AUTHOR_INFO_CAP );

				\restore_current_blog();
				// No need to restore $_user — it's a clone.

				if ( $user_has_cap ) {
					break;
				}
			}

			return $user_has_cap;
		}

		return $user->has_cap( \BETTER_SEO_AUTHOR_INFO_CAP );
	}

	/**
	 * Returns user data for a given user ID, memoized.
	 *
	 * Optionally returns a specific property from the user data object.
	 *
	 * @since 1.0.0
	 *
	 * @param int         $user_id The user ID.
	 * @param string|null $key     Optional. A specific property to return from the user object.
	 * @return \WP_User|mixed|null The user object, a specific property value, or null if not found.
	 */
	public static function get_userdata( int $user_id, ?string $key = null ): mixed {

		$userdata = umemo( __METHOD__, null, $user_id )
			?? umemo( __METHOD__, \get_userdata( $user_id ), $user_id );

		return isset( $key )
			? ( $userdata->$key ?? null )
			: ( $userdata ?: null );
	}
}