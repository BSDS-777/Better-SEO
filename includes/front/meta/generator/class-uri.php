<?php
/**
 * Better SEO - Front Meta Generator URI
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
 * Class Better_SEO\Front\Meta\Generator\URI
 *
 * Generates URI-related link tags including canonical URL,
 * pagination prev/next links, and shortlink.
 *
 * @since 1.0.0
 */
final class URI {

	/**
	 * Registered generator callbacks for this pool.
	 *
	 * @since 1.0.0
	 * @var   array<int, array{0: class-string, 1: string}>
	 */
	public const GENERATORS = [
		[ __CLASS__, 'generate_canonical_url' ],
		[ __CLASS__, 'generate_pagination_urls' ],
		[ __CLASS__, 'generate_shortlink' ],
	];

	/**
	 * Generates the canonical link tag.
	 *
	 * Yields nothing if no indexable canonical URL is available.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the canonical link tag data.
	 */
	public static function generate_canonical_url(): \Generator {

		$url = Meta\URI::get_indexable_canonical_url();

		if ( $url ) {
			yield 'canonical' => [
				'tag'        => 'link',
				'attributes' => [
					'rel'  => 'canonical',
					'href' => $url,
				],
			];
		}
	}

	/**
	 * Generates the prev and next pagination link tags.
	 *
	 * Yields prev and/or next link tags as available.
	 * Yields nothing if neither URL is available.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields prev and/or next link tag data.
	 */
	public static function generate_pagination_urls(): \Generator {

		[ $prev, $next ] = Meta\URI::get_paged_urls();

		if ( $prev ) {
			yield 'prev' => [
				'tag'        => 'link',
				'attributes' => [
					'rel'  => 'prev',
					'href' => $prev,
				],
			];
		}

		if ( $next ) {
			yield 'next' => [
				'tag'        => 'link',
				'attributes' => [
					'rel'  => 'next',
					'href' => $next,
				],
			];
		}
	}

	/**
	 * Generates the shortlink link tag.
	 *
	 * Yields nothing if no shortlink URL is available.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the shortlink link tag data.
	 */
	public static function generate_shortlink(): \Generator {

		$url = Meta\URI::get_shortlink_url();

		if ( $url ) {
			yield 'shortlink' => [
				'tag'        => 'link',
				'attributes' => [
					'rel'  => 'shortlink',
					'href' => $url,
				],
			];
		}
	}
}