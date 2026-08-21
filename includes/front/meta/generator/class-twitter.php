<?php
/**
 * Better SEO - Front Meta Generator Twitter
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Front\Meta\Generator
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

namespace Better_SEO\Front\Meta\Generator;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\Meta;

/**
 * Class Better_SEO\Front\Meta\Generator\Twitter
 *
 * Generates Twitter Card meta tags including card type, site, creator,
 * title, description, and image.
 *
 * @since 1.0.0
 */
final class Twitter {

	/**
	 * Registered generator callbacks for this pool.
	 *
	 * @since 1.0.0
	 * @var   array<int, array{0: class-string, 1: string}>
	 */
	public const GENERATORS = [
		[ __CLASS__, 'generate_twitter_card' ],
		[ __CLASS__, 'generate_twitter_site' ],
		[ __CLASS__, 'generate_twitter_creator' ],
		[ __CLASS__, 'generate_twitter_title' ],
		[ __CLASS__, 'generate_twitter_description' ],
		[ __CLASS__, 'generate_twitter_image' ],
	];

	/**
	 * Generates the twitter:card meta tag.
	 *
	 * Yields nothing if no card type is available.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the twitter:card meta tag data.
	 */
	public static function generate_twitter_card(): \Generator {

		$card = Meta\Twitter::get_card_type();

		if ( $card ) {
			yield 'twitter:card' => [
				'attributes' => [
					'name'    => 'twitter:card',
					'content' => $card,
				],
			];
		}
	}

	/**
	 * Generates the twitter:site meta tag.
	 *
	 * Yields nothing if no site handle is configured.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the twitter:site meta tag data.
	 */
	public static function generate_twitter_site(): \Generator {

		$site = Meta\Twitter::get_site();

		if ( $site ) {
			yield 'twitter:site' => [
				'attributes' => [
					'name'    => 'twitter:site',
					'content' => $site,
				],
			];
		}
	}

	/**
	 * Generates the twitter:creator meta tag.
	 *
	 * Yields nothing if no creator handle is available.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the twitter:creator meta tag data.
	 */
	public static function generate_twitter_creator(): \Generator {

		$creator = Meta\Twitter::get_creator();

		if ( $creator ) {
			yield 'twitter:creator' => [
				'attributes' => [
					'name'    => 'twitter:creator',
					'content' => $creator,
				],
			];
		}
	}

	/**
	 * Generates the twitter:title meta tag.
	 *
	 * Yields nothing if the title is empty.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the twitter:title meta tag data.
	 */
	public static function generate_twitter_title(): \Generator {

		$title = Meta\Twitter::get_title();

		if ( \strlen( $title ) ) {
			yield 'twitter:title' => [
				'attributes' => [
					'name'    => 'twitter:title',
					'content' => $title,
				],
			];
		}
	}

	/**
	 * Generates the twitter:description meta tag.
	 *
	 * Yields nothing if the description is empty.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields the twitter:description meta tag data.
	 */
	public static function generate_twitter_description(): \Generator {

		$description = Meta\Twitter::get_description();

		if ( \strlen( $description ) ) {
			yield 'twitter:description' => [
				'attributes' => [
					'name'    => 'twitter:description',
					'content' => $description,
				],
			];
		}
	}

	/**
	 * Generates the twitter:image and twitter:image:alt meta tags.
	 *
	 * Only grabs a single image — Twitter uses the final (less favorable)
	 * image if multiple are provided. Yields nothing if no image is available.
	 *
	 * @since 1.0.0
	 *
	 * @return \Generator Yields twitter:image and optionally twitter:image:alt meta tag data.
	 */
	public static function generate_twitter_image(): \Generator {

		// Only grab a single image — Twitter uses the final (less favorable) image if multiple are provided.
		$image = current( Meta\Image::get_image_details( null, true ) );

		if ( $image ) {
			yield 'twitter:image' => [
				'attributes' => [
					'name'    => 'twitter:image',
					'content' => $image['url'],
				],
			];

			if ( $image['alt'] ) {
				yield 'twitter:image:alt' => [
					'attributes' => [
						'name'    => 'twitter:image:alt',
						'content' => $image['alt'],
					],
				];
			}
		}
	}
}