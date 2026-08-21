<?php
/**
 * Better SEO - Helper Compatibility
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Helper
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

namespace Better_SEO\Helper;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\memo;

use Better_SEO\{
	Admin,
	Data,
};

/**
 * Class Better_SEO\Helper\Compatibility
 *
 * Provides plugin and theme compatibility detection utilities for Better SEO,
 * including conflict detection, active plugin type checks, and builder detection.
 *
 * @since 1.0.0
 */
final class Compatibility {

	/**
	 * Registers a persistent admin notice if conflicting SEO plugins are detected.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function try_plugin_conflict_notification(): void {

		if ( ! self::get_active_conflicting_plugin_types( true )['seo_tools'] ) {
			return;
		}

		Admin\Notice\Persistent::register_notice(
			\__( 'Multiple SEO plugins have been detected. You should only use one.', 'better-seo' ),
			'seo-plugin-conflict',
			[ 'type' => 'warning' ],
			[
				'screens'    => [ 'edit', 'edit-tags', 'dashboard', 'plugins', 'toplevel_page_better-seo-settings' ],
				'capability' => 'activate_plugins',
				'count'      => 3,
				'timeout'    => -1,
			],
		);
	}

	/**
	 * Clears the SEO plugin conflict persistent notice.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function clear_plugin_conflict_notification(): void {
		Admin\Notice\Persistent::clear_notice( 'seo-plugin-conflict' );
	}

	/**
	 * Returns the list of known conflicting plugins grouped by type.
	 *
	 * Applies the better_seo_conflicting_plugins filter for extensibility.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, string>> Map of conflict type to plugin name => file path.
	 */
	public static function get_conflicting_plugins(): array {

		$conflicting_plugins = [
			'seo_tools'    => [
				'Yoast SEO'           => 'wordpress-seo/wp-seo.php',
				'All in One SEO Pack' => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
				'SEO Ultimate'        => 'seo-ultimate/seo-ultimate.php',
				'SEOPress'            => 'wp-seopress/seopress.php',
				'Rank Math'           => 'seo-by-rank-math/rank-math.php',
				'Smart Crawl'         => 'smartcrawl-seo/wpmu-dev-seo.php',
			],
			'sitemaps'     => [
				'Google XML Sitemaps'             => 'google-sitemap-generator/sitemap.php',
				'XML Sitemap & Google News feeds' => 'xml-sitemap-feed/xml-sitemap.php',
				'Google Sitemap by BestWebSoft'   => 'google-sitemap-plugin/google-sitemap-plugin.php',
			],
			'open_graph'   => [
				'Facebook Open Graph Meta Tags for WordPress' => 'wonderm00ns-simple-facebook-open-graph-tags/wonderm00n-open-graph.php',
				'Open Graph'                            => 'opengraph/opengraph.php',
				'Open Graph Protocol Framework'         => 'open-graph-protocol-framework/open-graph-protocol-framework.php',
				'Shareaholic2'                          => 'shareaholic/sexy-bookmarks.php',
				'WordPress Social Sharing Optimization' => 'wpsso/wpsso.php',
			],
			'twitter_card' => [],
			'schema'       => [],
			'multilingual' => [
				'Polylang'       => 'polylang/polylang.php',
				'Polylang Pro'   => 'polylang-pro/polylang.php',
				'WPML'           => 'sitepress-multilingual-cms/sitepress.php',
				'TranslatePress' => 'translatepress-multilingual/index.php',
				'WPGlobus'       => 'wpglobus/wpglobus.php',
			],
		];

		/**
		 * Filters the list of conflicting plugins for Better SEO.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, array<string, string>> $conflicting_plugins Map of conflict type to plugins.
		 */
		return (array) \apply_filters( 'better_seo_conflicting_plugins', $conflicting_plugins );
	}

	/**
	 * Returns the active conflicting plugin types, memoized.
	 *
	 * When a full SEO tool conflict is detected, sitemaps, open_graph,
	 * twitter_card, and schema are also marked as conflicting.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $refresh Whether to bypass the memo cache. Default false.
	 * @return array<string, bool> Map of conflict type to whether it is active.
	 */
	public static function get_active_conflicting_plugin_types( bool $refresh = false ): array {

		if ( ! $refresh && null !== $memo = memo() ) {
			return $memo;
		}

		$conflicting_types = [
			'seo_tools'    => false,
			'sitemaps'     => false,
			'open_graph'   => false,
			'twitter_card' => false,
			'schema'       => false,
			'multilingual' => false,
		];

		$active_plugins = Data\Blog::get_active_plugins();

		foreach ( self::get_conflicting_plugins() as $type => $plugins ) {
			if ( array_intersect( $plugins, $active_plugins ) ) {
				$conflicting_types[ $type ] = true;
			}
		}

		if ( $conflicting_types['seo_tools'] ) {
			$conflicting_types = array_merge(
				$conflicting_types,
				[
					'sitemaps'     => true,
					'open_graph'   => true,
					'twitter_card' => true,
					'schema'       => true,
				],
			);
		}

		return memo( $conflicting_types );
	}

	/**
	 * Returns whether all specified plugin dependencies (globals, constants, functions, classes, methods) exist.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<int, string|array<int, string>>> $plugins {
	 *     Dependency arrays to check.
	 *     @type array<int, string>              $globals   Global variable names to check.
	 *     @type array<int, string>              $constants Constant names to check.
	 *     @type array<int, string>              $functions Function names to check.
	 *     @type array<int, string>              $classes   Class names to check.
	 *     @type array<int, array<int, string>>  $methods   [object/class, method] pairs to check.
	 * }
	 * @return bool True if all dependencies exist, false otherwise.
	 */
	public static function can_i_use( array $plugins = [] ): bool {

		foreach ( $plugins['globals'] ?? [] as $name ) {
			if ( ! isset( $GLOBALS[ $name ] ) ) {
				return false;
			}
		}

		foreach ( $plugins['constants'] ?? [] as $name ) {
			if ( ! \defined( $name ) ) {
				return false;
			}
		}

		foreach ( $plugins['functions'] ?? [] as $name ) {
			if ( ! \function_exists( $name ) ) {
				return false;
			}
		}

		foreach ( $plugins['classes'] ?? [] as $name ) {
			if ( ! class_exists( $name, false ) ) { // phpcs:ignore -- we don't autoload.
				return false;
			}
		}

		foreach ( $plugins['methods'] ?? [] as [ $object, $name ] ) {
			if ( ! method_exists( $object, $name ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Returns whether any of the given themes are currently active.
	 *
	 * @since 1.0.0
	 *
	 * @param string|array<int, string> $themes Theme slug(s) to check.
	 * @return bool True if any of the given themes are active.
	 */
	public static function is_theme_active( string|array $themes = '' ): bool {

		$active_themes = Data\Blog::get_active_themes();

		foreach ( (array) $themes as $theme ) {
			if ( \in_array( strtolower( $theme ), $active_themes, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns whether a non-HTML page builder is currently active, memoized.
	 *
	 * Detects Divi (ET Builder), WPBakery (Visual Composer), and Bricks Builder.
	 * Applies the better_seo_shortcode_based_page_builder_active filter.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if a non-HTML page builder is active.
	 */
	public static function is_non_html_builder_active(): bool {
		return memo() ?? memo(
			/**
			 * Filters whether a non-HTML (shortcode-based) page builder is active.
			 *
			 * @since 1.0.0
			 * @param bool $is_active Whether a non-HTML page builder is active.
			 */
			(bool) \apply_filters(
				'better_seo_shortcode_based_page_builder_active',
				(
					   \defined( 'ET_BUILDER_VERSION' )
					|| \defined( 'WPB_VC_VERSION' )
					|| \defined( 'BRICKS_VERSION' )
				),
			)
		);
	}
}