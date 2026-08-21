<?php
/**
 * Better SEO - Front Feed
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
	Data,
	Helper,
	Helper\Format,
};

/**
 * Class Better_SEO\Front\Feed
 *
 * Handles feed-specific output modifications including robots headers,
 * content excerpting, and source attribution links.
 *
 * @since 1.0.0
 */
final class Feed {

	/**
	 * Outputs robots noindex headers when on a feed request.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function output_robots_noindex_headers_on_feed(): void {
		if ( \is_feed() ) {
			Helper\Headers::output_robots_noindex_headers();
		}
	}

	/**
	 * Modifies the feed content to apply excerpting and source attribution.
	 *
	 * When excerpt_the_feed is enabled, clamps the content to the configured length.
	 * When source_the_feed is enabled, appends a source attribution link.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $content   The feed content. Default empty string.
	 * @param string|null $feed_type The feed type, or null if not set.
	 * @return string The modified feed content.
	 */
	public static function modify_the_content_feed( string $content = '', ?string $feed_type = null ): string {

		if ( empty( $content ) ) {
			return '';
		}

		if ( isset( $feed_type ) && Data\Plugin::get_option( 'excerpt_the_feed' ) ) {
			/**
			 * Filters the maximum content length for feed excerpts.
			 *
			 * @since 1.0.0
			 * @param int $length The maximum character length. Default 400.
			 */
			$clamp_length = (int) \apply_filters( 'better_seo_max_content_feed_length', 400 );

			$excerpt = Format\HTML::extract_content(
				$content,
				[
					'allow_shortcodes' => false,
					'clamp'            => $clamp_length,
				],
			);

			$content = "<p>{$excerpt}</p>";
		}

		if ( Data\Plugin::get_option( 'source_the_feed' ) ) {
			$content .= \sprintf(
				"\n" . '<p><a href="%s" rel="nofollow">%s</a></p>', // Keep XHTML valid!
				\esc_url( \get_permalink() ),
				\esc_html(
					/**
					 * Filters the feed source link text.
					 *
					 * @since 1.0.0
					 * @param string $source The source indication string.
					 */
					\apply_filters(
						'better_seo_feed_source_link_text',
						\_x( 'Source', 'The content source', 'better-seo' ),
					),
				),
			);
		}

		return $content;
	}
}