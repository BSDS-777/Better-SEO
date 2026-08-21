<?php
/**
 * Better SEO - Meta URI Utils
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Meta\URI
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

namespace Better_SEO\Meta\URI;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\umemo;

use Better_SEO\Data;

/**
 * Class Better_SEO\Meta\URI\Utils
 *
 * Provides URL utility methods for Better SEO URI generation, including
 * URL scheme management, pagination handling, domain matching, and
 * query string manipulation.
 *
 * @since 1.0.0
 */
class Utils {

	/**
	 * Returns the preferred URL scheme ('https' or 'http') for the site, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return string The preferred URL scheme string.
	 */
	public static function get_preferred_url_scheme(): string {
		return umemo( __METHOD__ ) ?? umemo(
			__METHOD__,
			self::get_url_scheme_from_setting() ?: self::detect_url_scheme(),
		);
	}

	/**
	 * Returns the URL scheme configured in plugin settings.
	 *
	 * @since 1.0.0
	 *
	 * @return string The configured URL scheme ('https', 'http'), or empty string for automatic.
	 */
	public static function get_url_scheme_from_setting(): string {
		$scheme = Data\Plugin::get_option( 'canonical_scheme' );
		return 'automatic' === $scheme ? '' : (string) $scheme;
	}

	/**
	 * Detects the current URL scheme from the WordPress home URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string The detected URL scheme ('https' or 'http').
	 */
	public static function detect_url_scheme(): string {
		return str_starts_with( \get_option( 'home' ), 'https' ) ? 'https' : 'http';
	}

	/**
	 * Sets the URL scheme on the given URL to the preferred scheme.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url    The URL to update.
	 * @param string $scheme Optional scheme to apply. Default empty (uses preferred scheme).
	 * @return string The URL with the updated scheme.
	 */
	public static function set_preferred_url_scheme( string $url, string $scheme = '' ): string {
		return self::set_url_scheme( $url, $scheme ?: self::get_preferred_url_scheme() );
	}

	/**
	 * Sets the URL scheme on the given URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url    The URL to update.
	 * @param string $scheme The scheme to apply ('https', 'http', 'relative', etc.).
	 * @return string The URL with the updated scheme.
	 */
	public static function set_url_scheme( string $url, string $scheme ): string {
		return \set_url_scheme( $url, $scheme );
	}

	/**
	 * Returns whether the given URL matches the current blog domain.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to check.
	 * @return bool True if the URL's host matches the blog domain.
	 */
	public static function url_matches_blog_domain( string $url ): bool {
		$blog_domain = parse_url( \get_option( 'home' ), PHP_URL_HOST );
		$url_domain  = parse_url( $url, PHP_URL_HOST );

		return $blog_domain && $url_domain && $blog_domain === $url_domain;
	}

	/**
	 * Converts a relative path to an absolute URL using the current scheme.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path The relative path to convert.
	 * @return string The absolute URL string.
	 */
	public static function convert_path_to_url( string $path ): string {
		return \trailingslashit( \get_option( 'home' ) ) . ltrim( $path, '/' );
	}

	/**
	 * Returns the relative path portion of the given URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to extract the path from.
	 * @return string The relative path string, or empty string if not determinable.
	 */
	public static function get_relative_part_from_url( string $url ): string {
		return parse_url( $url, PHP_URL_PATH ) ?? '';
	}

	/**
	 * Returns the URL permastruct for the given generation args or current query context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The URL permastruct string.
	 */
	public static function get_url_permastruct( ?array $args = null ): string {
		return \get_option( 'permalink_structure' ) ?: '';
	}

	/**
	 * Adds a pagination segment to the given URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url   The base URL.
	 * @param int    $page  The page number to add.
	 * @param bool   $paged Whether to use 'paged' (true) or 'page' (false) format. Default true.
	 * @return string The paginated URL string.
	 */
	public static function add_pagination_to_url( string $url, int $page, bool $paged = true ): string {

		if ( $page < 2 ) {
			return $url;
		}

		if ( \get_option( 'permalink_structure' ) ) {
			$base = $paged ? \trailingslashit( $url ) . 'page/' . $page . '/' : \trailingslashit( $url ) . $page . '/';
		} else {
			$base = \add_query_arg( $paged ? 'paged' : 'page', $page, $url );
		}

		return $base;
	}

	/**
	 * Removes the pagination segment from the given URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The paginated URL to strip.
	 * @return string The URL with pagination removed.
	 */
	public static function remove_pagination_from_url( string $url ): string {
		return preg_replace( '#/page/\d+/?$#i', '/', $url ) ?: $url;
	}

	/**
	 * Appends a query string to the given URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url   The base URL.
	 * @param string $query The query string to append (without leading '?').
	 * @return string The URL with the query string appended.
	 */
	public static function append_query_to_url( string $url, string $query ): string {

		if ( empty( $query ) ) {
			return $url;
		}

		$separator = str_contains( $url, '?' ) ? '&' : '?';
		return $url . $separator . $query;
	}

	/**
	 * Ensures the front page URL has a trailing slash if required by permalink settings.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The front page URL to process.
	 * @return string The URL with trailing slash applied if needed.
	 */
	public static function slash_front_page_url( string $url ): string {

		if ( empty( $url ) ) {
			return '';
		}

		return \user_trailingslashit( $url, 'front' );
	}

	/**
	 * Converts a URL to use an absolute scheme based on the current site scheme.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to make absolute.
	 * @return string The absolute URL string.
	 */
	public static function make_absolute_current_scheme_url( string $url ): string {

		if ( empty( $url ) ) {
			return '';
		}

		// Already absolute — just ensure scheme matches.
		if ( str_starts_with( $url, 'http' ) ) {
			return self::set_preferred_url_scheme( $url );
		}

		// Relative URL — prepend home URL.
		return self::set_preferred_url_scheme(
			self::convert_path_to_url( $url )
		);
	}
}