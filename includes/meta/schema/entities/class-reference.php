<?php
/**
 * Better SEO - Meta Schema Entities Reference
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Meta\Schema\Entities
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

namespace Better_SEO\Meta\Schema\Entities;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\Meta;

/**
 * Abstract Class Better_SEO\Meta\Schema\Entities\Reference
 *
 * Base class for Better SEO Schema.org entity references.
 * Provides shared ID generation, instant/dynamic reference creation,
 * and entity writer registration for the Schema graph.
 *
 * Subclasses must implement build() to return their entity array.
 * Dynamic references are deduplicated — entities referenced more than once
 * are written to the graph once and replaced with @id references elsewhere.
 *
 * @since 1.0.0
 */
abstract class Reference {

	/**
	 * The Schema.org @type for this entity.
	 *
	 * May be a string or array of strings for multi-type entities.
	 *
	 * @since 1.0.0
	 * @var   string|array<int, string>|null
	 */
	public static string|array|null $type = null;

	/**
	 * Registered entity references keyed by @id.
	 *
	 * @since 1.0.0
	 * @var   array<string, array<string, mixed>>
	 */
	public static array $references = [];

	/**
	 * Returns the Schema.org @id for this entity.
	 *
	 * Default implementation builds the ID from the front page URL and type.
	 * Subclasses may override for custom ID generation.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return string The entity @id string.
	 */
	public static function get_id( ?array $args = null ): string {
		return Meta\URI::get_bare_front_page_url() . '#/schema/' . current( (array) static::$type );
	}

	/**
	 * Returns an instant (non-dynamic) @id reference array for this entity.
	 *
	 * Use for single-use references that do not need deduplication.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return array<string, string> The ['@id' => string] reference array.
	 */
	public static function get_instant_ref( ?array $args = null ): array {
		return [ '@id' => static::get_id( $args ) ];
	}

	/**
	 * Returns a dynamic reference to this entity's data array.
	 *
	 * Registers the entity writer for deduplication. If the entity is referenced
	 * more than once, it will be written to the graph once and replaced with
	 * @id references elsewhere.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return array<string, mixed> Reference to the entity data array.
	 */
	public static function &get_dynamic_ref( ?array $args = null ): array {

		$id = static::get_id( $args );

		Meta\Schema::register_entity_writer(
			$id,
			[ __CLASS__, 'write' ],
		);

		if ( empty( static::$references[ $id ] ) ) {
			static::$references[ $id ] = [
				'entity'   => static::build( $args ),
				'referred' => 1,
			];
		} else {
			++static::$references[ $id ]['referred'];
		}

		return static::$references[ $id ]['entity'];
	}

	/**
	 * Builds and returns the Schema.org entity array for this reference type.
	 *
	 * Must be implemented by all concrete subclasses.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return array<string, mixed>|null The entity array, or null if not applicable.
	 */
	abstract public static function build( ?array $args = null ): ?array;

	/**
	 * Writes deduplicated entity references to the Schema graph.
	 *
	 * Entities referenced more than once are yielded to the graph and
	 * their stored data is replaced with a simple @id reference.
	 * Clears the references store after writing.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields entity arrays for multiply-referenced entities.
	 */
	public static function write(): \Generator {

		$refs = &static::$references;

		foreach ( $refs as $id => $data ) {
			if ( $data['referred'] > 1 ) {
				yield $data['entity'];

				$data['entity'] = [ '@id' => $id ];
			}
		}

		$refs = [];
	}
}