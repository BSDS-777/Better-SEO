<?php
/**
 * Better SEO - Compatibility: WooCommerce
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

use function Better_SEO\get_query_type_from_args;

use Better_SEO\{
	Data,
	Data\Filter\Sanitize,
	Helper\Query,
};

\add_action( 'woocommerce_init',                        __NAMESPACE__ . '\_init_wc_compat' );
\add_filter( 'better_seo_real_id',                      __NAMESPACE__ . '\_set_real_id_wc_shop' );
\add_filter( 'better_seo_is_singular_archive',          __NAMESPACE__ . '\_set_shop_singular_archive',              10, 2 );
\add_filter( 'better_seo_is_shop',                      __NAMESPACE__ . '\_set_wc_is_shop',                         10, 2 );
\add_filter( 'better_seo_is_product',                   __NAMESPACE__ . '\_set_wc_is_product',                      10, 2 );
\add_filter( 'better_seo_is_product_admin',             __NAMESPACE__ . '\_set_wc_is_product_admin' );
\add_filter( 'better_seo_robots_meta_array',            __NAMESPACE__ . '\_set_wc_noindex_defaults',                10, 3 );
\add_action( 'better_seo_seo_bar',                      __NAMESPACE__ . '\_assert_wc_noindex_defaults_seo_bar',     10, 2 );
\add_filter( 'better_seo_image_generation_params',      __NAMESPACE__ . '\_adjust_wc_image_generation_params',      10, 2 );
\add_filter( 'better_seo_public_post_type_archives',    __NAMESPACE__ . '\_filter_public_wc_post_type_archives' );
\add_filter( 'better_seo_generated_archive_title_items', __NAMESPACE__ . '\_filter_wc_shop_pta_title_items',        10, 2 );

/**
 * Initializes WooCommerce compatibility hooks after WooCommerce is loaded.
 *
 * Hooks Better SEO's primary term filter into WooCommerce product category
 * link resolution, and removes WooCommerce's own robots noindex filter
 * to prevent conflicts with Better SEO's robots output.
 *
 * @since 1.0.0
 *
 * @return void
 */
function _init_wc_compat(): void {
	// Use Better SEO's primary term selection for WooCommerce product category links.
	\add_filter( 'wc_product_post_type_link_product_cat', [ Query\Filter::class, 'filter_post_link_category' ], 10, 3 );

	// Use Better SEO's primary term selection for WooCommerce breadcrumbs.
	\add_filter( 'woocommerce_breadcrumb_main_term', [ Query\Filter::class, 'filter_post_link_category' ], 10, 2 );

	\add_filter( 'woocommerce_product_categories_widget_main_term', [ Query\Filter::class, 'filter_post_link_category' ], 10, 2 );

	// Remove WooCommerce's own robots noindex — Better SEO handles this.
	\remove_filter( 'wp_robots', 'wc_page_no_robots' );
}

/**
 * Returns the WooCommerce shop page ID, cached statically.
 *
 * Returns 0 if the shop page is not configured or the post does not exist.
 *
 * @since 1.0.0
 *
 * @return int The shop page ID, or 0 if not set.
 */
function _get_shop_page_id(): int {

	static $id;

	if ( isset( $id ) ) {
		return $id;
	}

	$id = \function_exists( 'wc_get_page_id' ) ? (int) \wc_get_page_id( 'shop' ) : 0;

	if ( \get_post( $id ) ) {
		return $id;
	}

	return $id = 0;
}

/**
 * Returns whether the given post or current page is the WooCommerce shop page.
 *
 * When $post is null, checks the current frontend query via is_shop().
 * When $post is provided, compares its ID against the shop page ID.
 *
 * @since 1.0.0
 *
 * @param \WP_Post|int|null $post Optional post object or ID to check. Default null.
 * @return bool True if the given post or current page is the shop page.
 */
function _is_shop( \WP_Post|int|null $post = null ): bool {

	if ( isset( $post ) ) {
		$id = \is_int( $post )
			? $post
			: ( \get_post( $post )->ID ?? 0 );

		$is_shop = $id && _get_shop_page_id() === $id;
	} else {
		$is_shop = ! \is_admin() && \function_exists( 'is_shop' ) && \is_shop();
	}

	return $is_shop;
}

/**
 * Filters the real ID to return the shop page ID when on the WooCommerce shop page.
 *
 * @since 1.0.0
 *
 * @param int $id The current real ID.
 * @return int The shop page ID if on the shop page, otherwise the original ID.
 */
function _set_real_id_wc_shop( int $id ): int {
	return _is_shop() ? _get_shop_page_id() : $id;
}

/**
 * Filters the singular archive flag to include the WooCommerce shop page.
 *
 * @since 1.0.0
 *
 * @param bool $is_singular_archive Whether the current page is a singular archive.
 * @param int  $id                  The post ID being checked.
 * @return bool True if the page is a singular archive or the shop page.
 */
function _set_shop_singular_archive( bool $is_singular_archive, int $id ): bool {
	return $is_singular_archive || ( _get_shop_page_id() && _is_shop( $id ) );
}

/**
 * Filters the is_shop flag to include WooCommerce shop page detection.
 *
 * @since 1.0.0
 *
 * @param bool                      $is_shop Whether the current page is the shop.
 * @param \WP_Post|int|null         $post    The post being checked.
 * @return bool True if the page is the shop page.
 */
function _set_wc_is_shop( bool $is_shop, mixed $post ): bool {
	return $is_shop || _is_shop( $post );
}

/**
 * Filters the is_product flag to include WooCommerce product detection.
 *
 * @since 1.0.0
 *
 * @param bool                      $is_product Whether the current page is a product.
 * @param \WP_Post|int|null         $post       The post being checked.
 * @return bool True if the page is a WooCommerce product.
 */
function _set_wc_is_product( bool $is_product, mixed $post ): bool {

	if ( $is_product ) {
		return $is_product;
	}

	if ( $post ) {
		return 'product' === \get_post_type( $post );
	}

	return \function_exists( 'is_product' ) && \is_product();
}

/**
 * Filters the is_product_admin flag to include WooCommerce product admin detection.
 *
 * @since 1.0.0
 *
 * @param bool $is_product_admin Whether the current admin page is a product edit screen.
 * @return bool True if the current admin page is a WooCommerce product edit screen.
 */
function _set_wc_is_product_admin( bool $is_product_admin ): bool {

	if ( $is_product_admin ) {
		return $is_product_admin;
	}

	return Query::is_singular_admin() && 'product' === Query::get_admin_post_type();
}

/**
 * Adds noindex to the robots meta array for WooCommerce cart, checkout, and account pages.
 *
 * Only applies when the page has not already been set to noindex, and when
 * the ROBOTS_IGNORE_SETTINGS flag is not set or the post meta allows it.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed>      $meta    The robots meta array.
 * @param array<string, mixed>|null $args    The generation args, or null for current query.
 * @param int                       $options The robots generation options bitmask.
 * @return array<string, mixed> The filtered robots meta array.
 */
function _set_wc_noindex_defaults( array $meta, mixed $args, int $options ): array {

	// Already noindexed — nothing to do.
	if ( 'noindex' === $meta['noindex'] ) {
		return $meta;
	}

	if ( isset( $args ) ) {
		if ( 'single' === get_query_type_from_args( $args ) ) {
			$page_id = $args['id'];
		}
	} else {
		if ( Query::is_singular() ) {
			$page_id = Query::get_the_real_id();
		}
	}

	if ( empty( $page_id ) ) {
		return $meta;
	}

	static $page_ids;

	if ( ! isset( $page_ids ) ) {
		if ( ! \function_exists( 'wc_get_page_id' ) ) {
			return $meta;
		}

		$page_ids = array_filter( [ \wc_get_page_id( 'cart' ), \wc_get_page_id( 'checkout' ), \wc_get_page_id( 'myaccount' ) ] );
	}

	if ( ! \in_array( $page_id, $page_ids, true ) ) {
		return $meta;
	}

	if (
		   $options & \Better_SEO\ROBOTS_IGNORE_SETTINGS
		|| 0 === Sanitize::qubit( Data\Plugin\Post::get_meta_item( '_genesis_noindex', $page_id ) )
	) {
		$meta['noindex'] = 'noindex';
	}

	return $meta;
}

/**
 * Asserts noindex SEO bar defaults for WooCommerce cart, checkout, and account pages.
 *
 * Updates the indexing SEO bar item to reflect WooCommerce's recommendation
 * that these dynamic pages should not be indexed.
 *
 * @since 1.0.0
 *
 * @param object $interpreter The SEO bar interpreter (Builder class).
 * @param object $builder     The SEO bar builder instance.
 * @return void
 */
function _assert_wc_noindex_defaults_seo_bar( object $interpreter, object $builder ): void {

	if ( $interpreter::$query['tax'] || ! \function_exists( 'wc_get_page_id' ) ) {
		return;
	}

	static $page_ids;

	if ( ! isset( $page_ids ) ) {
		$page_ids = array_filter( [ \wc_get_page_id( 'cart' ), \wc_get_page_id( 'checkout' ), \wc_get_page_id( 'myaccount' ) ] );
	}

	if ( ! \in_array( $interpreter::$query['id'], $page_ids, true ) ) {
		return;
	}

	$items = $interpreter::collect_seo_bar_items();

	// Skip if a blocking redirect is already set — noindex is irrelevant.
	if ( ! empty( $items['redirect']['meta']['blocking'] ) ) {
		return;
	}

	$index_item           = &$interpreter::edit_seo_bar_item( 'indexing' );
	$index_item['status'] = 0 !== Sanitize::qubit( $builder->get_query_cache()['meta']['_genesis_noindex'] )
		? $interpreter::STATE_OKAY
		: $interpreter::STATE_UNKNOWN;

	$index_item['assess']['recommends'] = \__( 'WooCommerce recommends not indexing dynamic pages.', 'better-seo' );
}

/**
 * Adds WooCommerce product gallery images to the image generation callbacks.
 *
 * For product pages, adds the gallery image generator.
 * For product category pages, adds the category thumbnail generator.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed>      $params The image generation parameters.
 * @param array<string, mixed>|null $args   The generation args, or null for current query.
 * @return array<string, mixed> The filtered image generation parameters.
 */
function _adjust_wc_image_generation_params( array $params, mixed $args ): array {

	$is_product          = false;
	$is_product_category = false;

	if ( isset( $args ) ) {
		$query_type = get_query_type_from_args( $args );

		if ( 'term' === $query_type ) {
			$is_product_category = 'product_cat' === $args['tax'];
		} elseif ( 'single' === $query_type ) {
			$is_product = Query::is_product( $args['id'] );
		}
	} else {
		if ( Query::is_product() ) {
			$is_product = true;
		} elseif ( \function_exists( 'is_product_category' ) && \is_product_category() ) {
			$is_product_category = true;
		}
	}

	if ( $is_product ) {
		$params['cbs']['wc_gallery'] = __NAMESPACE__ . '\_get_product_gallery_image_details';
	}

	if ( $is_product_category ) {
		$params['cbs']['wc_thumbnail'] = __NAMESPACE__ . '\_get_product_category_thumbnail_image_details';
	}

	return $params;
}

/**
 * Generates image details from the WooCommerce product gallery.
 *
 * Yields each gallery attachment's URL and ID. Yields an empty entry
 * if no gallery images are found.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed>|null $args Optional generation args. Default null.
 * @param string                    $size The image size to retrieve. Default 'full'.
 * @return \Generator Yields image detail arrays with 'url' and 'id' keys.
 */
function _get_product_gallery_image_details( ?array $args = null, string $size = 'full' ): \Generator {

	$post_id        = $args['id'] ?? Query::get_the_real_id();
	$attachment_ids = [];

	if ( $post_id && \metadata_exists( 'post', $post_id, '_product_image_gallery' ) ) {
		$attachment_ids = array_map(
			'absint',
			array_filter(
				explode(
					',',
					\get_post_meta( $post_id, '_product_image_gallery', true ),
				),
			),
		);
	}

	if ( $attachment_ids ) {
		foreach ( $attachment_ids as $id ) {
			yield [
				'url' => \wp_get_attachment_image_url( $id, $size ),
				'id'  => $id,
			];
		}
	} else {
		yield [
			'url' => '',
			'id'  => 0,
		];
	}
}

/**
 * Generates image details from the WooCommerce product category thumbnail.
 *
 * Yields the category thumbnail URL and attachment ID if set.
 * Yields an empty entry if no thumbnail is configured.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed>|null $args Optional generation args. Default null.
 * @param string                    $size The image size to retrieve. Default 'full'.
 * @return \Generator Yields image detail arrays with 'url' and 'id' keys.
 */
function _get_product_category_thumbnail_image_details( ?array $args = null, string $size = 'full' ): \Generator {

	$term_id      = $args['id'] ?? Query::get_the_real_id();
	$thumbnail_id = \get_term_meta( $term_id, 'thumbnail_id', true ) ?: 0;

	if ( $thumbnail_id ) {
		yield [
			'url' => \wp_get_attachment_image_url( $thumbnail_id, $size ) ?: '',
			'id'  => $thumbnail_id,
		];
	} else {
		yield [
			'url' => '',
			'id'  => 0,
		];
	}
}

/**
 * Removes the 'product' post type from the public post type archives list in admin.
 *
 * Prevents the WooCommerce shop page from appearing as a post type archive
 * in Better SEO's admin settings when a shop page is configured.
 *
 * @since 1.0.0
 *
 * @param array<int, string> $post_types The public post type archive slugs.
 * @return array<int, string> The filtered post type archive slugs.
 */
function _filter_public_wc_post_type_archives( array $post_types ): array {

	// Only filter in admin when a shop page is configured.
	if ( ! \is_admin() || ! _get_shop_page_id() ) {
		return $post_types;
	}

	return array_diff( $post_types, [ 'product' ] );
}

/**
 * Replaces the archive title items with the WooCommerce shop page title.
 *
 * When the current archive is the WooCommerce shop (product post type archive),
 * replaces the title items with the localized "Shop" string from WooCommerce.
 *
 * @since 1.0.0
 *
 * @param array<int, string>         $items  The archive title items array.
 * @param \WP_Post_Type|object|null  $object The post type object, or null for current query.
 * @return array<int, string> The filtered archive title items.
 */
function _filter_wc_shop_pta_title_items( array $items, mixed $object ): array {

	if ( $object ) {
		$replace = $object instanceof \WP_Post_Type && 'product' === $object->name;
	} else {
		$replace = Query::is_shop();
	}

	if ( ! $replace ) {
		return $items;
	}

	$shop = \_x( 'Shop', 'Page title', 'woocommerce' );

	[ $items[0], $items[1], $items[2] ] = [
		$shop,
		'',
		$shop,
	];

	return $items;
}
