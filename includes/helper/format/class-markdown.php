<?php
/**
 * Better SEO - Helper Format Markdown
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
 * Class Better_SEO\Helper\Format\Markdown
 *
 * Provides a lightweight Markdown-to-HTML converter for Better SEO admin notices
 * and descriptions, supporting bold, italic, code, headings, and links.
 *
 * @since 1.0.0
 */
class Markdown {

	/**
	 * Converts a subset of Markdown syntax to HTML.
	 *
	 * Supported conversions: **, *, `, [](), =–====== (h1–h6).
	 * Pass an array of HTML tag names to $convert to limit which conversions apply.
	 *
	 * @since 1.0.0
	 *
	 * @param string             $text    The Markdown text to convert.
	 * @param array<int, string> $convert Optional list of HTML tags to convert. Default all.
	 * @param array<string, mixed> $args  Optional arguments (a_internal: bool). Default [].
	 * @return string The converted HTML string, or empty string if too short.
	 */
	public static function convert( string $text, array $convert = [], array $args = [] ): string {

		$text = trim( str_replace( [ "\r\n", "\r", "\t" ], [ "\n", "\n", ' ' ], $text ) );

		if ( \strlen( $text ) < 3 ) {
			return '';
		}

		$args += [ 'a_internal' => false ];

		$conversions = [
			'**'     => 'strong',
			'*'      => 'em',
			'`'      => 'code',
			'[]()'   => 'a',
			'======' => 'h6',
			'====='  => 'h5',
			'===='   => 'h4',
			'==='    => 'h3',
			'=='     => 'h2',
			'='      => 'h1',
		];

		$md_types = empty( $convert ) ? $conversions : array_intersect( $conversions, $convert );

		if ( isset( $md_types['*'], $md_types['**'] ) ) {
			$text = self::strong_em( $text );
		}

		foreach ( $md_types as $type ) {
			switch ( $type ) {
				case 'strong':
					$text = self::strong( $text );
					break;

				case 'em':
					$text = self::em( $text );
					break;

				case 'code':
					$text = self::code( $text );
					break;

				case 'h6':
				case 'h5':
				case 'h4':
				case 'h3':
				case 'h2':
				case 'h1':
					$text = self::h123456( $text, $type );
					break;

				case 'a':
					$text = self::a( $text, $args['a_internal'] );
			}
		}

		return $text;
	}

	/**
	 * Converts ***text*** to <strong><em>text</em></strong>.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The text to process.
	 * @return string The processed text.
	 */
	private static function strong_em( string $text ): string {

		preg_match_all( '/\*{3}([^\*]+)\*{3}/', $text, $matches, \PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$text = str_replace(
				$match[0],
				\sprintf( '<strong><em>%s</em></strong>', \esc_html( $match[1] ) ),
				$text,
			);
		}

		return $text;
	}

	/**
	 * Converts **text** to <strong>text</strong>.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The text to process.
	 * @return string The processed text.
	 */
	private static function strong( string $text ): string {

		preg_match_all( '/\*{2}(.+?)\*{2}/', $text, $matches, \PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$text = str_replace(
				$match[0],
				\sprintf( '<strong>%s</strong>', \esc_html( $match[1] ) ),
				$text,
			);
		}

		return $text;
	}

	/**
	 * Converts *text* to <em>text</em>.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The text to process.
	 * @return string The processed text.
	 */
	private static function em( string $text ): string {

		preg_match_all( '/\*([^\*]+)\*/', $text, $matches, \PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$text = str_replace(
				$match[0],
				\sprintf( '<em>%s</em>', \esc_html( $match[1] ) ),
				$text,
			);
		}

		return $text;
	}

	/**
	 * Converts `text` to <code>text</code>.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The text to process.
	 * @return string The processed text.
	 */
	private static function code( string $text ): string {

		preg_match_all( '/`([^`]+)`/', $text, $matches, \PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$text = str_replace(
				$match[0],
				\sprintf( '<code>%s</code>', \esc_html( $match[1] ) ),
				$text,
			);
		}

		return $text;
	}

	/**
	 * Converts =text= through ======text====== to h1–h6 heading elements.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The text to process.
	 * @param string $type The heading tag (h1–h6). Default 'h1'.
	 * @return string The processed text.
	 */
	private static function h123456( string $text, string $type = 'h1' ): string {

		preg_match_all(
			\sprintf(
				'/\={%1$d}\s(.+)\s\={%1$d}/',
				filter_var( $type, \FILTER_SANITIZE_NUMBER_INT ),
			),
			$text,
			$matches,
			\PREG_SET_ORDER,
		);

		$type = \esc_attr( $type );

		foreach ( $matches as $match ) {
			$text = str_replace(
				$match[0],
				\sprintf( '<%1$s>%2$s</%1$s>', $type, \esc_html( $match[1] ) ),
				$text,
			);
		}

		return $text;
	}

	/**
	 * Converts [text](url) to anchor elements.
	 *
	 * External links (internal=false) include target="_blank" and rel attributes.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text     The text to process.
	 * @param bool   $internal Whether to render as an internal link. Default true.
	 * @return string The processed text.
	 */
	private static function a( string $text, bool $internal = true ): string {

		preg_match_all( '/\[([^\[\]]+)]\(((?:[^()\s]|\((?2)\))*)\)/', $text, $matches, \PREG_SET_ORDER );

		$format = $internal
			? '<a href="%s">%s</a>'
			: '<a href="%s" target="_blank" rel="nofollow noreferrer noopener">%s</a>';

		foreach ( $matches as $match ) {
			$text = str_replace(
				$match[0],
				\sprintf( $format, \esc_url( $match[2], [ 'https', 'http' ] ), \esc_html( $match[1] ) ),
				$text,
			);
		}

		return $text;
	}
}