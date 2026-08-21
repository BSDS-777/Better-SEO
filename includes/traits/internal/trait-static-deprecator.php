<?php
/**
 * Better SEO - Trait: Static Deprecator
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Traits\Internal
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

namespace Better_SEO\Traits\Internal;

\defined( 'BETTER_SEO_PRESENT' ) or die;

/**
 * Trait Better_SEO\Traits\Internal\Static_Deprecator
 *
 * Provides magic method interception for deprecated properties and methods
 * on Better SEO pool objects. Classes using this trait must define:
 *
 * - `$colloquial_handle` (string): A human-readable name for the pool object
 *   (e.g. 'better_seo()->admin()') used in deprecation notices.
 * - `$deprecated_methods` (array): Map of deprecated method names to their
 *   deprecation metadata (since, alternative, fallback).
 * - `$deprecated_properties` (array): Map of deprecated property names to their
 *   deprecation metadata (since, alternative, fallback).
 *
 * @since 1.0.0
 */
trait Static_Deprecator {

	/**
	 * Subpool storage for nested pool objects.
	 *
	 * @since 1.0.0
	 * @var   array<string, object>
	 */
	private static array $subpool = [];

	/**
	 * Intercepts writes to inaccessible or deprecated properties.
	 *
	 * If the property is registered as deprecated, fires a deprecation notice.
	 * If the property is unknown, fires an inaccessible notice and attempts
	 * to set the value on the static property if it exists on the class.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name  The property name being set.
	 * @param mixed  $value The value being assigned.
	 * @return void
	 */
	final public function __set( string $name, mixed $value ): void {

		$deprecated = $this->deprecated_properties[ $name ] ?? '';

		if ( $deprecated ) {
			$alternative = $deprecated['alternative'] ?? '';
			$since       = $deprecated['since'] ?? '';

			better_seo()->_inaccessible_p_or_m(
				"\${$name}",
				trim(
					\sprintf(
						'%s;%s',
						$since       ? "Since {$since} of Better SEO" : '',
						$alternative ? " Use {$alternative} instead"  : '',
					),
					'; ',
				),
				$this->colloquial_handle,
			);
		} else {
			// Unknown property — fire inaccessible notice and attempt static assignment.
			better_seo()->_inaccessible_p_or_m( "\${$name}", 'unknown' );

			if ( property_exists( static::class, $name ) ) {
				static::$$name = $value;
			}
		}
	}

	/**
	 * Intercepts reads from inaccessible or deprecated properties.
	 *
	 * If the property is registered as deprecated, fires a deprecation notice
	 * and returns the fallback value if one is defined.
	 * If the property is unknown, fires an inaccessible notice.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name The property name being accessed.
	 * @return mixed The fallback value if defined, or null.
	 */
	final public function __get( string $name ): mixed {

		$deprecated = $this->deprecated_properties[ $name ] ?? '';

		if ( $deprecated ) {
			$alternative = $deprecated['alternative'] ?? '';
			$since       = $deprecated['since'] ?? '';

			better_seo()->_inaccessible_p_or_m(
				"\${$name}",
				trim(
					\sprintf(
						'%s;%s',
						$since       ? "Since {$since} of Better SEO" : '',
						$alternative ? " Use {$alternative} instead"  : '',
					),
					'; ',
				),
				$this->colloquial_handle,
			);

			if ( $deprecated['fallback'] ) {
				return $deprecated['fallback'];
			}
		} else {
			better_seo()->_inaccessible_p_or_m( "\${$name}" );
		}

		return null;
	}

	/**
	 * Intercepts calls to inaccessible or deprecated instance methods.
	 *
	 * If the method is registered as deprecated, fires a deprecation notice
	 * and calls the fallback callable if one is defined.
	 * If the method is unknown, fires an inaccessible notice.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $name      The method name being called.
	 * @param array<int, mixed>    $arguments The arguments passed to the method.
	 * @return mixed The fallback callable's return value, or null.
	 */
	final public function __call( string $name, array $arguments ): mixed {

		$deprecated = $this->deprecated_methods[ $name ] ?? '';

		if ( $deprecated ) {
			better_seo()->_deprecated_function(
				\esc_html( "{$this->colloquial_handle}->{$name}()" ),
				\esc_html( $deprecated['since'] ?? '' ),
				! empty( $deprecated['alternative'] )
					? \esc_html( $deprecated['alternative'] )
					: null,
			);

			$fallback = $deprecated['fallback'] ?? null;

			if ( $fallback ) {
				return \call_user_func_array( $fallback, $arguments );
			}
		} else {
			better_seo()->_inaccessible_p_or_m( "{$this->colloquial_handle}->{$name}()" );
		}

		return null;
	}

	/**
	 * Intercepts static calls to pool objects.
	 *
	 * Pool objects must never be called statically — this method fires an
	 * inaccessible notice to warn developers of incorrect usage.
	 *
	 * @since 1.0.0
	 *
	 * @param string            $name      The static method name being called.
	 * @param array<int, mixed> $arguments The arguments passed to the method.
	 * @return void
	 */
	final public static function __callStatic( string $name, array $arguments ): void { // phpcs:ignore Generic.CodeAnalysis -- intentional catch-all.
		better_seo()->_inaccessible_p_or_m(
			\esc_html( "{$name}()" ),
			'Method unknown pool. Do not call pool statically! A fatal error may occur.',
		);
	}
}