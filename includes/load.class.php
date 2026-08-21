<?php
declare( strict_types=1 );

/**
 * Better SEO
 *
 * Public API facade.
 *
 * Copyright (C) 2026 Brian Smith
 *
 * Licensed under the GNU General Public License v2.0.
 *
 * This program is free software; you may redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2 as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 *
 * For more information, see:
 * https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Better_SEO
 * @since   1.0.0
 */

namespace Better_SEO;

defined( 'BETTER_SEO_PRESENT' ) || exit;

use Better_SEO\Data;
use function Better_SEO\is_headless;

/**
 * Public API facade for Better SEO.
 *
 * This class serves as the primary access point to Better SEO's
 * public API. Third-party integrations should access plugin
 * functionality through the `better_seo()` helper function
 * instead of interacting directly with internal classes.
 *
 * @since 1.0.0
 */
final class Load extends Pool {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @var static|null
	 */
	private static ?self $instance = null;

	/**
	 * Returns the singleton instance.
	 *
	 * Creates the instance on first access and returns the
	 * existing instance thereafter.
	 *
	 * @since 1.0.0
	 *
	 * @return static
	 */
	public static function get_instance(): static {
		return static::$instance ??= new static();
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	protected function __construct() {}

	/**
	 * Handles inaccessible property assignments.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name  Property name.
	 * @param mixed  $value Property value.
	 *
	 * @return void
	 */
	public function __set( string $name, mixed $value ): void {

		$this->_inaccessible_p_or_m(
			"better_seo()->{$name}",
			'unknown'
		);

		if ( property_exists( $this, $name ) ) {
			$this->$name = $value;
		}
	}

	/**
	 * Handles inaccessible property access.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Property name.
	 *
	 * @return void
	 */
	public function __get( string $name ): void {

		$this->_inaccessible_p_or_m(
			"better_seo()->{$name}",
			'unknown'
		);
	}

	/**
	 * Handles inaccessible method calls.
	 *
	 * Routes legacy method calls through the deprecated
	 * compatibility layer when available.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name      Method name.
	 * @param array  $arguments Method arguments.
	 *
	 * @return mixed
	 */
	public function __call( string $name, array $arguments ) {

		static $deprecated;

		$deprecated ??= new Internal\Deprecated();

		if ( is_callable( [ $deprecated, $name ] ) ) {
			return call_user_func_array(
				[ $deprecated, $name ],
				$arguments
			);
		}

		$this->_inaccessible_p_or_m(
			"better_seo()->{$name}()"
		);
	}

	/**
	 * Wrapper for deprecated-function notices.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $function    Function name.
	 * @param string      $version     Version deprecated.
	 * @param string|null $replacement Replacement function.
	 *
	 * @return void
	 */
	public function _deprecated_function(
		string $function,
		string $version,
		?string $replacement = null
	): void {
		Internal\Debug::_deprecated_function(
			$function,
			$version,
			$replacement
		);
	}

	/**
	 * Wrapper for doing-it-wrong notices.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $function Function name.
	 * @param string      $message  Notice message.
	 * @param string|null $version  Version introduced.
	 *
	 * @return void
	 */
	public function _doing_it_wrong(
		string $function,
		string $message,
		?string $version = null
	): void {
		Internal\Debug::_doing_it_wrong(
			$function,
			$message,
			$version
		);
	}

	/**
	 * Reports inaccessible properties or methods.
	 *
	 * @since 1.0.0
	 *
	 * @param string $item    Property or method name.
	 * @param string $message Optional message.
	 * @param string $handle  API handle.
	 *
	 * @return void
	 */
	public function _inaccessible_p_or_m(
		string $item,
		string $message = '',
		string $handle = 'better_seo()'
	): void {
		Internal\Debug::_inaccessible_p_or_m(
			$item,
			$message,
			$handle
		);
	}
}