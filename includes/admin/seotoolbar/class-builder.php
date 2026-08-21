<?php
/**
 * Better SEO - Admin SEO Toolbar Builder
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Admin\SEOToolbar
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

namespace Better_SEO\Admin\SEOToolbar;

\defined( 'BETTER_SEO_PRESENT' ) or exit;

use Better_SEO\Data;

/**
 * Class Better_SEO\Admin\SEOToolbar\Builder
 *
 * Public API for building and rendering SEO Toolbar output.
 * Delegates test execution to post/term-specific builder subclasses,
 * collects results, and renders the toolbar HTML.
 *
 * @since 1.0.0
 */
final class Builder {

	/**
	 * Test state constants.
	 *
	 * @since 1.0.0
	 */
	public const STATE_GOOD = 'good';
	public const STATE_OKAY = 'okay';
	public const STATE_BAD  = 'bad';

	/**
	 * Items collected from all registered tests.
	 *
	 * @since 1.0.0
	 * @var   array<string, array<string, mixed>>
	 */
	private static array $items = [];

	/**
	 * The current query being processed.
	 *
	 * @since 1.0.0
	 * @var   array<string, mixed>
	 */
	private static array $query = [];

	/**
	 * Generates SEO Toolbar HTML for a given query (post, term, user, etc).
	 *
	 * Routes to the appropriate builder (post/page or term) based on query args.
	 * Runs all registered tests, fires the better_seo_toolbar hook, and renders
	 * the toolbar HTML.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $query The query args (id, taxonomy, post_type, etc).
	 * @return string The complete toolbar HTML, or empty string if no ID.
	 */
	public static function generate_bar( array $query ): string {

		// Link the input query for action hooks.
		self::$query = &$query;

		$query += [
			'id'        => 0,
			'tax'       => $query['taxonomy'] ?? '',
			'taxonomy'  => $query['tax'] ?? '',
			'pta'       => '',
			'post_type' => '',
		];

		if ( empty( $query['id'] ) ) {
			return '';
		}

		if ( empty( $query['tax'] ) ) {
			$query['post_type'] = $query['post_type'] ?: \get_post_type( $query['id'] );
		}

		$builder = $query['tax']
			? Builder\Term::get_instance()
			: Builder\Page::get_instance();

		\do_action( 'better_seo_prepare_toolbar', self::class, $builder );

		$items = &self::collect_toolbar_items();

		foreach ( $builder->run_all_tests( $query ) as $key => $data ) {
			$items[ $key ] = $data;
		}

		\do_action( 'better_seo_toolbar', self::class, $builder );

		$bar = self::create_toolbar( self::$items );

		// Clear items and cache to prevent memory leaks between requests.
		self::$items = [];
		$builder->clear_query_cache();

		return $bar;
	}

	/**
	 * Returns a reference to the current toolbar items array.
	 *
	 * Allows external code to append items via reference.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>> Reference to the items array.
	 */
	public static function &collect_toolbar_items(): array {
		return self::$items;
	}

	/**
	 * Registers a single toolbar item by key.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $key  The item key.
	 * @param array<string, mixed> $item The item definition array.
	 * @return void
	 */
	public static function register_toolbar_item( string $key, array $item ): void {
		self::$items[ $key ] = $item;
	}

	/**
	 * Returns a reference to a specific toolbar item for editing.
	 *
	 * Returns a reference to a void array if the key does not exist,
	 * preventing accidental creation of new items via reference assignment.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The item key to edit.
	 * @return array<string, mixed> Reference to the item, or a void array if not found.
	 */
	public static function &edit_toolbar_item( string $key ): array {
		static $_void = [];

		if ( isset( self::$items[ $key ] ) ) {
			$_item = &self::$items[ $key ];
		} else {
			$_void = [];
			$_item = &$_void;
		}

		return $_item;
	}

	/**
	 * Builds and returns the full toolbar HTML wrapper from item definitions.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<string, mixed>> $items The toolbar item definitions.
	 * @return string The complete toolbar HTML string.
	 */
	private static function create_toolbar( array $items ): string {

		$blocks = [];

		foreach ( self::generate_toolbar_blocks( $items ) as $block ) {
			$blocks[] = $block;
		}

		// Always return the wrapper — may be populated via JS in the future.
		return \sprintf(
			'<div class="better-seo-toolbar better-seo-tooltip-super-wrap"><span class="better-seo-toolbar-inner-wrap">%s</span></div>',
			implode( '', $blocks ),
		);
	}

	/**
	 * Generates toolbar block HTML strings for each item.
	 *
	 * Uses a static cache for translated strings and symbol settings
	 * to avoid repeated lookups across items.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<string, mixed>> $items The toolbar item definitions.
	 * @return \Generator<int, string>
	 */
	private static function generate_toolbar_blocks( array $items ): \Generator {

		static $gettext = null;
		static $use_symbols = null;

		if ( null === $gettext ) {
			$gettext = [
				'assessment'  => \esc_html__( 'Assessment', 'better-seo' ),
				'assessments' => \esc_html__( 'Assessments', 'better-seo' ),
			];
			$use_symbols = (bool) Data\Plugin::get_option( 'seo_bar_symbols' );
		}

		foreach ( $items as $item ) {

			$status = $item['status'] ?? self::STATE_BAD;
			$symbol = $item['symbol'] ?? '?';
			$title  = $item['title'] ?? '';
			$reason = $item['reason'] ?? '';

			$assessments = [];
			if ( isset( $item['assess'] ) && \is_array( $item['assess'] ) ) {
				$assessments = array_values( $item['assess'] );
			}

			$count = \count( $assessments );
			$html  = $reason ? \sprintf( '%s<br/>', \esc_html( $reason ) ) : '';
			$html .= $count ? \sprintf(
				'<strong>%s</strong><br/>%s',
				\esc_html( $gettext[ $count < 2 ? 'assessment' : 'assessments' ] ),
				\esc_html( \implode( '<br/>', $assessments ) ),
			) : '';

			$aria = \sprintf(
				'%s — %s',
				\esc_attr( $title ),
				$count < 2 ? $gettext['assessment'] : $gettext['assessments'],
			);

			if ( $use_symbols ) {
				$aria = \sprintf(
					'%s — %s: %s',
					\esc_attr( $title ),
					\esc_attr( $symbol ),
					\esc_attr(
						$count < 2 ? $gettext['assessment'] : $gettext['assessments']
					),
			);
			}

			yield \sprintf(
				'<span class="better-seo-toolbar-section-wrap better-seo-tooltip-wrap"><span class="better-seo-toolbar-item better-seo-tooltip-item better-seo-toolbar-%1$s" title="%2$s" aria-label="%2$s" data-desc="%3$s" tabindex="0">%4$s</span></span>',
				$status,
				\esc_attr( $aria ),
				\esc_attr( $html ),
				\esc_html( $symbol ),
			);
		}
	}
}
