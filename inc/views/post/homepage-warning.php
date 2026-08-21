<?php
/**
 * Better SEO - View: Post Homepage Warning
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Views\Post
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

if ( ! \defined( 'BETTER_SEO_PRESENT' ) || ! Helper\Template::verify_secret( $secret ) ) {
	die;
}

// phpcs:disable WordPress.WP.GlobalVariablesOverride -- view file, not global scope.

?>
<div class="better-seo-flex-setting better-seo-flex" id="better-seo-is-homepage-warning">
	<div class="better-seo-flex-setting-input better-seo-flex">
		<div class="better-seo-flex-setting-input-inner-wrap better-seo-flex">
			<div class="better-seo-flex-setting-input-item better-seo-flex">
				<span>
					<?php
					\esc_html_e( 'Some fields on this page may be overridden by the Homepage SEO settings configured on the global SEO Settings page.', 'better-seo' );
					if ( \current_user_can( \BETTER_SEO_SETTINGS_CAP ) ) {
						echo ' &mdash; ';
						printf(
							'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
							// phpcs:ignore WordPress.Security.EscapeOutput -- menu_page_url() returns an escaped URL.
							\menu_page_url( \BETTER_SEO_SITE_OPTIONS_SLUG, false ) . '#better-seo-homepage-settings',
							\esc_html__( 'Manage those settings on the SEO Settings page.', 'better-seo' ),
						);
					}
					?>
				</span>
			</div>
		</div>
	</div>
</div>