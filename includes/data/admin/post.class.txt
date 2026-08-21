<?php
/**
 * Better SEO - Data Admin Post
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

use Better_SEO\{
	Data,
	Helper\Taxonomy,
};

/**
 * Class Better_SEO\Data\Admin\Post
 *
 * Handles saving of Better SEO post meta via post edit, quick edit, and bulk edit.
 *
 * @since 1.0.0
 */
final class Post {

	/**
	 * Nonce definitions for post meta save operations.
	 *
	 * @since 1.0.0
	 * @var   array<string, array<string, string>>
	 */
	public const SAVE_NONCES = [
		'post-edit'  => [
			'name'   => 'better_seo_post_nonce_name',
			'action' => 'better_seo_post_nonce_action',
		],
		'quick-edit' => [
			'name'   => 'better_seo_post_nonce_name',
			'action' => 'better_seo_post_nonce_action',
		],
		'bulk-edit'  => [
			'name'   => 'better_seo_post_nonce_name',
			'action' => 'better_seo_post_nonce_action',
		],
	];

	/**
	 * Routes post meta updates to the appropriate handler based on the edit context.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID being saved.
	 * @return void
	 */
	public static function update_meta( int $post_id ): void {
		// phpcs:disable WordPress.Security.NonceVerification -- Nonce verification is handled in each sub-method.
		if ( ! empty( $_POST['better-seo-quick'] ) ) {
			self::update_via_quick_edit( $post_id );
		} elseif ( ! empty( $_REQUEST['better-seo-bulk'] ) ) {
			// Sent via GET — use $_REQUEST for future-compatibility.
			self::update_via_bulk_edit( $post_id );
		} elseif ( ! empty( $_POST['better-seo'] ) ) {
			self::update_via_post_edit( $post_id );
		}
		// phpcs:enable WordPress.Security.NonceVerification
	}

	/**
	 * Routes primary term updates to the appropriate handler based on the edit context.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID being saved.
	 * @return void
	 */
	public static function update_primary_term( int $post_id ): void {

		// Resolves a quirk where wp_insert_post() has no proper guard.
		$post_id = \get_post( $post_id )->ID ?? null;

		if ( empty( $post_id ) ) {
			return;
		}

		if ( \wp_is_post_autosave( $post_id ) || \wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! \current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$post_type = \get_post_type( $post_id );

		if ( empty( $post_type ) ) {
			return;
		}

		if ( ! empty( $_POST['better-seo'] ) ) {
			// Post-edit context.
			foreach ( Taxonomy::get_hierarchical( 'names', $post_type ) as $taxonomy ) {
				if ( ! \wp_verify_nonce(
					$_POST[ self::SAVE_NONCES['post-edit']['name'] . "_pt_{$taxonomy}" ] ?? '',
					self::SAVE_NONCES['post-edit']['action'] . '_pt',
				) ) {
					continue;
				}

				Data\Plugin\Post::update_primary_term_id(
					$post_id,
					$taxonomy,
					\absint( $_POST['better-seo'][ "_primary_term_{$taxonomy}" ] ?? 0 ),
				);
			}
		} elseif ( ! empty( $_POST['better-seo-quick'] ) ) {
			// Quick-edit context.
			if ( ! \check_ajax_referer( 'inlineeditnonce', '_inline_edit', false ) ) {
				return;
			}

			foreach ( Taxonomy::get_hierarchical( 'names', $post_type ) as $taxonomy ) {
				if ( ! isset( $_POST['better-seo-quick'][ "primary_term_{$taxonomy}" ] ) ) {
					continue;
				}

				$term_id = \absint( \wp_unslash( $_POST['better-seo-quick'][ "primary_term_{$taxonomy}" ] ) );

				if ( $term_id > 0 ) {
					Data\Plugin\Post::update_primary_term_id( $post_id, $taxonomy, $term_id );
				}
			}
		} elseif ( ! empty( $_REQUEST['better-seo-bulk'] ) ) {
			// Bulk-edit context.
			static $verified_bulk_referer = false;

			if ( ! $verified_bulk_referer ) {
				\check_admin_referer( 'bulk-posts' );
				$verified_bulk_referer = true;
			}

			foreach ( Taxonomy::get_hierarchical( 'names', $post_type ) as $taxonomy ) {
				if ( ! isset( $_REQUEST['better-seo-bulk'][ "primary_term_{$taxonomy}" ] ) ) {
					continue;
				}

				$value = $_REQUEST['better-seo-bulk'][ "primary_term_{$taxonomy}" ];

				if ( 'nochange' === $value ) {
					continue;
				}

				$term_id = \absint( $value );

				if ( $term_id > 0 ) {
					$terms = \get_the_terms( $post_id, $taxonomy );

					if ( $terms && ! \is_wp_error( $terms ) ) {
						$valid_term_ids = \array_column( $terms, 'term_id' );

						if ( \in_array( $term_id, $valid_term_ids, true ) ) {
							Data\Plugin\Post::update_primary_term_id( $post_id, $taxonomy, $term_id );
						}
					}
				} else {
					Data\Plugin\Post::update_primary_term_id( $post_id, $taxonomy, 0 );
				}
			}
		}
	}

	/**
	 * Saves post meta from the standard post edit screen.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID being saved.
	 * @return void
	 */
	private static function update_via_post_edit( int $post_id ): void {

		$post_id = \get_post( $post_id )->ID ?? null;

		if ( empty( $post_id ) ) {
			return;
		}

		if ( \wp_is_post_autosave( $post_id ) || \wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Redundant capability check — may need removal for full block editor support.
		if (
			! \current_user_can( 'edit_post', $post_id )
			|| ! isset( $_POST[ self::SAVE_NONCES['post-edit']['name'] ] )
			|| ! \wp_verify_nonce( $_POST[ self::SAVE_NONCES['post-edit']['name'] ], self::SAVE_NONCES['post-edit']['action'] )
		) {
			return;
		}

		// Trim, sanitize, and save the metadata.
		Data\Plugin\Post::save_meta(
			$post_id,
			(array) \wp_unslash( $_POST['better-seo'] ),
		);
	}

	/**
	 * Saves post meta from the quick edit interface.
	 *
	 * Only overwrites the fields provided — does not reset all meta.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID being saved.
	 * @return void
	 */
	private static function update_via_quick_edit( int $post_id ): void {

		$post_id = \get_post( $post_id )->ID ?? null;

		if ( empty( $post_id ) ) {
			return;
		}

		// wp_ajax_inline_save() already performs these checks — this is an additional guard.
		if (
			! \current_user_can( 'edit_post', $post_id )
			|| ! \check_ajax_referer( 'inlineeditnonce', '_inline_edit', false )
			|| ! isset( $_POST[ self::SAVE_NONCES['quick-edit']['name'] ] )
			|| ! \wp_verify_nonce( $_POST[ self::SAVE_NONCES['quick-edit']['name'] ], self::SAVE_NONCES['quick-edit']['action'] )
		) {
			return;
		}

		$new_data = [];

		foreach ( (array) \wp_unslash( $_POST['better-seo-quick'] ) as $key => $value ) {
			switch ( $key ) {
				case 'doctitle':
					$new_data['_genesis_title'] = $value;
					break;

				case 'description':
				case 'noindex':
				case 'nofollow':
				case 'noarchive':
					$new_data[ "_genesis_{$key}" ] = $value;
					break;

				case 'redirect':
					$new_data[ $key ] = $value;
					break;

				case 'canonical':
					$new_data['_genesis_canonical_uri'] = $value;
					break;
			}
		}

		// Only overwrite provided fields — do not reset all meta.
		$data = array_merge(
			Data\Plugin\Post::get_meta( $post_id ),
			$new_data,
		);

		Data\Plugin\Post::save_meta( $post_id, $data );
	}

	/**
	 * Saves post meta from the bulk edit interface.
	 *
	 * Only overwrites the fields provided — does not reset all meta.
	 * Memoizes the referer check and new data across multiple post IDs.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID being saved.
	 * @return void
	 */
	private static function update_via_bulk_edit( int $post_id ): void {

		$post_id = \get_post( $post_id )->ID ?? null;

		if ( empty( $post_id ) ) {
			return;
		}

		// bulk_edit_posts() already performs these checks — this is an additional guard.
		if ( ! \current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Memoize the referer check — if it passes, we're good for subsequent posts.
		static $verified_referer = false;

		if ( ! $verified_referer ) {
			\check_admin_referer( 'bulk-posts' );

			if (
				! isset( $_REQUEST[ self::SAVE_NONCES['bulk-edit']['name'] ] )
				|| ! \wp_verify_nonce( $_REQUEST[ self::SAVE_NONCES['bulk-edit']['name'] ], self::SAVE_NONCES['bulk-edit']['action'] )
			) {
				return;
			}

			$verified_referer = true;
		}

		// Memoize new_data — same for all posts in the bulk edit.
		static $new_data;

		if ( ! isset( $new_data ) ) {
			$new_data = [];

			foreach ( (array) $_REQUEST['better-seo-bulk'] as $key => $value ) {
				switch ( $key ) {
					case 'noindex':
					case 'nofollow':
					case 'noarchive':
						if ( 'nochange' === $value ) {
							break;
						}
						$new_data[ "_genesis_{$key}" ] = $value;
				}
			}
		}

		$data = array_merge(
			Data\Plugin\Post::get_meta( $post_id ),
			$new_data,
		);

		Data\Plugin\Post::save_meta( $post_id, $data );
	}
}