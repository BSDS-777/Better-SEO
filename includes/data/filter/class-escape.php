<?php
/**
 * Better SEO - Data Filter Escape
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Data\Filter
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

namespace Better_SEO\Data\Filter;

\defined( 'BETTER_SEO_PRESENT' ) or die;

/**
 * Class Better_SEO\Data\Filter\Escape
 *
 * Provides escaping and encoding utilities for Better SEO output contexts,
 * including HTML attributes, JSON encoding, and XML URIs.
 *
 * @since 1.0.0
 */
class Escape {

	/**
	 * Sanitizes a string for use as an HTML option name attribute.
	 *
	 * Strips all characters except alphanumerics, brackets, underscores, hyphens, and @.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id The raw attribute value to sanitize.
	 * @return string The sanitized attribute string.
	 */
	public static function option_name_attribute( string $id ): string {
		return preg_replace( '/[^a-zA-Z0-9\[\]_\-@]/', '', $id );
	}

	/**
	 * JSON-encodes a value for safe use in an inline script context.
	 *
	 * Uses JSON_UNESCAPED_SLASHES, JSON_HEX_TAG, JSON_UNESCAPED_UNICODE,
	 * and JSON_INVALID_UTF8_IGNORE flags.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value   The value to encode.
	 * @param int   $options Additional JSON encoding options. Default 0.
	 * @return string|false The JSON-encoded string, or false on failure.
	 */
	public static function json_encode_script( mixed $value, int $options = 0 ): string|false {
		return json_encode(
			$value,
			  \JSON_UNESCAPED_SLASHES
			| \JSON_HEX_TAG
			| \JSON_UNESCAPED_UNICODE
			| \JSON_INVALID_UTF8_IGNORE
			| $options,
		);
	}

	/**
	 * JSON-encodes a value for safe use in an HTML context.
	 *
	 * Includes JSON_HEX_APOS, JSON_HEX_QUOT, and JSON_HEX_AMP flags
	 * in addition to the script encoding flags.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value   The value to encode.
	 * @param int   $options Additional JSON encoding options. Default 0.
	 * @return string|false The JSON-encoded string, or false on failure.
	 */
	public static function json_encode_html( mixed $value, int $options = 0 ): string|false {
		return json_encode(
			$value,
			  \JSON_UNESCAPED_SLASHES
			| \JSON_HEX_TAG
			| \JSON_HEX_APOS
			| \JSON_HEX_QUOT
			| \JSON_HEX_AMP
			| \JSON_UNESCAPED_UNICODE
			| \JSON_INVALID_UTF8_IGNORE
			| $options,
		);
	}

	/**
	 * JSON-encodes a value and escapes it for safe use in an HTML attribute.
	 *
	 * Applies htmlspecialchars() with ENT_QUOTES after JSON encoding,
	 * using the blog's configured character set.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value   The value to encode.
	 * @param int   $options Additional JSON encoding options. Default 0.
	 * @return string The JSON-encoded and HTML-escaped attribute string.
	 */
	public static function json_encode_attribute( mixed $value, int $options = 0 ): string {

		$charset = \get_option( 'blog_charset' ) ?: null;

		// Normalize UTF-8 charset variants to the canonical form.
		$charset = match ( $charset ) {
			'utf8', 'utf-8', 'UTF8' => 'UTF-8',
			default                 => $charset,
		};

		return htmlspecialchars(
			json_encode(
				$value,
				  \JSON_UNESCAPED_SLASHES
				| \JSON_HEX_TAG
				| \JSON_HEX_APOS
				| \JSON_HEX_QUOT
				| \JSON_UNESCAPED_UNICODE
				| \JSON_INVALID_UTF8_IGNORE
				| $options,
			),
			\ENT_QUOTES,
			$charset,
		);
	}

	/**
	 * Sanitizes a URI for safe use in an XML sitemap context.
	 *
	 * Strips disallowed characters, encodes spaces, and rebuilds the query string
	 * using XML-safe ampersand encoding.
	 *
	 * @since 1.0.0
	 *
	 * @param string $uri The URI to sanitize.
	 * @return string The sanitized XML-safe URI.
	 */
	public static function xml_uri( string $uri ): string {

		$uri = preg_replace(
			'/[^a-z0-9-~+_.?#=!&;,\/:%@$\|*\'()\[\]\x80-\xff]/i',
			'',
			str_replace( ' ', '%20', ltrim( $uri ) ),
		);

		$q = parse_url( $uri, PHP_URL_QUERY );

		if ( $q ) {
			parse_str( $q, $r );
			$uri = strtok( $uri, '?' ) . '?' . http_build_query( $r, '', '&amp;', PHP_QUERY_RFC3986 );
		}

		return $uri;
	}
}