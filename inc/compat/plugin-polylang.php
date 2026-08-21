<?php
/**
 * Better SEO - Compatibility: Polylang
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

use Better_SEO\{
	Helper\Query,
	Meta\URI,
};

\add_action( 'better_seo_sitemap_header',                  __NAMESPACE__ . '\_polylang_set_sitemap_language' );
\add_filter( 'better_seo_sitemap_endpoint_list',           __NAMESPACE__ . '\_polylang_register_sitemap_languages',       20 );
\add_filter( 'better_seo_sitemap_hpt_query_args',          __NAMESPACE__ . '\_polylang_sitemap_append_non_translatables' );
\add_filter( 'better_seo_sitemap_nhpt_query_args',         __NAMESPACE__ . '\_polylang_sitemap_append_non_translatables' );
\add_filter( 'better_seo_title_from_custom_field',         __NAMESPACE__ . '\pll__' );
\add_filter( 'better_seo_title_from_generation',           __NAMESPACE__ . '\pll__' );
\add_filter( 'better_seo_custom_field_description',        __NAMESPACE__ . '\pll__' );
\add_filter( 'better_seo_generated_description',           __NAMESPACE__ . '\pll__' );
\add_filter( 'better_seo_front_init',                      __NAMESPACE__ . '\_hijack_polylang_home_url' );
\add_filter( 'pll_home_url_white_list',                    __NAMESPACE__ . '\_polylang_allow_better_seo_home_url' );
\add_filter( 'pll_home_url_allow_list',                    __NAMESPACE__ . '\_polylang_allow_better_seo_home_url' );
\add_action( 'better_seo_sitemap_transients_cleared',      __NAMESPACE__ . '\_polylang_flush_sitemap' );
\add_action( 'admin_enqueue_scripts',                      __NAMESPACE__ . '\_defunct_badly_coded_polylang_script',       11 );
\add_filter( 'better_seo_seo_column_keys_order',           __NAMESPACE__ . '\_polylang_seo_column_keys_order' );

/**
 * Registers per-language sitemap endpoints for Polylang non-default languages.
 *
 * For query-parameter-based language detection (force_lang=0), appends a
 * `?lang=xx` query string to the base sitemap endpoint.
 * For subdirectory-based language detection (force_lang=1), prepends the
 * language slug as a path prefix.
 *
 * @since 1.0.0
 *
 * @param array<string, array<string, mixed>> $list The registered sitemap endpoint list.
 * @return array<string, array<string, mixed>> The filtered sitemap endpoint list with language variants.
 */
function _polylang_register_sitemap_languages( array $list ): array {

	if ( empty( $list['base'] ) ) {
		return $list;
	}

	if ( ! Helper\Compatibility::can_i_use( [
		'functions' => [
			'pll_languages_list',
			'pll_default_language',
		],
	] ) ) {
		return $list;
	}

	$force_lang = \get_option( 'polylang' )['force_lang'] ?? -1;

	if ( 0 === $force_lang ) {
		// Query parameter mode — append ?lang=xx to the base endpoint.
		foreach (
			array_diff(
				\pll_languages_list( [ 'hide_empty' => 1 ] ),
				[ \pll_default_language() ],
			)
			as $language
		) {
			$list[ "_base_polylang_{$language}" ] = [
				'endpoint' => URI\Utils::append_query_to_url(
					$list['base']['endpoint'],
					"lang={$language}",
				),
			] + $list['base'];
		}
	} elseif ( 1 === $force_lang ) {
		// Subdirectory mode — prepend language slug as path prefix.
		foreach (
			array_diff(
				\pll_languages_list( [ 'hide_empty' => 1 ] ),
				[ \pll_default_language() ],
			)
			as $language
		) {
			$list[ "_base_polylang_{$language}" ] = [
				'endpoint' => "{$language}/{$list['base']['endpoint']}",
			] + $list['base'];
		}
	}

	return $list;
}

/**
 * Sets the Polylang current language for sitemap requests.
 *
 * Reads the `lang` query parameter from the request and sets the Polylang
 * current language accordingly. Falls back to the default language when
 * using query-parameter mode (force_lang=0) and no lang param is present.
 *
 * @since 1.0.0
 *
 * @return void
 */
function _polylang_set_sitemap_language(): void {

	if ( ! \function_exists( 'PLL' ) || ! ( \PLL() instanceof \PLL_Frontend ) ) {
		return;
	}

	$lang = $_GET['lang'] ?? '';

	if ( ! \is_string( $lang ) || ! \strlen( $lang ) || ! preg_match( '#^[a-z_-]+$#', $lang ) ) {

		$force_lang = \get_option( 'polylang' )['force_lang'] ?? -1;

		if ( 0 === $force_lang ) {
			// Query parameter mode — fall back to default language.
			$lang = \function_exists( 'pll_default_language' ) ? \pll_default_language() : $lang;
		} else {
			// Other modes — no language to set, bail out.
			return;
		}
	}

	$new_lang = \PLL()->model->get_language( $lang );

	if ( $new_lang ) {
		\PLL()->curlang = $new_lang;

		if ( ! \did_action( 'pll_language_defined' ) ) {
			\do_action( 'pll_language_defined' );
		}
	}
}

/**
 * Appends non-translatable posts to the sitemap query for the default Polylang language.
 *
 * When generating the sitemap for the default language, includes posts that
 * are not assigned to any language (non-translatable content) alongside
 * posts assigned to the default language.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $args The WP_Query arguments for the sitemap query.
 * @return array<string, mixed> The filtered WP_Query arguments.
 */
function _polylang_sitemap_append_non_translatables( array $args ): array {

	if ( ! Helper\Compatibility::can_i_use( [
		'functions' => [
			'PLL',
			'pll_languages_list',
			'pll_default_language',
		],
	] ) ) {
		return $args;
	}

	if ( ! ( \PLL() instanceof \PLL_Frontend ) ) {
		return $args;
	}

	$default_lang = \pll_default_language( \OBJECT );

	if ( ! isset( $default_lang->slug, $default_lang->term_id ) ) {
		return $args;
	}

	if ( ( \PLL()->curlang->slug ?? null ) === $default_lang->slug ) {
		// Include posts with no language assigned alongside default language posts.
		$args['lang']      = '';
		$args['tax_query'] = [
			'relation' => 'OR',
			[
				'taxonomy' => 'language',
				'terms'    => \pll_languages_list( [ 'fields' => 'term_id' ] ),
				'operator' => 'NOT IN',
			],
			[
				'taxonomy' => 'language',
				'terms'    => $default_lang->term_id,
				'operator' => 'IN',
			],
		];
	}

	return $args;
}

/**
 * Translates a string using Polylang's pll__() function when available.
 *
 * Acts as a safe wrapper around pll__() — only calls it when Polylang is
 * active and the current context is a frontend request.
 *
 * @since 1.0.0
 *
 * @param string $string The string to translate.
 * @return string The translated string, or the original if Polylang is not active.
 */
function pll__( string $string ): string {

	if ( \function_exists( 'PLL' ) && \function_exists( 'pll__' ) ) {
		if ( \PLL() instanceof \PLL_Frontend ) {
			return \pll__( $string );
		}
	}

	return $string;
}

/**
 * Flushes all Better SEO sitemap transients from the database for Polylang compatibility.
 *
 * Deletes all transients matching the Better SEO sitemap transient prefix,
 * including their timeout entries, to ensure all language variants are cleared.
 *
 * @since 1.0.0
 *
 * @return void
 */
function _polylang_flush_sitemap(): void {
	global $wpdb;

	$transient_prefix = Sitemap\Cache::get_transient_prefix();

	$wpdb->query( $wpdb->prepare(
		"DELETE FROM $wpdb->options WHERE option_name LIKE %s",
		$wpdb->esc_like( "_transient_{$transient_prefix}" ) . '%',
	) );

	$wpdb->query( $wpdb->prepare(
		"DELETE FROM $wpdb->options WHERE option_name LIKE %s",
		$wpdb->esc_like( "_transient_timeout_{$transient_prefix}" ) . '%',
	) );
}

/**
 * Removes the Polylang ajaxSuccess handler that interferes with Better SEO's admin JS.
 *
 * Polylang registers an ajaxSuccess handler on the document that conflicts
 * with Better SEO's inline edit functionality. This function removes the
 * most recently registered handler from both pll_term and pll_post scripts.
 *
 * @since 1.0.0
 *
 * @return void
 */
function _defunct_badly_coded_polylang_script(): void {

	$remove_ajax_success = <<<'JS'
	jQuery( () => {
		const handler = jQuery._data( document, 'events' )?.ajaxSuccess?.pop().handler;
		handler && jQuery( document ).off( 'ajaxSuccess', handler );
	} );
	JS;

	\wp_add_inline_script( 'pll_term', $remove_ajax_success );
	\wp_add_inline_script( 'pll_post', $remove_ajax_success );
}

/**
 * Hijacks Polylang's home_url filter to prevent it from running during Better SEO's
 * front-end initialization, which occurs before template_redirect.
 *
 * Temporarily sets $wp_actions['template_redirect'] to trick Polylang's filter
 * into running, then unsets it to restore the original state.
 *
 * @since 1.0.0
 *
 * @return void
 */
function _hijack_polylang_home_url(): void {

	if ( ! \function_exists( 'PLL' ) || ! ( \PLL() instanceof \PLL_Frontend ) ) {
		return;
	}

	$default_cb = [ \PLL()->filters_links ?? null, 'home_url' ];
	$priority   = $default_cb[0] ? \has_filter( 'home_url', $default_cb ) : false;

	if ( false === $priority ) {
		return;
	}

	\remove_filter( 'home_url', $default_cb, $priority );

	\add_filter(
		'home_url',
		function ( mixed ...$args ) use ( $default_cb ): string {
			global $wp_actions;

			// If template_redirect has already fired, or pll_language_defined hasn't,
			// let Polylang's filter run normally.
			if ( isset( $wp_actions['template_redirect'] ) || ! isset( $wp_actions['pll_language_defined'] ) ) {
				return \call_user_func_array( $default_cb, $args );
			}

			// Temporarily fake template_redirect so Polylang processes the URL.
			$wp_actions['template_redirect'] = 1;

			$url = \call_user_func_array( $default_cb, $args );

			// Restore original state.
			unset( $wp_actions['template_redirect'] );

			return $url;
		},
		$priority,
		4, // Accept up to 4 arguments.
	);
}

/**
 * Adds the Better SEO plugin directory to Polylang's home URL allow list.
 *
 * Allows Polylang to process home_url() calls originating from Better SEO's
 * plugin files without stripping the language prefix.
 *
 * @since 1.0.0
 *
 * @param array<int, array<string, string>> $allow_list The Polylang home URL allow list.
 * @return array<int, array<string, string>> The filtered allow list.
 */
function _polylang_allow_better_seo_home_url( array $allow_list ): array {

	$allow_list[] = [ 'file' => \BETTER_SEO_DIR_PATH ];

	return $allow_list;
}

/**
 * Prepends Polylang language column keys to the SEO column key order.
 *
 * Ensures Polylang's language columns appear before the SEO bar column
 * in the WordPress admin post list tables.
 *
 * @since 1.0.0
 *
 * @param array<int, string> $order_keys The current SEO column key order.
 * @return array<int, string> The filtered column key order with language keys prepended.
 */
function _polylang_seo_column_keys_order( array $order_keys ): array {

	if ( ! \function_exists( 'PLL' ) || ! ( \PLL() instanceof \PLL_Admin ) ) {
		return $order_keys;
	}

	$language_keys = array_map(
		fn( object $language ): string => "language_{$language->slug}",
		\PLL()->model->get_languages_list(),
	);

	array_unshift( $order_keys, ...$language_keys );

	return $order_keys;
}