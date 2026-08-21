<?php
/**
 * Better SEO - Sitemap Optimized XSL
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Sitemap\Optimized
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

namespace Better_SEO\Sitemap\Optimized;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\Helper\Template;

/**
 * Class Better_SEO\Sitemap\Optimized\XSL
 *
 * Registers WordPress action hooks for rendering the Better SEO sitemap
 * XSL stylesheet sections (head, description, content, footer) and
 * handles site icon meta tag conversion for XSL compatibility.
 *
 * @since 1.0.0
 */
final class XSL {

	/**
	 * Registers all XSL stylesheet output hooks.
	 *
	 * Hooks template output callbacks onto the better_seo_xsl_* actions
	 * for head, description, content, and footer sections.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_hooks(): void {

		\add_action( 'better_seo_xsl_head', 'wp_site_icon', 99 );

		\add_action( 'better_seo_xsl_head', [ self::class, '_print_xsl_global_variables' ], 0 );
		\add_action( 'better_seo_xsl_head', [ self::class, '_print_xsl_title' ] );
		\add_action( 'better_seo_xsl_head', [ self::class, '_print_xsl_styles' ] );

		\add_action( 'better_seo_xsl_description', [ self::class, '_print_xsl_description' ] );

		\add_action( 'better_seo_xsl_content', [ self::class, '_print_xsl_content' ] );

		\add_action( 'better_seo_xsl_footer', [ self::class, '_print_xsl_footer' ] );
		\add_action( 'site_icon_meta_tags', [ self::class, '_convert_site_icon_meta_tags' ], PHP_INT_MAX );
	}

	/**
	 * Outputs the XSL global variables template.
	 *
	 * @hook better_seo_xsl_head 0
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function _print_xsl_global_variables(): void {
		Template::output_view( 'sitemap/xsl/vars' );
	}

	/**
	 * Outputs the XSL title template.
	 *
	 * @hook better_seo_xsl_head 10
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function _print_xsl_title(): void {
		Template::output_view( 'sitemap/xsl/title' );
	}

	/**
	 * Outputs the XSL styles template.
	 *
	 * @hook better_seo_xsl_head 10
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function _print_xsl_styles(): void {
		Template::output_view( 'sitemap/xsl/styles' );
	}

	/**
	 * Outputs the XSL description template.
	 *
	 * @hook better_seo_xsl_description 10
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function _print_xsl_description(): void {
		Template::output_view( 'sitemap/xsl/description' );
	}

	/**
	 * Outputs the XSL content/table template.
	 *
	 * @hook better_seo_xsl_content 10
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function _print_xsl_content(): void {
		Template::output_view( 'sitemap/xsl/table' );
	}

	/**
	 * Outputs the XSL footer template if the sitemap indicator filter is enabled.
	 *
	 * @hook better_seo_xsl_footer 10
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function _print_xsl_footer(): void {

		if ( \apply_filters( 'better_seo_indicator_sitemap', true ) ) {
			Template::output_view( 'sitemap/xsl/footer' );
		}
	}

	/**
	 * Converts site icon meta tags to XSL-compatible self-closing XHTML format.
	 *
	 * Strips disallowed attributes and ensures tags are properly balanced
	 * for use within the XSL stylesheet context.
	 *
	 * @hook site_icon_meta_tags PHP_INT_MAX
	 * @since 1.0.0
	 *
	 * @param array<int, string> $tags The site icon meta tag strings.
	 * @return array<int, string> The sanitized and balanced meta tag strings.
	 */
	public static function _convert_site_icon_meta_tags( array $tags ): array {

		foreach ( $tags as &$tag ) {
			$tag = \wp_kses(
				\force_balance_tags( $tag ),
				[
					'link' => [
						'charset'  => [],
						'rel'      => [],
						'sizes'    => [],
						'href'     => [],
						'hreflang' => [],
						'media'    => [],
						'rev'      => [],
						'target'   => [],
						'type'     => [],
					],
					'meta' => [
						'content'    => [],
						'property'   => [],
						'http-equiv' => [],
						'name'       => [],
						'scheme'     => [],
					],
				],
				[],
			);
		}

		return $tags;
	}
}