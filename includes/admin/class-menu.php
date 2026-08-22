<?php
/**
 * Better SEO - Admin Menu
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

declare( strict_types=1 );

namespace Better_SEO\Admin;

\defined( 'BETTER_SEO_PRESENT' ) or exit;

use function Better_SEO\{
	memo,
	has_run,
	is_headless,
};

/**
 * Class Better_SEO\Admin\Menu
 *
 * Registers the Better SEO top-level admin menu page and its settings submenu.
 * Provides helpers for retrieving the menu page hook name and issue badge count.
 *
 * @since 1.0.0
 */
class Menu {

	/**
	 * Registers the Better SEO top-level menu page and settings submenu.
	 *
	 * Called on the 'admin_menu' hook from Better_SEO\Load.
	 * Uses has_run() to ensure registration only happens once per request.
	 *
	 * @hook  admin_menu 10
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public static function register_menu_pages(): void {

		if ( has_run( __METHOD__ ) ) {
			return;
		}

		$menu = self::get_top_menu_args();

		\add_menu_page(
			$menu['page_title'],
			$menu['menu_title'],
			$menu['capability'],
			$menu['menu_slug'],
			$menu['callback'],
			$menu['icon'],
			$menu['position'],
		);

		// Register the settings page as a submenu entry under itself,
		// so it appears as the first submenu item with the full page title.
		\add_submenu_page(
			$menu['menu_slug'],
			$menu['page_title'],
			$menu['page_title'],
			$menu['capability'],
			$menu['menu_slug'],
			$menu['callback'],
		);

		if ( \current_user_can( $menu['capability'] ) ) {
			\add_action(
				'load-' . self::get_page_hook_name(),
				[ Settings\Plugin::class, 'register_seo_settings_meta_boxes' ],
			);
		}
	}

	/**
	 * Returns the top-level menu page arguments array, memoized.
	 *
	 * Applies the better_seo_top_menu_args filter to allow customization
	 * of the menu page title, capability, slug, callback, icon, and position.
	 *
	 * @since 1.0.0
	 *
	 * @return array{
	 *     page_title: string,
	 *     menu_title: string,
	 *     capability: string,
	 *     menu_slug:  string,
	 *     callback:   callable,
	 *     icon:       string,
	 *     position:   string,
	 * } The menu page arguments.
	 */
	public static function get_top_menu_args(): array {

		// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition -- intentional memoization pattern.
		if ( null !== $memo = memo() ) {
			return $memo;
		}

		$issue_count = self::get_top_menu_issue_count();

		/**
		 * Filters the Better SEO top-level admin menu page arguments.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $args The menu page arguments.
		 */
		return memo( \apply_filters(
			'better_seo_top_menu_args',
			[
				'page_title' => \esc_html__( 'SEO Settings', 'better-seo' ),
				'menu_title' => \esc_html__( 'SEO', 'better-seo' )
					. ( $issue_count ? self::get_issue_badge( $issue_count ) : '' ),
				'capability' => \BETTER_SEO_SETTINGS_CAP,
				'menu_slug'  => \BETTER_SEO_SITE_OPTIONS_SLUG,
				'callback'   => [ Settings\Plugin::class, 'prepare_settings_wrap' ],
				'icon'       => 'dashicons-search',
				'position'   => '90.9001',
			],
		) );
	}

	/**
	 * Returns the WordPress page hook name for the Better SEO settings page
	 * or a given submenu slug, memoized per slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $submenu Optional submenu slug. Default '' (main settings page).
	 * @return string The page hook name (e.g. 'toplevel_page_better-seo-settings').
	 */
	public static function get_page_hook_name( string $submenu = '' ): string {

		static $names = [];

		if ( $submenu ) {
			return $names[ $submenu ] ??= \get_plugin_page_hookname(
				$submenu,
				self::get_top_menu_args()['menu_slug'],
			);
		}

		return $names[''] ??= \get_plugin_page_hookname(
			self::get_top_menu_args()['menu_slug'],
			'',
		);
	}

	/**
	 * Returns the number of SEO issues to display in the admin menu badge.
	 *
	 * Returns 0 when the plugin is running in headless settings mode.
	 * Applies the better_seo_top_menu_issue_count filter to allow extensions
	 * to increment the issue count.
	 *
	 * @since 1.0.0
	 *
	 * @return int The issue count. Always non-negative.
	 */
	public static function get_top_menu_issue_count(): int {

		if ( is_headless( 'settings' ) ) {
			return 0;
		}

		/**
		 * Filters the Better SEO top menu issue count.
		 *
		 * Do not overwrite this value — increment it instead.
		 *
		 * @since 1.0.0
		 * @param int $count The current issue count.
		 */
		return memo() ?? memo( \absint( \apply_filters( 'better_seo_top_menu_issue_count', 0 ) ) );
	}

	/**
	 * Returns the HTML badge markup for the admin menu issue count indicator.
	 *
	 * Renders a visually styled counter badge with a screen-reader-accessible
	 * label describing the number of issues waiting.
	 *
	 * @since 1.0.0
	 *
	 * @param int $issue_count The number of issues to display. Must be > 0.
	 * @return string The HTML badge string, prefixed with a space.
	 */
	public static function get_issue_badge( int $issue_count ): string {

		$notice_i18n = \number_format_i18n( $issue_count );

		return ' ' . \sprintf(
			'<span class="better-seo-menu-issue menu-counter count-%d"><span class="better-seo-menu-issue-text" aria-hidden="true">%s</span><span class="screen-reader-text">%s</span></span>',
			$issue_count,
			$notice_i18n,
			\sprintf(
				/* translators: %s: number of SEO issues waiting for review */
				\_n( '%s issue waiting', '%s issues waiting', $issue_count, 'better-seo' ),
				$notice_i18n,
			),
		);
	}
}