<?php
/**
 * Better SEO - Compatibility: WPML
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

\add_filter( 'better_seo_sitemap_endpoint_list',      __NAMESPACE__ . '\_wpml_register_sitemap_languages',              20 );
\add_action( 'better_seo_sitemap_transients_cleared', __NAMESPACE__ . '\_wpml_flush_sitemap',                           10 );
\add_action( 'better_seo_sitemap_header',             __NAMESPACE__ . '\_wpml_sitemap_filter_display_translatables' );
\add_filter( 'better_seo_sitemap_hpt_query_args',     __NAMESPACE__ . '\_wpml_sitemap_filter_non_translatables' );
\add_filter( 'better_seo_sitemap_nhpt_query_args',    __NAMESPACE__ . '\_wpml_sitemap_filter_non_translatables' );

/**
 * Registers per-language sitemap endpoints for WPML non-default languages.
 *
 * For query-parameter-based language detection (WPML_LANGUAGE_NEGOTIATION_TYPE_PARAMETER),
 * appends a `?lang=xx` query string to the base sitemap endpoint.
 * For directory-based language detection (WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY),
 * prepends the language code as a path prefix.
 *
 * @since 1.0.0
 *
 * @param array<string, array<string, mixed>> $list The registered sitemap endpoint list.
 * @return array<string, array<string, mixed>> The filtered sitemap endpoint list with language variants.
 */
function _wpml_register_sitemap_languages( array $list ): array {
	global $sitepress;

	if ( empty( $list['base'] ) ) {
		return $list;
	}

	if (
		   empty( $sitepress )
		|| ! Helper\Compatibility::can_i_use(
			[
				'methods'   => [
					[ $sitepress, 'get_default_language' ],
					[ $sitepress, 'get_active_languages' ],
					[ $sitepress, 'get_setting' ],
				],
				'constants' => [
					'WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY',
					'WPML_LANGUAGE_NEGOTIATION_TYPE_PARAMETER',
				],
			],
		)
	) {
		return $list;
	}

	$negotiation_type = $sitepress->get_setting( 'language_negotiation_type' );

	if ( \WPML_LANGUAGE_NEGOTIATION_TYPE_PARAMETER === $negotiation_type ) {
		// Query parameter mode — append ?lang=xx to the base endpoint.
		foreach (
			array_diff(
				array_column( $sitepress->get_active_languages(), 'code' ),
				[ $sitepress->get_default_language() ],
			)
			as $language
		) {
			$list[ "base_wpml_{$language}" ] = [
				'endpoint' => URI\Utils::append_query_to_url(
					$list['base']['endpoint'],
					"lang={$language}",
				),
			] + $list['base'];
		}
	} elseif ( \WPML_LANGUAGE_NEGOTIATION_TYPE_DIRECTORY === $negotiation_type ) {
		// Directory mode — prepend language code as path prefix.
		foreach (
			array_diff(
				array_column( $sitepress->get_active_languages(), 'code' ),
				[ $sitepress->get_default_language() ],
			)
			as $language
		) {
			$list[ "base_wpml_{$language}" ] = [
				'endpoint' => "{$language}/{$list['base']['endpoint']}",
			] + $list['base'];
		}
	}

	return $list;
}

/**
 * Flushes all Better SEO sitemap transients from the database for WPML compatibility.
 *
 * Deletes all transients matching the Better SEO sitemap transient prefix,
 * including their timeout entries, to ensure all language variants are cleared.
 *
 * @since 1.0.0
 *
 * @return void
 */
function _wpml_flush_sitemap(): void {
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
 * Disables WPML's "display as translated" snippet filter during sitemap generation.
 *
 * Prevents WPML from injecting translated content snippets into sitemap output,
 * which would cause incorrect URLs to appear in the sitemap.
 *
 * @since 1.0.0
 *
 * @return void
 */
function _wpml_sitemap_filter_display_translatables(): void {
	\add_filter( 'wpml_should_use_display_as_translated_snippet', '__return_false' );
}

/**
 * Filters the sitemap query args to exclude non-translatable post types
 * when generating sitemaps for non-default WPML languages.
 *
 * Non-translatable post types only appear in the default language sitemap.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $args The WP_Query arguments for the sitemap query.
 * @return array<string, mixed> The filtered WP_Query arguments.
 */
function _wpml_sitemap_filter_non_translatables( array $args ): array {
	global $sitepress;

	if (
		   empty( $sitepress )
		|| ! Helper\Compatibility::can_i_use(
			[
				'methods' => [
					[ $sitepress, 'get_default_language' ],
					[ $sitepress, 'get_current_language' ],
					[ $sitepress, 'is_translated_post_type' ],
				],
			],
		)
	) {
		return $args;
	}

	// Non-default language — filter out non-translatable post types.
	if ( $sitepress->get_default_language() === $sitepress->get_current_language() ) {
		return $args;
	}

	$args['post_type'] = array_filter( (array) $args['post_type'], [ $sitepress, 'is_translated_post_type' ] );

	return $args;
}