<?php
/**
 * Better SEO - Helper Format Time
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

use function Better_SEO\umemo;

use Better_SEO\Data;

/**
 * Class Better_SEO\Helper\Format\Time
 *
 * Provides timestamp formatting utilities for Better SEO structured data output,
 * including conversion to ISO 8601 or date-only formats based on plugin settings.
 *
 * @since 1.0.0
 */
class Time {

	/**
	 * Converts a timestamp string to the preferred Better SEO format.
	 *
	 * Accepts MySQL datetime strings (Y-m-d H:i:s) or Unix timestamps.
	 * Returns empty string for empty, zero, or invalid timestamps.
	 *
	 * @since 1.0.0
	 *
	 * @param string|int $time The timestamp to convert (MySQL datetime or Unix timestamp).
	 * @return string The formatted timestamp string, or empty string if invalid.
	 */
	public static function convert_to_preferred_format( string|int $time ): string {

		if ( empty( $time ) || '0000-00-00 00:00:00' === $time ) {
			return '';
		}

		if ( is_numeric( $time ) ) {
			return gmdate( self::get_preferred_format(), (int) $time );
		}

		$value = $time ? date_create_from_format( 'Y-m-d H:i:s', $time ) : '';

		return $value ? date_format( $value, self::get_preferred_format() ) : '';
	}

	/**
	 * Returns the preferred timestamp format string based on plugin settings, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return string The preferred PHP date format string.
	 */
	public static function get_preferred_format(): string {
		return umemo( __METHOD__ )
			?? umemo(
				__METHOD__,
				self::get_format(
					(bool) Data\Plugin::get_option( 'timestamps_format' ),
				),
			);
	}

	/**
	 * Returns the timestamp format string for the given time inclusion preference.
	 *
	 * Applies the better_seo_timestamp_format filter.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $get_time Whether to include time in the format. True = ISO 8601, false = date only.
	 * @return string The PHP date format string.
	 */
	public static function get_format( bool $get_time ): string {
		/**
		 * Filters the Better SEO timestamp format string.
		 *
		 * @since 1.0.0
		 *
		 * @param string $format   The PHP date format string.
		 * @param bool   $get_time Whether time is included in the format.
		 */
		return (string) \apply_filters(
			'better_seo_timestamp_format',
			$get_time ? 'Y-m-d\TH:i:sP' : 'Y-m-d',
			$get_time,
		);
	}
}