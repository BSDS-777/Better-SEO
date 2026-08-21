<?php
/**
 * Better SEO - Internal Silencer
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Internal
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

namespace Better_SEO\Internal;

\defined( 'BETTER_SEO_PRESENT' ) or die;

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- Magic methods intentionally accept and ignore all parameters.

/**
 * Class Better_SEO\Internal\Silencer
 *
 * A null-object singleton that silently absorbs any property access,
 * method call, or static call without throwing errors.
 *
 * Used as a safe fallback when the Better SEO facade is unavailable,
 * preventing fatal errors from calls to better_seo() in edge cases.
 *
 * @since 1.0.0
 */
final class Silencer {

	/**
	 * The singleton instance.
	 *
	 * @since 1.0.0
	 * @var   static|null
	 */
	private static ?self $instance = null;

	/**
	 * Constructor. Intentionally empty.
	 *
	 * @since 1.0.0
	 */
	public function __construct(): void {}

	/**
	 * Returns the singleton Silencer instance.
	 *
	 * @since 1.0.0
	 *
	 * @return static The singleton instance.
	 */
	public static function instance(): static {
		return self::$instance ??= new self();
	}

	/**
	 * Silently returns null for any property access.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name The property name.
	 * @return null Always null.
	 */
	public function __get( string $name ): null {
		return null;
	}

	/**
	 * Silently accepts and returns any property assignment.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name  The property name.
	 * @param mixed  $value The value being set.
	 * @return mixed The value passed in.
	 */
	public function __set( string $name, mixed $value ): mixed {
		return $value;
	}

	/**
	 * Silently returns false for any isset() check.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name The property name.
	 * @return bool Always false.
	 */
	public function __isset( string $name ): bool {
		return false;
	}

	/**
	 * Silently returns null for any instance method call.
	 *
	 * @since 1.0.0
	 *
	 * @param string       $name      The method name.
	 * @param array<mixed> $arguments The method arguments.
	 * @return null Always null.
	 */
	public function __call( string $name, array $arguments ): null {
		return null;
	}

	/**
	 * Silently returns null for any static method call.
	 *
	 * @since 1.0.0
	 *
	 * @param string       $name      The method name.
	 * @param array<mixed> $arguments The method arguments.
	 * @return null Always null.
	 */
	public static function __callStatic( string $name, array $arguments ): null {
		return null;
	}
}