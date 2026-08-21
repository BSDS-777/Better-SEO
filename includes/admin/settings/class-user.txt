<?php
/**
 * Better SEO - Admin Settings User
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Admin\Settings
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

namespace Better_SEO\Admin\Settings;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\{
	Data,
	Helper\Template,
};

/**
 * Class Better_SEO\Admin\Settings\User
 *
 * Handles registration and output of the Better SEO settings fields
 * on user profile and author edit screens.
 *
 * @since 1.0.0
 */
final class User {

	/**
	 * Prepares and outputs the Better SEO user settings fields if the user has the required capability.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $user The current user object.
	 * @return void
	 */
	public static function prepare_setting_fields( \WP_User $user ): void {

		if ( ! Data\User::user_has_author_info_cap_on_network( $user ) ) {
			return;
		}

		self::output_setting_fields( $user );
	}

	/**
	 * Outputs the Better SEO user settings fields.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_User $user The current user object.
	 * @return void
	 */
	private static function output_setting_fields( \WP_User $user ): void {

		\wp_nonce_field(
			Data\Admin\User::SAVE_NONCES['user-edit']['action'],
			Data\Admin\User::SAVE_NONCES['user-edit']['name'],
		);

		/**
		 * Fires before the Better SEO author profile fields are rendered.
		 *
		 * @since 1.0.0
		 */
		\do_action( 'better_seo_before_author_fields' );

		Template::output_view( 'profile/settings', $user );

		/**
		 * Fires after the Better SEO author profile fields are rendered.
		 *
		 * @since 1.0.0
		 */
		\do_action( 'better_seo_after_author_fields' );
	}
}