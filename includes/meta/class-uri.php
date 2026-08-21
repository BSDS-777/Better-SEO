<?php
/**
 * Better SEO - Meta URI
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
	umemo,
	get_query_type_from_args,
	normalize_generation_args,
};

use Better_SEO\{
	Data,
	Helper\Query,
	Meta,
};

/**
 * Class Better_SEO\Meta\URI
 *
 * Provides URL generation for Better SEO canonical, redirect, pagination,
 * shortlink, and archive URLs across all query contexts.
 *
 * @since 1.0.0
 */
class URI {

	/**
	 * Returns the indexable canonical URL for the current page.
	 *
	 * Returns the custom canonical URL if set. Returns empty string if the
	 * current page has a noindex robots directive. Otherwise returns the generated URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string The indexable canonical URL, or empty string if noindexed.
	 */
	public static function get_indexable_canonical_url(): string {

		$custom_url = self::get_custom_canonical_url();

		if ( $custom_url ) {
			return $custom_url;
		}

		if ( str_contains( Robots::get_meta(), 'noindex' ) ) {
			return '';
		}

		return self::get_generated_url();
	}

	/**
	 * Returns the canonical URL (custom or generated) for the given args or current query.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The canonical URL string.
	 */
	public static function get_canonical_url( ?array $args = null ): string {
		return self::get_custom_canonical_url( $args )
			?: self::get_generated_url( $args );
	}

	/**
	 * Returns the custom canonical URL for the given args or current query context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The custom canonical URL string.
	 */
	public static function get_custom_canonical_url( ?array $args = null ): string {
		return isset( $args )
			? self::get_custom_canonical_url_from_args( $args )
			: self::get_custom_canonical_url_from_query();
	}

	/**
	 * Returns the generated URL for the given args or current query context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The generated URL string.
	 */
	public static function get_generated_url( ?array $args = null ): string {
		return isset( $args )
			? self::get_generated_url_from_args( $args )
			: self::get_generated_url_from_query();
	}

	/**
	 * Returns the custom canonical URL from the current query context.
	 *
	 * @since 1.0.0
	 *
	 * @return string The custom canonical URL string, or empty string if not set.
	 */
	public static function get_custom_canonical_url_from_query(): string {

		if ( Query::is_real_front_page() ) {
			if ( Query::is_static_front_page() ) {
				$url = Data\Plugin::get_option( 'homepage_canonical' )
					?: Data\Plugin\Post::get_meta_item( '_genesis_canonical_uri' );
			} else {
				$url = Data\Plugin::get_option( 'homepage_canonical' );
			}
		} elseif ( Query::is_singular() ) {
			$url = Data\Plugin\Post::get_meta_item( '_genesis_canonical_uri' );
		} elseif ( Query::is_editable_term() ) {
			$url = Data\Plugin\Term::get_meta_item( 'canonical' );
		} elseif ( \is_post_type_archive() ) {
			$url = Data\Plugin\PTA::get_meta_item( 'canonical' );
		}

		if ( empty( $url ) ) {
			return '';
		}

		if ( URI\Utils::url_matches_blog_domain( $url ) ) {
			$url = URI\Utils::set_preferred_url_scheme( $url );
		}

		return \sanitize_url( $url, [ 'https', 'http' ] );
	}

	/**
	 * Returns the custom canonical URL from the given generation args.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args The generation args.
	 * @return string The custom canonical URL string, or empty string if not set.
	 */
	public static function get_custom_canonical_url_from_args( array $args ): string {

		normalize_generation_args( $args );

		$url = match ( get_query_type_from_args( $args ) ) {
			'single'   => Query::is_static_front_page( $args['id'] )
				? ( Data\Plugin::get_option( 'homepage_canonical' )
					?: Data\Plugin\Post::get_meta_item( '_genesis_canonical_uri', $args['id'] ) )
				: Data\Plugin\Post::get_meta_item( '_genesis_canonical_uri', $args['id'] ),
			'term'     => Data\Plugin\Term::get_meta_item( 'canonical', $args['id'] ),
			'homeblog' => Data\Plugin::get_option( 'homepage_canonical' ),
			'pta'      => Data\Plugin\PTA::get_meta_item( 'canonical', $args['pta'] ),
			default    => null,
		};

		if ( empty( $url ) ) {
			return '';
		}

		if ( URI\Utils::url_matches_blog_domain( $url ) ) {
			$url = URI\Utils::set_preferred_url_scheme( $url );
		}

		return \sanitize_url( $url, [ 'https', 'http' ] );
	}

	/**
	 * Returns the generated URL from the current query context.
	 *
	 * @since 1.0.0
	 *
	 * @return string The generated URL string, or empty string if not determinable.
	 */
	public static function get_generated_url_from_query(): string {

		if ( Query::is_real_front_page() ) {
			$url = self::get_front_page_url();
		} elseif ( Query::is_singular() ) {
			$url = self::get_singular_url();
		} elseif ( Query::is_archive() ) {
			if ( Query::is_editable_term() ) {
				$url = self::get_term_url();
			} elseif ( \is_post_type_archive() ) {
				$url = self::get_pta_url();
			} elseif ( Query::is_author() ) {
				$url = self::get_author_url();
			} elseif ( \is_date() ) {
				$url = self::get_date_url();
			}
		} elseif ( Query::is_search() ) {
			$url = self::get_search_url();
		}

		return $url ?? '' ?: '';
	}

	/**
	 * Returns the generated URL from the given generation args.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args The generation args.
	 * @return string The generated URL string, or empty string if not determinable.
	 */
	public static function get_generated_url_from_args( array $args ): string {

		normalize_generation_args( $args );

		$url = match ( get_query_type_from_args( $args ) ) {
			'single'   => Query::is_static_front_page( $args['id'] )
				? self::get_bare_front_page_url()
				: self::get_bare_singular_url( $args['id'] ),
			'term'     => self::get_bare_term_url( $args['id'], $args['tax'] ),
			'homeblog' => self::get_bare_front_page_url(),
			'pta'      => self::get_bare_pta_url( $args['pta'] ),
			'user'     => self::get_bare_author_url( $args['uid'] ),
			default    => null,
		};

		return $url ?: '';
	}

	/**
	 * Returns the front page URL with pagination applied if needed.
	 *
	 * @since 1.0.0
	 *
	 * @return string The front page URL string.
	 */
	public static function get_front_page_url(): string {

		$url = URI\Utils::slash_front_page_url( Data\Blog::get_front_page_url() );

		if ( empty( $url ) ) {
			return '';
		}

		$page = max( Query::paged(), Query::page() );

		if ( $page > 1 ) {
			$url = URI\Utils::add_pagination_to_url( $url, $page, true );
		}

		return \sanitize_url(
			URI\Utils::set_preferred_url_scheme( $url ),
			[ 'https', 'http' ],
		);
	}

	/**
	 * Returns the bare front page URL (no pagination), memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return string The bare front page URL string.
	 */
	public static function get_bare_front_page_url(): string {
		return umemo( __METHOD__ ) ?? umemo(
			__METHOD__,
			\sanitize_url(
				URI\Utils::slash_front_page_url( URI\Utils::set_preferred_url_scheme(
					Data\Blog::get_front_page_url(),
				) ),
				[ 'https', 'http' ],
			),
		);
	}

	/**
	 * Returns the singular post URL with pagination applied if needed.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $post_id Optional post ID. Default null (current post).
	 * @return string The singular URL string.
	 */
	public static function get_singular_url( ?int $post_id = null ): string {

		if ( isset( $post_id ) ) {
			return self::get_bare_singular_url( $post_id );
		}

		$url = \get_permalink( Query::get_the_real_id() );

		if ( empty( $url ) ) {
			return '';
		}

		if ( Query::is_singular_archive() ) {
			$paged = Query::paged();

			if ( $paged > 1 ) {
				$url = URI\Utils::add_pagination_to_url( $url, $paged, true );
			}
		} else {
			$page = Query::page();

			if ( $page > 1 ) {
				$url = URI\Utils::add_pagination_to_url( $url, $page, false );
			}
		}

		return \sanitize_url(
			URI\Utils::set_preferred_url_scheme( $url ),
			[ 'https', 'http' ],
		);
	}

	/**
	 * Returns the bare singular post URL (no pagination).
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $post_id Optional post ID. Default null (current post).
	 * @return string The bare singular URL string.
	 */
	public static function get_bare_singular_url( ?int $post_id = null ): string {

		$url = \get_permalink( $post_id );

		if ( empty( $url ) ) {
			return '';
		}

		return \sanitize_url(
			URI\Utils::set_preferred_url_scheme( $url ),
			[ 'https', 'http' ],
		);
	}

	/**
	 * Returns the term archive URL with pagination applied if needed.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $term_id  Optional term ID. Default null (current term).
	 * @param string   $taxonomy Optional taxonomy slug. Default empty.
	 * @return string The term URL string.
	 */
	public static function get_term_url( ?int $term_id = null, string $taxonomy = '' ): string {

		if ( isset( $term_id ) ) {
			return self::get_bare_term_url( $term_id, $taxonomy );
		}

		$url = \get_term_link( Query::get_the_real_id(), $taxonomy );

		if ( empty( $url ) || ! \is_string( $url ) ) {
			return '';
		}

		return \sanitize_url(
			URI\Utils::set_preferred_url_scheme( URI\Utils::add_pagination_to_url(
				$url,
				Query::paged(),
				true,
			) ),
			[ 'https', 'http' ],
		);
	}

	/**
	 * Returns the bare term archive URL (no pagination).
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $term_id  Optional term ID. Default null (current term).
	 * @param string   $taxonomy Optional taxonomy slug. Default empty.
	 * @return string The bare term URL string.
	 */
	public static function get_bare_term_url( ?int $term_id = null, string $taxonomy = '' ): string {

		if ( empty( $term_id ) ) {
			$term_id  = Query::get_the_real_id();
			$taxonomy = Query::get_current_taxonomy();
		}

		$url = \get_term_link( $term_id, $taxonomy );

		if ( empty( $url ) || ! \is_string( $url ) ) {
			return '';
		}

		return \sanitize_url(
			URI\Utils::set_preferred_url_scheme( $url ),
			[ 'https', 'http' ],
		);
	}

	/**
	 * Returns the post type archive URL with pagination applied if needed.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $post_type Optional post type slug. Default null (current post type).
	 * @return string The PTA URL string.
	 */
	public static function get_pta_url( ?string $post_type = null ): string {

		if ( isset( $post_type ) ) {
			return self::get_bare_pta_url( $post_type );
		}

		$url = \get_post_type_archive_link( $post_type ?? Query::get_current_post_type() );

		if ( empty( $url ) ) {
			return '';
		}

		return \sanitize_url(
			URI\Utils::set_preferred_url_scheme( URI\Utils::add_pagination_to_url(
				$url,
				Query::paged(),
				true,
			) ),
			[ 'https', 'http' ],
		);
	}

	/**
	 * Returns the bare post type archive URL (no pagination).
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $post_type Optional post type slug. Default null (current post type).
	 * @return string The bare PTA URL string.
	 */
	public static function get_bare_pta_url( ?string $post_type = null ): string {

		$url = \get_post_type_archive_link( $post_type ?? Query::get_current_post_type() );

		if ( empty( $url ) ) {
			return '';
		}

		return \sanitize_url(
			URI\Utils::set_preferred_url_scheme( $url ),
			[ 'https', 'http' ],
		);
	}

	/**
	 * Returns the author archive URL with pagination applied if needed.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $id Optional author user ID. Default null (current author).
	 * @return string The author URL string.
	 */
	public static function get_author_url( ?int $id = null ): string {

		if ( isset( $id ) ) {
			return self::get_bare_author_url( $id );
		}

		$url = \get_author_posts_url( Query::get_the_real_id() );

		if ( empty( $url ) ) {
			return '';
		}

		return \sanitize_url(
			URI\Utils::set_preferred_url_scheme( URI\Utils::add_pagination_to_url(
				$url,
				Query::paged(),
				true,
			) ),
			[ 'https', 'http' ],
		);
	}

	/**
	 * Returns the bare author archive URL (no pagination).
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $id Optional author user ID. Default null (current author).
	 * @return string The bare author URL string.
	 */
	public static function get_bare_author_url( ?int $id = null ): string {

		$url = \get_author_posts_url( $id ?? Query::get_the_real_id() );

		if ( empty( $url ) ) {
			return '';
		}

		return \sanitize_url(
			URI\Utils::set_preferred_url_scheme( $url ),
			[ 'https', 'http' ],
		);
	}

	/**
	 * Returns the date archive URL with pagination applied if needed.
	 *
	 * @since 1.0.0
	 *
	 * @param int|null $year  Optional year. Default null (current query year).
	 * @param int|null $month Optional month. Default null.
	 * @param int|null $day   Optional day. Default null.
	 * @return string The date archive URL string.
	 */
	public static function get_date_url( ?int $year = null, ?int $month = null, ?int $day = null ): string {

		if ( isset( $year ) ) {
			return self::get_bare_date_url( $year, $month, $day );
		}

		$year  = \get_query_var( 'year' );
		$month = \get_query_var( 'monthnum' );
		$day   = \get_query_var( 'day' );

		if ( $day ) {
			$url = \get_day_link( $year, $month, $day );
		} elseif ( $month ) {
			$url = \get_month_link( $year, $month );
		} else {
			$url = \get_year_link( $year );
		}

		if ( empty( $url ) ) {
			return '';
		}

		return \sanitize_url(
			URI\Utils::set_preferred_url_scheme( URI\Utils::add_pagination_to_url(
				$url,
				Query::paged(),
				true,
			) ),
			[ 'https', 'http' ],
		);
	}

	/**
	 * Returns the bare date archive URL (no pagination).
	 *
	 * @since 1.0.0
	 *
	 * @param int      $year  The year.
	 * @param int|null $month Optional month. Default null.
	 * @param int|null $day   Optional day. Default null.
	 * @return string The bare date archive URL string.
	 */
	public static function get_bare_date_url( int $year, ?int $month = null, ?int $day = null ): string {

		if ( $day ) {
			$url = \get_day_link( $year, $month, $day );
		} elseif ( $month ) {
			$url = \get_month_link( $year, $month );
		} else {
			$url = \get_year_link( $year );
		}

		if ( empty( $url ) ) {
			return '';
		}

		return \sanitize_url(
			URI\Utils::set_preferred_url_scheme( $url ),
			[ 'https', 'http' ],
		);
	}

	/**
	 * Returns the search results URL with pagination applied if needed.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $search_query Optional search query. Default null (current search).
	 * @return string The search URL string.
	 */
	public static function get_search_url( ?string $search_query = null ): string {

		if ( isset( $search_query ) ) {
			return self::get_bare_search_url( $search_query );
		}

		$url = \get_search_link();

		if ( empty( $url ) ) {
			return '';
		}

		return \sanitize_url(
			URI\Utils::set_preferred_url_scheme( URI\Utils::add_pagination_to_url(
				$url,
				Query::paged(),
				true,
			) ),
			[ 'https', 'http' ],
		);
	}

	/**
	 * Returns the bare search results URL for the given query.
	 *
	 * @since 1.0.0
	 *
	 * @param string $search_query The search query string.
	 * @return string The bare search URL string.
	 */
	public static function get_bare_search_url( string $search_query ): string {

		$url = \get_search_link( $search_query );

		if ( empty( $url ) ) {
			return '';
		}

		return \sanitize_url(
			URI\Utils::set_preferred_url_scheme( $url ),
			[ 'https', 'http' ],
		);
	}

	/**
	 * Returns the [prev, next] pagination URL pair for the current query.
	 *
	 * Returns ['', ''] if pagination is disabled for the current query type.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> The [prev, next] URL pair.
	 */
	public static function get_paged_urls(): array {

		if ( Query::is_real_front_page() ) {
			$get = Data\Plugin::get_option( 'prev_next_frontpage' );
		} elseif ( Query::is_singular() || Query::is_singular_archive() ) {
			$get = Data\Plugin::get_option( 'prev_next_posts' );
		} elseif ( Query::is_archive() ) {
			$get = Data\Plugin::get_option( 'prev_next_archives' );
		} else {
			$get = false;
		}

		return $get ? self::get_generated_paged_urls() : [ '', '' ];
	}

	/**
	 * Returns the generated [prev, next] pagination URL pair for the current query.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> The [prev, next] URL pair.
	 */
	public static function get_generated_paged_urls(): array {

		$page     = max( Query::paged(), Query::page() );
		$numpages = Query::numpages();

		if ( ( $page + 1 ) <= $numpages ) {
			$url = URI\Utils::remove_pagination_from_url( self::get_generated_url() );

			if ( $url ) {
				$next = \sanitize_url(
					URI\Utils::add_pagination_to_url( $url, $page + 1 ),
					[ 'https', 'http' ],
				);
			}
		}

		if ( $page > 1 ) {
			$url ??= URI\Utils::remove_pagination_from_url( self::get_generated_url() );

			if ( $url ) {
				$prev = \sanitize_url(
					URI\Utils::add_pagination_to_url( $url, $page - 1 ),
					[ 'https', 'http' ],
				);
			}
		}

		return [
			$prev ?? '',
			$next ?? '',
		];
	}

	/**
	 * Returns the redirect URL for the given args or current query context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The redirect URL string, or empty string if not set.
	 */
	public static function get_redirect_url( ?array $args = null ): string {

		if ( isset( $args ) ) {
			normalize_generation_args( $args );

			$url = match ( get_query_type_from_args( $args ) ) {
				'single'   => Query::is_static_front_page( $args['id'] )
					? ( Data\Plugin::get_option( 'homepage_redirect' )
						?: Data\Plugin\Post::get_meta_item( 'redirect', $args['id'] ) )
					: Data\Plugin\Post::get_meta_item( 'redirect', $args['id'] ),
				'term'     => Data\Plugin\Term::get_meta_item( 'redirect', $args['id'] ),
				'homeblog' => Data\Plugin::get_option( 'homepage_redirect' ),
				'pta'      => Data\Plugin\PTA::get_meta_item( 'redirect', $args['pta'] ),
				default    => null,
			};
		} else {
			if ( Query::is_real_front_page() ) {
				if ( Query::is_static_front_page() ) {
					$url = Data\Plugin::get_option( 'homepage_redirect' )
						?: Data\Plugin\Post::get_meta_item( 'redirect' );
				} else {
					$url = Data\Plugin::get_option( 'homepage_redirect' );
				}
			} elseif ( Query::is_singular() ) {
				$url = Data\Plugin\Post::get_meta_item( 'redirect' );
			} elseif ( Query::is_editable_term() ) {
				$url = Data\Plugin\Term::get_meta_item( 'redirect' );
			} elseif ( \is_post_type_archive() ) {
				$url = Data\Plugin\PTA::get_meta_item( 'redirect' );
			}
		}

		if ( empty( $url ) ) {
			return '';
		}

		return \sanitize_url( $url, [ 'https', 'http' ] );
	}

	/**
	 * Returns the shortlink URL for the current page if enabled.
	 *
	 * Returns empty string if the shortlink_tag option is disabled or on the front page.
	 *
	 * @since 1.0.0
	 *
	 * @return string The shortlink URL string, or empty string if not applicable.
	 */
	public static function get_shortlink_url(): string {

		if (
			! Data\Plugin::get_option( 'shortlink_tag' )
			|| Query::is_real_front_page()
		) {
			return '';
		}

		return self::get_generated_shortlink_url();
	}

	/**
	 * Returns the generated shortlink URL for the current query context.
	 *
	 * Builds a query-string-based shortlink URL from the current query state.
	 *
	 * @since 1.0.0
	 *
	 * @return string The generated shortlink URL string, or empty string if not determinable.
	 */
	public static function get_generated_shortlink_url(): string {

		if ( Query::is_singular() ) {
			$query = [ 'p' => Query::get_the_real_id() ];
		} elseif ( Query::is_archive() ) {
			if ( Query::is_category() ) {
				$query = [ 'cat' => Query::get_the_real_id() ];
			} elseif ( Query::is_tag() ) {
				$slug = \get_queried_object()->slug ?? '';

				if ( $slug ) {
					$query = [ 'tag' => $slug ];
				}
			} elseif ( Query::is_tag() || Query::is_tax() ) {
				// Generate shortlink for object type and slug.
				$object = \get_queried_object();

				$tax  = $object->taxonomy ?? '';
				$slug = $object->slug ?? '';

				if ( $tax && $slug ) {
					$query = [ $tax => $slug ];
				}
			} elseif ( \is_date() && isset( $GLOBALS['wp_query']->query ) ) {
				$_query = $GLOBALS['wp_query']->query;
				$_date  = [
					'y' => $_query['year']     ?? '',
					'm' => $_query['monthnum'] ?? '',
					'd' => $_query['day']      ?? '',
				];

				$query = [ 'm' => implode( '', $_date ) ];
			} elseif ( Query::is_author() ) {
				$query = [ 'author' => Query::get_the_real_id() ];
			}
		} elseif ( Query::is_search() ) {
			$query = [ 's' => \get_search_query( false ) ];
		}

		if ( empty( $query ) ) {
			return '';
		}

		$page  = Query::page();
		$paged = Query::paged();

		if ( $page > 1 ) {
			$query += [ 'page' => $page ];
		} elseif ( $paged > 1 ) {
			$query += [ 'paged' => $paged ];
		}

		$query       = http_build_query( $query );
		$extra_query = parse_url( self::get_generated_url( null ), \PHP_URL_QUERY );

		if ( $extra_query ) {
			$query .= "&{$extra_query}";
		}

		return \sanitize_url(
			URI\Utils::append_query_to_url(
				self::get_bare_front_page_url(),
				$query,
			),
			[ 'https', 'http' ],
		);
	}
}