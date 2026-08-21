<?php
/**
 * Better SEO - Compatibility: Easy Digital Downloads
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

\add_filter( 'better_seo_is_product',       __NAMESPACE__ . '\_set_edd_is_product',       10, 2 );
\add_filter( 'better_seo_is_product_admin', __NAMESPACE__ . '\_set_edd_is_product_admin' );

/**
 * Filters the is_product flag to include Easy Digital Downloads download detection.
 *
 * Returns true if the given post is a valid EDD download object.
 *
 * @since 1.0.0
 *
 * @param bool                      $is_product Whether the current page is a product.
 * @param \WP_Post|int|null         $post       The post being checked.
 * @return bool True if the post is an EDD download.
 */
function _set_edd_is_product( bool $is_product, mixed $post ): bool {

	if ( $is_product || ! \function_exists( 'edd_get_download' ) ) {
		return $is_product;
	}

	$download = \edd_get_download(
		$post ? \get_post( $post ) : Query::get_the_real_id()
	);

	return ! empty( $download->ID );
}

/**
 * Filters the is_product_admin flag to include EDD download admin screen detection.
 *
 * @since 1.0.0
 *
 * @param bool $is_product_admin Whether the current admin page is a product edit screen.
 * @return bool True if the current admin page is an EDD download edit screen.
 */
function _set_edd_is_product_admin( bool $is_product_admin ): bool {

	if ( $is_product_admin ) {
		return $is_product_admin;
	}

	return Query::is_singular_admin() && 'download' === Query::get_admin_post_type();
}