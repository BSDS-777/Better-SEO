<?php
/**
 * Better SEO - Compatibility: bbPress
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Compatibility
 * @author     Brian Smith
 * @copyright  2026 Brian Smith
 * @license    GPL-2.0-or-later
 * @link       https://www.gnu.org/licenses/gpl-2.0.html
 * @access     private
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

namespace Better_SEO;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\{
	get_query_type_from_args,
	normalize_generation_args,
};

use Better_SEO\{
	Data,
	Data\Filter\Sanitize,
	Helper\Query,
};

\add_filter( 'bbp_title', [ Front\Title::class, 'set_document_title' ], 99 );

\add_filter( 'better_seo_title_from_generation',   __NAMESPACE__ . '\_bbpress_filter_title',                       10, 2 );
\add_filter( 'better_seo_seo_column_keys_order',   __NAMESPACE__ . '\_bbpress_filter_order_keys' );
\add_filter( 'better_seo_do_adjust_archive_query', __NAMESPACE__ . '\_bbpress_filter_do_adjust_query',             10, 2 );
\add_filter( 'better_seo_robots_meta_array',       __NAMESPACE__ . '\_bbpress_filter_robots',                      10, 2 );
\add_action( 'better_seo_seo_bar',                 __NAMESPACE__ . '\_assert_bbpress_noindex_defaults_seo_bar',    10, 2 );

/**
 * Filters the document title for bbPress pages.
 *
 * Returns the appropriate bbPress title for forums, topics, replies,
 * topic tags, user profiles, and search pages.
 *
 * @since 1.0.0
 *
 * @param string                    $title The current document title.
 * @param array<string, mixed>|null $args  The generation args, or null for current query.
 * @return string The filtered document title.
 */
function _bbpress_filter_title( string $title, mixed $args ): string {

	if (
		   isset( $args )
		|| ! \function_exists( 'is_bbpress' )
		|| ! \is_bbpress()
	) {
		return $title;
	}

	$new_title = [];

	if ( \bbp_is_forum_archive() ) {
		$new_title['text'] = \bbp_get_forum_archive_title();
	} elseif ( \bbp_is_topic_archive() ) {
		$new_title['text'] = \bbp_get_topic_archive_title();
	} elseif ( \bbp_is_forum_edit() ) {
		$new_title['text']   = \bbp_get_forum_title();
		$new_title['format'] = \esc_attr__( 'Forum Edit: %s', 'bbpress' );
	} elseif ( \bbp_is_topic_edit() ) {
		$new_title['text']   = \bbp_get_topic_title();
		$new_title['format'] = \esc_attr__( 'Topic Edit: %s', 'bbpress' );
	} elseif ( \bbp_is_reply_edit() ) {
		$new_title['text']   = \bbp_get_reply_title();
		$new_title['format'] = \esc_attr__( 'Reply Edit: %s', 'bbpress' );
	} elseif ( \bbp_is_topic_tag_edit() ) {
		$new_title['text']   = \bbp_get_topic_tag_name();
		$new_title['format'] = \esc_attr__( 'Topic Tag Edit: %s', 'bbpress' );
	} elseif ( \bbp_is_single_forum() ) {
		$new_title['text']   = \bbp_get_forum_title();
		$new_title['format'] = \esc_attr__( 'Forum: %s', 'bbpress' );
	} elseif ( \bbp_is_single_topic() ) {
		$new_title['text']   = \bbp_get_topic_title();
		$new_title['format'] = \esc_attr__( 'Topic: %s', 'bbpress' );
	} elseif ( \bbp_is_single_reply() ) {
		$new_title['text'] = \bbp_get_reply_title();
	} elseif ( \bbp_is_topic_tag() || \get_query_var( 'bbp_topic_tag' ) ) {
		$new_title['text']   = \bbp_get_topic_tag_name();
		$new_title['format'] = \esc_attr__( 'Topic Tag: %s', 'bbpress' );
	} elseif ( \bbp_is_single_user() ) {

		$is_user_home = \bbp_is_user_home();

		if ( \bbp_is_single_user_topics() ) {
			if ( true === $is_user_home ) {
				$new_title['text'] = \esc_attr__( 'Your Topics', 'bbpress' );
			} else {
				$new_title['text']   = Data\User::get_userdata( \bbp_get_user_id(), 'display_name' );
				$new_title['format'] = \esc_attr__( "%s's Topics", 'bbpress' );
			}
		} elseif ( \bbp_is_single_user_replies() ) {
			if ( true === $is_user_home ) {
				$new_title['text'] = \esc_attr__( 'Your Replies', 'bbpress' );
			} else {
				$new_title['text']   = Data\User::get_userdata( \bbp_get_user_id(), 'display_name' );
				$new_title['format'] = \esc_attr__( "%s's Replies", 'bbpress' );
			}
		} elseif ( \bbp_is_favorites() ) {
			if ( true === $is_user_home ) {
				$new_title['text'] = \esc_attr__( 'Your Favorites', 'bbpress' );
			} else {
				$new_title['text']   = Data\User::get_userdata( \bbp_get_user_id(), 'display_name' );
				$new_title['format'] = \esc_attr__( "%s's Favorites", 'bbpress' );
			}
		} elseif ( \bbp_is_subscriptions() ) {
			if ( true === $is_user_home ) {
				$new_title['text'] = \esc_attr__( 'Your Subscriptions', 'bbpress' );
			} else {
				$new_title['text']   = Data\User::get_userdata( \bbp_get_user_id(), 'display_name' );
				$new_title['format'] = \esc_attr__( "%s's Subscriptions", 'bbpress' );
			}
		} elseif ( true === $is_user_home ) {
			$new_title['text'] = \esc_attr__( 'Your Profile', 'bbpress' );
		} else {
			$new_title['text']   = Data\User::get_userdata( \bbp_get_user_id(), 'display_name' );
			$new_title['format'] = \esc_attr__( "%s's Profile", 'bbpress' );
		}
	} elseif ( \bbp_is_single_user_edit() ) {
		if ( \bbp_is_user_home_edit() ) {
			$new_title['text'] = \esc_attr__( 'Edit Your Profile', 'bbpress' );
		} else {
			$new_title['text']   = Data\User::get_userdata( \bbp_get_user_id(), 'display_name' );
			$new_title['format'] = \esc_attr__( "Edit %s's Profile", 'bbpress' );
		}
	} elseif ( \bbp_is_single_view() ) {
		$new_title['text']   = \bbp_get_view_title();
		$new_title['format'] = \esc_attr__( 'View: %s', 'bbpress' );
	} elseif ( \bbp_is_search() ) {
		$new_title['text'] = \bbp_get_search_title();
	}

	// Allow bbPress to filter the raw title array before formatting.
	$new_title = \apply_filters( 'bbp_raw_title_array', $new_title );

	// Merge with defaults — falls back to original $title if no bbPress title was found.
	$new_title = \bbp_parse_args(
		$new_title,
		[
			'text'   => $title,
			'format' => '%s',
		],
		'title',
	);

	$new_title = \sprintf( $new_title['format'], $new_title['text'] );

	$new_title = \apply_filters(
		'bbp_raw_title',
		$new_title,
		'&raquo;',
		'',
	);

	if ( $new_title === $title ) {
		return $title;
	}

	return $new_title;
}

/**
 * Filters the SEO column key order to include bbPress-specific column keys.
 *
 * @since 1.0.0
 *
 * @param array<int, string> $current_keys The current column key order.
 * @return array<int, string> The merged column key order including bbPress keys.
 */
function _bbpress_filter_order_keys( array $current_keys = [] ): array {

	$new_keys = [
		'bbp_topic_freshness',
		'bbp_forum_freshness',
		'bbp_reply_created',
	];

	return array_merge( $current_keys, $new_keys );
}

/**
 * Prevents Better SEO from adjusting the archive query for bbPress reply post types.
 *
 * @since 1.0.0
 *
 * @param bool      $adjust   Whether to adjust the archive query.
 * @param \WP_Query $wp_query The current WP_Query instance.
 * @return bool False if the query contains bbPress reply post types, original value otherwise.
 */
function _bbpress_filter_do_adjust_query( bool $adjust, \WP_Query $wp_query ): bool {

	if (
		   isset( $wp_query->query['post_type'] )
		&& \in_array( 'reply', (array) $wp_query->query['post_type'], true )
		&& \function_exists( 'is_bbpress' )
		&& \is_bbpress()
	) {
		$adjust = false;
	}

	return $adjust;
}

/**
 * Adds noindex to the robots meta array for non-public bbPress forums.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed>      $meta The robots meta array.
 * @param array<string, mixed>|null $args The generation args, or null for current query.
 * @return array<string, mixed> The filtered robots meta array.
 */
function _bbpress_filter_robots( array $meta, mixed $args ): array {

	if ( isset( $args ) ) {
		if ( ! Helper\Compatibility::can_i_use( [
			'functions' => [
				'bbp_get_forum_post_type',
				'bbp_get_topic_post_type',
				'bbp_get_reply_post_type',
			],
		] ) ) {
			return $meta;
		}

		normalize_generation_args( $args );

		if ( 'single' === get_query_type_from_args( $args ) ) {
			$forum_id = match ( \get_post_type( $args['id'] ) ) {
				\bbp_get_forum_post_type()                                    => $args['id'],
				\bbp_get_topic_post_type(), \bbp_get_reply_post_type()        => \get_post_meta( $args['id'], '_bbp_forum_id', true ),
				default                                                       => null,
			};
		}
	} else {
		if ( ! Helper\Compatibility::can_i_use( [
			'functions' => [
				'bbp_is_single_forum',
				'bbp_is_single_topic',
				'bbp_is_single_reply',
			],
		] ) ) {
			return $meta;
		}

		if ( \bbp_is_single_forum() ) {
			$forum_id = Query::get_the_real_id();
		} elseif ( \bbp_is_single_topic() ) {
			$forum_id = \get_post_meta( Query::get_the_real_id(), '_bbp_forum_id', true );
		} elseif ( \bbp_is_single_reply() ) {
			$forum_id = \get_post_meta( Query::get_the_real_id(), '_bbp_forum_id', true );
		}
	}

	if ( ! empty( $forum_id ) && ! \bbp_is_forum_public( $forum_id ) ) {
		$meta['noindex'] = 'noindex';
	}

	return $meta;
}

/**
 * Asserts noindex SEO bar defaults for non-public bbPress forums.
 *
 * Updates the indexing SEO bar item to reflect the forum's non-public status
 * and removes the override option since the forum is publicly unreachable.
 *
 * @since 1.0.0
 *
 * @param object $interpreter The SEO bar interpreter (Builder class).
 * @param object $builder     The SEO bar builder instance.
 * @return void
 */
function _assert_bbpress_noindex_defaults_seo_bar( object $interpreter, object $builder ): void {

	if ( $interpreter::$query['tax'] ) {
		return;
	}

	if ( ! Helper\Compatibility::can_i_use( [
		'functions' => [
			'bbp_get_forum_post_type',
			'bbp_get_topic_post_type',
			'bbp_get_reply_post_type',
			'bbp_is_forum_public',
		],
	] ) ) {
		return;
	}

	$items = $interpreter::collect_seo_bar_items();

	// Skip if a blocking redirect is already set — noindex is irrelevant.
	if ( ! empty( $items['redirect']['meta']['blocking'] ) ) {
		return;
	}

	$forum_id = match ( $interpreter::$query['post_type'] ) {
		\bbp_get_forum_post_type()                                    => $interpreter::$query['id'],
		\bbp_get_topic_post_type(), \bbp_get_reply_post_type()        => \get_post_meta( $interpreter::$query['id'], '_bbp_forum_id', true ),
		default                                                       => null,
	};

	if ( empty( $forum_id ) || \bbp_is_forum_public( $forum_id ) ) {
		return;
	}

	$index_item           = &$interpreter::edit_seo_bar_item( 'indexing' );
	$index_item['status'] = 0 !== Sanitize::qubit( $builder->get_query_cache()['meta']['_genesis_noindex'] )
		? $interpreter::STATE_OKAY
		: $interpreter::STATE_UNKNOWN;

	if ( 'forum' === $interpreter::$query['post_type'] ) {
		$index_item['assess']['notpublic'] = \__( 'NOT a public forum.', 'better-seo' );
	} else {
		$index_item['assess']['notpublic'] = \__( 'This page is not public.', 'better-seo' );
	}

	// No amount of overriding will fix this — the forum/topic/reply is publicly unreachable.
	unset( $index_item['override'] );
}