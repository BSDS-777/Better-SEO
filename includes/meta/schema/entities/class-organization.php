<?php
/**
 * Better SEO - Meta Schema Entities Organization
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
 * Class Better_SEO\Meta\Schema\Entities\Organization
 *
 * Generates the Schema.org Organization entity for the site's knowledge graph.
 * Includes name, URL, sameAs social profiles, and logo ImageObject.
 *
 * @since 1.0.0
 */
final class Organization extends Reference {

	/**
	 * The Schema.org @type for this entity.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public static string|array|null $type = 'Organization';

	/**
	 * Builds and returns the Schema.org Organization entity.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args (unused). Default null.
	 * @return array<string, mixed>|null The Organization entity array.
	 */
	public static function build( ?array $args = null ): ?array {

		$entity = [
			'@type' => static::$type,
			'@id'   => static::get_id(),
			'name'  => Sanitize::metadata_content( Data\Plugin::get_option( 'knowledge_name' ) ?: Data\Blog::get_public_blog_name() ),
			'url'   => Meta\URI::get_bare_front_page_url(),
		];

		foreach ( [
			'knowledge_facebook',
			'knowledge_twitter',
			'knowledge_instagram',
			'knowledge_youtube',
			'knowledge_linkedin',
			'knowledge_pinterest',
			'knowledge_soundcloud',
			'knowledge_tumblr',
		] as $option_key ) {
			$option = Data\Plugin::get_option( $option_key );

			if ( $option ) {
				$entity['sameAs'][] = \sanitize_url( $option, [ 'https', 'http' ] );
			}
		}

		$logo = Data\Plugin::get_option( 'knowledge_logo' )
			? current( Meta\Image::get_image_details( [ 'id' => 0 ], true, 'organization' ) )
			: [];

		if ( $logo ) {
			if ( $logo['width'] && $logo['height'] ) {
				// contentUrl and url are intentionally duplicated per Schema.org ImageObject spec.
				$entity['logo'] = [
					'@type'      => 'ImageObject',
					'url'        => $logo['url'],
					'contentUrl' => $logo['url'],
					'width'      => $logo['width'],
					'height'     => $logo['height'],
				];

				if ( $logo['caption'] ) {
					$entity['logo'] += [
						'inLanguage' => Data\Blog::get_language(),
						'caption'    => $logo['caption'],
					];
				}

				if ( $logo['filesize'] ) {
					$entity['logo'] += [ 'contentSize' => (string) $logo['filesize'] ];
				}
			} else {
				$entity['logo'] = $logo['url'];
			}
		}

		return $entity;
	}
}