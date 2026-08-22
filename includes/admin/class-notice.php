<?php
/**
 * Better SEO - Admin Notice
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

\defined( 'BETTER_SEO_PRESENT' ) or die;

use Better_SEO\Admin;

/**
 * Class Better_SEO\Admin\Notice
 *
 * Provides simple admin notice output functionality.
 *
 * @since  1.0.0
 * @access protected
 *         Use better_seo()->admin()->notice() instead.
 */
class Notice {

	/**
	 * Outputs a generated admin notice directly to the page.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $message The notice message to display.
	 * @param array<string, mixed> $args    Optional. Notice configuration arguments.
	 * @return void
	 */
	public static function output_notice( string $message, array $args = [] ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput -- escaping is handled via $args['escape'] in generate_notice().
		echo self::generate_notice( $message, $args );
	}

	/**
	 * Generates and returns an admin notice HTML string.
	 *
	 * Supported $args keys:
	 *  - 'type'   (string) Notice type: 'updated', 'warning', 'info', 'error'. Default 'updated'.
	 *  - 'icon'   (bool)   Whether to show the notice icon. Default true.
	 *  - 'escape' (bool)   Whether to escape the message output. Default true.
	 *  - 'inline' (bool)   Whether to render the notice inline. Default false.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $message The notice message to display.
	 * @param array<string, mixed> $args    Optional. Notice configuration arguments.
	 * @return string The generated notice HTML.
	 */
	public static function generate_notice( string $message, array $args = [] ): string {

		if ( ! \wp_doing_ajax() ) {
			Admin\Script\Registry::register_scripts_and_hooks();
			Admin\Script\Registry::footer_enqueue();
		}

		$args += [
			'type'   => 'updated',
			'icon'   => true,
			'escape' => true,
			'inline' => false,
		];

		$args['type'] = match( $args['type'] ) {
			'warning', 'info' => "notice-{$args['type']}",
			default           => $args['type'],
		};

		return vsprintf(
			'<div class="notice %s better-seo-notice %s %s">%s%s</div>',
			[
				\esc_attr( $args['type'] ),
				$args['icon']   ? 'better-seo-show-icon' : '',
				$args['inline'] ? 'inline' : '',
				\sprintf(
					! $args['escape'] && 0 === stripos( $message, '<p' )
						? '%s'
						: '<p>%s</p>',
					$args['escape'] ? \esc_html( $message ) : $message,
				),
				\sprintf(
					'<a class="hide-if-no-better-seo-js better-seo-dismiss" href="javascript:;" title="%s"></a>',
					\esc_attr__( 'Dismiss this notice', 'better-seo' ),
				),
			],
		);
	}
}