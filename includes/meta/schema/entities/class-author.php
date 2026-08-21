<?php
/**
 * Better SEO - Meta Schema Entities Author
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Meta\Schema\Entities
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

namespace Better_SEO\Meta\Schema\Entities;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\normalize_generation_args;

use Better_SEO\{
	Meta,
	Data,
	Helper\Query,
	Helper\Format\Strings,
};

/**
 * Class Better_SEO\Meta\Schema\Entities\Author
 *
 * Generates the Schema.org Person entity for the current post's author.
 * Includes name, sameAs social profile URLs, and description.
 *
 * @since 1.0.0
 */
final class Author extends Reference {

	/**
	 * The Schema.org @type for this entity.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public static string|array|null $type = 'Person';

	/**
	 * Returns the author user ID from the given args or current query context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return int The author user ID, or 0 if not determinable.
	 */
	private static function get_author_id_from_args( ?array $args ): int {

		if ( isset( $args ) ) {
			normalize_generation_args( $args );

			if ( $args['uid'] ) {
				$author_id = $args['uid'];
			} elseif ( empty( $args['tax'] ) && empty( $args['pta'] ) ) {
				$author_id = Query::get_post_author_id( $args['id'] );
			}
		} else {
			$author_id = Query::get_post_author_id();
		}

		return $author_id ?? 0;
	}

	/**
	 * Returns the Schema.org @id for the author entity.
	 *
	 * Generates a unique ID based on the front page URL and a hash of the author ID.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The author entity @id string, or empty string if no author found.
	 */
	public static function get_id( ?array $args = null ): string {

		$author_id = static::get_author_id_from_args( $args );

		if ( empty( $author_id ) ) {
			return '';
		}

		return Meta\URI::get_bare_front_page_url()
			. '#/schema/' . current( (array) static::$type ) . '/' . \wp_hash( "better_seo+{$author_id}" );
	}

	/**
	 * Builds and returns the Schema.org Person entity for the author.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return array<string, mixed>|null The Person entity array, or null if no author found.
	 */
	public static function build( ?array $args = null ): ?array {

		$author_id = static::get_author_id_from_args( $args );

		if ( empty( $author_id ) ) {
			return null;
		}

		$user_data = Data\User::get_userdata( $author_id );
		$user_meta = Data\Plugin\User::get_meta( $author_id );

		$entity = [
			'@type' => static::$type,
			'@id'   => static::get_id( [ 'uid' => $author_id ] ),
			'name'  => $user_data->display_name ?? '', // May be empty in edge cases.
		];

		if ( $user_meta['facebook_page'] ) {
			$entity['sameAs'][] = \sanitize_url( $user_meta['facebook_page'], [ 'https', 'http' ] );
		}

		if ( $user_meta['twitter_page'] ) {
			$entity['sameAs'][] = \sanitize_url( 'https://twitter.com/' . ltrim( $user_meta['twitter_page'], '@' ) );
		}

		if ( ! empty( $user_data->description ) ) {
			// Clamp to 250 chars per Schema.org Person description recommendations.
			$entity['description'] = Strings::clamp_sentence(
				\wp_strip_all_tags( $user_data->description ),
				1,
				250,
			);
		}

		return $entity;
	}
}