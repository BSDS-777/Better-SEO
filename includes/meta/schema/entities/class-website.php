<?php
/**
 * Better SEO - Meta Schema Entities WebSite
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

use Better_SEO\{
	Data,
	Data\Filter\Sanitize,
	Meta,
};

/**
 * Class Better_SEO\Meta\Schema\Entities\WebSite
 *
 * Generates the Schema.org WebSite entity for the site's knowledge graph.
 * Includes name, alternate name, description, language, optional Sitelinks
 * Searchbox potentialAction, and publisher reference (Organization or Person).
 *
 * @since 1.0.0
 */
final class WebSite extends Reference {

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
	 * @since 1.0.0
	 * @var   string
	 */
	public static string|array|null $type = 'WebSite';

	/**
	 * Builds and returns the Schema.org WebSite entity.
	 *
	 * Includes optional Sitelinks Searchbox (potentialAction) and publisher
	 * reference (Organization or Person) based on plugin settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args (unused). Default null.
	 * @return array<string, mixed>|null The WebSite entity array.
	 */
	public static function build( ?array $args = null ): ?array {

		$name    = Sanitize::metadata_content( Data\Blog::get_public_blog_name() );
		$altname = Sanitize::metadata_content( Data\Plugin::get_option( 'knowledge_name' ) );

		$entity = [
			'@type'         => static::$type,
			'@id'           => static::get_id(),
			'url'           => Meta\URI::get_bare_front_page_url(),
			'name'          => $name,
			'alternateName' => $name === $altname ? '' : $altname,
			'description'   => Sanitize::metadata_content( Data\Blog::get_filtered_blog_description() ),
			'inLanguage'    => Data\Blog::get_language(),
		];

		if ( Data\Plugin::get_option( 'ld_json_searchbox' ) ) {
			// Not a shared reference — Sitelinks Searchbox unique to WebSite.
			$entity['potentialAction'] = [
				'@type'       => 'SearchAction',
				'target'      => [
					'@type'       => 'EntryPoint',
					'urlTemplate' => str_replace(
						'better_seo_search_term_string',
						'{search_term_string}',
						Meta\URI::get_bare_search_url( 'better_seo_search_term_string' ),
					),
				],
				'query-input' => 'required name=search_term_string',
			];
		}

		if ( Data\Plugin::get_option( 'knowledge_output' ) ) {
			if ( 'organization' === Data\Plugin::get_option( 'knowledge_type' ) ) {
				$entity['publisher'] = &Organization::get_dynamic_ref();
			} else {
				$entity['publisher'] = &Person::get_dynamic_ref();
			}
		}

		return $entity;
	}
}