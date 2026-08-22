<?php
/**
 * Better SEO
 *
 * @package BetterSEO
 * @author  Brian Smith
 * @license GPL-2.0-or-later
 */

declare( strict_types=1 );

namespace Better_SEO\Traits;

\defined( 'BETTER_SEO_PRESENT' ) or exit;

/**
 * Provides static cache refresh utilities for option-backed classes.
 *
 * @since 1.0.0
 */
trait Property_Refresher {

	/**
	 * Flushes known static memoized properties on the called class.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public static function flush_cache(): void {
		if ( \property_exists( static::class, 'options_memo' ) ) {
			static::$options_memo = null;
		}

		if ( \property_exists( static::class, 'defaults_memo' ) ) {
			static::$defaults_memo = null;
		}
	}

	/**
	 * Registers automatic cache refresh hooks for option updates.
	 *
	 * @since 1.0.0
	 * @param string $option_name WordPress option key to watch.
	 * @return void
	 * @hook updated_option
	 * @hook added_option
	 * @hook deleted_option
	 */
	public static function register_option_refresh_hooks( string $option_name ): void {
		$refresh = static function ( string $changed_option ): void {
			if ( $changed_option !== $option_name ) {
				return;
			}

			static::flush_cache();
		};

		\add_action(
			'updated_option',
			static function ( string $changed_option ): void use ( $refresh ) {
				$refresh( $changed_option );
			},
			10,
			1
		);

		\add_action(
			'added_option',
			static function ( string $changed_option ): void use ( $refresh ) {
				$refresh( $changed_option );
			},
			10,
			1
		);

		\add_action(
			'deleted_option',
			static function ( string $changed_option ): void use ( $refresh ) {
				$refresh( $changed_option );
			},
			10,
			1
		);
	}
}
