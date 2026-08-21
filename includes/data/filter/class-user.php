<?php
/**
 * Better SEO - Data Filter User
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

/**
 * Class Better_SEO\Data\Filter\User
 *
 * Filters and sanitizes user meta values before they are saved to the database.
 * Unknown keys are removed to prevent storage of unsupported meta.
 * Empty registered metadata keys are preserved (not unset) to avoid overriding defaults.
 *
 * @since 1.0.0
 */
final class User {

	/**
	 * Filters and sanitizes a user meta array before saving.
	 *
	 * Sanitizes known keys and removes any unrecognized keys.
	 * Returns an empty array if the input yields no valid data.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $meta_value The raw user meta array to filter.
	 * @return array<string, mixed> The sanitized user meta array.
	 */
	public static function filter_meta_update( array $meta_value ): array {

		// If registered metadata yields empty — do not unset key! It would override defaults.
		foreach ( $meta_value as $key => &$value ) {
			switch ( $key ) {
				case 'facebook_page':
					$value = Sanitize::facebook_profile_link( $value );
					break;

				case 'twitter_page':
					$value = Sanitize::twitter_profile_handle( $value );
					break;

				case 'counter_type':
					// User preference — clamp to valid range 0–3.
					$value = \absint( $value );

					if ( $value > 3 ) {
						$value = 0;
					}
					break;

				default:
					// Remove unrecognized keys to prevent unsupported meta storage.
					unset( $meta_value[ $key ] );
			}
		}

		// Store an empty array on failure — Data\Plugin\User::get_meta() repopulates on demand.
		return $meta_value ?: [];
	}
}