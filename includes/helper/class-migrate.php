<?php
/**
 * Better SEO - Helper Migrate
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

use function Better_SEO\umemo;

/**
 * Class Better_SEO\Helper\Migrate
 *
 * Detects unprocessed syntax from other SEO plugins (Yoast SEO, Rank Math, SEOPress)
 * in meta title and description fields, enabling migration warnings.
 *
 * @since 1.0.0
 */
final class Migrate {

	/**
	 * Returns whether the given text contains unprocessed syntax from any supported SEO plugin.
	 *
	 * Checks for Yoast SEO, Rank Math, and SEOPress syntax patterns.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The text to check.
	 * @return bool True if unprocessed SEO plugin syntax is detected.
	 */
	public static function text_has_unprocessed_syntax( string $text ): bool {

		foreach ( [ 'yoast_seo', 'rank_math', 'seopress' ] as $type ) {
			if ( self::{"text_has_{$type}_syntax"}( $text ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns whether the given text contains unprocessed Yoast SEO syntax (%%tag%%).
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The text to check.
	 * @return bool True if Yoast SEO syntax is detected.
	 */
	public static function text_has_yoast_seo_syntax( string $text ): bool {

		if ( \strlen( $text ) < 6 || ! str_contains( $text, '%%' ) ) {
			return false;
		}

		$tags = umemo( __METHOD__ . '/tags' );

		if ( empty( $tags ) ) {
			$tags = umemo(
				__METHOD__ . '/tags',
				[
					'simple'       => implode(
						'|',
						[
							'focuskw',
							'page',
							'pagenumber',
							'pagetotal',
							'primary_category',
							'searchphrase',
							'term404',
							'wc_brand',
							'wc_price',
							'wc_shortdesc',
							'wc_sku',
							'archive_title',
							'author_first_name',
							'author_last_name',
							'caption',
							'category',
							'category_description',
							'category_title',
							'currentdate',
							'currentday',
							'currentmonth',
							'currentyear',
							'date',
							'excerpt',
							'excerpt_only',
							'id',
							'modified',
							'name',
							'parent_title',
							'permalink',
							'post_content',
							'post_year',
							'post_month',
							'post_day',
							'pt_plural',
							'pt_single',
							'sep',
							'sitedesc',
							'sitename',
							'tag',
							'tag_description',
							'term_description',
							'term_title',
							'title',
							'user_description',
							'userid',
						],
					),
					'wildcard_end' => implode( '|', [ 'ct_', 'cf_' ] ),
				],
			);
		}

		return preg_match( "/%%(?:{$tags['simple']})%%/", $text )
			|| preg_match( "/%%(?:{$tags['wildcard_end']})[^%]+?%%/", $text );
	}

	/**
	 * Returns whether the given text contains unprocessed Rank Math syntax (%tag%).
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The text to check.
	 * @return bool True if Rank Math syntax is detected.
	 */
	public static function text_has_rank_math_syntax( string $text ): bool {

		if ( \strlen( $text ) < 4 || ! str_contains( $text, '%' ) ) {
			return false;
		}

		$tags = umemo( __METHOD__ . '/tags' );

		if ( empty( $tags ) ) {
			$tags = umemo(
				__METHOD__ . '/tags',
				[
					'simple'       => implode(
						'|',
						[
							'currenttime',
							'filename',
							'focuskw',
							'group_desc',
							'group_name',
							'keywords',
							'org_name',
							'org_logo',
							'org_url',
							'page',
							'pagenumber',
							'pagetotal',
							'post_thumbnail',
							'primary_category',
							'primary_taxonomy_terms',
							'url',
							'wc_brand',
							'wc_price',
							'wc_shortdesc',
							'wc_sku',
							'category',
							'categories',
							'currentdate',
							'currentday',
							'currentmonth',
							'currentyear',
							'date',
							'excerpt',
							'excerpt_only',
							'id',
							'modified',
							'name',
							'parent_title',
							'post_author',
							'pt_plural',
							'pt_single',
							'seo_title',
							'seo_description',
							'sep',
							'sitedesc',
							'sitename',
							'tag',
							'tags',
							'term',
							'term_description',
							'title',
							'user_description',
							'userid',
						],
					),
					'wildcard_end' => implode(
						'|',
						[
							'categories',
							'count',
							'currenttime',
							'customfield',
							'customterm',
							'customterm_desc',
							'date',
							'modified',
							'tags',
						],
					),
				],
			);
		}

		return preg_match( "/%(?:{$tags['simple']})%/", $text )
			|| preg_match( "/%(?:{$tags['wildcard_end']})\([^\)]+?\)%/", $text );
	}

	/**
	 * Returns whether the given text contains unprocessed SEOPress syntax (%%tag%%).
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The text to check.
	 * @return bool True if SEOPress syntax is detected.
	 */
	public static function text_has_seopress_syntax( string $text ): bool {

		// %%sep%% is the shortest valid tag — minimum length is 7.
		if ( \strlen( $text ) < 7 || ! str_contains( $text, '%%' ) ) {
			return false;
		}

		$tags = umemo( __METHOD__ . '/tags' );

		if ( empty( $tags ) ) {
			$tags = umemo(
				__METHOD__ . '/tags',
				[
					'simple'       => implode(
						'|',
						[
							'author_website',
							'current_pagination',
							'currenttime',
							'post_thumbnail_url',
							'post_url',
							'target_keyword',
							'wc_single_price',
							'wc_single_price_exc_tax',
							'wc_sku',
							'_category_description',
							'_category_title',
							'archive_title',
							'author_bio',
							'author_first_name',
							'author_last_name',
							'author_nickname',
							'currentday',
							'currentmonth',
							'currentmonth_num',
							'currentmonth_short',
							'currentyear',
							'date',
							'excerpt',
							'post_author',
							'post_category',
							'post_content',
							'post_date',
							'post_excerpt',
							'post_modified_date',
							'post_tag',
							'post_title',
							'sep',
							'sitedesc',
							'sitename',
							'sitetitle',
							'tag_description',
							'tag_title',
							'tagline',
							'term_description',
							'term_title',
							'title',
							'wc_single_cat',
							'wc_single_short_desc',
							'wc_single_tag',
						],
					),
					'wildcard_end' => implode(
						'|',
						[
							'_cf_',
							'_ct_',
							'_ucf_',
						],
					),
				],
			);
		}

		return preg_match( "/%%(?:{$tags['simple']})%%/", $text )
			|| preg_match( "/%%(?:{$tags['wildcard_end']})[^%]+?%%/", $text );
	}
}