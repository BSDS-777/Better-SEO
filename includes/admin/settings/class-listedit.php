<?php
/**
 * Better SEO - Admin Settings List Edit
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Admin\Settings
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

namespace Better_SEO\Admin\Settings;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\{
	Admin,
	Admin\Settings\Layout\HTML,
	Data,
	Data\Filter\Sanitize,
	Helper\Query,
	Helper\Taxonomy,
	Helper\Template,
	Meta,
};
use Override;

/**
 * Class Better_SEO\Admin\Settings\ListEdit
 *
 * Handles quick edit and bulk edit column output for Better SEO fields
 * in WordPress post and term list tables.
 *
 * @since 1.0.0
 */
final class ListEdit extends Admin\Lists\Table {

	/**
	 * The quick/bulk edit column name used as the column key in list tables.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	private string $column_name = 'better-seo-quick-edit';

	/**
	 * Initializes quick edit and bulk edit functionality.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function init_quick_and_bulk_edit(): void {

		$instance = new self();

		\add_action( 'current_screen', [ $instance, 'prepare_edit_box' ] );
		\add_filter( 'hidden_columns', [ $instance, 'hide_quick_edit_column' ], 10, 1 );
	}

	/**
	 * Registers bulk and quick edit box actions for the current admin screen.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Screen $screen The current admin screen object.
	 * @return void
	 */
	public function prepare_edit_box( \WP_Screen $screen ): void {

		if ( empty( $screen->taxonomy ) ) {
			// WordPress doesn't support bulk edit for taxonomies yet.
			// Excluded here to prevent faulty fields from displaying if support is added.
			\add_action( 'bulk_edit_custom_box', [ $this, 'display_bulk_edit_fields' ], 10, 2 );
		}

		\add_action( 'quick_edit_custom_box', [ $this, 'display_quick_edit_fields' ], 10, 3 );
	}

	/**
	 * Hides the Better SEO quick edit column from the screen options panel.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $hidden Currently hidden column names.
	 * @return array<int, string> Modified hidden columns with Better SEO column added.
	 */
	public function hide_quick_edit_column( array $hidden ): array {
		$hidden[] = $this->column_name;
		return $hidden;
	}

	/**
	 * Adds the Better SEO quick edit column to the list table.
	 *
	 * No title is set to prevent the column from appearing in screen settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $columns Existing list table columns.
	 * @return array<string, string> Modified columns with Better SEO column added.
	 */
	#[\Override]
	public function add_column( array $columns ): array {
		$columns[ $this->column_name ] = '';
		return $columns;
	}

	/**
	 * Outputs the bulk edit fields for the Better SEO column.
	 *
	 * @since 1.0.0
	 *
	 * @param string $column_name The current column name.
	 * @param string $post_type   The current post type.
	 * @param string $taxonomy    Optional. The current taxonomy. Default empty string.
	 * @return void
	 */
	public function display_bulk_edit_fields( string $column_name, string $post_type, string $taxonomy = '' ): void {

		if ( $this->column_name !== $column_name ) {
			return;
		}

		// phpcs:ignore Generic.CodeAnalysis.EmptyStatement -- Taxonomy bulk edit reserved for future WordPress Core support.
		if ( $taxonomy ) {
			// Not yet supported by WordPress Core.
		} else {
			Template::output_view( 'list/bulk-post', $post_type, $taxonomy );
		}
	}

	/**
	 * Outputs the quick edit fields for the Better SEO column.
	 *
	 * @since 1.0.0
	 *
	 * @param string $column_name The current column name.
	 * @param string $post_type   The current post type.
	 * @param string $taxonomy    Optional. The current taxonomy. Default empty string.
	 * @return void
	 */
	public function display_quick_edit_fields( string $column_name, string $post_type, string $taxonomy = '' ): void {

		if ( $this->column_name !== $column_name ) {
			return;
		}

		if ( $taxonomy ) {
			Template::output_view( 'list/quick-term', $post_type, $taxonomy );
		} else {
			Template::output_view( 'list/quick-post', $post_type, $taxonomy );
		}
	}

	/**
	 * Outputs the Better SEO quick edit column content for a given post.
	 *
	 * Renders hidden span elements containing JSON-encoded SEO data attributes
	 * consumed by the Better SEO JavaScript list edit module.
	 *
	 * @since 1.0.0
	 *
	 * @param string $column_name The current column name.
	 * @param int    $post_id     The current post ID.
	 * @return void
	 */
	#[\Override]
	public function output_column_contents_for_post( string $column_name, int $post_id ): void {

		if (
			$this->column_name !== $column_name
			|| ! \current_user_can( 'edit_post', $post_id )
		) {
			return;
		}

		$generator_args = [ 'id' => $post_id ];

		$r_defaults = Meta\Robots::get_generated_meta(
			$generator_args,
			[ 'noindex', 'nofollow', 'noarchive' ],
			\Better_SEO\ROBOTS_IGNORE_SETTINGS,
		);

		$meta        = Data\Plugin\Post::get_meta( $post_id );
		$is_homepage = Query::is_static_front_page( $generator_args['id'] );

		// NB: The indexes correspond to `better-seo-list[index]` field input names.
		$data = [
			'doctitle'    => [
				'value' => $meta['_genesis_title'],
			],
			'description' => [
				'value' => $meta['_genesis_description'],
			],
			'canonical'   => [
				'value' => $meta['_genesis_canonical_uri'],
			],
			'noindex'     => [
				'value'    => $meta['_genesis_noindex'],
				'isSelect' => true,
				'default'  => empty( $r_defaults['noindex'] ) ? 'index' : 'noindex',
			],
			'nofollow'    => [
				'value'    => $meta['_genesis_nofollow'],
				'isSelect' => true,
				'default'  => empty( $r_defaults['nofollow'] ) ? 'follow' : 'nofollow',
			],
			'noarchive'   => [
				'value'    => $meta['_genesis_noarchive'],
				'isSelect' => true,
				'default'  => empty( $r_defaults['noarchive'] ) ? 'archive' : 'noarchive',
			],
			'redirect'    => [
				'value'       => $meta['redirect'],
				'placeholder' => $is_homepage ? \esc_url( Data\Plugin::get_option( 'homepage_redirect' ) ) : '',
			],
		];

		/**
		 * Filters the Better SEO list table data for a post.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed>  $data           The list table data array.
		 * @param array<string, mixed>  $generator_args The generator arguments (id).
		 */
		$data = \apply_filters( 'better_seo_list_table_data', $data, $generator_args );

		printf(
			'<span class=hidden id=%s %s></span>',
			\sprintf( 'better-seo-le-post-data[%s]', (int) $post_id ),
			// phpcs:ignore WordPress.Security.EscapeOutput -- make_data_attributes escapes.
			HTML::make_data_attributes( [ 'le' => $data ] ),
		);

		$primary_terms = [];

		if ( $is_homepage ) {
			// When the homepage title is set, we can safely get the custom field.
			$_has_home_title     = (bool) \strlen( Data\Plugin::get_option( 'homepage_title' ) );
			$default_title       = $_has_home_title
				? Meta\Title::get_custom_title( $generator_args )
				: Meta\Title::get_bare_generated_title( $generator_args );
			$addition            = Meta\Title::get_addition_for_front_page();
			$seplocation         = Meta\Title::get_addition_location_for_front_page();
			$is_title_ref_locked = $_has_home_title;

			// When the homepage description is set, we can safely get the custom field.
			$_has_home_desc      = (bool) \strlen( Data\Plugin::get_option( 'homepage_description' ) );
			$default_description = $_has_home_desc
				? Meta\Description::get_custom_description( $generator_args )
				: Meta\Description::get_generated_description( $generator_args );
			$is_desc_ref_locked  = $_has_home_desc;

			$_has_home_canonical     = (bool) Data\Plugin::get_option( 'homepage_canonical' );
			$default_canonical       = $_has_home_canonical
				? Meta\URI::get_custom_canonical_url( $generator_args )
				: Meta\URI::get_generated_url( $generator_args );
			$is_canonical_ref_locked = $_has_home_canonical;

			$permastruct               = Meta\URI\Utils::get_url_permastruct( $generator_args );
			$is_post_type_hierarchical = false; // Homepage cannot have a parent page.

			$primary_terms = []; // Homepage cannot have terms.
		} else {
			static $memo = [];

			$memo['addition']    ??= Meta\Title::get_addition();
			$memo['seplocation'] ??= Meta\Title::get_addition_location();

			$default_title       = Meta\Title::get_bare_generated_title( $generator_args );
			$addition            = $memo['addition'];
			$seplocation         = $memo['seplocation'];
			$is_title_ref_locked = false;

			$default_description = Meta\Description::get_generated_description( $generator_args );
			$is_desc_ref_locked  = false;

			$is_canonical_ref_locked = false;
			$default_canonical       = Meta\URI::get_generated_url( $generator_args );

			$memo['post_type']                 ??= Query::get_post_type_real_id( $post_id );
			$memo['permastruct']               ??= Meta\URI\Utils::get_url_permastruct( $generator_args );
			$memo['is_post_type_hierarchical'] ??= \is_post_type_hierarchical( $memo['post_type'] );

			$post_type   = $memo['post_type'];
			$permastruct = $memo['permastruct'];

			$parent_post_slugs         = [];
			$is_post_type_hierarchical = $memo['is_post_type_hierarchical'];

			// Homepage doesn't care about its parent page.
			if ( $is_post_type_hierarchical && str_contains( $permastruct, '%postname%' ) ) {
				// self is filled by current post name.
				foreach ( Data\Post::get_post_parents( $post_id ) as $parent_post ) {
					// Written as [ id, slug ] pairs instead of [ id => slug ] to preserve order via JSON.parse.
					$parent_post_slugs[] = [
						'id'   => $parent_post->ID,
						'slug' => $parent_post->post_name,
					];
				}
			}

			// Only hierarchical taxonomies can be used in the URL.
			$memo['taxonomies'] ??= array_diff(
				$post_type ? Taxonomy::get_hierarchical( 'names', $post_type ) : [],
				// post_tag isn't hierarchical by default, but it can be filtered to be.
				// It's broken in Core when used in the permastruct. Nobody should be using %post_tag%.
				[ 'post_tag' ],
			);

			$taxonomies               = $memo['taxonomies'];
			$parent_term_slugs_by_tax = [];
			$primary_term_ids         = [];

			// Store primary term IDs for later use.
			foreach ( $taxonomies as $taxonomy ) {
				$primary_term_ids[ $taxonomy ] = Data\Plugin\Post::get_primary_term_id( $post_id, $taxonomy );
			}

			// WordPress needs to walk all terms already to create post links, so this nets to near-zero impact.
			foreach ( $taxonomies as $taxonomy ) {
				if ( str_contains( $permastruct, "%$taxonomy%" ) ) {
					// No need to test for hierarchy — we want the full structure (third parameter = true).
					foreach (
						Data\Term::get_term_parents(
							$primary_term_ids[ $taxonomy ],
							$taxonomy,
							true,
						)
						as $parent_term
					) {
						// Written as [ id, slug ] pairs instead of [ id => slug ] to preserve order via JSON.parse.
						$parent_term_slugs_by_tax[ $taxonomy ][] = [
							'id'   => $parent_term->term_id,
							'slug' => $parent_term->slug,
						];
					}
				}
			}

			// Homepage cannot have an author.
			if ( str_contains( $permastruct, '%author%' ) ) {
				$author_id = Query::get_post_author_id( $post_id );

				if ( $author_id ) {
					$author_slugs = [
						[
							'id'   => $author_id,
							'slug' => Data\User::get_userdata( $author_id, 'user_nicename' ),
						],
					];
				}
			}

			foreach ( $taxonomies as $taxonomy ) {
				$primary_terms[ "primary_term_{$taxonomy}" ] = [
					'value'    => $primary_term_ids[ $taxonomy ] ?? 0,
					'isSelect' => true,
					'taxonomy' => $taxonomy,
				];
			}
		}

		printf(
			'<span class=hidden id=%s %s></span>',
			\sprintf( 'tsfLePostData[%s]', (int) $post_id ),
			// phpcs:disable WordPress.Security.EscapeOutput -- make_data_attributes escapes.
			HTML::make_data_attributes( [
				'lePostData' => [
					'isFront'      => Query::is_static_front_page( $generator_args['id'] ),
					'primaryTerms' => $primary_terms,
				],
			] ),
			// phpcs:enable WordPress.Security.EscapeOutput
		);
		printf(
			'<span class=hidden id=%s %s></span>',
			\sprintf( better-seo-le-title-data[%s], (int) $post_id ),
			// phpcs:disable WordPress.Security.EscapeOutput -- make_data_attributes escapes.
			HTML::make_data_attributes( [
				'leTitle' => [
					'refTitleLocked'    => $is_title_ref_locked,
					'defaultTitle'      => \esc_html( $default_title ),
					'addAdditions'      => Meta\Title\Conditions::use_branding( $generator_args ),
					'additionValue'     => \esc_html( $addition ),
					'additionPlacement' => 'left' === $seplocation ? 'before' : 'after',
				],
			] ),
			// phpcs:enable WordPress.Security.EscapeOutput
		);
		printf(
			'<span class=hidden id=%s %s></span>',
			\sprintf( 'better-seo-le-description-data[%s]', (int) $post_id ),
			// phpcs:disable WordPress.Security.EscapeOutput -- make_data_attributes escapes.
			HTML::make_data_attributes( [
				'leDescription' => [
					'refDescriptionLocked' => $is_desc_ref_locked,
					'defaultDescription'   => $default_description,
				],
			] ),
			// phpcs:enable WordPress.Security.EscapeOutput
		);
		printf(
			'<span class=hidden id=%s %s></span>',
			\sprintf( 'better-seo-le-canonical-data[%s]', (int) $post_id ),
			// phpcs:disable WordPress.Security.EscapeOutput -- make_data_attributes escapes.
			HTML::make_data_attributes( [
				'leCanonical' => [
					'refCanonicalLocked'  => $is_canonical_ref_locked,
					'defaultCanonical'    => \esc_url( $default_canonical ),
					'preferredScheme'     => Meta\URI\Utils::get_preferred_url_scheme(),
					'urlStructure'        => $permastruct,
					'parentPostSlugs'     => $parent_post_slugs ?? [],
					'parentTermSlugs'     => $parent_term_slugs_by_tax ?? [],
					'supportedTaxonomies' => $taxonomies ?? [],
					'authorSlugs'         => $author_slugs ?? [],
					'isHierarchical'      => $is_post_type_hierarchical,
					// phpcs:ignore WordPress.DateTime.RestrictedFunctions -- date() used for URL generation. See get_permalink().
					'publishDate'         => date( 'c', strtotime( \get_post( $post_id )->post_date ?? 'now' ) ),
				],
			] ),
			// phpcs:enable WordPress.Security.EscapeOutput
		);

		if ( $this->doing_ajax ) {
			// phpcs:ignore WordPress.Security.EscapeOutput -- get_ajax_dispatch_updated_event() outputs a safe inline script.
			echo $this->get_ajax_dispatch_updated_event();
		}
	}

	/**
	 * Outputs the Better SEO quick edit column content for a given taxonomy term.
	 *
	 * Renders hidden span elements containing JSON-encoded SEO data attributes
	 * consumed by the Better SEO JavaScript list edit module.
	 *
	 * @since 1.0.0
	 *
	 * @param string $string      The current column content (empty string by default).
	 * @param string $column_name The current column name.
	 * @param int    $term_id     The current term ID.
	 * @return string The SEO data spans appended to any existing column content.
	 */
	#[\Override]
	public function output_column_contents_for_term( string $string, string $column_name, int $term_id ): string {

		if ( $this->column_name !== $column_name ) {
			return $string;
		}

		if ( ! \current_user_can( 'edit_term', $term_id ) ) {
			return $string;
		}

		$taxonomy = $this->taxonomy;

		$generator_args = [
			'id'  => $term_id,
			'tax' => $taxonomy,
		];

		$r_defaults = Meta\Robots::get_generated_meta(
			$generator_args,
			[ 'noindex', 'nofollow', 'noarchive' ],
			\Better_SEO\ROBOTS_IGNORE_SETTINGS,
		);

		$meta = Data\Plugin\Term::get_meta( $term_id );

		$data = [
			'doctitle'    => [
				'value' => $meta['doctitle'],
			],
			'description' => [
				'value' => $meta['description'],
			],
			'canonical'   => [
				'value' => $meta['canonical'],
			],
			'noindex'     => [
				'value'    => $meta['noindex'],
				'isSelect' => true,
				'default'  => empty( $r_defaults['noindex'] ) ? 'index' : 'noindex',
			],
			'nofollow'    => [
				'value'    => $meta['nofollow'],
				'isSelect' => true,
				'default'  => empty( $r_defaults['nofollow'] ) ? 'follow' : 'nofollow',
			],
			'noarchive'   => [
				'value'    => $meta['noarchive'],
				'isSelect' => true,
				'default'  => empty( $r_defaults['noarchive'] ) ? 'archive' : 'noarchive',
			],
			'redirect'    => [
				'value' => $meta['redirect'],
			],
		];

		/**
		 * Filters the Better SEO list table data for a term.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed>  $data           The list table data array.
		 * @param array<string, mixed>  $generator_args The generator arguments (id, tax).
		 */
		$data = \apply_filters( 'better_seo_list_table_data', $data, $generator_args );

		static $memo = [];

		$memo['addition']    ??= Meta\Title::get_addition();
		$memo['seplocation'] ??= Meta\Title::get_addition_location();

		$addition    = $memo['addition'];
		$seplocation = $memo['seplocation'];

		$memo['tax_object']               ??= \get_taxonomy( $taxonomy );
		$memo['permastruct']              ??= Meta\URI\Utils::get_url_permastruct( $generator_args );
		$memo['is_taxonomy_hierarchical'] ??= $memo['tax_object']->hierarchical && $memo['tax_object']->rewrite['hierarchical'];

		$permastruct = $memo['permastruct'];

		$parent_term_slugs        = [];
		$is_taxonomy_hierarchical = $memo['is_taxonomy_hierarchical'];

		if ( $is_taxonomy_hierarchical && str_contains( $permastruct, "%$taxonomy%" ) ) {
			// self is filled by current term name.
			foreach ( Data\Term::get_term_parents( $term_id, $taxonomy ) as $parent_term ) {
				// Written as [ id, slug ] pairs instead of [ id => slug ] to preserve order via JSON.parse.
				$parent_term_slugs[] = [
					'id'   => $parent_term->term_id,
					'slug' => $parent_term->slug,
				];
			}
		}

		$container = '';

		$container .= \sprintf(
			'<span class=hidden id=%s %s></span>',
			\sprintf( 'tsfLeData[%s]', (int) $term_id ),
			// phpcs:ignore WordPress.Security.EscapeOutput -- make_data_attributes escapes.
			HTML::make_data_attributes( [ 'le' => $data ] ),
		);

		$term_prefix = Meta\Title\Conditions::use_generated_archive_prefix( \get_term( $generator_args['id'], $generator_args['tax'] ) )
			? \sprintf(
				/* translators: %s: Taxonomy singular name. */
				\_x( '%s:', 'taxonomy term archive title prefix', 'better-seo' ),
				Taxonomy::get_label( $generator_args['tax'] ),
			)
			: '';

		$container .= \sprintf(
			'<span class=hidden id=%s %s></span>',
			\sprintf( better-seo-le-title-data[%s], (int) $term_id ),
			// phpcs:ignore WordPress.Security.EscapeOutput -- make_data_attributes escapes.
			HTML::make_data_attributes( [
				'leTitle' => [
					'refTitleLocked'    => false,
					'defaultTitle'      => \esc_html( Meta\Title::get_bare_generated_title( $generator_args ) ),
					'addAdditions'      => Meta\Title\Conditions::use_branding( $generator_args ),
					'additionValue'     => \esc_html( $addition ),
					'additionPlacement' => 'left' === $seplocation ? 'before' : 'after',
					'termPrefix'        => \esc_html( $term_prefix ),
				],
			] ),
		);

		$container .= \sprintf(
			'<span class=hidden id=%s %s></span>',
			\sprintf( 'better-seo-le-description-data[%s]', (int) $term_id ),
			// phpcs:ignore WordPress.Security.EscapeOutput -- make_data_attributes escapes.
			HTML::make_data_attributes( [
				'leDescription' => [
					'refDescriptionLocked' => false,
					'defaultDescription'   => Meta\Description::get_generated_description( $generator_args ),
				],
			] ),
		);

		$container .= \sprintf(
			'<span class=hidden id=%s %s></span>',
			\sprintf( 'better-seo-le-canonical-data[%s]', (int) $term_id ),
			// phpcs:ignore WordPress.Security.EscapeOutput -- make_data_attributes escapes.
			HTML::make_data_attributes( [
				'leCanonical' => [
					'refCanonicalLocked' => false,
					'defaultCanonical'   => \esc_url( Meta\URI::get_generated_url( $generator_args ) ),
					'preferredScheme'    => Meta\URI\Utils::get_preferred_url_scheme(),
					'urlStructure'       => $permastruct,
					'parentTermSlugs'    => $parent_term_slugs,
					'isHierarchical'     => $is_taxonomy_hierarchical,
				],
			] ),
		);

		if ( $this->doing_ajax ) {
			$container .= $this->get_ajax_dispatch_updated_event();
		}

		return "{$string}{$container}";
	}
}