<?php
/**
 * Better SEO - Admin Plugin Table
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Admin
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

/**
 *Once you have a dedicated (better-seo.com domain) (BrianSmith.Design), update all placeholder URLs in this file to match your live site.
 *
 */

declare( strict_types=1 );

namespace Better_SEO\Admin;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use function Better_SEO\is_headless;

/**
 * Class Better_SEO\Admin\PluginTable
 *
 * Handles plugin action links and row meta displayed in the WordPress
 * Plugins admin table.
 *
 * @since 1.0.0
 */
final class PluginTable {

	/**
	 * Adds Better SEO action links to the plugin row in the Plugins table.
	 *
	 * Prepends Settings, Extensions, and Pricing links before the default links.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int|string, string> $links Existing plugin action links.
	 * @return array<int|string, string> Modified plugin action links.
	 */
	public static function add_plugin_action_links( array $links = [] ): array {

		$better_seo_links = [];

		if ( ! is_headless( 'settings' ) ) {
			$better_seo_links['settings'] = \sprintf(
				'<a href="%s">%s</a>',
				\esc_url( \admin_url( 'admin.php?page=' . \BETTER_SEO_SITE_OPTIONS_SLUG ) ),
				\esc_html__( 'Settings', 'better-seo' ),
			);
		}

		$better_seo_links['extensions'] = \sprintf(
			'<a href="%s" rel="noreferrer noopener" target="_blank">%s</a>',
			'https://better-seo.com/extensions/',
			\esc_html_x( 'Extensions', 'Plugin extensions', 'better-seo' ),
		);

		$better_seo_links['pricing'] = \sprintf(
			'<a href="%s" rel="noreferrer noopener" target="_blank">%s</a>',
			'https://better-seo.com/pricing/',
			\esc_html_x( 'Pricing', 'Plugin pricing', 'better-seo' ),
		);

		return array_merge( $better_seo_links, $links );
	}

	/**
	 * Adds Better SEO meta links to the plugin row in the Plugins table.
	 *
	 * Appends Support, Documentation, GitHub, and Extensions Manager links.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int|string, string> $plugin_meta Existing plugin row meta links.
	 * @param string                    $plugin_file Path to the plugin file relative to the plugins directory.
	 * @return array<int|string, string> Modified plugin row meta links.
	 */
	public static function add_plugin_row_meta( array $plugin_meta, string $plugin_file ): array {

		if ( \BETTER_SEO_PLUGIN_BASENAME !== $plugin_file ) {
			return $plugin_meta;
		}

		return array_merge(
			$plugin_meta,
			[
				'support'    => \sprintf(
					'<a href="%s" rel="noreferrer noopener nofollow" target="_blank">%s</a>',
					'https://better-seo.com/support/',
					\esc_html__( 'Support', 'better-seo' ),
				),
				'docs'       => \sprintf(
					'<a href="%s" rel="noreferrer noopener nofollow" target="_blank">%s</a>',
					'https://better-seo.com/docs/',
					\esc_html__( 'Documentation', 'better-seo' ),
				),
				'github'     => \sprintf(
					'<a href="%s" rel="noreferrer noopener nofollow" target="_blank">%s</a>',
					'https://github.com/BSDS-777/Better-SEO',
					\esc_html__( 'GitHub', 'better-seo' ),
				),
				'extensions' => \sprintf(
					'<a href="%s" rel="noreferrer noopener nofollow" target="_blank">%s</a>',
					'https://better-seo.com/extensions/',
					\esc_html__( 'Extensions', 'better-seo' ),
				),
			],
		);
	}
}