<?php
/**
 * Better SEO - Compatibility: BuddyPress
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

\add_filter( 'better_seo_meta_generator_pools', __NAMESPACE__ . '\_buddypress_filter_generator_pools' );

/**
 * Removes meta generator pools that should not run on BuddyPress pages.
 *
 * When a BuddyPress page is detected, the Robots, URI, Open_Graph,
 * Twitter, and Schema generator pools are removed to prevent Better SEO
 * from outputting conflicting meta tags on BuddyPress-controlled pages.
 *
 * @since 1.0.0
 *
 * @param array<int, string> $generator_pools The registered meta generator pool names.
 * @return array<int, string> The filtered generator pool names.
 */
function _buddypress_filter_generator_pools( array $generator_pools ): array {

	if ( \function_exists( 'is_buddypress' ) && \is_buddypress() ) {
		$generator_pools = array_diff(
			$generator_pools,
			[ 'Robots', 'URI', 'Open_Graph', 'Twitter', 'Schema' ],
		);
	}

	return $generator_pools;
}