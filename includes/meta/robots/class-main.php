<?php
/**
 * Better SEO - Meta Robots Main
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

use const Better_SEO\ROBOTS_ASSERT;

use function Better_SEO\umemo;

/**
 * Class Better_SEO\Meta\Robots\Main
 *
 * Orchestrates robots meta directive generation for Better SEO.
 * Manages the factory selection (Args vs Front), generator lifecycle,
 * and optional assertion collection for SEO Bar analysis.
 *
 * @since 1.0.0
 */
final class Main {

	/**
	 * The current generation args.
	 *
	 * @since 1.0.0
	 * @var   array<string, mixed>|null
	 */
	private ?array $args = null;

	/**
	 * The current generation options bitmask.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	private int $options = 0;

	/**
	 * The singleton instance.
	 *
	 * @since 1.0.0
	 * @var   static|null
	 */
	private static ?self $instance = null;

	/**
	 * Collected assertion data for SEO Bar analysis.
	 *
	 * @since 1.0.0
	 * @var   array<string, array<string, mixed>>
	 */
	private array $assertions = [];

	/**
	 * The list of supported robots directive getters.
	 *
	 * @since 1.0.0
	 * @var   array<int, string>
	 */
	private const GETTERS = [
		'noindex',
		'nofollow',
		'noarchive',
		'max_snippet',
		'max_image_preview',
		'max_video_preview',
	];

	/**
	 * Constructor. Private — use instance() to obtain the singleton.
	 *
	 * @since 1.0.0
	 */
	private function __construct(): void {}

	/**
	 * Returns the singleton Main instance.
	 *
	 * @since 1.0.0
	 *
	 * @return static The singleton instance.
	 */
	public static function instance(): static {
		return self::$instance ??= new self();
	}

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
		$this->args    = $args;
		$this->options = $options;
		return $this;
	}

	/**
	 * Returns the robots meta directives for the given getter list.
	 *
	 * Drives the factory generator, collecting results for each requested directive.
	 * When ROBOTS_ASSERT is set in options, also stores assertion data for SEO Bar.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string>|null $get Optional list of directives to retrieve. Default null (all).
	 * @return array<string, mixed> The robots directive results map.
	 */
	public function get( ?array $get = null ): array {

		$get = ( $get ?? false )
			? array_intersect( self::GETTERS, $get )
			: self::GETTERS;

		$options = $this->options;
		$assert  = (bool) ( $options & ROBOTS_ASSERT );

		if ( $assert ) {
			$this->reset_assertions();
		}

		$factory   = $this->get_factory();
		$halt      = $factory::HALT;
		$start     = $factory::START;
		$generator = $factory->set(
			$this->args,
			$options,
		)::generator();

		$results = [];

		foreach ( $get as $g ) {
			$generator->send( $g );

			do {
				if ( ( $r = $generator->current() ) === $halt ) {
					continue;
				}

				$results[ $g ] = $r;

				if ( $assert ) {
					$this->store_assertion( $g, $generator->key(), $r );
				}
			} while ( $start !== $generator->send( true ) );
		}

		return $results;
	}

	/**
	 * Returns the appropriate factory instance (Args or Front), memoized.
	 *
	 * Uses Args when generation args are set, Front for the current query context.
	 *
	 * @since 1.0.0
	 *
	 * @return Factory The factory instance.
	 */
	private function get_factory(): Factory {
		return umemo(
			__METHOD__,
			null,
			isset( $this->args ),
		)
		?? umemo(
			__METHOD__,
			isset( $this->args ) ? new Args() : new Front(),
			isset( $this->args ),
		);
	}

	/**
	 * Returns a reference to the assertions array for SEO Bar analysis.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>> Reference to the assertions array.
	 */
	public function &collect_assertions(): array {
		return $this->assertions;
	}

	/**
	 * Stores a single assertion result for SEO Bar analysis.
	 *
	 * @since 1.0.0
	 *
	 * @param string $get       The directive getter key (e.g. 'noindex').
	 * @param string $assertion The assertion key (e.g. 'globals_site').
	 * @param mixed  $result    The assertion result value.
	 * @return void
	 */
	private function store_assertion( string $get, string $assertion, mixed $result ): void {
		$this->collect_assertions()[ $get ][ $assertion ] = $result;
	}

	/**
	 * Resets the assertions array to empty.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function reset_assertions(): void {
		$assertions = &$this->collect_assertions();
		$assertions = [];
	}
}