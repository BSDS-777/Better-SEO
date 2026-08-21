<?php
/**
 * Better SEO - Data Plugin Helper
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

/**
 * Class Better_SEO\Data\Plugin\Helper
 *
 * Provides helper utilities for Better SEO plugin option key generation,
 * including robots option index key resolution for post types and taxonomies.
 *
 * @since 1.0.0
 */
class Helper {

	/**
	 * Returns the plugin option index key for a given robots field and type.
	 *
	 * Resolves the option array key used to store robots directives
	 * for post types and taxonomies in the plugin options.
	 *
	 * @since 1.0.0
	 *
	 * @param string $field The field type: 'post_type' or 'taxonomy'.
	 * @param string $type  The robots directive: 'noindex', 'nofollow', or 'noarchive'.
	 * @return string The resolved option index key, or empty string if field is unrecognized.
	 */
	public static function get_robots_option_index( string $field, string $type ): string {
		return match ( $field ) {
			'post_type' => "{$type}_post_types",
			'taxonomy'  => "{$type}_taxonomies",
			default     => '',
		};
	}
}

