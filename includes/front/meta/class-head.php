<?php
/**
 * Better SEO - Front Meta Head
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Front\Meta
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

namespace Better_SEO\Front\Meta;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\{
	memo,
	_bootstrap_timer,
};

use Better_SEO\{
	Data,
	Helper\Query,
};

/**
 * Class Better_SEO\Front\Meta\Head
 *
 * Handles the output of Better SEO meta tags in the document head,
 * including generator pool selection, tag rendering, and plugin indicators.
 *
 * @since 1.0.0
 */
final class Head {

	/**
	 * Prints the Better SEO meta tag wrap and all registered tags.
	 *
	 * Fires before/after output actions and prints the plugin indicator
	 * with optional timing information.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function print_wrap_and_tags(): void {

		if ( ! Query\Utils::query_supports_seo() ) {
			return;
		}

		/**
		 * Fires before Better SEO meta output begins.
		 *
		 * @since 1.0.0
		 */
		\do_action( 'better_seo_do_before_output' );

		$bootstrap_timer = _bootstrap_timer();
		$print_start     = hrtime( true );

		self::print_plugin_indicator( 'before' );

		self::print_tags();

		self::print_plugin_indicator(
			'after',
			( hrtime( true ) - $print_start ) / 1e9,
			$bootstrap_timer,
		);

		/**
		 * Fires after Better SEO meta output completes.
		 *
		 * @since 1.0.0
		 */
		\do_action( 'better_seo_do_after_output' );
	}

	/**
	 * Prints all Better SEO meta tags for the current query context.
	 *
	 * Selects the appropriate generator pools based on query type,
	 * applies filters, fills render data, and outputs all tags.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function print_tags(): void {

		/**
		 * Fires before Better SEO meta tags are output.
		 *
		 * @since 1.0.0
		 */
		\do_action( 'better_seo_before_meta_output' );

		$generator_pools = match ( true ) {
			\is_search()                        => [ 'Robots', 'URI', 'Open_Graph', 'Theme_Color', 'Webmasters', 'Schema' ],
			Query\Utils::is_query_exploited()   => [ 'Robots', 'Advanced_Query_Protection', 'Theme_Color', 'Webmasters' ],
			\is_404()                           => [ 'Robots', 'Theme_Color', 'Webmasters', 'Schema' ],
			default                             => [
				'Robots',
				'URI',
				'Description',
				'Theme_Color',
				'Open_Graph',
				'Facebook',
				'Twitter',
				'Webmasters',
				'Schema',
			],
		};

		$remove_pools = [];

		if ( ! Data\Plugin::get_option( 'og_tags' ) ) {
			$remove_pools[] = 'Open_Graph';
		}

		if ( ! Data\Plugin::get_option( 'facebook_tags' ) ) {
			$remove_pools[] = 'Facebook';
		}

		if ( ! Data\Plugin::get_option( 'twitter_tags' ) ) {
			$remove_pools[] = 'Twitter';
		}

		/**
		 * Filters the Better SEO meta generator pools.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, string> $generator_pools The active generator pool names.
		 */
		$generator_pools = \apply_filters(
			'better_seo_meta_generator_pools',
			$remove_pools ? array_diff( $generator_pools, $remove_pools ) : $generator_pools,
		);

		$tag_generators   = &Tags::tag_generators();
		$generators_queue = [];

		foreach ( $generator_pools as $pool ) {
			$generators_queue[] = ( "\\Better_SEO\\Front\\Meta\\Generator\\{$pool}" )::GENERATORS;
		}

		/**
		 * Filters the Better SEO meta tag generators.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, callable> $generators    The merged generator callbacks.
		 * @param array<int, string>      $generator_pools The active generator pool names.
		 */
		$tag_generators = \apply_filters(
			'better_seo_meta_generators',
			array_merge( ...$generators_queue ),
			$generator_pools,
		);

		Tags::fill_render_data_from_registered_generators();

		/**
		 * Filters the Better SEO meta tag render data.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $tags_render_data The render data array (by reference).
		 * @param array<string, callable> $tag_generators The registered tag generators.
		 */
		$tags_render_data = \apply_filters( // phpcs:ignore Generic.Formatting -- bug in PHPCS.
			'better_seo_meta_render_data',
			$tags_render_data = &Tags::tags_render_data(),
			$tag_generators,
		);

		Tags::render_tags();

		/**
		 * Fires after Better SEO meta tags are output.
		 *
		 * @since 1.0.0
		 */
		\do_action( 'better_seo_after_meta_output' );
	}

	/**
	 * Prints the Better SEO plugin indicator HTML comment.
	 *
	 * Outputs an opening or closing HTML comment with the plugin name
	 * and optional timing information.
	 *
	 * @since 1.0.0
	 *
	 * @param string $where            Whether to print 'before' or 'after' the tags.
	 * @param float  $meta_timer      Time in seconds spent rendering meta tags. Default 0.
	 * @param float  $bootstrap_timer Time in seconds spent bootstrapping. Default 0.
	 * @return void
	 */
	private static function print_plugin_indicator( string $where = 'before', float $meta_timer = 0, float $bootstrap_timer = 0 ): void {

		$cache = memo() ?? memo( [
			/**
			 * Filters whether to show the Better SEO plugin indicator comment.
			 *
			 * @since 1.0.0
			 * @param bool $show Whether to show the indicator. Default true.
			 */
			'run'        => (bool) \apply_filters( 'better_seo_indicator', true ),
			/**
			 * Filters whether to show timing information in the plugin indicator.
			 *
			 * @since 1.0.0
			 * @param bool $show Whether to show timing. Default true.
			 */
			'show_timer' => (bool) \apply_filters( 'better_seo_indicator_timing', true ),
			'annotation' => \esc_html( trim( vsprintf(
				/* translators: 1 = Better SEO, 2 = 'by Brian Smith' */
				\__( '%1$s %2$s', 'better-seo' ),
				[
					'Better SEO',
					\apply_filters( 'better_seo_indicator_author', true )
						? \__( 'by Brian Smith', 'better-seo' )
						: '',
				]
			) ) ),
		] );

		if ( ! $cache['run'] ) {
			return;
		}

		match ( $where ) {
			'before' => static function() use ( $cache ): void {
				echo "\n<!-- {$cache['annotation']} -->\n";
			},
			'after' => static function() use ( $cache, $meta_timer, $bootstrap_timer ): void {
				if ( $cache['show_timer'] && $meta_timer && $bootstrap_timer ) {
					$timers = \sprintf(
						' | %s meta | %s boot',
						number_format( $meta_timer * 1e3, 2, null, '' ) . 'ms',
						number_format( $bootstrap_timer * 1e3, 2, null, '' ) . 'ms',
					);
				} else {
					$timers = '';
				}

				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped earlier.
				echo "<!-- / {$cache['annotation']}{$timers} -->\n\n";
			},
			default => static fn() => null,
		}();
	}
}