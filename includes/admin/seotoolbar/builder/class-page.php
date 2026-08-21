<?php
/**
 * Better SEO - Admin SEO Bar Builder Page
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Admin\SEOBar\Builder
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

namespace Better_SEO\Admin\SEOBar\Builder;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use const Better_SEO\ROBOTS_ASSERT;

use Better_SEO\{
	Data,
	Data\Filter\Sanitize,
	Meta,
	RobotsTXT,
	Admin\SEOBar\Builder,
};
use Better_SEO\Helper\{
	Guidelines,
	Format\Strings,
	Migrate,
	Query,
};

/**
 * Class Better_SEO\Admin\SEOBar\Builder\Page
 *
 * SEO Bar builder implementation for posts and pages.
 * Runs title, description, indexing, following, archiving, and redirect tests.
 *
 * @since 1.0.0
 */
final class Page extends Main {

	/**
	 * Registered test names for the page builder.
	 *
	 * @since 1.0.0
	 * @var   array<int, string>
	 */
	protected static array $tests = [
		'title',
		'description',
		'indexing',
		'following',
		'archiving',
		'redirect',
	];

	/**
	 * Primes the shared static cache with global robots and guideline data.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function prime_cache(): void {
		// phpcs:disable PEAR.Functions.FunctionCallSignature.Indent -- False negative.
		static::get_cache( 'general/i18n/textsizeguidelines' )
			or static::set_cache(
				'general/i18n/textsizeguidelines',
				Guidelines::get_text_size_guidelines_i18n()
			);

		static::get_cache( 'general/detect/robotsglobal' )
			or static::set_cache(
				'general/detect/robotsglobal',
				[
					'hasrobotstxt' => RobotsTXT\Utils::has_root_robots_txt(),
					'blogpublic'   => Data\Blog::is_public(),
					'site'         => [
						'noindex'   => Data\Plugin::get_option( 'site_noindex' ),
						'nofollow'  => Data\Plugin::get_option( 'site_nofollow' ),
						'noarchive' => Data\Plugin::get_option( 'site_noarchive' ),
					],
					'posttype'     => [
						'noindex'   => Data\Plugin::get_option( Data\Plugin\Helper::get_robots_option_index( 'post_type', 'noindex' ) ),
						'nofollow'  => Data\Plugin::get_option( Data\Plugin\Helper::get_robots_option_index( 'post_type', 'nofollow' ) ),
						'noarchive' => Data\Plugin::get_option( Data\Plugin\Helper::get_robots_option_index( 'post_type', 'noarchive' ) ),
					],
				],
			);
		// phpcs:enable PEAR.Functions.FunctionCallSignature.Indent -- False negative.
	}

	/**
	 * Primes the per-instance query cache for the current post/page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	protected function prime_query_cache(): void {
		$this->query_cache = [
			'post'   => \get_post( static::$query['id'] ),
			'meta'   => Data\Plugin\Post::get_meta( static::$query['id'] ),
			'states' => [
				'ishome'       => Query::is_real_front_page_by_id( static::$query['id'] ),
				'locale'       => \get_locale(),
				'isprotected'  => Data\Post::is_protected( static::$query['id'] ),
				'isdraft'      => Data\Post::is_draft( static::$query['id'] ),
				'robotsmeta'   => array_merge(
					[
						'noindex'   => false,
						'nofollow'  => false,
						'noarchive' => false,
					],
					Meta\Robots::get_generated_meta(
						[ 'id' => static::$query['id'] ],
						[ 'noindex', 'nofollow', 'noarchive' ],
						ROBOTS_ASSERT,
					),
				),
				'robotsassert' => Meta\Robots::get_collected_meta_assertions(),
			],
		];
	}

	/**
	 * Determines whether a blocking redirect exists for the current post.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if a redirect meta value is set, false otherwise.
	 */
	protected function has_blocking_redirect(): bool {
		return ! empty( $this->query_cache['meta']['redirect'] );
	}

	/**
	 * Runs the title SEO Bar test for the current post/page.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The SEO Bar item definition.
	 */
	protected function test_title(): array {

		$cache = static::get_cache( 'page/title/defaults' ) ?: static::set_cache(
			'page/title/defaults',
			[
				'params'   => [
					'untitled'        => Meta\Title::get_untitled_title(),
					'blogname_quoted' => preg_quote(
						Sanitize::normalize_metadata_content_for_strcmp( Data\Blog::get_public_blog_name() ),
						'/',
					),
					/* translators: 1 = An assessment, 2 = Disclaimer, e.g. "take it with a grain of salt" */
					'disclaim'        => \__( '%1$s (%2$s)', 'better-seo' ),
					'estimated'       => \__( 'Estimated from the number of characters found. The pixel counter asserts the true length.', 'better-seo' ),
				],
				'assess'   => [
					'empty'      => \__( 'No title could be fetched.', 'better-seo' ),
					'untitled'   => \sprintf(
						/* translators: %s = "Untitled" */
						\__( 'No title could be fetched, "%s" is used instead.', 'better-seo' ),
						Meta\Title::get_untitled_title(),
					),
					'protected'  => \__( 'A page protection state is added which increases the length.', 'better-seo' ),
					'branding'   => [
						'not'       => \__( "It's not branded. Search engines may ignore your title. Consider adding back the site title.", 'better-seo' ),
						'manual'    => \__( "It's manually branded.", 'better-seo' ),
						'automatic' => \__( "It's automatically branded.", 'better-seo' ),
					],
					'duplicated' => \__( 'The site title is found multiple times.', 'better-seo' ),
					'syntax'     => \__( "Markup syntax was found that isn't transformed. Consider rewriting the custom title.", 'better-seo' ),
				],
				'reason'   => [
					'incomplete' => \__( 'Incomplete.', 'better-seo' ),
					'duplicated' => \__( 'The branding is repeated.', 'better-seo' ),
					'notbranded' => \__( 'Not branded.', 'better-seo' ),
					'syntax'     => \__( 'Found markup syntax.', 'better-seo' ),
				],
				'defaults' => [
					'generated' => [
						'symbol' => \_x( 'TG', 'Title Generated', 'better-seo' ),
						'title'  => \__( 'Title, generated', 'better-seo' ),
						'status' => Builder::STATE_GOOD,
						'reason' => \__( 'Automatically generated.', 'better-seo' ),
						'assess' => [
							'base' => \__( "It's built from the page title.", 'better-seo' ),
						],
					],
					'custom'    => [
						'symbol' => \_x( 'T', 'Title', 'better-seo' ),
						'title'  => \__( 'Title', 'better-seo' ),
						'status' => Builder::STATE_GOOD,
						'reason' => \__( 'Obtained from page SEO meta input.', 'better-seo' ),
						'assess' => [
							'base' => \__( "It's built from page SEO meta input.", 'better-seo' ),
						],
					],
				],
			],
		);

		$generator_args = [ 'id' => static::$query['id'] ];
		$title_part     = Meta\Title::get_bare_custom_title( $generator_args );

		if ( \strlen( $title_part ) ) {
			$item = $cache['defaults']['custom'];

			if ( $this->query_cache['states']['ishome'] ) {
				// Don't use cache here — only one page can have this state.
				if ( Data\Plugin::get_option( 'homepage_title' ) ) {
					$item['assess']['homepage'] = \__( 'The title inputted at the SEO Settings screen is used.', 'better-seo' );
				} else {
					$item['assess']['homepage'] = \__( 'The title inputted at the Edit Page screen is used.', 'better-seo' );
				}
			}

			if ( Migrate::text_has_unprocessed_syntax( $title_part ) ) {
				$item['status']           = Builder::STATE_BAD;
				$item['reason']           = $cache['reason']['syntax'];
				$item['assess']['syntax'] = $cache['assess']['syntax'];

				return $item;
			}
		} else {
			$item = $cache['defaults']['generated'];

			if ( $this->query_cache['states']['ishome'] ) {
				// Don't use cache here — only one page can have this state.
				$item['assess']['base'] = \__( "It's built using the site title.", 'better-seo' );
			}

			$title_part = Meta\Title::get_bare_generated_title( $generator_args );
		}

		if ( ! \strlen( $title_part ) ) {
			$item['status']          = Builder::STATE_BAD;
			$item['reason']          = $cache['reason']['incomplete'];
			$item['assess']['empty'] = $cache['assess']['empty'];

			return $item;
		} elseif ( $title_part === $cache['params']['untitled'] ) {
			$item['status']             = Builder::STATE_BAD;
			$item['reason']             = $cache['reason']['incomplete'];
			$item['assess']['untitled'] = $cache['assess']['untitled'];

			return $item;
		}

		$title = $title_part;

		// Don't use cache — this can be filtered.
		if ( Meta\Title\Conditions::use_protection_status( $generator_args ) ) {
			$_title_before = $title;
			$title         = Meta\Title::add_protection_status( $title, $generator_args );

			if ( $title !== $_title_before ) {
				$item['assess']['protected'] = $cache['assess']['protected'];
			}
		}

		if ( Meta\Title\Conditions::use_branding( $generator_args ) ) {
			$_title_before = $title;
			$title         = Meta\Title::add_branding( $title, $generator_args );

			if ( $title === $_title_before ) {
				$item['assess']['branding'] = $cache['assess']['branding']['manual'];
			} else {
				$item['assess']['branding'] = $cache['assess']['branding']['automatic'];
			}
		} else {
			if ( $this->query_cache['states']['ishome'] ) {
				$item['assess']['branding'] = $cache['assess']['branding']['automatic'];
			} else {
				$item['assess']['branding'] = $cache['assess']['branding']['manual'];
			}
		}

		$strcmp_title = Sanitize::normalize_metadata_content_for_strcmp( $title );

		$brand_count = \strlen( $cache['params']['blogname_quoted'] )
			? preg_match_all(
				"/{$cache['params']['blogname_quoted']}/ui",
				$strcmp_title,
				$matches,
			)
			: 0;

		if ( ! $brand_count ) {
			$item['status']             = Builder::STATE_UNKNOWN;
			$item['reason']             = $cache['reason']['notbranded'];
			$item['assess']['branding'] = $cache['assess']['branding']['not'];
		} elseif ( $brand_count > 1 ) {
			$item['status']               = Builder::STATE_BAD;
			$item['reason']               = $cache['reason']['duplicated'];
			$item['assess']['duplicated'] = $cache['assess']['duplicated'];

			return $item;
		}

		$title_len = mb_strlen( $strcmp_title );

		$guidelines      = Guidelines::get_text_size_guidelines(
			$this->query_cache['states']['locale']
		)['title']['search']['chars'];
		$guidelines_i18n = static::get_cache( 'general/i18n/textsizeguidelines' );

		if ( $title_len < $guidelines['lower'] ) {
			$item['status'] = Builder::STATE_BAD;
			$item['reason'] = $guidelines_i18n['shortdot']['farTooShort'];
			$length_i18n    = $guidelines_i18n['long']['farTooShort'];
		} elseif ( $title_len < $guidelines['goodLower'] ) {
			$item['status'] = Builder::STATE_OKAY;
			$item['reason'] = $guidelines_i18n['shortdot']['tooShort'];
			$length_i18n    = $guidelines_i18n['long']['tooShort'];
		} elseif ( $title_len > $guidelines['upper'] ) {
			$item['status'] = Builder::STATE_BAD;
			$item['reason'] = $guidelines_i18n['shortdot']['farTooLong'];
			$length_i18n    = $guidelines_i18n['long']['farTooLong'];
		} elseif ( $title_len > $guidelines['goodUpper'] ) {
			$item['status'] = Builder::STATE_OKAY;
			$item['reason'] = $guidelines_i18n['shortdot']['tooLong'];
			$length_i18n    = $guidelines_i18n['long']['tooLong'];
		} else {
			$length_i18n = $guidelines_i18n['long']['good'];
		}

		$item['assess']['length'] = \sprintf(
			$cache['params']['disclaim'],
			$length_i18n,
			$cache['params']['estimated'],
		);

		return $item;
	}

	/**
	 * Runs the description SEO Bar test for the current post/page.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The SEO Bar item definition.
	 */
	protected function test_description(): array {

		$cache = static::get_cache( 'page/description/defaults' ) ?: static::set_cache(
			'page/description/defaults',
			[
				'params'   => [
					/* translators: 1 = An assessment, 2 = Disclaimer, e.g. "take it with a grain of salt" */
					'disclaim'   => \__( '%1$s (%2$s)', 'better-seo' ),
					'estimated'  => \__( 'Estimated from the number of characters found. The pixel counter asserts the true length.', 'better-seo' ),
					/**
					 * Filters the minimum word length to flag as a duplicate in the description.
					 *
					 * @since 1.0.0
					 * @param int $short_word_length The minimum string length of words to find as duplicates.
					 */
					'dupe_short' => (int) \apply_filters( 'better_seo_bother_me_desc_length', 3 ),
				],
				'assess'   => [
					'empty'     => \__( 'There is no usable content, so no description could be generated.', 'better-seo' ),
					'builder'   => \__( 'A page builder is used that renders content dynamically, so no description can be generated for performance and privacy reasons. Consider providing a custom description.', 'better-seo' ),
					'protected' => \__( 'The page is protected, so no description is generated.', 'better-seo' ),
					'excerpt'   => \__( "It's built from the page excerpt field.", 'better-seo' ),
					/* translators: %s = list of repeated words */
					'dupes'     => \__( 'Found repeated words: %s', 'better-seo' ),
					'syntax'    => \__( "Markup syntax was found that isn't transformed. Consider rewriting the custom description.", 'better-seo' ),
				],
				'reason'   => [
					'empty'         => \__( 'Empty.', 'better-seo' ),
					'founddupe'     => \__( 'Found repeated words.', 'better-seo' ),
					'foundmanydupe' => \__( 'Found too many repeated words.', 'better-seo' ),
					'syntax'        => \__( 'Found markup syntax.', 'better-seo' ),
				],
				'defaults' => [
					'generated'   => [
						'symbol' => \_x( 'DG', 'Description Generated', 'better-seo' ),
						'title'  => \__( 'Description, generated', 'better-seo' ),
						'status' => Builder::STATE_GOOD,
						'reason' => \__( 'Automatically generated.', 'better-seo' ),
						'assess' => [
							'base' => \__( "It's built from the page content.", 'better-seo' ),
						],
					],
					'emptynoauto' => [
						'symbol' => \_x( 'D', 'Description', 'better-seo' ),
						'title'  => \__( 'Description', 'better-seo' ),
						'status' => Builder::STATE_UNKNOWN,
						'reason' => \__( 'Empty.', 'better-seo' ),
						'assess' => [
							'noauto' => \__( 'No page description is set.', 'better-seo' ),
						],
					],
					'custom'      => [
						'symbol' => \_x( 'D', 'Description', 'better-seo' ),
						'title'  => \__( 'Description', 'better-seo' ),
						'status' => Builder::STATE_GOOD,
						'reason' => \__( 'Obtained from the page SEO meta input.', 'better-seo' ),
						'assess' => [
							'base' => \__( "It's built from the page SEO meta input.", 'better-seo' ),
						],
					],
				],
			],
		);

		$generator_args = [ 'id' => static::$query['id'] ];
		$desc           = Meta\Description::get_custom_description( $generator_args );

		if ( \strlen( $desc ) ) {
			$item = $cache['defaults']['custom'];

			if ( $this->query_cache['states']['ishome'] ) {
				// Don't use cache here — only one page can have this state.
				if ( Data\Plugin::get_option( 'homepage_description' ) ) {
					$item['assess']['homepage'] = \__( 'The description inputted at the SEO Settings screen is used.', 'better-seo' );
				} else {
					$item['assess']['homepage'] = \__( 'The description inputted at the Edit Page screen is used.', 'better-seo' );
				}
			}

			if ( Migrate::text_has_unprocessed_syntax( $desc ) ) {
				$item['status']           = Builder::STATE_BAD;
				$item['reason']           = $cache['reason']['syntax'];
				$item['assess']['syntax'] = $cache['assess']['syntax'];

				return $item;
			}
		} elseif ( ! Meta\Description::may_generate( $generator_args ) ) {
			return $cache['defaults']['emptynoauto'];
		} else {
			$item = $cache['defaults']['generated'];
			$desc = Meta\Description::get_generated_description( $generator_args );

			if ( ! \strlen( $desc ) ) {
				$item['reason'] = $cache['reason']['empty'];

				unset( $item['assess']['base'] );

				if ( Data\Post::uses_non_html_page_builder( static::$query['id'] ) ) {
					$item['status']          = Builder::STATE_UNKNOWN;
					$item['assess']['empty'] = $cache['assess']['builder'];
				} elseif ( Data\Post::is_protected( static::$query['id'] ) ) {
					$item['status']          = Builder::STATE_UNKNOWN;
					$item['assess']['empty'] = $cache['assess']['protected'];
				} else {
					$item['status']          = Builder::STATE_UNDEFINED;
					$item['assess']['empty'] = $cache['assess']['empty'];
				}

				return $item;
			} elseif ( ! empty( $this->query_cache['post']->post_excerpt ) ) {
				$item['assess']['base'] = $cache['assess']['excerpt'];
			}
		}

		$repeated_words = Strings::get_word_count( $desc, [ 'short_word_length' => $cache['params']['dupe_short'] ] );

		if ( $repeated_words ) {
			$dupes = [];

			foreach ( $repeated_words as $_repeated_word ) {
				$dupes[] = \sprintf(
					/* translators: 1: Word found, 2: Occurrences */
					\esc_attr__( '&#8220;%1$s&#8221; is used %2$d times.', 'better-seo' ),
					\esc_attr( key( $_repeated_word ) ),
					reset( $_repeated_word ),
				);
			}

			$item['assess']['dupe'] = implode( ' ', $dupes );

			$max = max( $repeated_words );
			$max = reset( $max );

			// Warn when more than 3x triplet+ or quintet+ words are found.
			if ( $max > 3 || \count( $repeated_words ) > 1 ) {
				$item['reason'] = $cache['reason']['foundmanydupe'];
				$item['status'] = Builder::STATE_BAD;
				return $item;
			} else {
				$item['reason'] = $cache['reason']['founddupe'];
				$item['status'] = Builder::STATE_OKAY;
			}
		}

		$guidelines      = Guidelines::get_text_size_guidelines(
			$this->query_cache['states']['locale']
		)['description']['search']['chars'];
		$guidelines_i18n = static::get_cache( 'general/i18n/textsizeguidelines' );

		$desc_len = mb_strlen( Sanitize::normalize_metadata_content_for_strcmp( $desc ) );

		if ( $desc_len < $guidelines['lower'] ) {
			$item['status'] = Builder::STATE_BAD;
			$item['reason'] = $guidelines_i18n['shortdot']['farTooShort'];
			$length_i18n    = $guidelines_i18n['long']['farTooShort'];
		} elseif ( $desc_len < $guidelines['goodLower'] ) {
			$item['status'] = Builder::STATE_OKAY;
			$item['reason'] = $guidelines_i18n['shortdot']['tooShort'];
			$length_i18n    = $guidelines_i18n['long']['tooShort'];
		} elseif ( $desc_len > $guidelines['upper'] ) {
			$item['status'] = Builder::STATE_BAD;
			$item['reason'] = $guidelines_i18n['shortdot']['farTooLong'];
			$length_i18n    = $guidelines_i18n['long']['farTooLong'];
		} elseif ( $desc_len > $guidelines['goodUpper'] ) {
			$item['status'] = Builder::STATE_OKAY;
			$item['reason'] = $guidelines_i18n['shortdot']['tooLong'];
			$length_i18n    = $guidelines_i18n['long']['tooLong'];
		} else {
			$length_i18n = $guidelines_i18n['long']['good'];
		}

		$item['assess']['length'] = \sprintf(
			$cache['params']['disclaim'],
			$length_i18n,
			$cache['params']['estimated'],
		);

		return $item;
	}

	/**
	 * Runs the indexing SEO Bar test for the current post/page.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The SEO Bar item definition.
	 */
	protected function test_indexing(): array {

		$cache = static::get_cache( 'page/indexing/defaults' ) ?: static::set_cache(
			'page/indexing/defaults',
			[
				'params'   => [],
				'assess'   => [
					'robotstxt'    => \__( 'The robots.txt file is nonstandard, and may still direct search engines differently.', 'better-seo' ),
					'notpublic'    => \__( 'WordPress discourages crawling via the Reading Settings.', 'better-seo' ),
					'site'         => \__( 'Indexing is discouraged for the whole site at the SEO Settings screen.', 'better-seo' ),
					'posttype'     => \__( 'Indexing is discouraged for this post type at the SEO Settings screen.', 'better-seo' ),
					'protected'    => \__( 'The page is protected, so indexing is discouraged.', 'better-seo' ),
					'override'     => \__( 'The page SEO meta input overrides the indexing state.', 'better-seo' ),
					'canonicalurl' => \__( 'A custom canonical URL is set that points to another page.', 'better-seo' ),
				],
				'reason'   => [
					'notpublic'    => \__( 'WordPress overrides the robots directive.', 'better-seo' ),
					'protected'    => \__( 'The page is protected.', 'better-seo' ),
					'notpublished' => \__( 'The page is not published.', 'better-seo' ),
					'canonicalurl' => \__( 'The canonical URL points to another page.', 'better-seo' ),
				],
				'defaults' => [
					'index'   => [
						'symbol' => \_x( 'I', 'Indexing', 'better-seo' ),
						'title'  => \__( 'Indexing', 'better-seo' ),
						'status' => Builder::STATE_GOOD,
						'reason' => \__( 'Page may be indexed.', 'better-seo' ),
						'assess' => [
							'base' => \__( 'The robots meta tag allows indexing.', 'better-seo' ),
						],
					],
					'noindex' => [
						'symbol' => \_x( 'I', 'Indexing', 'better-seo' ),
						'title'  => \__( 'Indexing', 'better-seo' ),
						'status' => Builder::STATE_UNKNOWN,
						'reason' => \__( 'Page may not be indexed.', 'better-seo' ),
						'assess' => [
							'base' => \__( 'The robots meta tag does not allow indexing.', 'better-seo' ),
						],
					],
					'draft'   => [
						'symbol' => \_x( 'I', 'Indexing', 'better-seo' ),
						'title'  => \__( 'Indexing', 'better-seo' ),
						'status' => Builder::STATE_UNKNOWN,
						'reason' => \__( 'Page is invisible.', 'better-seo' ),
						'assess' => [
							'base' => \__( "This page isn't published and can't be found publicly.", 'better-seo' ),
						],
					],
				],
			],
		);

		if ( $this->query_cache['states']['isdraft'] ) {
			return $cache['defaults']['draft'];
		} elseif ( $this->query_cache['states']['robotsmeta']['noindex'] ) {
			$item = $cache['defaults']['noindex'];
		} else {
			$item = $cache['defaults']['index'];
		}

		$robots_global = static::get_cache( 'general/detect/robotsglobal' );

		if ( ! $robots_global['blogpublic'] ) {
			$item['status'] = Builder::STATE_BAD;
			$item['reason'] = $cache['reason']['notpublic'];

			unset( $item['assess']['base'] );

			$item['assess']['notpublic'] = $cache['assess']['notpublic'];
			$item['symbol']              = '!!!';

			return $item;
		}

		if ( $this->query_cache['states']['robotsmeta']['noindex'] ) {
			if ( $this->query_cache['states']['isprotected'] ) {
				$item['status']              = Builder::STATE_UNKNOWN;
				$item['reason']              = $cache['reason']['protected'];
				$item['assess']['protected'] = $cache['assess']['protected'];

				return $item;
			}
		}

		if ( $robots_global['site']['noindex'] ) {
			$item['assess']['site'] = $cache['assess']['site'];
		}

		if ( $this->query_cache['states']['ishome'] ) {
			if ( Data\Plugin::get_option( 'homepage_noindex' ) ) {
				// Don't use cache — this only runs once.
				$item['assess']['homepage'] = \__( 'Indexing is discouraged for the homepage at the SEO Settings screen.', 'better-seo' );
			}
		}

		if ( ! empty( $robots_global['posttype']['noindex'][ static::$query['post_type'] ] ) ) {
			$item['assess']['posttype'] = $cache['assess']['posttype'];
		}

		if ( $this->query_cache['meta']['_genesis_canonical_uri'] ) {
			$permalink = Meta\URI::get_generated_url( [ 'id' => static::$query['id'] ] );
			$canonical = Meta\URI::get_canonical_url( [ 'id' => static::$query['id'] ] );

			if ( $permalink !== $canonical ) {
				$item['status']              = Builder::STATE_UNKNOWN;
				$item['reason']              = $cache['reason']['canonicalurl'];
				$item['assess']['protected'] = $cache['assess']['canonicalurl'];
			}
		}

		if ( 0 !== Sanitize::qubit( $this->query_cache['meta']['_genesis_noindex'] ) ) {
			unset(
				$item['assess']['posttype'],
				$item['assess']['homepage'],
				$item['assess']['site'],
			);

			$item['assess']['override'] = $cache['assess']['override'];
		}

		if ( ! $this->query_cache['states']['robotsmeta']['noindex'] && $robots_global['hasrobotstxt'] ) {
			$item['assess']['robotstxt'] = $cache['assess']['robotstxt'];
		}

		return $item;
	}

	/**
	 * Runs the link following SEO Bar test for the current post/page.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The SEO Bar item definition.
	 */
	protected function test_following(): array {

		$cache = static::get_cache( 'page/following/defaults' ) ?: static::set_cache(
			'page/following/defaults',
			[
				'params'   => [],
				'assess'   => [
					'robotstxt' => \__( 'The robots.txt file is nonstandard, and may still direct search engines differently.', 'better-seo' ),
					'notpublic' => \__( 'WordPress discourages crawling via the Reading Settings.', 'better-seo' ),
					'site'      => \__( 'Link following is discouraged for the whole site at the SEO Settings screen.', 'better-seo' ),
					'posttype'  => \__( 'Link following is discouraged for this post type at the SEO Settings screen.', 'better-seo' ),
					'override'  => \__( 'The page SEO meta input overrides the link following state.', 'better-seo' ),
					'noindex'   => \__( 'The page may not be indexed, this may also discourage link following.', 'better-seo' ),
				],
				'reason'   => [
					'notpublic'    => \__( 'WordPress overrides the robots directive.', 'better-seo' ),
					'notpublished' => \__( 'The page is not published.', 'better-seo' ),
				],
				'defaults' => [
					'follow'   => [
						'symbol' => \_x( 'F', 'Following', 'better-seo' ),
						'title'  => \__( 'Following', 'better-seo' ),
						'status' => Builder::STATE_GOOD,
						'reason' => \__( 'Page links may be followed.', 'better-seo' ),
						'assess' => [
							'base' => \__( 'The robots meta tag allows link following.', 'better-seo' ),
						],
					],
					'nofollow' => [
						'symbol' => \_x( 'F', 'Following', 'better-seo' ),
						'title'  => \__( 'Following', 'better-seo' ),
						'status' => Builder::STATE_UNKNOWN,
						'reason' => \__( 'Page links may not be followed.', 'better-seo' ),
						'assess' => [
							'base' => \__( 'The robots meta tag does not allow link following.', 'better-seo' ),
						],
					],
					'draft'    => [
						'symbol' => \_x( 'F', 'Following', 'better-seo' ),
						'title'  => \__( 'Following', 'better-seo' ),
						'status' => Builder::STATE_UNKNOWN,
						'reason' => \__( 'Page is invisible.', 'better-seo' ),
						'assess' => [
							'base' => \__( "This page isn't published and can't be found publicly.", 'better-seo' ),
						],
					],
				],
			],
		);

		if ( $this->query_cache['states']['isdraft'] ) {
			return $cache['defaults']['draft'];
		} elseif ( $this->query_cache['states']['robotsmeta']['nofollow'] ) {
			$item = $cache['defaults']['nofollow'];
		} else {
			$item = $cache['defaults']['follow'];
		}

		$robots_global = static::get_cache( 'general/detect/robotsglobal' );

		if ( ! $robots_global['blogpublic'] ) {
			$item['status'] = Builder::STATE_BAD;
			$item['reason'] = $cache['reason']['notpublic'];

			unset( $item['assess']['base'] );

			$item['assess']['notpublic'] = $cache['assess']['notpublic'];
			$item['symbol']              = '!!!';

			return $item;
		}

		if ( $robots_global['site']['nofollow'] ) {
			$item['assess']['site'] = $cache['assess']['site'];
		}

		if ( $this->query_cache['states']['ishome'] ) {
			if ( Data\Plugin::get_option( 'homepage_nofollow' ) ) {
				// Don't use cache — this only runs once.
				$item['assess']['homepage'] = \__( 'Link following is discouraged for the homepage at the SEO Settings screen.', 'better-seo' );
			}
		}

		if ( ! empty( $robots_global['posttype']['nofollow'][ static::$query['post_type'] ] ) ) {
			$item['assess']['posttype'] = $cache['assess']['posttype'];
		}

		if ( 0 !== Sanitize::qubit( $this->query_cache['meta']['_genesis_nofollow'] ) ) {
			unset(
				$item['assess']['posttype'],
				$item['assess']['homepage'],
				$item['assess']['site'],
			);

			$item['assess']['override'] = $cache['assess']['override'];
		}

		if ( ! $this->query_cache['states']['robotsmeta']['nofollow'] ) {
			if ( $this->query_cache['states']['robotsmeta']['noindex'] ) {
				$item['status']            = Builder::STATE_OKAY;
				$item['assess']['noindex'] = $cache['assess']['noindex'];
			}

			if ( $robots_global['hasrobotstxt'] ) {
				$item['assess']['robotstxt'] = $cache['assess']['robotstxt'];
			}
		}

		return $item;
	}

	/**
	 * Runs the archiving SEO Bar test for the current post/page.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The SEO Bar item definition.
	 */
	protected function test_archiving(): array {

		$cache = static::get_cache( 'page/archiving/defaults' ) ?: static::set_cache(
			'page/archiving/defaults',
			[
				'params'   => [],
				'assess'   => [
					'robotstxt' => \__( 'The robots.txt file is nonstandard, and may still direct search engines differently.', 'better-seo' ),
					'notpublic' => \__( 'WordPress discourages crawling via the Reading Settings.', 'better-seo' ),
					'site'      => \__( 'Archiving is discouraged for the whole site at the SEO Settings screen.', 'better-seo' ),
					'posttype'  => \__( 'Archiving is discouraged for this post type at the SEO Settings screen.', 'better-seo' ),
					'override'  => \__( 'The page SEO meta input overrides the archiving state.', 'better-seo' ),
					'noindex'   => \__( 'The page may not be indexed, this may also discourage archiving.', 'better-seo' ),
				],
				'reason'   => [
					'notpublic'    => \__( 'WordPress overrides the robots directive.', 'better-seo' ),
					'notpublished' => \__( 'The page is not published.', 'better-seo' ),
				],
				'defaults' => [
					'archive'   => [
						'symbol' => \_x( 'A', 'Archiving', 'better-seo' ),
						'title'  => \__( 'Archiving', 'better-seo' ),
						'status' => Builder::STATE_GOOD,
						'reason' => \__( 'Page may be archived.', 'better-seo' ),
						'assess' => [
							'base' => \__( 'The robots meta tag allows archiving.', 'better-seo' ),
						],
					],
					'noarchive' => [
						'symbol' => \_x( 'A', 'Archiving', 'better-seo' ),
						'title'  => \__( 'Archiving', 'better-seo' ),
						'status' => Builder::STATE_UNKNOWN,
						'reason' => \__( 'Page may not be archived.', 'better-seo' ),
						'assess' => [
							'base' => \__( 'The robots meta tag does not allow archiving.', 'better-seo' ),
						],
					],
					'draft'     => [
						'symbol' => \_x( 'A', 'Archiving', 'better-seo' ),
						'title'  => \__( 'Archiving', 'better-seo' ),
						'status' => Builder::STATE_UNKNOWN,
						'reason' => \__( 'Page is invisible.', 'better-seo' ),
						'assess' => [
							'base' => \__( "This page isn't published and can't be found publicly.", 'better-seo' ),
						],
					],
				],
			],
		);

		if ( $this->query_cache['states']['isdraft'] ) {
			return $cache['defaults']['draft'];
		} elseif ( $this->query_cache['states']['robotsmeta']['noarchive'] ) {
			$item = $cache['defaults']['noarchive'];
		} else {
			$item = $cache['defaults']['archive'];
		}

		$robots_global = static::get_cache( 'general/detect/robotsglobal' );

		if ( ! $robots_global['blogpublic'] ) {
			$item['status'] = Builder::STATE_BAD;
			$item['reason'] = $cache['reason']['notpublic'];

			unset( $item['assess']['base'] );

			$item['assess']['notpublic'] = $cache['assess']['notpublic'];
			$item['symbol']              = '!!!';

			return $item;
		}

		if ( $robots_global['site']['noarchive'] ) {
			$item['assess']['site'] = $cache['assess']['site'];
		}

		if ( $this->query_cache['states']['ishome'] ) {
			if ( Data\Plugin::get_option( 'homepage_noarchive' ) ) {
				// Don't use cache — this only runs once.
				$item['assess']['homepage'] = \__( 'Archiving is discouraged for the homepage at the SEO Settings screen.', 'better-seo' );
			}
		}

		if ( ! empty( $robots_global['posttype']['noarchive'][ static::$query['post_type'] ] ) ) {
			$item['assess']['posttype'] = $cache['assess']['posttype'];
		}

		if ( 0 !== Sanitize::qubit( $this->query_cache['meta']['_genesis_noarchive'] ) ) {
			unset(
				$item['assess']['posttype'],
				$item['assess']['homepage'],
				$item['assess']['site'],
			);

			$item['assess']['override'] = $cache['assess']['override'];
		}

		if ( ! $this->query_cache['states']['robotsmeta']['noarchive'] ) {
			if ( $this->query_cache['states']['robotsmeta']['noindex'] ) {
				$item['status']            = Builder::STATE_OKAY;
				$item['assess']['noindex'] = $cache['assess']['noindex'];
			}

			if ( $robots_global['hasrobotstxt'] ) {
				$item['assess']['robotstxt'] = $cache['assess']['robotstxt'];
			}
		}

		return $item;
	}

	/**
	 * Runs the redirect SEO Bar test for the current post/page.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The SEO Bar item definition.
	 */
	protected function test_redirect(): array {

		if ( empty( $this->query_cache['meta']['redirect'] ) ) {
			$default = static::get_cache( 'page/redirect/default/0' ) ?: static::set_cache(
				'page/redirect/default/0',
				[
					'symbol' => \_x( 'R', 'Redirect', 'better-seo' ),
					'title'  => \__( 'Redirection', 'better-seo' ),
					'status' => Builder::STATE_GOOD,
					'reason' => \__( 'Page does not redirect visitors.', 'better-seo' ),
					'assess' => [
						'redirect' => \__( 'Visitors and crawlers may view this page.', 'better-seo' ),
					],
					'meta'   => [
						'blocking' => false,
					],
				],
			);

			if ( $this->query_cache['states']['isdraft'] ) {
				$default['assess']['redirect'] = \__( 'Visitors and crawlers may view this page once published.', 'better-seo' );
			}

			return $default;
		}

		return static::get_cache( 'post/redirect/default/1' ) ?: static::set_cache(
			'post/redirect/default/1',
			[
				'symbol' => \_x( 'R', 'Redirect', 'better-seo' ),
				'title'  => \__( 'Redirection', 'better-seo' ),
				'status' => Builder::STATE_UNKNOWN,
				'reason' => \__( 'Page redirects visitors.', 'better-seo' ),
				'assess' => [
					'redirect' => \__( 'All visitors and crawlers are being redirected. So, no other SEO enhancements are effective.', 'better-seo' ),
				],
				'meta'   => [
					'blocking' => true,
				],
			],
		);
	}
}