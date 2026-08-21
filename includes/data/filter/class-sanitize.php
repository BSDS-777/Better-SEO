<?php
/**
 * Better SEO - Data Filter Sanitize
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

use Better_SEO\{
	Helper,
	Helper\Format\Strings,
	Meta,
};

/**
 * Class Better_SEO\Data\Filter\Sanitize
 *
 * Provides sanitization utilities for Better SEO metadata, URLs, colors,
 * social profile handles, and image details.
 *
 * @since 1.0.0
 */
class Sanitize {

	/**
	 * Converts a value to a boolean integer (0 or 1).
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The value to convert.
	 * @return int 1 if truthy, 0 if falsy.
	 */
	public static function boolean_integer( mixed $value ): int {
		return (int) (bool) $value;
	}

	/**
	 * Converts a value to a numeric string representation of its integer cast.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The value to convert.
	 * @return string The integer value as a string.
	 */
	public static function numeric_string( mixed $value ): string {
		return (string) (int) $value;
	}

	/**
	 * Sanitizes a color string to a valid 3 or 6 character lowercase hex value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $color The raw color string (with or without # prefix).
	 * @return string The sanitized lowercase hex color string, or empty string if invalid.
	 */
	public static function rgb_hex( string $color ): string {

		preg_match(
			'/^(?:[a-f\d]{3}){1,2}/i',
			trim( $color, '# ' ),
			$matches,
		);

		return strtolower( $matches[0] ?? '' );
	}

	/**
	 * Sanitizes a color string to a valid 3, 4, 6, or 8 character lowercase hex value (with alpha).
	 *
	 * @since 1.0.0
	 *
	 * @param string $color The raw color string (with or without # prefix).
	 * @return string The sanitized lowercase hex color string, or empty string if invalid.
	 */
	public static function rgba_hex( string $color ): string {

		preg_match(
			'/^(?:[a-f\d]{8}|[a-f\d]{6}|[a-f\d]{3,4})/i',
			trim( $color, '# ' ),
			$matches,
		);

		return strtolower( $matches[0] ?? '' );
	}

	/**
	 * Sanitizes metadata text content for SEO output.
	 *
	 * Applies a chain of transformations: nbsp → space, newline → space,
	 * tab → space, trim, remove repeated spacing, lone hyphen → entity,
	 * backward solidus → entity, capital_P_dangit, wptexturize,
	 * and finally html_entity_decode for clean output.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $text The raw text to sanitize.
	 * @return string The sanitized metadata content string.
	 */
	public static function metadata_content( mixed $text ): string {

		if ( ! \is_scalar( $text ) || ! \strlen( (string) $text ) ) {
			return '';
		}

		return html_entity_decode(
			\wptexturize(
				\capital_P_dangit(
					self::backward_solidus_to_entity(
						self::lone_hyphen_to_entity(
							self::remove_repeated_spacing(
								trim(
									self::tab_to_space(
										self::newline_to_space(
											self::nbsp_to_space(
												(string) $text,
											),
										),
									),
								),
							),
						),
					),
				),
			),
			\ENT_HTML5 | \ENT_QUOTES | \ENT_SUBSTITUTE,
		);
	}

	/**
	 * Normalizes metadata content for string comparison (strcmp).
	 *
	 * Applies metadata_content() then decodes HTML entities to UTF-8.
	 * Uses UTF-8 explicitly — blog_charset is only for onboarding non-UTF-8 to UTF-8.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $text The raw text to normalize.
	 * @return string The normalized UTF-8 string for comparison.
	 */
	public static function normalize_metadata_content_for_strcmp( mixed $text ): string {
		return html_entity_decode(
			self::metadata_content( $text ),
			\ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5,
			'UTF-8',
		);
	}

	/**
	 * Removes repeated Unicode spacing characters from a string.
	 *
	 * Uses Unicode-aware regex to collapse consecutive spacing characters
	 * to a single instance without requiring mb_* functions.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The text to process.
	 * @return string The text with repeated spacing collapsed.
	 */
	public static function remove_repeated_spacing( string $text ): string {
		return preg_replace_callback(
			'/(\p{Zs}){2,}/u',
			// Unicode support sans mb_*: calculate bytes of match and remove that length.
			fn( array $matches ): string => substr( $matches[1], 0, \strlen( $matches[1] ) ),
			$text,
		);
	}

	/**
	 * Converts lone hyphens to their HTML entity equivalent (&#x2d;).
	 *
	 * Preserves double and triple hyphens (-- and ---) while converting
	 * single hyphens and existing &#45; and Unicode hyphen entities.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The text to process.
	 * @return string The text with lone hyphens converted to &#x2d;.
	 */
	public static function lone_hyphen_to_entity( string $text ): string {
		return str_replace(
			[ '&#45;', "\xe2\x80\x90" ],
			'&#x2d;',
			preg_replace( '/((-{2,3})(*SKIP)-|-)(?(2)(*FAIL))/', '&#x2d;', $text ),
		);
	}

	/**
	 * Replaces non-breaking space variants with a regular space.
	 *
	 * Handles &nbsp;, &#160;, &#xA0;, and the UTF-8 byte sequence \xc2\xa0.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The text to process.
	 * @return string The text with non-breaking spaces replaced by regular spaces.
	 */
	public static function nbsp_to_space( string $text ): string {
		return str_replace( [ '&nbsp;', '&#160;', '&#xA0;', "\xc2\xa0" ], ' ', $text );
	}

	/**
	 * Converts backslashes to their HTML entity equivalent (&#92;).
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The text to process.
	 * @return string The text with backslashes converted to &#92;.
	 */
	public static function backward_solidus_to_entity( string $text ): string {
		return str_replace( '\\', '&#92;', $text );
	}

	/**
	 * Converts newline and control characters to regular spaces, then trims.
	 *
	 * Converts \x0A (LF), \x0B (VT), \x0C (FF), and \x0D (CR) to \x20 (space).
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The text to process.
	 * @return string The trimmed text with newlines converted to spaces.
	 */
	public static function newline_to_space( string $text ): string {
		// Use \x20 — it's a human-visible real space.
		return trim(
			strtr( $text, "\x0A\x0B\x0C\x0D", "\x20\x20\x20\x20" ),
		);
	}

	/**
	 * Converts tab characters to regular spaces.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The text to process.
	 * @return string The text with tabs converted to spaces.
	 */
	public static function tab_to_space( string $text ): string {
		// Use \x20 — it's a human-visible real space.
		return strtr( $text, "\x09", "\x20" );
	}

	/**
	 * Converts a value to a qubit: -1, 0, or 1.
	 *
	 * Returns 1 if value >= 0.3334, -1 if value <= -0.3333, 0 otherwise.
	 * Used for tri-state SEO meta fields (force-on, default, force-off).
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The value to convert.
	 * @return int -1, 0, or 1.
	 */
	public static function qubit( mixed $value ): int {
		return $value >= .3334 <=> -.3333 >= $value;
	}

	/**
	 * Sanitizes a redirect URL for storage.
	 *
	 * If external redirects are not allowed, converts the URL to an
	 * absolute internal URL using the current scheme.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The raw redirect URL.
	 * @return string The sanitized redirect URL, or empty string if invalid.
	 */
	public static function redirect_url( string $url ): string {

		$url = trim( $url );

		if ( empty( $url ) ) {
			return '';
		}

		// Also checked when performing the redirect.
		if ( ! Helper\Redirect::allow_external_redirect() ) {
			$url = Meta\URI\Utils::set_url_scheme( Meta\URI\Utils::convert_path_to_url(
				Meta\URI\Utils::set_url_scheme( $url, 'relative' ),
			) );
		}

		return \sanitize_url( $url );
	}

	/**
	 * Sanitizes image detail arrays for SEO output.
	 *
	 * Accepts either a single image detail array or a list of detail arrays.
	 * Validates URL, dimensions, file type, and alt/caption text.
	 * Returns defaults array for invalid or unsupported images.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|array<int, array<string, mixed>> $details Image detail array or list.
	 * @return array<string, mixed>|array<int, array<string, mixed>> Sanitized image details.
	 */
	public static function image_details( array $details ): array {

		if ( isset( $details[0] ) && array_values( $details ) === $details ) {
			$sanitized_details = [];
			foreach ( $details as $deets ) {
				$sanitized_details[] = self::image_details( $deets );
			}
			return $sanitized_details;
		}

		$defaults = [
			'url'      => '',
			'id'       => 0,
			'width'    => 0,
			'height'   => 0,
			'alt'      => '',
			'caption'  => '',
			'filesize' => 0,
		];

		if ( empty( $details ) ) {
			return $defaults;
		}

		[ $url, $id, $width, $height, $alt, $caption, $filesize ] = array_values( array_merge( $defaults, $details ) );

		if ( empty( $url ) ) {
			return $defaults;
		}

		$url = \sanitize_url( Meta\URI\Utils::make_absolute_current_scheme_url( $url ), [ 'https', 'http' ] );

		if ( empty( $url ) ) {
			return $defaults;
		}

		// Reject unsupported image formats.
		if ( \in_array(
			strtolower( strtok( pathinfo( $url, \PATHINFO_EXTENSION ), '?' ) ),
			[ 'apng', 'bmp', 'ico', 'cur', 'svg', 'tif', 'tiff' ],
			true,
		) ) {
			return $defaults;
		}

		$width  = \absint( $width );
		$height = \absint( $height );

		if ( empty( $width ) || empty( $height ) ) {
			$width = $height = 0;
		}

		if ( $id && ( $width > 4096 || $height > 4096 || $filesize > 5 * \MB_IN_BYTES ) ) {
			$new_image = Meta\Image\Utils::get_largest_image_src( $id, 4096, 5 * \MB_IN_BYTES );
			$url       = $new_image ? \sanitize_url(
				Meta\URI\Utils::make_absolute_current_scheme_url( $new_image[0] ),
				[ 'https', 'http' ],
			) : '';

			if ( empty( $url ) ) {
				return $defaults;
			}

			$width  = $new_image[1];
			$height = $new_image[2];
		}

		if ( $alt ) {
			$alt = \wp_strip_all_tags( $alt );
			// Twitter card alt text limit: 420 chars. Trim to 417 to account for appended "...".
			$alt = \strlen( $alt ) > 420 ? Strings::clamp_sentence( $alt, 0, 417 ) : $alt;
		}

		if ( $caption ) {
			$caption = \wp_strip_all_tags( $caption, true );
		}

		return compact( 'url', 'id', 'width', 'height', 'alt', 'caption', 'filesize' );
	}

	/**
	 * Sanitizes a Facebook profile URL for storage.
	 *
	 * Extracts the path from the URL and rebuilds a canonical Facebook profile URL.
	 * Handles profile.php?id= format separately.
	 *
	 * @since 1.0.0
	 *
	 * @param string $link The raw Facebook profile URL or path.
	 * @return string The sanitized Facebook profile URL, or empty string if invalid.
	 */
	public static function facebook_profile_link( string $link ): string {

		$path = trim( Meta\URI\Utils::get_relative_part_from_url( $link ), ' /' );

		// /0 is a valid profile link.
		if ( ! \strlen( $path ) ) {
			return '';
		}

		$link = "https://www.facebook.com/{$path}";

		if ( str_contains( $link, 'profile.php' ) ) {
			parse_str( parse_url( $link, \PHP_URL_QUERY ), $r );

			if ( empty( $r['id'] ) ) {
				return '';
			}

			$link = 'https://www.facebook.com/profile.php?id=' . \absint( $r['id'] );
		}

		return \sanitize_url( $link );
	}

	/**
	 * Sanitizes a Twitter/X profile handle for storage.
	 *
	 * Strips the URL portion if present, removes invalid characters,
	 * and prepends @ to the handle. Returns empty string if length is invalid.
	 *
	 * @since 1.0.0
	 *
	 * @param string $handle The raw Twitter handle or profile URL.
	 * @return string The sanitized @handle string, or empty string if invalid.
	 */
	public static function twitter_profile_handle( string $handle ): string {

		$handle = preg_replace(
			'/[^a-z\d_]/i',
			'',
			trim( Meta\URI\Utils::get_relative_part_from_url( $handle ), ' /@' ),
		);

		$length = \strlen( $handle );

		if ( $length < 1 || $length > 18 ) {
			return '';
		}

		return "@{$handle}";
	}
}