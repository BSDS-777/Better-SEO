<?php
/**
 * Better SEO - Data Plugin Term
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
	Helper\Taxonomy,
	Traits\Property_Refresher,
};

/**
 * Class Better_SEO\Data\Plugin\Term
 *
 * Provides data access and persistence methods for Better SEO term meta,
 * including meta retrieval, saving, and deletion.
 *
 * @since 1.0.0
 */
class Term {
	use Property_Refresher;

	/**
	 * Memoized term meta arrays keyed by term ID.
	 *
	 * Capped at 70 entries to prevent memory overload.
	 *
	 * @since 1.0.0
	 * @var   array<int, array<string, mixed>>
	 */
	private static array $meta_memo = [];

	/**
	 * Returns a specific meta item value for a given term.
	 *
	 * @since 1.0.0
	 *
	 * @param string $item    The meta key to retrieve.
	 * @param int    $term_id The term ID. Defaults to current term.
	 * @return mixed The meta item value, or null if not found.
	 */
	public static function get_meta_item( string $item, int $term_id = 0 ): mixed {

		$term_id = $term_id ?: Query::get_the_real_id();

		return $term_id
			? static::get_meta( $term_id )[ $item ] ?? null
			: null;
	}

	/**
	 * Returns the full Better SEO meta array for a given term, memoized.
	 *
	 * Merges stored term meta with defaults. Returns defaults for unsupported
	 * taxonomies or when running headless. Applies the better_seo_term_meta filter.
	 *
	 * @since 1.0.0
	 *
	 * @param int  $term_id The term ID. Defaults to current term.
	 * @param bool $memo    Whether to use the memo cache. Default true.
	 * @return array<string, mixed> The merged term meta array.
	 */
	public static function get_meta( int $term_id = 0, bool $memo = true ): array {

		$term_id = $term_id ?: Query::get_the_real_id();

		if ( $memo && isset( static::$meta_memo[ $term_id ] ) ) {
			return static::$meta_memo[ $term_id ];
		}

		if ( empty( static::$meta_memo ) ) {
			static::register_automated_refresh( 'meta_memo' );
		}

		if ( empty( $term_id ) || ! Taxonomy::is_supported( \get_term( $term_id )->taxonomy ?? '' ) ) {
			return static::$meta_memo[ $term_id ] = static::get_default_meta( $term_id );
		}

		// Cap memo at 70 entries — keep the first 7 (lucky first) to avoid memory overload.
		if ( \count( static::$meta_memo ) > 69 ) {
			static::$meta_memo = \array_slice( static::$meta_memo, 0, 7, true );
		}

		$is_headless = is_headless( 'meta' );

		if ( $is_headless ) {
			$meta = [];
		} else {
			$meta = \get_term_meta( $term_id, \BETTER_SEO_TERM_OPTIONS, true ) ?: [];
		}

		/**
		 * Filters the Better SEO term meta array.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $meta        The merged term meta array.
		 * @param int                  $term_id     The term ID.
		 * @param bool                 $is_headless Whether the plugin is running headless.
		 */
		return static::$meta_memo[ $term_id ] = \apply_filters(
			'better_seo_term_meta',
			array_merge(
				static::get_default_meta( $term_id ),
				$meta,
			),
			$term_id,
			$is_headless,
		);
	}

	/**
	 * Returns the default Better SEO term meta array for a given term.
	 *
	 * Applies the better_seo_term_meta_defaults filter.
	 *
	 * @since 1.0.0
	 *
	 * @param int $term_id The term ID. Defaults to current term.
	 * @return array<string, mixed> The default term meta array.
	 */
	public static function get_default_meta( int $term_id = 0 ): array {
		/**
		 * Filters the Better SEO term meta defaults.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $defaults The default meta array.
		 * @param int                  $term_id  The term ID.
		 */
		return (array) \apply_filters(
			'better_seo_term_meta_defaults',
			[
				'doctitle'           => '',
				'title_no_blog_name' => 0,
				'description'        => '',
				'og_title'           => '',
				'og_description'     => '',
				'tw_title'           => '',
				'tw_description'     => '',
				'tw_card_type'       => '',
				'social_image_url'   => '',
				'social_image_id'    => 0,
				'canonical'          => '',
				'noindex'            => 0,
				'nofollow'           => 0,
				'noarchive'          => 0,
				'redirect'           => '',
			],
			$term_id ?: Query::get_the_real_id(),
		);
	}

	/**
	 * Updates a single Better SEO meta item for a given term.
	 *
	 * @since 1.0.0
	 *
	 * @param string $item    The meta key to update.
	 * @param mixed  $value   The new meta value.
	 * @param int    $term_id The term ID.
	 * @return void
	 */
	public static function update_single_meta_item( string $item, mixed $value, int $term_id ): void {

		$term_id = \get_term( $term_id )->term_id ?? null;

		if ( empty( $term_id ) ) {
			return;
		}

		$meta          = static::get_meta( $term_id, false );
		$meta[ $item ] = $value;

		static::save_meta( $term_id, $meta );
	}

	/**
	 * Saves Better SEO term meta to the database.
	 *
	 * Merges with defaults and applies the better_seo_save_term_data filter before saving.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $term_id The term ID.
	 * @param array<string, mixed> $data    The meta data to save.
	 * @return void
	 */
	public static function save_meta( int $term_id, array $data ): void {

		$term_id = \get_term( $term_id )->term_id ?? null;

		if ( empty( $term_id ) ) {
			return;
		}

		/**
		 * Filters the Better SEO term meta before saving.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $data    The merged meta data to save.
		 * @param int                  $term_id The term ID.
		 */
		$data = (array) \apply_filters(
			'better_seo_save_term_data',
			array_merge(
				static::get_default_meta( $term_id ),
				$data,
			),
			$term_id,
		);

		unset( static::$meta_memo[ $term_id ] );

		\update_term_meta( $term_id, \BETTER_SEO_TERM_OPTIONS, $data );
	}

	/**
	 * Deletes Better SEO term meta from the database.
	 *
	 * Removes all default meta keys from the stored meta. If no data remains,
	 * deletes the meta entry entirely; otherwise updates with remaining data.
	 *
	 * @since 1.0.0
	 *
	 * @param int $term_id The term ID.
	 * @return void
	 */
	public static function delete_meta( int $term_id ): void {

		$data = \get_term_meta( $term_id, \BETTER_SEO_TERM_OPTIONS, true );

		if ( \is_array( $data ) ) {
			foreach ( static::get_default_meta( $term_id ) as $key => $value ) {
				unset( $data[ $key ] );
			}
		}

		unset( static::$meta_memo[ $term_id ] );

		if ( empty( $data ) ) {
			\delete_term_meta( $term_id, \BETTER_SEO_TERM_OPTIONS );
		} else {
			\update_term_meta( $term_id, \BETTER_SEO_TERM_OPTIONS, $data );
		}
	}
}