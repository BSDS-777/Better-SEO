<?php
/**
 * Better SEO - Front Redirect
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Front
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

namespace Better_SEO\Front;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\{
	Helper,
	Helper\Query,
	Meta,
};

/**
 * Class Better_SEO\Front\Redirect
 *
 * Handles meta-setting-based redirects for Better SEO,
 * including external redirect control and HTTP status code filtering.
 *
 * @since 1.0.0
 */
final class Redirect {

	/**
	 * Initializes a redirect based on the Better SEO meta redirect setting.
	 *
	 * Checks if the current query supports SEO and if a redirect URL is set,
	 * then fires the redirect.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function init_meta_setting_redirect(): void {

		if ( ! Query\Utils::query_supports_seo() ) {
			return;
		}

		$url = Meta\URI::get_redirect_url();

		if ( $url ) {
			/**
			 * Fires before Better SEO performs a meta redirect.
			 *
			 * @since 1.0.0
			 *
			 * @param string $url The redirect URL.
			 */
			\do_action( 'better_seo_before_redirect', $url );

			self::do_redirect( $url );
		}
	}

	/**
	 * Performs an HTTP redirect to the given URL.
	 *
	 * Applies the better_seo_redirect_status_code filter to determine the HTTP status code.
	 * Enforces internal-only redirects when external redirects are not allowed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url The URL to redirect to.
	 * @return void
	 */
	public static function do_redirect( string $url ): void {

		$url = \sanitize_url( $url );

		if ( empty( $url ) ) {
			\status_header( 400 );
			exit;
		}

		/**
		 * Filters the HTTP redirect status code used by Better SEO.
		 *
		 * @since 1.0.0
		 * @param int $status_code The HTTP status code. Default 301.
		 */
		$redirect_type = \absint( \apply_filters( 'better_seo_redirect_status_code', 301 ) );

		if ( $redirect_type > 399 || $redirect_type < 300 ) {
			better_seo()->_doing_it_wrong( __METHOD__, 'You should use 3xx HTTP Status Codes. Recommended 301 and 302.', '' );
		}

		if ( ! Helper\Redirect::allow_external_redirect() ) {
			$url = Meta\URI\Utils::set_url_scheme( Meta\URI\Utils::convert_path_to_url(
				Meta\URI\Utils::set_url_scheme( $url, 'relative' ),
			) );

			\wp_safe_redirect( $url, $redirect_type );
			exit;
		}

		\wp_redirect( $url, $redirect_type );
		exit;
	}
}