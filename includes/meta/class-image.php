<?php
/**
 * Better SEO - Meta Image
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

use function Better_SEO\{
	get_query_type_from_args,
	normalize_generation_args,
};

use Better_SEO\{
	Data,
	Data\Filter\Sanitize,
	Helper\Query,
	Meta,
};

/**
 * Class Better_SEO\Meta\Image
 *
 * Provides image detail retrieval and generation for Better SEO social and
 * structured data output, including custom fields, featured images, content
 * images, fallbacks, and organization logos.
 *
 * @since 1.0.0
 */
class Image {

	/**
	 * Returns the first available image URL (custom or generated) for the given context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args    Optional generation args. Default null.
	 * @param string                    $context The image context ('social', 'organization', etc.). Default 'social'.
	 * @return string The image URL, or empty string if none found.
	 */
	public static function get_first_image_url( ?array $args = null, string $context = 'social' ): string {
		return self::get_first_custom_image_url( $args, $context )
			?: self::get_first_generated_image_url( $args, $context );
	}

	/**
	 * Returns the first custom image URL for the given context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args    Optional generation args. Default null.
	 * @param string                    $context The image context. Default 'social'.
	 * @return string The custom image URL, or empty string if none found.
	 */
	public static function get_first_custom_image_url( ?array $args = null, string $context = 'social' ): string {
		return current( self::get_custom_image_details( $args, null, $context ) )['url'] ?? '';
	}

	/**
	 * Returns the first generated image URL for the given context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args    Optional generation args. Default null.
	 * @param string                    $context The image context. Default 'social'.
	 * @return string The generated image URL, or empty string if none found.
	 */
	public static function get_first_generated_image_url( ?array $args = null, string $context = 'social' ): string {
		return current( self::get_generated_image_details( $args, null, $context ) )['url'] ?? '';
	}

	/**
	 * Returns image details (custom or generated) for the given context.
	 *
	 * Returns custom image details if available, otherwise falls back to generated.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args    Optional generation args. Default null.
	 * @param bool|null                 $single  Whether to return only the first image. Default false.
	 * @param string                    $context The image context. Default 'social'.
	 * @return array<int, array<string, mixed>> The image details array.
	 */
	public static function get_image_details( ?array $args = null, ?bool $single = false, string $context = 'social' ): array {
		return self::get_custom_image_details( $args, $single, $context )
			?: self::get_generated_image_details( $args, $single, $context );
	}

	/**
	 * Returns custom image details for the given context.
	 *
	 * Applies the better_seo_custom_image_details filter.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args    Optional generation args. Default null.
	 * @param bool|null                 $single  Whether to return only the first image. Default false.
	 * @param string                    $context The image context. Default 'social'.
	 * @return array<int, array<string, mixed>> The custom image details array.
	 */
	public static function get_custom_image_details( ?array $args = null, ?bool $single = false, string $context = 'social' ): array {

		/**
		 * Filters the Better SEO custom image details.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, array<string, mixed>> $details The custom image details.
		 * @param array<string, mixed>|null        $args    The generation args, or null.
		 * @param bool|null                        $single  Whether only the first image is returned.
		 */
		return \apply_filters(
			'better_seo_custom_image_details',
			$single
				? array_filter( [ self::generate_custom_image_details( $args, $context )->current() ] )
				: [ ...self::generate_custom_image_details( $args, $context ) ],
			$args,
			$single,
		);
	}

	/**
	 * Returns generated image details for the given context.
	 *
	 * Applies the better_seo_generated_image_details filter.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args    Optional generation args. Default null.
	 * @param bool|null                 $single  Whether to return only the first image. Default false.
	 * @param string                    $context The image context. Default 'social'.
	 * @return array<int, array<string, mixed>> The generated image details array.
	 */
	public static function get_generated_image_details( ?array $args = null, ?bool $single = false, string $context = 'social' ): array {

		/**
		 * Filters the Better SEO generated image details.
		 *
		 * @since 1.0.0
		 *
		 * @param array<int, array<string, mixed>> $details The generated image details.
		 * @param array<string, mixed>|null        $args    The generation args, or null.
		 * @param bool|null                        $single  Whether only the first image is returned.
		 * @param string                           $context The image context.
		 */
		return \apply_filters(
			'better_seo_generated_image_details',
			$single
				? array_filter( [ self::generate_generated_image_details( $args, $context )->current() ] )
				: [ ...self::generate_generated_image_details( $args, $context ) ],
			$args,
			$single,
			$context,
		);
	}

	/**
	 * Generates all image details (custom first, then generated) for the given context.
	 *
	 * Yields custom images first; only yields generated images if no custom images were found.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args    Optional generation args. Default null.
	 * @param string                    $context The image context. Default 'social'.
	 * @return \Generator Yields image detail arrays.
	 */
	public static function generate_image_details( ?array $args = null, string $context = 'social' ): \Generator {

		foreach ( self::generate_custom_image_details( $args, $context ) as $details ) {
			yield $details;
			$yielded_custom = true;
		}

		if ( empty( $yielded_custom ) ) {
			yield from self::generate_generated_image_details( $args, $context );
		}
	}

	/**
	 * Generates custom image details for the given args or current query context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args    Optional generation args. Default null.
	 * @param string                    $context The image context. Default 'social'.
	 * @return \Generator Yields custom image detail arrays.
	 */
	public static function generate_custom_image_details( ?array $args = null, string $context = 'social' ): \Generator {

		if ( isset( $args ) ) {
			yield from self::generate_custom_image_details_from_args( $args, $context );
		} else {
			yield from self::generate_custom_image_details_from_query( $context );
		}
	}

	/**
	 * Generates generated image details for the given args or current query context.
	 *
	 * Uses registered generator callbacks and falls back to fallback callbacks
	 * if no primary images are found.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args    Optional generation args. Default null.
	 * @param string                    $context The image context. Default 'social'.
	 * @return \Generator Yields generated image detail arrays.
	 */
	public static function generate_generated_image_details( ?array $args = null, string $context = 'social' ): \Generator {

		if ( isset( $args ) ) {
			normalize_generation_args( $args );
		}

		$params = self::get_image_generation_params( $args, $context );

		foreach (
			self::generate_image_from_callbacks( $args, $params['cbs'], $params['size'], ! $params['multi'] )
			as $details
		) {
			yield $details;
			$yielded_cbs = true;
		}

		if ( empty( $yielded_cbs ) ) {
			yield from self::generate_image_from_callbacks( $args, $params['fallback'], $params['size'], true );
		}
	}

	/**
	 * Generates custom image details from the current query context.
	 *
	 * @since 1.0.0
	 *
	 * @param string $context The image context. Default 'social'.
	 * @return \Generator Yields custom image detail arrays.
	 */
	public static function generate_custom_image_details_from_query( string $context = 'social' ): \Generator {

		if ( 'organization' === $context ) {
			$details = [
				'url' => Data\Plugin::get_option( 'knowledge_logo_url' ),
				'id'  => Data\Plugin::get_option( 'knowledge_logo_id' ),
			];
		} else {
			if ( Query::is_real_front_page() ) {
				if ( Query::is_static_front_page() ) {
					$details = [
						'url' => Data\Plugin::get_option( 'homepage_social_image_url' ),
						'id'  => Data\Plugin::get_option( 'homepage_social_image_id' ),
					];
					if ( ! $details['url'] ) {
						$details = [
							'url' => Data\Plugin\Post::get_meta_item( '_social_image_url' ),
							'id'  => Data\Plugin\Post::get_meta_item( '_social_image_id' ),
						];
					}
				} else {
					$details = [
						'url' => Data\Plugin::get_option( 'homepage_social_image_url' ),
						'id'  => Data\Plugin::get_option( 'homepage_social_image_id' ),
					];
				}
			} elseif ( Query::is_singular() ) {
				$details = [
					'url' => Data\Plugin\Post::get_meta_item( '_social_image_url' ),
					'id'  => Data\Plugin\Post::get_meta_item( '_social_image_id' ),
				];
			} elseif ( Query::is_editable_term() ) {
				$details = [
					'url' => Data\Plugin\Term::get_meta_item( 'social_image_url' ),
					'id'  => Data\Plugin\Term::get_meta_item( 'social_image_id' ),
				];
			} elseif ( \is_post_type_archive() ) {
				$details = [
					'url' => Data\Plugin\PTA::get_meta_item( 'social_image_url' ),
					'id'  => Data\Plugin\PTA::get_meta_item( 'social_image_id' ),
				];
			}
		}

		if ( ! empty( $details['url'] ) ) {
			$details = Sanitize::image_details( self::merge_extra_image_details( $details, 'full' ) );

			if ( $details['url'] ) {
				yield $details;
			}
		}
	}

	/**
	 * Generates custom image details from the given generation args.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $args    The generation args.
	 * @param string               $context The image context. Default 'social'.
	 * @return \Generator Yields custom image detail arrays.
	 */
	public static function generate_custom_image_details_from_args( array $args, string $context = 'social' ): \Generator {

		if ( 'organization' === $context ) {
			$details = [
				'url' => Data\Plugin::get_option( 'knowledge_logo_url' ),
				'id'  => Data\Plugin::get_option( 'knowledge_logo_id' ),
			];
		} else {
			normalize_generation_args( $args );

			$details = match ( get_query_type_from_args( $args ) ) {
				'single'   => Query::is_static_front_page( $args['id'] )
					? (
						empty( Data\Plugin::get_option( 'homepage_social_image_url' ) )
							? [
								'url' => Data\Plugin\Post::get_meta_item( '_social_image_url', $args['id'] ),
								'id'  => Data\Plugin\Post::get_meta_item( '_social_image_id', $args['id'] ),
							]
							: [
								'url' => Data\Plugin::get_option( 'homepage_social_image_url' ),
								'id'  => Data\Plugin::get_option( 'homepage_social_image_id' ),
							]
					)
					: [
						'url' => Data\Plugin\Post::get_meta_item( '_social_image_url', $args['id'] ),
						'id'  => Data\Plugin\Post::get_meta_item( '_social_image_id', $args['id'] ),
					],
				'term'     => [
					'url' => Data\Plugin\Term::get_meta_item( 'social_image_url', $args['id'] ),
					'id'  => Data\Plugin\Term::get_meta_item( 'social_image_id', $args['id'] ),
				],
				'homeblog' => [
					'url' => Data\Plugin::get_option( 'homepage_social_image_url' ),
					'id'  => Data\Plugin::get_option( 'homepage_social_image_id' ),
				],
				'pta'      => [
					'url' => Data\Plugin\PTA::get_meta_item( 'social_image_url', $args['pta'] ),
					'id'  => Data\Plugin\PTA::get_meta_item( 'social_image_id', $args['pta'] ),
				],
				default    => [],
			};
		}

		if ( ! empty( $details['url'] ) ) {
			$details = Sanitize::image_details( self::merge_extra_image_details( $details, 'full' ) );

			if ( $details['url'] ) {
				yield $details;
			}
		}
	}

	/**
	 * Returns the image generation parameters for the given args and context.
	 *
	 * Applies the better_seo_image_generation_params filter.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null $args    The generation args, or null for current query.
	 * @param string                    $context The image context.
	 * @return array<string, mixed> The image generation parameters (size, multi, cbs, fallback).
	 */
	private static function get_image_generation_params( ?array $args, string $context ): array {

		$generator = Image\Generator::class;

		if ( 'organization' === $context ) {
			$cbs = [
				'logo' => [ $generator, 'generate_site_logo_image_details' ],
				'icon' => [ $generator, 'generate_site_icon_image_details' ],
			];
		} else {
			if ( isset( $args ) ) {
				if ( 'single' === get_query_type_from_args( $args ) ) {
					if ( \wp_attachment_is_image( $args['id'] ) ) {
						$cbs = [
							'attachment' => [ $generator, 'generate_attachment_image_details' ],
						];
					} else {
						$cbs = [
							'featured' => [ $generator, 'generate_featured_image_details' ],
						];
						if ( 'social' === $context ) {
							$cbs['content'] = [ $generator, 'generate_content_image_details' ];
						}
					}
				}
			} else {
				if ( Query::is_attachment() ) {
					$cbs = [
						'attachment' => [ $generator, 'generate_attachment_image_details' ],
					];
				} elseif ( Query::is_singular() ) {
					$cbs = [
						'featured' => [ $generator, 'generate_featured_image_details' ],
					];

					if ( 'social' === $context ) {
						$cbs['content'] = [ $generator, 'generate_content_image_details' ];
					}
				}
			}

			if ( 'social' === $context ) {
				$fallback = [
					'settings' => [ $generator, 'generate_fallback_image_details' ],
					'header'   => [ $generator, 'generate_theme_header_image_details' ],
					'logo'     => [ $generator, 'generate_site_logo_image_details' ],
					'icon'     => [ $generator, 'generate_site_icon_image_details' ],
				];
			}
		}

		/**
		 * Filters the Better SEO image generation parameters.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed>      $params  The generation parameters (size, multi, cbs, fallback).
		 * @param array<string, mixed>|null $args    The generation args, or null.
		 * @param string                    $context The image context.
		 */
		return \apply_filters(
			'better_seo_image_generation_params',
			[
				'size'     => 'full',
				'multi'    => true,
				'cbs'      => $cbs ?? [],
				'fallback' => $fallback ?? [],
			],
			$args,
			$context,
		);
	}

	/**
	 * Generates image details from a list of generator callbacks.
	 *
	 * For query-based generation (args=null), uses a static fiber-based cache
	 * to avoid redundant generator calls across multiple invocations.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|null          $args   The generation args, or null for current query.
	 * @param array<string, array<int, mixed>>   $cbs    The generator callbacks.
	 * @param string                             $size   The image size. Default 'full'.
	 * @param bool                               $single Whether to stop after the first valid image.
	 * @return \Generator Yields sanitized image detail arrays.
	 */
	private static function generate_image_from_callbacks( ?array $args, array $cbs, string $size, bool $single ): \Generator {

		if ( isset( $args ) ) {
			foreach ( $cbs as $cb ) {
				foreach ( \call_user_func_array( $cb, [ $args, $size ] ) as $details ) {
					$details = Sanitize::image_details( self::merge_extra_image_details( $details, $size ) );

					if ( $details['url'] ) {
						yield $details;
						if ( $single ) {
							break 2;
						}
					}
				}
			}
		} else {
			// Memoize the query using a static fiber-based cache.
			static $m;

			foreach ( $cbs as $cb ) {
				$memo = &$m[ json_encode( [ $cb, $size ] ) ];

				// Yield previously cached values first.
				foreach ( $memo['values'] ?? [] as $details ) {
					yield $details;
					if ( $single ) {
						break 2;
					}
				}

				$memo['fiber'] ??= null;
				$fiber           = &$memo['fiber'];

				if ( isset( $fiber ) ) {
					// Fiber was already started — advance it or skip if exhausted.
					if ( ! $fiber ) {
						continue;
					}

					$fiber->next();
				} else {
					$fiber = \call_user_func_array( $cb, [ null, $size ] );
				}

				while ( $fiber->valid() || ( $fiber = false ) ) {
					$details = Sanitize::image_details( self::merge_extra_image_details(
						$fiber->current(),
						$size,
					) );

					if ( $details['url'] ) {
						yield $memo['values'][] = $details;
						if ( $single ) {
							break 2;
						}
					}

					$fiber->next();
				}
			}
		}
	}

	/**
	 * Merges extra image details (dimensions, alt, caption, filesize) into the given details array.
	 *
	 * If an image ID is present, fetches dimensions and metadata from the media library.
	 * Otherwise fills with zero/empty defaults.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $details The base image details array (url, id).
	 * @param string               $size    The image size to use for dimension/filesize lookup. Default 'full'.
	 * @return array<string, mixed> The merged image details array.
	 */
	public static function merge_extra_image_details( array $details, string $size = 'full' ): array {

		if ( $details['id'] ) {
			// Returns an array with 'width' and 'height' indexes.
			$details += Image\Utils::get_image_dimensions( $details['id'], $size );
			$details += [
				'alt'      => Image\Utils::get_image_alt_tag( $details['id'] ),
				'caption'  => Image\Utils::get_image_caption( $details['id'] ),
				'filesize' => Image\Utils::get_image_filesize( $details['id'], $size ),
			];
		} else {
			$details += [
				'width'    => 0,
				'height'   => 0,
				'alt'      => '',
				'caption'  => '',
				'filesize' => 0,
			];
		}

		return $details;
	}
}