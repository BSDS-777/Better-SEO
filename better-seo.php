<?php
/**
 * Better SEO
 *
 * @package           Better_SEO
 * @author            Brian Smith
 * @copyright         2026 Brian Smith
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Better SEO
 * Plugin URI:        https://briansmith.design/better-seo
 * Description:       A modern, lightweight WordPress SEO plugin featuring meta optimization, XML sitemaps, schema markup, Open Graph, and performance-focused SEO tools. Built for WordPress 7.0+, PHP 8.3, and Divi 5.
 * Version:           1.0.0
 * Requires at least: 7.0
 * Requires PHP:      8.1
 * Author:            Brian Smith
 * Author URI:        https://briansmith.design
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       better-seo
 * Domain Path:       /languages
 * Network:           false
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

// Prevent direct file access.
\defined( 'ABSPATH' ) or exit; // Access denied — direct file access is not permitted.

// ─── VERSION GUARD ─────────────────────────────────────────────────────────────

/**
 * Enforces minimum PHP and WordPress version requirements before loading.
 *
 * Displays an admin notice and deactivates the plugin if requirements are not met.
 * Uses a closure to avoid polluting the global namespace.
 */
( static function (): void {

	$php_min = '8.1';
	$wp_min  = '7.0';

	if ( version_compare( PHP_VERSION, $php_min, '<' ) ) {
		add_action(
			'admin_notices',
			static function () use ( $php_min ): void {
				printf(
					'<div class="notice notice-error"><p><strong>Better SEO</strong> requires PHP %s or higher. Your server is running PHP %s. Please upgrade PHP or contact your host.</p></div>',
					esc_html( $php_min ),
					esc_html( PHP_VERSION ),
				);
			},
		);
		// Deactivate gracefully.
		add_action(
			'admin_init',
			static function (): void {
				deactivate_plugins( plugin_basename( __FILE__ ) );
			},
		);
		return;
	}

	global $wp_version;
	if ( isset( $wp_version ) && version_compare( $wp_version, $wp_min, '<' ) ) {
		add_action(
			'admin_notices',
			static function () use ( $wp_min ): void {
				printf(
					'<div class="notice notice-error"><p><strong>Better SEO</strong> requires WordPress %s or higher. Please update WordPress.</p></div>',
					esc_html( $wp_min ),
				);
			},
		);
		add_action(
			'admin_init',
			static function (): void {
				deactivate_plugins( plugin_basename( __FILE__ ) );
			},
		);
		return;
	}

} )();

// Bail early if version requirements were not met (deactivation is pending).
if ( ! \defined( 'ABSPATH' ) ) {
	return;
}

// ─── CONSTANTS ─────────────────────────────────────────────────────────────────

/**
 * Signals that Better SEO is loaded and active.
 * All plugin files check for this constant before executing.
 *
 * @since 1.0.0
 * @var   bool
 */
\define( 'BETTER_SEO_PRESENT', true );

/**
 * Plugin version string.
 *
 * @since 1.0.0
 * @var   string
 */
\define( 'BETTER_SEO_VERSION', '1.0.0' );

/**
 * Database schema version. Incremented when the DB structure changes.
 *
 * @since 1.0.0
 * @var   string
 */
\define( 'BETTER_SEO_DB_VERSION', '1000' );

/**
 * WordPress option name for the plugin's site-wide settings array.
 * Must match the value used in class-input.php and settings.js (_getSettingsId).
 *
 * @since 1.0.0
 * @var   string
 */
\define( 'BETTER_SEO_SITE_OPTIONS', 'better-seo-site-settings' );

/**
 * WordPress option name for the plugin's site-wide cache array.
 *
 * @since 1.0.0
 * @var   string
 */
\define( 'BETTER_SEO_SITE_CACHE', 'better-seo-site-cache' );

/**
 * WordPress option name for the plugin's site-wide options slug (settings page).
 *
 * @since 1.0.0
 * @var   string
 */
\define( 'BETTER_SEO_SITE_OPTIONS_SLUG', 'better-seo-settings' );

/**
 * WordPress capability required to access the plugin settings page.
 *
 * @since 1.0.0
 * @var   string
 */
\define( 'BETTER_SEO_SETTINGS_CAP', 'manage_options' );

/**
 * WordPress capability required to edit author SEO info fields.
 *
 * @since 1.0.0
 * @var   string
 */
\define( 'BETTER_SEO_AUTHOR_INFO_CAP', 'better_seo_author_info' );

/**
 * Absolute filesystem path to the plugin directory, with trailing slash.
 *
 * @since 1.0.0
 * @var   string
 */
\define( 'BETTER_SEO_DIR_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Public URL to the plugin directory, with trailing slash.
 *
 * @since 1.0.0
 * @var   string
 */
\define( 'BETTER_SEO_DIR_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename (e.g. 'better-seo/better-seo.php').
 * Used for plugin_action_links and deactivation hooks.
 *
 * @since 1.0.0
 * @var   string
 */
\define( 'BETTER_SEO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// ─── AUTOLOADER ────────────────────────────────────────────────────────────────

/**
 * Load the Composer-generated PSR-4 autoloader.
 *
 * Maps Better_SEO\ → includes/ and loads global function files:
 *   - inc/functions/api.php       (Better_SEO() / BetterSeo() global functions)
 *   - inc/functions/deprecated.php (deprecated function stubs)
 *
 * Falls back to a manual autoloader if vendor/autoload.php is not present
 * (e.g. during development without running `composer install`).
 */
if ( file_exists( \BETTER_SEO_DIR_PATH . 'vendor/autoload.php' ) ) {
	require_once \BETTER_SEO_DIR_PATH . 'vendor/autoload.php';
} else {
	// Manual PSR-4 fallback autoloader.
	spl_autoload_register(
		static function ( string $class ): void {
			$prefix = 'Better_SEO\\';
			$len    = \strlen( $prefix );

			if ( strncmp( $prefix, $class, $len ) !== 0 ) {
				return;
			}

			$relative_class = substr( $class, $len );

			// Convert namespace separators and underscores to directory separators.
			// PSR-4: Better_SEO\Admin\Script\Loader → includes/admin/script/class-loader.php
			$parts     = explode( '\\', $relative_class );
			$class_name = array_pop( $parts );

			// Convert PascalCase class name to kebab-case file name.
			$file_name = 'class-' . strtolower(
				preg_replace( '/([A-Z])/', '-$1', lcfirst( $class_name ) ),
			) . '.php';

			// Convert namespace parts to lowercase directory names.
			$dir_parts = array_map(
				static fn( string $part ): string => strtolower(
					preg_replace( '/([A-Z])/', '-$1', lcfirst( $part ) ),
				),
				$parts,
			);

			$file = \BETTER_SEO_DIR_PATH . 'includes/' . implode( '/', $dir_parts )
				. ( $dir_parts ? '/' : '' ) . $file_name;

			if ( file_exists( $file ) ) {
				require_once $file;
			}
		},
	);

	// Load global function files manually when Composer is not available.
	require_once \BETTER_SEO_DIR_PATH . 'inc/functions/api.php';
	require_once \BETTER_SEO_DIR_PATH . 'inc/functions/deprecated.php';
}

// ─── ACTIVATION / DEACTIVATION HOOKS ──────────────────────────────────────────

/**
 * Runs on plugin activation.
 *
 * Sets up default options, flushes rewrite rules, and schedules cron events.
 *
 * @since 1.0.0
 */
register_activation_hook(
	__FILE__,
	static function (): void {
		// Set default options if not already present.
		if ( ! \get_option( \BETTER_SEO_SITE_OPTIONS ) ) {
			Better_SEO\Data\Plugin\Setup::reset_options();
		}

		// Schedule the sitemap prerender cron event.
		Better_SEO\Sitemap\Cron::schedule_single_event();

		// Flush rewrite rules so sitemap endpoints are registered.
		\flush_rewrite_rules();

		// Store the version at activation time for upgrade detection.
		\update_option( 'better_seo_activated_version', \BETTER_SEO_VERSION, false );
	},
);

/**
 * Runs on plugin deactivation.
 *
 * Clears scheduled cron events and flushes rewrite rules.
 * Does NOT delete options or data — that is handled by uninstall.php.
 *
 * @since 1.0.0
 */
register_deactivation_hook(
	__FILE__,
	static function (): void {
		// Remove scheduled cron events.
		\wp_clear_scheduled_hook( 'better_seo_sitemap_cron' );

		// Flush rewrite rules to remove sitemap endpoints.
		\flush_rewrite_rules();
	},
);

// ─── UPGRADE DETECTION ─────────────────────────────────────────────────────────

/**
 * Detects version upgrades and runs upgrade routines or suggestion notices.
 *
 * Compares the stored DB version against the current version and fires
 * the better_seo_upgrade action for any registered upgrade handlers.
 *
 * @since 1.0.0
 */
add_action(
	'init',
	static function (): void {
		$previous_version = \get_option( 'better_seo_upgraded_db_version', '0' );
		$current_version  = \BETTER_SEO_DB_VERSION;

		if ( $previous_version !== $current_version ) {
			/**
			 * Fires when Better SEO detects a version upgrade.
			 *
			 * @since 1.0.0
			 *
			 * @param string $previous_version The previous DB version string.
			 * @param string $current_version  The current DB version string.
			 */
			\do_action( 'better_seo_upgrade', $previous_version, $current_version );

			\update_option( 'better_seo_upgraded_db_version', $current_version, false );
		}
	},
	PHP_INT_MIN,
);

/**
 * Loads the upgrade suggestion notice on version upgrade.
 *
 * @since 1.0.0
 */
add_action(
	'better_seo_upgrade',
	static function ( string $previous_version, string $current_version ): void {
		if ( \is_admin() ) {
			require_once \BETTER_SEO_DIR_PATH . 'inc/functions/upgrade-suggestion.php';
		}
	},
	10,
	2,
);

// ─── COMPATIBILITY FILES ────────────────────────────────────────────────────────

/**
 * Loads third-party plugin and theme compatibility files.
 *
 * Each file is loaded only when the relevant plugin or theme is active,
 * using WordPress's active plugin/theme detection.
 *
 * @since 1.0.0
 */
add_action(
	'plugins_loaded',
	static function (): void {

		$active_plugins = Better_SEO\Data\Blog::get_active_plugins();
		$active_themes  = Better_SEO\Data\Blog::get_active_themes();

		$compat_dir = \BETTER_SEO_DIR_PATH . 'inc/compat/';

		// Plugin compatibility files — keyed by plugin basename.
		$plugin_compat = [
			'bbpress/bbpress.php'                          => 'plugin-bbpress.php',
			'buddypress/bp-loader.php'                     => 'plugin-buddypress.php',
			'easy-digital-downloads/easy-digital-downloads.php' => 'plugin-edd.php',
			'elementor/elementor.php'                      => 'plugin-elementor.php',
			'jetpack/jetpack.php'                          => 'plugin-jetpack.php',
			'polylang/polylang.php'                        => 'plugin-polylang.php',
			'polylang-pro/polylang.php'                    => 'plugin-polylang.php',
			'ultimate-member/ultimate-member.php'          => 'plugin-ultimatemember.php',
			'woocommerce/woocommerce.php'                  => 'plugin-woocommerce.php',
			'wpforo/wpforo.php'                            => 'plugin-wpforo.php',
			'sitepress-multilingual-cms/sitepress.php'     => 'plugin-wpml.php',
		];

		foreach ( $plugin_compat as $plugin_basename => $compat_file ) {
			if ( \in_array( $plugin_basename, $active_plugins, true ) ) {
				require_once $compat_dir . $compat_file;
			}
		}

		// Theme compatibility files — keyed by theme slug.
		$theme_compat = [
			'avada'   => 'theme-avada.php',
			'bricks'  => 'theme-bricks.php',
			'genesis' => 'theme-genesis.php',
		];

		foreach ( $theme_compat as $theme_slug => $compat_file ) {
			if ( \in_array( $theme_slug, $active_themes, true ) ) {
				require_once $compat_dir . $compat_file;
			}
		}
	},
	0,
);

// ─── BOOTSTRAP ─────────────────────────────────────────────────────────────────

/**
 * Bootstraps the Better SEO plugin.
 *
 * Initialises the main Load class instance, which registers all hooks,
 * filters, and admin/front-end components.
 *
 * @since 1.0.0
 */
add_action(
	'plugins_loaded',
	static function (): void {
		Better_SEO\Load::get_instance();
	},
	5,
);

/**
 * Loads the plugin text domain for translations.
 *
 * @since 1.0.0
 */
add_action(
	'init',
	static function (): void {
		\load_plugin_textdomain(
			'better-seo',
			false,
			\BETTER_SEO_DIR_PATH . 'languages',
		);
	},
);