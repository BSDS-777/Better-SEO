<?php
/**
 * Better SEO - Meta Theme Color
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

use Better_SEO\{
	Data,
	Data\Filter\Sanitize,
};

/**
 * Class Better_SEO\Meta\Theme_Color
 *
 * Provides the theme-color meta tag value for Better SEO,
 * returning the configured hex color with # prefix.
 *
 * @since 1.0.0
 */
class Theme_Color {

	/**
	 * Returns the theme color value for the theme-color meta tag.
	 *
	 * Sanitizes the configured hex color and prepends a # prefix.
	 * Returns empty string if no valid color is configured.
	 *
	 * @since 1.0.0
	 *
	 * @return string The theme color string (e.g. '#1a1a2e'), or empty string if not set.
	 */
	public static function get_theme_color(): string {

		$color = Sanitize::rgb_hex( Data\Plugin::get_option( 'theme_color' ) );

		return $color ? "#{$color}" : '';
	}
}