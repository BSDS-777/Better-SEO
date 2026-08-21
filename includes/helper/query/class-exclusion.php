<?php
/**
 * Better SEO - Helper Query Exclusion
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Helper\Query
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

namespace Better_SEO\Helper\Query;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\is_headless;

use Better_SEO\Data;
use Better_SEO\Helper\{
	Post_Type,
	Taxonomy,
};

/**
 * Class Better_SEO\Helper\Query\Exclusion
 *
 * Manages the cache of post IDs excluded from archive and search queries
 * based on Better SEO post meta settings.
 *
 * @since 1.0.0
 */
class Exclusion {

	/**
	 * Clears the excluded post IDs site cache.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the cache was updated successfully.
	 */
	public static function clear_excluded_post_ids_cache(): bool {
		return Data\Plugin::update_site_cache( 'excluded_ids', [] );
	}

	/**
	 * Returns the cached excluded post IDs for archive and search queries.
	 *
	 * Queries the database if the cache is not populated. Returns empty
	 * arrays when running headless. Stores results back to the site cache.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<int, int>|string> Map of 'archive' and 'search' to excluded post ID arrays.
	 */
	public static function get_excluded_ids_from_cache(): array {

		if ( is_headless( 'meta' ) ) {
			return [
				'archive' => '',
				'search'  => '',
			];
		}

		$cache = Data\Plugin::get_site_cache( 'excluded_ids' );

		if ( isset( $cache['archive'], $cache['search'] ) ) {
			return $cache;
		}

		global $wpdb;

		$supported_post_types = Post_Type::get_all_supported();
		$public_post_types    = Post_Type::get_all_public();

		$join  = '';
		$where = '';

		if ( $supported_post_types !== $public_post_types ) {
			$post_type__in = "'" . implode( "','", array_map( 'esc_sql', $supported_post_types ) ) . "'";

			$join  = "LEFT JOIN {$wpdb->posts} ON {$wpdb->postmeta}.post_id = {$wpdb->posts}.ID";
			$where = "AND {$wpdb->posts}.post_type IN ($post_type__in)";
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- join/where are built from escaped values.
		$cache = [
			'archive' => $wpdb->get_results(
				"SELECT post_id, meta_value FROM $wpdb->postmeta $join WHERE meta_key = 'exclude_from_archive' $where",
			),
			'search'  => $wpdb->get_results(
				"SELECT post_id, meta_value FROM $wpdb->postmeta $join WHERE meta_key = 'exclude_local_search' $where",
			),
		];
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( [ 'archive', 'search' ] as $type ) {
			array_walk(
				$cache[ $type ],
				static function ( mixed &$v ): void {
					if ( isset( $v->meta_value, $v->post_id ) && $v->meta_value ) {
						$v = (int) $v->post_id;
					} else {
						$v = false;
					}
				}
			);
			$cache[ $type ] = array_filter( $cache[ $type ] );
		}

		Data\Plugin::update_site_cache( 'excluded_ids', $cache );

		return $cache;
	}
}