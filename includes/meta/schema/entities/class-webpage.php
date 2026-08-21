<?php
/**
 * Better SEO - Meta Schema Entities WebPage
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Meta\Schema\Entities
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

namespace Better_SEO\Meta\Schema\Entities;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\{
	get_query_type_from_args,
	normalize_generation_args,
};

use Better_SEO\{
	Data,
	Meta,
	Helper\Query,
};

/**
 * Class Better_SEO\Meta\Schema\Entities\WebPage
 *
 * Generates the Schema.org WebPage entity for the current page.
 * Dynamically sets the @type to CollectionPage, SearchResultsPage, or WebPage
 * based on the current query context. Includes breadcrumbs, author, dates,
 * and ReadAction potentialAction for singular posts.
 *
 * @since 1.0.0
 */
final class WebPage extends Reference {

	/**
	 * Registered entity builders for this entity type.
	 *
	 * @since 1.0.0
	 * @var   array<int, array{0: class-string, 1: string}>
	 */
	public const BUILDERS = [
		[ __CLASS__, 'build' ],
	];

	/**
	 * The Schema.org @type for this entity.
	 *
	 * May be dynamically changed to 'CollectionPage' or ['CollectionPage', 'SearchResultsPage'].
	 *
	 * @since 1.0.0
	 * @var   string|array<int, string>|null
	 */
	public static string|array|null $type = 'WebPage';

	/**
	 * Returns the Schema.org @id for the WebPage entity.
	 *
	 * Uses the canonical URL as the @id.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The WebPage entity @id string.
	 */
	public static function get_id( ?array $args = null ): string {
		return Meta\URI::get_canonical_url( $args );
	}

	/**
	 * Builds and returns the Schema.org WebPage entity for the current page.
	 *
	 * Dynamically adjusts @type based on query context (CollectionPage for archives,
	 * SearchResultsPage for search). Includes breadcrumbs, author, dates, and
	 * ReadAction for singular posts.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return array<string, mixed>|null The WebPage entity array.
	 */
	public static function build( ?array $args = null ): ?array {

		// Reset type to default before building — may be changed below.
		static::$type = 'WebPage';

		$entity = [
			'@type'       => &static::$type,
			'@id'         => static::get_id( $args ),
			'url'         => Meta\URI::get_canonical_url( $args ),
			'name'        => Meta\Title::get_title( $args ),
			'description' => Meta\Description::get_description( $args ),
			'inLanguage'  => Data\Blog::get_language(),
			'isPartOf'    => WebSite::get_instant_ref(),
		];

		if ( Data\Plugin::get_option( 'ld_json_breadcrumbs' ) ) {
			$entity['breadcrumb'] = &BreadcrumbList::get_dynamic_ref( $args );
		}

		if ( isset( $args ) ) {
			normalize_generation_args( $args );

			switch ( get_query_type_from_args( $args ) ) {
				case 'single':
					$entity['potentialAction'] = [
						'@type'  => 'ReadAction',
						'target' => Meta\URI::get_canonical_url( $args ),
					];

					if ( Data\Plugin::get_option( 'knowledge_output' ) && Query::is_static_front_page( $args['id'] ) ) {
						$entity['about'] = &Organization::get_dynamic_ref();
					}

					if ( Query::is_single( $args['id'] ) ) {
						$entity['datePublished'] = Data\Post::get_published_time( $args['id'] );
						$entity['dateModified']  = Data\Post::get_modified_time( $args['id'] );
						$entity['author']        = &Author::get_dynamic_ref( [
							'uid' => Query::get_post_author_id( $args['id'] ),
						] );
					}

					if ( Query::is_singular_archive( $args['id'] ) ) {
						static::$type = 'CollectionPage';
					}
					break;

				case 'term':
					static::$type = 'CollectionPage';
			}
		} else {
			if ( Query::is_singular() ) {
				$entity['potentialAction'] = [
					'@type'  => 'ReadAction',
					'target' => Meta\URI::get_canonical_url(),
				];

				if ( Query::is_single() ) {
					$entity['datePublished'] = Data\Post::get_published_time();
					$entity['dateModified']  = Data\Post::get_modified_time();
					$entity['author']        = &Author::get_dynamic_ref();
				}
			}

			if ( Data\Plugin::get_option( 'knowledge_output' ) && Query::is_real_front_page() ) {
				$entity['about'] = &Organization::get_dynamic_ref();
			}

			if ( Query::is_archive() || Query::is_singular_archive() ) {
				static::$type = 'CollectionPage';
			} elseif ( Query::is_search() ) {
				static::$type = [ 'CollectionPage', 'SearchResultsPage' ];
			}
		}

		return $entity;
	}
}