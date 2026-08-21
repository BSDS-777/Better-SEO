<?php
/**
 * Better SEO - Meta Title Utils
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Meta\Title
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

namespace Better_SEO\Meta\Title;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\{
	get_query_type_from_args,
	normalize_generation_args,
};

use Better_SEO\Data;

/**
 * Class Better_SEO\Meta\Title\Utils
 *
 * Provides utility methods for Better SEO title generation, including
 * the title separator list and WordPress title filter management.
 *
 * @since 1.0.0
 */
class Utils {

	/**
	 * Returns the list of available title separator options.
	 *
	 * Applies the better_seo_separator_list filter.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Map of separator slug to HTML entity string.
	 */
	public static function get_separator_list(): array {
		/**
		 * Filters the Better SEO title separator list.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $separators Map of separator slug to HTML entity.
		 */
		return (array) \apply_filters(
			'better_seo_separator_list',
			[
				'hyphen' => '&#x2d;',
				'pipe'   => '|',
				'ndash'  => '&ndash;',
				'mdash'  => '&mdash;',
				'bull'   => '&bull;',
				'middot' => '&middot;',
				'lsaquo' => '&lsaquo;',
				'rsaquo' => '&rsaquo;',
				'frasl'  => '&frasl;',
				'laquo'  => '&laquo;',
				'raquo'  => '&raquo;',
				'le'     => '&le;',
				'ge'     => '&ge;',
				'lt'     => '&lt;',
				'gt'     => '&gt;',
			],
		);
	}

	/**
	 * Removes default WordPress title filters (wptexturize, strip_tags) for Better SEO generation.
	 *
	 * Stores removed filters so they can be restored via reset_default_title_filters().
	 * When $reset is true, restores all previously removed filters.
	 *
	 * @since 1.0.0
	 *
	 * @param bool                      $reset Whether to restore previously removed filters. Default false.
	 * @param array<string, mixed>|null $args  Optional generation args to limit which filters are removed. Default null.
	 * @return void
	 */
	public static function remove_default_title_filters( bool $reset = false, ?array $args = null ): void {

		static $filtered = [];

		if ( $reset ) {
			foreach ( $filtered as [ $filter, $function, $priority ] ) {
				\add_filter( $filter, $function, $priority );
			}

			// Reset the stored filters list.
			$filtered = [];
		} else {
			if ( isset( $args ) ) {
				normalize_generation_args( $args );

				$filters = match ( get_query_type_from_args( $args ) ) {
					'single' => [ 'single_post_title' ],
					'term'   => match ( $args['tax'] ) {
						'category' => [ 'single_cat_title' ],
						'post_tag' => [ 'single_tag_title' ],
						default    => [],
					},
					default  => [],
				};
			} else {
				$filters = [ 'single_post_title', 'single_cat_title', 'single_tag_title' ];
			}

			$functions = [ 'wptexturize' ];

			if ( ! Data\Plugin::get_option( 'title_strip_tags' ) ) {
				$functions[] = 'strip_tags';
			}

			foreach ( ( $filters ?? [] ) as $filter ) {
				foreach ( $functions as $function ) {
					// Safety limit: max 10 iterations per filter/function pair.
					$limit = 10;
					$i     = 0;

					while ( false !== ( $priority = \has_filter( $filter, $function ) ) ) {
						$filtered[] = [ $filter, $function, $priority ];
						\remove_filter( $filter, $function, $priority );

						if ( ++$i > $limit ) {
							break;
						}
					}
				}
			}
		}
	}

	/**
	 * Restores all WordPress title filters previously removed by remove_default_title_filters().
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function reset_default_title_filters(): void {
		self::remove_default_title_filters( true );
	}
}