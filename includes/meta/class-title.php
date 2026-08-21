<?php
/**
 * Better SEO - Meta Title
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Meta
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

namespace Better_SEO\Meta;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\{
	coalesce_strlen,
	get_query_type_from_args,
	memo,
	normalize_generation_args,
};

use Better_SEO\{
	Data,
	Data\Filter\Sanitize,
};
use Better_SEO\Helper\{
	Post_Type,
	Query,
	Taxonomy,
};

/**
 * Class Better_SEO\Meta\Title
 *
 * Provides title generation for Better SEO, including custom field retrieval,
 * auto-generation, branding, pagination, protection status, and archive titles.
 *
 * @since 1.0.0
 */
class Title {

	/**
	 * Returns the full title (custom or generated, with branding) for the given args or current query.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The full title string.
	 */
	public static function get_title( ?array $args = null ): string {
		return coalesce_strlen( self::get_custom_title( $args ) )
			?? self::get_generated_title( $args );
	}

	/**
	 * Returns the bare title (custom or generated, without branding) for the given args or current query.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The bare title string.
	 */
	public static function get_bare_title( ?array $args = null ): string {
		return coalesce_strlen( self::get_bare_custom_title( $args ) )
			?? self::get_bare_generated_title( $args );
	}

	/**
	 * Returns the custom title with optional branding, pagination, and protection status.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args   Optional generation args. Default null.
	 * @param bool                      $social Whether this is for social output. Default false.
	 * @return string The custom title string.
	 */
	public static function get_custom_title( ?array $args = null, bool $social = false ): string {

		$title = self::get_bare_custom_title( $args );

		if ( ! \strlen( $title ) ) {
			return '';
		}

		if ( Title\Conditions::use_protection_status( $args ) ) {
			$title = self::add_protection_status( $title, $args );
		}

		if ( Title\Conditions::use_pagination( $args ) ) {
			$title = self::add_pagination( $title );
		}

		if ( Title\Conditions::use_branding( $args, $social ) ) {
			$title = self::add_branding( $title, $args );
		}

		return Sanitize::metadata_content( $title );
	}

	/**
	 * Returns the generated title with optional branding, pagination, and protection status.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args   Optional generation args. Default null.
	 * @param bool                      $social Whether this is for social output. Default false.
	 * @return string The generated title string.
	 */
	public static function get_generated_title( ?array $args = null, bool $social = false ): string {

		$title = self::get_bare_generated_title( $args );

		if ( Title\Conditions::use_protection_status( $args ) ) {
			$title = self::add_protection_status( $title, $args );
		}

		if ( Title\Conditions::use_pagination( $args ) ) {
			$title = self::add_pagination( $title );
		}

		if ( Title\Conditions::use_branding( $args, $social ) ) {
			$title = self::add_branding( $title, $args );
		}

		return Sanitize::metadata_content( $title );
	}

	/**
	 * Returns the bare custom title (no branding/pagination) for the given args or current query.
	 *
	 * Applies the better_seo_title_from_custom_field filter.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The bare custom title string.
	 */
	public static function get_bare_custom_title( ?array $args = null ): string {

		if ( isset( $args ) ) {
			normalize_generation_args( $args );
			$title = self::get_custom_title_from_args( $args );
		} else {
			$title = self::get_custom_title_from_query();
		}

		/**
		 * Filters the Better SEO title from custom field.
		 *
		 * @since 1.0.0
		 *
		 * @param string                    $title The custom field title.
		 * @param array<string, mixed>|null $args  The generation args, or null.
		 */
		return Sanitize::metadata_content( (string) \apply_filters(
			'better_seo_title_from_custom_field',
			$title,
			$args,
		) );
	}

	/**
	 * Returns the bare generated title (no branding/pagination) for the given args or current query.
	 *
	 * Applies the better_seo_title_from_generation filter.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The bare generated title string.
	 */
	public static function get_bare_generated_title( ?array $args = null ): string {

		if ( isset( $args ) ) {
			normalize_generation_args( $args );
		}

		if ( null !== $memo = memo( null, $args ) ) {
			return $memo;
		}

		Title\Utils::remove_default_title_filters( false, $args );

		$title = isset( $args )
			? self::generate_title_from_args( $args )
			: self::generate_title_from_query();

		Title\Utils::reset_default_title_filters();

		/**
		 * Filters the Better SEO generated title.
		 *
		 * @since 1.0.0
		 *
		 * @param string                    $title The generated title.
		 * @param array<string, mixed>|null $args  The generation args, or null.
		 */
		$title = (string) \apply_filters(
			'better_seo_title_from_generation',
			$title ?: self::get_untitled_title(),
			$args,
		);

		return memo(
			\strlen( $title ) ? Sanitize::metadata_content( $title ) : '',
			$args,
		);
	}

	/**
	 * Returns the bare unfiltered custom title for the given args or current query.
	 *
	 * Does not apply the better_seo_title_from_custom_field filter.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The bare unfiltered custom title string.
	 */
	public static function get_bare_unfiltered_custom_title( ?array $args = null ): string {
		return isset( $args )
			? self::get_custom_title_from_args( $args )
			: self::get_custom_title_from_query();
	}

	/**
	 * Returns the custom title from the current query context.
	 *
	 * @since 1.0.0
	 *
	 * @return string The custom title string, or empty string if not set.
	 */
	public static function get_custom_title_from_query(): string {

		if ( Query::is_real_front_page() ) {
			if ( Query::is_static_front_page() ) {
				$title = coalesce_strlen( Data\Plugin::get_option( 'homepage_title' ) )
					?? Data\Plugin\Post::get_meta_item( '_genesis_title' );
			} else {
				$title = Data\Plugin::get_option( 'homepage_title' );
			}
		} elseif ( Query::is_singular() ) {
			$title = Data\Plugin\Post::get_meta_item( '_genesis_title' );
		} elseif ( Query::is_editable_term() ) {
			$title = Data\Plugin\Term::get_meta_item( 'doctitle' );
		} elseif ( \is_post_type_archive() ) {
			$title = Data\Plugin\PTA::get_meta_item( 'doctitle' );
		}

		if ( isset( $title ) && \strlen( $title ) ) {
			return Sanitize::metadata_content( $title );
		}

		return '';
	}

	/**
	 * Returns the custom title from the given generation args.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args The generation args.
	 * @return string The custom title string, or empty string if not set.
	 */
	public static function get_custom_title_from_args( array $args ): string {

		normalize_generation_args( $args );

		$title = match ( get_query_type_from_args( $args ) ) {
			'single'   => Query::is_static_front_page( $args['id'] )
				? ( coalesce_strlen( Data\Plugin::get_option( 'homepage_title' ) )
					?? Data\Plugin\Post::get_meta_item( '_genesis_title', $args['id'] ) )
				: Data\Plugin\Post::get_meta_item( '_genesis_title', $args['id'] ),
			'term'     => Data\Plugin\Term::get_meta_item( 'doctitle', $args['id'] ),
			'homeblog' => Data\Plugin::get_option( 'homepage_title' ),
			'pta'      => Data\Plugin\PTA::get_meta_item( 'doctitle', $args['pta'] ),
			default    => null,
		};

		if ( isset( $title ) && \strlen( $title ) ) {
			return Sanitize::metadata_content( $title );
		}

		return '';
	}

	/**
	 * Generates the title from the current query context.
	 *
	 * @since 1.0.0
	 *
	 * @return string The generated title string.
	 */
	public static function generate_title_from_query(): string {

		if ( Query::is_real_front_page() ) {
			$title = self::get_front_page_title();
		} elseif ( Query::is_singular() ) {
			$title = self::get_post_title();
		} elseif ( Query::is_archive() ) {
			$title = self::get_archive_title();
		} elseif ( Query::is_search() ) {
			$title = self::get_search_query_title();
		} elseif ( \is_404() ) {
			$title = self::get_404_title();
		}

		return $title ?? '';
	}

	/**
	 * Generates the title from the given generation args.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args The generation args.
	 * @return string The generated title string.
	 */
	public static function generate_title_from_args( array $args ): string {

		normalize_generation_args( $args );

		$title = match ( get_query_type_from_args( $args ) ) {
			'single'   => Query::is_static_front_page( $args['id'] )
				? self::get_front_page_title()
				: self::get_post_title( $args['id'] ),
			'term'     => self::get_archive_title( \get_term( $args['id'], $args['tax'] ) ),
			'homeblog' => self::get_front_page_title(),
			'pta'      => self::get_archive_title( \get_post_type_object( $args['pta'] ) ),
			'user'     => self::get_archive_title( Data\User::get_userdata( $args['uid'] ) ),
			default    => null,
		};

		return $title ?? '';
	}

	/**
	 * Returns the archive title for the given object or current query context.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Term|\WP_Post_Type|\WP_User|null $object Optional archive object. Default null.
	 * @return string The archive title string.
	 */
	public static function get_archive_title( mixed $object = null ): string {

		if ( $object && \is_wp_error( $object ) ) {
			return '';
		}

		return self::get_archive_title_list( $object )[0];
	}

	/**
	 * Returns the archive title list [title, prefix, title_without_prefix] for the given object.
	 *
	 * Applies the better_seo_generated_archive_title_items filter.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Term|\WP_Post_Type|\WP_User|null $object Optional archive object. Default null.
	 * @return array<int, string> The [title, prefix, title_without_prefix] array.
	 */
	public static function get_archive_title_list( mixed $object = null ): array {

		[ $title, $prefix ] = $object
			? self::get_archive_title_from_object( $object )
			: self::get_archive_title_from_query();

		$title_without_prefix = $title;

		if ( Title\Conditions::use_generated_archive_prefix( $object ) ) {
			if ( $prefix ) {
				$title = \sprintf(
					/* translators: 1: Title prefix. 2: Title. */
					\_x( '%1$s %2$s', 'archive title', 'better-seo' ),
					$prefix,
					$title,
				);
			}
		}

		/**
		 * Filters the Better SEO generated archive title items.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, string>                   $items               The [title, prefix, title_without_prefix] array.
		 * @param \WP_Term|\WP_Post_Type|\WP_User|null $object              The archive object.
		 * @param string                               $title               The full title with prefix.
		 * @param string                               $title_without_prefix The title without prefix.
		 * @param string                               $prefix              The title prefix.
		 */
		return \apply_filters(
			'better_seo_generated_archive_title_items',
			[
				$title,
				$prefix,
				$title_without_prefix,
			],
			$object,
			$title,
			$title_without_prefix,
			$prefix,
		);
	}

	/**
	 * Returns the [title, prefix] pair for the current archive query context.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> The [title, prefix] pair.
	 */
	public static function get_archive_title_from_query(): array {

		$title  = \__( 'Archives', 'better-seo' );
		$prefix = '';

		if ( Query::is_category() ) {
			$title  = self::get_term_title();
			$prefix = \_x( 'Category:', 'category archive title prefix', 'better-seo' );
		} elseif ( Query::is_tag() ) {
			$title  = self::get_term_title();
			$prefix = \_x( 'Tag:', 'tag archive title prefix', 'better-seo' );
		} elseif ( Query::is_author() ) {
			$title  = self::get_user_title();
			$prefix = \_x( 'Author:', 'author archive title prefix', 'better-seo' );
		} elseif ( \is_date() ) {
			if ( \is_year() ) {
				$title  = \get_the_date( \_x( 'Y', 'yearly archives date format', 'better-seo' ) );
				$prefix = \_x( 'Year:', 'date archive title prefix', 'better-seo' );
			} elseif ( \is_month() ) {
				$title  = \get_the_date( \_x( 'F Y', 'monthly archives date format', 'better-seo' ) );
				$prefix = \_x( 'Month:', 'date archive title prefix', 'better-seo' );
			} elseif ( \is_day() ) {
				$title  = \get_the_date( \_x( 'F j, Y', 'daily archives date format', 'better-seo' ) );
				$prefix = \_x( 'Day:', 'date archive title prefix', 'better-seo' );
			}
		} elseif ( \is_tax( 'post_format' ) ) {
			$title = match ( true ) {
				\is_tax( 'post_format', 'post-format-aside' )   => \_x( 'Asides',    'post format archive title', 'better-seo' ),
				\is_tax( 'post_format', 'post-format-gallery' ) => \_x( 'Galleries', 'post format archive title', 'better-seo' ),
				\is_tax( 'post_format', 'post-format-image' )   => \_x( 'Images',    'post format archive title', 'better-seo' ),
				\is_tax( 'post_format', 'post-format-video' )   => \_x( 'Videos',    'post format archive title', 'better-seo' ),
				\is_tax( 'post_format', 'post-format-quote' )   => \_x( 'Quotes',    'post format archive title', 'better-seo' ),
				\is_tax( 'post_format', 'post-format-link' )    => \_x( 'Links',     'post format archive title', 'better-seo' ),
				\is_tax( 'post_format', 'post-format-status' )  => \_x( 'Statuses',  'post format archive title', 'better-seo' ),
				\is_tax( 'post_format', 'post-format-audio' )   => \_x( 'Audio',     'post format archive title', 'better-seo' ),
				\is_tax( 'post_format', 'post-format-chat' )    => \_x( 'Chats',     'post format archive title', 'better-seo' ),
				default                                         => $title,
			};
		} elseif ( \is_post_type_archive() ) {
			$title  = self::get_post_type_archive_title();
			$prefix = \_x( 'Archives:', 'post type archive title prefix', 'better-seo' );
		} elseif ( Query::is_tax() ) {
			$term = \get_queried_object();

			if ( $term ) {
				$title  = self::get_term_title( $term );
				$prefix = \sprintf(
					/* translators: %s: Taxonomy singular name. */
					\_x( '%s:', 'taxonomy term archive title prefix', 'better-seo' ),
					Sanitize::metadata_content( Taxonomy::get_label( $term->taxonomy ?? '' ) ),
				);
			}
		}

		return [ $title, $prefix ];
	}

	/**
	 * Returns the [title, prefix] pair for the given archive object.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Term|\WP_Post_Type|\WP_User $object The archive object.
	 * @return array<int, string> The [title, prefix] pair.
	 */
	public static function get_archive_title_from_object( mixed $object ): array {

		$title  = \__( 'Archives', 'better-seo' );
		$prefix = '';

		if ( ! empty( $object->taxonomy ) ) {
			$title = self::get_term_title( $object );

			$prefix = match ( $object->taxonomy ) {
				'category' => \_x( 'Category:', 'category archive title prefix', 'better-seo' ),
				'post_tag' => \_x( 'Tag:', 'tag archive title prefix', 'better-seo' ),
				default    => \sprintf(
					/* translators: %s: Taxonomy singular name. */
					\_x( '%s:', 'taxonomy term archive title prefix', 'better-seo' ),
					Taxonomy::get_label( $object->taxonomy ),
				),
			};
		} elseif ( $object instanceof \WP_Post_Type ) {
			$title  = self::get_post_type_archive_title( $object->name );
			$prefix = \_x( 'Archives:', 'post type archive title prefix', 'better-seo' );
		} elseif ( $object instanceof \WP_User ) {
			$title  = self::get_user_title( $object->ID );
			$prefix = \_x( 'Author:', 'author archive title prefix', 'better-seo' );
		}

		return [ $title, $prefix ];
	}

	/**
	 * Returns the post title for the given post ID or current post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id Optional post ID. Default 0 (current post).
	 * @return string The post title string, or empty string if not available.
	 */
	public static function get_post_title( int $id = 0 ): string {

		$post = \get_post( $id ?: Query::get_the_real_id() );

		if ( isset( $post->post_title ) && \post_type_supports( $post->post_type, 'title' ) ) {
			$title = \apply_filters( 'single_post_title', $post->post_title, $post );
		}

		if ( isset( $title ) && \strlen( $title ) ) {
			return Sanitize::metadata_content( $title );
		}

		return '';
	}

	/**
	 * Returns the term title for the given term or current queried term.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Term|null $term Optional term object. Default null (current queried object).
	 * @return string The term title string, or empty string if not available.
	 */
	public static function get_term_title( ?\WP_Term $term = null ): string {

		$term ??= \get_queried_object();

		if ( ! isset( $term->name ) ) {
			return '';
		}

		$title = match ( $term->taxonomy ) {
			'category' => \apply_filters( 'single_cat_title', $term->name ),
			'post_tag' => \apply_filters( 'single_tag_title', $term->name ),
			default    => \apply_filters( 'single_term_title', $term->name ),
		};

		return \strlen( $title ) ? Sanitize::metadata_content( $title ) : '';
	}

	/**
	 * Returns the user display name for the given user ID or current user.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id Optional user ID. Default 0 (current user).
	 * @return string The sanitized user display name, or empty string if not found.
	 */
	public static function get_user_title( int $user_id = 0 ): string {
		return Sanitize::metadata_content(
			Data\User::get_userdata(
				$user_id ?: Query::get_the_real_id(),
				'display_name',
			)
			?? '',
		);
	}

	/**
	 * Returns the post type archive title for the given post type or current PTA.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type Optional post type slug. Default empty (current post type).
	 * @return string The post type archive title string, or empty string if not applicable.
	 */
	public static function get_post_type_archive_title( string $post_type = '' ): string {

		$post_type = $post_type ?: Query::get_current_post_type();

		if ( \is_array( $post_type ) ) {
			$post_type = reset( $post_type );
		}

		if ( ! \in_array( $post_type, Post_Type::get_public_pta(), true ) ) {
			return '';
		}

		$title = \apply_filters(
			'post_type_archive_title',
			Post_Type::get_label( $post_type, false ),
			$post_type,
		);

		return \strlen( $title ) ? Sanitize::metadata_content( $title ) : '';
	}

	/**
	 * Returns the "Untitled" fallback title string.
	 *
	 * @since 1.0.0
	 *
	 * @return string The untitled title string.
	 */
	public static function get_untitled_title(): string {
		return \__( 'Untitled', 'better-seo' );
	}

	/**
	 * Returns the search results page title for the current search query.
	 *
	 * @since 1.0.0
	 *
	 * @return string The sanitized search results title string.
	 */
	public static function get_search_query_title(): string {
		return Sanitize::metadata_content(
			\sprintf(
				/* translators: %s: search phrase */
				\__( 'Search Results for &#8220;%s&#8221;', 'better-seo' ),
				\get_search_query( true ),
			),
		);
	}

	/**
	 * Returns the 404 page title.
	 *
	 * Applies the better_seo_404_title filter.
	 *
	 * @since 1.0.0
	 *
	 * @return string The sanitized 404 title string.
	 */
	public static function get_404_title(): string {
		/**
		 * Filters the Better SEO 404 page title.
		 *
		 * @since 1.0.0
		 * @param string $title The 404 title.
		 */
		return Sanitize::metadata_content(
			(string) \apply_filters(
				'better_seo_404_title',
				\__( 'Page not found', 'better-seo' ),
			)
		);
	}

	/**
	 * Adds branding (site name/tagline with separator) to the given title.
	 *
	 * @since 1.0.0
	 *
	 * @param string                    $title The title to brand.
	 * @param array<string, mixed>|null $args  Optional generation args. Default null.
	 * @return string The branded title string.
	 */
	public static function add_branding( string $title, ?array $args = null ): string {

		if ( isset( $args ) ) {
			normalize_generation_args( $args );

			[ $addition, $seplocation ] = match ( get_query_type_from_args( $args ) ) {
				'single'   => Query::is_static_front_page( $args['id'] )
					? [ self::get_addition_for_front_page(), self::get_addition_location_for_front_page() ]
					: [ self::get_addition(), self::get_addition_location() ],
				'homeblog' => [ self::get_addition_for_front_page(), self::get_addition_location_for_front_page() ],
				default    => [ self::get_addition(), self::get_addition_location() ],
			};
		} else {
			if ( Query::is_real_front_page() ) {
				$addition    = self::get_addition_for_front_page();
				$seplocation = self::get_addition_location_for_front_page();
			} else {
				$addition    = self::get_addition();
				$seplocation = self::get_addition_location();
			}
		}

		$title    = trim( $title );
		$addition = trim( $addition );

		if ( \strlen( $addition ) && \strlen( $title ) ) {
			$sep = self::get_separator();

			if ( 'left' === $seplocation ) {
				return "{$addition} {$sep} {$title}";
			}

			return "{$title} {$sep} {$addition}";
		}

		return $title;
	}

	/**
	 * Adds pagination indicator to the given title if on a paginated page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title The title to paginate.
	 * @return string The title with pagination appended, or the original title.
	 */
	public static function add_pagination( string $title ): string {

		$page = max( Query::paged(), Query::page() );

		if ( $page >= 2 ) {
			$sep = self::get_separator();

			$paging = \sprintf(
				/* translators: %s: Page number. */
				\__( 'Page %s', 'better-seo' ),
				$page,
			);

			return \is_rtl() ? "{$paging} {$sep} {$title}" : "{$title} {$sep} {$paging}";
		}

		return $title;
	}

	/**
	 * Adds password protection or private status prefix to the given title.
	 *
	 * @since 1.0.0
	 *
	 * @param string                    $title The title to prefix.
	 * @param array<string, mixed>|null $args  Optional generation args. Default null.
	 * @return string The title with protection status prefix, or the original title.
	 */
	public static function add_protection_status( string $title, ?array $args = null ): string {

		if ( isset( $args ) ) {
			normalize_generation_args( $args );

			if ( 'single' !== get_query_type_from_args( $args ) ) {
				return $title;
			}
		} elseif ( ! Query::is_singular() ) {
			return $title;
		}

		$post = \get_post( $args['id'] ?? Query::get_the_real_id() );

		if ( ! empty( $post->post_password ) ) {
			return \sprintf(
				(string) \apply_filters(
					'protected_title_format',
					/* translators: %s: Protected post title. */
					\__( 'Protected: %s', 'better-seo' ),
					$post,
				),
				$title,
			);
		} elseif ( 'private' === ( $post->post_status ?? null ) ) {
			return \sprintf(
				(string) \apply_filters(
					'private_title_format',
					/* translators: %s: Private post title. */
					\__( 'Private: %s', 'better-seo' ),
					$post,
				),
				$title,
			);
		}

		return $title;
	}

	/**
	 * Returns the front page title (the public blog name).
	 *
	 * @since 1.0.0
	 *
	 * @return string The sanitized front page title string.
	 */
	public static function get_front_page_title(): string {
		return Sanitize::metadata_content( Data\Blog::get_public_blog_name() );
	}

	/**
	 * Returns the title addition (site name) for standard pages.
	 *
	 * @since 1.0.0
	 *
	 * @return string The sanitized site name string.
	 */
	public static function get_addition(): string {
		return Sanitize::metadata_content( Data\Blog::get_public_blog_name() );
	}

	/**
	 * Returns the title addition (tagline or description) for the front page, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return string The sanitized front page addition string.
	 */
	public static function get_addition_for_front_page(): string {
		return memo()
			?? memo( Sanitize::metadata_content(
				   coalesce_strlen( Data\Plugin::get_option( 'homepage_title_tagline' ) )
				?? Data\Blog::get_filtered_blog_description()
			) );
	}

	/**
	 * Returns the title addition location for standard pages.
	 *
	 * @since 1.0.0
	 *
	 * @return string The title location ('left' or 'right').
	 */
	public static function get_addition_location(): string {
		return Data\Plugin::get_option( 'title_location' );
	}

	/**
	 * Returns the title addition location for the front page.
	 *
	 * @since 1.0.0
	 *
	 * @return string The home title location ('left' or 'right').
	 */
	public static function get_addition_location_for_front_page(): string {
		return Data\Plugin::get_option( 'home_title_location' );
	}

	/**
	 * Returns the title separator character, memoized.
	 *
	 * Applies the better_seo_title_separator filter.
	 *
	 * @since 1.0.0
	 *
	 * @return string The title separator string.
	 */
	public static function get_separator(): string {
		/**
		 * Filters the Better SEO title separator.
		 *
		 * @since 1.0.0
		 * @param string $separator The title separator character.
		 */
		return memo() ?? memo(
			(string) \apply_filters(
				'better_seo_title_separator',
				(
					Title\Utils::get_separator_list()[ Data\Plugin::get_option( 'title_separator' ) ]
					?? '&#x2d;'
				),
			),
		);
	}
}