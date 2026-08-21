<?php
/**
 * Better SEO - Data Plugin Deprecated
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

use Better_SEO\Data;

/**
 * Class Better_SEO\Data\Plugin\Deprecated
 *
 * Handles retrieval of deprecated plugin option keys by mapping them
 * to their current equivalents via the deprecation map.
 *
 * @since 1.0.0
 */
final class Deprecated {

	/**
	 * Memoized deprecation map.
	 *
	 * @since 1.0.0
	 * @var   array<string, mixed>|null
	 */
	private static ?array $deprecation_map = null;

	/**
	 * Returns the value of a deprecated option by traversing the deprecation map.
	 *
	 * Maps the deprecated key to its current equivalent and retrieves the value
	 * from the active plugin options. Returns null if no mapping exists.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed ...$key The deprecated option key(s) to look up.
	 * @return mixed The mapped option value, or null if not found.
	 */
	public static function get_deprecated_option( mixed ...$key ): mixed {

		$map = self::$deprecation_map ??= self::get_deprecation_map();

		foreach ( $key as $k ) {
			$map = $map[ $k ] ?? null;
		}

		if ( empty( $map ) ) {
			return null;
		}

		$option = Data\Plugin::get_options();

		foreach ( (array) $map as $k ) {
			$option = $option[ $k ] ?? null;
		}

		return $option ?? null;
	}

	/**
	 * Returns the deprecation map, memoized.
	 *
	 * The map defines how deprecated option keys translate to current option keys.
	 * Returns an empty array by default — extend via subclass or filter as needed.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The deprecation map array.
	 */
	public static function get_deprecation_map(): array {
		return self::$deprecation_map ??= [];
	}
}
