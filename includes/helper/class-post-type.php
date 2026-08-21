<?php
/**
 * Better SEO - Helper Post Type
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Helper
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

namespace Better_SEO\Helper;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\{
	memo,
	umemo,
};

use Better_SEO\Data;

/**
 * Class Better_SEO\Helper\Post_Type
 *
 * Provides post type detection, support checking, and listing utilities for Better SEO,
 * including public, supported, hierarchical, and post type archive (PTA) queries.
 *
 * @since 1.0.0
 */
class Post_Type {

	/**
	 * Returns whether the given post type is disabled in Better SEO settings.
	 *
	 * Applies the better_seo_post_type_disabled filter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type The post type slug. Defaults to current post type.
	 * @return bool True if the post type is disabled.
	 */
	public static function is_disabled( string $post_type = '' ): bool {

		$post_type = $post_type ?: Query::get_current_post_type();

		/**
		 * Filters whether the given post type is disabled in Better SEO.
		 *
		 * @since 1.0.0
		 *
		 * @param bool   $disabled  Whether the post type is disabled.
		 * @param string $post_type The post type slug.
		 */
		return (bool) \apply_filters(
			'better_seo_post_type_disabled',
			Data\Plugin::get_option( 'disabled_post_types', $post_type ),
			$post_type,
		);
	}

	/**
	 * Returns whether the given post type is supported by Better SEO.
	 *
	 * Applies the better_seo_supported_post_type filter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type The post type slug. Defaults to current post type.
	 * @return bool True if the post type is supported.
	 */
	public static function is_supported( string $post_type = '' ): bool {

		$post_type = $post_type ?: Query::get_current_post_type();

		/**
		 * Filters whether the given post type is supported by Better SEO.
		 *
		 * @since 1.0.0
		 *
		 * @param bool   $supported Whether the post type is supported.
		 * @param string $post_type The post type slug.
		 */
		return (bool) \apply_filters(
			'better_seo_supported_post_type',
			$post_type
				&& ! self::is_disabled( $post_type )
				&& \in_array( $post_type, self::get_all_public(), true ),
			$post_type,
		);
	}

	/**
	 * Returns whether the given post type archive (PTA) is supported by Better SEO.
	 *
	 * Applies the better_seo_supported_post_type_archive filter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type The post type slug. Defaults to current post type.
	 * @return bool True if the post type archive is supported.
	 */
	public static function is_pta_supported( string $post_type = '' ): bool {

		$post_type = $post_type ?: Query::get_current_post_type();

		/**
		 * Filters whether the given post type archive is supported by Better SEO.
		 *
		 * @since 1.0.0
		 *
		 * @param bool   $supported Whether the post type archive is supported.
		 * @param string $post_type The post type slug.
		 */
		return (bool) \apply_filters(
			'better_seo_supported_post_type_archive',
			(
				   $post_type
				&& self::is_supported( $post_type )
				&& \in_array( $post_type, self::get_public_pta(), true )
			),
			$post_type,
		);
	}

	/**
	 * Returns whether the given post type supports taxonomies, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type The post type slug. Defaults to current post type.
	 * @return bool True if the post type has associated taxonomies.
	 */
	public static function supports_taxonomies( string $post_type = '' ): bool {

		if ( null !== $memo = memo( null, $post_type ) ) {
			return $memo;
		}

		$post_type = $post_type ?: Query::get_current_post_type();

		return $post_type && memo( (bool) \get_object_taxonomies( $post_type, 'names' ), $post_type );
	}

	/**
	 * Returns all supported post type archives, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> List of supported PTA post type slugs.
	 */
	public static function get_all_supported_pta(): array {
		return memo() ?? memo( array_values(
			array_filter(
				self::get_public_pta(),
				[ self::class, 'is_pta_supported' ],
			)
		) );
	}

	/**
	 * Returns all public post type archives, memoized.
	 *
	 * Applies the better_seo_public_post_type_archives filter.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> List of public PTA post type slugs.
	 */
	public static function get_public_pta(): array {
		return umemo( __METHOD__ )
			?? umemo(
				__METHOD__,
				(array) \apply_filters(
					'better_seo_public_post_type_archives',
					array_values(
						array_filter(
							self::get_all_public(),
							static fn( string $post_type ): bool => \get_post_type_object( $post_type )->has_archive ?? false,
						)
					)
				)
			);
	}

	/**
	 * Returns all supported public post types, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> List of supported public post type slugs.
	 */
	public static function get_all_supported(): array {
		return memo() ?? memo( array_values(
			array_filter(
				self::get_all_public(),
				[ self::class, 'is_supported' ],
			)
		) );
	}

	/**
	 * Returns all public post types, memoized.
	 *
	 * Merges forced-supported post types with all public viewable post types.
	 * Applies the better_seo_public_post_types filter.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> List of public post type slugs.
	 */
	public static function get_all_public(): array {
		return umemo( __METHOD__ )
			?? umemo(
				__METHOD__,
				(array) \apply_filters(
					'better_seo_public_post_types',
					array_values( array_filter(
						array_unique( array_merge(
							self::get_all_forced_supported(),
							array_keys( (array) \get_post_types( [ 'public' => true ] ) ),
						) ),
						'is_post_type_viewable',
					) ),
				),
			);
	}

	/**
	 * Returns all forced-supported post types (built-in public post types).
	 *
	 * Applies the better_seo_forced_supported_post_types filter.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> List of forced-supported post type slugs.
	 */
	public static function get_all_forced_supported(): array {
		/**
		 * Filters the forced-supported post types for Better SEO.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, string> $post_types The forced-supported post type slugs.
		 */
		return (array) \apply_filters(
			'better_seo_forced_supported_post_types',
			array_values( \get_post_types( [
				'public'   => true,
				'_builtin' => true,
			] ) ),
		);
	}

	/**
	 * Returns all hierarchical public post types, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Map of post type slug to slug (names format).
	 */
	public static function get_all_hierarchical(): array {
		return memo() ?? memo(
			\get_post_types(
				[
					'hierarchical' => true,
					'public'       => true,
				],
				'names',
			)
		);
	}

	/**
	 * Returns all non-hierarchical public post types, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Map of post type slug to slug (names format).
	 */
	public static function get_all_nonhierarchical(): array {
		return memo() ?? memo(
			\get_post_types(
				[
					'hierarchical' => false,
					'public'       => true,
				],
				'names',
			)
		);
	}

	/**
	 * Returns the singular or plural label for a given post type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type The post type slug.
	 * @param bool   $singular  Whether to return the singular label. Default true.
	 * @return string The post type label, or empty string if not found.
	 */
	public static function get_label( string $post_type, bool $singular = true ): string {
		return \get_post_type_object( $post_type )->labels->{
			$singular ? 'singular_name' : 'name'
		} ?? '';
	}
}