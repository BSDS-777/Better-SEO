<?php
/**
 * Better SEO - Compatibility: wpForo
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Compatibility
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 * @link       https://www.gnu.org/licenses/gpl-2.0.html
 * @access     private
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

namespace Better_SEO;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\memo;

use Better_SEO\Meta;

\add_action( 'better_seo_seo_bar',    __NAMESPACE__ . '\_assert_wpforo_page_seo_bar' );
\add_action( 'wpforo_before_init',    __NAMESPACE__ . '\_wpforo_fix_page' );

/**
 * Initializes wpForo compatibility hooks after wpForo is loaded.
 *
 * Conditionally hooks Better SEO's title and meta output filters based on
 * wpForo's SEO settings. When wpForo manages SEO meta, Better SEO's HTML
 * output is disabled. When wpForo does not manage SEO meta, Better SEO
 * removes wpForo's own meta tags and takes over canonical URL output.
 *
 * @hook wpforo_before_init 10
 * @since 1.0.0
 *
 * @return void
 */
function _wpforo_fix_page(): void {

	if ( \is_admin() || ! \function_exists( 'is_wpforo_page' ) || ! \is_wpforo_page() ) {
		return;
	}

	if ( _wpforo_seo_title_enabled() ) {
		\add_filter( 'better_seo_title_from_generation', __NAMESPACE__ . '\_wpforo_filter_pre_title', 10, 2 );
		\add_filter( 'better_seo_use_title_branding',    '__return_false' );
	}

	if ( _wpforo_seo_meta_enabled() ) {
		// Remove Better SEO's output — twofold in case wpForo changes operation order.
		_wpforo_disable_better_seo_html_output();

		// This won't run on wpForo at the time of writing (2.1.6) because
		// better_seo_after_init has already fired by this point.
		\add_action( 'better_seo_after_init', __NAMESPACE__ . '\_wpforo_disable_better_seo_html_output', 1 );
	} else {
		// wpForo SEO meta is disabled — remove wpForo's own meta tags and
		// let Better SEO handle canonical URL output instead.
		\remove_action( 'wp_head', 'wpforo_add_meta_tags', 1 );
		\add_filter( 'get_canonical_url', __NAMESPACE__ . '\_wpforo_filter_canonical_url' );
	}
}

/**
 * Removes Better SEO's head meta output action.
 *
 * Called when wpForo is managing SEO meta output to prevent duplicate tags.
 *
 * @since 1.0.0
 *
 * @return void
 */
function _wpforo_disable_better_seo_html_output(): void {
	\remove_action( 'wp_head', [ Front\Meta\Head::class, 'print_wrap_and_tags' ], 1 );
}

/**
 * Filters the canonical URL to use wpForo's request URI on wpForo pages.
 *
 * @since 1.0.0
 *
 * @param string $canonical_url The current canonical URL.
 * @return string The wpForo request URI if available, otherwise the original canonical URL.
 */
function _wpforo_filter_canonical_url( string $canonical_url ): string {
	return \function_exists( 'wpforo_get_request_uri' ) ? \wpforo_get_request_uri() : $canonical_url;
}

/**
 * Filters the document title to use wpForo's generated title on wpForo pages.
 *
 * Builds the wpForo title by joining all non-empty title parts with the
 * Better SEO separator, then returns it if it differs from the default title.
 *
 * @since 1.0.0
 *
 * @param string                    $title The current document title.
 * @param array<string, mixed>|null $args  The generation args, or null for current query.
 * @return string The wpForo title if available, otherwise the original title.
 */
function _wpforo_filter_pre_title( string $title, mixed $args ): string {

	if ( ! isset( $args ) ) {
		$sep          = Meta\Title::get_separator();
		$wpforo_title = implode(
			" {$sep} ",
			array_filter( (array) \wpforo_meta_title( '' ), 'strlen' ),
		);
	}

	return ( $wpforo_title ?? '' ) ?: $title;
}

/**
 * Asserts wpForo SEO bar status for pages containing the [wpforo] shortcode.
 *
 * Updates all relevant SEO bar items to STATE_UNDEFINED when wpForo is
 * managing SEO output for the current page, indicating that Better SEO
 * is not in control of those aspects.
 *
 * @hook better_seo_seo_bar 10
 * @since 1.0.0
 *
 * @param object $interpreter The SEO bar interpreter (Builder class).
 * @return void
 */
function _assert_wpforo_page_seo_bar( object $interpreter ): void {

	if ( $interpreter::$query['tax'] ) {
		return;
	}

	$meta_enabled  = _wpforo_seo_meta_enabled();
	$title_enabled = _wpforo_seo_title_enabled();

	if ( ! $meta_enabled && ! $title_enabled ) {
		return;
	}

	$items = &$interpreter::collect_seo_bar_items();

	// Skip if a blocking redirect is already set — SEO bar state is irrelevant.
	if ( ! empty( $items['redirect']['meta']['blocking'] ) ) {
		return;
	}

	// Only apply to pages that actually contain the [wpforo] shortcode.
	if ( ! \has_shortcode( Data\Post::get_content( $interpreter::$query['id'] ), 'wpforo' ) ) {
		return;
	}

	foreach ( $items as $id => &$item ) {
		switch ( $id ) {
			case 'redirect':
				// Never override the redirect item.
				continue 2;
			case 'title':
				if ( ! $title_enabled ) {
					continue 2;
				}
				break;
			default:
				if ( ! $meta_enabled ) {
					continue 2;
				}
		}

		$item['status'] = $interpreter::STATE_UNDEFINED;
		$item['reason'] = \__( '', 'better-seo' );

		// Clear existing assessments and replace with wpForo management notice.
		$item['assess'] = [];

		$item['assess']['base'] = \sprintf(
			\__( 'This is managed by plugin "%s."', 'better-seo' ),
			'wpForo Forum',
		);
	}
}

/**
 * Returns whether wpForo's SEO meta output is enabled, memoized.
 *
 * @since 1.0.0
 *
 * @return bool True if wpForo SEO meta is enabled.
 */
function _wpforo_seo_meta_enabled(): bool {
	return memo() ?? memo(
		\function_exists( 'wpforo_setting' ) && ( \wpforo_setting( 'seo', 'seo_meta' ) ?? true ),
	);
}

/**
 * Returns whether wpForo's SEO title output is enabled, memoized.
 *
 * @since 1.0.0
 *
 * @return bool True if wpForo SEO title is enabled.
 */
function _wpforo_seo_title_enabled(): bool {
	return memo() ?? memo(
		\function_exists( 'wpforo_setting' ) && ( \wpforo_setting( 'seo', 'seo_title' ) ?? true ),
	);
}