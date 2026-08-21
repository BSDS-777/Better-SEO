<?php
/**
 * Better SEO - Helper Format Minify
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
 * Class Better_SEO\Helper\Format\Minify
 *
 * Provides basic JavaScript and CSS minification utilities for Better SEO,
 * removing whitespace and formatting to reduce inline script/style size.
 *
 * @since 1.0.0
 */
class Minify {

	/**
	 * Minifies a JavaScript string by removing whitespace and formatting.
	 *
	 * Uses a static search/replace pair cache for performance across multiple calls.
	 *
	 * @since 1.0.0
	 *
	 * @param string $script The JavaScript string to minify.
	 * @return string The minified JavaScript string.
	 */
	public static function javascript( string $script ): string {

		static $pairs;

		if ( empty( $pairs ) ) {
			$sr = [
				"\r"   => '',
				"\n"   => '',
				"\t"   => '',
				'    ' => ' ',
				'   '  => ' ',
				'  '   => ' ',
				' ? '  => '?',
				' ! '  => '!',
				' :'   => ':',
				': '   => ':',
				' = '  => '=',
				' || ' => '||',
				' && ' => '&&',
				' ?? ' => '??',
				' =+ ' => '=+',
				' )'   => ')',
				') '   => ')',
				' ('   => '(',
				'( '   => '(',
				' {'   => '{',
				'{ '   => '{',
				' }'   => '}',
				'} '   => '}',
				', '   => ',',
				'; '   => ';',
			];

			$pairs = [
				'search'  => array_keys( $sr ),
				'replace' => array_values( $sr ),
			];
		}

		return trim( str_replace( $pairs['search'], $pairs['replace'], $script ) );
	}

	/**
	 * Minifies a CSS string by removing comments and whitespace.
	 *
	 * Strips CSS block comments before applying whitespace reduction.
	 * Uses a static search/replace pair cache for performance across multiple calls.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sheet The CSS string to minify.
	 * @return string The minified CSS string.
	 */
	public static function css( string $sheet ): string {

		static $pairs;

		if ( empty( $pairs ) ) {
			$sr = [
				"\r"   => '',
				"\n"   => '',
				"\t"   => '',
				'    ' => ' ',
				'   '  => ' ',
				'  '   => ' ',
				' :'   => ':',
				': '   => ':',
				' + '  => '+',
				' )'   => ')',
				') '   => ')',
				' ('   => '(',
				'( '   => '(',
				' {'   => '{',
				'{ '   => '{',
				' }'   => '}',
				'} '   => '}',
				', '   => ',',
				'; '   => ';',
			];

			$pairs = [
				'search'  => array_keys( $sr ),
				'replace' => array_values( $sr ),
			];
		}

		return trim( str_replace(
			$pairs['search'],
			$pairs['replace'],
			preg_replace(
				'/(\/\*[\w\W]*?\*\/)/',
				'',
				$sheet,
			),
		) );
	}
}