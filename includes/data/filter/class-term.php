<?php
/**
 * Better SEO - Data Filter Term
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
 * Class Better_SEO\Data\Filter\Term
 *
 * Filters and sanitizes term meta values before they are saved to the database.
 * Unknown keys are removed to prevent storage of unsupported meta.
 * Empty registered metadata keys are preserved (not unset) to avoid overriding defaults.
 *
 * @since 1.0.0
 */
final class Term {

	/**
	 * Filters and sanitizes a term meta array before saving.
	 *
	 * Sanitizes known keys and removes any unrecognized keys.
	 * Returns an empty array if the input is empty or not an array.
	 *
	 * Note: Empty values for registered keys are preserved intentionally —
	 * unsetting them would cause Data\Plugin\Term::get_meta() defaults to override them.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $meta_value The raw term meta array to filter.
	 * @return array<string, mixed> The sanitized term meta array.
	 */
	public static function filter_meta_update( array $meta_value ): array {

		// Store an empty array on failure — Data\Plugin\Term::get_meta() repopulates on demand.
		if ( empty( $meta_value ) || ! \is_array( $meta_value ) ) {
			return [];
		}

		// If registered metadata yields empty — do not unset key! It would override defaults.
		foreach ( $meta_value as $key => &$value ) {
			switch ( $key ) {
				case 'doctitle':
				case 'og_title':
				case 'tw_title':
				case 'description':
				case 'og_description':
				case 'tw_description':
					$value = Sanitize::metadata_content( $value );
					break;

				case 'canonical':
				case 'social_image_url':
					$value = \sanitize_url( $value, [ 'https', 'http' ] );
					break;

				case 'social_image_id':
					// Bound to social_image_url — reset to 0 if URL is empty.
					$value = empty( $meta_value['social_image_url'] ) ? 0 : \absint( $value );
					break;

				case 'noindex':
				case 'nofollow':
				case 'noarchive':
					$value = Sanitize::qubit( $value );
					break;

				case 'redirect':
					$value = Sanitize::redirect_url( $value );
					break;

				case 'title_no_blog_name':
					$value = Sanitize::boolean_integer( $value );
					break;

				case 'tw_card_type':
					if ( ! \in_array( $value, Meta\Twitter::get_supported_cards(), true ) ) {
						$value = ''; // Reset to default.
					}
					break;

				default:
					// Remove unrecognized keys to prevent unsupported meta storage.
					unset( $meta_value[ $key ] );
			}
		}

		// Store an empty array on failure — Data\Plugin\Term::get_meta() repopulates on demand.
		return $meta_value ?: [];
	}
}