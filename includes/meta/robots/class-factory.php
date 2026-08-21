<?php
/**
 * Better SEO - Meta Robots Factory
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Meta\Robots
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

namespace Better_SEO\Meta\Robots;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\Data;

/**
 * Class Better_SEO\Meta\Robots\Factory
 *
 * Abstract base factory for Better SEO robots meta generators.
 * Provides the generator loop, copyright assertion, and shared state
 * for the Args (query-args-based) and Front (current-query-based) subclasses.
 *
 * The START and HALT constants act as generator protocol sentinels:
 * - START signals the generator is ready for the next directive.
 * - HALT signals the current directive has been resolved.
 *
 * @since 1.0.0
 */
class Factory {

	/**
	 * Generator protocol sentinel: signals the generator is ready for the next directive.
	 *
	 * Encoded as the binary representation of "Brian".
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	public const START = 0b0100001001110010011010010110000101101110;

	/**
	 * Generator protocol sentinel: signals the current directive has been resolved.
	 *
	 * Encoded as the binary representation of "Smith".
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	public const HALT = 0b0101001101101101011010010111010001101000;

	/**
	 * The current generation args.
	 *
	 * @since 1.0.0
	 * @var   array<string, mixed>|null
	 */
	protected static ?array $args = null;

	/**
	 * The current generation options bitmask.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	protected static int $options = 0;

	/**
	 * Sets the generation args and options for the current generation pass.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args    The generation args. Default null.
	 * @param int                       $options The options bitmask. Default 0.
	 * @return static The current instance for method chaining.
	 */
	public function set( ?array $args = null, int $options = 0 ): static {
		static::$args    = $args;
		static::$options = $options;
		return $this;
	}

	/**
	 * Returns the robots directive generator for the current factory instance.
	 *
	 * Yields START to signal readiness, then processes each sent directive
	 * ('noindex', 'nofollow', 'noarchive', 'max_snippet', etc.) by delegating
	 * to assert_no() or assert_copyright(). Yields HALT after each directive resolves.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator The robots directive generator.
	 */
	public static function generator(): \Generator {

		while ( true ) {
			switch ( $sender = yield static::START ) {
				case 'noindex':
				case 'nofollow':
				case 'noarchive':
					foreach ( static::assert_no( $sender ) as $key => $value ) {
						yield $key => $value;
						if ( $value ) {
							yield static::HALT;
							break;
						}
					}
					break;

				case 'max_snippet':
				case 'max_image_preview':
				case 'max_video_preview':
					yield from static::assert_copyright( $sender );
					yield static::HALT;
					break;

				default:
					better_seo()->_doing_it_wrong(
						__METHOD__,
						\sprintf( 'Unregistered robots-generator getter provided: <code>%s</code>.', \esc_html( $sender ) ),
						'1.0.0',
					);
					yield static::HALT;
			}
		}
	}

	/**
	 * Asserts the robots directive for the given type.
	 *
	 * Must be implemented by subclasses (Args and Front).
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The robots directive type ('noindex', 'nofollow', 'noarchive').
	 * @return \Generator Yields assertion key => value pairs.
	 */
	protected static function assert_no( string $type ): \Generator {
		yield from [];
	}

	/**
	 * Asserts the copyright directive for the given type.
	 *
	 * Yields the configured copyright directive value if copyright directives are enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type The copyright directive type ('max_snippet', 'max_image_preview', 'max_video_preview').
	 * @return \Generator Yields 'globals_copyright' => mixed.
	 */
	final protected static function assert_copyright( string $type ): \Generator {

		if ( 'max_snippet' === $type ) {
			$type = 'max_snippet_length';
		}

		if ( Data\Plugin::get_option( 'set_copyright_directives' ) ) {
			yield 'globals_copyright' => Data\Plugin::get_option( $type );
		}
	}
}