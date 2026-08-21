<?php
/**
 * Better SEO - Data Plugin PTA
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Data\Plugin
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

namespace Better_SEO\Data\Plugin;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\is_headless;

use Better_SEO\{
	Data,
	Helper\Post_Type,
	Helper\Query,
	Traits\Property_Refresher,
};

/**
 * Class Better_SEO\Data\Plugin\PTA
 *
 * Provides data access methods for Better SEO Post Type Archive (PTA) meta,
 * including meta retrieval and default meta generation for all public PTAs.
 *
 * @since 1.0.0
 */
class PTA {
	use Property_Refresher;

	/**
	 * Memoized PTA meta arrays keyed by post type.
	 *
	 * Capped at 70 entries to prevent memory overload.
	 *
	 * @since 1.0.0
	 * @var   array<string, array<string, mixed>>
	 */
	private static array $meta_memo = [];

	/**
	 * Returns a specific meta item value for a given post type archive.
	 *
	 * @since 1.0.0
	 *
	 * @param string $item      The meta key to retrieve.
	 * @param string $post_type The post type slug. Defaults to current post type.
	 * @return mixed The meta item value, or null if not found.
	 */
	public static function get_meta_item( string $item, string $post_type = '' ): mixed {

		$post_type = $post_type ?: Query::get_current_post_type();

		return $post_type
			? static::get_meta( $post_type )[ $item ] ?? null
			: null;
	}

	/**
	 * Returns the full Better SEO PTA meta array for a given post type, memoized.
	 *
	 * Merges stored PTA meta with defaults. Returns defaults for unsupported
	 * post types or when running headless. Applies the better_seo_post_type_archive_meta filter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type The post type slug. Defaults to current post type.
	 * @return array<string, mixed> The merged PTA meta array.
	 */
	public static function get_meta( string $post_type = '' ): array {

		$post_type = $post_type ?: Query::get_current_post_type();

		if ( isset( static::$meta_memo[ $post_type ] ) ) {
			return static::$meta_memo[ $post_type ];
		}

		if ( empty( static::$meta_memo ) ) {
			static::register_automated_refresh( 'meta_memo' );
		}

		if ( empty( $post_type ) || ! Post_Type::is_supported( $post_type ) ) {
			return static::$meta_memo[ $post_type ] = static::get_default_meta( $post_type );
		}

		// Cap memo at 70 entries — keep the first 7 (lucky first) to avoid memory overload.
		if ( \count( static::$meta_memo ) > 69 ) {
			static::$meta_memo = \array_slice( static::$meta_memo, 0, 7, true );
		}

		$is_headless = is_headless( 'settings' );

		if ( $is_headless ) {
			$meta = [];
		} else {
			$meta = Data\Plugin::get_option( 'pta', $post_type ) ?: [];
		}

		/**
		 * Filters the Better SEO post type archive meta array.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $meta        The merged PTA meta array.
		 * @param string               $post_type   The post type slug.
		 * @param bool                 $is_headless Whether the plugin is running headless.
		 */
		return static::$meta_memo[ $post_type ] = \apply_filters(
			'better_seo_post_type_archive_meta',
			array_merge(
				static::get_default_meta( $post_type ),
				$meta,
			),
			$post_type,
			$is_headless,
		);
	}

	/**
	 * Returns the default meta arrays for all public post type archives.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>> Map of post type slug to default meta array.
	 */
	public static function get_all_default_meta(): array {

		$defaults = [];

		foreach ( Post_Type::get_public_pta() as $pta ) {
			$defaults[ $pta ] = static::get_default_meta( $pta );
		}

		return $defaults;
	}

	/**
	 * Returns the default Better SEO PTA meta array for a given post type.
	 *
	 * Applies the better_seo_get_post_type_archive_meta_defaults filter.
	 *
	 * @since 1.0.0
	 *
	 * @param string $post_type The post type slug. Defaults to current post type.
	 * @return array<string, mixed> The default PTA meta array.
	 */
	public static function get_default_meta( string $post_type = '' ): array {
		/**
		 * Filters the Better SEO post type archive meta defaults.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $defaults  The default meta array.
		 * @param string               $post_type The post type slug.
		 */
		return (array) \apply_filters(
			'better_seo_get_post_type_archive_meta_defaults',
			[
				'doctitle'           => '',
				'title_no_blog_name' => 0,
				'description'        => '',
				'og_title'           => '',
				'og_description'     => '',
				'tw_title'           => '',
				'tw_description'     => '',
				'tw_card_type'       => '',
				'social_image_url'   => '',
				'social_image_id'    => 0,
				'canonical'          => '',
				'noindex'            => 0,
				'nofollow'           => 0,
				'noarchive'          => 0,
				'redirect'           => '',
			],
			$post_type ?: Query::get_current_post_type(),
		);
	}
}