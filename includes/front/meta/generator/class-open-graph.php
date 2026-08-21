<?php
/**
 * Better SEO - Front Meta Generator Open Graph
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Front\Meta\Generator
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

namespace Better_SEO\Front\Meta\Generator;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\{
	Data,
	Meta,
};

/**
 * Class Better_SEO\Front\Meta\Generator\Open_Graph
 *
 * Generates Open Graph meta tags including type, locale, site name,
 * title, description, URL, images, and article timestamps.
 *
 * @since 1.0.0
 */
final class Open_Graph {

	/**
	 * Registered generator callbacks for this pool.
	 *
	 * @since 1.0.0
	 * @var   array<int, array{0: class-string, 1: string}>
	 */
	public const GENERATORS = [
		[ __CLASS__, 'generate_open_graph_type' ],
		[ __CLASS__, 'generate_open_graph_locale' ],
		[ __CLASS__, 'generate_open_graph_site_name' ],
		[ __CLASS__, 'generate_open_graph_title' ],
		[ __CLASS__, 'generate_open_graph_description' ],
		[ __CLASS__, 'generate_open_graph_url' ],
		[ __CLASS__, 'generate_open_graph_image' ],
		[ __CLASS__, 'generate_article_published_time' ],
		[ __CLASS__, 'generate_article_modified_time' ],
	];

	/**
	 * Generates the og:type Open Graph meta tag.
	 *
	 * Yields nothing if no type is available.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the og:type meta tag data.
	 */
	public static function generate_open_graph_type(): \Generator {

		$type = Meta\Open_Graph::get_type();

		if ( $type ) {
			yield 'og:type' => [
				'attributes' => [
					'property' => 'og:type',
					'content'  => $type,
				],
			];
		}
	}

	/**
	 * Generates the og:locale Open Graph meta tag.
	 *
	 * Yields nothing if no locale is available.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the og:locale meta tag data.
	 */
	public static function generate_open_graph_locale(): \Generator {

		$locale = Meta\Open_Graph::get_locale();

		if ( $locale ) {
			yield 'og:locale' => [
				'attributes' => [
					'property' => 'og:locale',
					'content'  => $locale,
				],
			];
		}
	}

	/**
	 * Generates the og:site_name Open Graph meta tag.
	 *
	 * Yields nothing if no site name is available.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the og:site_name meta tag data.
	 */
	public static function generate_open_graph_site_name(): \Generator {

		$sitename = Meta\Open_Graph::get_site_name();

		if ( $sitename ) {
			yield 'og:site_name' => [
				'attributes' => [
					'property' => 'og:site_name',
					'content'  => $sitename,
				],
			];
		}
	}

	/**
	 * Generates the og:title Open Graph meta tag.
	 *
	 * Yields nothing if the title is empty.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the og:title meta tag data.
	 */
	public static function generate_open_graph_title(): \Generator {

		$title = Meta\Open_Graph::get_title();

		if ( \strlen( $title ) ) {
			yield 'og:title' => [
				'attributes' => [
					'property' => 'og:title',
					'content'  => $title,
				],
			];
		}
	}

	/**
	 * Generates the og:description Open Graph meta tag.
	 *
	 * Yields nothing if the description is empty.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the og:description meta tag data.
	 */
	public static function generate_open_graph_description(): \Generator {

		$description = Meta\Open_Graph::get_description();

		if ( \strlen( $description ) ) {
			yield 'og:description' => [
				'attributes' => [
					'property' => 'og:description',
					'content'  => $description,
				],
			];
		}
	}

	/**
	 * Generates the og:url Open Graph meta tag.
	 *
	 * Yields nothing if no URL is available.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the og:url meta tag data.
	 */
	public static function generate_open_graph_url(): \Generator {

		$url = Meta\Open_Graph::get_url();

		if ( $url ) {
			yield 'og:url' => [
				'attributes' => [
					'property' => 'og:url',
					'content'  => $url,
				],
			];
		}
	}

	/**
	 * Generates og:image and related Open Graph image meta tags.
	 *
	 * Yields og:image, og:image:width, og:image:height, and og:image:alt
	 * for each available image. Respects the multi_og_image plugin option.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields og:image meta tag data entries.
	 */
	public static function generate_open_graph_image(): \Generator {

		$i = 0;

		foreach ( Meta\Image::get_image_details(
			null,
			! Data\Plugin::get_option( 'multi_og_image' ),
		) as $image ) {
			yield "og:image:{$i}" => [
				'attributes' => [
					'property' => 'og:image',
					'content'  => $image['url'],
				],
			];

			if ( $image['height'] && $image['width'] ) {
				yield "og:image:width:{$i}" => [
					'attributes' => [
						'property' => 'og:image:width',
						'content'  => $image['width'],
					],
				];
				yield "og:image:height:{$i}" => [
					'attributes' => [
						'property' => 'og:image:height',
						'content'  => $image['height'],
					],
				];
			}

			if ( $image['alt'] ) {
				yield "og:image:alt:{$i}" => [
					'attributes' => [
						'property' => 'og:image:alt',
						'content'  => $image['alt'],
					],
				];
			}

			++$i;
		}
	}

	/**
	 * Generates the article:published_time Open Graph meta tag.
	 *
	 * Yields nothing if no published time is available.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the article:published_time meta tag data.
	 */
	public static function generate_article_published_time(): \Generator {

		$time = Meta\Open_Graph::get_article_published_time();

		if ( $time ) {
			yield 'article:published_time' => [
				'attributes' => [
					'property' => 'article:published_time',
					'content'  => $time,
				],
			];
		}
	}

	/**
	 * Generates the article:modified_time Open Graph meta tag.
	 *
	 * Yields nothing if no modified time is available.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the article:modified_time meta tag data.
	 */
	public static function generate_article_modified_time(): \Generator {

		$time = Meta\Open_Graph::get_article_modified_time();

		if ( $time ) {
			yield 'article:modified_time' => [
				'attributes' => [
					'property' => 'article:modified_time',
					'content'  => $time,
				],
			];
		}
	}
}