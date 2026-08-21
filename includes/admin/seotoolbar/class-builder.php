<?php
/**
 * Better SEO - Admin SEO Bar Builder
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Admin\SEOBar
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

namespace Better_SEO\Admin\SEOBar;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\Data;

/**
 * Class Better_SEO\Admin\SEOBar\Builder
 *
 * Interprets Better SEO Bar item data and renders it as an HTML bar element.
 * Supports post, page, and taxonomy term contexts.
 *
 * @since 1.0.0
 */
final class Builder {

	/**
	 * Status: undefined — no data available to assess.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	public const STATE_UNDEFINED = 0xff4500;

	/**
	 * Status: unknown — data exists but cannot be assessed.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	public const STATE_UNKNOWN = 0x8b0000;

	/**
	 * Status: bad — assessment failed.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	public const STATE_BAD = 0xff0000;

	/**
	 * Status: okay — assessment passed with warnings.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	public const STATE_OKAY = 0x85bb65;

	/**
	 * Status: good — assessment passed.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	public const STATE_GOOD = 0x008000;

	/**
	 * The collected SEO Bar item definitions for the current bar.
	 *
	 * @since 1.0.0
	 * @var   array<string, array<string, mixed>>
	 */
	private static array $items = [];

	/**
	 * The current SEO Bar query arguments.
	 *
	 * @since 1.0.0
	 * @var   array<string, mixed>
	 */
	public static array $query = [];

	/**
	 * Generates and returns the full SEO Bar HTML for the given query.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $query The query arguments (id, tax, taxonomy, pta, post_type).
	 * @return string The generated SEO Bar HTML, or empty string if no ID is provided.
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

		\do_action( 'better_seo_prepare_seo_bar', self::class, $builder );

		$items = &self::collect_seo_bar_items();

		foreach ( $builder->run_all_tests( $query ) as $key => $data ) {
			$items[ $key ] = $data;
		}

		\do_action( 'better_seo_seo_bar', self::class, $builder );

		$bar = self::create_seo_bar( self::$items );

		// Clear items and cache to prevent memory leaks between requests.
		self::$items = [];
		$builder->clear_query_cache();

		return $bar;
	}

	/**
	 * Returns a reference to the current SEO Bar items array.
	 *
	 * Allows external code to append items via reference.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>> Reference to the items array.
	 */
	public static function &collect_seo_bar_items(): array {
		return self::$items;
	}

	/**
	 * Registers a single SEO Bar item by key.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $key  The item key.
	 * @param array<string, mixed> $item The item definition array.
	 * @return void
	 */
	public static function register_seo_bar_item( string $key, array $item ): void {
		self::$items[ $key ] = $item;
	}

	/**
	 * Returns a reference to a specific SEO Bar item for editing.
	 *
	 * Returns a reference to a void array if the key does not exist,
	 * preventing accidental creation of new items via reference assignment.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key The item key to edit.
	 * @return array<string, mixed> Reference to the item, or a void array if not found.
	 */
	public static function &edit_seo_bar_item( string $key ): array {
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
	 * Builds and returns the full SEO Bar HTML wrapper from item definitions.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<string, mixed>> $items The SEO Bar item definitions.
	 * @return string The complete SEO Bar HTML string.
	 */
	private static function create_seo_bar( array $items ): string {

		$blocks = [];

		foreach ( self::generate_seo_bar_blocks( $items ) as $block ) {
			$blocks[] = $block;
		}

		// Always return the wrapper — may be populated via JS in the future.
		return \sprintf(
			'<div class="better-seo-seo-bar better-seo-tooltip-super-wrap"><span class="better-seo-seo-bar-inner-wrap">%s</span></div>',
			implode( '', $blocks ),
		);
	}

	/**
	 * Generates SEO Bar block HTML strings for each item.
	 *
	 * Uses a static cache for translated strings and symbol settings
	 * to avoid repeated lookups across items.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<string, mixed>> $items The SEO Bar item definitions.
	 * @return \Generator Yields HTML block strings for each item.
	 */
	private static function generate_seo_bar_blocks( array $items ): \Generator {

		static $gettext, $use_symbols;

		$gettext ??= [
			/* translators: 1 = SEO Bar type title, 2 = Status reason, 3 = Assessments */
			'aria'        => \_x( '%1$s: %2$s %3$s', 'SEO Bar ARIA assessment enumeration', 'better-seo' ),
			/* translators: 1 = Assessment number, 2 = Assessment explanation */
			'enum'        => \_x( '%1$d: %2$s', 'assessment enumeration', 'better-seo' ),
			/* translators: 1 = Assessment(s) label, 2 = A list of assessments */
			'list'        => \_x( '%1$s: %2$s', 'assessment list', 'better-seo' ),
			'assessment'  => \__( 'Assessment', 'better-seo' ),
			'assessments' => \__( 'Assessments', 'better-seo' ),
		];

		$use_symbols ??= (bool) Data\Plugin::get_option( 'seo_bar_symbols' );

		foreach ( $items as $item ) {

			$status = match ( $item['status'] ) {
				self::STATE_GOOD      => 'good',
				self::STATE_OKAY      => 'okay',
				self::STATE_BAD       => 'bad',
				self::STATE_UNKNOWN   => 'unknown',
				default               => 'undefined',
			};

			if ( $use_symbols && $item['status'] ^ self::STATE_GOOD ) {
				$symbol = match ( $item['status'] ) {
					self::STATE_OKAY    => '!?',
					self::STATE_BAD     => '!!',
					self::STATE_UNKNOWN => '??',
					default             => '--',
				};
			} else {
				$symbol = $item['symbol'];
			}

			$html = \sprintf(
				'<strong>%s:</strong> %s<br>%s',
				\esc_html( $item['title'] ),
				\esc_html( $item['reason'] ),
				\sprintf(
					'<ol>%s</ol>',
					implode(
						'',
						array_map(
							static fn( string $a ): string => '<li>' . \esc_html( $a ) . '</li>',
							$item['assess'],
						),
					),
				),
			);

			$count       = \count( $item['assess'] );
			$assessments = [];

			if ( $count < 2 ) {
				$assessments[] = reset( $item['assess'] );
			} else {
				$i = 0;
				foreach ( $item['assess'] as $text ) {
					$assessments[] = \sprintf( $gettext['enum'], ++$i, $text );
				}
			}

			$aria = \sprintf(
				$gettext['aria'],
				$item['title'],
				$item['reason'],
				\sprintf(
					$gettext['list'],
					$count < 2 ? $gettext['assessment'] : $gettext['assessments'],
					implode( ' ', $assessments ),
				),
			);

			yield \sprintf(
				'<span class="better-seo-seo-bar-section-wrap better-seo-tooltip-wrap"><span class="better-seo-seo-bar-item better-seo-tooltip-item better-seo-seo-bar-%1$s" title="%2$s" aria-label="%2$s" data-desc="%3$s" tabindex="0">%4$s</span></span>',
				$status,
				\esc_attr( $aria ),
				\esc_attr( $html ),
				\esc_html( $symbol ),
			);
		}
	}
}