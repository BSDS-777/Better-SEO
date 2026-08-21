<?php
/**
 * Better SEO - Sitemap Registry
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Sitemap
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

namespace Better_SEO\Sitemap;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\{
	memo,
	has_run,
};

use Better_SEO\{
	Data,
	Helper,
	Helper\Query,
	Helper\Template,
	Meta,
};

/**
 * Class Better_SEO\Sitemap\Registry
 *
 * Handles sitemap endpoint registration, routing, output, and cache management
 * for Better SEO. Intercepts early WordPress requests to serve XML sitemaps
 * and XSL stylesheets before the main WordPress query runs.
 *
 * @since 1.0.0
 */
class Registry {

	/**
	 * Initializes the sitemap registry by detecting and routing sitemap requests.
	 *
	 * Parses the raw request URI against registered sitemap endpoints and,
	 * if a match is found, dispatches the appropriate sitemap callback.
	 *
	 * @hook parse_request 1
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function _init(): void {

		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$raw_uri = rawurldecode(
				\wp_check_invalid_utf8( stripslashes( $_SERVER['REQUEST_URI'] ) ),
			) ?: '/';
		} else {
			$raw_uri = '/';
		}

		// Root path — no sitemap to serve.
		if ( '/' === $raw_uri ) {
			return;
		}

		$path_info = self::get_sitemap_base_path_info();

		// Build a regex to match the sitemap base path prefix.
		$path_regex = '/^' . preg_quote( rawurldecode( $path_info['path'] ), '/' ) . '/ui';

		// URI doesn't start with the sitemap base path — not a sitemap request.
		if ( ! preg_match( $path_regex, $raw_uri ) ) {
			return;
		}

		$stripped_uri = preg_replace( $path_regex, '', rtrim( $raw_uri, '/' ) );

		// Nothing left after stripping the base path — not a valid sitemap endpoint.
		if ( ! $stripped_uri ) {
			return;
		}

		// Match the stripped URI against registered sitemap endpoints.
		if ( $path_info['use_query_var'] ) {
			foreach ( self::get_sitemap_endpoint_list() as $_id => $_data ) {
				$_regex = '/^' . preg_quote( $_id, '/' ) . '/i';

				if ( preg_match( $_regex, $stripped_uri ) ) {
					$sitemap_id = $_id;
					break;
				}
			}
		} else {
			foreach ( self::get_sitemap_endpoint_list() as $_id => $_data ) {
				if ( preg_match( $_data['regex'], $stripped_uri ) ) {
					$sitemap_id = $_id;
					break;
				}
			}
		}

		// No matching sitemap endpoint found.
		if ( empty( $sitemap_id ) ) {
			return;
		}

		// Mark the current request as a sitemap request and override query parameters.
		Query::is_sitemap( true );
		\add_action( 'pre_get_posts', [ self::class, '_override_query_parameters' ] );

		self::clean_up_globals();

		/**
		 * Fires when a sitemap request is detected, before the sitemap is output.
		 *
		 * @since 1.0.0
		 * @param string $sitemap_id The matched sitemap endpoint ID.
		 */
		\do_action( 'better_seo_sitemap_header', $sitemap_id );

		\call_user_func( self::get_sitemap_endpoint_list()[ $sitemap_id ]['callback'], $sitemap_id );
	}

	/**
	 * Overrides WP_Query parameters to prevent the sitemap from being treated as a home page request.
	 *
	 * @hook pre_get_posts 10
	 * @since 1.0.0
	 *
	 * @param \WP_Query $wp_query The current WP_Query instance.
	 * @return void
	 */
	public static function _override_query_parameters( \WP_Query $wp_query ): void {
		$wp_query->is_home    = false;
		$wp_query->is_sitemap = true;
	}

	/**
	 * Returns the expected URL for a registered sitemap endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id The sitemap endpoint ID. Default 'base'.
	 * @return string|false The sanitized sitemap URL, or false if the endpoint is not registered.
	 */
	public static function get_expected_sitemap_endpoint_url( string $id = 'base' ): string|false {

		$list = self::get_sitemap_endpoint_list();

		if ( ! isset( $list[ $id ] ) ) {
			return false;
		}

		$host      = Meta\URI\Utils::set_preferred_url_scheme( Meta\URI\Utils::get_site_host() );
		$path_info = self::get_sitemap_base_path_info();

		return \sanitize_url(
			$path_info['use_query_var']
				? "{$host}{$path_info['path']}{$id}"
				: "{$host}{$path_info['path']}{$list[ $id ]['endpoint']}",
		);
	}

	/**
	 * Returns the registered sitemap endpoint list, memoized.
	 *
	 * Applies the better_seo_sitemap_endpoint_list filter to allow third-party
	 * plugins to register additional sitemap endpoints.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string, mixed>> The sitemap endpoint list.
	 */
	public static function get_sitemap_endpoint_list(): array {
		return memo() ?? memo(
			/**
			 * Filters the Better SEO sitemap endpoint list.
			 *
			 * @since 1.0.0
			 *
			 * @param array<string, array<string, mixed>> $endpoints The registered sitemap endpoints.
			 */
			(array) \apply_filters(
				'better_seo_sitemap_endpoint_list',
				[
					'base'           => [
						'lock_id'  => 'base',
						'cache_id' => 'base',
						'endpoint' => 'sitemap.xml',
						'regex'    => '/^sitemap\.xml/i',
						'callback' => [ self::class, 'output_base_sitemap' ],
						'robots'   => true,
					],
					'index'          => [
						'lock_id'  => 'base',
						'cache_id' => 'base',
						'endpoint' => 'sitemap_index.xml',
						'regex'    => '/^sitemap_index\.xml/i',
						'callback' => [ self::class, 'output_base_sitemap' ],
						'robots'   => false,
					],
					'xsl-stylesheet' => [
						'lock_id'  => false,
						'cache_id' => false,
						'endpoint' => 'sitemap.xsl',
						'regex'    => '/^sitemap\.xsl/i',
						'callback' => [ self::class, 'output_stylesheet' ],
						'robots'   => false,
					],
				],
			),
		);
	}

	/**
	 * Clears all sitemap caches and schedules a cron prerender event.
	 *
	 * Uses has_run() to ensure this only executes once per request.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if sitemaps were refreshed, false if already run.
	 */
	public static function refresh_sitemaps(): bool {

		if ( has_run( __METHOD__ ) ) {
			return false;
		}

		Cache::clear_sitemap_caches();

		/**
		 * Fires after the sitemap transient cache is cleared.
		 *
		 * @since 1.0.0
		 * @param array<mixed> $args Empty args array (reserved for future use).
		 */
		\do_action( 'better_seo_sitemap_transient_cleared', [] );

		Cron::schedule_single_event();

		return true;
	}

	/**
	 * Refreshes sitemaps when a post is saved or updated.
	 *
	 * Skips post revisions and invalid post IDs.
	 *
	 * @hook save_post 10
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID being saved.
	 * @return bool True if sitemaps were refreshed, false otherwise.
	 */
	public static function _refresh_sitemap_on_post_change( int $post_id ): bool {

		if ( ! $post_id || \wp_is_post_revision( $post_id ) ) {
			return false;
		}

		return self::refresh_sitemaps();
	}

	/**
	 * Refreshes sitemaps when the permalink structure or category base is updated.
	 *
	 * Verifies the admin referer before refreshing.
	 *
	 * @hook update_option_permalink_structure 10
	 * @since 1.0.0
	 *
	 * @return bool True if sitemaps were refreshed, false otherwise.
	 */
	public static function _refresh_sitemap_transient_permalink_updated(): bool {

		if (
			   ( isset( $_POST['permalink_structure'] ) || isset( $_POST['category_base'] ) )
			&& \check_admin_referer( 'update-permalink' )
		) {
			return self::refresh_sitemaps();
		}

		return false;
	}

	/**
	 * Outputs the base XML sitemap for the given sitemap endpoint ID.
	 *
	 * Checks for an active sitemap lock before outputting. Sends appropriate
	 * HTTP headers and outputs the sitemap XML view, then exits.
	 *
	 * @since 1.0.0
	 *
	 * @param string $sitemap_id The sitemap endpoint ID. Default 'base'.
	 * @return void
	 */
	public static function output_base_sitemap( string $sitemap_id = 'base' ): void {

		if ( Lock::is_sitemap_locked( $sitemap_id ) ) {
			Lock::output_locked_header( $sitemap_id );
			exit;
		}

		Helper\Headers::clean_response_header();

		if ( ! headers_sent() ) {
			\status_header( 200 );
			header( 'Content-type: text/xml; charset=utf-8', true );
		}

		Template::output_view( 'sitemap/xml-sitemap', $sitemap_id );
		echo "\n";

		exit;
	}

	/**
	 * Outputs the XSL stylesheet for the sitemap.
	 *
	 * Sends appropriate HTTP headers, registers XSL hooks, outputs the
	 * stylesheet view, then exits.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function output_stylesheet(): void {

		Helper\Headers::clean_response_header();

		if ( ! headers_sent() ) {
			\status_header( 200 );
			header( 'Content-type: text/xsl; charset=utf-8', true );
			header( 'Cache-Control: max-age=1600', true );
		}

		Optimized\XSL::register_hooks();

		Template::output_view( 'sitemap/xsl-stylesheet' );
		exit;
	}

	/**
	 * Outputs the XML declaration and optional XSL stylesheet processing instruction.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function output_sitemap_header(): void {

		echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";

		if ( Data\Plugin::get_option( 'sitemap_styles' ) ) {
			printf(
				'<?xml-stylesheet type="text/xsl" href="%s"?>' . "\n",
				self::get_expected_sitemap_endpoint_url( 'xsl-stylesheet' ),
			);
		}
	}

	/**
	 * Outputs the opening <urlset> tag with all registered XML namespace declarations.
	 *
	 * Applies the better_seo_sitemap_schemas filter to allow additional namespaces.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function output_sitemap_urlset_open_tag(): void {

		$schemas = [
			'xmlns'              => 'http://www.sitemaps.org/schemas/sitemap/0.9',
			'xmlns:xhtml'        => 'http://www.w3.org/1999/xhtml',
			'xmlns:xsi'          => 'http://www.w3.org/2001/XMLSchema-instance',
			'xsi:schemaLocation' => [
				'http://www.sitemaps.org/schemas/sitemap/0.9',
				'http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd',
			],
		];

		/**
		 * Filters the Better SEO sitemap XML namespace schemas.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string|array<int, string>> $schemas The namespace schema declarations.
		 */
		$schemas = (array) \apply_filters( 'better_seo_sitemap_schemas', $schemas );

		array_walk(
			$schemas,
			function ( mixed &$schema, string $key ): void {
				$schema = \sprintf( '%s="%s"', $key, implode( ' ', (array) $schema ) );
			}
		);

		printf( "<urlset %s>\n", implode( ' ', $schemas ) );
	}

	/**
	 * Outputs the closing </urlset> tag.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function output_sitemap_urlset_close_tag(): void {
		echo '</urlset>';
	}

	/**
	 * Returns the sitemap base path (site path without trailing slash).
	 *
	 * Applies the better_seo_sitemap_base_path filter.
	 *
	 * @since 1.0.0
	 *
	 * @return string The sitemap base path.
	 */
	private static function get_sitemap_base_path(): string {
		/**
		 * Filters the Better SEO sitemap base path.
		 *
		 * @since 1.0.0
		 * @param string $base_path The site path without trailing slash.
		 */
		return \apply_filters(
			'better_seo_sitemap_base_path',
			rtrim(
				Meta\URI\Utils::get_site_path(),
				'/',
			),
		);
	}

	/**
	 * Returns the sitemap path prefix.
	 *
	 * Applies the better_seo_sitemap_path_prefix filter.
	 *
	 * @since 1.0.0
	 *
	 * @return string The sitemap path prefix. Default '/'.
	 */
	private static function get_sitemap_path_prefix(): string {
		/**
		 * Filters the Better SEO sitemap path prefix.
		 *
		 * @since 1.0.0
		 * @param string $prefix The sitemap path prefix.
		 */
		return \apply_filters( 'better_seo_sitemap_path_prefix', '/' );
	}

	/**
	 * Returns the sitemap base path info array, including the full path and whether
	 * a query variable is used (for non-pretty-permalink installations).
	 *
	 * @since 1.0.0
	 *
	 * @return array{path: string, use_query_var: bool} The path info array.
	 */
	private static function get_sitemap_base_path_info(): array {
		global $wp_rewrite;

		$base_path = self::get_sitemap_base_path();
		$prefix    = self::get_sitemap_path_prefix();

		$use_query_var = false;

		if ( $wp_rewrite->using_index_permalinks() ) {
			$path = "{$base_path}/index.php{$prefix}";
		} elseif ( $wp_rewrite->using_permalinks() ) {
			$path = "{$base_path}{$prefix}";
		} else {
			// Plain permalink structure — use query variable routing.
			$path = "{$base_path}{$prefix}?better-seo-sitemap=";

			$use_query_var = true;
		}

		return compact( 'path', 'use_query_var' );
	}

	/**
	 * Returns the amount of memory freed by the last clean_up_globals() call.
	 *
	 * @since 1.0.0
	 *
	 * @return int The number of bytes freed, or 0 if not yet measured.
	 */
	public static function get_freed_memory(): int {
		return self::clean_up_globals( true );
	}

	/**
	 * Removes unnecessary globals to free memory before sitemap output.
	 *
	 * When $get_freed_memory is true, returns the previously memoized freed memory
	 * value without performing any cleanup.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $get_freed_memory Whether to return the freed memory value instead of cleaning. Default false.
	 * @return int The number of bytes freed (when $get_freed_memory is false), or the memoized value (when true).
	 */
	private static function clean_up_globals( bool $get_freed_memory = false ): int {

		if ( $get_freed_memory ) {
			return memo() ?? 0;
		}

		$memory = memory_get_usage();

		$remove = [
			'wp_filter' => [
				'wp_head',
				'admin_head',
				'the_content',
				'the_content_feed',
				'the_excerpt_rss',
				'wp_footer',
				'admin_footer',
				'widgets_init',
			],
			'wp_registered_widgets',
			'wp_registered_sidebars',
			'wp_registered_widget_updates',
			'wp_registered_widget_controls',
			'_wp_deprecated_widgets_callbacks',
			'posts',
		];

		foreach ( $remove as $key => $value ) {
			if ( \is_array( $value ) ) {
				foreach ( $value as $v ) {
					unset( $GLOBALS[ $key ][ $v ] );
				}
			} else {
				unset( $GLOBALS[ $value ] );
			}
		}

		\remove_all_shortcodes();

		return memo( $memory - memory_get_usage() );
	}
}