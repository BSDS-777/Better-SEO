<?php
/**
 * Better SEO - Front OEmbed
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Front
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

namespace Better_SEO\Front;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\{
	Data,
	Meta,
};

/**
 * Class Better_SEO\Front\OEmbed
 *
 * Modifies oEmbed response data to use Better SEO titles, social images,
 * and optionally removes author information.
 *
 * @since 1.0.0
 */
final class OEmbed {

	/**
	 * Alters the oEmbed response data for a given post.
	 *
	 * Applies Better SEO title, social image, and author removal settings
	 * to the oEmbed response based on plugin options.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data The oEmbed response data.
	 * @param \WP_Post             $post The post being embedded.
	 * @return array<string, mixed> The modified oEmbed response data.
	 */
	public static function alter_response_data( array $data, \WP_Post $post ): array {

		if ( Data\Plugin::get_option( 'oembed_use_og_title' ) ) {
			$data['title'] = (
				Data\Plugin::get_option( 'og_tags' )
					? Meta\Open_Graph::get_title( [ 'id' => $post->ID ] )
					: Meta\Title::get_title( [ 'id' => $post->ID ] )
			) ?: $data['title'];
		}

		if ( Data\Plugin::get_option( 'oembed_use_social_image' ) ) {
			$image_details = current( Meta\Image::get_image_details(
				[ 'id' => $post->ID ],
				true,
				'oembed'
			) );

			if ( $image_details && $image_details['url'] && $image_details['width'] && $image_details['height'] ) {
				// Override WordPress provided thumbnail data.
				$data['thumbnail_url']    = $image_details['url'];
				$data['thumbnail_width']  = $image_details['width'];
				$data['thumbnail_height'] = $image_details['height'];
			}
		}

		if ( Data\Plugin::get_option( 'oembed_remove_author' ) ) {
			unset( $data['author_url'], $data['author_name'] );
		}

		return $data;
	}
}
