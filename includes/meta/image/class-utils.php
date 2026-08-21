<?php
/**
 * Better SEO - Meta Image Utils
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Meta\Image
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

namespace Better_SEO\Meta\Image;

\defined( 'BETTER_SEO_PRESENT' ) or die;

/**
 * Class Better_SEO\Meta\Image\Utils
 *
 * Provides image metadata utility methods for Better SEO, including
 * dimension retrieval, alt tag lookup, caption lookup, filesize lookup,
 * and largest-image-within-constraints resolution.
 *
 * @since 1.0.0
 */
class Utils {

	/**
	 * Returns the width and height of an attachment image at the given size.
	 *
	 * Falls back to the full image dimensions if the requested size is not found.
	 * Returns [0, 0] if no dimension data is available.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $src_id The attachment post ID.
	 * @param string $size   The image size slug (e.g. 'full', 'thumbnail').
	 * @return array<string, int> The ['width' => int, 'height' => int] dimensions array.
	 */
	public static function get_image_dimensions( int $src_id, string $size ): array {

		$data = \wp_get_attachment_metadata( $src_id ) ?? [];
		$data = $data['sizes'][ $size ] ?? $data;

		if ( isset( $data['width'], $data['height'] ) ) {
			return [
				'width'  => $data['width'],
				'height' => $data['height'],
			];
		}

		return [
			'width'  => 0,
			'height' => 0,
		];
	}

	/**
	 * Returns the alt text for the given attachment.
	 *
	 * @since 1.0.0
	 *
	 * @param int $src_id The attachment post ID.
	 * @return string The alt text string, or empty string if not set.
	 */
	public static function get_image_alt_tag( int $src_id ): string {
		return \get_post_meta( $src_id, '_wp_attachment_image_alt', true ) ?: '';
	}

	/**
	 * Returns the caption for the given attachment.
	 *
	 * @since 1.0.0
	 *
	 * @param int $src_id The attachment post ID.
	 * @return string The caption string, or empty string if not set.
	 */
	public static function get_image_caption( int $src_id ): string {
		return \wp_get_attachment_caption( $src_id ) ?: '';
	}

	/**
	 * Returns the filesize in bytes for the given attachment at the given size.
	 *
	 * Falls back to the full image filesize if the requested size has no filesize data.
	 * Returns 0 if no filesize data is available.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $src_id The attachment post ID.
	 * @param string $size   The image size slug (e.g. 'full', 'thumbnail').
	 * @return int The filesize in bytes, or 0 if not available.
	 */
	public static function get_image_filesize( int $src_id, string $size ): int {

		$data = \wp_get_attachment_metadata( $src_id ) ?: [];

		return ( $data['sizes'][ $size ]['filesize'] ?? $data['filesize'] ?? 0 ) ?: 0;
	}

	/**
	 * Returns the wp_get_attachment_image_src() result for the largest registered
	 * image size that fits within the given dimension and filesize constraints.
	 *
	 * Iterates all registered sizes for the attachment and selects the widest
	 * image that does not exceed $max_size in either dimension or $max_filesize in bytes.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id           The attachment post ID.
	 * @param int $max_size     The maximum allowed width or height in pixels. Default 4096.
	 * @param int $max_filesize The maximum allowed filesize in bytes. Default 5242880 (5MB).
	 * @return array<int, mixed>|false The wp_get_attachment_image_src() result, or false if none found.
	 */
	public static function get_largest_image_src( int $id, int $max_size = 4096, int $max_filesize = 5242880 ): array|false {

		$sizes = \wp_get_attachment_metadata( $id )['sizes'] ?? [];

		$largest_width = 0;
		$best_size     = '';

		foreach ( $sizes as $_size_name => $_size_data ) {
			if ( ( $_size_data['filesize'] ?? 0 ) > $max_filesize ) {
				continue;
			}

			if (
				isset( $_size_data['width'], $_size_data['height'] )
				&& $_size_data['width'] > $largest_width
				&& $_size_data['width'] <= $max_size
				&& $_size_data['height'] <= $max_size
			) {
				$largest_width = $_size_data['width'];
				$best_size     = $_size_name;
			}
		}

		return $best_size ? \wp_get_attachment_image_src( $id, $best_size ) : false;
	}
}