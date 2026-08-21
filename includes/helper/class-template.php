<?php
/**
 * Better SEO - Helper Template
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Helper
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

namespace Better_SEO\Helper;

\defined( 'BETTER_SEO_PRESENT' ) or die;

/**
 * Class Better_SEO\Helper\Template
 *
 * Provides secure view/template output utilities for Better SEO,
 * including path resolution, secret verification, and view inclusion.
 *
 * @since 1.0.0
 */
final class Template {

	/**
	 * The current view secret token used to verify view inclusion context.
	 *
	 * @since 1.0.0
	 * @var   string|null
	 */
	private static ?string $secret = null;

	/**
	 * Outputs a Better SEO view file by relative path within the views directory.
	 *
	 * Generates a unique secret token before inclusion to allow views to verify
	 * they are being included in the correct context via verify_secret().
	 * Will crash on PHP 8+ if the view path cannot be resolved — this is intentional.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file      The view file path relative to the views directory (without .php).
	 * @param mixed  ...$view_args Arguments passed to the view file scope.
	 * @return void
	 */
	public static function output_view( string $file, mixed ...$view_args ): void { // phpcs:ignore Generic.CodeAnalysis -- includes.
		$secret = self::$secret = uniqid( '', true );

		// Will crash on PHP 8+ if the view isn't resolved — intentional.
		require self::get_view_location( $file );
	}

	/**
	 * Outputs a view file by absolute path.
	 *
	 * Generates a unique secret token before inclusion to allow views to verify
	 * they are being included in the correct context via verify_secret().
	 *
	 * @since 1.0.0
	 *
	 * @param string $file      The absolute file path to the view.
	 * @param mixed  ...$view_args Arguments passed to the view file scope.
	 * @return void
	 */
	public static function output_absolute_view( string $file, mixed ...$view_args ): void { // phpcs:ignore Generic.CodeAnalysis -- includes.
		$secret = self::$secret = uniqid( '', true );

		require $file;
	}

	/**
	 * Returns the resolved absolute path for a view file within the views directory.
	 *
	 * Validates that the resolved path is within the views directory to prevent
	 * path traversal attacks. Returns null if the path is invalid or outside the views root.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file The view file path relative to the views directory (without .php).
	 * @return string|null The resolved absolute path, or null if invalid.
	 */
	public static function get_view_location( string $file ): ?string {

		static $realview;

		$realview ??= realpath( \BETTER_SEO_DIR_PATH_VIEWS );
		$path       = realpath( "{$realview}/{$file}.php" );

		if ( $path && str_starts_with( $path, $realview ) ) {
			return $path;
		}

		return null;
	}

	/**
	 * Returns whether the given value matches the current view secret token.
	 *
	 * Used by view files to verify they are being included via output_view()
	 * or output_absolute_view() rather than being accessed directly.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The value to verify against the current secret.
	 * @return bool True if the value matches the current secret token.
	 */
	public static function verify_secret( mixed $value ): bool {
		return isset( $value ) && self::$secret === $value;
	}
}