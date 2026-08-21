<?php
/**
 * Better SEO - Data Admin Term
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
 * Class Better_SEO\Data\Admin\Term
 *
 * Handles saving of Better SEO term meta via term edit and quick edit.
 *
 * @since 1.0.0
 * @access private
 */
final class Term {

	/**
	 * Nonce definitions for term meta save operations.
	 *
	 * @since 1.0.0
	 * @var   array<string, array<string, string>>
	 */
	public const SAVE_NONCES = [
		'term-edit'  => [
			'name'   => 'better_seo_term_nonce_name',
			'action' => 'better_seo_term_nonce_action',
		],
		'quick-edit' => [
			'name'   => 'better_seo_term_nonce_name',
			'action' => 'better_seo_term_nonce_action',
		],
	];

	/**
	 * Routes term meta updates to the appropriate handler based on the edit context.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $term_id  The term ID being saved.
	 * @param int    $tt_id    The term taxonomy ID.
	 * @param string $taxonomy The taxonomy slug.
	 * @return void
	 */
	public static function update_meta( int $term_id, int $tt_id, string $taxonomy ): void {
		// phpcs:disable WordPress.Security.NonceVerification -- Nonce verification is handled in each sub-method.
		if ( ! empty( $_POST['better-seo-quick'] ) ) {
			self::update_via_quick_edit( $term_id, $taxonomy );
		} elseif ( ! empty( $_POST['better-seo-meta'] ) ) {
			self::update_via_term_edit( $term_id, $taxonomy );
		}
		// phpcs:enable WordPress.Security.NonceVerification
	}

	/**
	 * Saves term meta from the quick edit interface.
	 *
	 * Only overwrites the fields provided — does not reset all meta.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $term_id  The term ID being saved.
	 * @param string $taxonomy The taxonomy slug.
	 * @return void
	 */
	private static function update_via_quick_edit( int $term_id, string $taxonomy ): void {

		$term = \get_term( $term_id, $taxonomy );

		// Check term_id directly — more precise than is_wp_error() for this guard.
		if ( empty( $term->term_id ) ) {
			return;
		}

		// Only overwrite provided fields — do not reset all meta.
		$data = array_merge(
			Data\Plugin\Term::get_meta( $term->term_id, false ),
			(array) \wp_unslash( $_POST['better-seo-quick'] ),
		);

		// Trim, sanitize, and save the metadata.
		Data\Plugin\Term::save_meta( $term->term_id, $data );
	}

	/**
	 * Saves term meta from the standard term edit screen.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $term_id  The term ID being saved.
	 * @param string $taxonomy The taxonomy slug.
	 * @return void
	 */
	private static function update_via_term_edit( int $term_id, string $taxonomy ): void {

		$term = \get_term( $term_id, $taxonomy );

		// Check term_id directly — more precise than is_wp_error() for this guard.
		if (
			empty( $term->term_id )
			|| ! \current_user_can( 'edit_term', $term->term_id )
			|| ! isset( $_POST[ self::SAVE_NONCES['term-edit']['name'] ] )
			|| ! \wp_verify_nonce( $_POST[ self::SAVE_NONCES['term-edit']['name'] ], self::SAVE_NONCES['term-edit']['action'] )
		) {
			return;
		}

		// Trim, sanitize, and save the metadata.
		Data\Plugin\Term::save_meta(
			$term->term_id,
			(array) \wp_unslash( $_POST['better-seo-meta'] ),
		);
	}
}