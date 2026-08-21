<?php
/**
 * Better SEO - Meta Schema Entities BreadcrumbList
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

use Better_SEO\{
	Data\Filter\Sanitize,
	Meta,
};

/**
 * Class Better_SEO\Meta\Schema\Entities\BreadcrumbList
 *
 * Generates the Schema.org BreadcrumbList entity for the current page.
 * The last breadcrumb item has its 'item' URL removed per Schema.org spec
 * (the current page does not need a URL in the final breadcrumb).
 *
 * @since 1.0.0
 */
final class BreadcrumbList extends Reference {

	/**
	 * The Schema.org @type for this entity.
	 *
	 * @since 1.0.0
	 * @var   string
	 */
	public static string|array|null $type = 'BreadcrumbList';

	/**
	 * Builds and returns the Schema.org BreadcrumbList entity.
	 *
	 * Returns null if no breadcrumb items are available.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return array<string, mixed>|null The BreadcrumbList entity array, or null if empty.
	 */
	public static function build( ?array $args = null ): ?array {

		$list = Meta\Breadcrumbs::get_breadcrumb_list( $args );

		$list_items = [];

		foreach ( $list as $i => $item ) {
			$list_items[] = [
				'@type'    => 'ListItem',
				'position' => $i + 1, // Positions start at 1, not 0.
				'item'     => \sanitize_url( $item['url'] ),
				'name'     => Sanitize::metadata_content( $item['name'] ),
			];
		}

		if ( empty( $list_items ) ) {
			return null;
		}

		// Remove 'item' URL from the last breadcrumb — current page per Schema.org spec.
		unset( $list_items[ array_key_last( $list_items ) ]['item'] );

		return [
			'@type'           => static::$type,
			'@id'             => static::get_id(),
			'itemListElement' => $list_items,
		];
	}
}