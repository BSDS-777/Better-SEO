<?php
/**
 * Better SEO - Admin Script Utils
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Admin\Script
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

namespace Better_SEO\Admin\Script;

\defined( 'BETTER_SEO_PRESENT' ) or die;

/**
 * Class Better_SEO\Admin\Script\Utils
 *
 * Provides shared utility methods for admin script handling,
 * including HTML entity decoding and AJAX capability nonce management.
 *
 * @since 1.0.0
 */
final class Utils {

	/**
	 * Decodes HTML entities in a string value.
	 *
	 * Returns the decoded string if the value is a non-empty string.
	 * Returns the original value unchanged for all other types.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The value to decode.
	 * @return mixed The decoded string, or the original value if not a non-empty string.
	 */
	public static function decode_entities( mixed $value ): mixed {
		return \is_string( $value ) && \strlen( $value )
			? html_entity_decode( $value, \ENT_QUOTES, 'UTF-8' )
			: $value;
	}

	/**
	 * Decodes HTML entities in a scalar value or all string values within an array.
	 *
	 * For scalar values, delegates to decode_entities().
	 * For arrays, decodes each element in place via reference.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $values A scalar value or an array of values to decode.
	 * @return mixed The decoded scalar, or the array with decoded string values.
	 */
	public static function decode_all_entities( mixed $values ): mixed {

		if ( \is_scalar( $values ) ) {
			return self::decode_entities( $values );
		}

		foreach ( $values as &$v ) {
			$v = self::decode_entities( $v );
		}

		return $values;
	}

	/**
	 * Creates a Better SEO AJAX capability nonce for the given capability.
	 *
	 * Returns an empty string if the current user does not have the capability.
	 *
	 * @since 1.0.0
	 *
	 * @param string $capability The WordPress capability to check (e.g. 'edit_posts').
	 * @param mixed  ...$args    Optional additional arguments passed to current_user_can().
	 * @return string The generated nonce string, or empty string if capability check fails.
	 */
	public static function create_ajax_capability_nonce( string $capability, mixed ...$args ): string {
		return \current_user_can( $capability, ...$args )
			? \wp_create_nonce( "better-seo-ajax-{$capability}" )
			: '';
	}

	/**
	 * Verifies a Better SEO AJAX capability nonce and checks user capability.
	 *
	 * Calls wp_die() with a 403 response if the capability check fails.
	 * Calls check_ajax_referer() with die=true if the capability check passes.
	 *
	 * @since 1.0.0
	 *
	 * @param string $capability The WordPress capability to check (e.g. 'edit_posts').
	 * @param mixed  ...$args    Optional additional arguments passed to current_user_can().
	 * @return void
	 */
	public static function check_ajax_capability_referer( string $capability, mixed ...$args ): void {

		if ( \current_user_can( $capability, ...$args ) ) {
			\check_ajax_referer( "better-seo-ajax-{$capability}", 'nonce', true );
			return;
		}

		\wp_die( -1, '', [ 'response' => 403 ] );
	}
}