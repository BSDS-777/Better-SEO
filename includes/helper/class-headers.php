<?php
/**
 * Better SEO - Helper Headers
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Helper
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

namespace Better_SEO\Helper;

\defined( 'BETTER_SEO_PRESENT' ) or die;

/**
 * Class Better_SEO\Helper\Headers
 *
 * Provides HTTP header utilities for Better SEO, including output buffer
 * cleanup and robots noindex header output.
 *
 * @since 1.0.0
 */
class Headers {

	/**
	 * Cleans all active output buffer levels.
	 *
	 * Ends and discards all active output buffers to ensure a clean
	 * response header state before sending custom headers.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if any output buffers were cleaned, false if none were active.
	 */
	public static function clean_response_header(): bool {

		$level = ob_get_level();

		if ( $level ) {
			while ( $level-- ) {
				ob_end_clean();
			}
			return true;
		}

		return false;
	}

	/**
	 * Outputs an X-Robots-Tag: noindex HTTP header if headers have not been sent.
	 *
	 * Applies the better_seo_set_noindex_header filter to allow suppression.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function output_robots_noindex_headers(): void {
		/**
		 * Filters whether to output the X-Robots-Tag: noindex header.
		 *
		 * @since 1.0.0
		 * @param bool $set Whether to output the noindex header. Default true.
		 */
		if ( \apply_filters( 'better_seo_set_noindex_header', true ) ) {
			headers_sent() or header( 'X-Robots-Tag: noindex', true );
		}
	}
}