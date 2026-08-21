<?php
/**
 * Better SEO - Data Plugin User
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Data\Plugin
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

namespace Better_SEO\Data\Plugin;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\is_headless;

use Better_SEO\{
	Helper\Query,
	Traits\Property_Refresher,
};

/**
 * Class Better_SEO\Data\Plugin\User
 *
 * Provides data access and persistence methods for Better SEO user meta,
 * including meta retrieval, saving, and deletion with headless mode support.
 *
 * @since 1.0.0
 */
class User {
	use Property_Refresher;

	/**
	 * Memoized user meta arrays keyed by user ID.
	 *
	 * Capped at 70 entries to prevent memory overload.
	 *
	 * @since 1.0.0
	 * @var   array<int, array<string, mixed>>
	 */
	private static array $meta_memo = [];

	/**
	 * Returns a specific meta item for the current post's author.
	 *
	 * @since 1.0.0
	 *
	 * @param string $item The meta key to retrieve.
	 * @return mixed The meta item value, or null if no author found.
	 */
	public static function get_current_post_author_meta_item( string $item ): mixed {

		$user_id = Query::get_post_author_id();

		return $user_id
			? static::get_meta_item( $item, $user_id )
			: null;
	}

	/**
	 * Returns the full Better SEO meta array for the current post's author.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>|null The author meta array, or null if no author found.
	 */
	public static function get_current_post_author_meta(): ?array {

		$user_id = Query::get_post_author_id();

		return $user_id
			? static::get_meta( $user_id )
			: null;
	}

	/**
	 * Returns a specific meta item value for a given user.
	 *
	 * @since 1.0.0
	 *
	 * @param string $item    The meta key to retrieve.
	 * @param int    $user_id The user ID. Defaults to current user.
	 * @return mixed The meta item value, or null if not found.
	 */
	public static function get_meta_item( string $item, int $user_id = 0 ): mixed {

		$user_id = $user_id ?: Query::get_current_user_id();

		return $user_id
			? static::get_meta( $user_id )[ $item ] ?? null
			: null;
	}

	/**
	 * Returns the full Better SEO meta array for a given user, memoized.
	 *
	 * Handles headless mode by filtering out unsupported or immutable meta keys.
	 * Applies the better_seo_user_meta filter.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The user ID. Defaults to current user.
	 * @return array<string, mixed> The merged user meta array.
	 */
	public static function get_meta( int $user_id = 0 ): array {

		$user_id = $user_id ?: Query::get_current_user_id();

		if ( isset( static::$meta_memo[ $user_id ] ) ) {
			return static::$meta_memo[ $user_id ];
		}

		if ( empty( static::$meta_memo ) ) {
			static::register_automated_refresh( 'meta_memo' );
		}

		if ( empty( $user_id ) ) {
			return static::$meta_memo[ $user_id ] = static::get_default_meta( $user_id );
		}

		// Cap memo at 70 entries — keep the first 7 (lucky first) to avoid memory overload.
		if ( \count( static::$meta_memo ) > 69 ) {
			static::$meta_memo = \array_slice( static::$meta_memo, 0, 7, true );
		}

		$is_headless = is_headless();

		if ( $is_headless['user'] ) {
			// Filter out everything that's 'not supported' or 'immutable' in headless mode.
			$meta = [];

			if ( \in_array( false, $is_headless, true ) ) {
				$_meta = \get_user_meta( $user_id, \BETTER_SEO_USER_OPTIONS, true ) ?: [];

				// The counter type is still supported for meta and settings.
				// Retrieve those items if either type (meta/settings) isn't headless.
				$non_headless_meta = [
					'counter_type' => [
						'meta',
						'settings',
					],
				];

				// Grab non-headless meta if any meta type isn't headless.
				foreach ( $non_headless_meta as $meta_key => $meta_types ) {
					if ( ! isset( $_meta[ $meta_key ] ) ) {
						continue;
					}

					foreach ( $meta_types as $meta_type ) {
						if ( $is_headless[ $meta_type ] ) {
							continue;
						}

						$meta[ $meta_key ] = $_meta[ $meta_key ];
						// This key bypasses headless mode — skip subsequent redundant checks.
						continue 2;
					}
				}
			}
		} else {
			$meta = (array) ( \get_user_meta( $user_id, \BETTER_SEO_USER_OPTIONS, true ) ?: [] );
		}

		/**
		 * Filters the Better SEO user meta array.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $meta        The merged user meta array.
		 * @param int                  $user_id     The user ID.
		 * @param bool                 $is_headless Whether user meta is running headless.
		 */
		return static::$meta_memo[ $user_id ] = \apply_filters(
			'better_seo_user_meta',
			array_merge(
				static::get_default_meta( $user_id ),
				$meta,
			),
			$user_id,
			$is_headless['user'],
		);
	}

	/**
	 * Returns the default Better SEO user meta array for a given user.
	 *
	 * Applies the better_seo_user_meta_defaults filter.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The user ID. Defaults to current user.
	 * @return array<string, mixed> The default user meta array.
	 */
	public static function get_default_meta( int $user_id = 0 ): array {
		/**
		 * Filters the Better SEO user meta defaults.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $defaults The default meta array.
		 * @param int                  $user_id  The user ID.
		 */
		return (array) \apply_filters(
			'better_seo_user_meta_defaults',
			[
				'counter_type'  => 3,
				'facebook_page' => '',
				'twitter_page'  => '',
			],
			$user_id ?: Query::get_current_user_id(),
		);
	}

	/**
	 * Updates a single Better SEO meta item for a given user.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user_id The user ID.
	 * @param string $item    The meta key to update.
	 * @param mixed  $value   The new meta value.
	 * @return void
	 */
	public static function update_single_meta_item( int $user_id, string $item, mixed $value ): void {

		$user_id = \get_userdata( $user_id )->ID ?? null;

		if ( empty( $user_id ) ) {
			return;
		}

		$meta          = static::get_meta( $user_id );
		$meta[ $item ] = $value;

		static::save_meta( $user_id, $meta );
	}

	/**
	 * Saves Better SEO user meta to the database.
	 *
	 * Merges with defaults and applies the better_seo_save_user_data filter before saving.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $user_id The user ID.
	 * @param array<string, mixed> $data    The meta data to save.
	 * @return void
	 */
	public static function save_meta( int $user_id, array $data ): void {

		$user_id = \get_userdata( $user_id )->ID ?? null;

		if ( empty( $user_id ) ) {
			return;
		}

		/**
		 * Filters the Better SEO user meta before saving.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $data    The merged meta data to save.
		 * @param int                  $user_id The user ID.
		 */
		$data = (array) \apply_filters(
			'better_seo_save_user_data',
			array_merge(
				static::get_default_meta( $user_id ),
				$data,
			),
			$user_id,
		);

		unset( static::$meta_memo[ $user_id ] );

		\update_user_meta( $user_id, \BETTER_SEO_USER_OPTIONS, $data );
	}

	/**
	 * Deletes Better SEO user meta from the database.
	 *
	 * Removes all default meta keys from the stored meta. If no data remains,
	 * deletes the meta entry entirely; otherwise updates with remaining data.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id The user ID.
	 * @return void
	 */
	public static function delete_meta( int $user_id ): void {

		$data = \get_user_meta( $user_id, \BETTER_SEO_USER_OPTIONS, true );

		if ( \is_array( $data ) ) {
			foreach ( static::get_default_meta( $user_id ) as $key => $value ) {
				unset( $data[ $key ] );
			}
		}

		unset( static::$meta_memo[ $user_id ] );

		if ( empty( $data ) ) {
			\delete_user_meta( $user_id, \BETTER_SEO_USER_OPTIONS );
		} else {
			\update_user_meta( $user_id, \BETTER_SEO_USER_OPTIONS, $data );
		}
	}
}