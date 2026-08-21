<?php
/**
 * Better SEO - Front Meta Generator Schema
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Front\Meta\Generator
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

namespace Better_SEO\Front\Meta\Generator;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\{
	Data\Filter\Escape,
	Meta,
};

/**
 * Class Better_SEO\Front\Meta\Generator\Schema
 *
 * Generates the JSON-LD Schema.org graph script tag for the current page.
 *
 * @since 1.0.0
 */
final class Schema {

	/**
	 * Registered generator callbacks for this pool.
	 *
	 * @since 1.0.0
	 * @var   array<int, array{0: class-string, 1: string}>
	 */
	public const GENERATORS = [
		[ __CLASS__, 'generate_schema_graph' ],
	];

	/**
	 * Generates the JSON-LD Schema.org graph script tag.
	 *
	 * Outputs a script tag with type application/ld+json containing the
	 * structured data graph. Uses JSON_PRETTY_PRINT when SCRIPT_DEBUG is enabled.
	 * Yields nothing if no graph data or encoded content is available.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the schema:graph script tag data.
	 */
	public static function generate_schema_graph(): \Generator {

		$graph = Meta\Schema::get_generated_graph();

		if ( $graph ) {
			$content = Escape::json_encode_script(
				$graph,
				\SCRIPT_DEBUG ? \JSON_PRETTY_PRINT : 0,
			);

			if ( $content ) {
				yield 'schema:graph' => [
					'attributes' => [
						'type' => 'application/ld+json',
					],
					'tag'        => 'script',
					'content'    => [
						'content' => $content,
						'escape'  => false, // Already encoded via json_encode_script().
					],
				];
			}
		}
	}
}