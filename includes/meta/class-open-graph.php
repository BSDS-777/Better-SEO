<?php
/**
 * Better SEO - Meta Open Graph
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Meta
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

namespace Better_SEO\Meta;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\{
	coalesce_strlen,
	get_query_type_from_args,
	memo,
	normalize_generation_args,
};

use Better_SEO\{
	Data,
	Data\Filter\Sanitize,
	Helper\Query,
};

/**
 * Class Better_SEO\Meta\Open_Graph
 *
 * Provides Open Graph meta values for Better SEO, including type, title,
 * description, locale, site name, URL, article timestamps, and supported locales.
 *
 * @since 1.0.0
 */
class Open_Graph {

	/**
	 * Returns the Open Graph type for the current query context.
	 *
	 * @since 1.0.0
	 *
	 * @return string The Open Graph type: 'product', 'article', 'profile', or 'website'.
	 */
	public static function get_type(): string {
		return match ( true ) {
			Query::is_product() => 'product',
			Query::is_single()  => 'article',
			Query::is_author()  => 'profile',
			default             => 'website',
		};
	}

	/**
	 * Returns the Open Graph title for the given args or current query context.
	 *
	 * Returns the custom OG title if set, otherwise falls back to the generated title.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The Open Graph title string.
	 */
	public static function get_title( ?array $args = null ): string {
		return coalesce_strlen( self::get_custom_title( $args ) )
			?? self::get_generated_title( $args );
	}

	/**
	 * Returns the custom Open Graph title for the given args or current query context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The custom Open Graph title string.
	 */
	public static function get_custom_title( ?array $args = null ): string {
		return isset( $args )
			? self::get_custom_title_from_args( $args )
			: self::get_custom_title_from_query();
	}

	/**
	 * Returns the custom Open Graph title from the current query context.
	 *
	 * Falls back to the standard custom title if no OG-specific title is set.
	 *
	 * @since 1.0.0
	 *
	 * @return string The custom Open Graph title string.
	 */
	public static function get_custom_title_from_query(): string {

		if ( Query::is_real_front_page() ) {
			if ( Query::is_static_front_page() ) {
				$title = coalesce_strlen( Data\Plugin::get_option( 'homepage_og_title' ) )
					?? Data\Plugin\Post::get_meta_item( '_open_graph_title' );
			} else {
				$title = Data\Plugin::get_option( 'homepage_og_title' );
			}
		} elseif ( Query::is_singular() ) {
			$title = Data\Plugin\Post::get_meta_item( '_open_graph_title' );
		} elseif ( Query::is_editable_term() ) {
			$title = Data\Plugin\Term::get_meta_item( 'og_title' );
		} elseif ( \is_post_type_archive() ) {
			$title = Data\Plugin\PTA::get_meta_item( 'og_title' );
		}

		if ( ! isset( $title ) ) {
			return '';
		}

		if ( \strlen( $title ) ) {
			return Sanitize::metadata_content( $title );
		}

		// An attempt was made to fetch an OG title — fall back to the standard custom title.
		return Title::get_custom_title( null, true );
	}

	/**
	 * Returns the custom Open Graph title from the given generation args.
	 *
	 * Falls back to the standard custom title if no OG-specific title is set.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args The generation args.
	 * @return string The custom Open Graph title string.
	 */
	public static function get_custom_title_from_args( array $args ): string {

		normalize_generation_args( $args );

		$title = match ( get_query_type_from_args( $args ) ) {
			'single'   => Query::is_static_front_page( $args['id'] )
				? ( coalesce_strlen( Data\Plugin::get_option( 'homepage_og_title' ) )
					?? Data\Plugin\Post::get_meta_item( '_open_graph_title', $args['id'] ) )
				: Data\Plugin\Post::get_meta_item( '_open_graph_title', $args['id'] ),
			'term'     => Data\Plugin\Term::get_meta_item( 'og_title', $args['id'] ),
			'homeblog' => Data\Plugin::get_option( 'homepage_og_title' ),
			'pta'      => Data\Plugin\PTA::get_meta_item( 'og_title', $args['pta'] ),
			default    => null,
		};

		// Do not check empty() — see strlen below.
		if ( ! isset( $title ) ) {
			return '';
		}

		if ( \strlen( $title ) ) {
			return Sanitize::metadata_content( $title );
		}

		// An attempt was made to fetch an OG title — fall back to the standard custom title.
		return Title::get_custom_title( $args, true );
	}

	/**
	 * Returns the generated Open Graph title for the given args or current query context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The generated Open Graph title string.
	 */
	public static function get_generated_title( ?array $args = null ): string {
		return Title::get_generated_title( $args, true );
	}

	/**
	 * Returns the Open Graph description for the given args or current query context.
	 *
	 * Returns the custom OG description if set, otherwise falls back to the generated description.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The Open Graph description string.
	 */
	public static function get_description( ?array $args = null ): string {
		return coalesce_strlen( self::get_custom_description( $args ) )
			?? self::get_generated_description( $args );
	}

	/**
	 * Returns the custom Open Graph description for the given args or current query context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The custom Open Graph description string.
	 */
	public static function get_custom_description( ?array $args = null ): string {
		return isset( $args )
			? self::get_custom_description_from_args( $args )
			: self::get_custom_description_from_query();
	}

	/**
	 * Returns the custom Open Graph description from the current query context.
	 *
	 * Falls back to the standard custom description if no OG-specific description is set.
	 *
	 * @since 1.0.0
	 *
	 * @return string The custom Open Graph description string.
	 */
	public static function get_custom_description_from_query(): string {

		if ( Query::is_real_front_page() ) {
			if ( Query::is_static_front_page() ) {
				$desc = coalesce_strlen( Data\Plugin::get_option( 'homepage_og_description' ) )
					?? Data\Plugin\Post::get_meta_item( '_open_graph_description' );
			} else {
				$desc = Data\Plugin::get_option( 'homepage_og_description' );
			}
		} elseif ( Query::is_singular() ) {
			$desc = Data\Plugin\Post::get_meta_item( '_open_graph_description' );
		} elseif ( Query::is_editable_term() ) {
			$desc = Data\Plugin\Term::get_meta_item( 'og_description' );
		} elseif ( \is_post_type_archive() ) {
			$desc = Data\Plugin\PTA::get_meta_item( 'og_description' );
		}

		if ( ! isset( $desc ) ) {
			return '';
		}

		if ( \strlen( $desc ) ) {
			return Sanitize::metadata_content( $desc );
		}

		// An attempt was made to fetch an OG description — fall back to the standard custom description.
		return Description::get_custom_description();
	}

	/**
	 * Returns the custom Open Graph description from the given generation args.
	 *
	 * Falls back to the standard custom description if no OG-specific description is set.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args The generation args.
	 * @return string The custom Open Graph description string.
	 */
	public static function get_custom_description_from_args( array $args ): string {

		normalize_generation_args( $args );

		$desc = match ( get_query_type_from_args( $args ) ) {
			'single'   => Query::is_static_front_page( $args['id'] )
				? ( coalesce_strlen( Data\Plugin::get_option( 'homepage_og_description' ) )
					?? Data\Plugin\Post::get_meta_item( '_open_graph_description', $args['id'] ) )
				: Data\Plugin\Post::get_meta_item( '_open_graph_description', $args['id'] ),
			'term'     => Data\Plugin\Term::get_meta_item( 'og_description', $args['id'] ),
			'homeblog' => Data\Plugin::get_option( 'homepage_og_description' ),
			'pta'      => Data\Plugin\PTA::get_meta_item( 'og_description', $args['pta'] ),
			default    => null,
		};

		// Do not check empty() — see strlen below.
		if ( ! isset( $desc ) ) {
			return '';
		}

		if ( \strlen( $desc ) ) {
			return Sanitize::metadata_content( $desc );
		}

		// An attempt was made to fetch an OG description — fall back to the standard custom description.
		return Description::get_custom_description( $args );
	}

	/**
	 * Returns the generated Open Graph description for the given args or current query context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The generated Open Graph description string.
	 */
	public static function get_generated_description( ?array $args = null ): string {
		return Description::get_generated_description( $args, 'opengraph' );
	}

	/**
	 * Returns the Open Graph locale for the current site language.
	 *
	 * Resolves the WordPress locale to a supported Facebook Open Graph locale.
	 * Falls back to 'en_US' if no match is found.
	 *
	 * @since 1.0.0
	 *
	 * @return string The Open Graph locale string (e.g. 'en_US', 'fr_FR').
	 */
	public static function get_locale(): string {

		$locale = \get_locale();

		$locale_len    = \strlen( $locale );
		$valid_locales = self::get_supported_locales();

		if ( $locale_len > 5 ) {
			$locale_len = 5;
			// More than standard full locale type is used — truncate to full format.
			$locale = substr( $locale, 0, $locale_len );
		}

		if ( 5 === $locale_len ) {
			if ( isset( $valid_locales[ $locale ] ) ) {
				return $locale;
			}

			$locale_len = 2;
			$locale     = substr( $locale, 0, $locale_len );
		}

		if ( 2 === $locale_len ) {
			$key = array_search( $locale, $valid_locales, true );

			if ( $key ) {
				return $key;
			}
		}

		return 'en_US';
	}

	/**
	 * Returns the Open Graph site name.
	 *
	 * @since 1.0.0
	 *
	 * @return string The public blog name.
	 */
	public static function get_site_name(): string {
		return Data\Blog::get_public_blog_name();
	}

	/**
	 * Returns the Open Graph URL for the current page.
	 *
	 * @since 1.0.0
	 *
	 * @return string The canonical URL string.
	 */
	public static function get_url(): string {
		return URI::get_canonical_url();
	}

	/**
	 * Returns the article:published_time value for the current single post.
	 *
	 * Returns empty string if the post_publish_time option is disabled or
	 * the current query is not a single post.
	 *
	 * @since 1.0.0
	 *
	 * @return string The published time string, or empty string if not applicable.
	 */
	public static function get_article_published_time(): string {

		if ( ! Data\Plugin::get_option( 'post_publish_time' ) || ! Query::is_single() ) {
			return '';
		}

		return Data\Post::get_published_time();
	}

	/**
	 * Returns the article:modified_time value for the current single post.
	 *
	 * Returns empty string if the post_modify_time option is disabled or
	 * the current query is not a single post.
	 *
	 * @since 1.0.0
	 *
	 * @return string The modified time string, or empty string if not applicable.
	 */
	public static function get_article_modified_time(): string {

		if ( ! Data\Plugin::get_option( 'post_modify_time' ) || ! Query::is_single() ) {
			return '';
		}

		return Data\Post::get_modified_time();
	}

	/**
	 * Returns the map of supported Open Graph locales.
	 *
	 * Keys are full locale strings (e.g. 'en_US'), values are 2-letter language codes (e.g. 'en').
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Map of full locale → 2-letter language code.
	 */
	public static function get_supported_locales(): array {
		return [
			'af_ZA' => 'af', // Afrikaans
			'ak_GH' => 'ak', // Akan
			'am_ET' => 'am', // Amharic
			'ar_AR' => 'ar', // Arabic
			'as_IN' => 'as', // Assamese
			'ay_BO' => 'ay', // Aymara
			'az_AZ' => 'az', // Azerbaijani
			'be_BY' => 'be', // Belarusian
			'bg_BG' => 'bg', // Bulgarian
			'bn_IN' => 'bn', // Bengali
			'br_FR' => 'br', // Breton
			'bs_BA' => 'bs', // Bosnian
			'ca_ES' => 'ca', // Catalan
			'cb_IQ' => 'cb', // Sorani Kurdish
			'ck_US' => 'ck', // Cherokee
			'co_FR' => 'co', // Corsican
			'cs_CZ' => 'cs', // Czech
			'cx_PH' => 'cx', // Cebuano
			'cy_GB' => 'cy', // Welsh
			'da_DK' => 'da', // Danish
			'de_DE' => 'de', // German
			'el_GR' => 'el', // Greek
			'en_GB' => 'en', // English (UK)
			'en_IN' => 'en', // English (India)
			'en_PI' => 'en', // English (Pirate)
			'en_UD' => 'en', // English (Upside Down)
			'en_US' => 'en', // English (US)
			'eo_EO' => 'eo', // Esperanto
			'es_CL' => 'es', // Spanish (Chile)
			'es_CO' => 'es', // Spanish (Colombia)
			'es_ES' => 'es', // Spanish (Spain)
			'es_LA' => 'es', // Spanish
			'es_MX' => 'es', // Spanish (Mexico)
			'es_VE' => 'es', // Spanish (Venezuela)
			'et_EE' => 'et', // Estonian
			'eu_ES' => 'eu', // Basque
			'fa_IR' => 'fa', // Persian
			'fb_LT' => 'fb', // Leet Speak
			'ff_NG' => 'ff', // Fulah
			'fi_FI' => 'fi', // Finnish
			'fo_FO' => 'fo', // Faroese
			'fr_CA' => 'fr', // French (Canada)
			'fr_FR' => 'fr', // French (France)
			'fy_NL' => 'fy', // Frisian
			'ga_IE' => 'ga', // Irish
			'gl_ES' => 'gl', // Galician
			'gn_PY' => 'gn', // Guarani
			'gu_IN' => 'gu', // Gujarati
			'gx_GR' => 'gx', // Classical Greek
			'ha_NG' => 'ha', // Hausa
			'he_IL' => 'he', // Hebrew
			'hi_IN' => 'hi', // Hindi
			'hr_HR' => 'hr', // Croatian
			'hu_HU' => 'hu', // Hungarian
			'hy_AM' => 'hy', // Armenian
			'id_ID' => 'id', // Indonesian
			'ig_NG' => 'ig', // Igbo
			'is_IS' => 'is', // Icelandic
			'it_IT' => 'it', // Italian
			'ja_JP' => 'ja', // Japanese
			'ja_KS' => 'ja', // Japanese (Kansai)
			'jv_ID' => 'jv', // Javanese
			'ka_GE' => 'ka', // Georgian
			'kk_KZ' => 'kk', // Kazakh
			'km_KH' => 'km', // Khmer
			'kn_IN' => 'kn', // Kannada
			'ko_KR' => 'ko', // Korean
			'ku_TR' => 'ku', // Kurdish (Kurmanji)
			'ky_KG' => 'ky', // Kyrgyz
			'la_VA' => 'la', // Latin
			'lg_UG' => 'lg', // Ganda
			'li_NL' => 'li', // Limburgish
			'ln_CD' => 'ln', // Lingala
			'lo_LA' => 'lo', // Lao
			'lt_LT' => 'lt', // Lithuanian
			'lv_LV' => 'lv', // Latvian
			'mg_MG' => 'mg', // Malagasy
			'mi_NZ' => 'mi', // Māori
			'mk_MK' => 'mk', // Macedonian
			'ml_IN' => 'ml', // Malayalam
			'mn_MN' => 'mn', // Mongolian
			'mr_IN' => 'mr', // Marathi
			'ms_MY' => 'ms', // Malay
			'mt_MT' => 'mt', // Maltese
			'my_MM' => 'my', // Burmese
			'nb_NO' => 'nb', // Norwegian (bokmal)
			'nd_ZW' => 'nd', // Ndebele
			'ne_NP' => 'ne', // Nepali
			'nl_BE' => 'nl', // Dutch (België)
			'nl_NL' => 'nl', // Dutch
			'nn_NO' => 'nn', // Norwegian (nynorsk)
			'ny_MW' => 'ny', // Chewa
			'or_IN' => 'or', // Oriya
			'pa_IN' => 'pa', // Punjabi
			'pl_PL' => 'pl', // Polish
			'ps_AF' => 'ps', // Pashto
			'pt_BR' => 'pt', // Portuguese (Brazil)
			'pt_PT' => 'pt', // Portuguese (Portugal)
			'qu_PE' => 'qu', // Quechua
			'rm_CH' => 'rm', // Romansh
			'ro_RO' => 'ro', // Romanian
			'ru_RU' => 'ru', // Russian
			'rw_RW' => 'rw', // Kinyarwanda
			'sa_IN' => 'sa', // Sanskrit
			'sc_IT' => 'sc', // Sardinian
			'se_NO' => 'se', // Northern Sámi
			'si_LK' => 'si', // Sinhala
			'sk_SK' => 'sk', // Slovak
			'sl_SI' => 'sl', // Slovenian
			'sn_ZW' => 'sn', // Shona
			'so_SO' => 'so', // Somali
			'sq_AL' => 'sq', // Albanian
			'sr_RS' => 'sr', // Serbian
			'sv_SE' => 'sv', // Swedish
			'sy_SY' => 'sy', // Swahili
			'sw_KE' => 'sw', // Syriac
			'sz_PL' => 'sz', // Silesian
			'ta_IN' => 'ta', // Tamil
			'te_IN' => 'te', // Telugu
			'tg_TJ' => 'tg', // Tajik
			'th_TH' => 'th', // Thai
			'tk_TM' => 'tk', // Turkmen
			'tl_PH' => 'tl', // Filipino
			'tl_ST' => 'tl', // Klingon
			'tr_TR' => 'tr', // Turkish
			'tt_RU' => 'tt', // Tatar
			'tz_MA' => 'tz', // Tamazight
			'uk_UA' => 'uk', // Ukrainian
			'ur_PK' => 'ur', // Urdu
			'uz_UZ' => 'uz', // Uzbek
			'vi_VN' => 'vi', // Vietnamese
			'wo_SN' => 'wo', // Wolof
			'xh_ZA' => 'xh', // Xhosa
			'yi_DE' => 'yi', // Yiddish
			'yo_NG' => 'yo', // Yoruba
			'zh_CN' => 'zh', // Simplified Chinese (China)
			'zh_HK' => 'zh', // Traditional Chinese (Hong Kong)
			'zh_TW' => 'zh', // Traditional Chinese (Taiwan)
			'zu_ZA' => 'zu', // Zulu
			'zz_TR' => 'zz', // Zazaki
		];
	}
}