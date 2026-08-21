<?php
/**
 * Better SEO - API Functions
 *
 * @package    Better_SEO
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

// ─── GLOBAL NAMESPACE ──────────────────────────────────────────────────────────

namespace {

	\defined( 'BETTER_SEO_PRESENT' ) or exit;

	/**
	 * Returns the Better SEO plugin instance.
	 *
	 * @since 1.0.0
	 *
	 * @return \Better_SEO\Load The Better SEO Load instance.
	 */
	function better_seo(): \Better_SEO\Load {
		return \Better_SEO\Load::get_instance();
	}

	/**
	 * Returns the current Better SEO database version string.
	 *
	 * @since 1.0.0
	 *
	 * @return string The database version string, or '0' if not set.
	 */
	function better_seo_db_version(): string {
		return (string) get_option( 'better_seo_upgraded_db_version', '0' );
	}

	/**
	 * Returns the class name of the Better SEO plugin instance.
	 *
	 * @since 1.0.0
	 *
	 * @return string The fully qualified class name of the Better SEO instance.
	 */
	function better_seo_class(): string {
		return get_class( better_seo() );
	}

	/**
	 * Outputs a Better SEO breadcrumb trail via shortcode.
	 *
	 * Supports the following shortcode attributes:
	 * - sep:   The separator character between breadcrumb items. Default '›'.
	 * - home:  The label for the home breadcrumb item. Default translated 'Home'.
	 * - class: The CSS class for the nav element. Default 'better-seo-breadcrumb'.
	 * - title: Set to 'meta' to use the SEO meta title for breadcrumb labels.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $atts The shortcode attributes.
	 * @return string The breadcrumb HTML output.
	 */
	function better_seo_breadcrumb( array $atts = [] ): string {

		$atts = shortcode_atts(
			[
				'sep'   => '›',
				'home'  => __( 'Home', 'better-seo' ),
				'class' => 'better-seo-breadcrumb',
				'title' => null,
			],
			$atts,
			'better_seo_breadcrumb',
		);

		// Sanitize the CSS class to only allow valid class name characters.
		preg_match( '/-?[a-z_]+[a-z\d_-]*/i', $atts['class'], $matches );

		$class = $matches[0] ?? 'better-seo-breadcrumb';
		$sep   = esc_html( $atts['sep'] );

		$options = [
			'use_meta_title' => isset( $atts['title'] ) ? 'meta' === $atts['title'] : null,
		];

		$crumbs = \Better_SEO\Meta\Breadcrumbs::get_breadcrumb_list( null, $options );
		$count  = count( $crumbs );
		$items  = [];

		$home = \Better_SEO\coalesce_strlen( $atts['home'] ) ?? $crumbs[0]['name'];

		if ( 1 === $count ) {
			$items[] = sprintf(
				'<span aria-current="page">%s</span>',
				esc_html( $home ),
			);
		} else {
			foreach ( $crumbs as $i => $crumb ) {
				if ( ( $count - 1 ) === $i ) {
					$items[] = sprintf(
						'<span aria-current="page">%s</span>',
						esc_html( $crumb['name'] ),
					);
				} else {
					$items[] = sprintf(
						'<a href="%s">%s</a>',
						esc_url( $crumb['url'] ),
						esc_html( 0 === $i ? $home : $crumb['name'] ),
					);
				}
			}
		}

		$html = '';
		foreach ( $items as $item ) {
			$html .= <<<HTML
				<li class="breadcrumb-item">{$item}</li>
				HTML;
		}

		/**
		 * Filters the CSS rules for the Better SEO breadcrumb shortcode.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, array<int, string>> $css   Map of CSS selector to declaration array.
		 * @param string                            $class The breadcrumb nav CSS class.
		 */
		$css = (array) apply_filters(
			'better_seo_breadcrumb_shortcode_css',
			[
				"nav.{$class} ol"                            => [
					'display:inline',
					'list-style:none',
					'margin-inline-start:0',
				],
				"nav.{$class} ol li"                         => [
					'display:inline',
				],
				"nav.{$class} ol li:not(:last-child)::after" => [
					"content:'{$sep}'",
					'margin-inline-end:1ch',
					'margin-inline-start:1ch',
				],
			],
			$class,
		);

		$styles = '';

		foreach ( $css as $selector => $declaration ) {
			$styles .= sprintf(
				'%s{%s}',
				$selector,
				implode( ';', $declaration ),
			);
		}

		$style = "<style>{$styles}</style>";
		$nav   = <<<HTML
			<nav aria-label="Breadcrumb" class="{$class}"><ol>{$html}</ol></nav>
			HTML;

		/**
		 * Filters the final HTML output of the Better SEO breadcrumb shortcode.
		 *
		 * @since 1.0.0
		 *
		 * @param string                            $output The full breadcrumb HTML output.
		 * @param array<int, array<string, string>> $crumbs The breadcrumb items array.
		 * @param string                            $nav    The nav element HTML.
		 * @param string                            $style  The inline style block HTML.
		 */
		return apply_filters(
			'better_seo_breadcrumb_shortcode_output',
			"{$nav}{$style}",
			$crumbs,
			$nav,
			$style,
		);
	}
}

// ─── BETTER_SEO NAMESPACE ──────────────────────────────────────────────────────

namespace Better_SEO {

	/**
	 * Returns whether Better SEO is running in headless mode for the given type.
	 *
	 * When the BETTER_SEO_HEADLESS constant is defined, all headless types default
	 * to true. If BETTER_SEO_HEADLESS is an array, its values override the defaults.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $type The headless type to check ('meta', 'settings', 'user').
	 *                          Pass null to return the full headless state array.
	 * @return bool|array<string, bool> True/false for a specific type, or the full state array.
	 */
	function is_headless( ?string $type = null ): bool|array {

		static $is_headless;

		if ( ! isset( $is_headless ) ) {
			if ( \defined( 'BETTER_SEO_HEADLESS' ) ) {
				$is_headless = [
					'meta'     => true,
					'settings' => true,
					'user'     => true,
				];

				if ( \is_array( \BETTER_SEO_HEADLESS ) ) {
					$is_headless = array_map(
						'wp_validate_boolean',
						array_merge( $is_headless, \BETTER_SEO_HEADLESS ),
					);
				}
			} else {
				$is_headless = [
					'meta'     => false,
					'settings' => false,
					'user'     => false,
				];
			}
		}

		return isset( $type )
			? $is_headless[ $type ] ?? false
			: $is_headless;
	}

	/**
	 * Normalizes generation args to ensure all required keys are present.
	 *
	 * Modifies the $args array in-place to add default values for missing keys.
	 * Sets $args to null if it is not an array (indicating current query context).
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args The generation args to normalize (passed by reference).
	 * @return void
	 */
	function normalize_generation_args( mixed &$args ): void {

		if ( \is_array( $args ) ) {
			$args += [
				'id'       => 0,
				'tax'      => $args['taxonomy'] ?? '',
				'taxonomy' => $args['tax'] ?? '',
				'pta'      => '',
				'uid'      => 0,
			];
		} else {
			$args = null;
		}
	}

	/**
	 * Returns the query type string for the given generation args.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args The normalized generation args.
	 * @return string The query type: 'user', 'pta', 'homeblog', 'term', or 'single'.
	 */
	function get_query_type_from_args( array $args ): string {

		if ( empty( $args['id'] ) ) {
			if ( $args['uid'] ) {
				return 'user';
			}

			if ( $args['pta'] ) {
				return 'pta';
			}

			return 'homeblog';
		} elseif ( $args['tax'] ) {
			return 'term';
		}

		return 'single';
	}

	/**
	 * Returns the string if it has length, or null if it is empty.
	 *
	 * @since 1.0.0
	 *
	 * @param string $string The string to check.
	 * @return string|null The original string if non-empty, or null.
	 */
	function coalesce_strlen( string $string ): ?string {
		return \strlen( $string ) ? $string : null;
	}

	/**
	 * Returns whether the given caller has already run in this request, and marks it as run.
	 *
	 * Uses a static array keyed by caller string. Returns false on first call
	 * (marking it as run), and true on all subsequent calls.
	 *
	 * @since 1.0.0
	 *
	 * @param string $caller The caller identifier (typically __METHOD__ or __FUNCTION__).
	 * @return bool False on first call, true if already run.
	 */
	function has_run( string $caller ): bool {
		static $ran = [];
		return $ran[ $caller ] ?? ! ( $ran[ $caller ] = true );
	}

	/**
	 * Gets or sets a memoized value keyed by call site and optional extra args.
	 *
	 * Uses debug_backtrace() to determine the call site (file + line), combined
	 * with any extra $args, to build a unique hash for the memo entry.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value_to_set The value to store. Pass null to retrieve.
	 * @param mixed ...$args      Additional args to include in the memo key.
	 * @return mixed The stored value, or null if not yet set.
	 */
	function memo( mixed $value_to_set = null, mixed ...$args ): mixed {

		static $memo = [];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- required for call-site keying.
		$hash = serialize(
			[
				'args' => $args,
				'file' => 0,
				'line' => 0,
			]
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions -- required for call-site keying.
			+ debug_backtrace( \DEBUG_BACKTRACE_IGNORE_ARGS, 2 )[1],
		);

		if ( isset( $value_to_set ) ) {
			return $memo[ $hash ] = $value_to_set;
		}

		return $memo[ $hash ] ?? null;
	}

	/**
	 * Gets or sets a memoized value keyed by an explicit string key and optional extra args.
	 *
	 * Unlike memo(), umemo() uses an explicit $key instead of the call site,
	 * making it suitable for use in loops or dynamic contexts.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key          The explicit memo key (typically __METHOD__).
	 * @param mixed  $value_to_set The value to store. Pass null to retrieve.
	 * @param mixed  ...$args      Additional args to include in the memo key.
	 * @return mixed The stored value, or null if not yet set.
	 */
	function umemo( string $key, mixed $value_to_set = null, mixed ...$args ): mixed {

		static $memo = [];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- required for key hashing.
		$hash = serialize( [ $key, $args ] );

		if ( isset( $value_to_set ) ) {
			return $memo[ $hash ] = $value_to_set;
		}

		return $memo[ $hash ] ?? null;
	}

	/**
	 * Gets or sets a memoized value keyed by call site, using a callable to generate the value.
	 *
	 * On first call at a given call site, invokes $func and stores the result.
	 * On subsequent calls, returns the stored result without invoking $func again.
	 * Returns null if $func returns null.
	 *
	 * @since 1.0.0
	 *
	 * @param callable $func The callable to invoke on first call.
	 * @return mixed The stored value, or null if $func returned null.
	 */
	function fmemo( callable $func ): mixed {

		static $memo = [];

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- required for call-site keying.
		$hash = serialize(
			[
				'file' => '',
				'line' => 0,
			]
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions -- required for call-site keying.
			+ debug_backtrace( 0, 2 )[1],
		);

		if ( ! isset( $memo[ $hash ] ) ) {
			// Store the sentinel hash value if $func returns null,
			// to distinguish "not set" from a legitimate null return.
			$memo[ $hash ] = \call_user_func( $func ) ?? $hash;
		}

		return $memo[ $hash ] === $hash ? null : $memo[ $hash ];
	}
}