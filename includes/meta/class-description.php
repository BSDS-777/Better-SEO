<?php
/**
 * Better SEO - Meta Description
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
	Meta,
};
use Better_SEO\Helper\{
	Guidelines,
	Query,
	Format\Strings,
};

/**
 * Class Better_SEO\Meta\Description
 *
 * Provides meta description generation for Better SEO, including custom field
 * retrieval, auto-generated descriptions, and generation permission checks.
 *
 * @since 1.0.0
 */
class Description {

	/**
	 * Returns the meta description for the given args or current query context.
	 *
	 * Returns the custom description if set, otherwise falls back to the generated description.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The meta description string.
	 */
	public static function get_description( ?array $args = null ): string {
		return coalesce_strlen( self::get_custom_description( $args ) )
			?? self::get_generated_description( $args );
	}

	/**
	 * Returns the custom meta description for the given args or current query context.
	 *
	 * Applies the better_seo_custom_field_description filter.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The sanitized custom description, or empty string if not set.
	 */
	public static function get_custom_description( ?array $args = null ): string {

		if ( isset( $args ) ) {
			normalize_generation_args( $args );
			$desc = self::get_custom_description_from_args( $args );
		} else {
			$desc = self::get_custom_description_from_query();
		}

		/**
		 * Filters the Better SEO custom field description.
		 *
		 * @since 1.0.0
		 *
		 * @param string                    $desc The custom-field description.
		 * @param array<string, mixed>|null $args The query arguments (id, tax, pta, uid), or null.
		 */
		return Sanitize::metadata_content( \apply_filters(
			'better_seo_custom_field_description',
			$desc,
			$args,
		) );
	}

	/**
	 * Returns the auto-generated meta description for the given args or current query context.
	 *
	 * Applies the better_seo_description_excerpt and better_seo_generated_description filters.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @param string                    $type The description context: 'search', 'opengraph', or 'twitter'. Default 'search'.
	 * @return string The sanitized generated description, or empty string if generation is disabled.
	 */
	public static function get_generated_description( ?array $args = null, string $type = 'search' ): string {

		if ( ! self::may_generate( $args ) ) {
			return '';
		}

		$type = match ( $type ) {
			'opengraph', 'twitter', 'search' => $type,
			default                          => 'search',
		};

		if ( isset( $args ) ) {
			normalize_generation_args( $args );
		}

		if ( null !== $memo = memo( null, $args, $type ) ) {
			return $memo;
		}

		$excerpt = Description\Excerpt::get_excerpt( $args );

		/**
		 * Filters the Better SEO description excerpt before clamping.
		 *
		 * @since 1.0.0
		 *
		 * @param string                    $excerpt The raw excerpt string.
		 * @param array<string, mixed>|null $args    The generation args, or null.
		 * @param string                    $type    The description context type.
		 */
		$excerpt = (string) \apply_filters(
			'better_seo_description_excerpt',
			$excerpt,
			$args,
			$type,
		);

		$desc = Strings::clamp_sentence(
			$excerpt,
			1,
			Guidelines::get_text_size_guidelines()['description'][ $type ]['chars']['goodUpper'],
		);

		/**
		 * Filters the Better SEO generated description.
		 *
		 * @since 1.0.0
		 *
		 * @param string                    $desc The generated description string.
		 * @param array<string, mixed>|null $args The generation args, or null.
		 * @param string                    $type The description context type.
		 */
		$desc = (string) \apply_filters(
			'better_seo_generated_description',
			$desc,
			$args,
			$type,
		);

		return memo(
			\strlen( $desc ) ? Sanitize::metadata_content( $desc ) : '',
			$args,
			$type,
		);
	}

	/**
	 * Returns the custom description from the current query context.
	 *
	 * @since 1.0.0
	 *
	 * @return string The sanitized custom description, or empty string if not set.
	 */
	public static function get_custom_description_from_query(): string {

		if ( Query::is_real_front_page() ) {
			if ( Query::is_static_front_page() ) {
				$desc = coalesce_strlen( Data\Plugin::get_option( 'homepage_description' ) )
					?? Data\Plugin\Post::get_meta_item( '_genesis_description' );
			} else {
				$desc = Data\Plugin::get_option( 'homepage_description' );
			}
		} elseif ( Query::is_singular() ) {
			$desc = Data\Plugin\Post::get_meta_item( '_genesis_description' );
		} elseif ( Query::is_editable_term() ) {
			$desc = Data\Plugin\Term::get_meta_item( 'description' );
		} elseif ( \is_post_type_archive() ) {
			$desc = Data\Plugin\PTA::get_meta_item( 'description' );
		}

		if ( isset( $desc ) && \strlen( $desc ) ) {
			return Sanitize::metadata_content( $desc );
		}

		return '';
	}

	/**
	 * Returns the custom description from the given generation args.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args The generation args.
	 * @return string The sanitized custom description, or empty string if not set.
	 */
	public static function get_custom_description_from_args( array $args ): string {

		normalize_generation_args( $args );

		$desc = match ( get_query_type_from_args( $args ) ) {
			'single'   => Query::is_static_front_page( $args['id'] )
				? ( coalesce_strlen( Data\Plugin::get_option( 'homepage_description' ) )
					?? Data\Plugin\Post::get_meta_item( '_genesis_description', $args['id'] ) )
				: Data\Plugin\Post::get_meta_item( '_genesis_description', $args['id'] ),
			'term'     => Data\Plugin\Term::get_meta_item( 'description', $args['id'] ),
			'homeblog' => Data\Plugin::get_option( 'homepage_description' ),
			'pta'      => Data\Plugin\PTA::get_meta_item( 'description', $args['pta'] ),
			default    => null,
		};

		if ( isset( $desc ) && \strlen( $desc ) ) {
			return Sanitize::metadata_content( $desc );
		}

		return '';
	}

	/**
	 * Returns whether auto-description generation is permitted for the given args.
	 *
	 * Applies the better_seo_enable_auto_description filter.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return bool True if auto-description generation is enabled.
	 */
	public static function may_generate( ?array $args = null ): bool {

		if ( isset( $args ) ) {
			normalize_generation_args( $args );
		}

		/**
		 * Filters whether Better SEO auto-description generation is enabled.
		 *
		 * @since 1.0.0
		 *
		 * @param bool                      $enabled Whether auto-description is enabled.
		 * @param array<string, mixed>|null $args    The generation args, or null.
		 */
		return (bool) \apply_filters(
			'better_seo_enable_auto_description',
			Data\Plugin::get_option( 'auto_description' ),
			$args,
		);
	}
}