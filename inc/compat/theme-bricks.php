<?php
/**
 * Better SEO - Compatibility: Bricks Theme
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

\add_filter( 'better_seo_public_post_types', __NAMESPACE__ . '\_bricks_fix_public_post_types' );
\add_filter( 'better_seo_public_taxonomies', __NAMESPACE__ . '\_bricks_fix_public_taxonomies' );

/**
 * Removes the Bricks template post type from the public post types list.
 *
 * When Bricks templates are not set to public, removes the Bricks template
 * post type slug from Better SEO's public post type list to prevent it from
 * appearing in sitemaps, SEO bars, and post type settings.
 *
 * @since 1.0.0
 *
 * @param array<int, string> $post_types The registered public post type slugs.
 * @return array<int, string> The filtered post type slugs.
 */
function _bricks_fix_public_post_types( array $post_types ): array {

	if (
		\defined( 'BRICKS_DB_TEMPLATE_SLUG' )
		&& class_exists( \Bricks\Database::class, false )
		&& ! \Bricks\Database::get_setting( 'publicTemplates' )
	) {
		$post_types = array_diff( $post_types, [ BRICKS_DB_TEMPLATE_SLUG ] );
	}

	return $post_types;
}

/**
 * Removes Bricks template taxonomies from the public taxonomies list.
 *
 * Removes the Bricks template tag and bundle taxonomies from Better SEO's
 * public taxonomy list to prevent them from appearing in sitemaps and settings.
 *
 * @since 1.0.0
 *
 * @param array<int, string> $taxonomies The registered public taxonomy slugs.
 * @return array<int, string> The filtered taxonomy slugs.
 */
function _bricks_fix_public_taxonomies( array $taxonomies ): array {

	$unset = [];

	if ( \defined( 'BRICKS_DB_TEMPLATE_TAX_TAG' ) ) {
		$unset[] = BRICKS_DB_TEMPLATE_TAX_TAG;
	}

	if ( \defined( 'BRICKS_DB_TEMPLATE_TAX_BUNDLE' ) ) {
		$unset[] = BRICKS_DB_TEMPLATE_TAX_BUNDLE;
	}

	return array_diff( $taxonomies, $unset );
}