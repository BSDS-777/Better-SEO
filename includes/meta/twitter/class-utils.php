<?php
/**
 * Better SEO - Meta Twitter Utils
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Meta\Twitter
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

namespace Better_SEO\Meta\Twitter;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\Data;

/**
 * Class Better_SEO\Meta\Twitter\Utils
 *
 * Utility methods for Better SEO Twitter Card meta output.
 *
 * This class is a stub for v1.0.0. Twitter Card meta is currently handled
 * entirely by Better_SEO\Meta\Twitter. Add Twitter-specific utility
 * methods here as the plugin grows — for example:
 *
 * - Card type validation helpers
 * - twitter:app meta generation (App Card type)
 * - twitter:player meta generation (Player Card type)
 * - twitter:label / twitter:data meta generation
 * - Handle format normalization (@username)
 * - Image size validation for specific card types
 *
 * @since 1.0.0
 *
 * @see \Better_SEO\Meta\Twitter The main Twitter Card meta class.
 */
class Utils {

	/**
	 * Returns whether the current card type supports large image display.
	 *
	 * The 'summary_large_image' card type displays a large image above the tweet.
	 * The 'summary' card type displays a small thumbnail.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current card type is 'summary_large_image'.
	 */
	public static function is_large_image_card(): bool {
		return 'summary_large_image' === \Better_SEO\Meta\Twitter::get_card_type();
	}

	/**
	 * Returns whether the current card type is a summary card.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current card type is 'summary'.
	 */
	public static function is_summary_card(): bool {
		return 'summary' === \Better_SEO\Meta\Twitter::get_card_type();
	}

	/**
	 * Returns the recommended image dimensions for the current Twitter Card type.
	 *
	 * Summary cards: minimum 144×144px, recommended 400×400px.
	 * Summary large image cards: minimum 300×157px, recommended 1200×628px.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, int> The recommended image dimensions ['width' => int, 'height' => int].
	 */
	public static function get_recommended_image_dimensions(): array {
		return self::is_large_image_card()
			? [ 'width' => 1200, 'height' => 628 ]
			: [ 'width' => 400,  'height' => 400 ];
	}

	/**
	 * Returns the twitter:label1 / twitter:data1 pair for the current post.
	 *
	 * Reserved for future use — twitter:label/data meta is not yet implemented.
	 * When implemented, this should generate structured data pairs (e.g. reading time,
	 * author name) and apply a better_seo_twitter_label_data filter.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, string>> List of [label, data] pairs, or empty array.
	 */
	public static function get_label_data_pairs(): array {
		/**
		 * Filters the Better SEO Twitter Card label/data pairs.
		 *
		 * Each entry should be an array with 'label' and 'data' keys.
		 *
		 * @since 1.0.0
		 * @param array<int, array<string, string>> $pairs The label/data pairs. Default empty.
		 */
		return (array) \apply_filters( 'better_seo_twitter_label_data_pairs', [] );
	}

	/**
	 * Returns the twitter:app:id:iphone value for the current page.
	 *
	 * Reserved for future use — App Card type is not yet implemented.
	 * When implemented, this should read from a dedicated plugin option
	 * and apply a better_seo_twitter_app_id_iphone filter.
	 *
	 * @since 1.0.0
	 *
	 * @return string The iPhone App Store ID, or empty string if not configured.
	 */
	public static function get_app_id_iphone(): string {
		/**
		 * Filters the Better SEO Twitter App Card iPhone App Store ID.
		 *
		 * @since 1.0.0
		 * @param string $app_id The iPhone App Store ID. Default empty.
		 */
		return (string) \apply_filters( 'better_seo_twitter_app_id_iphone', '' );
	}

	/**
	 * Returns the twitter:app:id:googleplay value for the current page.
	 *
	 * Reserved for future use — App Card type is not yet implemented.
	 * When implemented, this should read from a dedicated plugin option
	 * and apply a better_seo_twitter_app_id_googleplay filter.
	 *
	 * @since 1.0.0
	 *
	 * @return string The Google Play App ID, or empty string if not configured.
	 */
	public static function get_app_id_googleplay(): string {
		/**
		 * Filters the Better SEO Twitter App Card Google Play App ID.
		 *
		 * @since 1.0.0
		 * @param string $app_id The Google Play App ID. Default empty.
		 */
		return (string) \apply_filters( 'better_seo_twitter_app_id_googleplay', '' );
	}
}