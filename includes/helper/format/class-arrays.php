<?php
/**
 * Better SEO - Helper Format Arrays
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Helper\Format
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

namespace Better_SEO\Helper\Format;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\memo;

/**
 * Class Better_SEO\Helper\Format\Arrays
 *
 * Provides array manipulation utilities for Better SEO, including
 * list flattening, scrubbing, recursive merging, and recursive diff.
 *
 * @since 1.0.0
 */
class Arrays {

	/**
	 * Recursively flattens a sequential (list) array into a single-level list.
	 *
	 * Only flattens sequential (non-associative) arrays. Associative arrays
	 * and non-array values are preserved as-is.
	 *
	 * @since 1.0.0
	 *
	 * @param array<mixed> $array The array to flatten.
	 * @return array<mixed> The flattened array.
	 */
	public static function flatten_list( array $array ): array {

		if ( empty( $array ) || array_values( $array ) !== $array ) {
			return $array;
		}

		$ret = [];

		foreach ( $array as $value ) {
			if ( \is_array( $value ) && [] !== $value && array_values( $value ) === $value ) {
				$ret = array_merge( $ret, self::flatten_list( $value ) );
			} else {
				array_push( $ret, $value );
			}
		}

		return $ret;
	}

	/**
	 * Removes empty values from an array, preserving 0 and '0'.
	 *
	 * For single-element sub-arrays, unwraps the value.
	 * For multi-element sub-arrays, recursively scrubs them.
	 *
	 * @since 1.0.0
	 *
	 * @param array<mixed> $array The array to scrub.
	 * @return array<mixed> The scrubbed array.
	 */
	public static function scrub( array $array ): array {

		foreach ( $array as $key => &$item ) {
			// Keep 0 and '0', but remove empty string, null, false, and [].
			if ( empty( $item ) && 0 !== $item && '0' !== $item ) {
				unset( $array[ $key ] );
			} elseif ( \is_array( $item ) ) {
				if ( isset( $item[0] ) && 1 === \count( $item ) ) {
					$item = reset( $item );
				} else {
					$item = self::scrub( $item );
				}
			}
		}

		return $array;
	}

	/**
	 * Recursively merges arrays, with later arrays overwriting earlier ones.
	 *
	 * Unlike array_merge_recursive(), this does not create sub-arrays for
	 * duplicate keys — later values overwrite earlier ones at each level.
	 *
	 * @since 1.0.0
	 *
	 * @param array<mixed> ...$arrays Two or more arrays to merge.
	 * @return array<mixed> The recursively merged array.
	 */
	public static function array_merge_recursive_distinct( array ...$arrays ): array {

		$i = \count( $arrays );

		while ( --$i ) {
			$p = $i - 1;

			foreach ( $arrays[ $i ] as $key => $value ) {
				$arrays[ $p ][ $key ] = isset( $arrays[ $p ][ $key ] ) && \is_array( $value )
					? self::array_merge_recursive_distinct( $arrays[ $p ][ $key ], $value )
					: $value;
			}
		}

		return $arrays[0];
	}

	/**
	 * Recursively computes the associative difference between arrays.
	 *
	 * Returns keys/values that differ between arrays, removing identical
	 * entries at each nesting level.
	 *
	 * @since 1.0.0
	 *
	 * @param array<mixed> ...$arrays Two or more arrays to diff.
	 * @return array<mixed> The recursive associative diff result.
	 */
	public static function array_diff_assoc_recursive( array ...$arrays ): array {

		$i = \count( $arrays );

		while ( --$i ) {
			$p = $i - 1;

			if ( \is_array( $arrays[ $i ] ) && \is_array( $arrays[ $p ] ) ) {
				foreach ( $arrays[ $i ] as $key => &$value ) {
					if ( ! \array_key_exists( $key, $arrays[ $p ] ) ) {
						// Value doesn't exist in previous array — pass it along.
						$arrays[ $p ][ $key ] = $value;
						continue;
					}

					if (
						$value === $arrays[ $p ][ $key ]
						|| ( \is_array( $value ) && ! self::array_diff_assoc_recursive( ...array_column( $arrays, $key ) ) )
					) {
						foreach ( range( $p, $i ) as $_i ) {
							if ( \is_array( $arrays[ $_i ] ) ) {
								unset( $arrays[ $_i ][ $key ] );
							}
						}

						continue;
					}
				}
			}
		}

		return $arrays[0];
	}
}
