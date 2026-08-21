<?php
/**
 * Better SEO - Compatibility: Genesis Framework
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

\add_filter( 'genesis_detect_seo_plugins',  __NAMESPACE__ . '\_disable_genesis_seo',    10, 1 );
\add_filter( 'better_seo_term_meta_defaults', __NAMESPACE__ . '\_genesis_get_term_meta', 10, 2 );

/**
 * Tells Genesis Framework that Better SEO is an active SEO plugin.
 *
 * Returns a detection array containing the BETTER_SEO_PRESENT constant string,
 * which Genesis uses to detect active SEO plugins and disable its own SEO output.
 * The constant name is intentionally passed as a string — Genesis checks for its
 * existence via defined(), not by evaluating it.
 *
 * @since 1.0.0
 *
 * @return array<string, array<int, string>> The Genesis SEO plugin detection array.
 */
function _disable_genesis_seo(): array {
	return [
		'classes'   => [],
		'functions' => [],
		'constants' => [
			'BETTER_SEO_PRESENT', // Intentional string — Genesis checks defined() on this value.
		],
	];
}

/**
 * Merges Genesis Framework term meta into Better SEO's term meta defaults.
 *
 * Reads Genesis-specific term meta fields (doctitle, description, noindex,
 * nofollow, noarchive) and merges any non-empty values into Better SEO's
 * term meta defaults array for backward compatibility.
 *
 * @since 1.0.0
 *
 * @param array<string, mixed> $data    The current Better SEO term meta defaults.
 * @param int                  $term_id The term ID being processed.
 * @return array<string, mixed> The merged term meta defaults array.
 */
function _genesis_get_term_meta( array $data = [], int $term_id = 0 ): array {

	$genesis_data = array_filter( [
		'doctitle'    => \get_term_meta( $term_id, 'doctitle',    true ) ?: false,
		'description' => \get_term_meta( $term_id, 'description', true ) ?: false,
		'noindex'     => \get_term_meta( $term_id, 'noindex',     true ) ?: false,
		'nofollow'    => \get_term_meta( $term_id, 'nofollow',    true ) ?: false,
		'noarchive'   => \get_term_meta( $term_id, 'noarchive',   true ) ?: false,
	] );

	return array_merge( $data, $genesis_data );
}