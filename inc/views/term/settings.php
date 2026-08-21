<?php
/**
 * Better SEO - View: Term Settings
 *
 * @package    Better_SEO
 * @subpackage Better_SEO\Views\Term
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

?>
<div class="better-seo-section-header">
	<h2><?php \esc_html_e( 'General SEO Settings', 'better-seo' ); ?></h2>
</div>

<table class="form-table better-seo-term-meta">
	<tbody>
		<?php
		if ( Data\Plugin::get_option( 'display_seo_bar_metabox' ) ) {
			?>
			<tr class="form-field">
				<th scope="row"><strong><?php \esc_html_e( 'SEO Status', 'better-seo' ); ?></strong></th>
				<td>
					<?php
					// phpcs:ignore WordPress.Security.EscapeOutput -- generate_bar() escapes.
					echo Admin\SEOToolbar\Builder::generate_bar( $generator_args );
					?>
				</td>
			</tr>
			<?php
		}
		?>
	</tbody>
</table>
