<?php
/**
 * Better SEO - Admin SEO Toolbar List Table
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Admin\SEOToolbar
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

namespace Better_SEO\Admin\SEOToolbar;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\Admin;
use Override;

/**
 * Class Better_SEO\Admin\SEOToolbar\ListTable
 *
 * Adds and renders the Better SEO Toolbar column in WordPress post and term list tables.
 *
 * @since 1.0.0
 */
final class ListTable extends Admin\Lists\Table {

	/**
	 * The SEO Toolbar column name used as the column key in list tables.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	private string $column_name = 'better-seo-toolbar-wrap';

	/**
	 * Instantiates the ListTable class to register SEO Toolbar column hooks.
	 *
	 * @since 1.0.0
	 * @hook  admin_init 10
	 *
	 * @return void
	 */
	public static function init_seo_toolbar(): void {
		new self();
	}

	/**
	 * Adds the Better SEO Toolbar column to the list table.
	 *
	 * Inserts the SEO column before the first matching key from the order list,
	 * or appends it to the end if no matching key is found.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $columns Existing list table columns.
	 * @return array<string, string> Modified columns with SEO Toolbar column inserted.
	 */
	#[\Override]
	public function add_column( array $columns ): array {

		$seocolumn   = [ $this->column_name => 'SEO' ];
		$column_keys = array_keys( $columns );

		// Column keys to search for insertion point, in order of preference.
		$order_keys = [
			'comments',
			'posts',
			'date',
			'tags',
		];

		/**
		 * Filters the column keys used to determine SEO Toolbar column insertion order.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, string> $order_keys Column keys to search for, in order of preference.
		 */
		$order_keys = (array) \apply_filters( 'better_seo_seo_column_keys_order', $order_keys );

		$offset = false;

		foreach ( $order_keys as $key ) {
			$offset = array_search( $key, $column_keys, true );
			if ( false !== $offset ) {
				break;
			}
		}

		if ( false === $offset ) {
			// No matching key found — append SEO Toolbar column at the end.
			return array_merge( $columns, $seocolumn );
		}

		// Insert SEO Toolbar column before the matched column.
		$columns_before = $columns;

		return array_merge(
			array_splice( $columns, 0, $offset ),
			$seocolumn,
			array_splice( $columns_before, $offset ),
		);
	}

	/**
	 * Outputs the Better SEO Toolbar column content for a given post.
	 *
	 * @since 1.0.0
	 *
	 * @param string $column_name The current column name.
	 * @param int    $post_id     The current post ID.
	 * @return void
	 */
	#[\Override]
	public function output_column_contents_for_post( string $column_name, int $post_id ): void {

		if ( $this->column_name !== $column_name ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput -- Builder::generate_bar() handles escaping internally.
		echo Builder::generate_bar( [
			'id'        => $post_id,
			'post_type' => $this->post_type,
		] );

		if ( $this->doing_ajax ) {
			// phpcs:ignore WordPress.Security.EscapeOutput -- get_ajax_dispatch_updated_event() outputs a safe inline script.
			echo $this->get_ajax_dispatch_updated_event();
		}
	}

	/**
	 * Outputs the Better SEO Toolbar column content for a given taxonomy term.
	 *
	 * @since 1.0.0
	 *
	 * @param string $string      The current column content (empty string by default).
	 * @param string $column_name The current column name.
	 * @param int    $term_id     The current term ID.
	 * @return string The SEO Toolbar HTML prepended to any existing column content.
	 */
	#[\Override]
	public function output_column_contents_for_term( string $string, string $column_name, int $term_id ): string {

		if ( $this->column_name !== $column_name ) {
			return $string;
		}

		if ( $this->doing_ajax ) {
			$string .= $this->get_ajax_dispatch_updated_event();
		}

		return Builder::generate_bar( [
			'id'  => $term_id,
			'tax' => $this->taxonomy,
		] ) . $string;
	}
}
