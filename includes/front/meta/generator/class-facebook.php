<?php
/**
 * Better SEO - Front Meta Generator Facebook
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

use Better_SEO\Meta;

/**
 * Class Better_SEO\Front\Meta\Generator\Facebook
 *
 * Generates Facebook article meta tags including article:author
 * and article:publisher Open Graph properties.
 *
 * @since 1.0.0
 */
final class Facebook {

	/**
	 * Registered generator callbacks for this pool.
	 *
	 * @since 1.0.0
	 * @var   array<int, array{0: class-string, 1: string}>
	 */
	public const GENERATORS = [
		[ __CLASS__, 'generate_article_author' ],
		[ __CLASS__, 'generate_article_publisher' ],
	];

	/**
	 * Generates the article:author Open Graph meta tag.
	 *
	 * Yields nothing if no author URL is available.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the article:author meta tag data.
	 */
	public static function generate_article_author(): \Generator {

		$author = Meta\Facebook::get_author();

		if ( $author ) {
			yield 'article:author' => [
				'attributes' => [
					'property' => 'article:author',
					'content'  => $author,
				],
			];
		}
	}

	/**
	 * Generates the article:publisher Open Graph meta tag.
	 *
	 * Yields nothing if no publisher URL is available.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the article:publisher meta tag data.
	 */
	public static function generate_article_publisher(): \Generator {

		$publisher = Meta\Facebook::get_publisher();

		if ( $publisher ) {
			yield 'article:publisher' => [
				'attributes' => [
					'property' => 'article:publisher',
					'content'  => $publisher,
				],
			];
		}
	}
}
