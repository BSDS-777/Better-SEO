<?php
/**
 * Better SEO - Load
 *
 * @package    Better_SEO
 * @subpackage Better_SEO
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

namespace Better_SEO;

\defined( 'BETTER_SEO_PRESENT' ) or exit;

use Better_SEO\Data;
use function Better_SEO\is_headless;

/**
 * Class Better_SEO\Load
 *
 * Public API facade and singleton entry point for Better SEO.
 *
 * Extends Pool to expose the full proxied object pool API, and adds
 * magic method handling for inaccessible properties and deprecated calls.
 * Third-party integrations should access plugin functionality through the
 * `better_seo()` helper function rather than interacting with internal
 * classes directly.
 *
 * @since 1.0.0
 * @see   Better_SEO\Pool
 * @see   Better_SEO\Legacy_API
 * @link  https://en.wikipedia.org/wiki/Facade_pattern
 */
final class Load extends Pool {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var   static|null
	 */
	private static ?self $instance = null;

	/**
	 * Returns the singleton instance, creating it on first call.
	 *
	 * @since 1.0.0
	 *
	 * @return static The singleton instance.
	 */
	public static function get_instance(): static {
		return static::$instance ??= new static();
	}

	/**
	 * Protected constructor — use get_instance() instead.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {}

	/**
	 * Handles inaccessible property assignments.
	 *
	 * Reports a doing-it-wrong notice and assigns the value if the property
	 * exists on the object.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name  The property name.
	 * @param mixed  $value The value to assign.
	 * @return void
	 */
	public function __set( string $name, mixed $value ): void {

		$this->_inaccessible_p_or_m(
			"better_seo()->{$name}",
			'unknown',
		);

		if ( property_exists( $this, $name ) ) {
			$this->$name = $value;
		}
	}

	/**
	 * Handles inaccessible property reads.
	 *
	 * Reports a doing-it-wrong notice.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name The property name.
	 * @return void
	 */
	public function __get( string $name ): void {
		$this->_inaccessible_p_or_m(
			"better_seo()->{$name}",
			'unknown',
		);
	}

	/**
	 * Handles inaccessible method calls.
	 *
	 * Routes legacy method calls through the deprecated compatibility layer
	 * when available. Reports a doing-it-wrong notice for unknown methods.
	 *
	 * @since 1.0.0
	 *
	 * @param string       $name      The method name.
	 * @param array<mixed> $arguments The method arguments.
	 * @return mixed The return value from the deprecated method, if available.
	 */
	public function __call( string $name, array $arguments ): mixed {

		static $deprecated;

		$deprecated ??= new Internal\Deprecated();

		if ( is_callable( [ $deprecated, $name ] ) ) {
			return call_user_func_array(
				[ $deprecated, $name ],
				$arguments,
			);
		}

		$this->_inaccessible_p_or_m( "better_seo()->{$name}()" );

		return null;
	}

	/**
	 * Reports a deprecated function notice via the debug layer.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $function    The deprecated function name.
	 * @param string      $version     The version in which it was deprecated.
	 * @param string|null $replacement Optional replacement function name.
	 * @return void
	 */
	public function _deprecated_function(
		string $function,
		string $version,
		?string $replacement = null,
	): void {
		Internal\Debug::_deprecated_function(
			$function,
			$version,
			$replacement,
		);
	}

	/**
	 * Reports a doing-it-wrong notice via the debug layer.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $function The function name.
	 * @param string      $message  The notice message.
	 * @param string|null $version  The version when the correct behavior was introduced.
	 * @return void
	 */
	public function _doing_it_wrong(
		string $function,
		string $message,
		?string $version = null,
	): void {
		Internal\Debug::_doing_it_wrong(
			$function,
			$message,
			$version,
		);
	}

	/**
	 * Reports an inaccessible property or method notice via the debug layer.
	 *
	 * @since 1.0.0
	 *
	 * @param string $item    The property or method name (e.g. 'better_seo()->foo').
	 * @param string $message Optional additional context message.
	 * @param string $handle  The API handle string for the notice. Default 'better_seo()'.
	 * @return void
	 */
	public function _inaccessible_p_or_m(
		string $item,
		string $message = '',
		string $handle  = 'better_seo()',
	): void {
		Internal\Debug::_inaccessible_p_or_m(
			$item,
			$message,
			$handle,
		);
	}
}