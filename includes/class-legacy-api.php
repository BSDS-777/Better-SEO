<?php
/**
 * Better SEO - Legacy API
 *
 * @package    Better_SEO
 * @subpackage Better_SEO
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

namespace Better_SEO;

\defined( 'BETTER_SEO_PRESENT' ) or exit;

/**
 * Class Better_SEO\Legacy_API
 *
 * Base class for the Better_SEO\Pool facade.
 *
 * Provides backward-compatible accessor methods that proxy to the current
 * Better_SEO\Load singleton. This allows external code and extensions to
 * call methods on the Pool object and have them transparently forwarded
 * to the correct internal implementation, even as the internal API evolves.
 *
 * All public methods here are considered part of the external-facing API
 * surface. They should never be removed without a deprecation cycle.
 *
 * @since 1.0.0
 * @see   Better_SEO\Pool
 * @see   Better_SEO\Load
 * @link  https://en.wikipedia.org/wiki/Facade_pattern
 */
abstract class Legacy_API {

	/**
	 * Returns the Better SEO plugin version string.
	 *
	 * @since 1.0.0
	 * @api
	 *
	 * @return string The plugin version (e.g. '1.0.0').
	 */
	public static function version(): string {
		return \BETTER_SEO_VERSION;
	}

	/**
	 * Returns the Better SEO database version string.
	 *
	 * @since 1.0.0
	 * @api
	 *
	 * @return string The database version string (e.g. '1000').
	 */
	public static function db_version(): string {
		return (string) \get_option( 'better_seo_upgraded_db_version', '0' );
	}

	/**
	 * Returns the plugin's class name.
	 *
	 * Provided for backward compatibility with code that calls
	 * `Better_SEO()->get_class()` to inspect the active implementation.
	 *
	 * @since 1.0.0
	 * @api
	 *
	 * @return string The fully qualified class name of the Load singleton.
	 */
	public static function get_class(): string {
		return Load::class;
	}

	/**
	 * Returns whether the plugin is running in headless mode for the given type.
	 *
	 * Headless mode disables specific plugin output (meta, settings, or user fields)
	 * for use cases where the plugin is integrated into a headless WordPress setup.
	 *
	 * @since 1.0.0
	 * @api
	 *
	 * @param string|null $type The headless type to check: 'meta', 'settings', or 'user'.
	 *                          Pass null to return the full headless state array.
	 * @return bool|array<string, bool> True if headless for the given type, false otherwise.
	 *                                  Returns the full state array when $type is null.
	 */
	public static function is_headless( ?string $type = null ): bool|array {
		return is_headless( $type );
	}

	/**
	 * Returns the plugin's absolute filesystem directory path, with trailing slash.
	 *
	 * @since 1.0.0
	 * @api
	 *
	 * @return string The plugin directory path.
	 */
	public static function get_plugin_dir_path(): string {
		return \BETTER_SEO_DIR_PATH;
	}

	/**
	 * Returns the plugin's public URL, with trailing slash.
	 *
	 * @since 1.0.0
	 * @api
	 *
	 * @return string The plugin directory URL.
	 */
	public static function get_plugin_dir_url(): string {
		return \BETTER_SEO_DIR_URL;
	}

	/**
	 * Returns the plugin's basename (e.g. 'better-seo/better-seo.php').
	 *
	 * @since 1.0.0
	 * @api
	 *
	 * @return string The plugin basename.
	 */
	public static function get_plugin_basename(): string {
		return \BETTER_SEO_PLUGIN_BASENAME;
	}

	/**
	 * Returns the WordPress option name used for the plugin's site-wide settings.
	 *
	 * @since 1.0.0
	 * @api
	 *
	 * @return string The option name constant value.
	 */
	public static function get_options_name(): string {
		return \BETTER_SEO_SITE_OPTIONS;
	}

	/**
	 * Returns the WordPress capability required to access the plugin settings page.
	 *
	 * @since 1.0.0
	 * @api
	 *
	 * @return string The capability string.
	 */
	public static function get_settings_capability(): string {
		return \BETTER_SEO_SETTINGS_CAP;
	}
}