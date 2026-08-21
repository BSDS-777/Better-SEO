<?php
/**
 * Better SEO - Meta Robots Args
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Meta\Robots
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

namespace Better_SEO\Meta\Robots;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use const Better_SEO\{
	ROBOTS_IGNORE_SETTINGS,
	ROBOTS_IGNORE_PROTECTION,
};

use function Better_SEO\get_query_type_from_args;

use Better_SEO\{
	Data,
	Meta\Robots,
	Helper\Query,
	Helper\Taxonomy,
};

/**
 * Class Better_SEO\Meta\Robots\Args
 *
 * Robots directive assertion factory for generation-args-based queries.
 * Used when explicit generation args (id, tax, pta, uid) are provided
 * rather than relying on the current WordPress query state.
 *
 * @since 1.0.0
 */
final class Args extends Factory {

	/**
	 * Asserts the robots directive for the given type using generation args.
	 *
	 * Checks meta settings (qubit), global site/post-type/taxonomy settings,
	 * and index protection (password/private) in sequence.
	 * Uses PHP goto for structured label-based flow control.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The robots directive type ('noindex', 'nofollow', 'noarchive').
	 * @return \Generator Yields assertion key => value pairs.
	 */
	protected static function assert_no( string $type ): \Generator {

		$args = static::$args; // static: allow overrides.

		$asserting_noindex = 'noindex' === $type;
		$query_type        = get_query_type_from_args( $args );

		meta_settings:
		if ( ! ( static::$options & ROBOTS_IGNORE_SETTINGS ) ) {
			$qubit = null;

			switch ( $query_type ) {
				case 'single':
					$qubit = (int) Data\Plugin\Post::get_meta_item( "_genesis_{$type}", $args['id'] );
					break;
				case 'term':
					$qubit = (int) Data\Plugin\Term::get_meta_item( $type, $args['id'] );
					break;
				case 'pta':
					$qubit = (int) Data\Plugin\PTA::get_meta_item( $type, $args['pta'] );
			}

			switch ( isset( $qubit ) ) {
				case false:
					// No qubit set — continue to globals.
					break;
				case $qubit < -.3333:
					// Force-disabled (qubit < -1/3) — yield false and skip to protection check.
					yield 'meta_qubit_force' => false;
					goto index_protection;
				case $qubit > .3334:
					// Force-enabled (qubit > 1/3) — yield true and halt.
					yield 'meta_qubit_force' => true;
					// Fall through to default.
				default:
					// Default state — yield false and continue.
					yield 'meta_qubit_default' => false;
			}
		}

		globals:
		yield 'globals_site' => (bool) Data\Plugin::get_option( "site_{$type}" );

		switch ( $query_type ) {
			case 'single':
			case 'homeblog':
				if ( Query::is_real_front_page_by_id( $args['id'] ) ) {
					yield 'globals_homepage' => (bool) Data\Plugin::get_option( "homepage_{$type}" );
				}

				if ( $args['id'] ) {
					yield 'globals_post_type' => Robots::is_post_type_robots_set(
						$type,
						\get_post_type( $args['id'] ),
					);
				}
				break;

			case 'term':
				if ( $asserting_noindex ) {
					yield from static::assert_noindex_query_pass( '404' );
				}

				yield 'globals_taxonomy' => Robots::is_taxonomy_robots_set( $type, $args['tax'] );

				foreach ( Taxonomy::get_post_types( $args['tax'] ) as $post_type ) {
					$_is_pt_robots_set[] = Robots::is_post_type_robots_set( $type, $post_type );
				}

				yield 'globals_post_type_all' => isset( $_is_pt_robots_set ) && ! \in_array( false, $_is_pt_robots_set, true );
				break;

			case 'pta':
				yield 'globals_post_type' => Robots::is_post_type_robots_set( $type, $args['pta'] );
		}

		index_protection:
		if ( $asserting_noindex && ! ( static::$options & ROBOTS_IGNORE_PROTECTION ) ) {
			if ( 'single' === $query_type ) {
				yield from static::assert_noindex_query_pass( 'protected' ); // static: allow overrides.
			}
		}

		end:;
	}

	/**
	 * Asserts a specific noindex query pass for the given pass type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $pass The pass type: '404' or 'protected'.
	 * @return \Generator Yields the pass key => bool result.
	 */
	private static function assert_noindex_query_pass( string $pass ): \Generator {

		$args = static::$args; // static: allow overrides.

		switch ( $pass ) {
			case '404':
				yield '404' => ! Data\Term::is_term_populated( $args['id'], $args['tax'] );
				break;

			case 'protected':
				yield 'protected' => Data\Post::is_protected( $args['id'] );
		}
	}
}