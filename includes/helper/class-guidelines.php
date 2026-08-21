<?php
/**
 * Better SEO - Helper Guidelines
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

use function Better_SEO\memo;

/**
 * Class Better_SEO\Helper\Guidelines
 *
 * Provides locale-aware text size guidelines for Better SEO title and description
 * fields, including character and pixel thresholds for search, Open Graph, and Twitter.
 *
 * @since 1.0.0
 */
class Guidelines {

	/**
	 * Returns locale-aware text size guidelines for title and description fields, memoized.
	 *
	 * Character and pixel thresholds are adjusted per locale using language-specific
	 * multipliers to account for character width differences across writing systems.
	 * Applies the better_seo_input_guidelines filter.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $locale The locale string (e.g. 'en_US'). Defaults to get_locale().
	 * @return array<string, array<string, array<string, array<string, int>>>> The guidelines array.
	 */
	public static function get_text_size_guidelines( ?string $locale = null ): array {

		$locale = substr( $locale ?? \get_locale(), 0, 5 );

		if ( null !== $memo = memo( null, $locale ) ) {
			return $memo;
		}

		// Character width adjustment multipliers per locale.
		$character_adjustments = [
			'as'    => 148 / 160, // Assamese (অসমীয়া)
			'de_AT' => 158 / 160, // Austrian German (Österreichisch Deutsch)
			'de_CH' => 158 / 160, // Swiss German (Schweiz Deutsch)
			'de_DE' => 158 / 160, // German (Deutsch)
			'gu'    => 148 / 160, // Gujarati (ગુજરાતી)
			'ml_IN' => 100 / 160, // Malayalam (മലയാളം)
			'ja'    =>  70 / 160, // Japanese (日本語)
			'ko_KR' =>  82 / 160, // Korean (한국어)
			'ta_IN' => 120 / 160, // Tamil (தமிழ்)
			'zh_TW' =>  70 / 160, // Taiwanese Mandarin — Traditional Chinese (繁體中文)
			'zh_HK' =>  70 / 160, // Hong Kong Chinese (香港中文版)
			'zh_CN' =>  70 / 160, // Mandarin — Simplified Chinese (简体中文)
		];

		$c_adjust = $character_adjustments[ $locale ] ?? 1;

		// Pixel width adjustment multipliers per locale (RTL scripts).
		$pixel_adjustments = [
			'ar'    => 760 / 910, // Arabic (العربية)
			'ary'   => 760 / 910, // Moroccan Arabic (العربية المغربية)
			'azb'   => 760 / 910, // South Azerbaijani (گؤنئی آذربایجان)
			'fa_IR' => 760 / 910, // Iran Farsi (فارسی)
			'haz'   => 760 / 910, // Hazaragi (هزاره گی)
			'ckb'   => 760 / 910, // Central Kurdish (كوردی)
		];

		$p_adjust = $pixel_adjustments[ $locale ] ?? 1;

		/**
		 * Filters the Better SEO input guidelines.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $guidelines     The guidelines array.
		 * @param array<int, float>    $adjustments    The [character, pixel] adjustment multipliers.
		 * @param string               $locale         The current locale string.
		 */
		return memo(
			(array) \apply_filters(
				'better_seo_input_guidelines',
				[
					'title' => [
						'search' => [
							'chars'  => [
								'lower'     => (int) ( 25 * $c_adjust ),
								'goodLower' => (int) ( 35 * $c_adjust ),
								'goodUpper' => (int) ( 65 * $c_adjust ),
								'upper'     => (int) ( 75 * $c_adjust ),
							],
							'pixels' => [
								'lower'     => (int) ( 200 * $p_adjust ),
								'goodLower' => (int) ( 280 * $p_adjust ),
								'goodUpper' => (int) ( 520 * $p_adjust ),
								'upper'     => (int) ( 600 * $p_adjust ),
							],
						],
						'opengraph' => [
							'chars'  => [
								'lower'     => 15,
								'goodLower' => 25,
								'goodUpper' => 88,
								'upper'     => 100,
							],
							'pixels' => [],
						],
						'twitter' => [
							'chars'  => [
								'lower'     => 15,
								'goodLower' => 25,
								'goodUpper' => 69,
								'upper'     => 70,
							],
							'pixels' => [],
						],
					],
					'description' => [
						'search' => [
							'chars'  => [
								'lower'     => (int) ( 45 * $c_adjust ),
								'goodLower' => (int) ( 80 * $c_adjust ),
								'goodUpper' => (int) ( 160 * $c_adjust ),
								'upper'     => (int) ( 320 * $c_adjust ),
							],
							'pixels' => [
								'lower'     => (int) ( 256 * $p_adjust ),
								'goodLower' => (int) ( 455 * $p_adjust ),
								'goodUpper' => (int) ( 910 * $p_adjust ),
								'upper'     => (int) ( 1820 * $p_adjust ),
							],
						],
						'opengraph' => [
							'chars'  => [
								'lower'     => 45,
								'goodLower' => 80,
								'goodUpper' => 200,
								'upper'     => 300,
							],
							'pixels' => [],
						],
						'twitter' => [
							'chars'  => [
								'lower'     => 45,
								'goodLower' => 80,
								'goodUpper' => 200,
								'upper'     => 200,
							],
							'pixels' => [],
						],
					],
				],
				[ $c_adjust, $p_adjust ],
				$locale,
			),
			$locale,
		);
	}

	/**
	 * Returns internationalized label strings for text size guideline states, memoized.
	 *
	 * Provides long, short, and shortdot variants for each state:
	 * empty, farTooShort, tooShort, tooLong, farTooLong, good.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, string>> The i18n guideline label arrays.
	 */
	public static function get_text_size_guidelines_i18n(): array {
		return memo() ?? memo( [
			'long'     => [
				'empty'       => \esc_attr__( 'No Content.', 'better-seo' ),
				'farTooShort' => \esc_attr__( 'Considered short; provide more information.', 'better-seo' ),
				'tooShort'    => \esc_attr__( "It's short and it could have more information.", 'better-seo' ),
				'tooLong'     => \esc_attr__( "It's long and it might get truncated in search.", 'better-seo' ),
				'farTooLong'  => \esc_attr__( "It's too long and it will get truncated in search.", 'better-seo' ),
				'good'        => \esc_attr__( 'Length is good.', 'better-seo' ),
			],
			'short'    => [
				'empty'       => \esc_attr_x( 'Empty', 'The text field is empty', 'better-seo' ),
				'farTooShort' => \esc_attr__( 'Far too short', 'better-seo' ),
				'tooShort'    => \esc_attr__( 'Too short', 'better-seo' ),
				'tooLong'     => \esc_attr__( 'Too long', 'better-seo' ),
				'farTooLong'  => \esc_attr__( 'Far too long', 'better-seo' ),
				'good'        => \esc_attr__( 'Good', 'better-seo' ),
			],
			'shortdot' => [
				'empty'       => \esc_attr_x( 'Empty.', 'The text field is empty', 'better-seo' ),
				'farTooShort' => \esc_attr__( 'Far too short.', 'better-seo' ),
				'tooShort'    => \esc_attr__( 'Too short.', 'better-seo' ),
				'tooLong'     => \esc_attr__( 'Too long.', 'better-seo' ),
				'farTooLong'  => \esc_attr__( 'Far too long.', 'better-seo' ),
				'good'        => \esc_attr__( 'Good.', 'better-seo' ),
			],
		] );
	}
}