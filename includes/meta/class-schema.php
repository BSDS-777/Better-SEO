<?php
/**
 * Better SEO - Meta Schema
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Meta
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

namespace Better_SEO\Meta;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\{
	get_query_type_from_args,
	normalize_generation_args,
};

use Better_SEO\{
	Data,
	Helper\Format\Arrays,
	Helper\Query,
};

/**
 * Class Better_SEO\Meta\Schema
 *
 * Provides JSON-LD Schema.org graph generation for Better SEO,
 * including entity builder registration and writer queue management.
 *
 * @since 1.0.0
 */
class Schema {

	/**
	 * Registered entity writer callbacks keyed by ID.
	 *
	 * @since 1.0.0
	 * @var   array<string, callable>
	 */
	private static array $writer_queue = [];

	/**
	 * Returns the generated Schema.org JSON-LD graph for the given args or current query context.
	 *
	 * Builds the graph from registered entity builders, applies writer queue callbacks,
	 * and applies the better_seo_schema_entity_builders, better_seo_schema_queued_graph_data,
	 * and better_seo_schema_graph_data filters.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return array<string, mixed> The Schema.org graph array, or empty array if disabled or empty.
	 */
	public static function get_generated_graph( ?array $args = null ): array {

		if ( ! Data\Plugin::get_option( 'ld_json_enabled' ) ) {
			return [];
		}

		if ( isset( $args ) ) {
			normalize_generation_args( $args );

			// Protected posts only output WebSite schema — no WebPage.
			if ( 'single' === get_query_type_from_args( $args ) && Data\Post::is_protected( $args['id'] ) ) {
				$primaries = [ 'WebSite' ];
			}
		} else {
			// Protected posts only output WebSite schema — no WebPage.
			if ( Query::is_singular() && Data\Post::is_protected() ) {
				$primaries = [ 'WebSite' ];
			}
		}

		$primaries ??= [ 'WebSite', 'WebPage' ];

		$builders_queue = [];

		foreach ( $primaries as $class ) {
			$builders_queue[] = ( "\\Better_SEO\\Meta\\Schema\\Entities\\{$class}" )::BUILDERS;
		}

		/**
		 * Filters the Better SEO schema entity builders.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, callable>      $entity_builders The merged entity builder callbacks.
		 * @param array<string, mixed>|null $args            The generation args, or null.
		 */
		$entity_builders = \apply_filters(
			'better_seo_schema_entity_builders',
			array_merge( ...$builders_queue ),
			$args,
		);

		$graph = [];

		foreach ( $entity_builders as $builder ) {
			$graph[] = \call_user_func( $builder, $args );
		}

		/**
		 * Filters the Better SEO schema queued graph data.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, array<string, mixed>> $graph The queued graph data array.
		 * @param array<string, mixed>|null        $args  The generation args, or null.
		 */
		$graph = \apply_filters(
			'better_seo_schema_queued_graph_data',
			$graph,
			$args,
		);

		foreach ( self::$writer_queue as $writer ) {
			foreach ( \call_user_func( $writer ) as $extra_graph ) {
				$graph[] = $extra_graph;
			}
		}

		// Clear the writer queue after processing.
		self::$writer_queue = [];

		/**
		 * Filters the Better SEO schema graph data.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, array<string, mixed>> $graph The final graph data array.
		 * @param array<string, mixed>|null        $args  The generation args, or null.
		 */
		$graph = \apply_filters(
			'better_seo_schema_graph_data',
			$graph,
			$args,
		);

		if ( empty( $graph ) ) {
			return [];
		}

		return [
			'@context' => 'https://schema.org',
			'@graph'   => Arrays::scrub( $graph ),
		];
	}

	/**
	 * Registers an entity writer callback to be called during graph generation.
	 *
	 * Writers are called after entity builders and can append additional graph entries.
	 * The writer queue is cleared after each call to get_generated_graph().
	 *
	 * @since 1.0.0
	 *
	 * @param string   $id       The unique writer ID.
	 * @param callable $callback The writer callback. Must return an iterable of graph entries.
	 * @return void
	 */
	public static function register_entity_writer( string $id, callable $callback ): void {
		self::$writer_queue[ $id ] = $callback;
	}
}