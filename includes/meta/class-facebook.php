<?php
/**
 * Better SEO - Meta Facebook
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Meta
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

namespace Better_SEO\Meta;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\Data;

/**
 * Class Better_SEO\Meta\Facebook
 *
 * Provides Facebook Open Graph article meta values for Better SEO,
 * including article:author and article:publisher URLs.
 *
 * @since 1.0.0
 */
class Facebook {

    public static function get_author(): ?string {

        if ( 'article' !== Open_Graph::get_type() ) {
            return null;
        }

        return Data\Plugin\User::get_current_post_author_meta_item( 'facebook_page' )
            ?: Data\Plugin::get_option( 'facebook_author' )
            ?: null;
    }

    public static function get_publisher(): ?string {

        if ( 'article' !== Open_Graph::get_type() ) {
            return null;
        }

        return Data\Plugin::get_option( 'facebook_publisher' ) ?: null;
    }
}