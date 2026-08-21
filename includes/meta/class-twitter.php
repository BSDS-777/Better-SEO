<?php
/**
 * Better SEO - Meta Twitter
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
	normalize_generation_args,
};

use Better_SEO\{
	Data,
	Data\Filter\Sanitize,
	Helper\Query,
};

/**
 * Class Better_SEO\Meta\Twitter
 *
 * Provides Twitter Card meta values for Better SEO, including card type,
 * site handle, creator handle, title, and description.
 *
 * @since 1.0.0
 */
class Twitter {

	/**
	 * Returns whether Twitter meta should fall back to Open Graph values, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if Open Graph tags are enabled and should be used as fallback.
	 */
	public static function fallback_to_open_graph(): bool {
		static $fallback;
		return $fallback ??= (bool) Data\Plugin::get_option( 'og_tags' );
	}

	/**
	 * Returns the Twitter Card type (custom or generated) for the given args or current query.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The Twitter Card type string.
	 */
	public static function get_card_type( ?array $args = null ): string {
		return self::get_custom_card_type( $args )
			?: self::get_generated_card_type( $args );
	}

	/**
	 * Returns the custom Twitter Card type for the given args or current query context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The custom Twitter Card type, or empty string if not set or unsupported.
	 */
	public static function get_custom_card_type( ?array $args = null ): string {

		if ( isset( $args ) ) {
			normalize_generation_args( $args );

			$card = match ( get_query_type_from_args( $args ) ) {
				'single'   => Query::is_static_front_page( $args['id'] )
					? ( Data\Plugin::get_option( 'homepage_twitter_card_type' )
						?: Data\Plugin\Post::get_meta_item( '_better_seo_twitter_card_type', $args['id'] ) )
					: Data\Plugin\Post::get_meta_item( '_better_seo_twitter_card_type', $args['id'] ),
				'term'     => Data\Plugin\Term::get_meta_item( 'tw_card_type', $args['id'] ),
				'homeblog' => Data\Plugin::get_option( 'homepage_twitter_card_type' ),
				'pta'      => Data\Plugin\PTA::get_meta_item( 'tw_card_type', $args['pta'] ),
				default    => null,
			};
		} else {
			if ( Query::is_real_front_page() ) {
				if ( Query::is_static_front_page() ) {
					$card = Data\Plugin::get_option( 'homepage_twitter_card_type' )
						?: Data\Plugin\Post::get_meta_item( '_better_seo_twitter_card_type' );
				} else {
					$card = Data\Plugin::get_option( 'homepage_twitter_card_type' );
				}
			} elseif ( Query::is_singular() ) {
				$card = Data\Plugin\Post::get_meta_item( '_better_seo_twitter_card_type' );
			} elseif ( Query::is_editable_term() ) {
				$card = Data\Plugin\Term::get_meta_item( 'tw_card_type' );
			} elseif ( \is_post_type_archive() ) {
				$card = Data\Plugin\PTA::get_meta_item( 'tw_card_type' );
			}
		}

		if ( ! empty( $card ) && \in_array( $card, self::get_supported_cards(), true ) ) {
			return $card;
		}

		return '';
	}

	/**
	 * Returns the generated Twitter Card type from plugin settings.
	 *
	 * Falls back to the first supported card type if the configured value is unsupported.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args (unused). Default null.
	 * @return string The generated Twitter Card type string.
	 */
	public static function get_generated_card_type( ?array $args = null ): string {

		$card            = Data\Plugin::get_option( 'twitter_card' );
		$supported_cards = self::get_supported_cards();

		if ( ! \in_array( $card, $supported_cards, true ) ) {
			$card = reset( $supported_cards );
		}

		return $card;
	}

	/**
	 * Returns the list of supported Twitter Card types.
	 *
	 * Applies the better_seo_supported_twitter_card_types filter.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> The supported Twitter Card type slugs.
	 */
	public static function get_supported_cards(): array {
		/**
		 * Filters the Better SEO supported Twitter Card types.
		 *
		 * @since 1.0.0
		 * @param array<int, string> $cards The supported card type slugs.
		 */
		return \apply_filters(
			'better_seo_supported_twitter_card_types',
			[
				'summary',
				'summary_large_image',
			],
		);
	}

	/**
	 * Returns the Twitter site handle from plugin settings.
	 *
	 * @since 1.0.0
	 *
	 * @return string The Twitter site @handle string.
	 */
	public static function get_site(): string {
		return Data\Plugin::get_option( 'twitter_site' );
	}

	/**
	 * Returns the Twitter creator handle for the current post's author.
	 *
	 * Falls back to the plugin-level Twitter creator setting if no user-level value is set.
	 *
	 * @since 1.0.0
	 *
	 * @return string The Twitter creator @handle string.
	 */
	public static function get_creator(): string {
		return Data\Plugin\User::get_current_post_author_meta_item( 'twitter_page' )
			?: Data\Plugin::get_option( 'twitter_creator' );
	}

	/**
	 * Returns the Twitter title (custom or generated) for the given args or current query.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The Twitter title string.
	 */
	public static function get_title( ?array $args = null ): string {
		return coalesce_strlen( self::get_custom_title( $args ) )
			?? self::get_generated_title( $args );
	}

	/**
	 * Returns the custom Twitter title for the given args or current query context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The custom Twitter title string.
	 */
	public static function get_custom_title( ?array $args = null ): string {
		return isset( $args )
			? self::get_custom_title_from_args( $args )
			: self::get_custom_title_from_query();
	}

	/**
	 * Returns the custom Twitter title from the current query context.
	 *
	 * Falls back to Open Graph or standard title if no Twitter-specific title is set.
	 *
	 * @since 1.0.0
	 *
	 * @return string The custom Twitter title string.
	 */
	public static function get_custom_title_from_query(): string {

		if ( Query::is_real_front_page() ) {
			if ( Query::is_static_front_page() ) {
				$title = coalesce_strlen( Data\Plugin::get_option( 'homepage_twitter_title' ) )
					?? Data\Plugin\Post::get_meta_item( '_twitter_title' );
			} else {
				$title = Data\Plugin::get_option( 'homepage_twitter_title' );
			}
		} elseif ( Query::is_singular() ) {
			$title = Data\Plugin\Post::get_meta_item( '_twitter_title' );
		} elseif ( Query::is_editable_term() ) {
			$title = Data\Plugin\Term::get_meta_item( 'tw_title' );
		} elseif ( \is_post_type_archive() ) {
			$title = Data\Plugin\PTA::get_meta_item( 'tw_title' );
		}

		if ( ! isset( $title ) ) {
			return '';
		}

		if ( \strlen( $title ) ) {
			return Sanitize::metadata_content( $title );
		}

		return self::fallback_to_open_graph()
			? Open_Graph::get_custom_title_from_query()
			: Title::get_custom_title( null, true );
	}

	/**
	 * Returns the custom Twitter title from the given generation args.
	 *
	 * Falls back to Open Graph or standard title if no Twitter-specific title is set.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args The generation args.
	 * @return string The custom Twitter title string.
	 */
	public static function get_custom_title_from_args( array $args ): string {

		normalize_generation_args( $args );

		$title = match ( get_query_type_from_args( $args ) ) {
			'single'   => Query::is_static_front_page( $args['id'] )
				? ( coalesce_strlen( Data\Plugin::get_option( 'homepage_twitter_title' ) )
					?? Data\Plugin\Post::get_meta_item( '_twitter_title', $args['id'] ) )
				: Data\Plugin\Post::get_meta_item( '_twitter_title', $args['id'] ),
			'term'     => Data\Plugin\Term::get_meta_item( 'tw_title', $args['id'] ),
			'homeblog' => Data\Plugin::get_option( 'homepage_twitter_title' ),
			'pta'      => Data\Plugin\PTA::get_meta_item( 'tw_title', $args['pta'] ),
			default    => null,
		};

		if ( ! isset( $title ) ) {
			return '';
		}

		if ( \strlen( $title ) ) {
			return Sanitize::metadata_content( $title );
		}

		return self::fallback_to_open_graph()
			? Open_Graph::get_custom_title_from_args( $args )
			: Title::get_custom_title( $args, true );
	}

	/**
	 * Returns the generated Twitter title for the given args or current query.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The generated Twitter title string.
	 */
	public static function get_generated_title( ?array $args = null ): string {
		return Title::get_generated_title( $args, true );
	}

	/**
	 * Returns the Twitter description (custom or generated) for the given args or current query.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The Twitter description string.
	 */
	public static function get_description( ?array $args = null ): string {
		return coalesce_strlen( self::get_custom_description( $args ) )
			?? self::get_generated_description( $args );
	}

	/**
	 * Returns the custom Twitter description for the given args or current query context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The custom Twitter description string.
	 */
	public static function get_custom_description( ?array $args = null ): string {
		return isset( $args )
			? self::get_custom_description_from_args( $args )
			: self::get_custom_description_from_query();
	}

	/**
	 * Returns the custom Twitter description from the current query context.
	 *
	 * Falls back to Open Graph or standard description if no Twitter-specific description is set.
	 *
	 * @since 1.0.0
	 *
	 * @return string The custom Twitter description string.
	 */
	public static function get_custom_description_from_query(): string {

		if ( Query::is_real_front_page() ) {
			if ( Query::is_static_front_page() ) {
				$desc = coalesce_strlen( Data\Plugin::get_option( 'homepage_twitter_description' ) )
					?? Data\Plugin\Post::get_meta_item( '_twitter_description' );
			} else {
				$desc = Data\Plugin::get_option( 'homepage_twitter_description' );
			}
		} elseif ( Query::is_singular() ) {
			$desc = Data\Plugin\Post::get_meta_item( '_twitter_description' );
		} elseif ( Query::is_editable_term() ) {
			$desc = Data\Plugin\Term::get_meta_item( 'tw_description' );
		} elseif ( \is_post_type_archive() ) {
			$desc = Data\Plugin\PTA::get_meta_item( 'tw_description' );
		}

		if ( ! isset( $desc ) ) {
			return '';
		}

		if ( \strlen( $desc ) ) {
			return Sanitize::metadata_content( $desc );
		}

		return self::fallback_to_open_graph()
			? Open_Graph::get_custom_description_from_query()
			: Description::get_custom_description();
	}

	/**
	 * Returns the custom Twitter description from the given generation args.
	 *
	 * Falls back to Open Graph or standard description if no Twitter-specific description is set.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args The generation args.
	 * @return string The custom Twitter description string.
	 */
	public static function get_custom_description_from_args( array $args ): string {

		normalize_generation_args( $args );

		$desc = match ( get_query_type_from_args( $args ) ) {
			'single'   => Query::is_static_front_page( $args['id'] )
				? ( coalesce_strlen( Data\Plugin::get_option( 'homepage_twitter_description' ) )
					?? Data\Plugin\Post::get_meta_item( '_twitter_description', $args['id'] ) )
				: Data\Plugin\Post::get_meta_item( '_twitter_description', $args['id'] ),
			'term'     => Data\Plugin\Term::get_meta_item( 'tw_description', $args['id'] ),
			'homeblog' => Data\Plugin::get_option( 'homepage_twitter_description' ),
			'pta'      => Data\Plugin\PTA::get_meta_item( 'tw_description', $args['pta'] ),
			default    => null,
		};

		if ( ! isset( $desc ) ) {
			return '';
		}

		if ( \strlen( $desc ) ) {
			return Sanitize::metadata_content( $desc );
		}

		return self::fallback_to_open_graph()
			? Open_Graph::get_custom_description_from_args( $args )
			: Title::get_custom_description( $args );
	}

	/**
	 * Returns the generated Twitter description for the given args or current query.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The generated Twitter description string.
	 */
	public static function get_generated_description( ?array $args = null ): string {
		return Description::get_generated_description( $args, 'twitter' );
	}
}