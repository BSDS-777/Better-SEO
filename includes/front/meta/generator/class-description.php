<?php
/**
 * Better SEO - Front Meta Generator Description
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
 * Class Better_SEO\Front\Meta\Generator\Description
 *
 * Generates the meta description tag for the current page.
 *
 * @since 1.0.0
 */
final class Description {

	/**
	 * Registered generator callbacks for this pool.
	 *
	 * @since 1.0.0
	 * @var   array<int, array{0: class-string, 1: string}>
	 */
	public const GENERATORS = [
		[ __CLASS__, 'generate_description' ],
	];

	/**
	 * Generates the meta description tag.
	 *
	 * Yields nothing if the description is empty.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the description meta tag data.
	 */
	public static function generate_description(): \Generator {

		$description = Meta\Description::get_description();

		if ( \strlen( $description ) ) {
			yield 'description' => [
				'attributes' => [
					'name'    => 'description',
					'content' => $description,
				],
			];
		}
	}
}