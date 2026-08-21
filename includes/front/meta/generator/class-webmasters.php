<?php
/**
 * Better SEO - Front Meta Generator Webmasters
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Front\Meta\Generator
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

namespace Better_SEO\Front\Meta\Generator;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\Data;

/**
 * Class Better_SEO\Front\Meta\Generator\Webmasters
 *
 * Generates webmaster verification meta tags for Google, Bing, Yandex,
 * Baidu, and Pinterest.
 *
 * @since 1.0.0
 */
final class Webmasters {

	/**
	 * Registered generator callbacks for this pool.
	 *
	 * @since 1.0.0
	 * @var   array<int, array{0: class-string, 1: string}>
	 */
	public const GENERATORS = [
		[ __CLASS__, 'generate_google_verification' ],
		[ __CLASS__, 'generate_bing_verification' ],
		[ __CLASS__, 'generate_yandex_verification' ],
		[ __CLASS__, 'generate_baidu_verification' ],
		[ __CLASS__, 'generate_pinterest_verification' ],
	];

	/**
	 * Generates the Google Search Console verification meta tag.
	 *
	 * Yields nothing if no verification code is configured.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the google-site-verification meta tag data.
	 */
	public static function generate_google_verification(): \Generator {

		$code = Data\Plugin::get_option( 'google_verification' );

		if ( $code ) {
			yield 'google-site-verification' => [
				'attributes' => [
					'name'    => 'google-site-verification',
					'content' => $code,
				],
			];
		}
	}

	/**
	 * Generates the Bing Webmaster Tools verification meta tag.
	 *
	 * Yields nothing if no verification code is configured.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the msvalidate.01 meta tag data.
	 */
	public static function generate_bing_verification(): \Generator {

		$code = Data\Plugin::get_option( 'bing_verification' );

		if ( $code ) {
			yield 'msvalidate.01' => [
				'attributes' => [
					'name'    => 'msvalidate.01',
					'content' => $code,
				],
			];
		}
	}

	/**
	 * Generates the Yandex Webmaster verification meta tag.
	 *
	 * Yields nothing if no verification code is configured.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the yandex-verification meta tag data.
	 */
	public static function generate_yandex_verification(): \Generator {

		$code = Data\Plugin::get_option( 'yandex_verification' );

		if ( $code ) {
			yield 'yandex-verification' => [
				'attributes' => [
					'name'    => 'yandex-verification',
					'content' => $code,
				],
			];
		}
	}

	/**
	 * Generates the Baidu Search verification meta tag.
	 *
	 * Yields nothing if no verification code is configured.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the baidu-site-verification meta tag data.
	 */
	public static function generate_baidu_verification(): \Generator {

		$code = Data\Plugin::get_option( 'baidu_verification' );

		if ( $code ) {
			yield 'baidu-site-verification' => [
				'attributes' => [
					'name'    => 'baidu-site-verification',
					'content' => $code,
				],
			];
		}
	}

	/**
	 * Generates the Pinterest domain verification meta tag.
	 *
	 * Yields nothing if no verification code is configured.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the p:domain_verify meta tag data.
	 */
	public static function generate_pinterest_verification(): \Generator {

		$code = Data\Plugin::get_option( 'pint_verification' );

		if ( $code ) {
			yield 'p:domain_verify' => [
				'attributes' => [
					'name'    => 'p:domain_verify',
					'content' => $code,
				],
			];
		}
	}
}