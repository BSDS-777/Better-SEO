<?php
/**
 * Better SEO - RobotsTXT Main
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\RobotsTXT
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

namespace Better_SEO\RobotsTXT;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\{
	Data,
	Helper\Compatibility,
	Helper\Query,
	Meta,
	RobotsTXT,
	Sitemap,
};

/**
 * Class Better_SEO\RobotsTXT\Main
 *
 * Generates the robots.txt content for Better SEO, including default directives,
 * AI/SEO bot blocking, sitemap references, and deprecated filter support.
 *
 * @since 1.0.0
 */
class Main {

	/**
	 * Returns the generated robots.txt content string.
	 *
	 * Builds the robots.txt from registered sections, sorts by priority,
	 * and applies the better_seo_robots_txt filter.
	 *
	 * @since 1.0.0
	 *
	 * @return string The complete robots.txt content string.
	 */
	public static function get_robots_txt(): string {

		$output = '';

		// Warn if robots.txt is being served from a non-root location.
		$request_uri = rawurldecode( stripslashes( $_SERVER['REQUEST_URI'] ?? '' ) );
		if ( strrpos( $request_uri, '/' ) > 0 ) {
			$correct_location = \esc_url(
				\trailingslashit( Meta\URI\Utils::set_preferred_url_scheme(
					Meta\URI\Utils::get_site_host()
				) ) . 'robots.txt',
			);

			$output .= "# This is an invalid robots.txt location.\n# Please visit: {$correct_location}\n\n";
		}

		$site_path = parse_url( \site_url(), \PHP_URL_PATH ) ?: '';

		// Check deprecated filter — replaced by better_seo_robots_txt_sections.
		$disallow_queries = \apply_filters_deprecated(
			'better_seo_robots_disallow_queries',
			[ false ],
			'1.0.0 of Better SEO',
			'better_seo_robots_txt_sections'
		) ? '/*?*'
		  : '';

		$sitemaps = [];

		if ( Data\Plugin::get_option( 'sitemaps_robots' ) ) {
			if ( Data\Plugin::get_option( 'sitemaps_output' ) ) {
				foreach ( Sitemap\Registry::get_sitemap_endpoint_list() as $id => $data ) {
					if ( ! empty( $data['robots'] ) ) {
						$sitemaps[] = \esc_url( Sitemap\Registry::get_expected_sitemap_endpoint_url( $id ) );
					}
				}
			} elseif ( ! Compatibility::get_active_conflicting_plugin_types()['sitemaps'] && Sitemap\Utils::use_core_sitemaps() ) {
				$wp_sitemaps_server = \wp_sitemaps_get_server();

				if ( method_exists( $wp_sitemaps_server, 'add_robots' ) ) {
					$sitemaps[] = trim( "\n", \wp_sitemaps_get_server()->add_robots( '', Data\Blog::is_public() ) );
				}
			}
		}

		/**
		 * Filters the Better SEO robots.txt sections.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, array<string, mixed>> $sections  The robots.txt sections map.
		 * @param string                              $site_path The site path prefix.
		 */
		$robots_sections = (array) \apply_filters(
			'better_seo_robots_txt_sections',
			[
				'deprecated_before' => [
					// Check deprecated pre-output filter — replaced by better_seo_robots_txt_sections.
					'raw'      => (string) \apply_filters_deprecated(
						'better_seo_robots_txt_pre',
						[ '' ],
						'1.0.0 of Better SEO',
						'better_seo_robots_txt_sections',
					),
					'priority' => 0,
				],
				'default'           => [
					'user-agent' => [ '*' ],
					'disallow'   => [ "{$site_path}/wp-admin/", $disallow_queries ],
					'allow'      => [ "{$site_path}/wp-admin/admin-ajax.php" ],
				],
				'block_ai'          => Data\Plugin::get_option( 'robotstxt_block_ai' ) ? [
					'user-agent' => array_keys( RobotsTXT\Utils::get_blocked_user_agents( 'ai' ) ),
					'disallow'   => [ '/' ],
				] : [],
				'block_seo'         => Data\Plugin::get_option( 'robotstxt_block_seo' ) ? [
					'user-agent' => array_keys( RobotsTXT\Utils::get_blocked_user_agents( 'seo' ) ),
					'disallow'   => [ '/' ],
				] : [],
				'deprecated_after'  => [
					// Check deprecated post-output filter — replaced by better_seo_robots_txt_sections.
					'raw'      => (string) \apply_filters_deprecated(
						'better_seo_robots_txt_pro',
						[ '' ],
						'1.0.0 of Better SEO',
						'better_seo_robots_txt_sections',
					),
					'priority' => 500,
				],
				'sitemaps'          => [
					'sitemaps' => $sitemaps,
					'priority' => 1000,
				],
			],
			$site_path,
		);

		usort( $robots_sections, static fn( array $a, array $b ): int => ( $a['priority'] ?? 10 ) <=> ( $b['priority'] ?? 10 ) );

		$pieces     = [];
		$directives = [
			'user-agent' => 'User-agent',
			'disallow'   => 'Disallow',
			'allow'      => 'Allow',
			'sitemaps'   => 'Sitemap',
		];

		foreach ( $robots_sections as $section ) {
			$piece = '';

			if ( isset( $section['raw'] ) ) {
				$piece .= $section['raw'];
			}

			if ( ! empty( $section['user-agent'] ) || ! empty( $section['sitemaps'] ) ) {
				foreach ( $directives as $key => $directive ) {
					foreach ( $section[ $key ] ?? [] as $value ) {
						$piece .= \strlen( $value ) ? "{$directive}: {$value}\n" : '';
					}
				}
			}

			if ( \strlen( $piece ) ) {
				$pieces[] = $piece;
			}
		}

		$output .= implode( "\n", $pieces );

		/**
		 * Filters the Better SEO robots.txt output.
		 *
		 * @since 1.0.0
		 *
		 * @param string $output The complete robots.txt content string.
		 */
		return (string) \apply_filters( 'better_seo_robots_txt', $output );
	}
}