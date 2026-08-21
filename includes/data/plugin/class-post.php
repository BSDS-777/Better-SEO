<?php
/**
 * Better SEO - Data Plugin Post
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
	Data,
	Helper\Post_Type,
	Helper\Query,
	Traits\Property_Refresher,
};

/**
 * Class Better_SEO\Data\Plugin\Post
 *
 * Provides data access and persistence methods for Better SEO post meta,
 * including meta retrieval, saving, and primary term management.
 *
 * @since 1.0.0
 */
class Post {
	use Property_Refresher;

	/**
	 * Memoized post meta arrays keyed by post ID.
	 *
	 * Capped at 70 entries to prevent memory overload.
	 *
	 * @since 1.0.0
	 * @var   array<int, array<string, mixed>>
	 */
	private static array $meta_memo = [];

	/**
	 * Memoized primary term objects keyed by post ID and taxonomy.
	 *
	 * Capped at 70 entries to prevent memory overload.
	 *
	 * @since 1.0.0
	 * @var   array<int, array<string, \WP_Term|false>>
	 */
	private static array $pt_memo = [];

	/**
	 * Returns a specific meta item value for a given post.
	 *
	 * @since 1.0.0
	 *
	 * @param string $item    The meta key to retrieve.
	 * @param int    $post_id The post ID. Defaults to current post.
	 * @return mixed The meta item value, or null if not found.
	 */
	public static function get_meta_item( string $item, int $post_id = 0 ): mixed {

		$post_id = $post_id ?: Query::get_the_real_id();

		return $post_id
			? static::get_meta( $post_id )[ $item ] ?? null
			: null;
	}

	/**
	 * Returns the full Better SEO meta array for a given post, memoized.
	 *
	 * Merges stored post meta with defaults. Returns defaults for unsupported
	 * post types or when running headless. Applies the better_seo_post_meta filter.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID. Defaults to current post.
	 * @return array<string, mixed> The merged post meta array.
	 */
	public static function get_meta( int $post_id = 0 ): array {

		$post_id = $post_id ?: Query::get_the_real_id();

		if ( isset( static::$meta_memo[ $post_id ] ) ) {
			return static::$meta_memo[ $post_id ];
		}

		if ( empty( static::$meta_memo ) ) {
			static::register_automated_refresh( 'meta_memo' );
		}

		if ( empty( $post_id ) || ! Post_Type::is_supported( \get_post( $post_id )->post_type ?? '' ) ) {
			return static::$meta_memo[ $post_id ] = static::get_default_meta( $post_id );
		}

		// Cap memo at 70 entries — keep the first 7 (lucky first) to avoid memory overload.
		if ( \count( static::$meta_memo ) > 69 ) {
			static::$meta_memo = \array_slice( static::$meta_memo, 0, 7, true );
		}

		$defaults    = static::get_default_meta( $post_id );
		$is_headless = is_headless( 'meta' );

		if ( $is_headless ) {
			$meta = [];
		} else {
			$meta = array_intersect_key(
				\get_post_meta( $post_id ) ?: [], // Gets all post meta — note discrepancy with get_term_meta().
				$defaults,
			);

			foreach ( $meta as &$value ) {
				$value = $value[0];
			}
		}

		/**
		 * Filters the Better SEO post meta array.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $meta        The merged post meta array.
		 * @param int                  $post_id     The post ID.
		 * @param bool                 $is_headless Whether the plugin is running headless.
		 */
		return static::$meta_memo[ $post_id ] = \apply_filters(
			'better_seo_post_meta',
			array_merge( $defaults, $meta ),
			$post_id,
			$is_headless,
		);
	}

	/**
	 * Returns the default Better SEO post meta array for a given post.
	 *
	 * Applies the better_seo_post_meta_defaults filter.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID. Defaults to current post.
	 * @return array<string, mixed> The default post meta array.
	 */
	public static function get_default_meta( int $post_id = 0 ): array {
		/**
		 * Filters the Better SEO post meta defaults.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $defaults The default meta array.
		 * @param int                  $post_id  The post ID.
		 */
		return (array) \apply_filters(
			'better_seo_post_meta_defaults',
			[
				'_genesis_title'                  => '',
				'_better_seo_title_no_blogname'   => 0,
				'_genesis_description'            => '',
				'_genesis_canonical_uri'          => '',
				'redirect'                        => '',
				'_social_image_url'               => '',
				'_social_image_id'                => 0,
				'_genesis_noindex'                => 0,
				'_genesis_nofollow'               => 0,
				'_genesis_noarchive'              => 0,
				'exclude_local_search'            => 0,
				'exclude_from_archive'            => 0,
				'_open_graph_title'               => '',
				'_open_graph_description'         => '',
				'_twitter_title'                  => '',
				'_twitter_description'            => '',
				'_better_seo_twitter_card_type'   => '',
			],
			$post_id ?: Query::get_the_real_id(),
		);
	}

	/**
	 * Updates a single Better SEO meta item for a given post.
	 *
	 * @since 1.0.0
	 *
	 * @param string $item    The meta key to update.
	 * @param mixed  $value   The new meta value.
	 * @param int    $post_id The post ID.
	 * @return void
	 */
	public static function update_single_meta_item( string $item, mixed $value, int $post_id ): void {

		$post_id = \get_post( $post_id )->ID ?? null;

		if ( empty( $post_id ) ) {
			return;
		}

		$meta          = static::get_meta( $post_id );
		$meta[ $item ] = $value;

		static::save_meta( $post_id, $meta );
	}

	/**
	 * Saves Better SEO post meta to the database.
	 *
	 * Merges with defaults, applies the better_seo_save_post_meta filter,
	 * runs through the post meta filter, then updates or deletes each field.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $post_id The post ID.
	 * @param array<string, mixed> $data    The meta data to save.
	 * @return void
	 */
	public static function save_meta( int $post_id, array $data ): void {

		$post_id = \get_post( $post_id )->ID ?? null;

		if ( empty( $post_id ) ) {
			return;
		}

		/**
		 * Filters the Better SEO post meta before saving.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $data    The merged meta data to save.
		 * @param int                  $post_id The post ID.
		 */
		$data = (array) \apply_filters(
			'better_seo_save_post_meta',
			array_merge(
				static::get_default_meta( $post_id ),
				$data,
			),
			$post_id,
		);

		unset( static::$meta_memo[ $post_id ] );

		$data = Data\Filter\Post::filter_meta_update( $data );

		foreach ( (array) $data as $field => $value ) {
			if ( $value || ( \is_string( $value ) && \strlen( $value ) ) ) {
				\update_post_meta( $post_id, $field, $value );
			} else {
				\delete_post_meta( $post_id, $field );
			}
		}
	}

	/**
	 * Returns the primary term object for a given post and taxonomy, memoized.
	 *
	 * Falls back to the deepest child term with the lowest term ID if no
	 * primary term is explicitly set. Applies the better_seo_primary_term filter.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id  The post ID.
	 * @param string $taxonomy The taxonomy slug.
	 * @return \WP_Term|null The primary term object, or null if none found.
	 */
	public static function get_primary_term( int $post_id, string $taxonomy ): ?\WP_Term {

		if ( isset( static::$pt_memo[ $post_id ][ $taxonomy ] ) ) {
			return static::$pt_memo[ $post_id ][ $taxonomy ] ?: null;
		}

		// Register refresh only when memo is empty — avoids redundant registration.
		if ( empty( static::$pt_memo ) ) {
			static::register_automated_refresh( 'pt_memo' );
		}

		// Cap memo at 70 entries — keep the first 7 (lucky first) to avoid memory overload.
		if ( \count( static::$pt_memo ) > 69 ) {
			static::$pt_memo = \array_slice( static::$pt_memo, 0, 7, true );
		}

		$is_headless = is_headless( 'meta' );

		if ( $is_headless ) {
			$primary_id = 0;
		} else {
			$primary_id = (int) \get_post_meta( $post_id, "_primary_term_{$taxonomy}", true ) ?: 0;
		}

		$terms        = \get_the_terms( $post_id, $taxonomy );
		$primary_term = null;

		if ( $terms && \is_array( $terms ) ) {
			if ( $primary_id ) {
				foreach ( $terms as $term ) {
					if ( $primary_id === $term->term_id ) {
						$primary_term = $term;
						break;
					}
				}
			}

			if ( ! $primary_term ) {
				$term_ids = array_column( $terms, 'term_id' );
				asort( $term_ids );
				$primary_term = $terms[ array_key_first( $term_ids ) ] ?? null;

				if ( $primary_term && \count( $terms ) > 1 ) {
					// parent_id => child_id; could be 0 => child_id if it has no parent.
					$child_by_parent = array_column( $terms, 'term_id', 'parent' );
					// term_id => $term index; related to $terms, flipped to speed up lookups.
					$term_by_term_id = array_flip( $term_ids );

					// Chain the isset because it expects an array.
					while ( isset(
						$child_by_parent[ $primary_term->term_id ],
						$term_by_term_id[ $child_by_parent[ $primary_term->term_id ] ],
						$terms[ $term_by_term_id[ $child_by_parent[ $primary_term->term_id ] ] ],
					) ) {
						$primary_term = $terms[ $term_by_term_id[ $child_by_parent[ $primary_term->term_id ] ] ];
					}
				}
			}
		}

		/**
		 * Filters the Better SEO primary term for a post.
		 *
		 * @since 1.0.0
		 *
		 * @param \WP_Term|null $primary_term The resolved primary term, or null.
		 * @param int           $post_id      The post ID.
		 * @param string        $taxonomy     The taxonomy slug.
		 * @param bool          $is_headless  Whether the plugin is running headless.
		 */
		static::$pt_memo[ $post_id ][ $taxonomy ] = \apply_filters(
			'better_seo_primary_term',
			$primary_term,
			$post_id,
			$taxonomy,
			$is_headless,
		) ?: false;

		return static::$pt_memo[ $post_id ][ $taxonomy ] ?: null;
	}

	/**
	 * Returns the primary term ID for a given post and taxonomy.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id  The post ID.
	 * @param string $taxonomy The taxonomy slug.
	 * @return int The primary term ID, or 0 if none found.
	 */
	public static function get_primary_term_id( int $post_id, string $taxonomy ): int {
		return static::get_primary_term( $post_id, $taxonomy )->term_id ?? 0;
	}

	/**
	 * Updates the primary term ID for a given post and taxonomy.
	 *
	 * Deletes the meta entry if the value is 0 or empty.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $post_id  The post ID. Defaults to current post ID.
	 * @param string   $taxonomy The taxonomy slug.
	 * @param int      $value    The term ID to set as primary. 0 to remove.
	 * @return int|bool Meta ID on insert, true on update, false on failure, true on delete.
	 */
	public static function update_primary_term_id( ?int $post_id = null, string $taxonomy = '', int $value = 0 ): int|bool {

		unset( static::$pt_memo[ $post_id ?? \get_the_id() ] );

		$value = \absint( $value );

		if ( empty( $value ) ) {
			return \delete_post_meta( $post_id, "_primary_term_{$taxonomy}" );
		}

		return \update_post_meta( $post_id, "_primary_term_{$taxonomy}", $value );
	}
}