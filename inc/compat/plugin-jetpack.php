<?php
/**
 * Better SEO - Compatibility: Jetpack
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

use Better_SEO\Data;

/**
 * Disable Jetpack Open Graph output when Better SEO Open Graph tags are enabled.
 *
 * Prevents duplicate og: meta tags from being output by both plugins simultaneously.
 */
if ( Data\Plugin::get_option( 'og_tags' ) ) {
	\add_filter( 'jetpack_enable_open_graph', '__return_false' );
}

/**
 * Disable Jetpack Twitter Cards output when Better SEO Twitter tags are enabled.
 *
 * Prevents duplicate twitter: meta tags from being output by both plugins simultaneously.
 */
if ( Data\Plugin::get_option( 'twitter_tags' ) ) {
	\add_filter( 'jetpack_disable_twitter_cards', '__return_true' );
}
