<?php
/**
 * Better SEO - Meta Title Conditions
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Meta\Title
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

namespace Better_SEO\Meta\Title;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\{
	get_query_type_from_args,
	memo,
	normalize_generation_args,
};

use Better_SEO\{
	Data,
	Helper\Query,
	Meta,
};

/**
 * Class Better_SEO\Meta\Title\Conditions
 *
 * Provides condition checks for Better SEO title generation, determining
 * whether protection status, pagination, and branding should be applied.
 *
 * @since 1.0.0
 */
class Conditions {

	/**
	 * Returns whether the protection status prefix should be added to the title.
	 *
	 * Returns true only for singular posts that have a password set.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return bool True if the protection status prefix should be applied.
	 */
	public static function use_protection_status( ?array $args = null ): bool {

		if ( isset( $args ) ) {
			normalize_generation_args( $args );

			if ( 'single' !== get_query_type_from_args( $args ) ) {
				return false;
			}

			$id = $args['id'];
		} else {
			if ( ! Query::is_singular() ) {
				return false;
			}

			$id = Query::get_the_real_id();
		}

		$post = \get_post( $id );

		return ! empty( $post->post_password );
	}

	/**
	 * Returns whether the pagination indicator should be added to the title.
	 *
	 * Returns false for args-based generation, 404 pages, and admin screens.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return bool True if the pagination indicator should be applied.
	 */
	public static function use_pagination( ?array $args = null ): bool {

		if ( isset( $args ) || \is_404() || \is_admin() ) {
			return false;
		}

		return Query::is_multipage();
	}

	/**
	 * Returns whether branding (site name/tagline) should be added to the title.
	 *
	 * For social titles, checks the social_title_rem_additions option.
	 * Applies the better_seo_use_title_branding filter.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args   Optional generation args. Default null.
	 * @param bool                      $social Whether this is for social output. Default false.
	 * @return bool True if branding should be applied.
	 */
	public static function use_branding( ?array $args = null, bool $social = false ): bool {

		$use = $social ? ! Data\Plugin::get_option( 'social_title_rem_additions' ) : true;

		if ( $use ) {
			if ( isset( $args ) ) {
				normalize_generation_args( $args );

				$use = match ( get_query_type_from_args( $args ) ) {
					'single'   => Query::is_static_front_page( $args['id'] )
						? self::use_front_page_tagline()
						: self::use_post_branding( $args['id'] ),
					'term'     => self::use_term_branding( $args['id'] ),
					'homeblog' => self::use_front_page_tagline(),
					'pta'      => self::use_pta_branding( $args['pta'] ),
					'user'     => ! Data\Plugin::get_option( 'title_rem_additions' ),
					default    => $use,
				};
			} else {
				if ( Query::is_real_front_page() ) {
					$use = self::use_front_page_tagline();
				} elseif ( Query::is_singular() ) {
					$use = self::use_post_branding();
				} elseif ( Query::is_editable_term() ) {
					$use = self::use_term_branding();
				} elseif ( \is_post_type_archive() ) {
					$use = self::use_pta_branding();
				} else {
					$use = ! Data\Plugin::get_option( 'title_rem_additions' );
				}
			}
		}

		/**
		 * Filters whether Better SEO should apply title branding.
		 *
		 * @since 1.0.0
		 *
		 * @param bool                      $use    Whether to apply branding.
		 * @param array<string, mixed>|null $args   The generation args, or null.
		 * @param bool                      $social Whether this is for social output.
		 */
		return \apply_filters(
			'better_seo_use_title_branding',
			$use,
			$args,
			(bool) $social,
		);
	}

	/**
	 * Returns whether the front page tagline should be used as the title addition.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the homepage tagline option is enabled and a tagline is set.
	 */
	private static function use_front_page_tagline(): bool {
		return Data\Plugin::get_option( 'homepage_tagline' )
			&& Meta\Title::get_addition_for_front_page();
	}

	/**
	 * Returns whether branding should be applied to the given post's title.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Optional post ID. Default 0 (current post).
	 * @return bool True if branding should be applied.
	 */
	private static function use_post_branding( int $id = 0 ): bool {
		return ! Data\Plugin\Post::get_meta_item( '_better_seo_title_no_blogname', $id )
			&& ! Data\Plugin::get_option( 'title_rem_additions' );
	}

	/**
	 * Returns whether branding should be applied to the given term's title.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Optional term ID. Default 0 (current term).
	 * @return bool True if branding should be applied.
	 */
	private static function use_term_branding( int $id = 0 ): bool {
		return ! Data\Plugin\Term::get_meta_item( 'title_no_blog_name', $id )
			&& ! Data\Plugin::get_option( 'title_rem_additions' );
	}

	/**
	 * Returns whether branding should be applied to the given PTA title.
	 *
	 * @since 1.0.0
	 *
	 * @param string $pta Optional post type slug. Default empty (current PTA).
	 * @return bool True if branding should be applied.
	 */
	private static function use_pta_branding( string $pta = '' ): bool {
		return ! Data\Plugin\PTA::get_meta_item( 'title_no_blog_name', $pta )
			&& ! Data\Plugin::get_option( 'title_rem_additions' );
	}

	/**
	 * Returns whether the archive title prefix should be displayed.
	 *
	 * Applies the better_seo_use_archive_prefix filter.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Term|\WP_Post_Type|\WP_User|null $term Optional archive object. Default null (current queried object).
	 * @return bool True if the archive prefix should be displayed.
	 */
	public static function use_generated_archive_prefix( mixed $term = null ): bool {
		/**
		 * Filters whether Better SEO should display the archive title prefix.
		 *
		 * @since 1.0.0
		 *
		 * @param bool  $use    Whether to display the prefix.
		 * @param mixed $object The archive object.
		 */
		return \apply_filters(
			'better_seo_use_archive_prefix',
			! Data\Plugin::get_option( 'title_rem_prefixes' ),
			$term ?? \get_queried_object(),
		);
	}
}