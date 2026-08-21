<?php
/**
 * Better SEO - Helper Taxonomy
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
 * Class Better_SEO\Helper\Taxonomy
 *
 * Provides taxonomy detection, support checking, and listing utilities for Better SEO,
 * including public, supported, hierarchical, and forced-supported taxonomy queries.
 *
 * @since 1.0.0
 */
class Taxonomy {

	/**
	 * Returns whether the given taxonomy is disabled in Better SEO settings.
	 *
	 * A taxonomy is disabled if explicitly disabled in settings, or if none of
	 * its associated post types are supported by Better SEO.
	 * Applies the better_seo_taxonomy_disabled filter.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $taxonomy The taxonomy slug. Default null (current taxonomy).
	 * @return bool True if the taxonomy is disabled.
	 */
	public static function is_disabled( ?string $taxonomy = null ): bool {

		$disabled = false;

		if ( $taxonomy && Data\Plugin::get_option( 'disabled_taxonomies', $taxonomy ) ) {
			$disabled = true;
		} else {
			foreach ( self::get_post_types( $taxonomy ) as $type ) {
				if ( Post_Type::is_supported( $type ) ) {
					$disabled = false;
					break;
				}
				$disabled = true;
			}
		}

		/**
		 * Filters whether the given taxonomy is disabled in Better SEO.
		 *
		 * @since 1.0.0
		 *
		 * @param bool        $disabled Whether the taxonomy is disabled.
		 * @param string|null $taxonomy The taxonomy slug.
		 */
		return \apply_filters(
			'better_seo_taxonomy_disabled',
			$disabled,
			$taxonomy,
		);
	}

	/**
	 * Returns whether the given taxonomy is supported by Better SEO.
	 *
	 * Applies the better_seo_supported_taxonomy filter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $taxonomy The taxonomy slug. Defaults to current taxonomy.
	 * @return bool True if the taxonomy is supported.
	 */
	public static function is_supported( string $taxonomy = '' ): bool {

		$taxonomy = $taxonomy ?: Query::get_current_taxonomy();

		/**
		 * Filters whether the given taxonomy is supported by Better SEO.
		 *
		 * @since 1.0.0
		 *
		 * @param bool   $supported Whether the taxonomy is supported.
		 * @param string $taxonomy  The taxonomy slug.
		 */
		return (bool) \apply_filters(
			'better_seo_supported_taxonomy',
			(
				   $taxonomy
				&& ! self::is_disabled( $taxonomy )
				&& \in_array( $taxonomy, self::get_all_public(), true )
			),
			$taxonomy,
		);
	}

	/**
	 * Returns all supported public taxonomies, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> List of supported taxonomy slugs.
	 */
	public static function get_all_supported(): array {
		return memo() ?? memo( array_values( array_filter(
			self::get_all_public(),
			[ self::class, 'is_supported' ],
		) ) );
	}

	/**
	 * Returns all public taxonomies, memoized.
	 *
	 * Merges forced-supported taxonomies with all public non-builtin viewable taxonomies.
	 * Applies the better_seo_public_taxonomies filter.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> List of public taxonomy slugs.
	 */
	public static function get_all_public(): array {
		return umemo( __METHOD__ )
			?? umemo(
				__METHOD__,
				(array) \apply_filters(
					'better_seo_public_taxonomies',
					array_filter(
						array_unique( array_merge(
							self::get_all_forced_supported(),
							// array_values() because get_taxonomies() gives a sequential array.
							array_values( \get_taxonomies( [
								'public'   => true,
								'_builtin' => false,
							] ) ),
						) ),
						'is_taxonomy_viewable',
					)
				)
			);
	}

	/**
	 * Returns all forced-supported taxonomies (built-in public taxonomies).
	 *
	 * Applies the better_seo_forced_supported_taxonomies filter.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> List of forced-supported taxonomy slugs.
	 */
	public static function get_all_forced_supported(): array {
		/**
		 * Filters the forced-supported taxonomies for Better SEO.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, string> $taxonomies The forced-supported taxonomy slugs.
		 */
		return (array) \apply_filters(
			'better_seo_forced_supported_taxonomies',
			array_values( \get_taxonomies( [
				'public'   => true,
				'_builtin' => true,
			] ) ),
		);
	}

	/**
	 * Returns the post types associated with the given taxonomy.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $taxonomy The taxonomy slug. Defaults to current taxonomy.
	 * @return array<int, string> List of associated post type slugs.
	 */
	public static function get_post_types( ?string $taxonomy = '' ): array {

		$taxonomy = $taxonomy ?: Query::get_current_taxonomy();
		$tax      = $taxonomy ? \get_taxonomy( $taxonomy ) : null;

		return $tax->object_type ?? [];
	}

	/**
	 * Returns hierarchical taxonomies for the given post type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $get       Whether to return 'names' (slugs) or 'objects'. Default 'objects'.
	 * @param string $post_type The post type slug. Defaults to current post type.
	 * @return array<string, \WP_Taxonomy>|array<int, string> Taxonomy objects or slug list.
	 */
	public static function get_hierarchical( string $get = 'objects', string $post_type = '' ): array {

		$post_type = $post_type ?: Query::get_current_post_type();

		if ( ! $post_type ) {
			return [];
		}

		$taxonomies = array_filter(
			\get_object_taxonomies( $post_type, 'objects' ),
			static fn( \WP_Taxonomy $t ): bool => ! empty( $t->hierarchical ),
		);

		return 'names' === $get ? array_keys( $taxonomies ) : $taxonomies;
	}

	/**
	 * Returns the singular or plural label for a given taxonomy.
	 *
	 * @since 1.0.0
	 *
	 * @param string $taxonomy The taxonomy slug. Defaults to current taxonomy.
	 * @param bool   $singular Whether to return the singular label. Default true.
	 * @return string The taxonomy label, or empty string if not found.
	 */
	public static function get_label( string $taxonomy = '', bool $singular = true ): string {

		$taxonomy = $taxonomy ?: Query::get_current_taxonomy();
		$tax      = $taxonomy ? \get_taxonomy( $taxonomy ) : null;

		return $tax->labels->{
			$singular ? 'singular_name' : 'name'
		} ?? '';
	}
}