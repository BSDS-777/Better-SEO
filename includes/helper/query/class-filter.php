<?php
/**
 * Better SEO - Helper Query Filter
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Helper\Query
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

namespace Better_SEO\Helper\Query;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\{
	Data,
	Helper\Query,
};

/**
 * Class Better_SEO\Helper\Query\Filter
 *
 * Provides WordPress filter callbacks for Better SEO query modifications,
 * including primary term resolution for post permalink category links.
 *
 * @since 1.0.0
 */
final class Filter {

	/**
	 * Filters the post link category to use the Better SEO primary term.
	 *
	 * Replaces the default category term used in post permalinks with the
	 * Better SEO primary term for the post, if one is set.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Term      $term  The current category term object.
	 * @param \WP_Term[]|null $terms All available terms for the post, or null.
	 * @param \WP_Post|null  $post  The current post object, or null.
	 * @return \WP_Term The primary term if set, or the original term.
	 */
	public static function filter_post_link_category( \WP_Term $term, ?array $terms = null, ?\WP_Post $post = null ): \WP_Term {
		return Data\Plugin\Post::get_primary_term(
			$post->ID ?? Query::get_the_real_id(),
			$term->taxonomy,
		) ?? $term;
	}
}