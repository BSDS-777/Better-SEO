<?php
/**
 * Better SEO - Admin Lists Post States
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Admin\Lists
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

namespace Better_SEO\Admin\Lists;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\Data;

/**
 * Class Better_SEO\Admin\Lists\PostStates
 *
 * Appends Better SEO-specific post states to the post list table,
 * such as "No Search" and "No Archive" visibility indicators.
 *
 * @since 1.0.0
 * @access protected
 *         Use better_seo()->admin() instead.
 */
final class PostStates {

	/**
	 * Appends Better SEO post states to the post list table display_post_states filter.
	 *
	 * Adds "No Search" state when a post is excluded from local search,
	 * and "No Archive" state when a post is excluded from archive queries.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $post_states Existing post states for the post.
	 * @param \WP_Post           $post        The current post object.
	 * @return array<int, string> Modified post states array.
	 */
	public static function add_post_state( array $post_states, \WP_Post $post ): array {

		if (
			Data\Plugin::get_option( 'alter_search_query' )
			&& Data\Plugin\Post::get_meta_item( 'exclude_local_search', $post->ID )
		) {
			$post_states[] = \esc_html__( 'No Search', 'better-seo' );
		}

		if (
			Data\Plugin::get_option( 'alter_archive_query' )
			&& Data\Plugin\Post::get_meta_item( 'exclude_from_archive', $post->ID )
		) {
			$post_states[] = \esc_html__( 'No Archive', 'better-seo' );
		}

		return $post_states;
	}
}