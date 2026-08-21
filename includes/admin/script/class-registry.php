<?php
/**
 * Better SEO - Admin Script Registry
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Admin\Script
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

namespace Better_SEO\Admin\Script;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\{
	has_run,
	umemo,
	is_headless,
};

use Better_SEO\Data;
use Better_SEO\Helper\{
	Format,
	Post_Type,
	Query,
	Taxonomy,
	Template,
};

/**
 * Class Better_SEO\Admin\Script\Registry
 *
 * Registers and outputs all Better SEO admin scripts and styles.
 * Relies on WP_Dependencies to prevent duplicate loading and handle autoloading.
 *
 * @since 1.0.0
 * @access protected
 *         Use better_seo()->admin() instead.
 *
 * @see \WP_Styles
 * @see \WP_Scripts
 * @see \WP_Dependencies
 * @see \Better_SEO\Admin\Script\Loader
 */
final class Registry {

	/**
	 * Bitmask flag: script has been registered.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	public const REGISTERED = 0b01;

	/**
	 * Bitmask flag: script has been loaded/enqueued.
	 *
	 * @since 1.0.0
	 * @var   int
	 */
	public const LOADED = 0b10;

	/**
	 * Registered script definitions.
	 *
	 * @since 1.0.0
	 * @var   array<int, array<string, mixed>>
	 */
	private static array $scripts = [];

	/**
	 * Registered script templates.
	 *
	 * @since 1.0.0
	 * @var   array<string, array<int, array{0: string, 1: array<string, mixed>}>>
	 */
	private static array $templates = [];

	/**
	 * Script registration and load status queue.
	 *
	 * @since 1.0.0
	 * @var   array<string, array<string, int>>
	 */
	private static array $queue = [];

	/**
	 * Initializes script registration based on the current admin context.
	 *
	 * @since    1.0.0
	 * @internal Called via WordPress admin hooks.
	 *
	 * @return void
	 */
	public static function _init(): void {

		$register = (
			Query::is_seo_settings_page()
			|| Data\Plugin::get_site_cache( 'persistent_notices' )
			|| (
				! is_headless( 'meta' )
				&& (
					( Query::is_archive_admin() && Taxonomy::is_supported() )
					|| ( Query::is_singular_admin() && Post_Type::is_supported() )
				)
			)
		);

		if ( \apply_filters( 'better_seo_register_scripts', $register ) ) {
			self::register_scripts_and_hooks();
		}
	}

	/**
	 * Registers all Better SEO scripts and WordPress action/filter hooks.
	 *
	 * Safe to call multiple times — uses has_run() to prevent duplicate registration.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_scripts_and_hooks(): void {

		if ( has_run( __METHOD__ ) ) {
			return;
		}

		if ( \did_action( 'admin_enqueue_scripts' ) ) {
			Loader::init();
		}

		if ( \did_action( 'in_admin_header' ) ) {
			self::footer_enqueue();
		}

		\add_action( 'admin_enqueue_scripts', [ Loader::class, 'init' ], 0 );
		\add_filter( 'admin_body_class', [ self::class, '_add_body_class' ] );
		\add_action( 'in_admin_header', [ self::class, '_print_better_seo_js_script' ] );
		\add_action( 'admin_enqueue_scripts', [ self::class, '_prepare_admin_scripts' ], 1 );
		\add_action( 'admin_footer', [ self::class, '_output_templates' ], 999 );
	}

	/**
	 * Immediately prepares and outputs all registered admin scripts and templates.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function enqueue(): void {
		self::_prepare_admin_scripts();
		self::_output_templates();
	}

	/**
	 * Schedules script enqueue to run in the admin footer.
	 *
	 * Safe to call multiple times — uses has_run() to prevent duplicate scheduling.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function footer_enqueue(): void {

		if ( has_run( __METHOD__ ) ) {
			return;
		}

		// Priority 998: runs 1 before _output_templates at priority 999.
		\add_action( 'admin_footer', [ self::class, 'enqueue' ], 998 );
	}

	/**
	 * Adds Better SEO admin body classes.
	 *
	 * @since 1.0.0
	 *
	 * @param string $classes Space-separated list of existing CSS body classes.
	 * @return string Modified space-separated CSS body classes.
	 */
	public static function _add_body_class( string $classes ): string {

		$low_contrast = Data\Plugin::get_option( 'seo_bar_low_contrast' )
			? 'better-seo-seo-bar-low-contrast'
			: '';

		return " better-seo-no-js {$low_contrast} {$classes}";
	}

	/**
	 * Prints the Better SEO no-JS to JS transform script using ES2015.
	 *
	 * Replaces the better-seo-no-js body class with better-seo-js as soon
	 * as JavaScript is confirmed available.
	 *
	 * @since    1.0.0
	 * @internal Called via in_admin_header action.
	 *
	 * @return void
	 */
	public static function _print_better_seo_js_script(): void {
		echo "<script>(()=>{const a=0;document.body.classList.replace('better-seo-no-js','better-seo-js')})()</script>";
	}

	/**
	 * Forwards and autoloads all registered admin scripts.
	 *
	 * @since    1.0.0
	 * @internal Called via admin_enqueue_scripts action.
	 *
	 * @return void
	 */
	public static function _prepare_admin_scripts(): void {
		self::forward_known_scripts();
		self::autoload_known_scripts();
	}

	/**
	 * Returns the registration/load status bitmask for a given script.
	 *
	 * @since 1.0.0
	 * @see   self::REGISTERED
	 * @see   self::LOADED
	 *
	 * @param string $id   The script ID.
	 * @param string $type The script type: 'js' or 'css'.
	 * @return int Bitmask status value.
	 */
	public static function get_status_of( string $id, string $type ): int {
		return self::$queue[ $type ][ $id ] ?? 0b0;
	}

	/**
	 * Registers one or more script definitions into the internal store.
	 *
	 * Accepts either a single script definition array or a list of definitions.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|array<int, array<string, mixed>> $script Script definition or list of definitions.
	 * @return void
	 */
	public static function register( array $script ): void {
		// array_values() === $script check is ~350x faster than array_is_list() polyfill.
		if ( isset( $script[0] ) && array_values( $script ) === $script ) {
			foreach ( $script as $s ) {
				self::register( $s );
			}
			return;
		}

		self::$scripts[] = $script;
	}

	/**
	 * Forwards (registers with WordPress) a single known script by ID and type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id   The script ID.
	 * @param string $type The script type: 'js' or 'css'.
	 * @return void
	 */
	public static function forward_known_script( string $id, string $type ): void {
		if ( ! ( self::get_status_of( $id, $type ) & self::REGISTERED ) ) {
			foreach ( self::$scripts as $s ) {
				if ( $s['id'] === $id && $s['type'] === $type ) {
					self::forward_script( $s );
				}
			}
		}
	}

	/**
	 * Forwards and enqueues a single known script by ID and type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id   The script ID.
	 * @param string $type The script type: 'js' or 'css'.
	 * @return void
	 */
	public static function enqueue_known_script( string $id, string $type ): void {

		self::forward_known_script( $id, $type );

		$status = self::get_status_of( $id, $type );

		if ( ( $status & self::REGISTERED ) && ! ( $status & self::LOADED ) ) {
			self::load_script( $id, $type );
		}
	}

	/**
	 * Forwards all registered scripts to WordPress that have not yet been registered.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function forward_known_scripts(): void {
		foreach ( self::$scripts as $s ) {
			if ( self::get_status_of( $s['id'], $s['type'] ) & self::REGISTERED ) {
				continue;
			}
			self::forward_script( $s );
		}
	}

	/**
	 * Autoloads all registered scripts marked with autoload = true.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function autoload_known_scripts(): void {
		foreach ( self::$scripts as $s ) {
			if ( $s['autoload'] ) {
				if ( self::get_status_of( $s['id'], $s['type'] ) & self::LOADED ) {
					continue;
				}
				self::load_script( $s['id'], $s['type'] );
			}
		}
	}

	/**
	 * Registers a single script definition with WordPress.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $s The script definition array.
	 * @return void
	 */
	private static function forward_script( array $s ): void {

		$registered = false;

		match ( $s['type'] ) {
			'css' => static function() use ( $s, &$registered ): void {
				\wp_register_style( $s['id'], self::generate_file_url( $s, 'css' ), $s['deps'], $s['ver'], 'all' );

				if ( isset( $s['inline'] ) ) {
					\wp_add_inline_style( $s['id'], self::create_inline_css( $s['inline'] ) );
				}

				$registered = true;
			},
			'js' => static function() use ( $s, &$registered ): void {
				\wp_register_script( $s['id'], self::generate_file_url( $s, 'js' ), $s['deps'], $s['ver'], true );

				if ( isset( $s['l10n'] ) ) {
					\wp_localize_script( $s['id'], $s['l10n']['name'], $s['l10n']['data'] );
				}

				if ( isset( $s['tmpl'] ) ) {
					self::register_template( $s['id'], $s['tmpl'] );
				}

				if ( isset( $s['inline'] ) ) {
					\wp_add_inline_script( $s['id'], self::create_inline_js( $s['inline'] ) );
				}

				$registered = true;
			},
			default => static fn() => null,
		}();

		if ( $registered ) {
			if ( isset( self::$queue[ $s['type'] ][ $s['id'] ] ) ) {
				self::$queue[ $s['type'] ][ $s['id'] ] |= self::REGISTERED;
			} else {
				self::$queue[ $s['type'] ][ $s['id'] ] = self::REGISTERED;
			}
		}
	}

	/**
	 * Enqueues a previously registered script with WordPress.
	 *
	 * @since 1.0.0
	 *
	 * @param string $id   The script ID.
	 * @param string $type The script type: 'js' or 'css'.
	 * @return void
	 */
	private static function load_script( string $id, string $type ): void {

		if ( ! ( self::get_status_of( $id, $type ) & self::REGISTERED ) ) {
			return;
		}

		$loaded = match ( $type ) {
			'css' => static function() use ( $id ): bool {
				\wp_enqueue_style( $id );
				return true;
			},
			'js' => static function() use ( $id ): bool {
				\wp_enqueue_script( $id );
				return true;
			},
			default => static fn(): bool => false,
		}();

		if ( $loaded ) {
			if ( isset( self::$queue[ $type ][ $id ] ) ) {
				self::$queue[ $type ][ $id ] |= self::LOADED;
			} else {
				self::$queue[ $type ][ $id ] = self::LOADED;
			}
		}
	}

	/**
	 * Generates the full file URL for a script definition.
	 *
	 * Uses a static cache for the min/rtl suffixes to avoid repeated computation.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $script The script definition array.
	 * @param string               $type   The file type: 'js' or 'css'.
	 * @return string The full script file URL.
	 */
	private static function generate_file_url( array $script, string $type = 'js' ): string {

		static $min, $rtl;

		if ( ! isset( $min, $rtl ) ) {
			$min = \SCRIPT_DEBUG ? '' : '.min';
			$rtl = \is_rtl() ? '.rtl' : '';
		}

		$_rtl = ! empty( $script['hasrtl'] ) ? $rtl : '';

		return "{$script['base']}{$script['name']}{$_rtl}{$min}.{$type}";
	}

	/**
	 * Generates an inline CSS string from a selector-to-declaration map.
	 *
	 * Supports color token replacement via convert_color_css_declaration().
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<int, string>> $styles Map of CSS selectors to declaration arrays.
	 * @return string The generated inline CSS string.
	 */
	private static function create_inline_css( array $styles ): string {

		$out = '';

		foreach ( $styles as $selector => $declaration ) {
			$out .= \sprintf(
				'%s{%s}',
				$selector,
				implode( ';', self::convert_color_css_declaration( $declaration ) ),
			);
		}

		return $out;
	}

	/**
	 * Generates an inline JavaScript string from an array of script snippets.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $scripts Array of JavaScript snippets.
	 * @return string The concatenated inline JavaScript string.
	 */
	private static function create_inline_js( array $scripts ): string {

		$out = '';

		foreach ( $scripts as $script ) {
			$out .= ";{$script}";
		}

		return $out;
	}

	/**
	 * Replaces color token placeholders in CSS declarations with actual admin color values.
	 *
	 * Uses umemo() to cache the color conversion table across calls.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $css Array of CSS declaration strings with color tokens.
	 * @return array<int, string> CSS declarations with tokens replaced by actual color values.
	 */
	private static function convert_color_css_declaration( array $css ): array {

		$conversions = umemo( __METHOD__ . '/conversions' );

		if ( ! $conversions ) {
			$_scheme = \get_user_option( 'admin_color' ) ?: 'fresh';
			$_colors = $GLOBALS['_wp_admin_css_colors'];

			if (
				! \is_array( $_colors[ $_scheme ]->colors ?? null )
				|| \count( $_colors[ $_scheme ]->colors ) < 4
			) {
				// Unexpected color scheme — use safe fallback colors.
				$_colors = [
					'#1a1a2e',
					'#3d3d3d',
					'#1a1a2e',
					'#35C888',
				];
			} else {
				$_colors = $_colors[ $_scheme ]->colors;
			}

			$_conversion_table = [
				'{{$bg}}'               => $_colors[0],
				'{{$rel_bg}}'           => '#' . Format\Color::get_relative_fontcolor( $_colors[0] ),
				'{{$bg_accent}}'        => $_colors[1],
				'{{$rel_bg_accent}}'    => '#' . Format\Color::get_relative_fontcolor( $_colors[1] ),
				'{{$color}}'            => $_colors[2],
				'{{$rel_color}}'        => '#' . Format\Color::get_relative_fontcolor( $_colors[2] ),
				'{{$color_accent}}'     => $_colors[3],
				'{{$rel_color_accent}}' => '#' . Format\Color::get_relative_fontcolor( $_colors[3] ),
			];

			$conversions = umemo(
				__METHOD__ . '/conversions',
				[
					'search'  => array_keys( $_conversion_table ),
					'replace' => array_values( $_conversion_table ),
				],
			);
		}

		return str_replace( $conversions['search'], $conversions['replace'], $css );
	}

	/**
	 * Registers a template file for output when its associated script is enqueued.
	 *
	 * @since 1.0.0
	 *
	 * @param string                                        $id        The script ID to associate the template with.
	 * @param array<string, mixed>|array<int, array<string, mixed>> $templates Template definition or list of definitions.
	 * @return void
	 */
	private static function register_template( string $id, array $templates ): void {

		// Wrap single template definition in an array for uniform processing.
		if ( isset( $templates['file'] ) ) {
			$templates = [ $templates ];
		}

		foreach ( $templates as $t ) {
			self::$templates[ $id ][] = [
				$t['file'],
				$t['args'] ?? [],
			];
		}
	}

	/**
	 * Outputs all registered templates whose associated scripts are enqueued.
	 *
	 * @since    1.0.0
	 * @internal Called via admin_footer action at priority 999.
	 *
	 * @return void
	 */
	public static function _output_templates(): void {
		foreach ( self::$templates as $id => $templates ) {
			if ( \wp_script_is( $id, 'enqueued' ) ) {
				// Unset before the inner loop to prevent infinite recursion.
				unset( self::$templates[ $id ] );

				foreach ( $templates as $t ) {
					Template::output_absolute_view( $t[0], $t[1] );
				}
			}
		}
	}
}