<?php
/**
 * Better SEO - Admin Utils
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

use Better_SEO\Helper\Format\Markdown;

/**
 * Class Better_SEO\Admin\Utils
 *
 * Provides shared admin utility methods for redirects and
 * extension suggestion visibility.
 *
 * @since 1.0.0
 */
class Utils {

	/**
	 * Redirects the user to a given admin page hook with optional query arguments.
	 *
	 * Handles the edge case where headers have already been sent by outputting
	 * a fallback HTML redirect link instead of a silent white screen.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $page_hook  The admin page hook to redirect to.
	 * @param array<string, string> $query_args Optional. Query arguments to append to the URL.
	 * @return void
	 */
	public static function redirect( string $page_hook, array $query_args = [] ): void {

		if ( empty( $page_hook ) ) {
			return;
		}

		// menu_page_url() always uses esc_url() for display, breaking ampersands. Undo that via html_entity_decode().
		$url = html_entity_decode( \menu_page_url( $page_hook, false ) );

		if ( ! $url ) {
			\wp_die(
				\esc_html__( 'Redirect error: Page not found.', 'better-seo' ),
				\esc_html__( 'Redirect Error', 'better-seo' ),
				[ 'response' => 404 ],
			);
		}

		$target = \sanitize_url(
			\add_query_arg(
				array_filter( $query_args, static fn( string $value ): bool => strlen( $value ) > 0 ),
				$url,
			),
			[ 'https', 'http' ],
		);

		// Predict white screen before redirect fires.
		$headers_sent = headers_sent();

		\wp_safe_redirect( $target, 302 );

		// White screen of death for non-debugging users. Render a fallback link instead.
		if ( $headers_sent && $target ) {

			// Bail if WordPress's redirect header was already sent successfully.
			if ( \in_array(
				'Location: ' . \wp_sanitize_redirect( $target ),
				headers_list(),
				true,
			) ) {
				exit;
			}

			// phpcs:ignore WordPress.Security.EscapeOutput -- Markdown::convert() escapes output. esc_url() applied to URL argument.
			printf(
				'<p><strong>%s</strong></p>',
				Markdown::convert(
					\sprintf(
						/* translators: %s = Redirect URL in markdown link format */
						\esc_html__( 'There has been an error redirecting. Refresh the page or follow [this link](%s).', 'better-seo' ),
						\esc_url( $target ),
					),
					[ 'a' ],
					[ 'a_internal' => true ],
				),
			);
		}

		exit;
	}

	/**
	 * Determines whether extension suggestions should be displayed to the current user.
	 *
	 * Returns true if the current user can install plugins and the
	 * BETTER_SEO_DISABLE_SUGGESTIONS constant is not set to true.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if extension suggestions should be shown, false otherwise.
	 */
	public static function display_extension_suggestions(): bool {
		return \current_user_can( 'install_plugins' )
			&& ! ( \defined( 'BETTER_SEO_DISABLE_SUGGESTIONS' ) && \BETTER_SEO_DISABLE_SUGGESTIONS );
	}
}