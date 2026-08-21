<?php
/**
 * Better SEO - Front Meta Generator Theme Color
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
 * Class Better_SEO\Front\Meta\Generator\Theme_Color
 *
 * Generates the theme-color meta tag for the current page.
 *
 * @since 1.0.0
 */
final class Theme_Color {

	/**
	 * Registered generator callbacks for this pool.
	 *
	 * @since 1.0.0
	 * @var   array<int, array{0: class-string, 1: string}>
	 */
	public const GENERATORS = [
		[ __CLASS__, 'generate_theme_color' ],
	];

	/**
	 * Generates the theme-color meta tag.
	 *
	 * Yields nothing if no theme color is configured.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the theme-color meta tag data.
	 */
	public static function generate_theme_color(): \Generator {

		$theme_color = Meta\Theme_Color::get_theme_color();

		if ( $theme_color ) {
			yield 'theme-color' => [
				'attributes' => [
					'name'    => 'theme-color',
					'content' => $theme_color,
				],
			];
		}
	}
}