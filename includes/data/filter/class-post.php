<?php
/**
 * Better SEO - Data Filter Post
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Data\Filter
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

namespace Better_SEO\Data\Filter;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\Meta;

/**
 * Class Better_SEO\Data\Filter\Post
 *
 * Filters and sanitizes post meta values before they are saved to the database.
 * Unknown keys are removed to prevent storage of unsupported meta.
 *
 * Note: The original file had incorrect @package (Filter\Term) and @subpackage (Data\Term).
 * Both have been corrected to reflect this class's actual identity as Filter\Post.
 *
 * @since 1.0.0
 */
final class Post {

	/**
	 * Filters and sanitizes a post meta array before saving.
	 *
	 * Sanitizes known keys and removes any unrecognized keys.
	 * Returns an empty array if the input is empty.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $meta_value The raw post meta array to filter.
	 * @return array<string, mixed> The sanitized post meta array.
	 */
	public static function filter_meta_update( array $meta_value ): array {

		if ( empty( $meta_value ) ) {
			return [];
		}

		foreach ( $meta_value as $key => &$value ) {
			switch ( $key ) {
				case '_genesis_title':
				case '_open_graph_title':
				case '_twitter_title':
				case '_genesis_description':
				case '_open_graph_description':
				case '_twitter_description':
					$value = Sanitize::metadata_content( $value );
					break;

				case '_genesis_canonical_uri':
				case '_social_image_url':
					$value = \sanitize_url( $value, [ 'https', 'http' ] );
					break;

				case '_social_image_id':
					// Bound to _social_image_url — reset to 0 if URL is empty.
					$value = empty( $meta_value['_social_image_url'] ) ? 0 : \absint( $value );
					break;

				case '_genesis_noindex':
				case '_genesis_nofollow':
				case '_genesis_noarchive':
					$value = Sanitize::qubit( $value );
					break;

				case 'redirect':
					$value = Sanitize::redirect_url( $value );
					break;

				case '_better_seo_title_no_blogname':
				case 'exclude_local_search':
				case 'exclude_from_archive':
					$value = Sanitize::boolean_integer( $value );
					break;

				case '_better_seo_twitter_card_type':
					if ( ! \in_array( $value, Meta\Twitter::get_supported_cards(), true ) ) {
						$value = ''; // Reset to default.
					}
					break;

				default:
					// Remove unrecognized keys to prevent unsupported meta storage.
					unset( $meta_value[ $key ] );
			}
		}

		return $meta_value;
	}
}