<?php
/**
 * Better SEO - Meta Image Generator
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

use Better_SEO\{
	Data,
	Helper\Query,
	Helper\Format,
};

/**
 * Class Better_SEO\Meta\Image\Generator
 *
 * Provides image detail generators for Better SEO social and structured data output.
 * Each generator method yields image detail arrays containing 'url' and 'id' keys.
 *
 * Generators are registered via Better_SEO\Meta\Image::get_image_generation_params()
 * and called by Better_SEO\Meta\Image::generate_image_from_callbacks().
 *
 * @since 1.0.0
 */
final class Generator {

	/**
	 * Maximum number of content images to yield per post.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	private const MAX_CONTENT_IMAGES = 5;

	/**
	 * Generates image details from the current attachment post.
	 *
	 * Yields the attachment image URL and ID if the attachment is an image.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @param string                    $size The image size to retrieve. Default 'full'.
	 * @return \Generator Yields image detail arrays with 'url' and 'id' keys.
	 */
	public static function generate_attachment_image_details( ?array $args = null, string $size = 'full' ): \Generator {

		$id  = $args['id'] ?? Query::get_the_real_id();
		$url = $id ? \wp_get_attachment_image_url( $id, $size ) : '';

		if ( $url ) {
			yield [
				'url' => $url,
				'id'  => $id,
			];
		}
	}

	/**
	 * Generates image details from the featured image of the current or given post.
	 *
	 * Yields the featured image URL and attachment ID if a featured image is set.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @param string                    $size The image size to retrieve. Default 'full'.
	 * @return \Generator Yields image detail arrays with 'url' and 'id' keys.
	 */
	public static function generate_featured_image_details( ?array $args = null, string $size = 'full' ): \Generator {

		$id  = \get_post_thumbnail_id( $args['id'] ?? Query::get_the_real_id() );
		$url = $id ? \wp_get_attachment_image_url( $id, $size ) : '';

		if ( $url ) {
			yield [
				'url' => $url,
				'id'  => $id,
			];
		}
	}

	/**
	 * Generates image details from img tags found in the post content.
	 *
	 * Strips non-image HTML elements, then extracts img src attributes.
	 * Yields up to MAX_CONTENT_IMAGES images. Image IDs are set to 0 since
	 * content images may not be WordPress media library attachments.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args. Default null.
	 * @return \Generator Yields image detail arrays with 'url' and 'id' keys.
	 */
	public static function generate_content_image_details( ?array $args = null ): \Generator {

		if ( isset( $args ) ) {
			if ( empty( $args['tax'] ) && empty( $args['pta'] ) && empty( $args['uid'] ) ) {
				$content = Data\Post::get_content( $args['id'] );
			}
		} elseif ( Query::is_singular() ) {
			// $GLOBALS['pages'] isn't populated here — skip pagination to conserve CPU.
			$content = Data\Post::get_content();
		}

		if ( empty( $content ) ) {
			return;
		}

		if ( \strlen( $content ) > 10 && false !== stripos( $content, '<img ' ) ) {
			$content = Format\HTML::strip_tags_cs(
				$content,
				[
					'space' => [],
					'clear' =>
						[ 'address', 'aside', 'blockquote', 'button', 'canvas', 'code', 'datalist', 'dialog', 'dl', 'fieldset', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'iframe', 'input', 'label', 'map', 'menu', 'nav', 'noscript', 'ol', 'object', 'output', 'pre', 'script', 'select', 'style', 'svg', 'table', 'template', 'textarea', 'ul', 'var', 'video' ],
					'strip' => false,
				],
			);

			preg_match_all(
				'/<img\b[^>]+?\bsrc=(["\'"])?([^"\'>\s]+)\1?[^>]*?>/mi',
				$content,
				$matches,
				\PREG_SET_ORDER,
			);
		}

		$yielded_images = 0;

		foreach ( $matches ?? [] as $match ) {
			if ( empty( $match[2] ) ) {
				continue;
			}

			yield [
				'url' => $match[2],
				'id'  => 0,
			];

			if ( ++$yielded_images > self::MAX_CONTENT_IMAGES ) {
				break;
			}
		}
	}

	/**
	 * Generates image details from the plugin fallback social image setting.
	 *
	 * Yields the configured fallback image URL and ID if set.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields image detail arrays with 'url' and 'id' keys.
	 */
	public static function generate_fallback_image_details(): \Generator {

		$url = Data\Plugin::get_option( 'social_image_fb_url' );

		if ( $url ) {
			yield [
				'url' => $url,
				'id'  => Data\Plugin::get_option( 'social_image_fb_id' ) ?: 0,
			];
		}
	}

	/**
	 * Generates image details from the active theme's header image.
	 *
	 * Supports both string URL and object-based header image data.
	 * Yields the header image URL and attachment ID (0 if not a media library attachment).
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args (unused). Default null.
	 * @param string                    $size The image size to retrieve. Default 'full'.
	 * @return \Generator Yields image detail arrays with 'url' and 'id' keys.
	 */
	public static function generate_theme_header_image_details( ?array $args = null, string $size = 'full' ): \Generator {

		$image = \get_theme_mod(
			'header_image_data',
			\get_theme_support( 'custom-header', 'default-image' ),
		);

		if ( \is_string( $image ) && $image ) {
			yield [
				'url' => $image,
				'id'  => 0,
			];
		} elseif ( \is_object( $image ) && ! empty( $image->url ) ) {
			if ( empty( $image->attachment_id ) ) {
				// attachment_id is not stored by default — yield URL only.
				yield [
					'url' => $image->url,
					'id'  => 0,
				];
			} else {
				$url = \wp_get_attachment_image_url( $image->attachment_id, $size );

				if ( $url ) {
					yield [
						'url' => $url,
						'id'  => $image->attachment_id,
					];
				}
			}
		}
	}

	/**
	 * Generates image details from the site's custom logo (Customizer).
	 *
	 * Yields the custom logo URL and attachment ID if a logo is configured.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args (unused). Default null.
	 * @param string                    $size The image size to retrieve. Default 'full'.
	 * @return \Generator Yields image detail arrays with 'url' and 'id' keys.
	 */
	public static function generate_site_logo_image_details( ?array $args = null, string $size = 'full' ): \Generator {

		$id  = \get_theme_mod( 'custom_logo' );
		$url = $id ? \wp_get_attachment_image_url( $id, $size ) : '';

		if ( $url ) {
			yield [
				'url' => $url,
				'id'  => $id,
			];
		}
	}

	/**
	 * Generates image details from the site icon (favicon/app icon).
	 *
	 * Yields the site icon URL and attachment ID if a site icon is configured.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args Optional generation args (unused). Default null.
	 * @param string                    $size The image size to retrieve. Default 'full'.
	 * @return \Generator Yields image detail arrays with 'url' and 'id' keys.
	 */
	public static function generate_site_icon_image_details( ?array $args = null, string $size = 'full' ): \Generator {

		$id  = \get_option( 'site_icon' );
		$url = $id ? \wp_get_attachment_image_url( $id, $size ) : '';

		if ( $url ) {
			yield [
				'url' => $url,
				'id'  => $id,
			];
		}
	}
}