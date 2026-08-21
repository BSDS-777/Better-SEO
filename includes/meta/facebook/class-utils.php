<?php
/**
 * Better SEO - Meta Facebook Utils
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Meta\Facebook
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

namespace Better_SEO\Meta\Facebook;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\Data;

/**
 * Class Better_SEO\Meta\Facebook\Utils
 *
 * Utility methods for Better SEO Facebook Open Graph meta output.
 *
 * This class is a stub for v1.0.0. Facebook meta is currently handled
 * entirely by Better_SEO\Meta\Facebook. Add Facebook-specific utility
 * methods here as the plugin grows — for example:
 *
 * - Article type detection helpers
 * - Facebook App ID retrieval
 * - Profile URL normalization
 * - og:article:tag generation
 * - og:article:section generation
 *
 * @since 1.0.0
 *
 * @see \Better_SEO\Meta\Facebook The main Facebook meta class.
 */
class Utils {

	/**
	 * Returns whether the current page should output Facebook article meta tags.
	 *
	 * Article meta (author, publisher) is only relevant for 'article' Open Graph type pages.
	 * This is a convenience wrapper for use within Facebook sub-generators.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the current page is an article type.
	 */
	public static function is_article_type(): bool {
		return 'article' === \Better_SEO\Meta\Open_Graph::get_type();
	}

	/**
	 * Returns the Facebook App ID from plugin settings.
	 *
	 * Reserved for future use — Facebook App ID support is not yet implemented.
	 * When implemented, this should read from a dedicated plugin option and
	 * apply a better_seo_facebook_app_id filter.
	 *
	 * @since 1.0.0
	 *
	 * @return string The Facebook App ID string, or empty string if not configured.
	 */
	public static function get_app_id(): string {
		/**
		 * Filters the Better SEO Facebook App ID.
		 *
		 * @since 1.0.0
		 * @param string $app_id The Facebook App ID. Default empty.
		 */
		return (string) \apply_filters( 'better_seo_facebook_app_id', '' );
	}

	/**
	 * Returns the article section for the current post.
	 *
	 * Reserved for future use — maps the primary category to an og:article:section value.
	 * When implemented, this should retrieve the primary term name for the current post
	 * and apply a better_seo_facebook_article_section filter.
	 *
	 * @since 1.0.0
	 *
	 * @return string The article section string, or empty string if not applicable.
	 */
	public static function get_article_section(): string {
		/**
		 * Filters the Better SEO Facebook article section.
		 *
		 * @since 1.0.0
		 * @param string $section The article section string. Default empty.
		 */
		return (string) \apply_filters( 'better_seo_facebook_article_section', '' );
	}
}