<?php
/**
 * Better SEO - Compatibility: Ultimate Member
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

use Better_SEO\Helper\Compatibility;

\add_action( 'template_redirect',           __NAMESPACE__ . '\_um_reinstate_title_support', 100000 );
\add_filter( 'better_seo_query_supports_seo', __NAMESPACE__ . '\_um_determine_support' );

/**
 * Reinstates WordPress title filters for Ultimate Member user profile pages.
 *
 * Ultimate Member removes the default title filters on user profile pages.
 * This function re-adds them at a very late priority so Better SEO can
 * still generate the correct document title for UM user pages.
 *
 * @hook template_redirect 100000
 * @since 1.0.0
 *
 * @return void
 */
function _um_reinstate_title_support(): void {

	if ( ! Compatibility::can_i_use( [
		'functions' => [
			'um_is_core_page',
			'um_get_requested_user',
			'um_dynamic_user_profile_pagetitle',
		],
	] ) ) {
		return;
	}

	if ( \um_is_core_page( 'user' ) && \um_get_requested_user() ) {
		\add_filter( 'wp_title',              'um_dynamic_user_profile_pagetitle', 100000, 2 );
		\add_filter( 'pre_get_document_title', 'um_dynamic_user_profile_pagetitle', 100000, 2 );
	}
}

/**
 * Determines whether Better SEO should support SEO output for the current query.
 *
 * Disables Better SEO's SEO output on Ultimate Member user profile pages,
 * since UM manages its own title and meta output for those pages.
 *
 * @hook better_seo_query_supports_seo 10
 * @since 1.0.0
 *
 * @param bool $supported Whether the current query supports Better SEO output.
 * @return bool False on UM user profile pages, original value otherwise.
 */
function _um_determine_support( bool $supported = true ): bool {

	// Already unsupported — bail early.
	if ( ! $supported ) {
		return $supported;
	}

	if ( ! Compatibility::can_i_use( [
		'functions' => [
			'um_queried_user',
			'um_is_core_page',
		],
	] ) ) {
		return $supported;
	}

	return ! ( \um_queried_user() && \um_is_core_page( 'user' ) );
}