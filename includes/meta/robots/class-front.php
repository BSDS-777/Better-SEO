<?php
/**
 * Better SEO - Meta Robots Front
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

use Better_SEO\Data;
use Better_SEO\Meta\Robots;
use Better_SEO\Helper\{
	Query,
	Taxonomy,
};

/**
 * Class Better_SEO\Meta\Robots\Front
 *
 * Robots directive assertion factory for the current WordPress query context.
 * Used when no explicit generation args are provided — reads from the current
 * query state (is_singular, is_archive, is_search, etc.).
 *
 * @since 1.0.0
 */
final class Front extends Factory {

	/**
	 * Asserts the robots directive for the given type using the current query context.
	 *
	 * Checks meta settings (qubit), global site/post-type/taxonomy settings,
	 * index protection (pagination, password/private, comment pagination),
	 * and query exploitation protection in sequence.
	 * Uses PHP goto for structured label-based flow control.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The robots directive type ('noindex', 'nofollow', 'noarchive').
	 * @return \Generator Yields assertion key => value pairs.
	 */
	protected static function assert_no( string $type ): \Generator {

		$asserting_noindex = 'noindex' === $type;

		meta_settings:
		if ( ! ( static::$options & ROBOTS_IGNORE_SETTINGS ) ) {

			$qubit = null;

			if ( Query::is_editable_term() ) {
				$qubit = (int) Data\Plugin\Term::get_meta_item( $type );
			} elseif ( Query::is_singular() ) {
				$qubit = (int) Data\Plugin\Post::get_meta_item( "_genesis_{$type}" );
			} elseif ( \is_post_type_archive() ) {
				$qubit = (int) Data\Plugin\PTA::get_meta_item( $type );
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

		globals: {
			yield 'globals_site' => (bool) Data\Plugin::get_option( "site_{$type}" );

			if ( Query::is_real_front_page() ) {
				yield 'globals_homepage' => (bool) Data\Plugin::get_option( "homepage_{$type}" );
			} else {
				if ( $asserting_noindex ) {
					yield from static::assert_noindex_query_pass( '404' ); // static: allow overrides.
				}

				if ( Query::is_archive() ) {
					if ( Query::is_author() ) {
						yield 'globals_author' => (bool) Data\Plugin::get_option( "author_{$type}" );
					} elseif ( \is_date() ) {
						yield 'globals_date' => (bool) Data\Plugin::get_option( "date_{$type}" );
					}
				} elseif ( Query::is_search() ) {
					yield 'globals_search' => (bool) Data\Plugin::get_option( "search_{$type}" );
				}
			}

			if ( Query::is_archive() ) {
				if ( Query::is_category() || Query::is_tag() || Query::is_tax() ) {
					yield 'globals_taxonomy' => Robots::is_taxonomy_robots_set( $type, Query::get_current_taxonomy() );

					foreach ( Taxonomy::get_post_types() as $post_type ) {
						$_is_post_type_robots_set[] = Robots::is_post_type_robots_set( $type, $post_type );
					}

					yield 'globals_post_type_all' => isset( $_is_post_type_robots_set ) && ! \in_array( false, $_is_post_type_robots_set, true );
				} elseif ( \is_post_type_archive() ) {
					yield 'globals_post_type' => Robots::is_post_type_robots_set( $type, Query::get_current_post_type() );
				}
			} elseif ( Query::is_singular() ) {
				yield 'globals_post_type' => Robots::is_post_type_robots_set( $type, Query::get_current_post_type() );
			}
		}

		index_protection:
		if ( $asserting_noindex && ! ( static::$options & ROBOTS_IGNORE_PROTECTION ) ) {
			if ( Query::is_real_front_page() ) {
				yield from static::assert_noindex_query_pass( 'paged_home' );
			} elseif ( Query::is_archive() || Query::is_singular_archive() ) {
				yield from static::assert_noindex_query_pass( 'paged' );
			}

			if ( Query::is_singular() ) {
				yield from static::assert_noindex_query_pass( 'protected' );

				if ( Query::is_comment_paged() ) {
					yield from static::assert_noindex_query_pass( 'cpage' );
				}
			}
		}

		exploit_protection:
		if ( Query\Utils::is_query_exploited() ) {
			if ( \in_array( $type, [ 'noindex', 'nofollow' ], true ) ) {
				yield 'query_protection' => true;
			}
		}

		end:;
	}

	/**
	 * Asserts a specific noindex query pass for the given pass type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $pass The pass type: 'paged_home', '404', 'paged', 'protected', or 'cpage'.
	 * @return \Generator Yields the pass key => bool result.
	 */
	private static function assert_noindex_query_pass( string $pass ): \Generator {

		switch ( $pass ) {
			case 'paged_home':
				yield 'paged_home' =>
					Data\Plugin::get_option( 'home_paged_noindex' ) && ( Query::page() > 1 || Query::paged() > 1 );
				break;

			case '404':
				if ( Query::is_singular_archive() ) {
					yield '404' => \is_404();
				} else {
					if ( $GLOBALS['wp_query']->post_count ?? true ) {
						yield '404' => false;
					} else {
						/**
						 * Filters whether Better SEO should noindex archives with no posts.
						 *
						 * @since 1.0.0
						 * @param bool $enable Whether to noindex empty archives. Default true.
						 */
						yield '404' => (bool) \apply_filters( 'better_seo_enable_noindex_no_posts', true );
					}
				}
				break;

			case 'paged':
				yield 'paged' => Data\Plugin::get_option( 'paged_noindex' ) && Query::paged() > 1;
				break;

			case 'protected':
				yield 'protected' => Data\Post::is_protected( Query::get_the_real_id() );
				break;

			case 'cpage':
				/**
				 * Filters whether Better SEO should noindex comment pagination pages.
				 *
				 * @since 1.0.0
				 * @param bool $enable Whether to noindex comment pagination. Default true.
				 */
				yield 'cpage' => \apply_filters( 'better_seo_enable_noindex_comment_pagination', true );
		}
	}
}