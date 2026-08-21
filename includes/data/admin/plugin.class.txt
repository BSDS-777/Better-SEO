<?php
/**
 * Better SEO - Data Admin Plugin
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Data\Admin
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

namespace Better_SEO\Data\Admin;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\{
	Admin,
	Data,
	Helper\Query,
	Helper\Format\Arrays,
	Sitemap,
};

/**
 * Class Better_SEO\Data\Admin\Plugin
 *
 * Handles plugin settings registration, processing, and database version updates
 * for the Better SEO admin settings page.
 *
 * @since 1.0.0
 */
final class Plugin {

	/**
	 * Registers the Better SEO site options with WordPress settings API.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_settings(): void {
		\register_setting(
			\BETTER_SEO_SITE_OPTIONS,
			\BETTER_SEO_SITE_OPTIONS,
			[
				'type' => 'array',
			],
		);
	}

	/**
	 * Processes the settings form submission on the Better SEO settings page.
	 *
	 * Handles both reset and standard submission flows.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function process_settings_update(): void {

		if (
			empty( $_POST[ \BETTER_SEO_SITE_OPTIONS ] )
			|| ! \is_array( $_POST[ \BETTER_SEO_SITE_OPTIONS ] )
		) {
			return;
		}

		// Also handled in /wp-admin/options.php — guard here for out-of-scope registrations.
		if ( ! \current_user_can( \BETTER_SEO_SETTINGS_CAP ) ) {
			return;
		}

		// Also handled in /wp-admin/options.php — guard here for out-of-scope registrations.
		\check_admin_referer( \BETTER_SEO_SITE_OPTIONS . '-options', '_wpnonce' );

		if ( ! empty( $_POST[ \BETTER_SEO_SITE_OPTIONS ]['better-seo-settings-reset'] ) ) {
			self::process_settings_reset();
		} else {
			self::process_settings_submission();
		}
	}

	/**
	 * Processes a settings reset request.
	 *
	 * Resets options to defaults, refreshes sitemaps, clears caches,
	 * and redirects back to the settings page.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function process_settings_reset(): void {

		if ( Arrays::array_diff_assoc_recursive( Data\Plugin::get_options(), Data\Plugin\Setup::get_default_options() ) ) {
			// Settings differ from defaults — attempt reset.
			$state = Data\Plugin\Setup::reset_options() ? 'reset' : 'error';
		} else {
			// Settings already match defaults — reset anyway but report unchanged.
			Data\Plugin\Setup::reset_options();
			$state = 'unchanged';
		}

		Sitemap\Registry::refresh_sitemaps();
		Query\Exclusion::clear_excluded_post_ids_cache();

		Data\Plugin::update_site_cache( 'settings_notice', $state );

		// Redirect to flush all triggers after processing.
		Admin\Utils::redirect( \BETTER_SEO_SITE_OPTIONS_SLUG );
	}

	/**
	 * Processes a standard settings form submission.
	 *
	 * Clears caches, registers update hooks, and allows WordPress to save the options.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private static function process_settings_submission(): void {

		Sitemap\Cache::clear_sitemap_caches();
		Query\Exclusion::clear_excluded_post_ids_cache();

		\add_action(
			'update_option_' . \BETTER_SEO_SITE_OPTIONS,
			[ Data\Plugin::class, 'flush_cache' ],
			0
		);

		// Preemptively set settings_notice to unchanged — overwritten below if options actually change.
		Data\Plugin::update_site_cache( 'settings_notice', 'unchanged' );

		\add_action(
			'update_option_' . \BETTER_SEO_SITE_OPTIONS,
			[ self::class, 'set_option_updated_notice' ],
		);

		\add_action(
			'update_option_' . \BETTER_SEO_SITE_OPTIONS,
			[ self::class, 'update_db_version' ],
			12,
		);

		\add_action(
			'update_option_' . \BETTER_SEO_SITE_OPTIONS,
			[ Sitemap\Registry::class, 'refresh_sitemaps' ],
		);

		// Mitigate race condition — repopulate excluded post cache if options affecting it change.
		\add_action(
			'update_option_' . \BETTER_SEO_SITE_OPTIONS,
			[ Query\Exclusion::class, 'clear_excluded_post_ids_cache' ],
		);
	}

	/**
	 * Sets the settings notice to 'updated' after a successful option save.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function set_option_updated_notice(): void {
		Data\Plugin::update_site_cache( 'settings_notice', 'updated' );
	}

	/**
	 * Updates the stored Better SEO database version after a settings save.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function update_db_version(): void {
		\update_option( 'better_seo_upgraded_db_version', \BETTER_SEO_DB_VERSION, true );
	}

	/**
	 * Applies backward compatibility transformations to the options array before saving.
	 *
	 * Reserved for future database migration logic.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $options The options array being saved.
	 * @return array<string, mixed> The (potentially modified) options array.
	 */
	public static function set_backward_compatibility( array $options ): array {
		return $options;
	}
}