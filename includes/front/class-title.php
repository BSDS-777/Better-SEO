<?php
/**
 * Better SEO - Front Title
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
	Helper\Query,
	Meta,
};

/**
 * Class Better_SEO\Front\Title
 *
 * Handles document title output for Better SEO by overwriting
 * WordPress's default title filters with Better SEO's title generation.
 *
 * @since 1.0.0
 */
final class Title {

	/**
	 * Overwrites WordPress title filters with Better SEO's title output.
	 *
	 * Removes all existing pre_get_document_title and wp_title filters,
	 * then registers Better SEO's set_document_title callback.
	 * Bails if the current query does not support SEO or if the
	 * better_seo_overwrite_titles filter returns false.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function overwrite_title_filters(): void {

		if (
			! Query\Utils::query_supports_seo()
			|| ! \apply_filters( 'better_seo_overwrite_titles', true )
		) {
			return;
		}

		\remove_all_filters( 'pre_get_document_title', false );
		\add_filter( 'pre_get_document_title', [ self::class, 'set_document_title' ], 10 );

		\remove_all_filters( 'wp_title', false );
		\add_filter( 'wp_title', [ self::class, 'set_document_title' ], 9 );
	}

	/**
	 * Returns the Better SEO document title, escaped for HTML output.
	 *
	 * Applies the better_seo_pre_get_document_title filter before escaping.
	 *
	 * @since 1.0.0
	 *
	 * @return string The escaped document title.
	 */
	public static function set_document_title(): string {
		/**
		 * Filters the Better SEO document title before output.
		 *
		 * @since 1.0.0
		 *
		 * @param string $title   The generated document title.
		 * @param int    $post_id The current post ID.
		 */
		return \esc_html( \apply_filters(
			'better_seo_pre_get_document_title',
			Meta\Title::get_title(),
			Query::get_the_real_id(),
		) );
	}
}