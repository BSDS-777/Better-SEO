<?php
/**
 * Better SEO - Meta Open Graph Utils
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Meta\Open_Graph
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

namespace Better_SEO\Meta\Open_Graph;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\Data;

/**
 * Class Better_SEO\Meta\Open_Graph\Utils
 *
 * Utility methods for Better SEO Open Graph meta output.
 *
 * This class is a stub for v1.0.0. Open Graph meta is currently handled
 * entirely by Better_SEO\Meta\Open_Graph. Add Open Graph-specific utility
 * methods here as the plugin grows — for example:
 *
 * - og:type resolution helpers
 * - Locale normalization utilities
 * - Multi-image support helpers
 * - og:video meta generation
 * - og:audio meta generation
 * - og:book / og:profile type helpers
 *
 * @since 1.0.0
 *
 * @see \Better_SEO\Meta\Open_Graph The main Open Graph meta class.
 */
class Utils {

	/**
	 * Returns whether the current page should output article-specific Open Graph meta.
	 *
	 * Article meta (published_time, modified_time, author, publisher) is only
	 * relevant when the og:type is 'article'.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current og:type is 'article'.
	 */
	public static function is_article_type(): bool {
		return 'article' === \Better_SEO\Meta\Open_Graph::get_type();
	}

	/**
	 * Returns whether the current page should output profile-specific Open Graph meta.
	 *
	 * Profile meta is only relevant when the og:type is 'profile'.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current og:type is 'profile'.
	 */
	public static function is_profile_type(): bool {
		return 'profile' === \Better_SEO\Meta\Open_Graph::get_type();
	}

	/**
	 * Returns whether the current page should output product-specific Open Graph meta.
	 *
	 * Product meta is only relevant when the og:type is 'product'.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current og:type is 'product'.
	 */
	public static function is_product_type(): bool {
		return 'product' === \Better_SEO\Meta\Open_Graph::get_type();
	}

	/**
	 * Returns the og:video URL for the current post.
	 *
	 * Reserved for future use — og:video meta is not yet implemented.
	 * When implemented, this should detect embedded video URLs from post content
	 * and apply a better_seo_open_graph_video_url filter.
	 *
	 * @since 1.0.0
	 *
	 * @return string The og:video URL string, or empty string if not applicable.
	 */
	public static function get_video_url(): string {
		/**
		 * Filters the Better SEO Open Graph video URL.
		 *
		 * @since 1.0.0
		 * @param string $url The og:video URL. Default empty.
		 */
		return (string) \apply_filters( 'better_seo_open_graph_video_url', '' );
	}

	/**
	 * Returns the og:audio URL for the current post.
	 *
	 * Reserved for future use — og:audio meta is not yet implemented.
	 * When implemented, this should detect embedded audio URLs from post content
	 * and apply a better_seo_open_graph_audio_url filter.
	 *
	 * @since 1.0.0
	 *
	 * @return string The og:audio URL string, or empty string if not applicable.
	 */
	public static function get_audio_url(): string {
		/**
		 * Filters the Better SEO Open Graph audio URL.
		 *
		 * @since 1.0.0
		 * @param string $url The og:audio URL. Default empty.
		 */
		return (string) \apply_filters( 'better_seo_open_graph_audio_url', '' );
	}

	/**
	 * Returns the og:determiner value for the current page.
	 *
	 * Reserved for future use — og:determiner is not yet implemented.
	 * Valid values: 'a', 'an', 'the', 'auto', ''.
	 * Applies a better_seo_open_graph_determiner filter.
	 *
	 * @since 1.0.0
	 *
	 * @return string The og:determiner value, or empty string for default behavior.
	 */
	public static function get_determiner(): string {
		/**
		 * Filters the Better SEO Open Graph determiner.
		 *
		 * @since 1.0.0
		 * @param string $determiner The og:determiner value. Default empty.
		 */
		return (string) \apply_filters( 'better_seo_open_graph_determiner', '' );
	}
}