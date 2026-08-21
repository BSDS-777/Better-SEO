<?php
/**
 * Better SEO - Helper Format Color
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Helper\Format
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

namespace Better_SEO\Helper\Format;

\defined( 'BETTER_SEO_PRESENT' ) or die;

/**
 * Class Better_SEO\Helper\Format\Color
 *
 * Provides color utility methods for Better SEO admin UI,
 * including relative luminance calculation for accessible font color selection.
 *
 * @since 1.0.0
 */
class Color {

	/**
	 * Returns a relative font color (black or white variant) for the given hex background color.
	 *
	 * Calculates relative luminance using the WCAG formula and returns a
	 * hex color string that provides sufficient contrast against the background.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hex The background hex color (with or without # prefix). Default empty.
	 * @return string The relative font color as a 6-character lowercase hex string.
	 */
	public static function get_relative_fontcolor( string $hex = '' ): string {

		$hex = ltrim( $hex, '#' );

		// Expand 3-character hex to 6-character: rgb → rrggbb.
		[ $r, $g, $b ] = array_map(
			'hexdec',
			str_split(
				\strlen( $hex ) >= 6 ? $hex : "{$hex[0]}{$hex[0]}{$hex[1]}{$hex[1]}{$hex[2]}{$hex[2]}",
				2,
			),
		);

		// Calculate relative luminance per WCAG 2.x specification.
		$get_relative_luminance = static function ( int $v ): float {
			// Convert hex channel (0–255) to 0–1 float.
			$v /= 0xFF;

			return $v > .03928
				? ( ( $v + .055 ) / 1.055 ) ** 2.4
				: $v / 12.92;
		};

		$rl = .2126 * $get_relative_luminance( $r )
			+ .7152 * $get_relative_luminance( $g )
			+ .0722 * $get_relative_luminance( $b );

		$gr = round( $r * .2989 / 8 * $rl );
		$gg = round( $g * .5870 / 8 * $rl );
		$gb = round( $b * .1140 / 8 * $rl );

		// Invert channels for dark backgrounds to ensure contrast.
		if ( $rl < .5 ) {
			$gr ^= 0xFF;
			$gg ^= 0xFF;
			$gb ^= 0xFF;
		}

		return vsprintf( '%02x%02x%02x', [ $gr, $gg, $gb ] );
	}
}