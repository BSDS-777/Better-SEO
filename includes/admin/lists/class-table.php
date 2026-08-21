<?php
/**
 * Better SEO - Admin Lists Table
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Admin\Lists
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

namespace Better_SEO\Admin\Lists;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\Helper\{
	Post_Type,
	Query,
	Taxonomy,
};

/**
 * Class Better_SEO\Admin\Lists\Table
 *
 * Abstract base class for Better SEO admin list table column management.
 * Handles registration of custom columns for posts, pages, and taxonomy terms,
 * including support for standard and AJAX-based inline editing workflows.
 *
 * @since 1.0.0
 */
abstract class Table {

	/**
	 * The current post type being processed.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	protected string $post_type = '';

	/**
	 * The current taxonomy being processed.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	protected string $taxonomy = '';

	/**
	 * Whether the current request is an AJAX request.
	 *
	 * @since 1.0.0
	 * @var   bool
	 */
	protected bool $doing_ajax = false;

	/**
	 * Constructor. Registers WordPress action hooks for column initialization.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Initialize columns on screen load.
		\add_action( 'current_screen', [ $this, 'prepare_columns' ] );

		// AJAX handlers for inline editing column support.
		\add_action( 'wp_ajax_add-tag', [ $this, 'prepare_columns_wp_ajax_add_tag' ], -1 );
		\add_action( 'wp_ajax_inline-save', [ $this, 'prepare_columns_wp_ajax_inline_save' ], -1 );
		\add_action( 'wp_ajax_inline-save-tax', [ $this, 'prepare_columns_wp_ajax_inline_save_tax' ], -1 );
	}

	/**
	 * Prepares columns for the current admin screen.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Screen $screen The current admin screen object.
	 * @return void
	 */
	public function prepare_columns( \WP_Screen $screen ): void {
		$this->init_columns( $screen );
	}

	/**
	 * Prepares columns for the wp_ajax_add-tag AJAX action.
	 *
	 * Verifies nonce and user capability before initializing AJAX columns.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function prepare_columns_wp_ajax_add_tag(): void {

		if (
			! \check_ajax_referer( 'add-tag', '_wpnonce_add-tag', false )
			|| empty( $_POST['taxonomy'] )
		) {
			return;
		}

		$taxonomy   = sanitize_key( wp_unslash( $_POST['taxonomy'] ) );
		$tax_object = $taxonomy ? \get_taxonomy( $taxonomy ) : false;

		if ( $tax_object && \current_user_can( $tax_object->cap->edit_terms ) ) {
			$this->init_columns_ajax();
		}
	}

	/**
	 * Prepares columns for the wp_ajax_inline-save AJAX action.
	 *
	 * Verifies nonce and user capability before initializing AJAX columns.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function prepare_columns_wp_ajax_inline_save(): void {

		if (
			! \check_ajax_referer( 'inlineeditnonce', '_inline_edit', false )
			|| empty( $_POST['post_ID'] )
			|| empty( $_POST['post_type'] )
			|| ! \current_user_can(
				'page' === $_POST['post_type'] ? 'edit_page' : 'edit_post',
				(int) $_POST['post_ID'],
			)
		) {
			return;
		}

		$this->init_columns_ajax();
	}

	/**
	 * Prepares columns for the wp_ajax_inline-save-tax AJAX action.
	 *
	 * Verifies nonce and user capability before initializing AJAX columns.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function prepare_columns_wp_ajax_inline_save_tax(): void {

		if (
			! \check_ajax_referer( 'taxinlineeditnonce', '_inline_edit', false )
			|| empty( $_POST['tax_ID'] )
			|| ! \current_user_can( 'edit_term', (int) $_POST['tax_ID'] )
		) {
			return;
		}

		$this->init_columns_ajax();
	}

	/**
	 * Initializes columns for the current admin screen.
	 *
	 * Checks whether the current screen is a supported post type or taxonomy
	 * list table and registers the appropriate column filters and actions.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Screen $screen The current admin screen object.
	 * @return void
	 */
	private function init_columns( \WP_Screen $screen ): void {

		if (
			! Query::is_wp_lists_edit()
			|| empty( $screen->id )
		) {
			return;
		}

		$post_type = $screen->post_type ?? '';
		$taxonomy  = $screen->taxonomy ?? '';

		if ( $taxonomy ) {
			if ( ! Taxonomy::is_supported( $taxonomy ) ) {
				return;
			}
		} else {
			if ( ! Post_Type::is_supported( $post_type ) ) {
				return;
			}
		}

		$this->post_type = $post_type;
		$this->taxonomy  = $taxonomy;

		if ( $taxonomy ) {
			\add_filter( "manage_{$taxonomy}_custom_column", [ $this, 'output_column_contents_for_term' ], 1, 3 );
		}

		\add_filter( "manage_{$screen->id}_columns", [ $this, 'add_column' ] );

		// Always load posts and pages — many CPT plugins rely on these hooks.
		\add_action( 'manage_posts_custom_column', [ $this, 'output_column_contents_for_post' ], 1, 2 );
		\add_action( 'manage_pages_custom_column', [ $this, 'output_column_contents_for_post' ], 1, 2 );
	}

	/**
	 * Initializes columns for AJAX inline editing requests.
	 *
	 * Reads post type and taxonomy from POST data and registers the appropriate
	 * column filters and actions for the AJAX response.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function init_columns_ajax(): void {
		// phpcs:disable WordPress.Security.NonceVerification -- prepare_columns_wp_ajax_* methods verify nonces before calling this.

		$taxonomy  = isset( $_POST['taxonomy'] )  ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) )  : '';
		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( $_POST['post_type'] ) ) : '';

		// wp-admin/js/inline-edit-tax.js sends tax_type instead of post_type.
		$post_type = $post_type
			?: ( isset( $_POST['tax_type'] ) ? sanitize_key( wp_unslash( $_POST['tax_type'] ) ) : '' );

		if ( $taxonomy ) {
			if ( ! Taxonomy::is_supported( $taxonomy ) ) {
				return;
			}
		} else {
			if ( ! Post_Type::is_supported( $post_type ) ) {
				return;
			}
		}

		$this->doing_ajax = true;
		$this->post_type  = $post_type;
		$this->taxonomy   = $taxonomy;

		$screen_id = isset( $_POST['screen'] ) ? sanitize_key( wp_unslash( $_POST['screen'] ) ) : '';

		// Not elseif — either request type may need term column output.
		if ( $taxonomy ) {
			\add_filter( "manage_{$taxonomy}_custom_column", [ $this, 'output_column_contents_for_term' ], 1, 3 );
		}

		if ( $screen_id ) {
			// Standard inline-save action — screen ID is available.
			\add_filter( "manage_{$screen_id}_columns", [ $this, 'add_column' ] );

			// Always load posts and pages — many CPT plugins rely on these hooks.
			\add_action( 'manage_posts_custom_column', [ $this, 'output_column_contents_for_post' ], 1, 2 );
			\add_action( 'manage_pages_custom_column', [ $this, 'output_column_contents_for_post' ], 1, 2 );
		} elseif ( $taxonomy ) {
			/**
			 * The inline-save-tax action does not POST a 'screen' value.
			 *
			 * @see WP Core wp_ajax_inline_save_tax():
			 *    `_get_list_table( 'WP_Terms_List_Table', array( 'screen' => "edit-$taxonomy" ) );`
			 */
			\add_filter( "manage_edit-{$taxonomy}_columns", [ $this, 'add_column' ] );
		}

		// phpcs:enable WordPress.Security.NonceVerification
	}

	/**
	 * Returns a JavaScript snippet that dispatches the Better SEO list edit update event.
	 *
	 * @since 1.0.0
	 *
	 * @return string Inline script tag dispatching the betterSeoLeDispatchUpdate event.
	 */
	protected function get_ajax_dispatch_updated_event(): string {
		return "<script>'use strict';(()=>document.dispatchEvent(new Event('betterSeoLeDispatchUpdate')))();</script>";
	}

	/**
	 * Adds the Better SEO custom column to the list table.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $columns Existing list table columns.
	 * @return array<string, string> Modified columns array.
	 */
	abstract public function add_column( array $columns ): array;

	/**
	 * Outputs the Better SEO column content for a given post.
	 *
	 * @since 1.0.0
	 *
	 * @param string $column_name The name of the current column.
	 * @param int    $post_id     The current post ID.
	 * @return void
	 */
	abstract public function output_column_contents_for_post( string $column_name, int $post_id ): void;

	/**
	 * Outputs the Better SEO column content for a given taxonomy term.
	 *
	 * @since 1.0.0
	 *
	 * @param string $string      The current column content (empty string by default).
	 * @param string $column_name The name of the current column.
	 * @param int    $term_id     The current term ID.
	 * @return void
	 */
	abstract public function output_column_contents_for_term( string $string, string $column_name, int $term_id ): void;
}