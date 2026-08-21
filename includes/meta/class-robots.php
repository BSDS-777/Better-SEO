<?php
/**
 * Better SEO - Meta Robots
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
	umemo,
	normalize_generation_args,
};

use Better_SEO\{
	Data,
	Helper\Query,
};

/**
 * Class Better_SEO\Meta\Robots
 *
 * Provides robots meta tag generation for Better SEO, including noindex,
 * nofollow, noarchive, and copyright directives for the current query context.
 *
 * @since 1.0.0
 */
class Robots {

	/**
	 * Returns the collected meta assertions from the robots main instance.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The collected meta assertions array.
	 */
	public static function get_collected_meta_assertions(): array {
		return Robots\Main::instance()->collect_assertions();
	}

	/**
	 * Returns the robots meta string for the current page, memoized.
	 *
	 * Returns empty string if the blog is not public.
	 *
	 * @since 1.0.0
	 *
	 * @return string The comma-separated robots meta string.
	 */
	public static function get_meta(): string {
		return umemo( __METHOD__ ) ?? umemo(
			__METHOD__,
			Data\Blog::is_public()
				? implode( ',', self::get_generated_meta() )
				: '',
		);
	}

	/**
	 * Returns the generated robots meta array for the given args or current query context.
	 *
	 * Converts boolean values to directive strings (e.g. ['noindex' => true] → ['noindex' => 'noindex']).
	 * Applies the better_seo_robots_meta_array filter.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args    Optional generation args. Default null.
	 * @param array<int, string>|null   $get     Optional list of directives to retrieve. Default null (all).
	 * @param int                       $options Bitmask options for robots generation. Default 0b00.
	 * @return array<string, string> The filtered robots meta directive array.
	 */
	public static function get_generated_meta( ?array $args = null, ?array $get = null, int $options = 0b00 ): array {

		normalize_generation_args( $args );

		$meta = Robots\Main::instance()->set( $args, $options )->get( $get );

		foreach ( $meta as $k => $v ) {
			switch ( $k ) {
				case 'noindex':
				case 'nofollow':
				case 'noarchive':
					if ( $v ) {
						// Convert [ 'noindex' => true ] to [ 'noindex' => 'noindex' ].
						$meta[ $k ] = $k;
					}
					break;

				case 'max_snippet':
				case 'max_image_preview':
				case 'max_video_preview':
					if ( false !== $v ) {
						// Convert [ 'max_snippet' => x ] to [ 'max-snippet' => 'max-snippet:x' ].
						$meta[ $k ] = str_replace( '_', '-', $k ) . ":{$v}";
					}
			}
		}

		/**
		 * Filters the Better SEO robots meta array.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed>      $meta    The robots meta directive array.
		 * @param array<string, mixed>|null $args    The generation args, or null.
		 * @param int                       $options The bitmask options.
		 */
		return array_filter( (array) \apply_filters(
			'better_seo_robots_meta_array',
			$meta,
			$args,
			$options,
		) );
	}

	/**
	 * Returns whether a robots directive is set for the given post type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type      The robots directive type ('noindex', 'nofollow', 'noarchive').
	 * @param string $post_type Optional post type slug. Defaults to current post type.
	 * @return bool True if the robots directive is set for the post type.
	 */
	public static function is_post_type_robots_set( string $type, string $post_type = '' ): bool {
		return (bool) Data\Plugin::get_option(
			Data\Plugin\Helper::get_robots_option_index( 'post_type', $type ),
			$post_type ?: Query::get_current_post_type(),
		);
	}

	/**
	 * Returns whether a robots directive is set for the given taxonomy.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type     The robots directive type ('noindex', 'nofollow', 'noarchive').
	 * @param string $taxonomy Optional taxonomy slug. Defaults to current taxonomy.
	 * @return bool True if the robots directive is set for the taxonomy.
	 */
	public static function is_taxonomy_robots_set( string $type, string $taxonomy = '' ): bool {
		return (bool) Data\Plugin::get_option(
			Data\Plugin\Helper::get_robots_option_index( 'taxonomy', $type ),
			$taxonomy ?: Query::get_current_taxonomy(),
		);
	}
}