<?php
/**
 * Better SEO - Meta Breadcrumbs
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Meta
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

namespace Better_SEO\Meta;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\{
	get_query_type_from_args,
	memo,
	normalize_generation_args,
};

use Better_SEO\{
	Data,
	Helper\Query,
	Helper\Taxonomy,
	Meta,
};

/**
 * Class Better_SEO\Meta\Breadcrumbs
 *
 * Generates structured breadcrumb lists for Better SEO Schema.org output,
 * supporting singular posts, terms, PTAs, authors, dates, search, and 404 pages.
 *
 * @since 1.0.0
 */
class Breadcrumbs {

	/**
	 * Current breadcrumb generation options.
	 *
	 * @since 1.0.0
	 * @var   array<string, mixed>
	 */
	private static array $options = [];

	/**
	 * Returns the breadcrumb list for the given args or current query context.
	 *
	 * Applies the better_seo_breadcrumb_list filter.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args    Optional generation args. Default null (current query).
	 * @param array<string, mixed>      $options Optional breadcrumb options. Default [].
	 * @return array<int, array<string, string>> The breadcrumb list.
	 */
	public static function get_breadcrumb_list( ?array $args = null, array $options = [] ): array {

		self::$options = array_merge(
			[
				'use_meta_title' => (bool) Data\Plugin::get_option( 'breadcrumb_use_meta_title' ),
			],
			array_filter(
				$options,
				static fn( mixed $v ): bool => null !== $v,
			),
		);

		ksort( self::$options );

		if ( isset( $args ) ) {
			normalize_generation_args( $args );
			$list = self::get_breadcrumb_list_from_args( $args );
		} else {
			$list = memo( null, self::$options ) ?? memo( self::get_breadcrumb_list_from_query(), self::$options );
		}

		/**
		 * Filters the Better SEO breadcrumb list.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, array<string, string>> $list    The breadcrumb list.
		 * @param array<string, mixed>|null         $args    The generation args, or null for current query.
		 * @param array<string, mixed>              $options The breadcrumb options.
		 */
		return (array) \apply_filters(
			'better_seo_breadcrumb_list',
			$list,
			$args,
			self::$options,
		);
	}

	/**
	 * Returns the breadcrumb title for the given args or current query context.
	 *
	 * Uses meta title if the use_meta_title option is enabled, otherwise
	 * falls back to the generated title.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The breadcrumb title.
	 */
	private static function get_breadcrumb_title( ?array $args = null ): string {

		if ( self::$options['use_meta_title'] ) {
			return Meta\Title::get_bare_title( $args );
		}

		return Meta\Title::get_bare_generated_title( $args );
	}

	/**
	 * Returns the breadcrumb list for the current query context.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, string>> The breadcrumb list.
	 */
	private static function get_breadcrumb_list_from_query(): array {

		if ( Query::is_real_front_page() ) {
			$list = self::get_front_page_breadcrumb_list();
		} elseif ( Query::is_singular() ) {
			$list = self::get_singular_breadcrumb_list();
		} elseif ( Query::is_archive() ) {
			if ( Query::is_editable_term() ) {
				$list = self::get_term_breadcrumb_list();
			} elseif ( \is_post_type_archive() ) {
				$list = self::get_pta_breadcrumb_list();
			} elseif ( Query::is_author() ) {
				$list = self::get_author_breadcrumb_list();
			} elseif ( \is_date() ) {
				$list = self::get_date_breadcrumb_list();
			}
		} elseif ( Query::is_search() ) {
			$list = self::get_search_breadcrumb_list();
		} elseif ( \is_404() ) {
			$list = self::get_404_breadcrumb_list();
		}

		return $list ?? [];
	}

	/**
	 * Returns the breadcrumb list for the given generation args.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args The generation args.
	 * @return array<int, array<string, string>> The breadcrumb list.
	 */
	private static function get_breadcrumb_list_from_args( array $args ): array {

		return match ( get_query_type_from_args( $args ) ) {
			'single'   => Query::is_static_front_page( $args['id'] )
				? self::get_front_page_breadcrumb_list()
				: self::get_singular_breadcrumb_list( $args['id'] ),
			'term'     => self::get_term_breadcrumb_list( $args['id'], $args['tax'] ),
			'homeblog' => self::get_front_page_breadcrumb_list(),
			'pta'      => self::get_pta_breadcrumb_list( $args['pta'] ),
			'user'     => self::get_author_breadcrumb_list( $args['uid'] ),
			default    => [],
		};
	}

	/**
	 * Returns the front page breadcrumb list.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, string>> The front page breadcrumb list.
	 */
	private static function get_front_page_breadcrumb_list(): array {
		return [ self::get_front_breadcrumb() ];
	}

	/**
	 * Returns the singular post breadcrumb list.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $id Optional post ID. Default null (current post).
	 * @return array<int, array<string, string>> The singular breadcrumb list.
	 */
	private static function get_singular_breadcrumb_list( ?int $id = null ): array {

		$post = \get_post( $id ?? Query::get_the_real_id() );

		if ( empty( $post ) ) {
			return [];
		}

		$crumbs    = [];
		$post_type = \get_post_type( $post );

		if ( \get_post_type_object( $post_type )->has_archive ?? false ) {
			$crumbs[] = [
				'url'  => Meta\URI::get_bare_pta_url( $post_type ),
				'name' => self::get_breadcrumb_title( [ 'pta' => $post_type ] ),
			];
		}

		$taxonomies      = array_keys( array_filter(
			Taxonomy::get_hierarchical( 'objects', $post_type ),
			'is_taxonomy_viewable',
		) );
		$taxonomy        = reset( $taxonomies );
		$primary_term_id = $taxonomy ? Data\Plugin\Post::get_primary_term_id( $post->ID, $taxonomy ) : 0;

		if ( $primary_term_id ) {
			foreach ( Data\Term::get_term_parents(
				$primary_term_id,
				$taxonomy,
				true, // Include self.
			) as $parent ) {
				$crumbs[] = [
					'url'  => Meta\URI::get_bare_term_url( $parent->term_id, $parent->taxonomy ),
					'name' => self::get_breadcrumb_title( [
						'id'  => $parent->term_id,
						'tax' => $parent->taxonomy,
					] ),
				];
			}
		}

		foreach ( Data\Post::get_post_parents( $post->ID ) as $parent ) {
			$crumbs[] = [
				'url'  => Meta\URI::get_bare_singular_url( $parent->ID ),
				'name' => self::get_breadcrumb_title( [ 'id' => $parent->ID ] ),
			];
		}

		if ( isset( $id ) ) {
			$crumbs[] = [
				'url'  => Meta\URI::get_bare_singular_url( $post->ID ),
				'name' => self::get_breadcrumb_title( [ 'id' => $post->ID ] ),
			];
		} else {
			$crumbs[] = [
				'url'  => Meta\URI::get_bare_singular_url(),
				'name' => self::get_breadcrumb_title(),
			];
		}

		return [
			self::get_front_breadcrumb(),
			...$crumbs,
		];
	}

	/**
	 * Returns the taxonomy term breadcrumb list.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null    $term_id  Optional term ID. Default null (current term).
	 * @param string      $taxonomy Optional taxonomy slug. Default empty.
	 * @return array<int, array<string, string>> The term breadcrumb list.
	 */
	private static function get_term_breadcrumb_list( ?int $term_id = null, string $taxonomy = '' ): array {

		$crumbs = [];

		if ( isset( $term_id ) ) {
			$taxonomy = $taxonomy ?: ( \get_term( $term_id )->taxonomy ?? '' );
		} else {
			$taxonomy = Query::get_current_taxonomy();
		}

		foreach ( Data\Term::get_term_parents(
			$term_id,
			$taxonomy,
			false, // Exclude self — added separately below.
		) as $parent ) {
			$crumbs[] = [
				'url'  => Meta\URI::get_bare_term_url( $parent->term_id, $parent->taxonomy ),
				'name' => self::get_breadcrumb_title( [
					'id'  => $parent->term_id,
					'tax' => $parent->taxonomy,
				] ),
			];
		}

		if ( isset( $term_id ) ) {
			$crumbs[] = [
				'url'  => Meta\URI::get_bare_term_url( $term_id, $taxonomy ),
				'name' => self::get_breadcrumb_title( [
					'id'  => $term_id,
					'tax' => $taxonomy,
				] ),
			];
		} else {
			$crumbs[] = [
				'url'  => Meta\URI::get_bare_term_url(),
				'name' => self::get_breadcrumb_title(),
			];
		}

		return [
			self::get_front_breadcrumb(),
			...$crumbs,
		];
	}

	/**
	 * Returns the post type archive breadcrumb list.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $post_type Optional post type slug. Default null (current PTA).
	 * @return array<int, array<string, string>> The PTA breadcrumb list.
	 */
	private static function get_pta_breadcrumb_list( ?string $post_type = null ): array {

		$crumbs = [];

		if ( isset( $post_type ) ) {
			$crumbs[] = [
				'url'  => Meta\URI::get_pta_url( $post_type ),
				'name' => self::get_breadcrumb_title( [ 'pta' => $post_type ] ),
			];
		} else {
			$crumbs[] = [
				'url'  => Meta\URI::get_bare_pta_url(),
				'name' => self::get_breadcrumb_title(),
			];
		}

		return [
			self::get_front_breadcrumb(),
			...$crumbs,
		];
	}

	/**
	 * Returns the author archive breadcrumb list.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $id Optional author user ID. Default null (current author).
	 * @return array<int, array<string, string>> The author breadcrumb list.
	 */
	private static function get_author_breadcrumb_list( ?int $id = null ): array {

		$crumbs = [];

		if ( isset( $id ) ) {
			$crumbs[] = [
				'url'  => Meta\URI::get_author_url( $id ),
				'name' => self::get_breadcrumb_title( [ 'uid' => $id ] ),
			];
		} else {
			$crumbs[] = [
				'url'  => Meta\URI::get_bare_author_url(),
				'name' => self::get_breadcrumb_title(),
			];
		}

		return [
			self::get_front_breadcrumb(),
			...$crumbs,
		];
	}

	/**
	 * Returns the date archive breadcrumb list.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, string>> The date breadcrumb list.
	 */
	private static function get_date_breadcrumb_list(): array {
		return [
			self::get_front_breadcrumb(),
			[
				'url'  => Meta\URI::get_bare_date_url(
					\get_query_var( 'year' ),
					\get_query_var( 'monthnum' ),
					\get_query_var( 'day' ),
				),
				'name' => Meta\Title::get_bare_generated_title(),
			],
		];
	}

	/**
	 * Returns the search results breadcrumb list.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, string>> The search breadcrumb list.
	 */
	private static function get_search_breadcrumb_list(): array {
		return [
			self::get_front_breadcrumb(),
			[
				'url'  => Meta\URI::get_search_url(),
				'name' => Meta\Title::get_search_query_title(),
			],
		];
	}

	/**
	 * Returns the 404 page breadcrumb list.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, string>> The 404 breadcrumb list.
	 */
	private static function get_404_breadcrumb_list(): array {
		return [
			self::get_front_breadcrumb(),
			[
				'url'  => '',
				'name' => Meta\Title::get_404_title(),
			],
		];
	}

	/**
	 * Returns the front page breadcrumb entry.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> The front page breadcrumb entry with url and name.
	 */
	private static function get_front_breadcrumb(): array {
		return [
			'url'  => Meta\URI::get_bare_front_page_url(),
			'name' => Meta\Title::get_front_page_title(),
		];
	}
}