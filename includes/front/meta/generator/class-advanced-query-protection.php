<?php
/**
 * Better SEO - Front Meta Generator Advanced Query Protection
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

/**
 * Class Better_SEO\Front\Meta\Generator\Advanced_Query_Protection
 *
 * Generates the Better SEO advanced query protection meta tag,
 * used to signal that the current page is protected against query exploitation.
 *
 * @since 1.0.0
 */
final class Advanced_Query_Protection {

	/**
	 * Registered generator callbacks for this pool.
	 *
	 * @since 1.0.0
	 * @var   array<int, array{0: class-string, 1: string}>
	 */
	public const GENERATORS = [
		[ __CLASS__, 'generate_advanced_query_protection' ],
	];

	/**
	 * Generates the advanced query protection meta tag.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the better-seo:aqp meta tag data.
	 */
	public static function generate_advanced_query_protection(): \Generator {
		yield 'better-seo:aqp' => [
			'attributes' => [
				'name'  => 'better-seo:aqp',
				'value' => '1',
			],
		];
	}
}
