<?php
/**
 * Better SEO - Data Admin User
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Data\Admin
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

namespace Better_SEO\Data\Admin;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\Data;

/**
 * Class Better_SEO\Data\Admin\User
 *
 * Handles saving of Better SEO user meta from the profile and user edit screens.
 *
 * @since 1.0.0
 */
final class User {

	/**
	 * Nonce definitions for user meta save operations.
	 *
	 * @since 1.0.0
	 * @var   array<string, array<string, string>>
	 */
	public const SAVE_NONCES = [
		'user-edit' => [
			'name'   => 'better_seo_user_nonce_name',
			'action' => 'better_seo_user_nonce_action',
		],
	];

	/**
	 * Saves Better SEO user meta from the profile or user edit screen.
	 *
	 * Verifies capability, nonce, and network author info capability before saving.
	 * Only overwrites the fields provided — does not reset all meta.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The user ID being saved.
	 * @return void
	 */
	public static function update_meta( int $user_id ): void {

		if ( empty( $_POST['better-seo-user-meta'] ) ) {
			return;
		}

		if ( ! \current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		if (
			! isset( $_POST[ self::SAVE_NONCES['user-edit']['name'] ] )
			|| ! \wp_verify_nonce( $_POST[ self::SAVE_NONCES['user-edit']['name'] ], self::SAVE_NONCES['user-edit']['action'] )
		) {
			return;
		}

		if ( ! Data\User::user_has_author_info_cap_on_network( $user_id ) ) {
			return;
		}

		// Only overwrite provided fields — do not reset all meta.
		$data = array_merge(
			Data\Plugin\User::get_meta( $user_id ),
			(array) ( $_POST['better-seo-user-meta'] ?? [] ),
		);

		Data\Plugin\User::save_meta( $user_id, $data );
	}
}