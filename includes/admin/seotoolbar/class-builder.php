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

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\Data;

/**
 * Class Better_SEO\Admin\SEOToolbar\Builder
 *
 * Orchestrates SEO toolbar generation by delegating to specialized builders
 * for posts/pages and terms, collecting test results, and rendering HTML output.
 *
 * @since 1.0.0
 */
class Builder {

	public const STATE_GOOD = 'good';
	public const STATE_OKAY = 'okay';
	public const STATE_BAD = 'bad';
	public const STATE_UNKNOWN = 'unknown';
	public const STATE_UNDEFINED = 'undefined';

	private static array $query = [];
	private static array $items = [];

	public static function generate_bar( array $query ): string {

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

		$items = &self::collect_seo_toolbar_items();

		foreach ( $builder->run_all_tests( $query ) as $key => $data ) {
			$items[ $key ] = $data;
		}

		\do_action( 'better_seo_toolbar', self::class, $builder );

		$bar = self::create_seo_toolbar( self::$items );

		self::$items = [];
		$builder->clear_query_cache();

		return $bar;
	}

	public static function &collect_seo_toolbar_items(): array {
		return self::$items;
	}

	public static function register_seo_toolbar_item( string $key, array $item ): void {
		self::$items[ $key ] = $item;
	}

	public static function &edit_seo_toolbar_item( string $key ): array {
		static $_void = [];

		if ( isset( self::$items[ $key ] ) ) {
			$_item = &self::$items[ $key ];
		} else {
			$_void = [];
			$_item = &$_void;
		}

		return $_item;
	}

	private static function create_seo_toolbar( array $items ): string {

		$blocks = [];

		foreach ( self::generate_seo_toolbar_blocks( $items ) as $block ) {
			$blocks[] = $block;
		}

		return \sprintf(
			'<div class="better-seo-toolbar better-seo-tooltip-super-wrap"><span class="better-seo-toolbar-inner-wrap">%s</span></div>',
			implode( '', $blocks ),
		);
	}

	private static function generate_seo_toolbar_blocks( array $items ): \Generator {

		static $gettext = [];

		if ( ! $gettext ) {
			$gettext = [
				'assessment'  => \_x( 'assessment', 'toolbar item count', 'better-seo' ),
				'assessments' => \_x( 'assessments', 'toolbar item count', 'better-seo' ),
			];
		}

		foreach ( $items as $item ) {

			if ( empty( $item['title'] ) || empty( $item['status'] ) ) {
				continue;
			}

			$status       = $item['status'] ?? '';
			$symbol       = $item['symbol'] ?? \_x( 'X', 'toolbar item symbol', 'better-seo' );
			$assessments  = array_values( array_filter( $item['assess'] ?? [] ) ) ?: [ $item['reason'] ?? '' ];
			$count        = \count( $assessments );
			$aria         = \sprintf(
				'%s — %s %s',
				$item['title'],
				$count,
				$count < 2 ? $gettext['assessment'] : $gettext['assessments'],
			);
			$html         = \sprintf(
				'<strong>%s</strong><div>%s</div>',
				\esc_html( $item['title'] ),
				\implode( '<div>', array_map( '\\esc_html', $assessments ) ) . \str_repeat( '</div>', $count ),
			);

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
