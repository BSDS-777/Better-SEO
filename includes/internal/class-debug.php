<?php
/**
 * Better SEO - Internal Debug
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Internal
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

namespace Better_SEO\Internal;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\memo;

use Better_SEO\{
	Data,
	Front,
};
use Better_SEO\Helper\{
	Post_Type,
	Query,
	Taxonomy,
	Template,
};

// phpcs:disable WordPress.PHP.DevelopmentFunctions -- Debug class intentionally uses development functions.

/**
 * Class Better_SEO\Internal\Debug
 *
 * Provides debug output utilities for Better SEO, including deprecated function
 * notices, doing-it-wrong notices, inaccessible member warnings, and query
 * state debug output.
 *
 * @since 1.0.0
 */
final class Debug {

	/**
	 * Triggers a deprecated function notice.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $function    The deprecated function name.
	 * @param string      $version     The version when the function was deprecated.
	 * @param string|null $replacement The replacement function, or null if none.
	 * @return void
	 */
	public static function _deprecated_function( string $function, string $version, ?string $replacement = null ): void { // phpcs:ignore -- Copied method name convention.

		\do_action( 'deprecated_function_run', $function, $replacement, $version );

		if ( \WP_DEBUG && \apply_filters( 'deprecated_function_trigger_error', true ) ) {

			if ( isset( $replacement ) ) {
				$message = \sprintf(
					/* translators: 1: Function name, 2: 'Deprecated', 3: Plugin version, 4: Replacement function */
					\esc_html__( '%1$s is %2$s since version %3$s of Better SEO! Use %4$s instead.', 'better-seo' ),
					\esc_html( $function ),
					'<strong>' . \esc_html__( 'deprecated', 'better-seo' ) . '</strong>',
					\esc_html( $version ) ?: 'unknown',
					$replacement, // phpcs:ignore WordPress.Security.EscapeOutput -- Caller is responsible for escaping.
				);
			} else {
				$message = \sprintf(
					/* translators: 1: Function name, 2: 'Deprecated', 3: Plugin version */
					\esc_html__( '%1$s is %2$s since version %3$s of Better SEO with no alternative available.', 'better-seo' ),
					\esc_html( $function ),
					'<strong>' . \esc_html__( 'deprecated', 'better-seo' ) . '</strong>',
					\esc_html( $version ) ?: 'unknown',
				);
			}

			trigger_error(
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- combobulate_error_message escapes.
				self::combobulate_error_message( self::get_error(), $message, \E_USER_DEPRECATED ),
				\E_USER_DEPRECATED,
			);
		}
	}

	/**
	 * Triggers a doing-it-wrong notice.
	 *
	 * @since 1.0.0
	 *
	 * @param string      $function The function called incorrectly.
	 * @param string      $message  The explanation of what was done wrong.
	 * @param string|null $version  The version when the notice was added, or null.
	 * @return void
	 */
	public static function _doing_it_wrong( string $function, string $message, ?string $version = null ): void { // phpcs:ignore -- Copied method name convention.

		\do_action( 'doing_it_wrong_run', $function, $message, $version );

		if ( \WP_DEBUG && \apply_filters( 'doing_it_wrong_trigger_error', true ) ) {

			$ver_message = $version
				/* translators: 1: Plugin version */
				? \sprintf( \__( '(This message was added in v1.0.0 Better SEO.)', 'better-seo' ), $version )
				: '';

			$message = \sprintf(
				/* translators: 1: Function name, 2: 'Incorrectly', 3: Error message, 4: Plugin version notification */
				\esc_html__( '%1$s was called %2$s. %3$s %4$s', 'better-seo' ),
				\esc_html( $function ),
				'<strong>' . \esc_html__( 'incorrectly', 'better-seo' ) . '</strong>',
				$message, // phpcs:ignore WordPress.Security.EscapeOutput -- Caller is responsible for escaping.
				\esc_html( $ver_message ),
			);

			trigger_error(
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- combobulate_error_message escapes.
				self::combobulate_error_message( self::get_error(), $message, \E_USER_NOTICE ),
				\E_USER_NOTICE,
			);
		}
	}

	/**
	 * Triggers an inaccessible property or method warning.
	 *
	 * @since 1.0.0
	 *
	 * @param string $p_or_m  The inaccessible property or method name.
	 * @param string $message Optional explanation message. Default empty.
	 * @param string $handle  The facade handle name. Default 'better_seo()'.
	 * @return void
	 */
	public static function _inaccessible_p_or_m( string $p_or_m, string $message = '', string $handle = 'better_seo()' ): void {

		/**
		 * Fires when an inaccessible property or method is accessed.
		 *
		 * @since 1.0.0
		 *
		 * @param string $p_or_m  The inaccessible property or method name.
		 * @param string $message The explanation message.
		 */
		\do_action( 'better_seo_inaccessible_p_or_m_run', $p_or_m, $message );

		/**
		 * Filters whether to trigger an error for inaccessible property/method access.
		 *
		 * @since 1.0.0
		 * @param bool $trigger Whether to trigger the error. Default true.
		 */
		if ( \WP_DEBUG && \apply_filters( 'better_seo_inaccessible_p_or_m_trigger_error', true ) ) {
			$message = \sprintf(
				/* translators: 1: Method or Property name, 2: "inaccessible", 3: Class name, 4: Message */
				\esc_html__( '%1$s is %2$s in %3$s. %4$s', 'better-seo' ),
				'<code>' . \esc_html( $p_or_m ) . '</code>',
				'<strong>' . \esc_html__( 'inaccessible', 'better-seo' ) . '</strong>',
				\sprintf( '<b>%s</b>', \esc_html( $handle ) ),
				\esc_html( $message ),
			);

			trigger_error(
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- combobulate_error_message escapes.
				self::combobulate_error_message( self::get_error(), $message, \E_USER_WARNING ),
				\E_USER_WARNING,
			);
		}
	}

	/**
	 * Returns the most relevant backtrace entry for error reporting.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The backtrace entry, or empty array if unavailable.
	 */
	private static function get_error(): array {

		$backtrace = debug_backtrace( \DEBUG_BACKTRACE_PROVIDE_OBJECT, 6 );

		if ( ! $backtrace ) {
			return [];
		}

		$error = $backtrace[3];

		foreach ( \array_slice( $backtrace, 3 ) as $trace ) {
			if (
				isset( $trace['object'] )
				&& is_a( $trace['object'], better_seo_class(), false )
			) {
				$error = $trace;
				break;
			}
		}

		return $error;
	}

	/**
	 * Builds a formatted error message string for trigger_error().
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $error   The backtrace error entry.
	 * @param string               $message The error message HTML.
	 * @param int                  $code    The PHP error code (E_USER_*).
	 * @return string The formatted error message string.
	 */
	private static function combobulate_error_message( array $error, string $message, int $code ): string {

		$type = match ( $code ) {
			\E_USER_ERROR      => 'Error',
			\E_USER_DEPRECATED => 'Deprecated',
			\E_USER_WARNING    => 'Warning',
			default            => 'Notice',
		};

		$file = \esc_html( $error['file'] ?? '' );
		$line = \esc_html( $error['line'] ?? '' );

		$_message  = "'<span><strong>{$type}:</strong> {$message}";
		$_message .= $file ? " In {$file}" : '';
		$_message .= $line ? " on line {$line}" : '';
		$_message .= "</span><br>\n";

		return $_message;
	}

	/**
	 * Outputs the Better SEO debug output view.
	 *
	 * @since    1.0.0
	 * @internal Called via WordPress action hook.
	 *
	 * @return void
	 */
	public static function _do_debug_output(): void {
		Template::output_view( 'debug/output' );
	}

	/**
	 * Outputs the Better SEO debug header view for the current admin screen.
	 *
	 * @since    1.0.0
	 * @internal Called via WordPress action hook.
	 *
	 * @return void
	 */
	public static function _output_debug_header(): void {

		if ( \is_admin() && ! Query::is_term_edit() && ! Query::is_post_edit() && ! Query::is_seo_settings_page( true ) ) {
			return;
		}

		if ( Query::is_seo_settings_page( true ) ) {
			/**
			 * Filters the current object ID to the front page ID on the SEO settings screen.
			 *
			 * @since 1.0.0
			 */
			\add_filter( 'better_seo_current_object_id', static fn(): int => Query::get_the_front_page_id() );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput -- Template::output_view() handles escaping internally.
		Template::output_view( 'debug/header' );
	}

	/**
	 * Outputs the current WordPress query debug output.
	 *
	 * @since    1.0.0
	 * @internal Called via WordPress action hook.
	 *
	 * @return void
	 */
	public static function _output_debug_query(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput -- get_debug_query_output() escapes all values.
		echo self::get_debug_query_output();
	}

	/**
	 * Outputs the cached WordPress query debug output from meta generation time.
	 *
	 * @since    1.0.0
	 * @internal Called via WordPress action hook.
	 *
	 * @return void
	 */
	public static function _output_debug_query_from_cache(): void {
		// phpcs:ignore WordPress.Security.EscapeOutput -- get_debug_query_output_from_cache() escapes all values.
		echo self::get_debug_query_output_from_cache();
	}

	/**
	 * Pre-populates the debug query output cache at meta generation time.
	 *
	 * @since    1.0.0
	 * @internal Called via WordPress action hook.
	 *
	 * @return void
	 */
	public static function _set_debug_query_output_cache(): void {
		self::get_debug_query_output_from_cache();
	}

	/**
	 * Returns the cached debug query output from meta generation time, memoized.
	 *
	 * @since 1.0.0
	 *
	 * @return string The cached debug query output HTML.
	 */
	private static function get_debug_query_output_from_cache(): string {
		return memo() ?? memo( self::get_debug_query_output( 'yup' ) );
	}

	/**
	 * Generates and returns the WordPress query state debug output HTML.
	 *
	 * Collects all relevant query state variables, formats them as an HTML
	 * debug panel, and returns the result. Timing information is included.
	 *
	 * @since 1.0.0
	 *
	 * @param string $cache_version Whether this is a cached ('yup') or live ('nope') output.
	 * @return string The debug output HTML string.
	 */
	private static function get_debug_query_output( string $cache_version = 'nope' ): string {

		// Start timer.
		$_t = hrtime( true );

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase -- Debug variable names match WP query property names.
		$page_id                        = Query::get_the_real_id();
		$is_query_exploited             = Query\Utils::is_query_exploited();
		$query_supports_seo             = Query\Utils::query_supports_seo();
		$is_404                         = \is_404();
		$is_admin                       = \is_admin();
		$is_attachment                  = Query::is_attachment();
		$is_archive                     = Query::is_archive();
		$is_term_edit                   = Query::is_term_edit();
		$is_post_edit                   = Query::is_post_edit();
		$is_wp_lists_edit               = Query::is_wp_lists_edit();
		$is_author                      = Query::is_author();
		$is_category                    = Query::is_category();
		$is_date                        = \is_date();
		$is_year                        = \is_year();
		$is_month                       = \is_month();
		$is_day                         = \is_day();
		$is_feed                        = \is_feed();
		$is_robots                      = \is_robots();
		$is_real_front_page             = Query::is_real_front_page();
		$is_blog                        = Query::is_blog();
		$is_blog_as_page                = Query::is_blog_as_page();
		$is_page                        = Query::is_page();
		$page                           = Query::page();
		$paged                          = Query::paged();
		$is_preview                     = Query::is_preview();
		$is_customize_preview           = \is_customize_preview();
		$is_search                      = Query::is_search();
		$is_single                      = Query::is_single();
		$is_singular                    = Query::is_singular();
		$is_static_front_page           = Query::is_static_front_page();
		$is_tag                         = Query::is_tag();
		$is_tax                         = Query::is_tax();
		$is_shop                        = Query::is_shop();
		$is_product                     = Query::is_product();
		$is_seo_settings_page           = Query::is_seo_settings_page( true );
		$numpages                       = Query::numpages();
		$is_multipage                   = Query::is_multipage();
		$is_singular_archive            = Query::is_singular_archive();
		$is_term_meta_capable           = Query::is_editable_term();
		$is_post_type_supported         = Post_Type::is_supported();
		$is_post_type_archive_supported = Post_Type::is_pta_supported();
		$has_page_on_front              = Query\Utils::has_page_on_front();
		$has_assigned_page_on_front     = Query\Utils::has_assigned_page_on_front();
		$has_blog_page                  = Query\Utils::has_blog_page();
		$is_taxonomy_supported          = Taxonomy::is_supported();
		$get_post_type                  = \get_post_type();
		$get_post_type_real_id          = Query::get_post_type_real_id();
		$admin_post_type                = Query::get_admin_post_type();
		$current_taxonomy               = Query::get_current_taxonomy();
		$current_post_type              = Query::get_current_post_type();
		$is_taxonomy_disabled           = Taxonomy::is_disabled();
		$is_post_type_archive           = \is_post_type_archive();
		$is_protected                   = Data\Post::is_protected( $page_id );
		$wp_doing_ajax                  = \wp_doing_ajax();
		$wp_doing_cron                  = \wp_doing_cron();
		$wp_is_rest                     = \defined( 'REST_REQUEST' ) && \REST_REQUEST;
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

		$timer = ( hrtime( true ) - $_t ) / 1e9;

		$vars = get_defined_vars();

		unset( $vars['timer'], $vars['_t'] );

		$current     = array_filter( $vars );
		$not_current = array_diff_key( $vars, $current );

		ksort( $current );
		ksort( $not_current );

		$output = '';

		foreach ( $current as $name => $value ) {
			$type = \esc_html( '(' . \gettype( $value ) . ')' );
			$name = \esc_html( $name );

			if ( \is_bool( $value ) ) {
				$value = $value ? 'true' : 'false';
			} else {
				$value = \esc_html( var_export( $value, true ) );
			}

			$output .= "<span style=background:#dadada>{$name} => <span style=color:#>{$type} {$value}</span></span>\n";
		}

		foreach ( $not_current as $name => $value ) {
			$type = \esc_html( '(' . \gettype( $value ) . ')' );
			$name = \esc_html( $name );

			if ( \is_bool( $value ) ) {
				$value = $value ? 'true' : 'false';
			} else {
				$value = \esc_html( var_export( $value, true ) );
			}

			$output .= "{$name} => <span style=color:#0a00f0>{$type} {$value}</span>\n";
		}

		$title = 'yup' === $cache_version
			? 'WordPress Query at Meta Generation'
			: ( \is_admin() ? 'Current WordPress Admin Query' : 'Current WordPress Query' );

		$output = str_replace( [ "\r\n", "\r", "\n" ], "<br>\n", $output );
		$timer  = number_format( number_format( $timer, 5 ), 5 );

		return <<<HTML
			<div style="display:block;width:100%;background:#;color:#;border-bottom:1px solid #">
				<div style="display:inline-block;width:100%;padding: 0;margin: 0;border-bottom:1px solid #">
					<h2 style="color:#;font-size:22px;padding: 0;margin: 0">{$title}</h2>
				</div>
				<div style="display:inline-block;width:100%;padding: 0;border-bottom:1px solid #">
					Generated in: {$timer} seconds
				</div>
				<div style="display:inline-block;width:100%;padding: 0;font-size:14px">
					{$output}
				</div>
			</div>
		HTML;
	}
}