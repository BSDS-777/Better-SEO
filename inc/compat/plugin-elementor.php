<?php
/**
 * Better SEO - Compatibility: Elementor
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

use Better_SEO\Helper\Query;

/**
 * Elementor internal post types that should be excluded from public SEO output.
 *
 * These post types are used internally by Elementor and should not appear
 * in sitemaps, SEO bars, or public post type lists.
 *
 * @since 1.0.0
 * @var   array<int, string>
 */
const ELEMENTOR_DUMB_POST_TYPES = [
	'e-landing-page',
	'elementor_library',
	'e-floating-buttons',
];

\add_filter( 'better_seo_public_post_types', __NAMESPACE__ . '\_elementor_fix_dumb_post_types' );
\add_filter( 'better_seo_robots_meta_array', __NAMESPACE__ . '\_elementor_force_noindex' );

/**
 * Removes Elementor internal post types from the public post types list.
 *
 * Only applies in admin screens and sitemap contexts where these post types
 * should not appear in Better SEO's post type lists or sitemaps.
 *
 * @since 1.0.0
 *
 * @param array<int, string> $post_types The registered public post type slugs.
 * @return array<int, string> The filtered post type slugs.
 */
function _elementor_fix_dumb_post_types( array $post_types ): array {

	if ( \is_admin() || Query::is_sitemap() ) {
		return array_diff( $post_types, ELEMENTOR_DUMB_POST_TYPES );
	}

	return $post_types;
}

/**
 * Forces noindex on Elementor internal post type pages.
 *
 * Adds noindex to the robots meta array when the current page belongs to
 * one of Elementor's internal post types that should not be indexed.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $meta The robots meta array.
 * @return array<string, mixed> The filtered robots meta array.
 */
function _elementor_force_noindex( array $meta ): array {

	// Already noindexed — nothing to do.
	if ( 'noindex' === $meta['noindex'] ) {
		return $meta;
	}

	if ( \in_array( Query::get_post_type_real_id(), ELEMENTOR_DUMB_POST_TYPES, true ) ) {
		$meta['noindex'] = 'noindex';
	}

	return $meta;
}