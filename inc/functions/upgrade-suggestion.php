<?php
/**
 * Better SEO - Upgrade Suggestion
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Bootstrap\Install
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

namespace Better_SEO\Suggestion;

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\{
	Admin,
	Helper\Format\Markdown,
};

// phpcs:disable VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- included file context.

// phpcs:ignore better-seo.Performance.Opcodes.ShouldHaveNamespaceEscape -- namespace escape not needed in included file context.
_prepare( $previous_version, $current_version );

/**
 * Prepares upgrade suggestion notices based on version transition.
 *
 * Skips if the version has not changed, if suggestions are disabled via
 * the BETTER_SEO_DISABLE_SUGGESTIONS constant, or if not on the main site.
 *
 * @since 1.0.0
 *
 * @param string $previous_version The previous plugin version string.
 * @param string $current_version  The current plugin version string.
 * @return void
 */
function _prepare( string $previous_version, string $current_version ): void {

	// 0 — No version change, nothing to suggest.
	// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- intentional loose comparison for version strings.
	if ( $previous_version == $current_version ) {
		return;
	}

	// 1 — Suggestions disabled via constant.
	if ( \defined( 'BETTER_SEO_DISABLE_SUGGESTIONS' ) && \BETTER_SEO_DISABLE_SUGGESTIONS ) {
		return;
	}

	// 2 — Only show suggestions on the main site.
	if ( ! \is_main_site() ) {
		return;
	}

	$show_sale = true;

	if (
		\function_exists( 'better_seo_extension_manager' )
		&& method_exists( \better_seo_extension_manager(), 'is_connected_user' )
	) {
		$show_sale = ! \better_seo_extension_manager()->is_connected_user();
	}

	if ( $show_sale ) {
		// phpcs:ignore better-seo.Performance.Opcodes.ShouldHaveNamespaceEscape -- namespace escape not needed in included file context.
		_suggest_temp_sale( $previous_version, $current_version );
	}
}

/**
 * Registers a temporary sale/welcome notice for eligible version upgrades.
 *
 * Displays a persistent admin notice when upgrading from a pre-release version
 * to v1.0.0, with a configurable timeout and screen exclusion list.
 *
 * @since 1.0.0
 *
 * @param string $previous_version The previous plugin version string.
 * @param string $current_version  The current plugin version string.
 * @return void
 */
function _suggest_temp_sale( string $previous_version, string $current_version ): void {

	if ( $previous_version < '0000' && $current_version < '1000' ) {
		Admin\Notice\Persistent::register_notice(
			Markdown::convert(
				\sprintf(
					'<p>Thank you for installing Better SEO v1.0.0</p><p></p>',
					'',
					'https://briansmith.design',
				),
				[ 'a' ],
				[ 'a_internal' => false ],
			),
			'suggest-sale',
			[
				'type'   => 'info',
				'icon'   => false,
				'escape' => false,
			],
			[
				'screens'      => [],
				'excl_screens' => [
					'update-core',
					'post',
					'term',
					'upload',
					'media',
					'plugin-editor',
					'plugin-install',
					'themes',
					'widgets',
					'user',
					'nav-menus',
					'theme-editor',
					'profile',
					'export',
					'site-health',
					'export-personal-data',
					'erase-personal-data',
				],
				'capability'   => 'install_plugins',
				'user'         => 0,
				'count'        => 1,
				'timeout'      => strtotime( 'January 1st, 2028, 23:00EST+1' ) - time(),
			],
		);
	}
}